<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$video_db_id = isset($input['video_db_id']) && $input['video_db_id'] !== '' ? (int)$input['video_db_id'] : null;
$youtube_id = isset($input['youtube_id']) ? trim($input['youtube_id']) : null;
$url = isset($input['url']) ? trim($input['url']) : null;

try {
    // Prefer storing in video_plays table if it exists
    $tableExists = false;
    $res = $pdo->query("SHOW TABLES LIKE 'video_plays'")->fetch();
    if ($res) $tableExists = true;

    if ($tableExists) {
        $stmt = $pdo->prepare("INSERT INTO video_plays (video_id, youtube_id, url, played_at, ip_address, user_agent) VALUES (?, ?, ?, NOW(), ?, ?)");
        $stmt->execute([$video_db_id, $youtube_id, $url, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
    } else {
        // Fallback to activity_logs table
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, table_name, record_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $desc = 'Video played: youtube_id=' . $youtube_id . ' url=' . $url;
        $stmt->execute([0, 'video_play', $desc, 'videos', $video_db_id, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
    }

    echo json_encode(['status' => 'ok']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

?>
