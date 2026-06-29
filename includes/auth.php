<?php
// ============================================================
// includes/auth.php – Authentication System
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Session
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(array(
        'lifetime' => 3600,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ));
    session_start();
}

// CSRF Protection
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_verify() {
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Password
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, array('cost' => 12));
}

function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// Login
function login_admin($admin) {
    session_regenerate_id(true);
    $_SESSION['user_type'] = 'admin';
    $_SESSION['user_id'] = $admin['id'];
    $_SESSION['user_name'] = $admin['name'];
    $_SESSION['user_email'] = $admin['email'];
    $_SESSION['user_role'] = $admin['role'];
    $_SESSION['login_time'] = time();
}

function login_graduate($grad) {
    session_regenerate_id(true);
    $_SESSION['user_type'] = 'graduate';
    $_SESSION['user_id'] = $grad['id'];
    $_SESSION['user_name'] = $grad['first_name'] . ' ' . $grad['last_name'];
    $_SESSION['user_email'] = $grad['email'];
    $_SESSION['user_student_id'] = $grad['student_id'];
    $_SESSION['login_time'] = time();
}

function logout() {
    $_SESSION = array();
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

// ============================================================
// UPDATE PASSWORD FUNCTIONS
// ============================================================
function update_graduate_password($email, $hashed_password) {
    try {
        $db = Database::getInstance()->getConnection();
        
        if ($db === null) {
            error_log('Database connection is null in update_graduate_password');
            return false;
        }
        
        $stmt = $db->prepare("UPDATE graduates SET password = ? WHERE email = ?");
        return $stmt->execute([$hashed_password, $email]);
    } catch (PDOException $e) {
        error_log('update_graduate_password error: ' . $e->getMessage());
        return false;
    }
}

function update_admin_password($email, $hashed_password) {
    try {
        $db = Database::getInstance()->getConnection();
        
        if ($db === null) {
            error_log('Database connection is null in update_admin_password');
            return false;
        }
        
        $stmt = $db->prepare("UPDATE admins SET password = ? WHERE email = ?");
        return $stmt->execute([$hashed_password, $email]);
    } catch (PDOException $e) {
        error_log('update_admin_password error: ' . $e->getMessage());
        return false;
    }
}

// ============================================================
// GUARD FUNCTIONS - These use the functions from functions.php
// ============================================================
function require_admin() {
    if (!is_admin()) {
        $_SESSION['redirect_after_login'] = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        header('Location: ' . BASE_URL . '/login.php?error=unauthorized');
        exit;
    }
}

function require_graduate() {
    if (!is_graduate()) {
        $_SESSION['redirect_after_login'] = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        header('Location: ' . BASE_URL . '/login.php?error=unauthorized');
        exit;
    }
}

function require_login() {
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

// ============================================================
// NOTE: The following functions are already in functions.php:
// - is_logged_in()
// - is_admin()
// - is_graduate()
// - user_type()
// - user_id()
// - find_admin_by_email()
// - find_graduate_by_email()
// - audit_log()
// ============================================================
?>
