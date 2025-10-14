<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['email']) || !isset($input['password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing email or password']);
        exit;
    }
    
    $email = trim($input['email']);
    $password = $input['password'];
    
    $db = new Database();
    $conn = $db->getConnection();
    
    $query = "SELECT player_id, username, email, password, silver, gold, created_at FROM players WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$email]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($player && password_verify($password, $player['password'])) {
        // Generate new auth token
        $token = bin2hex(random_bytes(32));
        $token_query = "INSERT INTO auth_tokens (player_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY)) 
                       ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)";
        $stmt = $conn->prepare($token_query);
        $stmt->execute([$player['player_id'], $token]);
        
        unset($player['password']);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Login successful',
            'player' => $player,
            'token' => $token
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>