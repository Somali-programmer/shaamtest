<?php
// Database Configuration
define('DB_HOST', 'sql103.infinityfree.com');
define('DB_NAME', 'if0_40546630_youtuber');
define('DB_USER', 'if0_40546630');
define('DB_PASS', 'nimcoapdi12');

// Application Configuration
define('SITE_URL', 'http://shaamtest.ct.ws/');
define('UPLOAD_PATH', __DIR__ . '/../assets/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Security Configuration
define('ADMIN_SESSION_TIMEOUT', 3600); // 1 hour
define('BCRYPT_COST', 12);

// Create database connection with error handling
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    // Avoid printing database error details directly to responses.
    // Return JSON for API requests, or a minimal HTML message for pages.
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
    $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    $isApi = strpos($scriptName, '/api/') !== false || stripos($acceptHeader, 'application/json') !== false || $isAjax;

    if ($isApi) {
        header('Content-Type: application/json', true, 500);
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    } else {
        http_response_code(500);
        echo '<h1>Service unavailable</h1><p>Database connection failed. Please try again later.</p>';
        exit;
    }
}

// Utility functions
function getYouTubeId($url) {
    preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches);
    return $matches[1] ?? '';
}

function generateSlug($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    
    if (empty($text)) {
        return 'n-a';
    }
    
    return $text;
}

function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

function logActivity($pdo, $user_id, $action, $description = '', $table_name = null, $record_id = null) {
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, description, table_name, record_id, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    try {
        $stmt->execute([
            $user_id,
            $action,
            $description,
            $table_name,
            $record_id,
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    } catch (PDOException $e) {
        error_log("Activity log failed: " . $e->getMessage());
        return false;
    }
    return true;
}

// Start session if not already started (cross-browser friendly defaults)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'] ?? '',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

/**
 * Lightweight check for admin session validity without forcing redirect.
 * Returns true if session is valid, false otherwise.
 */
function checkAdminSession() {
    // If basic session flags missing, try cookie fallback for cross-browser
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        if (!isset($_COOKIE['admin_session']) || $_COOKIE['admin_session'] !== session_id()) {
            return false;
        }
        // Check timeout
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > ADMIN_SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            return false;
        }
    }
    return true;
}

/**
 * Check admin authentication and session timeout.
 * Redirects to `login.php` (in the admin folder) if not authenticated or timed out.
 */
function checkAdminAuth() {
    global $pdo;

    if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
        header('Location: login.php');
        exit;
    }

    // Session timeout handling
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > ADMIN_SESSION_TIMEOUT) {
        // Destroy session and force re-login
        session_unset();
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }

    // Update last activity timestamp
    $_SESSION['last_activity'] = time();

    // Optional: ensure admin user still exists and is active
    if (isset($_SESSION['admin_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT id, is_active FROM admin_users WHERE id = ?");
            $stmt->execute([$_SESSION['admin_id']]);
            $user = $stmt->fetch();
            if (!$user || !$user['is_active']) {
                session_unset();
                session_destroy();
                header('Location: login.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log('checkAdminAuth DB error: ' . $e->getMessage());
        }
    }
}
?>