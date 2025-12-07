<?php
require_once '../includes/config.php';
checkAdminAuth();

$page_title = "Manage Posts";
$message = '';
$message_type = '';

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $excerpt = trim($_POST['excerpt']);
    $category_id = (int)$_POST['category_id'];
    $tags = $_POST['tags'] ? json_encode(explode(',', $_POST['tags'])) : '[]';
    $reading_time = (int)$_POST['reading_time'];
    $status = $_POST['status'];
    $meta_title = trim($_POST['meta_title']);
    $meta_description = trim($_POST['meta_description']);
    $meta_keywords = trim($_POST['meta_keywords']);
    $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;

    // Generate slug from title
    $slug = generateSlug($title);

    // Set published_at if status is published and it's a new post or status changed to published
    $published_at = null;
    if ($status === 'published') {
        $published_at = date('Y-m-d H:i:s');
    }

    try {
        // Handle file upload
        $featured_image = null;
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = UPLOAD_PATH;
            $file_type = $_FILES['featured_image']['type'];
            
            if (in_array($file_type, ALLOWED_IMAGE_TYPES)) {
                $file_extension = pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION);
                $filename = 'post_' . time() . '_' . uniqid() . '.' . $file_extension;
                $file_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $file_path)) {
                    $featured_image = $filename;

                    // Log file upload
                    logActivity($pdo, $_SESSION['admin_id'], 'upload', 'Uploaded featured image: ' . $filename, 'posts', $post_id);
                }
            }
        }

        if ($post_id > 0) {
            // Update existing post
            // First, get the current post to check if status is changing to published
            $stmt = $pdo->prepare("SELECT status FROM posts WHERE id = ?");
            $stmt->execute([$post_id]);
            $current_post = $stmt->fetch();

            // If status is changing to published and it wasn't published before, set published_at
            if ($status === 'published' && $current_post['status'] !== 'published') {
                $published_at = date('Y-m-d H:i:s');
            }

            $sql = "UPDATE posts SET title = ?, slug = ?, content = ?, excerpt = ?, category_id = ?, tags = ?, reading_time = ?, status = ?, meta_title = ?, meta_description = ?, meta_keywords = ?, published_at = ?";
            $params = [$title, $slug, $content, $excerpt, $category_id, $tags, $reading_time, $status, $meta_title, $meta_description, $meta_keywords, $published_at];

            if ($featured_image) {
                $sql .= ", featured_image = ?";
                $params[] = $featured_image;
            }

            $sql .= " WHERE id = ?";
            $params[] = $post_id;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $message = 'Post updated successfully!';
            logActivity($pdo, $_SESSION['admin_id'], 'update', 'Updated post: ' . $title, 'posts', $post_id);
        } else {
            // Create new post
            $stmt = $pdo->prepare("INSERT INTO posts (title, slug, content, excerpt, category_id, tags, reading_time, status, featured_image, meta_title, meta_description, meta_keywords, author_id, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $slug, $content, $excerpt, $category_id, $tags, $reading_time, $status, $featured_image, $meta_title, $meta_description, $meta_keywords, $_SESSION['admin_id'], $published_at]);
            $post_id = $pdo->lastInsertId();

            $message = 'Post created successfully!';
            logActivity($pdo, $_SESSION['admin_id'], 'create', 'Created post: ' . $title, 'posts', $post_id);
        }
        
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle delete action
if (isset($_GET['delete'])) {
    $post_id = (int)$_GET['delete'];
    try {
        // Get post title for logging
        $stmt = $pdo->prepare("SELECT title FROM posts WHERE id = ?");
        $stmt->execute([$post_id]);
        $post = $stmt->fetch();

        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
        $stmt->execute([$post_id]);

        $message = 'Post deleted successfully!';
        $message_type = 'success';
        logActivity($pdo, $_SESSION['admin_id'], 'delete', 'Deleted post: ' . $post['title'], 'posts', $post_id);
    } catch (PDOException $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle publish action (quick publish from list)
if (isset($_GET['publish'])) {
    $post_id = (int)$_GET['publish'];
    try {
        $stmt = $pdo->prepare("UPDATE posts SET status = 'published', published_at = COALESCE(published_at, NOW()) WHERE id = ?");
        $stmt->execute([$post_id]);

        $message = 'Post published successfully!';
        $message_type = 'success';
        logActivity($pdo, $_SESSION['admin_id'], 'publish', 'Published post ID: ' . $post_id, 'posts', $post_id);
    } catch (PDOException $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle unpublish action (set back to draft)
if (isset($_GET['unpublish'])) {
    $post_id = (int)$_GET['unpublish'];
    try {
        $stmt = $pdo->prepare("UPDATE posts SET status = 'draft' WHERE id = ?");
        $stmt->execute([$post_id]);

        $message = 'Post unpublished (moved to draft).';
        $message_type = 'success';
        logActivity($pdo, $_SESSION['admin_id'], 'unpublish', 'Unpublished post ID: ' . $post_id, 'posts', $post_id);
    } catch (PDOException $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get post for editing
$edit_post = null;
if (isset($_GET['edit'])) {
    $post_id = (int)$_GET['edit'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
        $stmt->execute([$post_id]);
        $edit_post = $stmt->fetch();

        // Convert tags JSON to string
        if ($edit_post && $edit_post['tags']) {
            $tags_array = json_decode($edit_post['tags'], true);
            $edit_post['tags_string'] = implode(',', $tags_array);
        } else {
            $edit_post['tags_string'] = '';
        }
    } catch (PDOException $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get all posts with category names
try {
    $stmt = $pdo->query("
        SELECT p.*, c.name as category_name 
        FROM posts p 
        LEFT JOIN categories c ON p.category_id = c.id 
        ORDER BY p.created_at DESC
    ");
    $posts = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Get categories for dropdown
try {
    $stmt = $pdo->query("SELECT id, name FROM categories WHERE is_active = TRUE ORDER BY name");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<?php include 'includes/admin-header.php'; ?>

<div class="content-header">
    <div class="header-left">
        <h1><?php echo $edit_post ? 'Edit Post' : 'Add New Post'; ?></h1>
        <p class="page-description"><?php echo $edit_post ? 'Update your post content and settings' : 'Create a new blog post'; ?></p>
    </div>
    <div class="header-right">
        <a href="manage-posts.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i>
            <span class="btn-text">Back to Posts</span>
        </a>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> fade-in">
        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="posts-manager">
    <!-- Post Form -->
    <div class="card form-card">
        <div class="card-header">
            <h2><?php echo $edit_post ? 'Edit Post' : 'Create New Post'; ?></h2>
            <?php if ($edit_post): ?>
                <div class="post-status-indicator">
                    <span class="status-badge status-<?php echo $edit_post['status']; ?>">
                        <?php echo ucfirst($edit_post['status']); ?>
                    </span>
                    <span class="post-id">ID: <?php echo $edit_post['id']; ?></span>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="POST" action="" enctype="multipart/form-data" id="postForm">
                <?php if ($edit_post): ?>
                    <input type="hidden" name="post_id" value="<?php echo (int)$edit_post['id']; ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-main-content">
                        <div class="form-group">
                            <label for="title" class="form-label">Title *</label>
                            <input type="text" id="title" name="title" class="form-control" required
                                   value="<?php echo $edit_post ? htmlspecialchars($edit_post['title']) : ''; ?>"
                                   placeholder="Enter post title">
                        </div>

                        <div class="form-group">
                            <label for="content" class="form-label">Content *</label>
                            <textarea id="content" name="content" class="form-control" required 
                                      rows="12" placeholder="Write your post content here"><?php echo $edit_post ? htmlspecialchars($edit_post['content']) : ''; ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="excerpt" class="form-label">Excerpt</label>
                            <textarea id="excerpt" name="excerpt" class="form-control" 
                                      rows="4" placeholder="Brief description of your post (optional)"><?php echo $edit_post ? htmlspecialchars($edit_post['excerpt']) : ''; ?></textarea>
                            <div class="form-help">This will be displayed in post listings and meta descriptions.</div>
                        </div>
                    </div>

                    <div class="form-sidebar">
                        <!-- Publishing Options -->
                        <div class="form-section">
                            <h3 class="section-title">Publishing</h3>
                            <div class="form-group">
                                <label for="status" class="form-label">Status</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="draft" <?php echo (!$edit_post || $edit_post['status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                                    <option value="published" <?php echo ($edit_post && $edit_post['status'] === 'published') ? 'selected' : ''; ?>>Published</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="category_id" class="form-label">Category *</label>
                                <select id="category_id" name="category_id" class="form-control" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" 
                                            <?php echo ($edit_post && $edit_post['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="reading_time" class="form-label">Reading Time (minutes)</label>
                                <input type="number" id="reading_time" name="reading_time" class="form-control" min="0"
                                       value="<?php echo $edit_post ? (int)$edit_post['reading_time'] : 0; ?>"
                                       placeholder="Estimated reading time">
                            </div>
                        </div>

                        <!-- Featured Image -->
                        <div class="form-section">
                            <h3 class="section-title">Featured Image</h3>
                            <div class="form-group">
                                <div class="file-upload">
                                    <input type="file" id="featured_image" name="featured_image" accept="image/*" class="file-input">
                                    <label for="featured_image" class="file-upload-label">
                                        <i class="fas fa-upload"></i>
                                        <span class="file-upload-text">
                                            <?php echo $edit_post && $edit_post['featured_image'] ? 'Change Image' : 'Choose Image'; ?>
                                        </span>
                                    </label>
                                    <?php if ($edit_post && $edit_post['featured_image']): ?>
                                        <div class="current-image">
                                            <img src="../assets/uploads/<?php echo htmlspecialchars($edit_post['featured_image']); ?>" 
                                                 alt="Current featured image" class="image-preview">
                                            <div class="image-info">
                                                <span>Current image</span>
                                                <a href="../assets/uploads/<?php echo htmlspecialchars($edit_post['featured_image']); ?>" 
                                                   target="_blank" class="btn-link">View Full</a>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="form-help">
                                    Recommended: 1200×630px. Max file size: 5MB. Formats: JPG, PNG, GIF, WebP.
                                </div>
                            </div>
                        </div>

                        <!-- Tags -->
                        <div class="form-section">
                            <h3 class="section-title">Tags</h3>
                            <div class="form-group">
                                <label for="tags" class="form-label">Tags</label>
                                <input type="text" id="tags" name="tags" class="form-control" 
                                       value="<?php echo $edit_post ? htmlspecialchars($edit_post['tags_string']) : ''; ?>" 
                                       placeholder="Enter tags separated by commas">
                                <div class="form-help">Separate tags with commas (e.g., technology, web development, php)</div>
                            </div>
                        </div>

                        <!-- SEO Settings -->
                        <div class="form-section">
                            <h3 class="section-title">SEO Settings</h3>
                            <div class="form-group">
                                <label for="meta_title" class="form-label">Meta Title</label>
                                <input type="text" id="meta_title" name="meta_title" class="form-control" 
                                       value="<?php echo $edit_post ? htmlspecialchars($edit_post['meta_title']) : ''; ?>"
                                       placeholder="Meta title for SEO">
                                <div class="char-count" id="metaTitleCount">0/60</div>
                            </div>

                            <div class="form-group">
                                <label for="meta_description" class="form-label">Meta Description</label>
                                <textarea id="meta_description" name="meta_description" class="form-control" 
                                          rows="3" placeholder="Meta description for SEO"><?php echo $edit_post ? htmlspecialchars($edit_post['meta_description']) : ''; ?></textarea>
                                <div class="char-count" id="metaDescCount">0/160</div>
                            </div>

                            <div class="form-group">
                                <label for="meta_keywords" class="form-label">Meta Keywords</label>
                                <input type="text" id="meta_keywords" name="meta_keywords" class="form-control" 
                                       value="<?php echo $edit_post ? htmlspecialchars($edit_post['meta_keywords']) : ''; ?>"
                                       placeholder="Keywords for SEO">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" name="save_post" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i>
                        <?php echo $edit_post ? 'Update Post' : 'Publish Post'; ?>
                    </button>

                    <?php if ($edit_post): ?>
                        <a href="manage-posts.php" class="btn btn-outline">
                            <i class="fas fa-times"></i>
                            Cancel
                        </a>
                        
                        <?php if ($edit_post['status'] !== 'published'): ?>
                            <a href="manage-posts.php?publish=<?php echo $edit_post['id']; ?>" 
                               class="btn btn-success"
                               onclick="return confirm('Publish this post now?')">
                                <i class="fas fa-upload"></i>
                                Publish Now
                            </a>
                        <?php else: ?>
                            <a href="manage-posts.php?unpublish=<?php echo $edit_post['id']; ?>" 
                               class="btn btn-warning"
                               onclick="return confirm('Unpublish this post? It will be moved to drafts.')">
                                <i class="fas fa-eye-slash"></i>
                                Unpublish
                            </a>
                        <?php endif; ?>
                        
                        <a href="../index.php?p=<?php echo $edit_post['id']; ?>" 
                           target="_blank" 
                           class="btn btn-outline">
                            <i class="fas fa-eye"></i>
                            View Post
                        </a>
                    <?php else: ?>
                        <button type="submit" name="save_draft" value="draft" class="btn btn-outline">
                            <i class="fas fa-save"></i>
                            Save Draft
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Posts List -->
    <?php if (!$edit_post): ?>
    <div class="card posts-list-card">
        <div class="card-header">
            <h2>All Posts (<?php echo count($posts); ?>)</h2>
            <div class="header-actions">
                <div class="search-box">
                    <input type="text" id="postsSearch" placeholder="Search posts..." class="search-input">
                    <i class="fas fa-search search-icon"></i>
                </div>
                <div class="view-controls">
                    <button class="view-btn active" data-view="table">
                        <i class="fas fa-table"></i>
                    </button>
                    <button class="view-btn" data-view="grid">
                        <i class="fas fa-th"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if ($posts): ?>
                <!-- Table View -->
                <div class="table-view view-content active">
                    <div class="table-responsive">
                        <table class="data-table mobile-friendly">
                            <thead>
                                <tr>
                                    <th width="50">ID</th>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Views</th>
                                    <th>Likes</th>
                                    <th>Date</th>
                                    <th width="200">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($posts as $post): ?>
                                <tr>
                                    <td><?php echo $post['id']; ?></td>
                                    <td>
                                        <div class="post-title-cell">
                                            <strong><?php echo htmlspecialchars($post['title']); ?></strong>
                                            <?php if ($post['featured_image']): ?>
                                                <span class="has-image-indicator" title="Has featured image">
                                                    <i class="fas fa-image"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($post['category_name'] ?? 'Uncategorized'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $post['status']; ?>">
                                            <?php echo ucfirst($post['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo number_format($post['views']); ?></td>
                                    <td><?php echo number_format($post['likes']); ?></td>
                                    <td>
                                        <span class="date-display">
                                            <?php echo date('M j, Y', strtotime($post['created_at'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="manage-posts.php?edit=<?php echo $post['id']; ?>" 
                                               class="btn btn-primary btn-sm"
                                               title="Edit post">
                                                <i class="fas fa-edit"></i>
                                                <span class="btn-text">Edit</span>
                                            </a>

                                            <?php if ($post['status'] !== 'published'): ?>
                                                <a href="manage-posts.php?publish=<?php echo $post['id']; ?>" 
                                                   class="btn btn-success btn-sm"
                                                   title="Publish post">
                                                    <i class="fas fa-upload"></i>
                                                    <span class="btn-text">Publish</span>
                                                </a>
                                            <?php else: ?>
                                                <a href="manage-posts.php?unpublish=<?php echo $post['id']; ?>" 
                                                   class="btn btn-warning btn-sm"
                                                   title="Unpublish post">
                                                    <i class="fas fa-eye-slash"></i>
                                                    <span class="btn-text">Unpublish</span>
                                                </a>
                                            <?php endif; ?>

                                            <a href="manage-posts.php?delete=<?php echo $post['id']; ?>" 
                                               class="btn btn-danger btn-sm"
                                               onclick="return confirm('Are you sure you want to delete this post? This action cannot be undone.');"
                                               title="Delete post">
                                                <i class="fas fa-trash"></i>
                                                <span class="btn-text">Delete</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Grid View -->
                <div class="grid-view view-content">
                    <div class="posts-grid">
                        <?php foreach ($posts as $post): ?>
                        <div class="post-card">
                            <div class="post-card-header">
                                <?php if ($post['featured_image']): ?>
                                    <img src="../assets/uploads/<?php echo htmlspecialchars($post['featured_image']); ?>" 
                                         alt="<?php echo htmlspecialchars($post['title']); ?>"
                                         class="post-card-image">
                                <?php else: ?>
                                    <div class="post-card-placeholder">
                                        <i class="fas fa-newspaper"></i>
                                    </div>
                                <?php endif; ?>
                                <span class="status-badge status-<?php echo $post['status']; ?>">
                                    <?php echo ucfirst($post['status']); ?>
                                </span>
                            </div>
                            <div class="post-card-body">
                                <h3 class="post-card-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                                <div class="post-card-meta">
                                    <span class="post-category"><?php echo htmlspecialchars($post['category_name'] ?? 'Uncategorized'); ?></span>
                                    <span class="post-date"><?php echo date('M j, Y', strtotime($post['created_at'])); ?></span>
                                </div>
                                <div class="post-card-stats">
                                    <span class="stat">
                                        <i class="fas fa-eye"></i>
                                        <?php echo number_format($post['views']); ?>
                                    </span>
                                    <span class="stat">
                                        <i class="fas fa-heart"></i>
                                        <?php echo number_format($post['likes']); ?>
                                    </span>
                                </div>
                                <?php if ($post['excerpt']): ?>
                                    <p class="post-card-excerpt"><?php echo htmlspecialchars($post['excerpt']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="post-card-actions">
                                <a href="manage-posts.php?edit=<?php echo $post['id']; ?>" 
                                   class="btn btn-primary btn-sm">
                                    <i class="fas fa-edit"></i>
                                    Edit
                                </a>
                                <?php if ($post['status'] !== 'published'): ?>
                                    <a href="manage-posts.php?publish=<?php echo $post['id']; ?>" 
                                       class="btn btn-success btn-sm">
                                        <i class="fas fa-upload"></i>
                                        Publish
                                    </a>
                                <?php else: ?>
                                    <a href="manage-posts.php?unpublish=<?php echo $post['id']; ?>" 
                                       class="btn btn-warning btn-sm">
                                        <i class="fas fa-eye-slash"></i>
                                        Unpublish
                                    </a>
                                <?php endif; ?>
                                <a href="../index.php?p=<?php echo $post['id']; ?>" 
                                   target="_blank" 
                                   class="btn btn-outline btn-sm">
                                    <i class="fas fa-eye"></i>
                                    View
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-newspaper"></i>
                    <h3>No Posts Found</h3>
                    <p>Get started by creating your first blog post.</p>
                    <a href="manage-posts.php?action=create" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i>
                        Create Your First Post
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
    /* Posts Manager Specific Styles */
    .posts-manager {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }

    .content-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .header-left h1 {
        margin-bottom: 0.25rem;
    }

    .page-description {
        color: var(--admin-text-light);
        font-size: 0.9rem;
    }

    .form-card {
        margin-bottom: 2rem;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
        align-items: start;
    }

    .form-main-content {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .form-sidebar {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        position: sticky;
        top: 1rem;
    }

    .form-section {
        background: var(--admin-bg);
        border: 1px solid var(--admin-border);
        border-radius: 8px;
        padding: 1.5rem;
    }

    .section-title {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 1rem;
        color: var(--admin-text);
        border-bottom: 1px solid var(--admin-border);
        padding-bottom: 0.5rem;
    }

    .form-help {
        font-size: 0.8rem;
        color: var(--admin-text-light);
        margin-top: 0.5rem;
    }

    .char-count {
        font-size: 0.8rem;
        color: var(--admin-text-light);
        text-align: right;
        margin-top: 0.25rem;
    }

    .current-image {
        margin-top: 1rem;
        text-align: center;
    }

    .current-image .image-preview {
        max-width: 100%;
        border-radius: 6px;
        margin-bottom: 0.5rem;
    }

    .image-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.8rem;
        color: var(--admin-text-light);
    }

    .btn-link {
        color: var(--admin-accent);
        text-decoration: none;
        font-size: 0.8rem;
    }

    .btn-link:hover {
        text-decoration: underline;
    }

    .form-actions {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
        padding-top: 1.5rem;
        border-top: 1px solid var(--admin-border);
        margin-top: 1.5rem;
    }

    .post-status-indicator {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .post-id {
        font-size: 0.8rem;
        color: var(--admin-text-light);
        background: var(--admin-bg);
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
    }

    /* Posts List Styles */
    .posts-list-card .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .header-actions {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .search-box {
        position: relative;
    }

    .search-input {
        padding: 0.5rem 1rem 0.5rem 2.5rem;
        border: 1px solid var(--admin-border);
        border-radius: 20px;
        font-size: 0.9rem;
        width: 250px;
    }

    .search-icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--admin-text-light);
    }

    .view-controls {
        display: flex;
        border: 1px solid var(--admin-border);
        border-radius: 6px;
        overflow: hidden;
    }

    .view-btn {
        background: white;
        border: none;
        padding: 0.5rem;
        cursor: pointer;
        transition: all 0.3s;
    }

    .view-btn.active {
        background: var(--admin-accent);
        color: white;
    }

    .view-btn:hover:not(.active) {
        background: var(--admin-border-light);
    }

    .view-content {
        display: none;
    }

    .view-content.active {
        display: block;
    }

    /* Table View Enhancements */
    .post-title-cell {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .has-image-indicator {
        color: var(--admin-accent);
        font-size: 0.8rem;
    }

    /* Grid View */
    .posts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .post-card {
        border: 1px solid var(--admin-border);
        border-radius: 8px;
        overflow: hidden;
        background: white;
        transition: all 0.3s ease;
    }

    .post-card:hover {
        box-shadow: var(--admin-shadow-lg);
        transform: translateY(-2px);
    }

    .post-card-header {
        position: relative;
        height: 160px;
        overflow: hidden;
    }

    .post-card-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .post-card-placeholder {
        width: 100%;
        height: 100%;
        background: var(--admin-border-light);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--admin-text-light);
        font-size: 2rem;
    }

    .post-card-header .status-badge {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
    }

    .post-card-body {
        padding: 1.5rem;
    }

    .post-card-title {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.75rem;
        line-height: 1.3;
        color: var(--admin-text);
    }

    .post-card-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
        font-size: 0.8rem;
        color: var(--admin-text-light);
    }

    .post-card-stats {
        display: flex;
        gap: 1rem;
        margin-bottom: 0.75rem;
    }

    .stat {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.8rem;
        color: var(--admin-text-light);
    }

    .post-card-excerpt {
        font-size: 0.9rem;
        color: var(--admin-text);
        line-height: 1.4;
        margin-bottom: 0;
    }

    .post-card-actions {
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--admin-border);
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
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

        .posts-grid {
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

        .form-actions {
            flex-direction: column;
        }

        .form-actions .btn {
            width: 100%;
            justify-content: center;
        }

        .posts-grid {
            grid-template-columns: 1fr;
        }

        .post-card-actions {
            flex-direction: column;
        }

        .post-card-actions .btn {
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

        .post-card-body {
            padding: 1rem;
        }

        .post-card-actions {
            padding: 0.75rem 1rem;
        }

        .view-controls {
            align-self: center;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Character counters for SEO fields
        const metaTitle = document.getElementById('meta_title');
        const metaDesc = document.getElementById('meta_description');
        const metaTitleCount = document.getElementById('metaTitleCount');
        const metaDescCount = document.getElementById('metaDescCount');

        function updateCharCounts() {
            if (metaTitle && metaTitleCount) {
                const titleLength = metaTitle.value.length;
                metaTitleCount.textContent = `${titleLength}/60`;
                metaTitleCount.style.color = titleLength > 60 ? 'var(--admin-danger)' : 'var(--admin-text-light)';
            }

            if (metaDesc && metaDescCount) {
                const descLength = metaDesc.value.length;
                metaDescCount.textContent = `${descLength}/160`;
                metaDescCount.style.color = descLength > 160 ? 'var(--admin-danger)' : 'var(--admin-text-light)';
            }
        }

        if (metaTitle) metaTitle.addEventListener('input', updateCharCounts);
        if (metaDesc) metaDesc.addEventListener('input', updateCharCounts);
        updateCharCounts();

        // View controls for posts list
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
                localStorage.setItem('postsView', viewType);
            });
        });

        // Restore view preference
        const savedView = localStorage.getItem('postsView') || 'table';
        const savedBtn = document.querySelector(`.view-btn[data-view="${savedView}"]`);
        if (savedBtn) {
            savedBtn.click();
        }

        // Search functionality
        const searchInput = document.getElementById('postsSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('.table-view tbody tr, .grid-view .post-card');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
        }

        // Auto-generate meta title and description
        const titleInput = document.getElementById('title');
        const excerptInput = document.getElementById('excerpt');
        
        if (titleInput && !metaTitle.value) {
            titleInput.addEventListener('blur', function() {
                if (!metaTitle.value) {
                    metaTitle.value = this.value;
                    updateCharCounts();
                }
            });
        }

        if (excerptInput && !metaDesc.value) {
            excerptInput.addEventListener('blur', function() {
                if (!metaDesc.value && this.value) {
                    metaDesc.value = this.value.substring(0, 160);
                    updateCharCounts();
                }
            });
        }

        // Image preview for featured image
        const featuredImageInput = document.getElementById('featured_image');
        if (featuredImageInput) {
            featuredImageInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        // Remove existing current image
                        const currentImage = document.querySelector('.current-image');
                        if (currentImage) {
                            currentImage.remove();
                        }

                        // Create new preview
                        const previewContainer = document.createElement('div');
                        previewContainer.className = 'current-image';
                        
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'image-preview';
                        img.alt = 'Image preview';
                        
                        const info = document.createElement('div');
                        info.className = 'image-info';
                        info.innerHTML = '<span>New image preview</span>';
                        
                        previewContainer.appendChild(img);
                        previewContainer.appendChild(info);
                        
                        featuredImageInput.parentNode.appendChild(previewContainer);
                    }
                    reader.readAsDataURL(file);
                }
            });
        }

        // Form validation
        const postForm = document.getElementById('postForm');
        if (postForm) {
            postForm.addEventListener('submit', function(e) {
                const title = document.getElementById('title').value.trim();
                const content = document.getElementById('content').value.trim();
                const category = document.getElementById('category_id').value;
                
                if (!title) {
                    e.preventDefault();
                    alert('Please enter a post title');
                    document.getElementById('title').focus();
                    return;
                }
                
                if (!content) {
                    e.preventDefault();
                    alert('Please enter post content');
                    document.getElementById('content').focus();
                    return;
                }
                
                if (!category) {
                    e.preventDefault();
                    alert('Please select a category');
                    document.getElementById('category_id').focus();
                    return;
                }
            });
        }

        // Auto-save draft (basic implementation)
        let autoSaveTimer;
        const formInputs = document.querySelectorAll('#postForm input, #postForm textarea, #postForm select');
        
        formInputs.forEach(input => {
            input.addEventListener('input', function() {
                clearTimeout(autoSaveTimer);
                autoSaveTimer = setTimeout(() => {
                    // In a real implementation, you'd save to localStorage or send via AJAX
                    console.log('Auto-save triggered');
                }, 2000);
            });
        });
    });
</script>

<?php include 'includes/admin-footer.php'; ?>