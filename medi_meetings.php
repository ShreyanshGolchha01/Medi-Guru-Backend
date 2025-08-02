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

// Check if request method is GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError('Method not allowed', 405);
}

// Get and validate token
$token = getAuthHeader();
$decoded = validateToken($token);

try {
    // Fetch all meetings with creator information
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.name,
            m.date,
            m.time,
            m.topic,
            m.hosters,
            m.created_by,
            m.created_at,
            u.name as created_by_name,
            u.role as created_by_role,
            CASE 
                WHEN m.date > CURDATE() THEN 'upcoming'
                WHEN m.date = CURDATE() THEN 'ongoing'
                ELSE 'completed'
            END as status
        FROM meetings m 
        JOIN users u ON m.created_by = u.id 
        ORDER BY m.date DESC, m.time DESC
    ");
    
    $stmt->execute();
    $meetings = $stmt->fetchAll();

    // Format the response
    $formattedMeetings = [];
    foreach ($meetings as $meeting) {
        $formattedMeetings[] = [
            'id' => $meeting['id'],
            'title' => $meeting['name'],
            'name' => $meeting['name'],
            'date' => $meeting['date'],
            'time' => $meeting['time'],
            'topic' => $meeting['topic'],
            'hosters' => $meeting['hosters'],
            'instructor' => $meeting['hosters'],
            'created_by' => $meeting['created_by'],
            'created_by_name' => $meeting['created_by_name'],
            'created_by_role' => $meeting['created_by_role'],
            'created_at' => $meeting['created_at'],
            'status' => $meeting['status'],
            'location' => 'Training Hall', // Default location
            'duration' => '2 hours', // Default duration
            'attendees' => 0, // Will be updated when attendance system is implemented
            'maxAttendees' => 50, // Default max attendees
            'category' => 'Medical Training' // Default category
        ];
    }

    sendResponse([
        'meetings' => $formattedMeetings,
        'total' => count($formattedMeetings),
        'message' => 'Meetings fetched successfully'
    ]);

} catch (PDOException $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    sendError('Unexpected error: ' . $e->getMessage(), 500);
}
?>
