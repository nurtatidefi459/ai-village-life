<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../config/payment_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'get_items';
    
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($action === 'get_items') {
        $query = "SELECT ai.*, p.username as seller_name, pba.bank_name, pba.account_number
                 FROM auction_items ai 
                 JOIN players p ON ai.seller_id = p.player_id 
                 LEFT JOIN player_bank_accounts pba ON p.player_id = pba.player_id
                 WHERE ai.status = 'active' AND ai.expires_at > NOW() 
                 ORDER BY ai.created_at DESC 
                 LIMIT 50";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'items' => $items]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    
    if ($action === 'list_item') {
        // Check if player has bank account registered
        if (!hasBankAccount($conn, $auth['player_id'])) {
            echo json_encode(['success' => false, 'message' => 'You must register your bank account first to sell items']);
            exit;
        }
        
        $item_name = $input['item_name'] ?? '';
        $description = $input['description'] ?? '';
        $price = intval($input['price'] ?? 0);
        $item_category = $input['category'] ?? 'general';
        
        if ($item_name && $price > 0) {
            $item_id = 'AUC_' . uniqid();
            $query = "INSERT INTO auction_items (id, seller_id, item_name, description, price, category, created_at, expires_at) 
                     VALUES (?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))";
            $stmt = $conn->prepare($query);
            
            if ($stmt->execute([$item_id, $auth['player_id'], $item_name, $description, $price, $item_category])) {
                // Send notification to seller
                sendAuctionNotification($conn, $auth['player_id'], 'item_listed', $item_name, $price);
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Item listed successfully',
                    'item_id' => $item_id,
                    'fee_info' => [
                        'fee_percentage' => PaymentConfig::$developer_account['fee_percentage'],
                        'fee_amount' => $price * (PaymentConfig::$developer_account['fee_percentage'] / 100),
                        'you_receive' => $price * (1 - PaymentConfig::$developer_account['fee_percentage'] / 100)
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to list item']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid item data']);
        }
    } elseif ($action === 'buy_item') {
        $item_id = $input['item_id'] ?? '';
        
        // Get item details
        $query = "SELECT ai.*, p.player_id as seller_id, p.username as seller_name, 
                         pba.bank_name, pba.account_number, pba.account_holder
                 FROM auction_items ai 
                 JOIN players p ON ai.seller_id = p.player_id 
                 LEFT JOIN player_bank_accounts pba ON p.player_id = pba.player_id
                 WHERE ai.id = ? AND ai.status = 'active'";
        $stmt = $conn->prepare($query);
        $stmt->execute([$item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            echo json_encode(['success' => false, 'message' => 'Item not found or no longer available']);
            exit;
        }
        
        // Check if buyer has enough gold
        $buyer_query = "SELECT gold FROM players WHERE player_id = ?";
        $stmt = $conn->prepare($buyer_query);
        $stmt->execute([$auth['player_id']]);
        $buyer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($buyer['gold'] < $item['price']) {
            echo json_encode(['success' => false, 'message' => 'Insufficient gold']);
            exit;
        }
        
        // Start transaction
        $conn->beginTransaction();
        
        try {
            // Deduct gold from buyer
            $deduct_query = "UPDATE players SET gold = gold - ? WHERE player_id = ?";
            $stmt = $conn->prepare($deduct_query);
            $stmt->execute([$item['price'], $auth['player_id']]);
            
            // Calculate fee and seller amount
            $fee_percentage = PaymentConfig::$developer_account['fee_percentage'];
            $fee_amount = $item['price'] * ($fee_percentage / 100);
            $seller_amount = $item['price'] - $fee_amount;
            
            // Add gold to seller (after fee)
            $add_query = "UPDATE players SET gold = gold + ? WHERE player_id = ?";
            $stmt = $conn->prepare($add_query);
            $stmt->execute([$seller_amount, $item['seller_id']]);
            
            // Update item status
            $update_item = "UPDATE auction_items SET status = 'sold', buyer_id = ?, sold_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update_item);
            $stmt->execute([$auth['player_id'], $item_id]);
            
            // Record transaction
            $transaction_id = 'AUC_TXN_' . uniqid();
            $txn_query = "INSERT INTO auction_transactions (id, item_id, seller_id, buyer_id, price, fee_amount, seller_amount, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($txn_query);
            $stmt->execute([$transaction_id, $item_id, $item['seller_id'], $auth['player_id'], $item['price'], $fee_amount, $seller_amount]);
            
            $conn->commit();
            
            // Send notifications
            sendAuctionNotification($conn, $auth['player_id'], 'item_purchased', $item['item_name'], $item['price']);
            sendAuctionNotification($conn, $item['seller_id'], 'item_sold', $item['item_name'], $seller_amount);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Item purchased successfully!',
                'transaction' => [
                    'item_name' => $item['item_name'],
                    'price' => $item['price'],
                    'fee' => $fee_amount,
                    'seller_received' => $seller_amount
                ]
            ]);
            
        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Transaction failed: ' . $e->getMessage()]);
        }
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

function hasBankAccount($conn, $player_id) {
    $query = "SELECT id FROM player_bank_accounts WHERE player_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$player_id]);
    return $stmt->rowCount() > 0;
}

function sendAuctionNotification($conn, $player_id, $type, $item_name, $amount) {
    $query = "SELECT email, username FROM players WHERE player_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$player_id]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($player) {
        $subject = "AI Village Life - Auction Notification";
        $message = "";
        
        switch ($type) {
            case 'item_listed':
                $message = "Hello {$player['username']},\n\nYour item '{$item_name}' has been listed on the auction house for {$amount} gold.";
                break;
            case 'item_sold':
                $message = "Hello {$player['username']},\n\nCongratulations! Your item '{$item_name}' has been sold for {$amount} gold (after fees).";
                break;
            case 'item_purchased':
                $message = "Hello {$player['username']},\n\nYou have successfully purchased '{$item_name}' for {$amount} gold.";
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
    // Simulate email sending
    error_log("AUCTION EMAIL to {$to}: {$subject} - {$body}");
    return true;
}
?>