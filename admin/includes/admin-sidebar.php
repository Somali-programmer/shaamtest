<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-podcast"></i>
            <span>Shaam-Show</span>
        </div>
        <small>Admin Panel</small>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="manage-posts.php" class="nav-link <?php echo $current_page == 'manage-posts.php' ? 'active' : ''; ?>">
                    <i class="fas fa-newspaper"></i>
                    <span>Manage Posts</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="manage-videos.php" class="nav-link <?php echo $current_page == 'manage-videos.php' ? 'active' : ''; ?>">
                    <i class="fas fa-video"></i>
                    <span>Manage Videos</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="manage-gallery.php" class="nav-link <?php echo $current_page == 'manage-gallery.php' ? 'active' : ''; ?>">
                    <i class="fas fa-images"></i>
                    <span>Manage Gallery</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="manage-comments.php" class="nav-link <?php echo $current_page == 'manage-comments.php' ? 'active' : ''; ?>">
                    <i class="fas fa-comments"></i>
                    <span>Manage Comments</span>
                    <?php
                    // Show pending comments count
                    try {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE status = 'pending'");
                        $stmt->execute();
                        $pending_count = $stmt->fetchColumn();
                        if ($pending_count > 0) {
                            echo '<span class="nav-badge">' . $pending_count . '</span>';
                        }
                    } catch (PDOException $e) {
                        // Silently fail
                    }
                    ?>
                </a>
            </li>

            <li class="nav-item">
                <a href="likes-report.php" class="nav-link <?php echo $current_page == 'likes-report.php' ? 'active' : ''; ?>">
                    <i class="fas fa-heart"></i>
                    <span>Likes Report</span>
                </a>
            </li>
            
            <li class="nav-section">
                <span>Tools</span>
            </li>
            
            <li class="nav-item">
                <a href="../frontend/index.html" target="_blank" class="nav-link">
                    <i class="fas fa-external-link-alt"></i>
                    <span>View Website</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="sidebar-footer">
        <div class="server-info">
            <small>PHP: <?php echo PHP_VERSION; ?></small>
            <small>MySQL: Connected</small>
        </div>
    </div>
</aside>