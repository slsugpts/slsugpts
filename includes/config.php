<?php
// ============================================================
// Database Configuration - Railway
// ============================================================

// Get database URL from environment
$db_url = getenv('DATABASE_URL');

if ($db_url) {
    // Parse the DATABASE_URL (mysql://user:pass@host:port/database)
    $parsed = parse_url($db_url);
    
    define('DB_HOST', $parsed['host']);
    define('DB_PORT', $parsed['port'] ?? 3306);
    define('DB_USER', $parsed['user']);
    define('DB_PASS', $parsed['pass']);
    define('DB_NAME', ltrim($parsed['path'], '/'));
} else {
    // Fallback for local development
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_PORT', getenv('DB_PORT') ?: 3306);
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') ?: '');
    define('DB_NAME', getenv('DB_NAME') ?: 'slsu_gpts');
}

// Site Configuration
define('BASE_URL', getenv('APP_URL') ?: 'https://slsu-gpts-production.up.railway.app');
define('SITE_NAME', 'SLSU GPTS');

// Database connection function
function db() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// CSRF Functions
function csrf_field() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
}

function csrf_verify() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Password functions
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// Auth functions
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
}

function is_admin() {
    return is_logged_in() && $_SESSION['user_type'] === 'admin';
}

function login_graduate($grad) {
    $_SESSION['user_id'] = $grad['id'];
    $_SESSION['user_type'] = 'student';
    $_SESSION['user_name'] = $grad['first_name'] . ' ' . $grad['last_name'];
    $_SESSION['user_email'] = $grad['email'];
}

function login_admin($admin) {
    $_SESSION['user_id'] = $admin['id'];
    $_SESSION['user_type'] = 'admin';
    $_SESSION['user_name'] = $admin['name'];
    $_SESSION['user_email'] = $admin['email'];
}

function logout() {
    session_destroy();
}

// Find functions
function find_graduate_by_email($email) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM graduates WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch();
}

function find_admin_by_email($email) {
    $db = db();
    $stmt = $db->prepare("SELECT * FROM admins WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch();
}

// Update functions
function update_graduate_password($email, $hashed) {
    $db = db();
    $stmt = $db->prepare("UPDATE graduates SET password = ? WHERE email = ?");
    return $stmt->execute([$hashed, $email]);
}

function update_admin_password($email, $hashed) {
    $db = db();
    $stmt = $db->prepare("UPDATE admins SET password = ? WHERE email = ?");
    return $stmt->execute([$hashed, $email]);
}

// Audit log function
function audit_log($action, $description = '') {
    if (!is_logged_in()) return;
    
    $db = db();
    $stmt = $db->prepare("INSERT INTO audit_logs (user_type, user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_type'],
        $_SESSION['user_id'],
        $action,
        $description,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}
?>
