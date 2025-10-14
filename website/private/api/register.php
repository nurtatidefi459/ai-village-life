<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['username']) || !isset($input['email']) || !isset($input['password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    $username = trim($input['username']);
    $email = trim($input['email']);
    $password = $input['password'];
    
    // Validate input
    if (strlen($username) < 3) {
        echo json_encode(['success' => false, 'message' => 'Username must be at least 3 characters']);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }
    
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        exit;
    }
    
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if email already exists
    $check_query = "SELECT id FROM players WHERE email = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->execute([$email]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        exit;
    }
    
    // Check if username already exists
    $check_query = "SELECT id FROM players WHERE username = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->execute([$username]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already taken']);
        exit;
    }
    
    // Generate unique player ID
    $player_id = 'PLAYER_' . uniqid() . '_' . rand(1000, 9999);
    
    // Insert new player
    $insert_query = "INSERT INTO players (player_id, username, email, password, silver, gold, created_at) 
                     VALUES (?, ?, ?, ?, 100, 0, NOW())";
    $stmt = $conn->prepare($insert_query);
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    if ($stmt->execute([$player_id, $username, $email, $hashed_password])) {
        // Generate auth token
        $token = bin2hex(random_bytes(32));
        $token_query = "INSERT INTO auth_tokens (player_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))";
        $stmt = $conn->prepare($token_query);
        $stmt->execute([$player_id, $token]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Registration successful',
            'player_id' => $player_id,
            'token' => $token
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registration failed']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>