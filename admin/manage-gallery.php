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
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $gallery_id = isset($_POST['gallery_id']) ? (int)$_POST['gallery_id'] : 0;
    $is_mobile = isset($_POST['is_mobile']) ? (int)$_POST['is_mobile'] : 0;

    // Mobile-specific adjustments: increase limits for mobile uploads if needed
    if ($is_mobile) {
        @ini_set('max_execution_time', 300); // 5 minutes
        @ini_set('upload_max_filesize', '10M');
        @ini_set('post_max_size', '10M');
    }

    try {
        // Handle file upload with improved checks and mobile support
        $image_path = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../assets/uploads/';

                // Create uploads directory if it doesn't exist
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                // Ensure writable
                if (!is_writable($upload_dir)) {
                    @chmod($upload_dir, 0755);
                }

                // Security checks using server-detected mime type
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $file_type = mime_content_type($_FILES['image']['tmp_name']);

                if (in_array($file_type, $allowed_types)) {
                    // Get file extension and normalize
                    $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

                    // Generate secure filename
                    $filename = 'gallery_' . date('Ymd_His') . '_' . uniqid() . '.' . $file_extension;
                    $file_path = $upload_dir . $filename;

                    // Move uploaded file with retries (helpful on mobile networks)
                    $max_retries = 3;
                    $uploaded = false;

                    for ($i = 0; $i < $max_retries; $i++) {
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $file_path)) {
                            $uploaded = true;
                            break;
                        }
                        usleep(100000); // 100ms
                    }

                    if ($uploaded) {
                        // Compress images for mobile uploads to save space/bandwidth
                        if ($is_mobile && in_array($file_extension, ['jpg', 'jpeg', 'png'])) {
                            // call local function defined below
                            compressImage($file_path, $file_path, 80);
                        }

                        $image_path = $filename;
                    } else {
                        $message = 'Failed to upload file. Please try again.';
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.';
                    $message_type = 'error';
                }
            } else {
                $upload_errors = [
                    UPLOAD_ERR_INI_SIZE => 'File is too large (server limit).',
                    UPLOAD_ERR_FORM_SIZE => 'File is too large (form limit).',
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                    UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'
                ];
                $message = 'Upload error: ' . ($upload_errors[$_FILES['image']['error']] ?? 'Unknown error');
                $message_type = 'error';
            }
        }

        if ($gallery_id > 0) {
            // Update existing gallery item
            if ($image_path) {
                $stmt = $pdo->prepare("UPDATE gallery SET title = ?, description = ?, category = ?, image_path = ? WHERE id = ?");
                $stmt->execute([$title, $description, $category, $image_path, $gallery_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE gallery SET title = ?, description = ?, category = ? WHERE id = ?");
                $stmt->execute([$title, $description, $category, $gallery_id]);
            }
            $message = 'Gallery item updated successfully!';
            $message_type = 'success';
        } else {
            // Create new gallery item
            if (!$image_path) {
                $message = 'Please select an image to upload.';
                $message_type = 'error';
            } else {
                $stmt = $pdo->prepare("INSERT INTO gallery (title, description, category, image_path) VALUES (?, ?, ?, ?)");
                $stmt->execute([$title, $description, $category, $image_path]);
                $message = 'Gallery item added successfully!';
                $message_type = 'success';
            }
        }
    } catch (PDOException $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle delete action
if (isset($_GET['delete'])) {
    $gallery_id = (int)$_GET['delete'];
    try {
        // Get image path to delete file
        $stmt = $pdo->prepare("SELECT image_path FROM gallery WHERE id = ?");
        $stmt->execute([$gallery_id]);
        $image = $stmt->fetch();
        
        if ($image && file_exists('../assets/uploads/' . $image['image_path'])) {
            unlink('../assets/uploads/' . $image['image_path']);
        }
        
        $stmt = $pdo->prepare("DELETE FROM gallery WHERE id = ?");
        $stmt->execute([$gallery_id]);
        $message = 'Gallery item deleted successfully!';
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get gallery item for editing
$edit_item = null;
if (isset($_GET['edit'])) {
    $gallery_id = (int)$_GET['edit'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM gallery WHERE id = ?");
        $stmt->execute([$gallery_id]);
        $edit_item = $stmt->fetch();
    } catch (PDOException $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Get all gallery items
try {
    $stmt = $pdo->query("SELECT * FROM gallery ORDER BY created_at DESC");
    $gallery_items = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Get unique categories for filter
$categories = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT category FROM gallery WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Silently fail, categories will be empty
}

// Image compression function (used for mobile uploads)
function compressImage($source, $destination, $quality) {
    $info = @getimagesize($source);
    if (!$info) return false;

    $mime = $info['mime'];
    if ($mime === 'image/jpeg') {
        $image = imagecreatefromjpeg($source);
        imagejpeg($image, $destination, $quality);
    } elseif ($mime === 'image/png') {
        $image = imagecreatefrompng($source);
        // Convert quality 0-100 to PNG compression 0-9 (invert)
        $pngQuality = (int) round((100 - $quality) / 11.111111);
        imagepng($image, $destination, max(0, min(9, $pngQuality)));
    } elseif ($mime === 'image/gif') {
        $image = imagecreatefromgif($source);
        imagegif($image, $destination);
    } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
        $image = imagecreatefromwebp($source);
        imagewebp($image, $destination, $quality);
    }

    if (isset($image) && is_resource($image)) {
        imagedestroy($image);
    }

    return true;
}
?>

<?php include 'includes/admin-header.php'; ?>

<div class="content-header">
    <div class="header-left">
        <h1><?php echo $edit_item ? 'Edit Gallery Item' : 'Add Gallery Item'; ?></h1>
        <p class="page-description"><?php echo $edit_item ? 'Update image details' : 'Upload and add new images to your gallery'; ?></p>
    </div>
    <div class="header-right">
        <a href="manage-gallery.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i>
            <span class="btn-text">Back to Gallery</span>
        </a>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> fade-in">
        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="gallery-manager">
    <!-- Gallery Form -->
    <div class="card form-card">
        <div class="card-header">
            <h2><?php echo $edit_item ? 'Edit Gallery Item' : 'Add New Image'; ?></h2>
            <?php if ($edit_item): ?>
                <div class="gallery-item-info">
                    <span class="item-id">ID: <?php echo $edit_item['id']; ?></span>
                    <span class="item-date">Added: <?php echo date('M j, Y', strtotime($edit_item['created_at'])); ?></span>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="POST" action="" enctype="multipart/form-data" id="galleryForm">
                <?php if ($edit_item): ?>
                    <input type="hidden" name="gallery_id" value="<?php echo $edit_item['id']; ?>">
                <?php endif; ?>
                <input type="hidden" name="is_mobile" id="isMobile" value="0">

                <div class="form-grid">
                    <div class="form-main-content">
                        <div class="form-group">
                            <label for="title" class="form-label">Image Title *</label>
                            <input type="text" id="title" name="title" class="form-control" 
                                   value="<?php echo $edit_item ? htmlspecialchars($edit_item['title']) : ''; ?>" 
                                   required
                                   placeholder="Enter image title">
                        </div>

                        <div class="form-group">
                            <label for="description" class="form-label">Description</label>
                            <textarea id="description" name="description" class="form-control" 
                                      rows="4" placeholder="Brief description of the image"><?php echo $edit_item ? htmlspecialchars($edit_item['description']) : ''; ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="category" class="form-label">Category</label>
                            <input type="text" id="category" name="category" class="form-control"
                                   value="<?php echo $edit_item ? htmlspecialchars($edit_item['category']) : ''; ?>"
                                   placeholder="e.g., Events, Behind the Scenes, Portraits"
                                   list="categorySuggestions">
                            <datalist id="categorySuggestions">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>">
                                <?php endforeach; ?>
                            </datalist>
                            <div class="form-help">Categorize your images for better organization</div>
                        </div>
                    </div>

                    <div class="form-sidebar">
                        <!-- Image Upload -->
                        <div class="form-section">
                            <h3 class="section-title">Image Upload</h3>
                            <div class="form-group">
                                    <div class="file-upload-area">
                                    <input type="file" id="image" name="image" accept="image/*" 
                                           <?php echo $edit_item ? '' : 'required'; ?> 
                                           class="file-input"
                                           data-max-size="5242880"
                                           onchange="previewImage(this)">
                                    <label for="image" class="file-upload-label">
                                        <div class="upload-icon">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                        </div>
                                        <div class="upload-text">
                                            <strong><?php echo $edit_item ? 'Change Image' : 'Choose Image'; ?></strong>
                                            <span>Click to browse or drag & drop</span>
                                        </div>
                                    </label>
                                    <div class="upload-requirements">
                                        <small>Supported formats: JPG, PNG, GIF, WebP</small>
                                        <small>Max file size: 5MB</small>
                                    </div>
                                    <div id="fileInfo" style="margin-top: 10px; display: none;">
                                        <p id="fileName" style="font-size: 0.9rem; color: var(--admin-text-light);"></p>
                                        <p id="fileSize" style="font-size: 0.8rem; color: var(--admin-text-light);"></p>
                                    </div>
                                    <div id="imagePreview"></div>
                                </div>
                                
                                <?php if ($edit_item): ?>
                                    <div class="current-image-preview">
                                        <div class="preview-header">
                                            <span>Current Image</span>
                                            <a href="../assets/uploads/<?php echo htmlspecialchars($edit_item['image_path']); ?>" 
                                               target="_blank" 
                                               class="btn-link">
                                                View Full
                                            </a>
                                        </div>
                                        <img src="../assets/uploads/<?php echo htmlspecialchars($edit_item['image_path']); ?>" 
                                             alt="<?php echo htmlspecialchars($edit_item['title']); ?>"
                                             class="image-preview-large">
                                        <div class="image-info">
                                            <span><?php echo $edit_item['image_path']; ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="form-section">
                            <h3 class="section-title">Quick Actions</h3>
                            <div class="quick-actions">
                                <?php if ($edit_item): ?>
                                    <a href="../assets/uploads/<?php echo htmlspecialchars($edit_item['image_path']); ?>" 
                                       target="_blank" 
                                       class="btn btn-outline btn-block">
                                        <i class="fas fa-eye"></i>
                                        View Full Image
                                    </a>
                                    <a href="manage-gallery.php?delete=<?php echo $edit_item['id']; ?>" 
                                       class="btn btn-danger btn-block"
                                       onclick="return confirm('Are you sure you want to delete this image? This action cannot be undone.');">
                                        <i class="fas fa-trash"></i>
                                        Delete Image
                                    </a>
                                <?php else: ?>
                                    <button type="reset" class="btn btn-outline btn-block">
                                        <i class="fas fa-undo"></i>
                                        Reset Form
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Image Statistics -->
                        <?php if ($edit_item): ?>
                        <div class="form-section">
                            <h3 class="section-title">Image Info</h3>
                            <div class="image-stats">
                                <?php
                                $image_path = '../assets/uploads/' . $edit_item['image_path'];
                                if (file_exists($image_path)) {
                                    $image_size = filesize($image_path);
                                    $image_info = getimagesize($image_path);
                                ?>
                                <div class="stat-item">
                                    <div class="stat-label">File Size</div>
                                    <div class="stat-value"><?php echo round($image_size / 1024 / 1024, 2); ?> MB</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-label">Dimensions</div>
                                    <div class="stat-value"><?php echo $image_info[0] . '×' . $image_info[1]; ?></div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-label">Type</div>
                                    <div class="stat-value"><?php echo strtoupper(pathinfo($edit_item['image_path'], PATHINFO_EXTENSION)); ?></div>
                                </div>
                                <?php } ?>
                                <div class="stat-item">
                                    <div class="stat-label">Uploaded</div>
                                    <div class="stat-value"><?php echo date('M j, Y', strtotime($edit_item['created_at'])); ?></div>
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
                        <?php echo $edit_item ? 'Update Item' : 'Add to Gallery'; ?>
                    </button>

                    <?php if ($edit_item): ?>
                        <a href="manage-gallery.php" class="btn btn-outline">
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

    <!-- Gallery Items -->
    <?php if (!$edit_item): ?>
    <div class="card gallery-list-card">
        <div class="card-header">
            <h2>Gallery Items (<?php echo count($gallery_items); ?>)</h2>
            <div class="header-actions">
                <div class="search-box">
                    <input type="text" id="gallerySearch" placeholder="Search gallery..." class="search-input">
                    <i class="fas fa-search search-icon"></i>
                </div>
                <div class="filter-controls">
                    <select id="categoryFilter" class="form-control">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
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
            <?php if ($gallery_items): ?>
                <!-- Grid View -->
                <div class="grid-view view-content active">
                    <div class="gallery-grid">
                        <?php foreach ($gallery_items as $item): ?>
                        <div class="gallery-item" data-category="<?php echo htmlspecialchars($item['category'] ?? ''); ?>">
                            <div class="gallery-item-image">
                                <img src="../assets/uploads/<?php echo htmlspecialchars($item['image_path']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['title']); ?>"
                                     loading="lazy"
                                     onerror="this.src='../assets/images/placeholder.jpg'">
                                <div class="gallery-item-overlay">
                                    <div class="overlay-content">
                                        <h4 class="item-title"><?php echo htmlspecialchars($item['title']); ?></h4>
                                        <?php if ($item['category']): ?>
                                            <span class="item-category"><?php echo htmlspecialchars($item['category']); ?></span>
                                        <?php endif; ?>
                                        <div class="item-actions">
                                            <a href="manage-gallery.php?edit=<?php echo $item['id']; ?>" 
                                               class="btn btn-primary btn-sm"
                                               title="Edit image">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="manage-gallery.php?delete=<?php echo $item['id']; ?>" 
                                               class="btn btn-danger btn-sm"
                                               onclick="return confirm('Are you sure you want to delete this image?')"
                                               title="Delete image">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <a href="../assets/uploads/<?php echo htmlspecialchars($item['image_path']); ?>" 
                                               target="_blank" 
                                               class="btn btn-outline btn-sm"
                                               title="View full image">
                                                <i class="fas fa-expand"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="gallery-item-info">
                                <h4 class="item-title"><?php echo htmlspecialchars($item['title']); ?></h4>
                                <?php if ($item['category']): ?>
                                    <span class="item-category"><?php echo htmlspecialchars($item['category']); ?></span>
                                <?php endif; ?>
                                <span class="item-date"><?php echo date('M j, Y', strtotime($item['created_at'])); ?></span>
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
                                    <th width="80">Image</th>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Date Added</th>
                                    <th width="120">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gallery_items as $item): ?>
                                <tr data-category="<?php echo htmlspecialchars($item['category'] ?? ''); ?>">
                                    <td>
                                        <div class="gallery-thumbnail">
                                            <img src="../assets/uploads/<?php echo htmlspecialchars($item['image_path']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['title']); ?>"
                                                 class="gallery-thumb">
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($item['category']): ?>
                                            <span class="category-badge"><?php echo htmlspecialchars($item['category']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Uncategorized</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="description-preview">
                                            <?php echo $item['description'] ? htmlspecialchars(substr($item['description'], 0, 80)) . '...' : '—'; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="date-display">
                                            <?php echo date('M j, Y', strtotime($item['created_at'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="manage-gallery.php?edit=<?php echo $item['id']; ?>" 
                                               class="btn btn-primary btn-sm"
                                               title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="../assets/uploads/<?php echo htmlspecialchars($item['image_path']); ?>" 
                                               target="_blank" 
                                               class="btn btn-outline btn-sm"
                                               title="View">
                                                <i class="fas fa-eye"></i>
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
                    <i class="fas fa-images"></i>
                    <h3>No Gallery Items Found</h3>
                    <p>Get started by uploading your first image to the gallery.</p>
                    <a href="manage-gallery.php?action=create" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i>
                        Add Your First Image
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
    /* Gallery Manager Specific Styles */
    .gallery-manager {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }

    .gallery-item-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .item-id, .item-date {
        font-size: 0.8rem;
        color: var(--admin-text-light);
        background: var(--admin-bg);
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
    }

    .file-upload-area {
        border: 2px dashed var(--admin-border);
        border-radius: 8px;
        padding: 2rem;
        text-align: center;
        transition: all 0.3s ease;
        background: var(--admin-bg);
    }

    .file-upload-area.dragover {
        border-color: var(--admin-accent);
        background: var(--admin-card-bg);
    }

    .upload-icon {
        font-size: 3rem;
        color: var(--admin-text-light);
        margin-bottom: 1rem;
    }

    .upload-text strong {
        display: block;
        font-size: 1.1rem;
        margin-bottom: 0.5rem;
        color: var(--admin-text);
    }

    .upload-text span {
        color: var(--admin-text-light);
        font-size: 0.9rem;
    }

    .upload-requirements {
        margin-top: 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .upload-requirements small {
        color: var(--admin-text-light);
    }

    .current-image-preview {
        margin-top: 1.5rem;
        border: 1px solid var(--admin-border);
        border-radius: 8px;
        overflow: hidden;
    }

    .preview-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 1rem;
        background: var(--admin-bg);
        border-bottom: 1px solid var(--admin-border);
    }

    .image-preview-large {
        width: 100%;
        height: auto;
        display: block;
    }

    .image-info {
        padding: 0.75rem 1rem;
        background: var(--admin-bg);
        border-top: 1px solid var(--admin-border);
        font-size: 0.8rem;
        color: var(--admin-text-light);
    }

    .image-stats {
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

    /* Gallery Grid View */
    .gallery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1.5rem;
    }

    .gallery-item {
        border: 1px solid var(--admin-border);
        border-radius: 8px;
        overflow: hidden;
        background: white;
        transition: all 0.3s ease;
    }

    .gallery-item:hover {
        box-shadow: var(--admin-shadow-lg);
        transform: translateY(-2px);
    }

    .gallery-item-image {
        position: relative;
        aspect-ratio: 1;
        overflow: hidden;
    }

    .gallery-item-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .gallery-item:hover .gallery-item-image img {
        transform: scale(1.05);
    }

    .gallery-item-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
        padding: 1rem;
    }

    .gallery-item:hover .gallery-item-overlay {
        opacity: 1;
    }

    .overlay-content {
        text-align: center;
        color: white;
        width: 100%;
    }

    .overlay-content .item-title {
        color: white;
        margin-bottom: 0.5rem;
        font-size: 1rem;
    }

    .item-category {
        display: inline-block;
        background: var(--admin-accent);
        color: white;
        padding: 0.25rem 0.5rem;
        border-radius: 12px;
        font-size: 0.7rem;
        margin-bottom: 1rem;
    }

    .item-actions {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
    }

    .gallery-item-info {
        padding: 1rem;
    }

    .gallery-item-info .item-title {
        font-size: 0.9rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        line-height: 1.3;
    }

    .gallery-item-info .item-category {
        background: var(--admin-border-light);
        color: var(--admin-text);
        margin-bottom: 0.25rem;
    }

    .gallery-item-info .item-date {
        font-size: 0.8rem;
        color: var(--admin-text-light);
        display: block;
        background: none;
        padding: 0;
    }

    /* List View Styles */
    .gallery-thumbnail {
        width: 60px;
        height: 60px;
        border-radius: 6px;
        overflow: hidden;
    }

    .gallery-thumb {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .category-badge {
        background: var(--admin-border-light);
        color: var(--admin-text);
        padding: 0.25rem 0.5rem;
        border-radius: 12px;
        font-size: 0.8rem;
    }

    .description-preview {
        max-width: 200px;
        font-size: 0.9rem;
        color: var(--admin-text-light);
    }

    .text-muted {
        color: var(--admin-text-light);
    }

    /* Filter Controls */
    .filter-controls {
        display: flex;
        gap: 1rem;
        align-items: center;
    }

    .filter-controls .form-control {
        width: auto;
        min-width: 150px;
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

        .gallery-grid {
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
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
            gap: 1rem;
        }

        .search-input {
            width: 100%;
        }

        .filter-controls {
            justify-content: space-between;
        }

        .filter-controls .form-control {
            flex: 1;
        }

        .form-actions {
            flex-direction: column;
        }

        .form-actions .btn {
            width: 100%;
            justify-content: center;
        }

        .gallery-grid {
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
        }

        .item-actions {
            flex-wrap: wrap;
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

        .file-upload-area {
            padding: 1.5rem;
        }

        .upload-icon {
            font-size: 2rem;
        }

        .gallery-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .gallery-item-info {
            padding: 0.75rem;
        }

        .view-controls {
            align-self: center;
        }

        .gallery-thumbnail {
            width: 50px;
            height: 50px;
        }
    }

    @media (max-width: 360px) {
        .gallery-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Drag and drop file upload
        const fileUploadArea = document.querySelector('.file-upload-area');
        const fileInput = document.getElementById('image');

        // Helper: detect mobile
        function isMobileDevice() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        }

        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Preview handler used both for desktop and mobile
        function previewImage(input) {
            const fileInfo = document.getElementById('fileInfo');
            const fileName = document.getElementById('fileName');
            const fileSize = document.getElementById('fileSize');
            const uploadText = document.querySelector('.upload-text strong');
            const preview = document.getElementById('imagePreview');

            if (input.files && input.files[0]) {
                const file = input.files[0];

                // Show file info
                if (fileName) fileName.textContent = `File: ${file.name}`;
                if (fileSize) fileSize.textContent = `Size: ${formatFileSize(file.size)}`;
                if (uploadText) uploadText.textContent = 'Change Image';
                if (fileInfo) fileInfo.style.display = 'block';

                // Check file size (5MB max or data-max-size)
                const maxSize = parseInt(input.dataset.maxSize) || 5242880;
                if (file.size > maxSize) {
                    alert('File is too large! Maximum size is 5MB.');
                    input.value = '';
                    if (fileInfo) fileInfo.style.display = 'none';
                    if (uploadText) uploadText.textContent = 'Choose Image';
                    if (preview) preview.innerHTML = '';
                    return;
                }

                // Create preview
                if (preview) preview.innerHTML = '';
                const reader = new FileReader();

                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.maxWidth = '200px';
                    img.style.maxHeight = '200px';
                    img.style.borderRadius = '5px';
                    img.style.marginTop = '10px';
                    img.style.objectFit = 'cover';

                    // For mobile, use smaller preview
                    if (isMobileDevice()) {
                        img.style.maxWidth = '150px';
                        img.style.maxHeight = '150px';
                    }

                    if (preview) preview.appendChild(img);
                }

                reader.readAsDataURL(file);
            }
        }

        if (fileUploadArea && fileInput) {
            // Drag over event
            fileUploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });

            // Drag leave event
            fileUploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });

            // Drop event
            fileUploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    
                    // Trigger change event to update preview
                    const event = new Event('change');
                    fileInput.dispatchEvent(event);
                }
            });

            // Click to select file
            fileUploadArea.addEventListener('click', function() {
                fileInput.click();
            });

            // File input change event — delegate to previewImage for consistent behavior
            fileInput.addEventListener('change', function(e) {
                // call centralized preview handler
                previewImage(this);
            });
        }

        // View controls for gallery
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
                localStorage.setItem('galleryView', viewType);
            });
        });

        // Restore view preference
        const savedView = localStorage.getItem('galleryView') || 'grid';
        const savedBtn = document.querySelector(`.view-btn[data-view="${savedView}"]`);
        if (savedBtn) {
            savedBtn.click();
        }

        // Search functionality
        const searchInput = document.getElementById('gallerySearch');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const items = document.querySelectorAll('.gallery-item, .list-view tbody tr');
                
                items.forEach(item => {
                    const text = item.textContent.toLowerCase();
                    item.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
        }

        // Category filter
        const categoryFilter = document.getElementById('categoryFilter');
        if (categoryFilter) {
            categoryFilter.addEventListener('change', function() {
                const selectedCategory = this.value;
                const items = document.querySelectorAll('.gallery-item, .list-view tbody tr');
                
                items.forEach(item => {
                    const itemCategory = item.dataset.category || '';
                    if (!selectedCategory || itemCategory === selectedCategory) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        }

        // Form validation
        const galleryForm = document.getElementById('galleryForm');
        if (galleryForm) {
            galleryForm.addEventListener('submit', function(e) {
                // set mobile flag before validation
                const isMobileField = document.getElementById('isMobile');
                if (isMobileField) isMobileField.value = isMobileDevice() ? '1' : '0';

                const title = document.getElementById('title').value.trim();
                const image = document.getElementById('image').files[0];
                
                if (!title) {
                    e.preventDefault();
                    alert('Please enter an image title');
                    document.getElementById('title').focus();
                    return;
                }
                
                if (!image && !<?php echo $edit_item ? 'true' : 'false'; ?>) {
                    e.preventDefault();
                    alert('Please select an image to upload');
                    return;
                }
            });
        }

        // Auto-generate slug from title
        const titleInput = document.getElementById('title');
        if (titleInput) {
            titleInput.addEventListener('blur', function() {
                // You could add auto-slug generation here if needed
            });
        }

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

        // Lazy loading for images
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        imageObserver.unobserve(img);
                    }
                });
            });

            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }
    });
</script>

<?php include 'includes/admin-footer.php'; ?>