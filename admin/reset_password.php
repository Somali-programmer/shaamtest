<?php
require_once '../includes/config.php';

// Reset admin password to 'admin123' with proper bcrypt hash
$new_password = 'admin123';
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("UPDATE admin_users SET password = ?, login_attempts = 0 WHERE username = 'admin'");
    $stmt->execute([$hashed_password]);
    
    echo "Password reset successfully!<br>";
    echo "New password: <strong>admin123</strong><br>";
    echo "Hashed password stored in database.<br>";
    echo "<a href='login.php'>Go to Login</a>";
    
    // Delete this file after use for security
    echo "<script>setTimeout(function(){ alert('Please delete this reset_password.php file for security!'); }, 1000);</script>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>