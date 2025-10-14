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
    
    $amount = intval($input['amount'] ?? 0);
    $payment_method = $input['payment_method'] ?? 'bank_transfer';
    
    // Validasi amount
    if ($amount < 1 || !array_key_exists($amount, PaymentConfig::$gold_prices)) {
        echo json_encode(['success' => false, 'message' => 'Invalid gold amount']);
        exit;
    }
    
    // Cek monthly limit
    if (!checkMonthlyLimit($conn, $auth['player_id'], $amount)) {
        echo json_encode(['success' => false, 'message' => 'Monthly top-up limit reached']);
        exit;
    }
    
    $price = PaymentConfig::$gold_prices[$amount];
    
    // Create pending transaction
    $transaction_id = 'TOPUP_' . uniqid();
    $query = "INSERT INTO topup_transactions (id, player_id, gold_amount, price, payment_method, status, created_at) 
              VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
    $stmt = $conn->prepare($query);
    
    if ($stmt->execute([$transaction_id, $auth['player_id'], $amount, $price, $payment_method])) {
        // Send email notification
        sendTopUpNotification($conn, $auth['player_id'], $amount, $price, $transaction_id);
        
        echo json_encode([
            'success' => true, 
            'message' => "Top-up order created. Please transfer Rp " . number_format($price) . " to complete your purchase.",
            'transaction_id' => $transaction_id,
            'payment_info' => [
                'bank_name' => PaymentConfig::$developer_account['bank_name'],
                'account_number' => PaymentConfig::$developer_account['account_number'],
                'account_holder' => PaymentConfig::$developer_account['account_holder'],
                'amount' => $price,
                'gold_amount' => $amount
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create top-up order']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get top-up history
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
    
    $query = "SELECT * FROM topup_transactions WHERE player_id = ? ORDER BY created_at DESC LIMIT 10";
    $stmt = $conn->prepare($query);
    $stmt->execute([$auth['player_id']]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'transactions' => $transactions]);
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

function checkMonthlyLimit($conn, $player_id, $amount) {
    $current_month = date('Y-m-01');
    $next_month = date('Y-m-01', strtotime('+1 month'));
    
    $query = "SELECT SUM(gold_amount) as total_gold 
              FROM topup_transactions 
              WHERE player_id = ? AND status = 'completed' 
              AND created_at >= ? AND created_at < ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$player_id, $current_month, $next_month]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $current_total = $result['total_gold'] ?? 0;
    $new_total = $current_total + $amount;
    
    return $new_total <= PaymentConfig::$topup_limits['max_gold_per_month'];
}

function sendTopUpNotification($conn, $player_id, $gold_amount, $price, $transaction_id) {
    $query = "SELECT email, username FROM players WHERE player_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$player_id]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($player) {
        $subject = "AI Village Life - Top-up Order Created";
        $message = "
        Hello {$player['username']},
        
        Your top-up order has been created:
        - Transaction ID: {$transaction_id}
        - Gold Amount: {$gold_amount}
        - Price: Rp " . number_format($price) . "
        
        Please transfer to:
        Bank: " . PaymentConfig::$developer_account['bank_name'] . "
        Account: " . PaymentConfig::$developer_account['account_number'] . "
        Name: " . PaymentConfig::$developer_account['account_holder'] . "
        
        After transfer, please confirm your payment in the game.
        
        Thank you,
        AI Village Life Team
        ";
        
        // In real implementation, use PHPMailer or similar
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
    // Simulate email sending (in production, use PHPMailer)
    error_log("EMAIL to {$to}: {$subject} - {$body}");
    return true;
}
?>