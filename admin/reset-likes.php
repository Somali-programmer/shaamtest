<?php
require_once __DIR__ . '/includes/admin-header.php';
checkAdminAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: likes-report.php');
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$type = isset($_POST['type']) && in_array($_POST['type'], ['post','video']) ? $_POST['type'] : '';

if (!$id || !$type) {
    $_SESSION['notification'] = ['type' => 'error', 'message' => 'Invalid parameters'];
    header('Location: likes-report.php');
    exit;
}

try {
    if ($type === 'post') {
        $pdo->prepare('DELETE FROM likes WHERE post_id = ?')->execute([$id]);
        $pdo->prepare('UPDATE posts SET likes = 0 WHERE id = ?')->execute([$id]);
    } else {
        $pdo->prepare('DELETE FROM likes WHERE video_id = ?')->execute([$id]);
        $pdo->prepare('UPDATE videos SET likes = 0 WHERE id = ?')->execute([$id]);
    }
    $_SESSION['notification'] = ['type' => 'success', 'message' => 'Likes reset successfully'];
} catch (PDOException $e) {
    $_SESSION['notification'] = ['type' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
}

header('Location: likes-report.php');
exit;

?>
