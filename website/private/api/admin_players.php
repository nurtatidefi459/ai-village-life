<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once 'admin_middleware.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
    
    $db = new Database();
    $conn = $db->getConnection();
    
    $admin = requireAdminAuth($conn, $token);
    
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;
    $search = $_GET['search'] ?? '';
    
    $where = "1=1";
    $params = [];
    
    if (!empty($search)) {
        $where .= " AND (p.username LIKE ? OR p.email LIKE ? OR p.player_id LIKE ?)";
        $search_term = "%{$search}%";
        $params = [$search_term, $search_term, $search_term];
    }
    
    $query = "SELECT p.player_id, p.username, p.email, p.silver, p.gold, p.created_at, p.last_login,
                     pba.bank_name, pba.account_number, pba.account_holder
              FROM players p
              LEFT JOIN player_bank_accounts pba ON p.player_id = pba.player_id
              WHERE {$where}
              ORDER BY p.created_at DESC
              LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM players p WHERE {$where}";
    $stmt = $conn->prepare($count_query);
    
    if (!empty($search)) {
        $stmt->execute([$search_term, $search_term, $search_term]);
    } else {
        $stmt->execute();
    }
    
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true, 
        'players' => $players,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $headers = getallheaders();
    $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;
    
    $db = new Database();
    $conn = $db->getConnection();
    
    $admin = requireAdminAuth($conn, $token, 'super_admin');
    
    $action = $input['action'] ?? '';
    $player_id = $input['player_id'] ?? '';
    $amount = intval($input['amount'] ?? 0);
    $currency = $input['currency'] ?? 'gold'; // gold or silver
    $reason = $input['reason'] ?? '';
    
    if (!$player_id || !$amount || !$reason) {
        echo json_encode(['success' => false, 'message' => 'Player ID, amount, and reason are required']);
        exit;
    }
    
    // Verify player exists
    $query = "SELECT username FROM players WHERE player_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$player_id]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$player) {
        echo json_encode(['success' => false, 'message' => 'Player not found']);
        exit;
    }
    
    $conn->beginTransaction();
    
    try {
        if ($action === 'add_currency') {
            $update_query = "UPDATE players SET {$currency} = {$currency} + ? WHERE player_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->execute([$amount, $player_id]);
            
            $message = "Added {$amount} {$currency} to player {$player['username']}";
            
        } elseif ($action === 'remove_currency') {
            // Check if player has enough currency
            $check_query = "SELECT {$currency} FROM players WHERE player_id = ?";
            $stmt = $conn->prepare($check_query);
            $stmt->execute([$player_id]);
            $current_amount = $stmt->fetch(PDO::FETCH_ASSOC)[$currency];
            
            if ($current_amount < $amount) {
                throw new Exception("Player doesn't have enough {$currency}");
            }
            
            $update_query = "UPDATE players SET {$currency} = {$currency} - ? WHERE player_id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->execute([$amount, $player_id]);
            
            $message = "Removed {$amount} {$currency} from player {$player['username']}";
            
        } else {
            throw new Exception('Invalid action');
        }
        
        // Log admin action
        logAdminAction($conn, $admin['admin_id'], 'player_currency_update', 
            "{$message}. Reason: {$reason}");
        
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => $message]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Action failed: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>