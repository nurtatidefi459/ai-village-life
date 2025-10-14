<?php
function verifyAdminToken($conn, $token) {
    if (!$token) return null;
    
    $query = "SELECT at.admin_id, a.username, a.role, a.is_active 
              FROM admin_tokens at 
              JOIN admins a ON at.admin_id = a.id 
              WHERE at.token = ? AND at.expires_at > NOW() AND a.is_active = TRUE";
    $stmt = $conn->prepare($query);
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function requireAdminAuth($conn, $token, $required_role = null) {
    $admin = verifyAdminToken($conn, $token);
    
    if (!$admin) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired admin token']);
        exit;
    }
    
    if ($required_role && $admin['role'] !== 'super_admin' && $admin['role'] !== $required_role) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
        exit;
    }
    
    return $admin;
}

function logAdminAction($conn, $admin_id, $action, $description = '') {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $query = "INSERT INTO admin_logs (admin_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->execute([$admin_id, $action, $description, $ip_address, $user_agent]);
}
?>