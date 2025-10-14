<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once 'admin_middleware.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
    
    $db = new Database();
    $conn = $db->getConnection();
    
    $admin = requireAdminAuth($conn, $token);
    
    // Get dashboard statistics
    $stats = [];
    
    // Total players
    $query = "SELECT COUNT(*) as total_players FROM players";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['total_players'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_players'];
    
    // Pending top-ups
    $query = "SELECT COUNT(*) as pending_topups FROM topup_transactions WHERE status = 'waiting_verification'";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['pending_topups'] = $stmt->fetch(PDO::FETCH_ASSOC)['pending_topups'];
    
    // Total revenue (completed top-ups)
    $query = "SELECT SUM(price) as total_revenue FROM topup_transactions WHERE status = 'completed'";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;
    
    // Active auctions
    $query = "SELECT COUNT(*) as active_auctions FROM auction_items WHERE status = 'active' AND expires_at > NOW()";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['active_auctions'] = $stmt->fetch(PDO::FETCH_ASSOC)['active_auctions'];
    
    // Recent transactions (last 7 days)
    $query = "SELECT DATE(created_at) as date, COUNT(*) as count, SUM(price) as revenue 
              FROM topup_transactions 
              WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
              GROUP BY DATE(created_at) 
              ORDER BY date DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['recent_transactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // System status
    $query = "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('game_version', 'maintenance_mode')";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($settings as $setting) {
        $stats[$setting['setting_key']] = $setting['setting_value'];
    }
    
    echo json_encode(['success' => true, 'stats' => $stats]);
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>