<?php
// CORS headers - MUST BE FIRST
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

require_once 'medi_database.php';
require_once 'medi_helpers.php';

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Method not allowed', 405);
}

// Get and validate token
$token = getAuthHeader();
$decoded = validateToken($token);

// Get JSON input (parsed data from frontend)
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$meetingId = $input['meetingId'] ?? '';
$fileType = $input['type'] ?? '';
$data = $input['data'] ?? [];
$fileName = $input['fileName'] ?? '';

if (!$meetingId || !$fileType || empty($data)) {
    sendError('Invalid parameters. meetingId, type, and data are required.', 400);
}

if (!in_array($fileType, ['pretest', 'attendance', 'posttest', 'registered'])) {
    sendError('Invalid file type. Must be pretest, attendance, posttest, or registered.', 400);
}

try {
    $pdo->beginTransaction();
    
    // Create uploads directory if not exists
    $uploadsDir = 'medi_uploads/';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }
    
    // Generate file path for storing original file info
    $timestamp = time();
    $userId = $decoded['user_id'];
    $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
    $storedFileName = "{$fileType}_{$meetingId}_{$userId}_{$timestamp}.{$fileExtension}";
    $filePath = $uploadsDir . $storedFileName;
    
    // Save the original data to file for backup/reference
    $backupData = [
        'originalFileName' => $fileName,
        'uploadedAt' => date('Y-m-d H:i:s'),
        'uploadedBy' => $userId,
        'meetingId' => $meetingId,
        'fileType' => $fileType,
        'data' => $data
    ];
    
    // Save as JSON file for data backup
    $jsonFileName = "{$fileType}_{$meetingId}_{$userId}_{$timestamp}.json";
    $jsonFilePath = $uploadsDir . $jsonFileName;
    file_put_contents($jsonFilePath, json_encode($backupData, JSON_PRETTY_PRINT));
    
    $processedCount = 0;
    $errors = [];
    
    // Process data based on file type
    switch ($fileType) {
        case 'registered':
            // Clear existing registered participants for this meeting
            $deleteStmt = $pdo->prepare("DELETE FROM registered WHERE m_id = ?");
            $deleteStmt->execute([$meetingId]);
            
            foreach ($data as $index => $row) {
                try {
                    $name = trim($row['Name'] ?? $row['name'] ?? $row['participant_name'] ?? '');
                    $designation = trim($row['Designation'] ?? $row['designation'] ?? $row['Department'] ?? $row['department'] ?? '');
                    $block = trim($row['Block'] ?? $row['block'] ?? $row['Location'] ?? $row['location'] ?? '');
                    $phone = trim($row['Phone'] ?? $row['phone'] ?? $row['mobile'] ?? $row['Mobile'] ?? '');
                    
                    if (empty($name)) {
                        $errors[] = "Row " . ($index + 2) . ": Name is required";
                        continue;
                    }
                    
                    if (empty($designation)) {
                        $errors[] = "Row " . ($index + 2) . ": Designation is required";
                        continue;
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO registered 
                        (m_id, name, designation, block, phone) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([$meetingId, $name, $designation, $block, $phone]);
                    $processedCount++;
                } catch (Exception $e) {
                    $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                }
            }
            
            // Update files table
            $updateField = 'registered_url';
            break;
            
        case 'attendance':
            // Clear existing attendance for this meeting
            $deleteStmt = $pdo->prepare("DELETE FROM meeting_attendance WHERE meeting_id = ?");
            $deleteStmt->execute([$meetingId]);
            
            foreach ($data as $index => $row) {
                try {
                    $name = trim($row['Name'] ?? $row['name'] ?? $row['participant_name'] ?? '');
                    $loginTime = trim($row['Login Time'] ?? $row['login_time'] ?? $row['Login_Time'] ?? '');
                    $attendedTime = trim($row['Duration'] ?? $row['duration'] ?? $row['attended_time'] ?? $row['Attended_Time'] ?? '');
                    
                    if (empty($name)) {
                        $errors[] = "Row " . ($index + 2) . ": Name is required";
                        continue;
                    }
                    
                    if (empty($loginTime)) {
                        $errors[] = "Row " . ($index + 2) . ": Login Time is required";
                        continue;
                    }
                    
                    if (empty($attendedTime)) {
                        $errors[] = "Row " . ($index + 2) . ": Duration is required";
                        continue;
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO meeting_attendance 
                        (meeting_id, participant_name, login_time, attended_time, uploaded_by, recorded_at) 
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $stmt->execute([$meetingId, $name, $loginTime, $attendedTime, $userId]);
                    $processedCount++;
                } catch (Exception $e) {
                    $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                }
            }
            
            // Update files table
            $updateField = 'attend_url';
            break;
            
        case 'pretest':
            // Clear existing pretest results for this meeting
            $deleteStmt = $pdo->prepare("DELETE FROM pretest_results WHERE meeting_id = ?");
            $deleteStmt->execute([$meetingId]);
            
            foreach ($data as $index => $row) {
                try {
                    $name = trim($row['Name'] ?? $row['name'] ?? '');
                    $department = trim($row['Department'] ?? $row['department'] ?? '');
                    $score = intval($row['Score'] ?? $row['score'] ?? 0);
                    
                    // Try to find total marks from Excel file, otherwise calculate from max score
                    $totalMarks = intval($row['Total Marks'] ?? $row['total_marks'] ?? $row['Total'] ?? 0);
                    
                    // If total marks not found in file, auto-detect based on score ranges
                    if ($totalMarks == 0) {
                        if ($score <= 20) {
                            $totalMarks = 20;
                        } elseif ($score <= 50) {
                            $totalMarks = 50;
                        } elseif ($score <= 100) {
                            $totalMarks = 100;
                        } else {
                            $totalMarks = $score; // Use score itself if very high
                        }
                    }
                    
                    if (empty($name)) {
                        $errors[] = "Row " . ($index + 2) . ": Name is required";
                        continue;
                    }
                    
                    if ($score < 0) {
                        $errors[] = "Row " . ($index + 2) . ": Score cannot be negative";
                        continue;
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO pretest_results 
                        (meeting_id, name, department, score, total_marks, uploaded_by, recorded_at) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $stmt->execute([$meetingId, $name, $department, $score, $totalMarks, $userId]);
                    $processedCount++;
                } catch (Exception $e) {
                    $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                }
            }
            
            $updateField = 'pre_url';
            break;
            
        case 'posttest':
            // Clear existing posttest results for this meeting
            $deleteStmt = $pdo->prepare("DELETE FROM posttest_results WHERE meeting_id = ?");
            $deleteStmt->execute([$meetingId]);
            
            foreach ($data as $index => $row) {
                try {
                    $name = trim($row['Name'] ?? $row['name'] ?? '');
                    $department = trim($row['Department'] ?? $row['department'] ?? '');
                    $score = intval($row['Score'] ?? $row['score'] ?? 0);
                    
                    // Try to find total marks from Excel file, otherwise calculate from max score
                    $totalMarks = intval($row['Total Marks'] ?? $row['total_marks'] ?? $row['Total'] ?? 0);
                    
                    // If total marks not found in file, auto-detect based on score ranges
                    if ($totalMarks == 0) {
                        if ($score <= 20) {
                            $totalMarks = 20;
                        } elseif ($score <= 50) {
                            $totalMarks = 50;
                        } elseif ($score <= 100) {
                            $totalMarks = 100;
                        } else {
                            $totalMarks = $score; // Use score itself if very high
                        }
                    }
                    
                    if (empty($name)) {
                        $errors[] = "Row " . ($index + 2) . ": Name is required";
                        continue;
                    }
                    
                    if ($score < 0) {
                        $errors[] = "Row " . ($index + 2) . ": Score cannot be negative";
                        continue;
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO posttest_results 
                        (meeting_id, name, department, score, total_marks, uploaded_by, recorded_at) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $stmt->execute([$meetingId, $name, $department, $score, $totalMarks, $userId]);
                    $processedCount++;
                } catch (Exception $e) {
                    $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
                }
            }
            
            $updateField = 'post_url';
            break;
    }
    
    // Update files table with JSON file path
    $fileStmt = $pdo->prepare("
        INSERT INTO files (m_id, {$updateField}, created_at) 
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
        {$updateField} = VALUES({$updateField}), 
        created_at = NOW()
    ");
    $fileStmt->execute([$meetingId, $jsonFileName]);
    
    $pdo->commit();
    
    // Response
    $response = [
        'success' => true,
        'message' => ucfirst($fileType) . ' data processed successfully',
        'processed_count' => $processedCount,
        'total_count' => count($data),
        'file_name' => $fileName,
        'saved_file' => $jsonFileName,
        'file_path' => $jsonFilePath,
        'errors' => $errors
    ];
    
    if (!empty($errors)) {
        $response['message'] .= ' with some errors';
        $response['warning'] = 'Some rows could not be processed. Check errors for details.';
    }
    
    sendResponse($response, 200);

} catch (Exception $e) {
    $pdo->rollback();
    sendError('Processing failed: ' . $e->getMessage(), 500);
}
?>
