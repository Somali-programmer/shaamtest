<?php
require_once __DIR__ . '/includes/admin-header.php';
require_once __DIR__ . '/includes/admin-sidebar.php';

checkAdminAuth();

// Fetch top posts by likes
try {
    $postsStmt = $pdo->prepare("SELECT id, title, slug, likes, published_at FROM posts ORDER BY likes DESC, views DESC LIMIT 50");
    $postsStmt->execute();
    $topPosts = $postsStmt->fetchAll();

    $videosStmt = $pdo->prepare("SELECT id, title, slug, likes, published_at FROM videos ORDER BY likes DESC, views DESC LIMIT 50");
    $videosStmt->execute();
    $topVideos = $videosStmt->fetchAll();
} catch (PDOException $e) {
    $topPosts = [];
    $topVideos = [];
}

?>

<main class="admin-main">
    <div class="admin-container">
        <h1>Likes Report</h1>
        <section class="report-section">
            <h2>Top Posts</h2>
            <div style="margin-bottom: 0.5rem;">
                <a href="?export=posts" class="btn">Export CSV (Posts)</a>
            </div>
            <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Likes</th>
                        <th>Views</th>
                        <th>Published</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topPosts as $i => $p): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo htmlspecialchars($p['title']); ?></td>
                        <td><?php echo (int)$p['likes']; ?></td>
                        <td>
                            <?php
                            // fetch views safely
                            try {
                                $vStmt = $pdo->prepare('SELECT views FROM posts WHERE id = ?');
                                $vStmt->execute([$p['id']]);
                                echo (int)$vStmt->fetchColumn();
                            } catch (Exception $e) {
                                echo '0';
                            }
                            ?>
                        </td>
                        <td><?php echo $p['published_at']; ?></td>
                        <td>
                            <a href="manage-posts.php?edit=<?php echo $p['id']; ?>" class="btn">Edit</a>
                            <a href="../?p=<?php echo urlencode($p['slug']); ?>" target="_blank" class="btn">View</a>
                            <form method="POST" action="reset-likes.php" style="display:inline;margin-left:6px;" onsubmit="return confirm('Reset likes for this post?');">
                                <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                <input type="hidden" name="type" value="post">
                                <button type="submit" class="btn btn-danger">Reset Likes</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </section>

        <section class="report-section">
            <h2>Top Videos</h2>
            <div style="margin-bottom: 0.5rem;">
                <a href="?export=videos" class="btn">Export CSV (Videos)</a>
            </div>
            <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Likes</th>
                        <th>Views</th>
                        <th>Published</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topVideos as $i => $v): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo htmlspecialchars($v['title']); ?></td>
                        <td><?php echo (int)$v['likes']; ?></td>
                        <td>
                            <?php
                            try {
                                $vStmt = $pdo->prepare('SELECT views FROM videos WHERE id = ?');
                                $vStmt->execute([$v['id']]);
                                echo (int)$vStmt->fetchColumn();
                            } catch (Exception $e) {
                                echo '0';
                            }
                            ?>
                        </td>
                        <td><?php echo $v['published_at']; ?></td>
                        <td>
                            <a href="manage-videos.php?edit=<?php echo $v['id']; ?>" class="btn">Edit</a>
                            <a href="../videos.html#video-<?php echo $v['id']; ?>" target="_blank" class="btn">View</a>
                            <form method="POST" action="reset-likes.php" style="display:inline;margin-left:6px;" onsubmit="return confirm('Reset likes for this video?');">
                                <input type="hidden" name="id" value="<?php echo $v['id']; ?>">
                                <input type="hidden" name="type" value="video">
                                <button type="submit" class="btn btn-danger">Reset Likes</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </section>
    </div>
</main>

<?php
// CSV export handling
if (isset($_GET['export'])) {
    $which = $_GET['export'];
    if ($which === 'posts') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="likes_posts.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['id','title','slug','likes','views','published_at']);
        foreach ($topPosts as $p) {
            // fetch views quickly
            $v = 0;
            try { $vStmt = $pdo->prepare('SELECT views FROM posts WHERE id = ?'); $vStmt->execute([$p['id']]); $v = (int)$vStmt->fetchColumn(); } catch (Exception $e) {}
            fputcsv($out, [$p['id'], $p['title'], $p['slug'], $p['likes'], $v, $p['published_at']]);
        }
        fclose($out);
        exit;
    } elseif ($which === 'videos') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="likes_videos.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['id','title','slug','likes','views','published_at']);
        foreach ($topVideos as $v) {
            $vv = 0;
            try { $vStmt = $pdo->prepare('SELECT views FROM videos WHERE id = ?'); $vStmt->execute([$v['id']]); $vv = (int)$vStmt->fetchColumn(); } catch (Exception $e) {}
            fputcsv($out, [$v['id'], $v['title'], $v['slug'], $v['likes'], $vv, $v['published_at']]);
        }
        fclose($out);
        exit;
    }
}

require_once __DIR__ . '/includes/admin-footer.php';
?>
