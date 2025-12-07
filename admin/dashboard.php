<?php
require_once '../includes/config.php';
checkAdminAuth();

$page_title = "Dashboard";

// Get statistics with error handling
try {
    // Total posts
    $stmt = $pdo->query("SELECT COUNT(*) FROM posts");
    $total_posts = $stmt->fetchColumn();

    // Total videos
    $stmt = $pdo->query("SELECT COUNT(*) FROM videos");
    $total_videos = $stmt->fetchColumn();

    // Total gallery items
    $stmt = $pdo->query("SELECT COUNT(*) FROM gallery");
    $total_gallery = $stmt->fetchColumn();

    // Pending comments
    $stmt = $pdo->query("SELECT COUNT(*) FROM comments WHERE status = 'pending'");
    $pending_comments = $stmt->fetchColumn();

    // Recent posts
    $stmt = $pdo->query("SELECT * FROM posts ORDER BY created_at DESC LIMIT 5");
    $recent_posts = $stmt->fetchAll();

    // Recent comments
    $stmt = $pdo->query("
        SELECT c.*, p.title as post_title 
        FROM comments c 
        LEFT JOIN posts p ON c.post_id = p.id 
        ORDER BY c.created_at DESC LIMIT 5
    ");
    $recent_comments = $stmt->fetchAll();

    // Recent videos
    $stmt = $pdo->query("SELECT * FROM videos ORDER BY created_at DESC LIMIT 3");
    $recent_videos = $stmt->fetchAll();

    // Additional stats for mobile
    $published_posts = $pdo->query("SELECT COUNT(*) FROM posts WHERE status = 'published'")->fetchColumn();
    $total_views = $pdo->query("SELECT SUM(views) FROM posts")->fetchColumn() ?: 0;
    $total_likes = $pdo->query("SELECT SUM(likes) FROM posts")->fetchColumn() ?: 0;

} catch (PDOException $e) {
    $message = "Database error: " . $e->getMessage();
    $message_type = "error";
    // Initialize empty arrays to prevent errors
    $recent_posts = [];
    $recent_comments = [];
    $recent_videos = [];
    $total_posts = $total_videos = $total_gallery = $pending_comments = 0;
}
?>

<?php include 'includes/admin-header.php'; ?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card fade-in" data-aos="fade-up">
        <div class="stat-icon posts">
            <i class="fas fa-newspaper" aria-hidden="true"></i>
        </div>
        <div class="stat-content">
            <h3>Total Posts</h3>
            <div class="stat-number"><?php echo $total_posts; ?></div>
            <div class="stat-change positive">
                <i class="fas fa-arrow-up" aria-hidden="true"></i>
                <?php echo $published_posts ?? $total_posts; ?> Published
            </div>
        </div>
    </div>

    <div class="stat-card fade-in" data-aos="fade-up" data-aos-delay="100">
        <div class="stat-icon videos">
            <i class="fas fa-video" aria-hidden="true"></i>
        </div>
        <div class="stat-content">
            <h3>Total Videos</h3>
            <div class="stat-number"><?php echo $total_videos; ?></div>
            <div class="stat-change positive">
                <i class="fas fa-play" aria-hidden="true"></i>
                Embedded
            </div>
        </div>
    </div>

    <div class="stat-card fade-in" data-aos="fade-up" data-aos-delay="200">
        <div class="stat-icon gallery">
            <i class="fas fa-images" aria-hidden="true"></i>
        </div>
        <div class="stat-content">
            <h3>Gallery Items</h3>
            <div class="stat-number"><?php echo $total_gallery; ?></div>
            <div class="stat-change positive">
                <i class="fas fa-image" aria-hidden="true"></i>
                Uploaded
            </div>
        </div>
    </div>

    <div class="stat-card fade-in" data-aos="fade-up" data-aos-delay="300">
        <div class="stat-icon comments">
            <i class="fas fa-comments" aria-hidden="true"></i>
        </div>
        <div class="stat-content">
            <h3>Pending Comments</h3>
            <div class="stat-number"><?php echo $pending_comments; ?></div>
            <div class="stat-change <?php echo $pending_comments > 0 ? 'negative' : 'positive'; ?>">
                <i class="fas fa-<?php echo $pending_comments > 0 ? 'exclamation' : 'check'; ?>-circle" aria-hidden="true"></i>
                <?php echo $pending_comments > 0 ? 'Needs review' : 'All clear'; ?>
            </div>
        </div>
    </div>
</div>

<!-- Additional Mobile Stats (hidden on desktop) -->
<div class="mobile-stats-grid" style="display: none;">
    <div class="stat-card">
        <div class="stat-content">
            <h3>Total Views</h3>
            <div class="stat-number"><?php echo number_format($total_views); ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-content">
            <h3>Total Likes</h3>
            <div class="stat-number"><?php echo number_format($total_likes); ?></div>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <!-- Left Column -->
    <div class="dashboard-left">
        <!-- Quick Actions -->
        <div class="card fade-in" data-aos="fade-right">
            <div class="card-header">
                <h2>Quick Actions</h2>
                <span class="card-subtitle">Manage your content</span>
            </div>
            <div class="card-body">
                <div class="quick-actions-grid">
                    <a href="manage-posts.php?action=create" class="quick-action-btn">
                        <div class="quick-action-icon">
                            <i class="fas fa-plus-circle" aria-hidden="true"></i>
                        </div>
                        <span>Add New Post</span>
                    </a>
                    <a href="manage-videos.php?action=create" class="quick-action-btn">
                        <div class="quick-action-icon">
                            <i class="fas fa-video" aria-hidden="true"></i>
                        </div>
                        <span>Add New Video</span>
                    </a>
                    <a href="manage-gallery.php?action=create" class="quick-action-btn">
                        <div class="quick-action-icon">
                            <i class="fas fa-images" aria-hidden="true"></i>
                        </div>
                        <span>Add Gallery Image</span>
                    </a>
                    <a href="manage-comments.php" class="quick-action-btn">
                        <div class="quick-action-icon">
                            <i class="fas fa-comments" aria-hidden="true"></i>
                        </div>
                        <span>Manage Comments</span>
                        <?php if ($pending_comments > 0): ?>
                            <span class="quick-action-badge"><?php echo $pending_comments; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Posts -->
        <div class="card fade-in" data-aos="fade-right" data-aos-delay="100">
            <div class="card-header">
                <h2>Recent Posts</h2>
                <a href="manage-posts.php" class="btn btn-outline btn-sm">
                    View All
                    <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
            <div class="card-body">
                <?php if ($recent_posts): ?>
                    <div class="table-responsive">
                        <table class="data-table mobile-friendly">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_posts as $post): ?>
                                <tr>
                                    <td>
                                        <div class="post-title">
                                            <strong><?php echo htmlspecialchars($post['title']); ?></strong>
                                            <?php if ($post['featured_image']): ?>
                                                <span class="post-has-image" title="Has featured image">
                                                    <i class="fas fa-image" aria-hidden="true"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $post['status']; ?>">
                                            <?php echo ucfirst($post['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="date-display">
                                            <?php echo date('M j, Y', strtotime($post['created_at'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="manage-posts.php?edit=<?php echo $post['id']; ?>" 
                                               class="btn btn-primary btn-sm"
                                               aria-label="Edit post <?php echo htmlspecialchars($post['title']); ?>">
                                                <i class="fas fa-edit" aria-hidden="true"></i>
                                                <span class="btn-text">Edit</span>
                                            </a>
                                            <a href="../index.php?p=<?php echo $post['id']; ?>" 
                                               target="_blank" 
                                               class="btn btn-outline btn-sm"
                                               aria-label="View post <?php echo htmlspecialchars($post['title']); ?>">
                                                <i class="fas fa-eye" aria-hidden="true"></i>
                                                <span class="btn-text">View</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-newspaper" aria-hidden="true"></i>
                        <h3>No Posts Yet</h3>
                        <p>Create your first blog post to get started.</p>
                        <a href="manage-posts.php?action=create" class="btn btn-primary">
                            <i class="fas fa-plus-circle" aria-hidden="true"></i>
                            Create Post
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column -->
    <div class="dashboard-right">
        <!-- Recent Comments -->
        <div class="card fade-in" data-aos="fade-left">
            <div class="card-header">
                <h2>Recent Comments</h2>
                <a href="manage-comments.php" class="btn btn-outline btn-sm">
                    View All
                    <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
            <div class="card-body">
                <?php if ($recent_comments): ?>
                    <div class="comments-list">
                        <?php foreach ($recent_comments as $comment): ?>
                        <div class="comment-item">
                            <div class="comment-header">
                                <div class="comment-author">
                                    <strong><?php echo htmlspecialchars($comment['author_name']); ?></strong>
                                    <?php if ($comment['author_email']): ?>
                                        <span class="comment-email"><?php echo htmlspecialchars($comment['author_email']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="status-badge status-<?php echo $comment['status']; ?>">
                                    <?php echo ucfirst($comment['status']); ?>
                                </span>
                            </div>
                            <div class="comment-content">
                                <?php echo htmlspecialchars(substr($comment['content'], 0, 100)); ?>
                                <?php if (strlen($comment['content']) > 100): ?>...<?php endif; ?>
                            </div>
                            <div class="comment-footer">
                                <div class="comment-meta">
                                    <span class="comment-on">
                                        On: <?php echo htmlspecialchars($comment['post_title'] ?? 'N/A'); ?>
                                    </span>
                                    <span class="comment-date">
                                        <?php echo date('M j, g:i A', strtotime($comment['created_at'])); ?>
                                    </span>
                                </div>
                                <div class="comment-actions">
                                    <?php if ($comment['status'] === 'pending'): ?>
                                        <a href="manage-comments.php?approve=<?php echo $comment['id']; ?>" 
                                           class="btn btn-success btn-sm"
                                           aria-label="Approve comment from <?php echo htmlspecialchars($comment['author_name']); ?>">
                                            <i class="fas fa-check" aria-hidden="true"></i>
                                            <span class="btn-text">Approve</span>
                                        </a>
                                    <?php endif; ?>
                                    <a href="manage-comments.php?delete=<?php echo $comment['id']; ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Are you sure you want to delete this comment?')"
                                       aria-label="Delete comment from <?php echo htmlspecialchars($comment['author_name']); ?>">
                                        <i class="fas fa-trash" aria-hidden="true"></i>
                                        <span class="btn-text">Delete</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-comments" aria-hidden="true"></i>
                        <h3>No Comments Yet</h3>
                        <p>Comments will appear here when users start engaging.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Videos -->
        <div class="card fade-in" data-aos="fade-left" data-aos-delay="100">
            <div class="card-header">
                <h2>Recent Videos</h2>
                <a href="manage-videos.php" class="btn btn-outline btn-sm">
                    View All
                    <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
            <div class="card-body">
                <?php if ($recent_videos): ?>
                    <div class="videos-grid">
                        <?php foreach ($recent_videos as $video): ?>
                        <div class="video-item">
                            <div class="video-thumbnail">
                                <img src="https://img.youtube.com/vi/<?php echo $video['youtube_id']; ?>/mqdefault.jpg" 
                                     alt="<?php echo htmlspecialchars($video['title']); ?>"
                                     loading="lazy"
                                     onerror="this.src='../assets/images/placeholder-video.jpg'">
                                <div class="video-overlay">
                                    <i class="fas fa-play" aria-hidden="true"></i>
                                </div>
                            </div>
                            <div class="video-info">
                                <h4 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h4>
                                <div class="video-actions">
                                    <a href="manage-videos.php?edit=<?php echo $video['id']; ?>" 
                                       class="btn btn-primary btn-sm">
                                        <i class="fas fa-edit" aria-hidden="true"></i>
                                        Edit
                                    </a>
                                    <a href="../videos.php#video-<?php echo $video['id']; ?>" 
                                       target="_blank" 
                                       class="btn btn-outline btn-sm">
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                        View
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-video" aria-hidden="true"></i>
                        <h3>No Videos Yet</h3>
                        <p>Add your first YouTube video.</p>
                        <a href="manage-videos.php?action=create" class="btn btn-primary">
                            <i class="fas fa-plus-circle" aria-hidden="true"></i>
                            Add Video
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    /* Dashboard specific styles */
    .dashboard-left,
    .dashboard-right {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .quick-actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .quick-action-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 1.5rem 1rem;
        background: var(--admin-border-light);
        border: 2px solid transparent;
        border-radius: 10px;
        text-decoration: none;
        color: var(--admin-text);
        transition: all 0.3s ease;
        text-align: center;
        position: relative;
    }

    .quick-action-btn:hover {
        background: var(--admin-card-bg);
        border-color: var(--admin-accent);
        transform: translateY(-2px);
        box-shadow: var(--admin-shadow-lg);
    }

    .quick-action-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: var(--admin-accent);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 0.75rem;
        color: white;
        font-size: 1.5rem;
    }

    .quick-action-badge {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        background: var(--admin-danger);
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 0.7rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .mobile-stats-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .post-title {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .post-has-image {
        color: var(--admin-accent);
        font-size: 0.8rem;
    }

    .comments-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        max-height: 400px;
        overflow-y: auto;
    }

    .comment-item {
        padding: 1rem;
        border: 1px solid var(--admin-border);
        border-radius: 8px;
        background: var(--admin-bg);
    }

    .comment-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 0.5rem;
    }

    .comment-author {
        flex: 1;
    }

    .comment-email {
        display: block;
        font-size: 0.8rem;
        color: var(--admin-text-light);
        margin-top: 0.25rem;
    }

    .comment-content {
        color: var(--admin-text);
        margin-bottom: 0.75rem;
        line-height: 1.4;
    }

    .comment-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .comment-meta {
        font-size: 0.8rem;
        color: var(--admin-text-light);
    }

    .comment-on,
    .comment-date {
        display: block;
    }

    .comment-actions {
        display: flex;
        gap: 0.25rem;
    }

    .videos-grid {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .video-item {
        display: flex;
        gap: 1rem;
        padding: 1rem;
        border: 1px solid var(--admin-border);
        border-radius: 8px;
        background: var(--admin-bg);
    }

    .video-thumbnail {
        position: relative;
        flex-shrink: 0;
        width: 120px;
        height: 80px;
        border-radius: 6px;
        overflow: hidden;
    }

    .video-thumbnail img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .video-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
    }

    .video-info {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .video-title {
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
        line-height: 1.3;
    }

    .video-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .btn-text {
        display: inline;
    }

    /* Mobile optimizations */
    @media (max-width: 991px) {
        .quick-actions-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .comment-footer {
            flex-direction: column;
            align-items: stretch;
        }

        .comment-actions {
            justify-content: center;
        }
    }

    @media (max-width: 767px) {
        .mobile-stats-grid {
            display: grid;
        }

        .quick-actions-grid {
            grid-template-columns: 1fr;
        }

        .video-item {
            flex-direction: column;
            text-align: center;
        }

        .video-thumbnail {
            width: 100%;
            height: 150px;
        }

        .btn-text {
            display: none;
        }

        .comment-header {
            flex-direction: column;
            gap: 0.5rem;
        }

        .comment-actions {
            justify-content: stretch;
        }

        .comment-actions .btn {
            flex: 1;
            justify-content: center;
        }
    }

    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .quick-action-btn {
            padding: 1rem;
        }

        .quick-action-icon {
            width: 50px;
            height: 50px;
            font-size: 1.25rem;
        }

        .video-thumbnail {
            height: 120px;
        }
    }

    /* Print styles for dashboard */
    @media print {
        .quick-actions-grid,
        .btn-group,
        .comment-actions,
        .video-actions {
            display: none !important;
        }

        .card {
            break-inside: avoid;
        }
    }
</style>

<script>
    // Dashboard enhancements
    document.addEventListener('DOMContentLoaded', function() {
        // Show mobile stats on mobile devices
        if (window.innerWidth <= 767) {
            document.querySelector('.mobile-stats-grid').style.display = 'grid';
        }

        // Add loading states to quick action buttons
        document.querySelectorAll('.quick-action-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!this.getAttribute('href').includes('#')) {
                    this.style.opacity = '0.7';
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                }
            });
        });

        // Enhance table responsiveness
        const tables = document.querySelectorAll('.data-table');
        tables.forEach(table => {
            if (!table.closest('.table-responsive')) {
                const wrapper = document.createElement('div');
                wrapper.className = 'table-responsive';
                table.parentNode.insertBefore(wrapper, table);
                wrapper.appendChild(table);
            }
        });

        // Auto-refresh comments every 30 seconds
        setInterval(() => {
            // In a real implementation, you'd fetch new comments via AJAX
            console.log('Auto-refresh check for new comments');
        }, 30000);
    });

    // Simple animations
    if (window.IntersectionObserver) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        });

        document.querySelectorAll('.fade-in').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });
    }
</script>

<?php include 'includes/admin-footer.php'; ?>

<!-- Reset Admin Password Link (for development only) -->
<div class="dev-tools no-print">
    <a href="http://localhost/shaam/tools/reset_admin_password.php?user=admin&pass=123456m" 
       class="btn btn-danger btn-sm dev-reset" 
       target="_blank" 
       rel="noopener"
       style="display: flex; align-items: center; position: fixed; bottom: 1rem; right: 1rem; z-index: 1000;">
        <i class="fas fa-lock" style="margin-right: 0.5rem;"></i>
        Reset Admin Password
    </a>
</div>