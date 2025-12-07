<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : null;
    $video_id = isset($_POST['video_id']) ? (int)$_POST['video_id'] : null;
    $author_name = trim($_POST['author_name']);
    $author_email = trim($_POST['author_email']);
    $content = trim($_POST['content']);

    // Basic validation
    if (empty($author_name) || empty($content)) {
        throw new Exception('Name and comment are required');
    }

    if (!filter_var($author_email, FILTER_VALIDATE_EMAIL) && !empty($author_email)) {
        throw new Exception('Please provide a valid email address');
    }

    // Insert comment
    $stmt = $pdo->prepare("INSERT INTO comments (post_id, video_id, author_name, author_email, content, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$post_id, $video_id, $author_name, $author_email, $content]);

    echo json_encode([
        'success' => true,
        'message' => 'Comment submitted successfully! It will be visible after approval.'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>