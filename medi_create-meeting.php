<?php
// CORS headers - MUST BE FIRST
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Debug logging
error_log('Received payload: ' . json_encode($input));

// Validate required fields
$required_fields = ['name', 'date', 'time', 'topic', 'hosters'];
validateRequired($input, $required_fields);

$meetingName = trim($input['name']);
$meetingDate = trim($input['date']);
$meetingTime = trim($input['time']);
$meetingTopic = trim($input['topic']);
$meetingHost = trim($input['hosters']);

// Additional validation
if (strlen($meetingName) < 3) {
    sendError('Meeting name must be at least 3 characters long', 400);
}

if (strlen($meetingTopic) < 10) {
    sendError('Meeting topic must be at least 10 characters long', 400);
}

// Validate date format and ensure it's not in the past
$dateObj = DateTime::createFromFormat('Y-m-d', $meetingDate);
if (!$dateObj || $dateObj->format('Y-m-d') !== $meetingDate) {
    sendError('Invalid date format. Use YYYY-MM-DD', 400);
}

$today = new DateTime();
$today->setTime(0, 0, 0);
if ($dateObj < $today) {
    sendError('Meeting date cannot be in the past', 400);
}

// Validate time format
$timeObj = DateTime::createFromFormat('H:i', $meetingTime);
if (!$timeObj || $timeObj->format('H:i') !== $meetingTime) {
    sendError('Invalid time format. Use HH:MM', 400);
}

try {
    // Check if meeting already exists at the same date and time
    $checkStmt = $pdo->prepare("SELECT id FROM meetings WHERE date = ? AND time = ?");
    $checkStmt->execute([$meetingDate, $meetingTime]);
    
    if ($checkStmt->fetch()) {
        sendError('A meeting is already scheduled at this date and time', 409);
    }

    // Insert new meeting
    $insertStmt = $pdo->prepare("
        INSERT INTO meetings (name, date, time, topic, hosters, created_by) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $insertStmt->execute([
        $meetingName,
        $meetingDate,
        $meetingTime,
        $meetingTopic,
        $meetingHost,
        $decoded['user_id']
    ]);

    $meetingId = $pdo->lastInsertId();

    // Fetch the created meeting details
    $selectStmt = $pdo->prepare("
        SELECT m.*, u.name as created_by_name 
        FROM meetings m 
        JOIN users u ON m.created_by = u.id 
        WHERE m.id = ?
    ");
    $selectStmt->execute([$meetingId]);
    $meeting = $selectStmt->fetch();

    // Prepare response
    $response = [
        'success' => true,
        'id' => $meeting['id'],
        'name' => $meeting['name'],
        'date' => $meeting['date'],
        'time' => $meeting['time'],
        'topic' => $meeting['topic'],
        'hosters' => $meeting['hosters'],
        'created_by' => $meeting['created_by'],
        'created_by_name' => $meeting['created_by_name'],
        'created_at' => $meeting['created_at'],
        'status' => 'upcoming', // Default status
        'message' => 'Meeting created successfully'
    ];

    sendResponse($response, 201);

} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendError('Unexpected error: ' . $e->getMessage(), 500);
}
?>
