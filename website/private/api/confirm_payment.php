<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../config/payment_config.php';

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
    
    $transaction_id = $input['transaction_id'] ?? '';
    $proof_image = $input['proof_image'] ?? ''; // Base64 encoded image
    $transfer_date = $input['transfer_date'] ?? '';
    $bank_name = $input['bank_name'] ?? '';
    $account_number = $input['account_number'] ?? '';
    
    // Validate transaction
    $query = "SELECT * FROM topup_transactions WHERE id = ? AND player_id = ? AND status = 'pending'";
    $stmt = $conn->prepare($query);
    $stmt->execute([$transaction_id, $auth['player_id']]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        echo json_encode(['success' => false, 'message' => 'Invalid transaction']);
        exit;
    }
    
    // Update transaction with payment proof
    $update_query = "UPDATE topup_transactions SET 
                    proof_image = ?, transfer_date = ?, bank_name = ?, account_number = ?, 
                    status = 'waiting_verification', updated_at = NOW() 
                    WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    
    if ($stmt->execute([$proof_image, $transfer_date, $bank_name, $account_number, $transaction_id])) {
        // Notify admin for verification
        notifyAdminForVerification($transaction_id);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Payment confirmation submitted. Please wait for verification.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to confirm payment']);
    }
}

function notifyAdminForVerification($transaction_id) {
    // Send notification to admin (email, dashboard, etc.)
    error_log("Admin Notification: Transaction {$transaction_id} needs verification");
}

function verifyToken($conn, $token) {
    if (!$token) return null;
    
    $query = "SELECT player_id FROM auth_tokens WHERE token = ? AND expires_at > NOW()";
    $stmt = $conn->prepare($query);
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>