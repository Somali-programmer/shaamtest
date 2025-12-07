<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// In a real application, you would have a likes table and track user likes.
// For simplicity, we'll just simulate like counting.

$post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : null;
$video_id = isset($_POST['video_id']) ? (int)$_POST['video_id'] : null;

// Here you would typically:
// 1. Check if the user has already liked (using session or user account)
// 2. Insert or delete a like record
// 3. Count the total likes

// Since we don't have a likes table, we'll just return a random number for demonstration.

$likes = rand(5, 50);

echo json_encode([
    'success' => true,
    'likes' => $likes
]);
?>