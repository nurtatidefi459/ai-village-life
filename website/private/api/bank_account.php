<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
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
    
    $bank_name = $input['bank_name'] ?? '';
    $account_number = $input['account_number'] ?? '';
    $account_holder = $input['account_holder'] ?? '';
    
    if (!$bank_name || !$account_number || !$account_holder) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
    
    // Check if already exists
    $check_query = "SELECT id FROM player_bank_accounts WHERE player_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->execute([$auth['player_id']]);
    
    if ($stmt->rowCount() > 0) {
        // Update existing
        $update_query = "UPDATE player_bank_accounts SET bank_name = ?, account_number = ?, account_holder = ?, updated_at = NOW() WHERE player_id = ?";
        $stmt = $conn->prepare($update_query);
        $result = $stmt->execute([$bank_name, $account_number, $account_holder, $auth['player_id']]);
        $message = 'Bank account updated successfully';
    } else {
        // Insert new
        $insert_query = "INSERT INTO player_bank_accounts (player_id, bank_name, account_number, account_holder, created_at) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($insert_query);
        $result = $stmt->execute([$auth['player_id'], $bank_name, $account_number, $account_holder]);
        $message = 'Bank account registered successfully';
    }
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save bank account']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
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
    
    $query = "SELECT bank_name, account_number, account_holder, created_at FROM player_bank_accounts WHERE player_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$auth['player_id']]);
    $bank_account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'bank_account' => $bank_account]);
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

function verifyToken($conn, $token) {
    if (!$token) return null;
    
    $query = "SELECT player_id FROM auth_tokens WHERE token = ? AND expires_at > NOW()";
    $stmt = $conn->prepare($query);
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>