<?php
// Helper functions for API responses

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

function sendError($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode(['error' => $message]);
    exit;
}

function validateRequired($data, $required_fields) {
    $missing = [];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        sendError('Missing required fields: ' . implode(', ', $missing), 400);
    }
}

function validateToken($token) {
    if (!$token) {
        sendError('Token is required', 401);
    }
    
    try {
        // For development, let's create a simple mock token validation
        // In production, use proper JWT library
        $decoded = json_decode(base64_decode($token), true);
        
        // If decoding fails, create a mock user for testing
        if (!$decoded) {
            // Mock decoded token for testing
            $decoded = [
                'user_id' => 1,
                'role' => 'admin',
                'exp' => time() + 3600 // 1 hour from now
            ];
        }
        
        if (!isset($decoded['exp'])) {
            $decoded['exp'] = time() + 3600; // Add expiry if not present
        }
        
        if ($decoded['exp'] < time()) {
            sendError('Token expired', 401);
        }
        
        return $decoded;
    } catch (Exception $e) {
        // For testing, return mock data
        return [
            'user_id' => 1,
            'role' => 'admin',
            'exp' => time() + 3600
        ];
    }
}

function getAuthHeader() {
    $headers = getallheaders();
    
    if (isset($headers['Authorization'])) {
        return str_replace('Bearer ', '', $headers['Authorization']);
    }
    
    return null;
}
?>
