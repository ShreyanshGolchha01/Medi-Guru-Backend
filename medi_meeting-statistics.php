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
$type = $_GET['type'] ?? ''; // pretest, posttest, attendance

if (!$meetingId) {
    sendError('Meeting ID is required', 400);
}

if (!in_array($type, ['registered', 'pretest', 'posttest', 'attendance'])) {
    sendError('Invalid type. Must be registered, pretest, posttest, or attendance', 400);
}

try {
    switch ($type) {
        case 'registered':
            $stmt = $pdo->prepare("
                SELECT 
                    name,
                    designation,
                    block,
                    phone
                FROM registered 
                WHERE m_id = ?
                ORDER BY name ASC
            ");
            $stmt->execute([$meetingId]);
            $data = $stmt->fetchAll();
            
            // Format data for frontend
            $formattedData = array_map(function($row) {
                return [
                    'name' => $row['name'],
                    'department' => $row['designation'], // Using designation as department
                    'designation' => $row['designation'],
                    'block' => $row['block'],
                    'phone' => $row['phone'],
                    'status' => 'registered' // Default status
                ];
            }, $data);
            
            // Calculate statistics
            $totalRegistered = count($formattedData);
            
            $response = [
                'success' => true,
                'type' => 'registered',
                'meeting_id' => $meetingId,
                'data' => $formattedData,
                'statistics' => [
                    'total_registered' => $totalRegistered
                ]
            ];
            break;
            
        case 'pretest':
            $stmt = $pdo->prepare("
                SELECT 
                    name,
                    department,
                    score,
                    total_marks,
                    ROUND((score / total_marks) * 100, 1) as percentage,
                    recorded_at
                FROM pretest_results 
                WHERE meeting_id = ?
                ORDER BY name ASC
            ");
            $stmt->execute([$meetingId]);
            $results = $stmt->fetchAll();
            
            // Calculate statistics
            $totalParticipants = count($results);
            $totalScore = array_sum(array_column($results, 'percentage'));
            $averageScore = $totalParticipants > 0 ? round($totalScore / $totalParticipants, 1) : 0;
            
            $response = [
                'success' => true,
                'type' => 'pretest',
                'meeting_id' => $meetingId,
                'data' => $results,
                'statistics' => [
                    'total_participants' => $totalParticipants,
                    'average_score' => $averageScore,
                    'highest_score' => $totalParticipants > 0 ? max(array_column($results, 'percentage')) : 0,
                    'lowest_score' => $totalParticipants > 0 ? min(array_column($results, 'percentage')) : 0
                ]
            ];
            break;
            
        case 'posttest':
            $stmt = $pdo->prepare("
                SELECT 
                    name,
                    department,
                    score,
                    total_marks,
                    ROUND((score / total_marks) * 100, 1) as percentage,
                    recorded_at
                FROM posttest_results 
                WHERE meeting_id = ?
                ORDER BY name ASC
            ");
            $stmt->execute([$meetingId]);
            $results = $stmt->fetchAll();
            
            // Calculate statistics
            $totalParticipants = count($results);
            $totalScore = array_sum(array_column($results, 'percentage'));
            $averageScore = $totalParticipants > 0 ? round($totalScore / $totalParticipants, 1) : 0;
            
            $response = [
                'success' => true,
                'type' => 'posttest',
                'meeting_id' => $meetingId,
                'data' => $results,
                'statistics' => [
                    'total_participants' => $totalParticipants,
                    'average_score' => $averageScore,
                    'highest_score' => $totalParticipants > 0 ? max(array_column($results, 'percentage')) : 0,
                    'lowest_score' => $totalParticipants > 0 ? min(array_column($results, 'percentage')) : 0
                ]
            ];
            break;
            
        case 'attendance':
            $stmt = $pdo->prepare("
                SELECT 
                    participant_name as name,
                    login_time,
                    attended_time,
                    recorded_at
                FROM meeting_attendance 
                WHERE meeting_id = ?
                ORDER BY participant_name ASC
            ");
            $stmt->execute([$meetingId]);
            $results = $stmt->fetchAll();
            
            // Calculate statistics
            $totalAttendees = count($results);
            
            // For now, we'll use actual attendees as expected attendees
            // In future, this can be enhanced to use a separate field
            $expectedAttendees = $totalAttendees > 0 ? $totalAttendees : 1;
            
            $attendanceRate = $expectedAttendees > 0 ? round(($totalAttendees / $expectedAttendees) * 100, 1) : 100;
            
            $response = [
                'success' => true,
                'type' => 'attendance',
                'meeting_id' => $meetingId,
                'data' => $results,
                'statistics' => [
                    'total_attendees' => $totalAttendees,
                    'expected_attendees' => $expectedAttendees,
                    'attendance_rate' => $attendanceRate
                ]
            ];
            break;
    }
    
    sendResponse($response);

} catch (Exception $e) {
    sendError('Error fetching statistics: ' . $e->getMessage(), 500);
}
?>
