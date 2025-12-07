<?php
require_once '../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$post_id = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;
$video_id = isset($_GET['video_id']) ? (int)$_GET['video_id'] : 0;

try {
    if ($post_id > 0) {
        $stmt = $pdo->prepare("
            SELECT * FROM comments 
            WHERE post_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$post_id]);
    } elseif ($video_id > 0) {
        $stmt = $pdo->prepare("
            SELECT * FROM comments 
            WHERE video_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$video_id]);
    } else {
        echo json_encode([]);
        exit;
    }
    
    $comments = $stmt->fetchAll();
    echo json_encode($comments, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>