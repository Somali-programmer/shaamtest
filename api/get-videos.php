<?php
require_once '../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    
    $stmt = $pdo->prepare("SELECT * FROM videos ORDER BY created_at DESC LIMIT ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($videos, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>