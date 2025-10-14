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
    
    $status = $_GET['status'] ?? 'waiting_verification';
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;
    
    $query = "SELECT tt.*, p.username, p.email, p.player_id 
              FROM topup_transactions tt
              JOIN players p ON tt.player_id = p.player_id
              WHERE tt.status = ?
              ORDER BY tt.created_at DESC
              LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$status, $limit, $offset]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) as total FROM topup_transactions WHERE status = ?";
    $stmt = $conn->prepare($count_query);
    $stmt->execute([$status]);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true, 
        'transactions' => $transactions,
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
    $transaction_id = $input['transaction_id'] ?? '';
    $notes = $input['notes'] ?? '';
    
    if (!$transaction_id) {
        echo json_encode(['success' => false, 'message' => 'Transaction ID is required']);
        exit;
    }
    
    // Get transaction details
    $query = "SELECT * FROM topup_transactions WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        echo json_encode(['success' => false, 'message' => 'Transaction not found']);
        exit;
    }
    
    $conn->beginTransaction();
    
    try {
        if ($action === 'approve') {
            // Update transaction status
            $update_query = "UPDATE topup_transactions SET status = 'completed', updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->execute([$transaction_id]);
            
            // Add gold to player
            $gold_query = "UPDATE players SET gold = gold + ? WHERE player_id = ?";
            $stmt = $conn->prepare($gold_query);
            $stmt->execute([$transaction['gold_amount'], $transaction['player_id']]);
            
            // Send notification to player
            sendTopUpApprovalNotification($conn, $transaction['player_id'], $transaction['gold_amount']);
            
            $message = "Top-up approved and gold added to player";
            
        } elseif ($action === 'reject') {
            // Update transaction status
            $update_query = "UPDATE topup_transactions SET status = 'cancelled', admin_notes = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->execute([$notes, $transaction_id]);
            
            // Send rejection notification to player
            sendTopUpRejectionNotification($conn, $transaction['player_id'], $transaction['gold_amount'], $notes);
            
            $message = "Top-up rejected";
            
        } else {
            throw new Exception('Invalid action');
        }
        
        // Log admin action
        logAdminAction($conn, $admin['admin_id'], 'topup_' . $action, 
            "Transaction {$transaction_id}: {$message}. Notes: {$notes}");
        
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

function sendTopUpApprovalNotification($conn, $player_id, $gold_amount) {
    $query = "SELECT email, username FROM players WHERE player_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$player_id]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($player) {
        $subject = "AI Village Life - Top-up Approved";
        $message = "
        Hello {$player['username']},
        
        Your top-up of {$gold_amount} Gold has been approved and added to your account.
        
        You can now use your gold in the game!
        
        Thank you,
        AI Village Life Team
        ";
        
        sendEmail($player['email'], $subject, $message);
    }
}

function sendTopUpRejectionNotification($conn, $player_id, $gold_amount, $reason) {
    $query = "SELECT email, username FROM players WHERE player_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$player_id]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($player) {
        $subject = "AI Village Life - Top-up Rejected";
        $message = "
        Hello {$player['username']},
        
        Your top-up of {$gold_amount} Gold has been rejected.
        
        Reason: {$reason}
        
        If you believe this is a mistake, please contact support.
        
        Thank you,
        AI Village Life Team
        ";
        
        sendEmail($player['email'], $subject, $message);
    }
}

function sendEmail($to, $subject, $body) {
    // In production, use PHPMailer or similar
    error_log("ADMIN EMAIL to {$to}: {$subject} - {$body}");
    return true;
}
?>