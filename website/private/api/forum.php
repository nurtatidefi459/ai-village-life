<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'get_posts';
    
    $db = new Database();
    $conn = $db->getConnection();
    
    if ($action === 'get_posts') {
        $query = "SELECT fp.*, p.username as author 
                 FROM forum_posts fp 
                 JOIN players p ON fp.author_id = p.player_id 
                 ORDER BY fp.created_at DESC 
                 LIMIT 20";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'posts' => $posts]);
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
    
    if ($action === 'create_post') {
        $title = $input['title'] ?? '';
        $content = $input['content'] ?? '';
        
        if ($title && $content) {
            $post_id = 'POST_' . uniqid();
            $query = "INSERT INTO forum_posts (id, author_id, title, content, created_at) 
                     VALUES (?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($query);
            
            if ($stmt->execute([$post_id, $auth['player_id'], $title, $content])) {
                echo json_encode(['success' => true, 'message' => 'Post created successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create post']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Title and content are required']);
        }
    }
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