<?php
// ============================================================
// includes/functions.php – Helper Functions
// ============================================================

require_once __DIR__ . '/db.php';

// ============================================================
// SESSION / USER FUNCTIONS - MUST BE DEFINED FIRST
// ============================================================

function is_logged_in() {
    return isset($_SESSION['user_type']) && isset($_SESSION['user_id']);
}

function is_admin() {
    return (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin');
}

function is_graduate() {
    return (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'graduate');
}

function user_id() {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
}

function user_name() {
    return isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
}

function user_type() {
    return isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';
}

// ============================================================
// LOOKUP FUNCTIONS
// ============================================================

function find_admin_by_email($email) {
    return fetch('SELECT * FROM admins WHERE email = ? AND is_active = 1 LIMIT 1', array($email));
}

function find_graduate_by_email($email) {
    return fetch('SELECT * FROM graduates WHERE email = ? AND is_active = 1 LIMIT 1', array($email));
}

// ============================================================
// AUDIT LOG
// ============================================================

function audit_log($action, $description = '') {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    insert('audit_logs', array(
        'user_type' => user_type(),
        'user_id' => user_id(),
        'action' => $action,
        'description' => $description,
        'ip_address' => $ip,
        'user_agent' => $user_agent
    ));
}

// ============================================================
// NOTIFICATION FUNCTIONS
// ============================================================

/**
 * Send a notification to a graduate
 * 
 * @param int $recipient_id The graduate/user ID
 * @param string $subject The notification subject
 * @param string $message The notification message
 * @param string $type The notification type (survey, account, announcement, event, reminder, update)
 * @param string $sender_type Who sent it (system, admin)
 * @param int|null $sender_id Optional sender ID
 * @return bool Success status
 */
function send_notification($recipient_id, $subject, $message, $type = 'update', $sender_type = 'system', $sender_id = null) {
    $db = db();
    $stmt = $db->prepare("
        INSERT INTO notifications (recipient_type, recipient_id, subject, message, type, sender_type, sender_id) 
        VALUES ('graduate', ?, ?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$recipient_id, $subject, $message, $type, $sender_type, $sender_id]);
}

/**
 * Send notification to multiple graduates at once
 * 
 * @param array $recipient_ids Array of graduate IDs
 * @param string $subject The notification subject
 * @param string $message The notification message
 * @param string $type The notification type
 * @param string $sender_type Who sent it
 * @return int Number of notifications sent
 */
function send_notification_bulk($recipient_ids, $subject, $message, $type = 'update', $sender_type = 'system') {
    $db = db();
    $count = 0;
    $stmt = $db->prepare("
        INSERT INTO notifications (recipient_type, recipient_id, subject, message, type, sender_type) 
        VALUES ('graduate', ?, ?, ?, ?, ?)
    ");
    
    foreach ($recipient_ids as $id) {
        if ($stmt->execute([$id, $subject, $message, $type, $sender_type])) {
            $count++;
        }
    }
    return $count;
}

/**
 * Get unread notifications count for a user
 */
function get_unread_notifications_count($user_type, $user_id) {
    $db = db();
    $result = fetch(
        'SELECT COUNT(*) as count FROM notifications 
         WHERE recipient_type = ? AND recipient_id = ? AND is_read = 0',
        array($user_type, $user_id)
    );
    if ($result && isset($result['count'])) {
        return (int)$result['count'];
    }
    return 0;
}

/**
 * Get unread system notifications only (no messages)
 */
function get_unread_system_notifications_count($user_id) {
    $db = db();
    $result = fetch(
        'SELECT COUNT(*) as count FROM notifications 
         WHERE recipient_type = ? AND recipient_id = ? AND is_read = 0 
         AND sender_type != ? AND type NOT IN (?, ?)',
        array('graduate', $user_id, 'admin', 'message', 'reply')
    );
    if ($result && isset($result['count'])) {
        return (int)$result['count'];
    }
    return 0;
}

// ============================================================
// COLLEGE & PROGRAM FUNCTIONS
// ============================================================

function get_colleges() {
    $result = fetchAll('SELECT * FROM colleges WHERE is_active = 1 ORDER BY name');
    if ($result) {
        return $result;
    }
    return array();
}

function get_programs($college_id = null) {
    $sql = 'SELECT p.*, c.name as college_name FROM programs p 
            LEFT JOIN colleges c ON p.college_id = c.id 
            WHERE p.is_active = 1';
    $params = array();
    if ($college_id) {
        $sql .= ' AND p.college_id = ?';
        $params[] = $college_id;
    }
    $sql .= ' ORDER BY p.name';
    $result = fetchAll($sql, $params);
    if ($result) {
        return $result;
    }
    return array();
}

function get_program_name($id) {
    $result = fetch('SELECT name FROM programs WHERE id = ?', array($id));
    if ($result && isset($result['name'])) {
        return $result['name'];
    }
    return 'N/A';
}

function get_college_name($id) {
    $result = fetch('SELECT name FROM colleges WHERE id = ?', array($id));
    if ($result && isset($result['name'])) {
        return $result['name'];
    }
    return 'N/A';
}

// ============================================================
// GRADUATE FUNCTIONS
// ============================================================

function get_graduate_full_name($id) {
    $result = fetch('SELECT first_name, last_name FROM graduates WHERE id = ?', array($id));
    if ($result && isset($result['first_name']) && isset($result['last_name'])) {
        return $result['first_name'] . ' ' . $result['last_name'];
    }
    return 'Unknown';
}

function get_total_graduates() {
    $result = fetch('SELECT COUNT(*) as count FROM graduates WHERE is_active = 1');
    if ($result && isset($result['count'])) {
        return (int)$result['count'];
    }
    return 0;
}

function get_recent_graduates($limit = 8) {
    $result = fetchAll(
        'SELECT g.*, p.code as program_code, p.name as program_name 
         FROM graduates g 
         LEFT JOIN programs p ON g.program_id = p.id 
         WHERE g.is_active = 1 
         ORDER BY g.created_at DESC 
         LIMIT ?',
        array($limit)
    );
    if ($result) {
        return $result;
    }
    return array();
}

function get_user_graduate($id) {
    $result = fetch('SELECT * FROM graduates WHERE id = ?', array($id));
    if ($result) {
        return $result;
    }
    return array();
}

// ============================================================
// TRACER SURVEY FUNCTIONS
// ============================================================

function get_total_surveys() {
    $result = fetch('SELECT COUNT(*) as count FROM tracer_records');
    if ($result && isset($result['count'])) {
        return (int)$result['count'];
    }
    return 0;
}

function get_employment_status_count($status) {
    $result = fetch('SELECT COUNT(*) as count FROM tracer_records WHERE employment_status = ?', array($status));
    if ($result && isset($result['count'])) {
        return (int)$result['count'];
    }
    return 0;
}

function get_employment_rate() {
    $total = get_total_surveys();
    if ($total == 0) return 0;
    $employed = get_employment_status_count('Employed') + get_employment_status_count('Self-employed');
    return round(($employed / $total) * 100);
}

function get_recent_surveys($limit = 8) {
    $result = fetchAll(
        'SELECT t.*, g.first_name, g.last_name, g.student_id 
         FROM tracer_records t 
         JOIN graduates g ON t.graduate_id = g.id 
         ORDER BY t.submitted_at DESC 
         LIMIT ?',
        array($limit)
    );
    if ($result) {
        return $result;
    }
    return array();
}

// ============================================================
// ADMIN FUNCTIONS
// ============================================================

function get_user_admin($id) {
    $result = fetch('SELECT * FROM admins WHERE id = ?', array($id));
    if ($result) {
        return $result;
    }
    return array();
}

// ============================================================
// USER / SESSION FUNCTIONS (Legacy - kept for compatibility)
// ============================================================

function get_current_user_data() {
    if (function_exists('is_admin') && is_admin()) {
        $user = get_user_admin(user_id());
        if ($user) {
            return $user;
        }
    } elseif (function_exists('is_graduate') && is_graduate()) {
        $user = get_user_graduate(user_id());
        if ($user) {
            return $user;
        }
    }
    return array();
}

// ============================================================
// FORMATTING FUNCTIONS
// ============================================================

function format_date($date, $format = 'M d, Y') {
    if (empty($date)) {
        return 'N/A';
    }
    $timestamp = strtotime($date);
    if ($timestamp === false || $timestamp < 0) {
        return 'N/A';
    }
    return date($format, $timestamp);
}

function format_datetime($datetime, $format = 'M d, Y h:i A') {
    if (empty($datetime)) {
        return 'N/A';
    }
    $timestamp = strtotime($datetime);
    if ($timestamp === false || $timestamp < 0) {
        return 'N/A';
    }
    return date($format, $timestamp);
}

function time_ago($datetime) {
    if (empty($datetime)) {
        return 'N/A';
    }
    $time = strtotime($datetime);
    if ($time === false || $time < 0) {
        return 'N/A';
    }
    $diff = time() - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return round($diff / 60) . ' minutes ago';
    if ($diff < 86400) return round($diff / 3600) . ' hours ago';
    if ($diff < 604800) return round($diff / 86400) . ' days ago';
    if ($diff < 2592000) return round($diff / 604800) . ' weeks ago';
    if ($diff < 31536000) return round($diff / 2592000) . ' months ago';
    return date('M d, Y', $time);
}

// ============================================================
// FILE & UTILITY FUNCTIONS
// ============================================================

function generate_random_string($length = 10) {
    return bin2hex(random_bytes($length));
}

function upload_file($file, $target_dir, $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'pdf')) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return array('success' => false, 'message' => 'Upload failed');
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed_types)) {
        return array('success' => false, 'message' => 'File type not allowed');
    }
    
    $max_size = 5 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        return array('success' => false, 'message' => 'File too large');
    }
    
    $filename = generate_random_string() . '.' . $extension;
    $target_path = rtrim($target_dir, '/') . '/' . $filename;
    
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return array('success' => true, 'filename' => $filename);
    }
    
    return array('success' => false, 'message' => 'Failed to move file');
}

function delete_file($filepath) {
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return false;
}

// ============================================================
// DATA HELPERS
// ============================================================

function get_years() {
    $years = array();
    $current = date('Y');
    for ($i = $current; $i >= 1990; $i--) {
        $years[] = $i;
    }
    return $years;
}

function get_salary_ranges() {
    return array(
        'Below ₱10,000',
        '₱10,000 - ₱15,000',
        '₱15,001 - ₱20,000',
        '₱20,001 - ₱25,000',
        '₱25,001 - ₱30,000',
        '₱30,001 - ₱40,000',
        '₱40,001 - ₱50,000',
        'Above ₱50,000'
    );
}

function get_employment_statuses() {
    return array('Employed', 'Self-employed', 'Unemployed', 'Freelancer', 'Contractual', 'Part-time', 'Not in labor force');
}

function get_work_arrangements() {
    return array('On-site', 'Hybrid', 'Remote/WFH', 'Flexible');
}

function get_job_relevance_options() {
    return array('Highly relevant', 'Somewhat relevant', 'Not relevant');
}

function get_badge_class($type) {
    $classes = array(
        'admin' => 'badge-danger',
        'graduate' => 'badge-primary',
        'active' => 'badge-success',
        'inactive' => 'badge-danger',
        'Employed' => 'badge-success',
        'Self-employed' => 'badge-info',
        'Unemployed' => 'badge-warning',
        'Freelancer' => 'badge-purple',
        'Contractual' => 'badge-secondary',
        'Part-time' => 'badge-warning',
        'Not in labor force' => 'badge-secondary'
    );
    if (isset($classes[$type])) {
        return $classes[$type];
    }
    return 'badge-secondary';
}

function is_active_page($page) {
    $current = basename($_SERVER['PHP_SELF']);
    return $current == $page;
}

// ============================================================
// NOTIFICATION FUNCTIONS FOR ADMIN
// ============================================================

/**
 * Send notification to all admins
 */
function notify_admins($subject, $message, $type = 'system', $sender_type = 'system', $sender_id = null) {
    global $db;
    
    // Get all admin IDs
    $admins = $db->query("SELECT id FROM admins WHERE is_active = 1")->fetchAll();
    
    $count = 0;
    foreach ($admins as $admin) {
        $stmt = $db->prepare("
            INSERT INTO notifications (recipient_type, recipient_id, subject, message, type, sender_type, sender_id) 
            VALUES ('admin', ?, ?, ?, ?, ?, ?)
        ");
        if ($stmt->execute([$admin['id'], $subject, $message, $type, $sender_type, $sender_id])) {
            $count++;
        }
    }
    return $count;
}

/**
 * Send notification when a student updates their profile
 */
function notify_profile_update($graduate_id) {
    global $db;
    
    // Get graduate info
    $grad = fetch("SELECT first_name, last_name, student_id FROM graduates WHERE id = ?", [$graduate_id]);
    if (!$grad) {
        return false;
    }
    
    $name = $grad['first_name'] . ' ' . $grad['last_name'];
    $student_id = $grad['student_id'];
    $subject = "Profile Update: $name";
    $message = "Student $name ($student_id) has updated their profile information.";
    $type = 'profile_update';
    $sender_type = 'graduate';
    
    return notify_admins($subject, $message, $type, $sender_type, $graduate_id);
}

/**
 * Send notification when a student submits a tracer survey
 */
function notify_survey_submit($graduate_id) {
    global $db;
    
    // Get graduate info
    $grad = fetch("SELECT first_name, last_name, student_id FROM graduates WHERE id = ?", [$graduate_id]);
    if (!$grad) {
        return false;
    }
    
    $name = $grad['first_name'] . ' ' . $grad['last_name'];
    $student_id = $grad['student_id'];
    $subject = "New Tracer Survey: $name";
    $message = "Student $name ($student_id) has submitted a new tracer survey.";
    $type = 'survey_submit';
    $sender_type = 'graduate';
    
    return notify_admins($subject, $message, $type, $sender_type, $graduate_id);
}

/**
 * Send notification when a student updates their tracer survey
 */
function notify_survey_update($graduate_id) {
    global $db;
    
    // Get graduate info
    $grad = fetch("SELECT first_name, last_name, student_id FROM graduates WHERE id = ?", [$graduate_id]);
    if (!$grad) {
        return false;
    }
    
    $name = $grad['first_name'] . ' ' . $grad['last_name'];
    $student_id = $grad['student_id'];
    $subject = "Tracer Survey Updated: $name";
    $message = "Student $name ($student_id) has updated their tracer survey.";
    $type = 'survey_update';
    $sender_type = 'graduate';
    
    return notify_admins($subject, $message, $type, $sender_type, $graduate_id);
}
