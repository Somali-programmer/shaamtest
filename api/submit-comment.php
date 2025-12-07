<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

// Accept JSON payload or form POST
$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?? $_POST;

$video_input = isset($data['video_id']) ? trim($data['video_id']) : null;
$author_name = isset($data['author_name']) ? sanitizeInput($data['author_name']) : '';
$author_email = isset($data['author_email']) ? sanitizeInput($data['author_email']) : '';
$content = isset($data['content']) ? trim($data['content']) : '';

// Basic validation
if (empty($author_name) || empty($content)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Name and comment content are required.']);
    exit;
}

// Resolve video_id: frontend may send YouTube ID (11 chars) or DB id
$video_id = null;
if (!empty($video_input)) {
    // if numeric, treat as DB id
    if (is_numeric($video_input)) {
        $video_id = (int)$video_input;
    } else {
        // try to resolve youtube_id -> videos.id
        try {
            $stmt = $pdo->prepare('SELECT id FROM videos WHERE youtube_id = ? LIMIT 1');
            $stmt->execute([$video_input]);
            $found = $stmt->fetch();
            if ($found) $video_id = (int)$found['id'];
        } catch (PDOException $e) {
            // ignore and leave video_id null
        }
    }
}

// Determine author IP
$author_ip = $_SERVER['REMOTE_ADDR'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '0.0.0.0');

// Insert comment as 'pending'
try {
    $ins = $pdo->prepare('INSERT INTO comments (post_id, video_id, parent_id, author_name, author_email, author_website, author_ip, content, status, likes, is_admin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $ins->execute([
        null,
        $video_id,
        null,
        $author_name,
        $author_email ?: null,
        null,
        $author_ip,
        $content,
        'pending',
        0,
        false
    ]);

    // Optionally log activity (best-effort)
    try { logActivity($pdo, 0, 'new_comment', 'New comment submitted', 'comments', $pdo->lastInsertId()); } catch (Exception $e) {}

    echo json_encode(['success' => true, 'message' => 'Thank you — your comment was submitted and will appear after approval.']);
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

?>
