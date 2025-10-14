<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';
require_once '../config/gold_trading_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list_gold';
    
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($action === 'list_gold') {
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 20);
        $offset = ($page - 1) * $limit;
        $sort = $_GET['sort'] ?? 'price_asc'; // price_asc, price_desc, newest
        
        $order_by = "gl.price_per_gold ASC";
        switch ($sort) {
            case 'price_desc':
                $order_by = "gl.price_per_gold DESC";
                break;
            case 'newest':
                $order_by = "gl.created_at DESC";
                break;
        }
        
        $query = "SELECT gl.*, p.username as seller_name, pba.bank_name, pba.account_number, pba.account_holder
                 FROM gold_listings gl
                 JOIN players p ON gl.seller_id = p.player_id
                 LEFT JOIN player_bank_accounts pba ON p.player_id = pba.player_id
                 WHERE gl.status = 'active' AND gl.expires_at > NOW()
                 ORDER BY {$order_by}
                 LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$limit, $offset]);
        $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count
        $count_query = "SELECT COUNT(*) as total FROM gold_listings WHERE status = 'active' AND expires_at > NOW()";
        $stmt = $conn->prepare($count_query);
        $stmt->execute();
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo json_encode([
            'success' => true, 
            'listings' => $listings,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ],
            'price_limits' => [
                'min' => GoldTradingConfig::$min_price_per_gold,
                'max' => GoldTradingConfig::$max_price_per_gold
            ]
        ]);
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
    
    if ($action === 'create_listing') {
        // Validasi: seller harus punya rekening bank
        if (!hasBankAccount($conn, $auth['player_id'])) {
            echo json_encode(['success' => false, 'message' => 'You must register your bank account first to sell gold']);
            exit;
        }
        
        $gold_amount = intval($input['gold_amount'] ?? 0);
        $price_per_gold = floatval($input['price_per_gold'] ?? 0);
        
        // Validasi jumlah gold
        if (!GoldTradingConfig::validateGoldAmount($gold_amount)) {
            echo json_encode([
                'success' => false, 
                'message' => "Gold amount must be between " . GoldTradingConfig::$min_gold_per_listing . " and " . GoldTradingConfig::$max_gold_per_listing
            ]);
            exit;
        }
        
        // Validasi harga
        if (!GoldTradingConfig::validatePrice($price_per_gold)) {
            echo json_encode([
                'success' => false, 
                'message' => "Price must be between Rp " . number_format(GoldTradingConfig::$min_price_per_gold) . " and Rp " . number_format(GoldTradingConfig::$max_price_per_gold) . " per gold"
            ]);
            exit;
        }
        
        // Cek apakah seller punya cukup gold
        $check_gold_query = "SELECT gold FROM players WHERE player_id = ?";
        $stmt = $conn->prepare($check_gold_query);
        $stmt->execute([$auth['player_id']]);
        $player_gold = $stmt->fetch(PDO::FETCH_ASSOC)['gold'];
        
        if ($player_gold < $gold_amount) {
            echo json_encode(['success' => false, 'message' => 'Insufficient gold. You have ' . $player_gold . ' gold']);
            exit;
        }
        
        $total_price = $gold_amount * $price_per_gold;
        $fee_amount = GoldTradingConfig::calculateFee($total_price);
        $seller_amount = GoldTradingConfig::calculateSellerAmount($total_price);
        
        $conn->beginTransaction();
        
        try {
            // Lock gold dari seller
            $lock_gold_query = "UPDATE players SET gold = gold - ? WHERE player_id = ?";
            $stmt = $conn->prepare($lock_gold_query);
            $stmt->execute([$gold_amount, $auth['player_id']]);
            
            // Buat listing
            $listing_id = 'GOLD_' . uniqid();
            $expires_at = date('Y-m-d H:i:s', strtotime('+' . GoldTradingConfig::$listing_duration_days . ' days'));
            
            $insert_query = "INSERT INTO gold_listings (id, seller_id, gold_amount, price_per_gold, total_price, fee_amount, seller_amount, expires_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->execute([$listing_id, $auth['player_id'], $gold_amount, $price_per_gold, $total_price, $fee_amount, $seller_amount, $expires_at]);
            
            $conn->commit();
            
            // Kirim notifikasi
            sendGoldTradingNotification($conn, $auth['player_id'], 'listing_created', $gold_amount, $price_per_gold);
            
            echo json_encode([
                'success' => true,
                'message' => 'Gold listing created successfully!',
                'listing' => [
                    'id' => $listing_id,
                    'gold_amount' => $gold_amount,
                    'price_per_gold' => $price_per_gold,
                    'total_price' => $total_price,
                    'fee' => $fee_amount,
                    'you_receive' => $seller_amount,
                    'expires_at' => $expires_at
                ]
            ]);
            
        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to create listing: ' . $e->getMessage()]);
        }
        
    } elseif ($action === 'buy_gold') {
        $listing_id = $input['listing_id'] ?? '';
        
        // Validasi: buyer harus punya rekening bank
        if (!hasBankAccount($conn, $auth['player_id'])) {
            echo json_encode(['success' => false, 'message' => 'You must register your bank account first to buy gold']);
            exit;
        }
        
        // Get listing details
        $query = "SELECT gl.*, p.username as seller_name, 
                         pba.bank_name, pba.account_number, pba.account_holder
                 FROM gold_listings gl
                 JOIN players p ON gl.seller_id = p.player_id
                 LEFT JOIN player_bank_accounts pba ON p.player_id = pba.player_id
                 WHERE gl.id = ? AND gl.status = 'active'";
        $stmt = $conn->prepare($query);
        $stmt->execute([$listing_id]);
        $listing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$listing) {
            echo json_encode(['success' => false, 'message' => 'Gold listing not found or no longer available']);
            exit;
        }
        
        // Cek apakah buyer membeli dari dirinya sendiri
        if ($listing['seller_id'] === $auth['player_id']) {
            echo json_encode(['success' => false, 'message' => 'You cannot buy your own gold listing']);
            exit;
        }
        
        // Create transaction record
        $transaction_id = 'GOLD_TXN_' . uniqid();
        $insert_query = "INSERT INTO gold_transactions (id, gold_listing_id, buyer_id, seller_id, gold_amount, total_price, fee_amount, seller_amount, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        $stmt = $conn->prepare($insert_query);
        $stmt->execute([
            $transaction_id, 
            $listing_id, 
            $auth['player_id'], 
            $listing['seller_id'],
            $listing['gold_amount'],
            $listing['total_price'],
            $listing['fee_amount'],
            $listing['seller_amount']
        ]);
        
        // Update listing status menjadi reserved
        $update_listing = "UPDATE gold_listings SET status = 'sold', buyer_id = ? WHERE id = ?";
        $stmt = $conn->prepare($update_listing);
        $stmt->execute([$auth['player_id'], $listing_id]);
        
        // Kirim notifikasi ke buyer dan seller
        sendGoldTradingNotification($conn, $auth['player_id'], 'gold_purchased', $listing['gold_amount'], $listing['price_per_gold']);
        sendGoldTradingNotification($conn, $listing['seller_id'], 'gold_sold_pending', $listing['gold_amount'], $listing['price_per_gold']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Gold purchase initiated! Please transfer Rp ' . number_format($listing['total_price']) . ' to complete the transaction.',
            'transaction' => [
                'id' => $transaction_id,
                'seller_bank' => [
                    'bank_name' => $listing['bank_name'],
                    'account_number' => $listing['account_number'],
                    'account_holder' => $listing['account_holder']
                ],
                'amount' => $listing['total_price'],
                'gold_amount' => $listing['gold_amount']
            ]
        ]);
        
    } elseif ($action === 'confirm_payment') {
        $transaction_id = $input['transaction_id'] ?? '';
        $proof_image = $input['proof_image'] ?? '';
        $transfer_date = $input['transfer_date'] ?? '';
        $bank_name = $input['bank_name'] ?? '';
        $account_number = $input['account_number'] ?? '';
        
        // Get transaction details
        $query = "SELECT gt.*, gl.seller_id 
                 FROM gold_transactions gt
                 JOIN gold_listings gl ON gt.gold_listing_id = gl.id
                 WHERE gt.id = ? AND gt.buyer_id = ? AND gt.status = 'pending'";
        $stmt = $conn->prepare($query);
        $stmt->execute([$transaction_id, $auth['player_id']]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$transaction) {
            echo json_encode(['success' => false, 'message' => 'Transaction not found']);
            exit;
        }
        
        // Update transaction dengan bukti transfer
        $update_query = "UPDATE gold_transactions SET 
                        payment_proof_image = ?, transfer_date = ?, buyer_bank_name = ?, buyer_account_number = ?,
                        status = 'waiting_verification', updated_at = NOW()
                        WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->execute([$proof_image, $transfer_date, $bank_name, $account_number, $transaction_id]);
        
        // Kirim notifikasi ke seller
        sendGoldTradingNotification($conn, $transaction['seller_id'], 'payment_received', $transaction['gold_amount'], $transaction['total_price']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Payment confirmation submitted! The seller will verify your payment.'
        ]);
        
    } elseif ($action === 'cancel_listing') {
        $listing_id = $input['listing_id'] ?? '';
        
        $conn->beginTransaction();
        
        try {
            // Get listing details
            $query = "SELECT * FROM gold_listings WHERE id = ? AND seller_id = ? AND status = 'active'";
            $stmt = $conn->prepare($query);
            $stmt->execute([$listing_id, $auth['player_id']]);
            $listing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$listing) {
                throw new Exception('Listing not found or cannot be cancelled');
            }
            
            // Kembalikan gold ke seller
            $return_gold_query = "UPDATE players SET gold = gold + ? WHERE player_id = ?";
            $stmt = $conn->prepare($return_gold_query);
            $stmt->execute([$listing['gold_amount'], $auth['player_id']]);
            
            // Update status listing
            $update_query = "UPDATE gold_listings SET status = 'cancelled' WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->execute([$listing_id]);
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Gold listing cancelled successfully. Your gold has been returned.'
            ]);
            
        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to cancel listing: ' . $e->getMessage()]);
        }
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

// Fungsi helper
function hasBankAccount($conn, $player_id) {
    $query = "SELECT id FROM player_bank_accounts WHERE player_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$player_id]);
    return $stmt->rowCount() > 0;
}

function sendGoldTradingNotification($conn, $player_id, $type, $gold_amount, $price) {
    $query = "SELECT email, username FROM players WHERE player_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$player_id]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($player) {
        $subject = "AI Village Life - Gold Trading Notification";
        $message = "";
        
        switch ($type) {
            case 'listing_created':
                $message = "Hello {$player['username']},\n\nYour gold listing has been created:\n- Gold Amount: {$gold_amount}\n- Price: Rp " . number_format($price) . " per gold\n\nYour gold has been locked and will be returned if not sold within 7 days.";
                break;
            case 'gold_purchased':
                $message = "Hello {$player['username']},\n\nYou have purchased {$gold_amount} gold for Rp " . number_format($price) . ".\n\nPlease complete the payment to receive your gold.";
                break;
            case 'gold_sold_pending':
                $message = "Hello {$player['username']},\n\nYour gold listing has been purchased:\n- Gold Amount: {$gold_amount}\n- Total Price: Rp " . number_format($price) . "\n\nPlease wait for the buyer to complete the payment.";
                break;
            case 'payment_received':
                $message = "Hello {$player['username']},\n\nPayment has been received for your gold sale:\n- Gold Amount: {$gold_amount}\n- Amount: Rp " . number_format($price) . "\n\nPlease verify the payment in your account.";
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
    error_log("GOLD TRADING EMAIL to {$to}: {$subject} - {$body}");
    return true;
}
?>