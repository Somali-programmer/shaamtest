<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/config.php';

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get user IP
function getUserIP() {
    $ip = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

// Get user agent
function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $post_id = isset($input['post_id']) ? (int)$input['post_id'] : null;
    $video_id = isset($input['video_id']) ? (int)$input['video_id'] : null;
    $action = $input['action'] ?? 'toggle'; // 'like' or 'unlike'
    
    $user_ip = getUserIP();
    $user_agent = getUserAgent();
    
    try {
        if ($post_id || $video_id) {
            // Check if already liked
            $sql = "SELECT id FROM likes WHERE ";
            $params = [];
            
            if ($post_id) {
                $sql .= "post_id = ? AND ";
                $params[] = $post_id;
            } else {
                $sql .= "video_id = ? AND ";
                $params[] = $video_id;
            }
            
            $sql .= "user_ip = ?";
            $params[] = $user_ip;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $existing = $stmt->fetch();
            
            if ($existing && $action !== 'like') {
                // Unlike
                $stmt = $pdo->prepare("DELETE FROM likes WHERE id = ?");
                $stmt->execute([$existing['id']]);
                $liked = false;
            } elseif (!$existing && $action !== 'unlike') {
                // Like
                $stmt = $pdo->prepare("INSERT INTO likes (post_id, video_id, user_ip, user_agent) VALUES (?, ?, ?, ?)");
                $stmt->execute([$post_id, $video_id, $user_ip, $user_agent]);
                $liked = true;
            } else {
                $liked = $existing ? true : false;
            }
            
            // Get like count
            $sql = "SELECT COUNT(*) as count FROM likes WHERE ";
            if ($post_id) {
                $sql .= "post_id = ?";
                $params = [$post_id];
            } else {
                $sql .= "video_id = ?";
                $params = [$video_id];
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $count = $stmt->fetch()['count'];
            
            echo json_encode([
                'success' => true,
                'liked' => $liked,
                'count' => $count
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid data']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get like status and count
    $post_id = isset($_GET['post_id']) ? (int)$_GET['post_id'] : null;
    $video_id = isset($_GET['video_id']) ? (int)$_GET['video_id'] : null;
    $user_ip = getUserIP();
    
    try {
        if ($post_id || $video_id) {
            // Get like count
            $sql = "SELECT COUNT(*) as count FROM likes WHERE ";
            $params = [];
            
            if ($post_id) {
                $sql .= "post_id = ?";
                $params = [$post_id];
            } else {
                $sql .= "video_id = ?";
                $params = [$video_id];
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $count = $stmt->fetch()['count'];
            
            // Check if user liked
            $sql = "SELECT id FROM likes WHERE ";
            if ($post_id) {
                $sql .= "post_id = ? AND ";
                $params2 = [$post_id];
            } else {
                $sql .= "video_id = ? AND ";
                $params2 = [$video_id];
            }
            $sql .= "user_ip = ?";
            $params2[] = $user_ip;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params2);
            $liked = $stmt->fetch() ? true : false;
            
            echo json_encode([
                'success' => true,
                'liked' => $liked,
                'count' => $count
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>