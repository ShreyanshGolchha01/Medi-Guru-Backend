<?php
// CORS headers - MUST BE FIRST
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
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

// Check if request method is GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

// Get and validate token
$token = getAuthHeader();
$decoded = validateToken($token);

// Get meeting ID from query parameter
$meetingId = $_GET['meetingId'] ?? '';

if (!$meetingId) {
    sendError('Meeting ID is required', 400);
}

try {
    // Check upload status for this meeting
    $stmt = $pdo->prepare("
        SELECT 
            pre_url,
            attend_url,
            post_url,
            registered_url,
            created_at
        FROM files 
        WHERE m_id = ?
    ");
    $stmt->execute([$meetingId]);
    $fileRecord = $stmt->fetch();
    
    // Also check actual data counts
    $registeredStmt = $pdo->prepare("SELECT COUNT(*) as count FROM registered WHERE m_id = ?");
    $registeredStmt->execute([$meetingId]);
    $registeredCount = $registeredStmt->fetch()['count'];
    
    $attendanceStmt = $pdo->prepare("SELECT COUNT(*) as count FROM meeting_attendance WHERE meeting_id = ?");
    $attendanceStmt->execute([$meetingId]);
    $attendanceCount = $attendanceStmt->fetch()['count'];
    
    $pretestStmt = $pdo->prepare("SELECT COUNT(*) as count FROM pretest_results WHERE meeting_id = ?");
    $pretestStmt->execute([$meetingId]);
    $pretestCount = $pretestStmt->fetch()['count'];
    
    $posttestStmt = $pdo->prepare("SELECT COUNT(*) as count FROM posttest_results WHERE meeting_id = ?");
    $posttestStmt->execute([$meetingId]);
    $posttestCount = $posttestStmt->fetch()['count'];
    
    // Determine status for each file type
    $status = [
        'registeredParticipants' => $registeredCount > 0 ? 'uploaded' : 'pending',
        'preTest' => $pretestCount > 0 ? 'uploaded' : 'not-required',
        'attendance' => $attendanceCount > 0 ? 'uploaded' : 'pending',
        'postTest' => $posttestCount > 0 ? 'uploaded' : 'not-required'
    ];
    
    // Add file information if available
    $fileInfo = [];
    if ($fileRecord) {
        if ($fileRecord['registered_url']) {
            $fileInfo['registered'] = [
                'filename' => $fileRecord['registered_url'],
                'uploaded_at' => $fileRecord['created_at'],
                'record_count' => $registeredCount
            ];
        }
        if ($fileRecord['pre_url']) {
            $fileInfo['pretest'] = [
                'filename' => $fileRecord['pre_url'],
                'uploaded_at' => $fileRecord['created_at'],
                'record_count' => $pretestCount
            ];
        }
        if ($fileRecord['attend_url']) {
            $fileInfo['attendance'] = [
                'filename' => $fileRecord['attend_url'],
                'uploaded_at' => $fileRecord['created_at'],
                'record_count' => $attendanceCount
            ];
        }
        if ($fileRecord['post_url']) {
            $fileInfo['posttest'] = [
                'filename' => $fileRecord['post_url'],
                'uploaded_at' => $fileRecord['created_at'],
                'record_count' => $posttestCount
            ];
        }
    }
    
    sendResponse([
        'success' => true,
        'meeting_id' => $meetingId,
        'upload_status' => $status,
        'file_info' => $fileInfo,
        'summary' => [
            'registered_count' => $registeredCount,
            'attendance_count' => $attendanceCount,
            'pretest_count' => $pretestCount,
            'posttest_count' => $posttestCount
        ]
    ]);

} catch (Exception $e) {
    sendError('Error fetching upload status: ' . $e->getMessage(), 500);
}
?>
