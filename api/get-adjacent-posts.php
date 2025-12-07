<?php
require_once '../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($post_id <= 0) {
    echo json_encode(['previous' => null, 'next' => null]);
    exit;
}

try {
    // Get current post's creation date
    $stmt = $pdo->prepare("SELECT created_at FROM posts WHERE id = ?");
    $stmt->execute([$post_id]);
    $current_post = $stmt->fetch();
    
    if (!$current_post) {
        echo json_encode(['previous' => null, 'next' => null]);
        exit;
    }
    
    // Get previous post (older)
    $prevStmt = $pdo->prepare("
        SELECT id, title 
        FROM posts 
        WHERE created_at < ? AND status = 'published' 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $prevStmt->execute([$current_post['created_at']]);
    $previous = $prevStmt->fetch();
    
    // Get next post (newer)
    $nextStmt = $pdo->prepare("
        SELECT id, title 
        FROM posts 
        WHERE created_at > ? AND status = 'published' 
        ORDER BY created_at ASC 
        LIMIT 1
    ");
    $nextStmt->execute([$current_post['created_at']]);
    $next = $nextStmt->fetch();
    
    echo json_encode([
        'previous' => $previous ?: null,
        'next' => $next ?: null
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (PDOException $e) {
    echo json_encode(['previous' => null, 'next' => null]);
}
?>