<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';

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
    
    $action = $input['action'] ?? '';
    $transaction_id = $input['transaction_id'] ?? '';
    
    // Get transaction details
    $query = "SELECT gt.*, gl.seller_id 
             FROM gold_transactions gt
             JOIN gold_listings gl ON gt.gold_listing_id = gl.id
             WHERE gt.id = ? AND gt.status = 'waiting_verification'";
    $stmt = $conn->prepare($query);
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        echo json_encode(['success' => false, 'message' => 'Transaction not found or already processed']);
        exit;
    }
    
    // Cek apakah user adalah seller yang berhak
    if ($transaction['seller_id'] !== $auth['player_id']) {
        echo json_encode(['success' => false, 'message' => 'You are not authorized to verify this transaction']);
        exit;
    }
    
    $conn->beginTransaction();
    
    try {
        if ($action === 'confirm_payment') {
            // Update transaction status
            $update_query = "UPDATE gold_transactions SET status = 'completed', updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->execute([$transaction_id]);
            
            // Berikan gold ke buyer
            $add_gold_query = "UPDATE players SET gold = gold + ? WHERE player_id = ?";
            $stmt = $conn->prepare($add_gold_query);
            $stmt->execute([$transaction['gold_amount'], $transaction['buyer_id']]);
            
            // Update listing
            $update_listing = "UPDATE gold_listings SET sold_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update_listing);
            $stmt->execute([$transaction['gold_listing_id']]);
            
            // Kirim notifikasi
            sendGoldVerificationNotification($conn, $transaction['buyer_id'], 'payment_confirmed', $transaction['gold_amount']);
            
            $message = "Payment confirmed! Gold has been delivered to the buyer.";
            
        } elseif ($action === 'reject_payment') {
            $reason = $input['reason'] ?? 'Payment verification failed';
            
            // Update transaction status
            $update_query = "UPDATE gold_transactions SET status = 'cancelled', updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->execute([$transaction_id]);
            
            // Kembalikan listing status ke active
            $update_listing = "UPDATE gold_listings SET status = 'active', buyer_id = NULL WHERE id = ?";
            $stmt = $conn->prepare($update_listing);
            $stmt->execute([$transaction['gold_listing_id']]);
            
            // Kirim notifikasi
            sendGoldVerificationNotification($conn, $transaction['buyer_id'], 'payment_rejected', $transaction['gold_amount'], $reason);
            
            $message = "Payment rejected. The gold listing is available again.";
            
        } else {
            throw new Exception('Invalid action');
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => $message]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Action failed: ' . $e->getMessage()]);
    }
}

function sendGoldVerificationNotification($conn, $player_id, $type, $gold_amount, $reason = '') {
    $query = "SELECT email, username FROM players WHERE player_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$player_id]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($player) {
        $subject = "AI Village Life - Gold Trading Update";
        $message = "";
        
        switch ($type) {
            case 'payment_confirmed':
                $message = "Hello {$player['username']},\n\nYour payment has been confirmed! You have received {$gold_amount} gold.\n\nThank you for your purchase!";
                break;
            case 'payment_rejected':
                $message = "Hello {$player['username']},\n\nYour payment has been rejected by the seller.\nReason: {$reason}\n\nYour gold purchase has been cancelled.";
                break;
        }
        
        $message .= "\n\nThank you,\nAI Village Life Team";
        
        sendEmail($player['email'], $subject, $message);
    }
}

function verifyToken($conn, $token) {
    if (!$token) return null;
    
    $query = "SELECT player_id FROM auth_tokens WHERE token = ? AND expires_at > NOW()";
    $stmt = $conn->prepare($query);
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function sendEmail($to, $subject, $body) {
    error_log("GOLD VERIFICATION EMAIL to {$to}: {$subject} - {$body}");
    return true;
}
?>