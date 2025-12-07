<?php
session_start();
require_once '../includes/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$message_type = '';

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $youtube_url = trim($_POST['youtube_url']);
    $description = trim($_POST['description']);
    $video_id = isset($_POST['video_id']) ? (int)$_POST['video_id'] : 0;

    // Extract YouTube ID from URL
    $youtube_id = getYouTubeId($youtube_url);

    // Server-side validation for required fields
    if (empty($title)) {
        $message = 'Video title is required.';
        $message_type = 'error';
    }

    // Generate a safe slug and ensure uniqueness
    $slug = generateSlug($title);
    if (empty($slug)) {
        $slug = 'video-' . time();
    }
    $base_slug = $slug;
    $counter = 1;
    try {
        while (true) {
            if ($video_id > 0) {
                $chk = $pdo->prepare("SELECT id FROM videos WHERE slug = ? AND id != ? LIMIT 1");
                $chk->execute([$slug, $video_id]);
            } else {
                $chk = $pdo->prepare("SELECT id FROM videos WHERE slug = ? LIMIT 1");
                $chk->execute([$slug]);
            }
            if (!$chk->fetch()) break;
            $slug = $base_slug . '-' . $counter++;
        }
    } catch (PDOException $e) {
        // If slug check fails, fallback to timestamped slug
        $slug = $base_slug . '-' . time();
    }

    if (empty($youtube_id)) {
        $message = 'Invalid YouTube URL. Please provide a valid YouTube video link.';
        $message_type = 'error';
    } else {
        try {
            if ($video_id > 0) {
                // Update existing video
                $stmt = $pdo->prepare("UPDATE videos SET title = ?, slug = ?, youtube_url = ?, youtube_id = ?, description = ? WHERE id = ?");
                $stmt->execute([$title, $slug, $youtube_url, $youtube_id, $description, $video_id]);
                $message = 'Video updated successfully!';
            } else {
                // Create new video
                $stmt = $pdo->prepare("INSERT INTO videos (title, slug, youtube_url, youtube_id, description) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$title, $slug, $youtube_url, $youtube_id, $description]);
                $message = 'Video added successfully!';
            }
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Handle delete action
if (isset($_GET['delete'])) {
    $video_id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM videos WHERE id = ?");
        $stmt->execute([$video_id]);
        $message = 'Video deleted successfully!';
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get video for editing
$edit_video = null;
if (isset($_GET['edit'])) {
    $video_id = (int)$_GET['edit'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM videos WHERE id = ?");
        $stmt->execute([$video_id]);
        $edit_video = $stmt->fetch();
    } catch (PDOException $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get all videos
try {
    $stmt = $pdo->query("SELECT * FROM videos ORDER BY created_at DESC");
    $videos = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<?php include 'includes/admin-header.php'; ?>

<div class="content-header">
    <div class="header-left">
        <h1><?php echo $edit_video ? 'Edit Video' : 'Add New Video'; ?></h1>
        <p class="page-description"><?php echo $edit_video ? 'Update video details' : 'Add a new YouTube video to your collection'; ?></p>
    </div>
    <div class="header-right">
        <a href="manage-videos.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i>
            <span class="btn-text">Back to Videos</span>
        </a>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> fade-in">
        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="videos-manager">
    <!-- Video Form -->
    <div class="card form-card">
        <div class="card-header">
            <h2><?php echo $edit_video ? 'Edit Video' : 'Add New Video'; ?></h2>
            <?php if ($edit_video): ?>
                <div class="video-info-badge">
                    <span class="video-id">ID: <?php echo $edit_video['id']; ?></span>
                    <span class="video-views">
                        <i class="fas fa-eye"></i>
                        <?php echo number_format($edit_video['views'] ?? 0); ?> views
                    </span>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="videoForm">
                <?php if ($edit_video): ?>
                    <input type="hidden" name="video_id" value="<?php echo $edit_video['id']; ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-main-content">
                        <div class="form-group">
                            <label for="title" class="form-label">Video Title *</label>
                            <input type="text" id="title" name="title" class="form-control" 
                                   value="<?php echo $edit_video ? htmlspecialchars($edit_video['title']) : ''; ?>" 
                                   required
                                   placeholder="Enter video title">
                            <div class="form-help">This title will be displayed on your website.</div>
                        </div>

                        <div class="form-group">
                            <label for="youtube_url" class="form-label">YouTube URL *</label>
                            <div class="input-with-button">
                                <input type="url" id="youtube_url" name="youtube_url" class="form-control" 
                                       value="<?php echo $edit_video ? htmlspecialchars($edit_video['youtube_url']) : ''; ?>" 
                                       placeholder="https://www.youtube.com/watch?v=..." 
                                       required>
                                <button type="button" id="previewVideo" class="btn btn-outline">
                                    <i class="fas fa-eye"></i>
                                    Preview
                                </button>
                            </div>
                            <div class="form-help">
                                Supported formats: youtube.com/watch?v=... or youtu.be/...
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description" class="form-label">Description</label>
                            <textarea id="description" name="description" class="form-control" 
                                      rows="6" placeholder="Brief description of the video"><?php echo $edit_video ? htmlspecialchars($edit_video['description']) : ''; ?></textarea>
                            <div class="form-help">This description will be shown below the video player.</div>
                        </div>
                    </div>

                    <div class="form-sidebar">
                        <!-- Video Preview -->
                        <div class="form-section">
                            <h3 class="section-title">Video Preview</h3>
                            <div class="video-preview-container">
                                <?php if ($edit_video): ?>
                                    <div class="video-thumbnail">
                                        <img src="https://img.youtube.com/vi/<?php echo $edit_video['youtube_id']; ?>/maxresdefault.jpg" 
                                             alt="<?php echo htmlspecialchars($edit_video['title']); ?>"
                                             class="video-preview-image"
                                             onerror="this.src='https://img.youtube.com/vi/<?php echo $edit_video['youtube_id']; ?>/hqdefault.jpg'">
                                        <div class="video-overlay">
                                            <i class="fas fa-play"></i>
                                        </div>
                                    </div>
                                    <div class="video-info">
                                        <div class="video-id-display">
                                            <strong>YouTube ID:</strong>
                                            <code><?php echo $edit_video['youtube_id']; ?></code>
                                        </div>
                                        <a href="<?php echo htmlspecialchars($edit_video['youtube_url']); ?>" 
                                           target="_blank" 
                                           class="btn btn-outline btn-sm">
                                            <i class="fas fa-external-link-alt"></i>
                                            Open on YouTube
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="video-preview-placeholder">
                                        <i class="fas fa-video"></i>
                                        <p>Enter a YouTube URL to see preview</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="form-section">
                            <h3 class="section-title">Quick Actions</h3>
                            <div class="quick-actions">
                                <?php if ($edit_video): ?>
                                    <a href="../videos.php#video-<?php echo $edit_video['id']; ?>" 
                                       target="_blank" 
                                       class="btn btn-outline btn-block">
                                        <i class="fas fa-eye"></i>
                                        View on Website
                                    </a>
                                    <a href="manage-videos.php?delete=<?php echo $edit_video['id']; ?>" 
                                       class="btn btn-danger btn-block"
                                       onclick="return confirm('Are you sure you want to delete this video? This action cannot be undone.');">
                                        <i class="fas fa-trash"></i>
                                        Delete Video
                                    </a>
                                <?php else: ?>
                                    <button type="reset" class="btn btn-outline btn-block">
                                        <i class="fas fa-undo"></i>
                                        Reset Form
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Video Statistics -->
                        <?php if ($edit_video): ?>
                        <div class="form-section">
                            <h3 class="section-title">Video Statistics</h3>
                            <div class="video-stats">
                                <div class="stat-item">
                                    <div class="stat-label">Created</div>
                                    <div class="stat-value"><?php echo date('M j, Y', strtotime($edit_video['created_at'])); ?></div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-label">Last Updated</div>
                                    <div class="stat-value"><?php echo date('M j, Y', strtotime($edit_video['updated_at'])); ?></div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-label">Views</div>
                                    <div class="stat-value"><?php echo number_format($edit_video['views'] ?? 0); ?></div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-label">Likes</div>
                                    <div class="stat-value"><?php echo number_format($edit_video['likes'] ?? 0); ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i>
                        <?php echo $edit_video ? 'Update Video' : 'Add Video'; ?>
                    </button>

                    <?php if ($edit_video): ?>
                        <a href="manage-videos.php" class="btn btn-outline">
                            <i class="fas fa-times"></i>
                            Cancel
                        </a>
                    <?php else: ?>
                        <button type="reset" class="btn btn-outline">
                            <i class="fas fa-undo"></i>
                            Reset Form
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Videos List -->
    <?php if (!$edit_video): ?>
    <div class="card videos-list-card">
        <div class="card-header">
            <h2>All Videos (<?php echo count($videos); ?>)</h2>
            <div class="header-actions">
                <div class="search-box">
                    <input type="text" id="videosSearch" placeholder="Search videos..." class="search-input">
                    <i class="fas fa-search search-icon"></i>
                </div>
                <div class="view-controls">
                    <button class="view-btn active" data-view="grid">
                        <i class="fas fa-th"></i>
                    </button>
                    <button class="view-btn" data-view="list">
                        <i class="fas fa-list"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if ($videos): ?>
                <!-- Grid View -->
                <div class="grid-view view-content active">
                    <div class="videos-grid">
                        <?php foreach ($videos as $video): ?>
                        <div class="video-card">
                            <div class="video-card-header">
                                <img src="https://img.youtube.com/vi/<?php echo $video['youtube_id']; ?>/maxresdefault.jpg" 
                                     alt="<?php echo htmlspecialchars($video['title']); ?>"
                                     class="video-card-image"
                                     onerror="this.src='https://img.youtube.com/vi/<?php echo $video['youtube_id']; ?>/hqdefault.jpg'">
                                <div class="video-card-overlay">
                                    <i class="fas fa-play"></i>
                                </div>
                                <div class="video-card-duration">
                                    <i class="fas fa-clock"></i>
                                    <!-- Duration would typically come from YouTube API -->
                                </div>
                            </div>
                            <div class="video-card-body">
                                <h3 class="video-card-title"><?php echo htmlspecialchars($video['title']); ?></h3>
                                <div class="video-card-meta">
                                    <span class="video-date"><?php echo date('M j, Y', strtotime($video['created_at'])); ?></span>
                                    <span class="video-views">
                                        <i class="fas fa-eye"></i>
                                        <?php echo number_format($video['views'] ?? 0); ?>
                                    </span>
                                </div>
                                <?php if ($video['description']): ?>
                                    <p class="video-card-description"><?php echo htmlspecialchars(substr($video['description'], 0, 100)); ?>...</p>
                                <?php endif; ?>
                            </div>
                            <div class="video-card-actions">
                                <a href="manage-videos.php?edit=<?php echo $video['id']; ?>" 
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-edit"></i>
                                    Edit
                                </a>
                                <a href="../videos.php#video-<?php echo $video['id']; ?>" 
                                   target="_blank" 
                                   class="btn btn-outline btn-sm">
                                    <i class="fas fa-eye"></i>
                                    View
                                </a>
                                <a href="manage-videos.php?delete=<?php echo $video['id']; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Are you sure you want to delete this video? This action cannot be undone.');">
                                    <i class="fas fa-trash"></i>
                                    Delete
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- List View -->
                <div class="list-view view-content">
                    <div class="table-responsive">
                        <table class="data-table mobile-friendly">
                            <thead>
                                <tr>
                                    <th width="80">Thumbnail</th>
                                    <th>Title</th>
                                    <th>YouTube ID</th>
                                    <th>Views</th>
                                    <th>Date Added</th>
                                    <th width="180">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($videos as $video): ?>
                                <tr>
                                    <td>
                                        <div class="video-thumbnail-small">
                                            <img src="https://img.youtube.com/vi/<?php echo $video['youtube_id']; ?>/mqdefault.jpg" 
                                                 alt="<?php echo htmlspecialchars($video['title']); ?>"
                                                 class="video-thumb">
                                        </div>
                                    </td>
                                    <td>
                                        <div class="video-title-cell">
                                            <strong><?php echo htmlspecialchars($video['title']); ?></strong>
                                            <?php if ($video['description']): ?>
                                                <div class="video-description-preview">
                                                    <?php echo htmlspecialchars(substr($video['description'], 0, 80)); ?>...
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <code class="youtube-id"><?php echo htmlspecialchars($video['youtube_id']); ?></code>
                                    </td>
                                    <td>
                                        <span class="video-views-count">
                                            <?php echo number_format($video['views'] ?? 0); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="date-display">
                                            <?php echo date('M j, Y', strtotime($video['created_at'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="manage-videos.php?edit=<?php echo $video['id']; ?>" 
                                               class="btn btn-primary btn-sm"
                                               title="Edit video">
                                                <i class="fas fa-edit"></i>
                                                <span class="btn-text">Edit</span>
                                            </a>
                                            <a href="../videos.php#video-<?php echo $video['id']; ?>" 
                                               target="_blank" 
                                               class="btn btn-outline btn-sm"
                                               title="View video">
                                                <i class="fas fa-eye"></i>
                                                <span class="btn-text">View</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-video"></i>
                    <h3>No Videos Found</h3>
                    <p>Get started by adding your first YouTube video.</p>
                    <a href="manage-videos.php?action=create" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i>
                        Add Your First Video
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
    /* Videos Manager Specific Styles */
    .videos-manager {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }

    .video-info-badge {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .video-id {
        font-size: 0.8rem;
        color: var(--admin-text-light);
        background: var(--admin-bg);
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
    }

    .video-views {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.8rem;
        color: var(--admin-text-light);
    }

    .input-with-button {
        display: flex;
        gap: 0.5rem;
    }

    .input-with-button .form-control {
        flex: 1;
    }

    .video-preview-container {
        text-align: center;
    }

    .video-thumbnail {
        position: relative;
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 1rem;
    }

    .video-preview-image {
        width: 100%;
        height: auto;
        border-radius: 8px;
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
        font-size: 2rem;
        opacity: 0;
        transition: opacity 0.3s;
    }

    .video-thumbnail:hover .video-overlay {
        opacity: 1;
    }

    .video-info {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        align-items: center;
    }

    .video-id-display {
        font-size: 0.9rem;
    }

    .video-id-display code {
        background: var(--admin-bg);
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-family: monospace;
    }

    .video-preview-placeholder {
        padding: 3rem 2rem;
        background: var(--admin-bg);
        border: 2px dashed var(--admin-border);
        border-radius: 8px;
        color: var(--admin-text-light);
        text-align: center;
    }

    .video-preview-placeholder i {
        font-size: 3rem;
        margin-bottom: 1rem;
        display: block;
    }

    .quick-actions {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .btn-block {
        width: 100%;
        justify-content: center;
    }

    .video-stats {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .stat-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--admin-border-light);
    }

    .stat-label {
        font-size: 0.9rem;
        color: var(--admin-text-light);
    }

    .stat-value {
        font-weight: 600;
        color: var(--admin-text);
    }

    /* Videos Grid View */
    .videos-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
    }

    .video-card {
        border: 1px solid var(--admin-border);
        border-radius: 8px;
        overflow: hidden;
        background: white;
        transition: all 0.3s ease;
    }

    .video-card:hover {
        box-shadow: var(--admin-shadow-lg);
        transform: translateY(-2px);
    }

    .video-card-header {
        position: relative;
        height: 160px;
        overflow: hidden;
    }

    .video-card-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .video-card-overlay {
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
        font-size: 2rem;
        opacity: 0;
        transition: opacity 0.3s;
    }

    .video-card:hover .video-card-overlay {
        opacity: 1;
    }

    .video-card-duration {
        position: absolute;
        bottom: 0.5rem;
        right: 0.5rem;
        background: rgba(0,0,0,0.8);
        color: white;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.8rem;
    }

    .video-card-body {
        padding: 1.5rem;
    }

    .video-card-title {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.75rem;
        line-height: 1.3;
        color: var(--admin-text);
    }

    .video-card-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
        font-size: 0.8rem;
        color: var(--admin-text-light);
    }

    .video-card-description {
        font-size: 0.9rem;
        color: var(--admin-text);
        line-height: 1.4;
        margin-bottom: 0;
    }

    .video-card-actions {
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--admin-border);
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    /* List View Styles */
    .video-thumbnail-small {
        width: 60px;
        height: 45px;
        border-radius: 4px;
        overflow: hidden;
    }

    .video-thumb {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .video-title-cell {
        max-width: 300px;
    }

    .video-description-preview {
        font-size: 0.8rem;
        color: var(--admin-text-light);
        margin-top: 0.25rem;
        line-height: 1.3;
    }

    .youtube-id {
        background: var(--admin-bg);
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-family: monospace;
        font-size: 0.8rem;
    }

    .video-views-count {
        font-weight: 600;
        color: var(--admin-text);
    }

    /* Mobile Responsive Styles */
    @media (max-width: 991px) {
        .form-grid {
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        .form-sidebar {
            position: static;
        }

        .videos-grid {
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        }
    }

    @media (max-width: 767px) {
        .content-header {
            flex-direction: column;
            align-items: stretch;
        }

        .header-right {
            align-self: stretch;
        }

        .header-right .btn {
            width: 100%;
            justify-content: center;
        }

        .header-actions {
            flex-direction: column;
            align-items: stretch;
            width: 100%;
        }

        .search-input {
            width: 100%;
        }

        .input-with-button {
            flex-direction: column;
        }

        .form-actions {
            flex-direction: column;
        }

        .form-actions .btn {
            width: 100%;
            justify-content: center;
        }

        .videos-grid {
            grid-template-columns: 1fr;
        }

        .video-card-actions {
            flex-direction: column;
        }

        .video-card-actions .btn {
            width: 100%;
            justify-content: center;
        }

        .actions {
            flex-direction: column;
        }

        .actions .btn {
            width: 100%;
            justify-content: center;
            margin-bottom: 0.25rem;
        }

        .btn-text {
            display: inline;
        }
    }

    @media (max-width: 480px) {
        .form-section {
            padding: 1rem;
        }

        .video-card-body {
            padding: 1rem;
        }

        .video-card-actions {
            padding: 0.75rem 1rem;
        }

        .view-controls {
            align-self: center;
        }

        .video-thumbnail-small {
            width: 50px;
            height: 38px;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // YouTube URL validation and preview
        const youtubeUrlInput = document.getElementById('youtube_url');
        const previewButton = document.getElementById('previewVideo');
        const previewContainer = document.querySelector('.video-preview-container');

        function extractYouTubeId(url) {
            const regExp = /^.*((youtu.be\/)|(v\/)|(\/u\/\w\/)|(embed\/)|(watch\?))\??v?=?([^#&?]*).*/;
            const match = url.match(regExp);
            return (match && match[7].length === 11) ? match[7] : false;
        }

        function updateVideoPreview() {
            const url = youtubeUrlInput.value;
            const youtubeId = extractYouTubeId(url);
            
            if (youtubeId) {
                // Remove existing placeholder or preview
                const existingPlaceholder = previewContainer.querySelector('.video-preview-placeholder');
                const existingPreview = previewContainer.querySelector('.video-thumbnail');
                
                if (existingPlaceholder) existingPlaceholder.remove();
                if (existingPreview) existingPreview.remove();
                
                // Create new preview
                const thumbnailContainer = document.createElement('div');
                thumbnailContainer.className = 'video-thumbnail';
                
                const img = document.createElement('img');
                img.src = `https://img.youtube.com/vi/${youtubeId}/maxresdefault.jpg`;
                img.alt = 'YouTube thumbnail preview';
                img.className = 'video-preview-image';
                img.onerror = function() {
                    this.src = `https://img.youtube.com/vi/${youtubeId}/hqdefault.jpg`;
                };
                
                const overlay = document.createElement('div');
                overlay.className = 'video-overlay';
                overlay.innerHTML = '<i class="fas fa-play"></i>';
                
                const info = document.createElement('div');
                info.className = 'video-info';
                info.innerHTML = `
                    <div class="video-id-display">
                        <strong>YouTube ID:</strong>
                        <code>${youtubeId}</code>
                    </div>
                    <a href="${url}" target="_blank" class="btn btn-outline btn-sm">
                        <i class="fas fa-external-link-alt"></i>
                        Open on YouTube
                    </a>
                `;
                
                thumbnailContainer.appendChild(img);
                thumbnailContainer.appendChild(overlay);
                previewContainer.appendChild(thumbnailContainer);
                previewContainer.appendChild(info);
            } else if (youtubeUrlInput.value && !previewContainer.querySelector('.video-preview-placeholder')) {
                // Show error state
                const existingPlaceholder = previewContainer.querySelector('.video-preview-placeholder');
                const existingPreview = previewContainer.querySelector('.video-thumbnail');
                
                if (existingPlaceholder) existingPlaceholder.remove();
                if (existingPreview) existingPreview.remove();
                
                const errorPlaceholder = document.createElement('div');
                errorPlaceholder.className = 'video-preview-placeholder';
                errorPlaceholder.innerHTML = `
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Invalid YouTube URL</p>
                    <small>Please enter a valid YouTube video URL</small>
                `;
                previewContainer.appendChild(errorPlaceholder);
            }
        }

        if (youtubeUrlInput) {
            youtubeUrlInput.addEventListener('input', updateVideoPreview);
            youtubeUrlInput.addEventListener('blur', updateVideoPreview);
        }

        if (previewButton) {
            previewButton.addEventListener('click', updateVideoPreview);
        }

        // View controls for videos list
        const viewBtns = document.querySelectorAll('.view-btn');
        const viewContents = document.querySelectorAll('.view-content');

        viewBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const viewType = this.dataset.view;
                
                // Update active button
                viewBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Show corresponding content
                viewContents.forEach(content => content.classList.remove('active'));
                document.querySelector(`.${viewType}-view`).classList.add('active');
                
                // Save preference
                localStorage.setItem('videosView', viewType);
            });
        });

        // Restore view preference
        const savedView = localStorage.getItem('videosView') || 'grid';
        const savedBtn = document.querySelector(`.view-btn[data-view="${savedView}"]`);
        if (savedBtn) {
            savedBtn.click();
        }

        // Search functionality
        const searchInput = document.getElementById('videosSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const videoCards = document.querySelectorAll('.video-card, .list-view tbody tr');
                
                videoCards.forEach(card => {
                    const text = card.textContent.toLowerCase();
                    card.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
        }

        // Form validation
        const videoForm = document.getElementById('videoForm');
        if (videoForm) {
            videoForm.addEventListener('submit', function(e) {
                const title = document.getElementById('title').value.trim();
                const youtubeUrl = document.getElementById('youtube_url').value.trim();
                const youtubeId = extractYouTubeId(youtubeUrl);
                
                if (!title) {
                    e.preventDefault();
                    alert('Please enter a video title');
                    document.getElementById('title').focus();
                    return;
                }
                
                if (!youtubeId) {
                    e.preventDefault();
                    alert('Please enter a valid YouTube URL');
                    document.getElementById('youtube_url').focus();
                    return;
                }
            });
        }

        // Auto-generate title from YouTube (if possible)
        // This would typically require YouTube API integration
        // For now, we'll just provide a placeholder function
        function fetchVideoTitle(youtubeId) {
            // In a real implementation, you'd use YouTube API
            console.log('Fetching video title for:', youtubeId);
            // This would be an API call to get video details
        }

        // Enhanced YouTube URL detection
        youtubeUrlInput?.addEventListener('paste', function(e) {
            setTimeout(() => {
                const url = this.value;
                const youtubeId = extractYouTubeId(url);
                if (youtubeId) {
                    // Optionally fetch video title from YouTube API
                    // fetchVideoTitle(youtubeId);
                }
            }, 100);
        });

        // Responsive table enhancements
        const tables = document.querySelectorAll('.data-table');
        tables.forEach(table => {
            if (!table.closest('.table-responsive')) {
                const wrapper = document.createElement('div');
                wrapper.className = 'table-responsive';
                table.parentNode.insertBefore(wrapper, table);
                wrapper.appendChild(table);
            }
        });
    });
</script>

<?php include 'includes/admin-footer.php'; ?>