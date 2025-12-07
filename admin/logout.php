<?php
require_once '../includes/config.php';

// Check if user is logged in
if (isset($_SESSION['admin_logged_in'])) {
    $admin_id = $_SESSION['admin_id'] ?? 0;
    $admin_username = $_SESSION['admin_username'] ?? 'Unknown';
    
    // Log the logout activity
    logActivity($pdo, $admin_id, 'logout', 'Admin user logged out');
}

// Completely destroy the session
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session
session_destroy();

// Redirect to login page
header('Location: login.php');
exit;
?>