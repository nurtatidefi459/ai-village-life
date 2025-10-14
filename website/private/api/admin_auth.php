<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['username']) || !isset($input['password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing username or password']);
        exit;
    }
    
    $username = trim($input['username']);
    $password = $input['password'];
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $db = new Database();
    $conn = $db->getConnection();
    
    $query = "SELECT id, username, password, email, role, is_active FROM admins WHERE username = ? AND is_active = TRUE";
    $stmt = $conn->prepare($query);
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin && password_verify($password, $admin['password'])) {
        // Generate admin token
        $token = bin2hex(random_bytes(32));
        $token_query = "INSERT INTO admin_tokens (admin_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 8 HOUR))";
        $stmt = $conn->prepare($token_query);
        $stmt->execute([$admin['id'], $token]);
        
        // Update last login
        $update_query = "UPDATE admins SET last_login = NOW() WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->execute([$admin['id']]);
        
        // Log login action
        logAdminAction($conn, $admin['id'], 'login', "Admin logged in from IP: {$ip_address}", $ip_address, $user_agent);
        
        unset($admin['password']);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Login successful',
            'admin' => $admin,
            'token' => $token
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

function logAdminAction($conn, $admin_id, $action, $description, $ip_address, $user_agent) {
    $query = "INSERT INTO admin_logs (admin_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->execute([$admin_id, $action, $description, $ip_address, $user_agent]);
}
?>