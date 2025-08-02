<?php
// CORS headers - MUST BE FIRST
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Enable error logging for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'medi_database.php';

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['email']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Email and password are required']);
    exit;
}

$email = trim($input['email']);
$password = trim($input['password']);

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email format']);
    exit;
}

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    // Verify password (plain text comparison as requested)
    if ($password !== $user['password']) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid password']);
        exit;
    }

    // Generate JWT token (simple token for now)
    $token = base64_encode(json_encode([
        'user_id' => $user['id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'exp' => time() + (24 * 60 * 60) // 24 hours
    ]));

    // Update last login timestamp
    $updateStmt = $pdo->prepare("UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $updateStmt->execute([$user['id']]);

    // Prepare response data (based on actual table structure)
    $response = [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'joinedDate' => $user['created_at'],
        'lastLogin' => date('Y-m-d\TH:i:s\Z'),
        'isActive' => true,
        'token' => $token,
        'refreshToken' => 'refresh-' . $token
    ];

    http_response_code(200);
    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
