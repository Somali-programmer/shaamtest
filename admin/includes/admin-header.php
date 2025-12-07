<?php
$current_page = basename($_SERVER['PHP_SELF']);
$admin_name = $_SESSION['admin_username'] ?? 'Admin';

// Set default page title if not defined
if (!isset($page_title)) {
    $page_title = 'Dashboard';
}

// Check if we're on mobile
$is_mobile = false;
if (isset($_SERVER['HTTP_USER_AGENT'])) {
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $mobile_agents = [
        'Android', 'webOS', 'iPhone', 'iPad', 'iPod', 'BlackBerry', 
        'Windows Phone', 'Mobile', 'Tablet'
    ];
    foreach ($mobile_agents as $agent) {
        if (stripos($user_agent, $agent) !== false) {
            $is_mobile = true;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-mobile="<?php echo $is_mobile ? 'true' : 'false'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title) . ' - Shaam-Show Admin'; ?></title>
    <meta name="description" content="Shaam-Show Admin Panel">
    
    <!-- Preload critical resources -->
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    
    <!-- Theme Color for Mobile -->
    <meta name="theme-color" content="#FF6B35">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    
    <?php if (isset($additional_css)): ?>
        <?php echo $additional_css; ?>
    <?php endif; ?>
</head>
<body class="<?php echo $is_mobile ? 'mobile-device' : 'desktop-device'; ?>">
    <!-- Mobile Overlay -->
    <div class="mobile-sidebar-overlay" id="mobileSidebarOverlay" aria-hidden="true"></div>
    
    <!-- Loading Overlay (for async operations) -->
    <div class="loading-overlay" id="globalLoading" style="display: none;">
        <div class="loading-spinner"></div>
    </div>
    
    <!-- Admin Container -->
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include 'admin-sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Header -->
            <div class="admin-header">
                <div class="header-left">
                    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar" aria-expanded="false">
                        <i class="fas fa-bars"></i>
                        <span class="sr-only">Toggle sidebar</span>
                    </button>
                    <h1><?php echo htmlspecialchars($page_title); ?></h1>
                </div>
                <div class="header-right">
                    <div class="admin-user" role="button" aria-label="User menu" tabindex="0">
                        <i class="fas fa-user-circle" aria-hidden="true"></i>
                        <span><?php echo htmlspecialchars($admin_name); ?></span>
                        <div class="user-dropdown">
                            <a href="../index.php" target="_blank" rel="noopener">
                                <i class="fas fa-external-link-alt"></i>
                                View Website
                            </a>
                            <a href="logout.php">
                                <i class="fas fa-sign-out-alt"></i>
                                Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <?php if (isset($message) && isset($message_type)): ?>
                    <div class="alert alert-<?php echo $message_type; ?> fade-in" role="alert">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>