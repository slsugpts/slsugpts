<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$db = db();
$user_id = $_SESSION['user_id'];

// Get unread count for badge
$unread_reply_count = $db->query("SELECT COUNT(*) FROM notifications WHERE recipient_type = 'admin' AND recipient_id = $user_id AND is_read = 0 AND type = 'reply'")->fetchColumn();

// Get unread notification count for badge
$unread_count = $db->query(
    "SELECT COUNT(*) FROM notifications WHERE recipient_type = 'admin' AND recipient_id = " . (int)$_SESSION['user_id'] . " AND is_read = 0 AND type != 'reply'"
)->fetchColumn();

// Handle viewing a single reply
$view_reply = null;
$view_message_id = 0;
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $view_message_id = (int)$_GET['view'];
    $stmt = $db->prepare("
        SELECT n.*, g.first_name, g.last_name, g.student_id, g.email
        FROM notifications n
        LEFT JOIN graduates g ON n.sender_id = g.id
        WHERE n.id = ? AND n.recipient_type = 'admin' AND n.recipient_id = ? AND n.type = 'reply'
    ");
    $stmt->execute([$view_message_id, $user_id]);
    $view_reply = $stmt->fetch();
    
    // Mark as read when viewed
    if ($view_reply && !$view_reply['is_read']) {
        $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND recipient_type = 'admin' AND recipient_id = ? AND type = 'reply'")->execute([$view_message_id, $user_id]);
        // Refresh the view_reply data
        $stmt = $db->prepare("
            SELECT n.*, g.first_name, g.last_name, g.student_id, g.email
            FROM notifications n
            LEFT JOIN graduates g ON n.sender_id = g.id
            WHERE n.id = ? AND n.recipient_type = 'admin' AND n.recipient_id = ? AND n.type = 'reply'
        ");
        $stmt->execute([$view_message_id, $user_id]);
        $view_reply = $stmt->fetch();
    }
}

// Mark reply as read via AJAX or direct
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND recipient_type = 'admin' AND recipient_id = ? AND type = 'reply'")->execute([$_GET['mark_read'], $user_id]);
    header('Location: messages.php');
    exit;
}

// Mark reply as read (old method)
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND recipient_type = 'admin' AND recipient_id = ? AND type = 'reply'")->execute([$_GET['read'], $user_id]);
    header('Location: messages.php');
    exit;
}

// Delete reply
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $db->prepare("DELETE FROM notifications WHERE id = ? AND recipient_type = 'admin' AND recipient_id = ? AND type = 'reply'")->execute([$_GET['delete'], $user_id]);
    header('Location: messages.php');
    exit;
}

// Admin replies back to graduate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_to_graduate'])) {
    $reply_to = (int)($_POST['reply_to'] ?? 0);
    $graduate_id = (int)($_POST['graduate_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if (!$graduate_id || !$subject || !$message) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO notifications (recipient_type, recipient_id, subject, message, type, sender_type, sender_id) 
                VALUES ('graduate', ?, ?, ?, 'message', 'admin', ?)
            ");
            $stmt->execute([$graduate_id, $subject, $message, $_SESSION['user_id']]);
            $success = 'Reply sent successfully!';
            audit_log('ADMIN_REPLY', "Admin replied to graduate #$graduate_id");
            
            if ($reply_to) {
                $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND recipient_type = 'admin' AND recipient_id = ?")->execute([$reply_to, $user_id]);
            }
            
            header('Location: messages.php?success=' . urlencode($success));
            exit;
        } catch (PDOException $e) {
            $error = 'Error sending reply: ' . $e->getMessage();
            header('Location: messages.php?error=' . urlencode($error));
            exit;
        }
    }
}

// Get all replies from graduates
$replies = $db->query("
    SELECT n.*, g.first_name, g.last_name, g.student_id, g.email
    FROM notifications n
    LEFT JOIN graduates g ON n.sender_id = g.id
    WHERE n.recipient_type = 'admin' AND n.recipient_id = $user_id 
    AND n.type = 'reply'
    ORDER BY n.sent_at DESC 
    LIMIT 50
")->fetchAll();

// Check for success/error messages
if (isset($_GET['success'])) {
    $success = urldecode($_GET['success']);
}
if (isset($_GET['error'])) {
    $error = urldecode($_GET['error']);
}

$total_replies = count($replies);
$unread_reply_count = $db->query("SELECT COUNT(*) FROM notifications WHERE recipient_type = 'admin' AND recipient_id = $user_id AND is_read = 0 AND type = 'reply'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages – SLSU GPTS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --gold: #C9A84C;
            --gold-lt: #E8C97A;
            --navy: #1B5E20;
            --navy-light: #E8F5E9;
            --white: #1B5E20;
            --gray: #2E7D32;
            --red: #E05656;
            --green: #4CAF50;
            --sidebar-w: 260px;
            --radius: 12px;
            --transition: .3s ease;
        }
        body {
            font-family: 'DM Sans', sans-serif;
            background: #f8f9fa;
            color: #1F2937;
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: var(--sidebar-w);
            background: var(--navy-light);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            overflow-y: auto;
            z-index: 100;
        }
        .sidebar-brand {
            padding: 1.75rem 1.5rem 1.25rem;
            border-bottom: 1px solid rgba(27,94,32,.1);
            display: flex;
            align-items: center;
            gap: .85rem;
        }
        .brand-logo {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--gold), var(--gold-lt));
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: 1rem;
            font-weight: 900;
            color: var(--navy-light);
            flex-shrink: 0;
        }
        .brand-text {
            font-size: .92rem;
            font-weight: 700;
            color: var(--white);
            line-height: 1.2;
        }
        .brand-text span {
            display: block;
            font-size: .7rem;
            color: var(--gold);
            font-weight: 400;
        }
        .nav-section-label {
            padding: 1.25rem 1.5rem .5rem;
            font-size: .65rem;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: rgba(27,94,32,.5);
        }
        .sidebar-nav {
            padding: 0 .75rem;
            flex: 1;
        }
        .nav-item {
            display: flex;
            align-items: center;
            gap: .8rem;
            padding: .7rem .9rem;
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--gray);
            font-size: .88rem;
            font-weight: 500;
            transition: var(--transition);
            margin-bottom: .2rem;
        }
        .nav-item:hover {
            background: rgba(27,94,32,.05);
            color: var(--white);
        }
        .nav-item.active {
            background: rgba(201,168,76,.15);
            color: var(--gold);
            border-left: 3px solid var(--gold);
        }
        .nav-item i {
            width: 18px;
            text-align: center;
            font-size: .9rem;
        }
        .nav-badge {
            margin-left: auto;
            background: var(--red);
            color: white;
            font-size: .7rem;
            padding: 2px 8px;
            border-radius: 50px;
            min-width: 20px;
            text-align: center;
            animation: pulse 2s infinite;
        }
        .nav-badge.zero {
            display: none;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .sidebar-footer {
            padding: 1.25rem;
            border-top: 1px solid rgba(27,94,32,.1);
        }
        .admin-info {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: 1rem;
        }
        .admin-avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--gold), var(--gold-lt));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .85rem;
            color: var(--navy-light);
            flex-shrink: 0;
        }
        .admin-name {
            font-size: .85rem;
            font-weight: 600;
            color: var(--white);
            line-height: 1.2;
        }
        .admin-role {
            font-size: .72rem;
            color: var(--gold);
        }
        .logout-btn {
            display: flex;
            align-items: center;
            gap: .6rem;
            width: 100%;
            padding: .65rem .9rem;
            background: rgba(224,86,86,.15);
            border: 1px solid rgba(224,86,86,.2);
            border-radius: var(--radius);
            color: #E05656;
            font-size: .85rem;
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
            font-family: 'DM Sans', sans-serif;
            font-weight: 500;
        }
        .logout-btn:hover {
            background: rgba(224,86,86,.25);
        }
        .main {
            margin-left: var(--sidebar-w);
            flex: 1;
            padding: 2rem 2.5rem;
            background: #f8f9fa;
            min-height: 100vh;
        }
        .page-header {
            margin-bottom: 2rem;
        }
        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--white);
        }
        .page-header p {
            color: var(--gray);
            font-size: .9rem;
            margin-top: .3rem;
            font-weight: 300;
        }
        .breadcrumb {
            font-size: .78rem;
            color: var(--gray);
            margin-bottom: .5rem;
        }
        .breadcrumb span {
            color: var(--gold);
        }
        .card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: var(--radius);
            overflow: hidden;
        }
        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .card-title {
            font-weight: 700;
            font-size: .95rem;
            color: var(--white);
            display: flex;
            align-items: center;
            gap: .6rem;
        }
        .card-title i {
            color: var(--gold);
        }
        .card-body {
            padding: 0;
        }
        .message-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: var(--transition);
        }
        .message-item:hover {
            background: #f9fafb;
        }
        .message-item.unread {
            background: rgba(201,168,76,.05);
            border-left: 3px solid var(--gold);
        }
        .message-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(201,168,76,.15);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gold);
            flex-shrink: 0;
        }
        .message-content {
            flex: 1;
            min-width: 0;
        }
        .message-content .message-link {
            text-decoration: none;
            color: inherit;
            display: block;
            cursor: pointer;
        }
        .message-content h4 {
            font-size: .9rem;
            color: var(--white);
            margin-bottom: .2rem;
        }
        .message-content .message-preview {
            font-size: .85rem;
            color: var(--gray);
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .message-content .sender-info {
            font-size: .75rem;
            color: var(--gold);
            font-weight: 600;
            margin-top: .2rem;
        }
        .message-content .status-time {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: .2rem;
            font-size: .7rem;
        }
        .message-content .status-time .read-status {
            color: var(--green);
        }
        .message-content .status-time .unread-status {
            color: var(--red);
        }
        .message-content .status-time .time {
            color: var(--gray);
        }
        .message-actions {
            display: flex;
            gap: .5rem;
            flex-shrink: 0;
        }
        .message-actions a {
            color: var(--gray);
            font-size: .85rem;
            text-decoration: none;
            padding: 6px 8px;
            border-radius: 6px;
            transition: var(--transition);
        }
        .message-actions a:hover {
            color: var(--gold);
            background: rgba(201,168,76,.1);
        }
        .message-actions .reply-btn {
            color: var(--gold);
            font-weight: 600;
        }
        .message-actions .reply-btn:hover {
            background: rgba(201,168,76,.15);
        }
        .empty-state {
            padding: 3rem;
            text-align: center;
            color: var(--gray);
        }
        .empty-state i {
            font-size: 3rem;
            color: #e5e7eb;
            margin-bottom: 1rem;
        }
        .alert {
            padding: .8rem 1.2rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .alert-success {
            background: rgba(76,175,80,.15);
            border: 1px solid rgba(76,175,80,.3);
            color: var(--green);
        }
        .alert-error {
            background: rgba(224,86,86,.15);
            border: 1px solid rgba(224,86,86,.3);
            color: var(--red);
        }
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .modal-overlay.show {
            display: flex;
        }
        .modal {
            background: white;
            border-radius: var(--radius);
            max-width: 650px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 2rem;
            position: relative;
            animation: modalIn 0.3s ease;
        }
        @keyframes modalIn {
            from { transform: translateY(-20px) scale(0.95); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }
        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--gray);
            cursor: pointer;
            transition: var(--transition);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-close:hover {
            color: var(--red);
            background: rgba(224,86,86,.1);
        }
        .modal h2 {
            font-family: 'Playfair Display', serif;
            color: var(--white);
            margin-bottom: .25rem;
            padding-right: 2rem;
            font-size: 1.3rem;
        }
        .modal .modal-subtitle {
            color: var(--gray);
            font-size: .85rem;
            margin-bottom: 1rem;
        }
        .modal .message-meta {
            background: #f9fafb;
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
        }
        .modal .message-meta .meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem 1.5rem;
            font-size: .85rem;
            color: var(--gray);
        }
        .modal .message-meta .meta-row span {
            display: flex;
            align-items: center;
            gap: .4rem;
        }
        .modal .message-meta .meta-row .label {
            font-weight: 600;
            color: var(--white);
        }
        .modal .message-body {
            color: var(--white);
            font-size: .95rem;
            line-height: 1.8;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #f9fafb;
            border-radius: var(--radius);
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .modal .reply-section {
            border-top: 2px solid #f3f4f6;
            padding-top: 1.5rem;
        }
        .modal .reply-section .reply-title {
            font-weight: 700;
            color: var(--white);
            font-size: 1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .modal .reply-section .reply-title i {
            color: var(--gold);
        }
        .modal .form-group {
            margin-bottom: 1rem;
        }
        .modal .form-group label {
            display: block;
            font-size: .8rem;
            font-weight: 600;
            color: var(--gray);
            margin-bottom: .3rem;
        }
        .modal .form-group input[type="text"] {
            width: 100%;
            padding: .7rem 1rem;
            border: 1px solid #e5e7eb;
            border-radius: var(--radius);
            font-size: .9rem;
            color: #1F2937;
            transition: var(--transition);
        }
        .modal .form-group input[type="text"]:not([readonly]) {
            background: white;
            cursor: text;
        }
        .modal .form-group input[type="text"][readonly] {
            background: #f9fafb;
            cursor: not-allowed;
        }
        .modal .form-group input[type="text"]:focus {
            border-color: var(--gold);
            outline: none;
            box-shadow: 0 0 0 3px rgba(201,168,76,.1);
        }
        .modal .form-group textarea {
            width: 100%;
            padding: .8rem 1rem;
            border: 1px solid #e5e7eb;
            border-radius: var(--radius);
            font-size: .9rem;
            font-family: 'DM Sans', sans-serif;
            resize: vertical;
            min-height: 100px;
            transition: var(--transition);
        }
        .modal .form-group textarea:focus {
            border-color: var(--gold);
            outline: none;
            box-shadow: 0 0 0 3px rgba(201,168,76,.1);
        }
        .modal .form-actions {
            display: flex;
            gap: .75rem;
            margin-top: .5rem;
            flex-wrap: wrap;
        }
        .modal .btn-send {
            padding: .7rem 2rem;
            background: var(--gold);
            color: var(--navy-light);
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-size: .9rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .modal .btn-send:hover {
            background: var(--gold-lt);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(201,168,76,.3);
        }
        .modal .btn-cancel {
            padding: .7rem 2rem;
            background: #e5e7eb;
            color: var(--gray);
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-size: .9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
        }
        .modal .btn-cancel:hover {
            background: #d1d5db;
        }
        .badge-unread {
            display: inline-block;
            background: var(--red);
            color: white;
            font-size: .6rem;
            padding: 2px 8px;
            border-radius: 50px;
            margin-left: .5rem;
        }
        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); }
            .main { margin-left: 0; padding: 1.25rem; }
            .modal { margin: 1rem; padding: 1.5rem; }
            .message-item { flex-wrap: wrap; }
            .message-actions { margin-top: .5rem; width: 100%; justify-content: flex-end; }
        }
        @media (max-width: 600px) {
            .modal .form-actions { flex-direction: column; }
            .modal .form-actions .btn-send,
            .modal .form-actions .btn-cancel { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">S</div>
        <div class="brand-text">SLSU GPTS <span>Admin Panel</span></div>
    </div>
    <div class="nav-section-label">Main</div>
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="graduates.php" class="nav-item"><i class="fas fa-user-graduate"></i> Graduates</a>
        <a href="tracer.php" class="nav-item"><i class="fas fa-map-marked-alt"></i> Tracer Records</a>
        <a href="notifications.php" class="nav-item"><i class="fas fa-bell"></i> Notifications <span class="nav-badge <?= $unread_count > 0 ? '' : 'zero' ?>"><?= $unread_count ?></span></a>
    </nav>
    <div class="nav-section-label">Messages</div>
    <nav class="sidebar-nav">
        <a href="send_message.php" class="nav-item"><i class="fas fa-paper-plane"></i> Send Message</a>
        <a href="messages.php" class="nav-item active"><i class="fas fa-envelope"></i> Inbox <span class="nav-badge <?= $unread_reply_count > 0 ? '' : 'zero' ?>"><?= $unread_reply_count ?></span></a>
    </nav>
    <div class="nav-section-label">System</div>
    <nav class="sidebar-nav">
        <a href="reports.php" class="nav-item"><i class="fas fa-chart-bar"></i> Reports</a>
        <a href="programs.php" class="nav-item"><i class="fas fa-book"></i> Programs</a>
        <a href="audit_logs.php" class="nav-item"><i class="fas fa-history"></i> Audit Logs</a>
        <a href="settings.php" class="nav-item"><i class="fas fa-cog"></i> Settings</a>
    </nav>
    <div class="sidebar-footer">
        <div class="admin-info">
            <div class="admin-avatar"><i class="fas fa-user-shield"></i></div>
            <div>
                <div class="admin-name"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
                <div class="admin-role"><?= ucfirst($_SESSION['user_role'] ?? 'Admin') ?></div>
            </div>
        </div>
        <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>
<main class="main">
    <div class="page-header">
        <div class="breadcrumb">SLSU GPTS / <span>Messages</span></div>
        <h1>Messages from Graduates</h1>
        <p><?= $unread_reply_count ?> unread <?= $unread_reply_count == 1 ? 'reply' : 'replies' ?></p>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-inbox"></i> All Replies <span style="font-size:.7rem;color:var(--gray);font-weight:400;margin-left:.5rem;">(<?= $total_replies ?> total)</span></span>
        </div>
        <div class="card-body">
            <?php if (empty($replies)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No messages from graduates yet.</p>
                    <p style="font-size:.8rem;margin-top:.5rem;color:var(--gray);">When graduates reply to your messages, they will appear here.</p>
                </div>
            <?php else: ?>
                <?php foreach ($replies as $n): ?>
                    <div class="message-item <?= $n['is_read'] ? '' : 'unread' ?>" data-message-id="<?= $n['id'] ?>">
                        <div class="message-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="message-content">
                            <a href="?view=<?= $n['id'] ?>" class="message-link" onclick="markAsRead(<?= $n['id'] ?>)">
                                <h4>
                                    <?= htmlspecialchars($n['subject']) ?>
                                    <?php if (!$n['is_read']): ?>
                                        <span class="badge-unread">New</span>
                                    <?php endif; ?>
                                </h4>
                                <div class="message-preview"><?= htmlspecialchars($n['message']) ?></div>
                                <div class="sender-info">
                                    <i class="fas fa-user"></i> <?= htmlspecialchars($n['first_name'] ?? 'Unknown') ?> 
                                    (<?= htmlspecialchars($n['student_id'] ?? 'N/A') ?>)
                                </div>
                                <div class="status-time">
                                    <?php if ($n['is_read']): ?>
                                        <span class="read-status"><i class="fas fa-check-circle"></i> Read</span>
                                    <?php else: ?>
                                        <span class="unread-status"><i class="fas fa-circle"></i> Unread</span>
                                    <?php endif; ?>
                                    <span class="time"><i class="far fa-clock"></i> <?= time_ago($n['sent_at']) ?></span>
                                </div>
                            </a>
                        </div>
                        <div class="message-actions">
                            <a href="#" onclick="openReplyModal(<?= $n['id'] ?>, <?= $n['sender_id'] ?>, '<?= htmlspecialchars($n['subject'], ENT_QUOTES) ?>')" class="reply-btn" title="Reply"><i class="fas fa-reply"></i> Reply</a>
                            <?php if (!$n['is_read']): ?>
                                <a href="?read=<?= $n['id'] ?>" title="Mark as read"><i class="fas fa-check"></i></a>
                            <?php endif; ?>
                            <a href="?delete=<?= $n['id'] ?>" title="Delete" onclick="return confirm('Delete this message?')"><i class="fas fa-trash-alt"></i></a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- View Reply Modal -->
<?php if (isset($_GET['view']) && is_numeric($_GET['view']) && $view_reply): ?>
<div class="modal-overlay show" id="viewModal">
    <div class="modal">
        <button class="modal-close" onclick="closeViewModal(<?= $view_message_id ?>)" id="viewModalClose">&times;</button>
        
        <h2><?= htmlspecialchars($view_reply['subject']) ?></h2>
        <p class="modal-subtitle">Reply from a graduate</p>
        
        <div class="message-meta">
            <div class="meta-row">
                <span><span class="label">From:</span> <?= htmlspecialchars($view_reply['first_name'] ?? 'Unknown') ?> (<?= htmlspecialchars($view_reply['student_id'] ?? 'N/A') ?>)</span>
                <span><span class="label">Email:</span> <?= htmlspecialchars($view_reply['email'] ?? '') ?></span>
                <span><span class="label">Sent:</span> <?= date('F d, Y h:i A', strtotime($view_reply['sent_at'])) ?></span>
                <span><span class="label">Status:</span> <?= $view_reply['is_read'] ? '<span style="color:var(--green);"><i class="fas fa-check-circle"></i> Read</span>' : '<span style="color:var(--red);"><i class="fas fa-circle"></i> Unread</span>' ?></span>
            </div>
        </div>
        
        <div class="message-body">
            <?= nl2br(htmlspecialchars($view_reply['message'])) ?>
        </div>
        
        <div class="reply-section">
            <div class="reply-title"><i class="fas fa-reply"></i> Reply to Graduate</div>
            <form method="POST" action="messages.php">
                <input type="hidden" name="reply_to" value="<?= $view_reply['id'] ?>">
                <input type="hidden" name="graduate_id" value="<?= $view_reply['sender_id'] ?>">
                <div class="form-group">
                    <label>Subject</label>
                    <?php 
                    // Remove ALL "RE: " prefixes from the subject
                    $clean_subject = preg_replace('/^(RE:\s*)+/i', '', $view_reply['subject']);
                    ?>
                    <input type="text" name="subject" value="RE: <?= htmlspecialchars($clean_subject) ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Your Reply</label>
                    <textarea name="message" placeholder="Type your reply here..." required></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" name="reply_to_graduate" class="btn-send"><i class="fas fa-paper-plane"></i> Send Reply</button>
                    <button type="button" class="btn-cancel" onclick="closeViewModal(<?= $view_message_id ?>)"><i class="fas fa-times"></i> Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Reply Modal (for the reply button) -->
<div class="modal-overlay" id="replyModal">
    <div class="modal">
        <button class="modal-close" onclick="closeReplyModal()">&times;</button>
        <h2><i class="fas fa-reply" style="color:var(--gold);"></i> Reply to Graduate</h2>
        <p class="modal-subtitle">Send a reply to the graduate's message</p>
        
        <form method="POST" action="messages.php">
            <input type="hidden" name="reply_to" id="reply_to_id" value="">
            <input type="hidden" name="graduate_id" id="graduate_id" value="">
            
            <div class="form-group">
                <label>Subject</label>
                <input type="text" name="subject" id="reply_subject" placeholder="Enter subject..." required>
            </div>
            <div class="form-group">
                <label>Your Reply</label>
                <textarea name="message" id="reply_message" placeholder="Type your reply here..." required></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" name="reply_to_graduate" class="btn-send"><i class="fas fa-paper-plane"></i> Send Reply</button>
                <button type="button" class="btn-cancel" onclick="closeReplyModal()"><i class="fas fa-times"></i> Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
// Function to mark message as read via AJAX
function markAsRead(messageId) {
    if (!messageId) return;
    
    // Use fetch API to mark as read silently
    fetch('messages.php?mark_read=' + messageId, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    }).catch(function(error) {
        // Silently fail if there's an error
        console.log('Error marking as read:', error);
    });
}

// Close view modal and redirect to messages page
function closeViewModal(messageId) {
    // Mark as read if not already marked
    if (messageId) {
        markAsRead(messageId);
    }
    window.location.href = 'messages.php';
}

// Close view modal when clicking outside or pressing Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        // Check if view modal is open
        var viewModal = document.getElementById('viewModal');
        if (viewModal && viewModal.classList.contains('show')) {
            closeViewModal(<?= $view_message_id ?>);
        }
        if (document.getElementById('replyModal').classList.contains('show')) {
            closeReplyModal();
        }
    }
});

// Close view modal when clicking outside
document.addEventListener('click', function(e) {
    var viewModal = document.getElementById('viewModal');
    if (viewModal && e.target === viewModal) {
        closeViewModal(<?= $view_message_id ?>);
    }
    if (document.getElementById('replyModal') && e.target === document.getElementById('replyModal')) {
        closeReplyModal();
    }
});

function openReplyModal(replyId, graduateId, subject) {
    document.getElementById('reply_to_id').value = replyId;
    document.getElementById('graduate_id').value = graduateId;
    // Remove ALL existing "RE: " prefixes before adding a new one
    var cleanSubject = subject.replace(/^(RE:\s*)+/i, '');
    document.getElementById('reply_subject').value = 'RE: ' + cleanSubject;
    document.getElementById('reply_subject').removeAttribute('readonly');
    document.getElementById('replyModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeReplyModal() {
    document.getElementById('replyModal').classList.remove('show');
    document.body.style.overflow = '';
}

// Auto-hide success messages after 5 seconds
setTimeout(function() {
    var alerts = document.querySelectorAll('.alert:not(.modal .alert)');
    alerts.forEach(function(alert) {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity = '0';
        setTimeout(function() {
            alert.style.display = 'none';
        }, 500);
    });
}, 5000);
</script>
</body>
</html>