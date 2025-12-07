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

// Handle approve action
if (isset($_GET['approve'])) {
    $comment_id = (int)$_GET['approve'];
    try {
        $stmt = $pdo->prepare("UPDATE comments SET status = 'approved' WHERE id = ?");
        $stmt->execute([$comment_id]);
        $message = 'Comment approved successfully!';
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle delete action
if (isset($_GET['delete'])) {
    $comment_id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
        $stmt->execute([$comment_id]);
        $message = 'Comment deleted successfully!';
        $message_type = 'success';
    } catch (PDOException $e) {
        $message = 'Error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_action'])) {
    $comment_ids = $_POST['comment_ids'] ?? [];
    $action = $_POST['bulk_action'];
    
    if (!empty($comment_ids)) {
        try {
            $placeholders = str_repeat('?,', count($comment_ids) - 1) . '?';
            
            if ($action === 'approve') {
                $stmt = $pdo->prepare("UPDATE comments SET status = 'approved' WHERE id IN ($placeholders)");
                $stmt->execute($comment_ids);
                $message = 'Selected comments approved successfully!';
            } elseif ($action === 'delete') {
                $stmt = $pdo->prepare("DELETE FROM comments WHERE id IN ($placeholders)");
                $stmt->execute($comment_ids);
                $message = 'Selected comments deleted successfully!';
            }
            
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Get all comments with post/video info
try {
    $stmt = $pdo->query("
        SELECT c.*, 
               p.title as post_title,
               v.title as video_title
        FROM comments c 
        LEFT JOIN posts p ON c.post_id = p.id 
        LEFT JOIN videos v ON c.video_id = v.id 
        ORDER BY c.created_at DESC
    ");
    $comments = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Get counts for tabs
$pending_count = 0;
$approved_count = 0;
$all_count = count($comments);
foreach ($comments as $comment) {
    if ($comment['status'] === 'pending') $pending_count++;
    if ($comment['status'] === 'approved') $approved_count++;
}
?>

<?php include 'includes/admin-header.php'; ?>

<div class="content-header">
    <div class="header-left">
        <h1>Manage Comments</h1>
        <p class="page-description">Moderate and manage user comments across your content</p>
    </div>
    <div class="header-right">
        <div class="comment-stats">
            <div class="stat-item">
                <span class="stat-number"><?php echo $all_count; ?></span>
                <span class="stat-label">Total</span>
            </div>
            <div class="stat-item">
                <span class="stat-number text-warning"><?php echo $pending_count; ?></span>
                <span class="stat-label">Pending</span>
            </div>
            <div class="stat-item">
                <span class="stat-number text-success"><?php echo $approved_count; ?></span>
                <span class="stat-label">Approved</span>
            </div>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> fade-in">
        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<div class="comments-manager">
    <div class="card comments-card">
        <div class="card-header">
            <h2>Comments Management</h2>
            <div class="header-actions">
                <div class="search-box">
                    <input type="text" id="commentsSearch" placeholder="Search comments..." class="search-input">
                    <i class="fas fa-search search-icon"></i>
                </div>
            </div>
        </div>
        <div class="card-body">
            <!-- Tabs -->
            <div class="tabs">
                <button class="tab active" onclick="filterComments('all')">
                    All Comments
                    <span class="tab-badge"><?php echo $all_count; ?></span>
                </button>
                <button class="tab" onclick="filterComments('pending')">
                    Pending
                    <span class="tab-badge"><?php echo $pending_count; ?></span>
                </button>
                <button class="tab" onclick="filterComments('approved')">
                    Approved
                    <span class="tab-badge"><?php echo $approved_count; ?></span>
                </button>
            </div>

            <form method="POST" action="" id="bulkForm">
                <!-- Bulk Actions -->
                <div class="bulk-actions">
                    <div class="bulk-select">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                        <label for="selectAll">Select All</label>
                    </div>
                    <select name="bulk_action" class="form-control bulk-action-select">
                        <option value="">Bulk Actions</option>
                        <option value="approve">Approve Selected</option>
                        <option value="delete">Delete Selected</option>
                    </select>
                    <button type="submit" class="btn btn-primary bulk-action-btn">
                        Apply
                    </button>
                    <div class="selected-count" id="selectedCount">
                        0 comments selected
                    </div>
                </div>

                <!-- Comments Table -->
                <?php if ($comments): ?>
                    <div class="table-responsive">
                        <table class="data-table mobile-friendly">
                            <thead>
                                <tr>
                                    <th width="30">
                                        <input type="checkbox" id="selectAllHeader" onchange="toggleSelectAll()">
                                    </th>
                                    <th>Author</th>
                                    <th>Comment</th>
                                    <th>On</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th width="120">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="commentsTable">
                                <?php foreach ($comments as $comment): ?>
                                <tr class="comment-row" data-status="<?php echo $comment['status']; ?>">
                                    <td>
                                        <input type="checkbox" name="comment_ids[]" value="<?php echo $comment['id']; ?>" class="comment-checkbox" onchange="updateSelectedCount()">
                                    </td>
                                    <td>
                                        <div class="comment-author">
                                            <strong><?php echo htmlspecialchars($comment['author_name']); ?></strong>
                                            <?php if ($comment['author_email']): ?>
                                                <br><small class="author-email"><?php echo htmlspecialchars($comment['author_email']); ?></small>
                                            <?php endif; ?>
                                            <?php if ($comment['author_website']): ?>
                                                <br><small class="author-website">
                                                    <a href="<?php echo htmlspecialchars($comment['author_website']); ?>" target="_blank">
                                                        <?php echo htmlspecialchars($comment['author_website']); ?>
                                                    </a>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="comment-content">
                                            <?php echo htmlspecialchars($comment['content']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="comment-context">
                                            <?php if ($comment['post_title']): ?>
                                                <i class="fas fa-newspaper text-primary"></i>
                                                Post: <?php echo htmlspecialchars($comment['post_title']); ?>
                                            <?php elseif ($comment['video_title']): ?>
                                                <i class="fas fa-video text-danger"></i>
                                                Video: <?php echo htmlspecialchars($comment['video_title']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $comment['status']; ?>">
                                            <?php echo ucfirst($comment['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="comment-date">
                                            <?php echo date('M j, Y', strtotime($comment['created_at'])); ?>
                                            <br>
                                            <small><?php echo date('g:i A', strtotime($comment['created_at'])); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <?php if ($comment['status'] === 'pending'): ?>
                                                <a href="manage-comments.php?approve=<?php echo $comment['id']; ?>" 
                                                   class="btn btn-success btn-sm"
                                                   title="Approve comment">
                                                    <i class="fas fa-check"></i>
                                                    <span class="btn-text">Approve</span>
                                                </a>
                                            <?php else: ?>
                                                <a href="manage-comments.php?approve=<?php echo $comment['id']; ?>" 
                                                   class="btn btn-outline btn-sm"
                                                   title="Re-approve comment" style="display: none;">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            <a href="manage-comments.php?delete=<?php echo $comment['id']; ?>" 
                                               class="btn btn-danger btn-sm"
                                               onclick="return confirm('Are you sure you want to delete this comment? This action cannot be undone.')"
                                               title="Delete comment">
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
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <h3>No Comments Yet</h3>
                        <p>Comments will appear here when users start engaging with your content.</p>
                        <div class="empty-state-actions">
                            <a href="../index.php" target="_blank" class="btn btn-outline">
                                <i class="fas fa-external-link-alt"></i>
                                View Website
                            </a>
                            <a href="manage-posts.php" class="btn btn-primary">
                                <i class="fas fa-newspaper"></i>
                                Manage Posts
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Quick Stats Card -->
    <div class="card stats-card">
        <div class="card-header">
            <h2>Comments Overview</h2>
        </div>
        <div class="card-body">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Comments</h3>
                        <div class="stat-number"><?php echo $all_count; ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Pending Review</h3>
                        <div class="stat-number"><?php echo $pending_count; ?></div>
                        <?php if ($pending_count > 0): ?>
                            <div class="stat-change negative">
                                <i class="fas fa-exclamation-circle"></i>
                                Needs attention
                            </div>
                        <?php else: ?>
                            <div class="stat-change positive">
                                <i class="fas fa-check-circle"></i>
                                All clear
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon approved">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Approved</h3>
                        <div class="stat-number"><?php echo $approved_count; ?></div>
                        <div class="stat-change positive">
                            <i class="fas fa-chart-line"></i>
                            <?php echo $all_count > 0 ? round(($approved_count / $all_count) * 100) : 0; ?>% approved
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Comments Manager Specific Styles */
    .comments-manager {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }

    .comment-stats {
        display: flex;
        gap: 1.5rem;
        align-items: center;
    }

    .stat-item {
        text-align: center;
    }

    .stat-number {
        display: block;
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
    }

    .stat-label {
        font-size: 0.8rem;
        color: var(--admin-text-light);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .text-warning {
        color: var(--admin-warning);
    }

    .text-success {
        color: var(--admin-success);
    }

    .text-primary {
        color: var(--admin-info);
    }

    .text-danger {
        color: var(--admin-danger);
    }

    .text-muted {
        color: var(--admin-text-light);
    }

    /* Tabs */
    .tabs {
        display: flex;
        margin-bottom: 1.5rem;
        border-bottom: 1px solid var(--admin-border);
        flex-wrap: wrap;
    }

    .tab {
        padding: 0.75rem 1.5rem;
        background: none;
        border: none;
        cursor: pointer;
        font-weight: 500;
        color: var(--admin-text-light);
        border-bottom: 2px solid transparent;
        transition: all 0.3s;
        min-height: 44px;
        flex: 1;
        min-width: 120px;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .tab.active {
        color: var(--admin-accent);
        border-bottom-color: var(--admin-accent);
    }

    .tab-badge {
        background: var(--admin-accent);
        color: white;
        padding: 0.2rem 0.5rem;
        border-radius: 10px;
        font-size: 0.7rem;
    }

    /* Bulk Actions */
    .bulk-actions {
        display: flex;
        gap: 1rem;
        align-items: center;
        margin-bottom: 1rem;
        padding: 1rem;
        background: var(--admin-border-light);
        border-radius: 5px;
        flex-wrap: wrap;
    }

    .bulk-select {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .bulk-action-select {
        width: auto;
        min-width: 150px;
    }

    .bulk-action-btn {
        min-width: 80px;
    }

    .selected-count {
        margin-left: auto;
        font-size: 0.9rem;
        color: var(--admin-text-light);
    }

    /* Comment Table Styles */
    .comment-author {
        min-width: 150px;
    }

    .author-email, .author-website {
        font-size: 0.8rem;
        color: var(--admin-text-light);
    }

    .author-website a {
        color: var(--admin-info);
        text-decoration: none;
    }

    .author-website a:hover {
        text-decoration: underline;
    }

    .comment-content {
        max-width: 300px;
        line-height: 1.4;
        word-wrap: break-word;
    }

    .comment-context {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        min-width: 150px;
    }

    .comment-date {
        font-size: 0.9rem;
        color: var(--admin-text);
    }

    .comment-date small {
        color: var(--admin-text-light);
    }

    /* Stats Card */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
    }

    .stats-card .stat-card {
        background: var(--admin-card-bg);
        padding: 1.5rem;
        border-radius: 8px;
        box-shadow: var(--admin-shadow);
        border-left: 4px solid var(--admin-accent);
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: all 0.3s;
    }

    .stats-card .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--admin-shadow-lg);
    }

    .stats-card .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        color: white;
        flex-shrink: 0;
    }

    .stats-card .stat-icon.pending {
        background: linear-gradient(135deg, #f093fb, #f5576c);
    }

    .stats-card .stat-icon.approved {
        background: linear-gradient(135deg, #43e97b, #38f9d7);
    }

    .stats-card .stat-content h3 {
        font-size: 0.8rem;
        color: var(--admin-text-light);
        margin-bottom: 0.5rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stats-card .stat-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--admin-text);
        line-height: 1;
        margin-bottom: 0.25rem;
    }

    .stats-card .stat-change {
        font-size: 0.7rem;
    }

    .empty-state-actions {
        display: flex;
        gap: 1rem;
        justify-content: center;
        margin-top: 1.5rem;
        flex-wrap: wrap;
    }

    /* Mobile Responsive Styles */
    @media (max-width: 991px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 767px) {
        .content-header {
            flex-direction: column;
            align-items: stretch;
            gap: 1rem;
        }

        .comment-stats {
            justify-content: space-around;
            width: 100%;
        }

        .header-actions {
            flex-direction: column;
            align-items: stretch;
            width: 100%;
        }

        .search-input {
            width: 100%;
        }

        .tabs {
            flex-direction: column;
        }

        .tab {
            flex: none;
            text-align: left;
            min-width: auto;
            justify-content: flex-start;
        }

        .bulk-actions {
            flex-direction: column;
            align-items: stretch;
            gap: 0.75rem;
        }

        .bulk-select {
            order: 1;
        }

        .bulk-action-select {
            order: 2;
            width: 100%;
        }

        .bulk-action-btn {
            order: 3;
            width: 100%;
        }

        .selected-count {
            order: 4;
            margin-left: 0;
            text-align: center;
        }

        .comment-content {
            max-width: 200px;
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

        .empty-state-actions {
            flex-direction: column;
        }

        .empty-state-actions .btn {
            width: 100%;
            justify-content: center;
        }
    }

    @media (max-width: 480px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }

        .stats-card .stat-card {
            padding: 1rem;
        }

        .comment-content {
            max-width: 150px;
        }

        .comment-context {
            min-width: 120px;
        }

        .comment-author {
            min-width: 120px;
        }
    }

    @media (max-width: 360px) {
        .comment-stats {
            flex-direction: column;
            gap: 0.5rem;
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }

        .stat-number {
            margin-bottom: 0;
        }
    }
</style>

<script>
    // Tab filtering
    function filterComments(status) {
        const rows = document.querySelectorAll('.comment-row');
        const tabs = document.querySelectorAll('.tab');
        
        // Update active tab
        tabs.forEach(tab => tab.classList.remove('active'));
        event.target.classList.add('active');
        
        // Filter rows
        rows.forEach(row => {
            if (status === 'all' || row.dataset.status === status) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });

        // Update selected count after filtering
        updateSelectedCount();
    }

    // Bulk selection
    function toggleSelectAll() {
        const checkboxes = document.querySelectorAll('.comment-checkbox');
        const selectAll = document.getElementById('selectAll');
        const selectAllHeader = document.getElementById('selectAllHeader');
        
        const isChecked = selectAll.checked || selectAllHeader.checked;
        checkboxes.forEach(checkbox => {
            if (checkbox.closest('.comment-row').style.display !== 'none') {
                checkbox.checked = isChecked;
            }
        });
        
        // Sync the two select all checkboxes
        selectAll.checked = isChecked;
        selectAllHeader.checked = isChecked;
        
        updateSelectedCount();
    }

    // Update selected count
    function updateSelectedCount() {
        const checkboxes = document.querySelectorAll('.comment-checkbox');
        const selectedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
        const selectedCountElement = document.getElementById('selectedCount');
        
        if (selectedCountElement) {
            selectedCountElement.textContent = `${selectedCount} comment${selectedCount !== 1 ? 's' : ''} selected`;
        }
    }

    // Update select all when individual checkboxes change
    document.addEventListener('DOMContentLoaded', function() {
        const checkboxes = document.querySelectorAll('.comment-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateSelectedCount();
                
                // Update select all checkbox state
                const visibleCheckboxes = Array.from(document.querySelectorAll('.comment-checkbox'))
                    .filter(cb => cb.closest('.comment-row').style.display !== 'none');
                const allChecked = visibleCheckboxes.length > 0 && 
                    visibleCheckboxes.every(cb => cb.checked);
                const someChecked = visibleCheckboxes.some(cb => cb.checked);
                
                document.getElementById('selectAll').checked = allChecked;
                document.getElementById('selectAllHeader').checked = allChecked;
                document.getElementById('selectAll').indeterminate = !allChecked && someChecked;
                document.getElementById('selectAllHeader').indeterminate = !allChecked && someChecked;
            });
        });

        // Search functionality
        const searchInput = document.getElementById('commentsSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('.comment-row');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });

                // Update selected count after search
                updateSelectedCount();
            });
        }

        // Bulk form submission
        const bulkForm = document.getElementById('bulkForm');
        if (bulkForm) {
            bulkForm.addEventListener('submit', function(e) {
                const selectedCount = document.querySelectorAll('.comment-checkbox:checked').length;
                const action = document.querySelector('select[name="bulk_action"]').value;
                
                if (selectedCount === 0) {
                    e.preventDefault();
                    alert('Please select at least one comment');
                    return;
                }
                
                if (!action) {
                    e.preventDefault();
                    alert('Please select a bulk action');
                    return;
                }
                
                if (action === 'delete') {
                    if (!confirm(`Are you sure you want to delete ${selectedCount} comment${selectedCount !== 1 ? 's' : ''}? This action cannot be undone.`)) {
                        e.preventDefault();
                    }
                }
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

        // Auto-refresh comments every 60 seconds
        setInterval(() => {
            // In a real implementation, you'd fetch new comments via AJAX
            console.log('Auto-refresh check for new comments');
        }, 60000);
    });

    // Quick approve with keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + A to approve selected comments
        if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
            e.preventDefault();
            const selectedCount = document.querySelectorAll('.comment-checkbox:checked').length;
            if (selectedCount > 0) {
                document.querySelector('select[name="bulk_action"]').value = 'approve';
                document.getElementById('bulkForm').submit();
            }
        }
    });
</script>

<?php include 'includes/admin-footer.php'; ?>