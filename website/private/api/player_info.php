<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';

function verifyToken($conn, $token) {
    if (!$token) return null;
    
    $query = "SELECT player_id FROM auth_tokens WHERE token = ? AND expires_at > NOW()";
    $stmt = $conn->prepare($query);
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
    
    $db = new Database();
    $conn = $db->getConnection();
    
    $auth = verifyToken($conn, $token);
    if (!$auth) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
        exit;
    }
    
    $player_id = $input['player_id'] ?? $auth['player_id'];
    
    $query = "SELECT player_id, username, email, silver, gold, created_at, last_login FROM players WHERE player_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$player_id]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($player) {
        // Update last login
        $update_query = "UPDATE players SET last_login = NOW() WHERE player_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->execute([$player_id]);
        
        echo json_encode(['success' => true, 'player' => $player]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Player not found']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>