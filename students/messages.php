<?php
require_once __DIR__ . '/../includes/auth.php';
require_graduate();

$db = db();
$user_id = $_SESSION['user_id'];

// Get unread count for badge (messages from admin only)
$unread_count = $db->query("
    SELECT COUNT(*) FROM notifications 
    WHERE recipient_type = 'graduate' AND recipient_id = $user_id 
    AND is_read = 0 AND sender_type = 'admin'
")->fetchColumn();

// Get unread notification count for sidebar
$unread_notif_count = $db->query("
    SELECT COUNT(*) FROM notifications 
    WHERE recipient_type = 'graduate' AND recipient_id = $user_id 
    AND is_read = 0 AND (sender_type IS NULL OR sender_type = 'system')
")->fetchColumn();

// Get messages FROM admin (incoming messages)
$messages = $db->query("
    SELECT n.*, 'Admin' as sender_name
    FROM notifications n
    WHERE n.recipient_type = 'graduate' AND n.recipient_id = $user_id 
    AND n.sender_type = 'admin'
    ORDER BY n.sent_at DESC 
    LIMIT 50
")->fetchAll();

// Get replies FROM graduate (outgoing replies)
$replies = $db->query("
    SELECT n.*, 'You' as sender_name
    FROM notifications n
    WHERE n.sender_type = 'graduate' AND n.sender_id = $user_id
    ORDER BY n.sent_at DESC 
    LIMIT 50
")->fetchAll();

// Mark a message as read
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND recipient_type = 'graduate' AND recipient_id = ?")->execute([$_GET['read'], $user_id]);
    header('Location: messages.php');
    exit;
}

// Mark all messages from admin as read
if (isset($_GET['mark_all_read'])) {
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE recipient_type = 'graduate' AND recipient_id = ? AND sender_type = 'admin' AND is_read = 0")->execute([$user_id]);
    header('Location: messages.php');
    exit;
}

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $message_id = (int)($_POST['message_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if (!$message) {
        $error = 'Please enter a reply message.';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO notifications (recipient_type, recipient_id, subject, message, type, sender_type, sender_id) 
                VALUES ('admin', 1, ?, ?, 'reply', 'graduate', ?)
            ");
            $stmt->execute([$subject, $message, $user_id]);
            $success = 'Reply sent successfully!';
            audit_log('GRADUATE_REPLY', "Graduate replied to admin message #$message_id");
            
            if ($message_id) {
                $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND recipient_type = 'graduate' AND recipient_id = ?")->execute([$message_id, $user_id]);
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

// Get fresh unread count after any updates
$unread_count = $db->query("
    SELECT COUNT(*) FROM notifications 
    WHERE recipient_type = 'graduate' AND recipient_id = $user_id 
    AND is_read = 0 AND sender_type = 'admin'
")->fetchColumn();

$total_messages = count($messages);
$total_replies = count($replies);

// Determine which tab to show (default: messages)
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'messages';

// Handle viewing a message - mark as read immediately
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $view_message_id = (int)$_GET['view'];
    
    // First, mark it as read
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND recipient_type = 'graduate' AND recipient_id = ? AND sender_type = 'admin'")->execute([$view_message_id, $user_id]);
    
    // Then fetch the message data
    $stmt = $db->prepare("
        SELECT n.*, 'Admin' as sender_name
        FROM notifications n
        WHERE n.id = ? AND n.recipient_type = 'graduate' AND n.recipient_id = ? AND n.sender_type = 'admin'
    ");
    $stmt->execute([$view_message_id, $user_id]);
    $view_message = $stmt->fetch();
    
    // Redirect to remove the view parameter from URL but keep it in session for modal
    if ($view_message) {
        $_SESSION['view_message'] = $view_message;
        header('Location: messages.php?tab=messages&show_modal=1');
        exit;
    }
}

// Check if we need to show the modal from session
$show_modal = isset($_GET['show_modal']) && isset($_SESSION['view_message']);
if ($show_modal) {
    $view_message = $_SESSION['view_message'];
    // Clear session data after retrieving
    unset($_SESSION['view_message']);
} else {
    $view_message = null;
}

// Check for success/error messages
if (isset($_GET['success'])) {
    $success = urldecode($_GET['success']);
}
if (isset($_GET['error'])) {
    $error = urldecode($_GET['error']);
}
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
            --card-bg: #F0F7F0;
            --card-border: #D4E8D4;
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
        .nav-item .nav-icon {
            width: 20px;
            text-align: center;
        }
        .nav-item .nav-badge {
            margin-left: auto;
            background: var(--red);
            color: white;
            font-size: .7rem;
            padding: 2px 8px;
            border-radius: 50px;
            min-width: 20px;
            text-align: center;
        }
        .nav-item .nav-badge.zero {
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
        .user-info {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: 1rem;
        }
        .user-avatar {
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
        .user-name {
            font-size: .85rem;
            font-weight: 600;
            color: var(--white);
            line-height: 1.2;
        }
        .user-role {
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
        .page-header h1 span {
            color: var(--gold);
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
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            background: rgba(255,255,255,0.5);
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
        .tabs {
            display: flex;
            border-bottom: 2px solid var(--card-border);
            background: rgba(255,255,255,0.3);
            padding: 0 1.5rem;
        }
        .tab-btn {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            font-size: .9rem;
            font-weight: 600;
            color: var(--gray);
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            font-family: 'DM Sans', sans-serif;
        }
        .tab-btn:hover {
            color: var(--white);
        }
        .tab-btn.active {
            color: var(--gold);
        }
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--gold);
        }
        .tab-btn .tab-badge {
            display: inline-block;
            background: var(--red);
            color: white;
            font-size: .6rem;
            padding: 1px 6px;
            border-radius: 50px;
            margin-left: .3rem;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .message-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: var(--transition);
            cursor: pointer;
        }
        .message-item:hover {
            background: rgba(255,255,255,0.5);
        }
        .message-item.unread {
            background: rgba(201,168,76,.08);
            border-left: 3px solid var(--gold);
        }
        .message-item .click-area {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex: 1;
            text-decoration: none;
            color: inherit;
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
        .empty-state {
            padding: 3rem;
            text-align: center;
            color: var(--gray);
        }
        .empty-state i {
            font-size: 3rem;
            color: #c5d5c5;
            margin-bottom: 1rem;
        }
        .btn {
            padding: .5rem 1.5rem;
            background: var(--gold);
            color: var(--navy-light);
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
            font-size: .85rem;
        }
        .btn:hover {
            background: var(--gold-lt);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(201,168,76,0.3);
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
        .btn-mark-all {
            background: var(--gray);
            color: white;
        }
        .btn-mark-all:hover {
            background: #6B7280;
        }
        .reply-icon {
            color: var(--gold);
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
            background: #f9fafb;
            cursor: not-allowed;
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
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(201,168,76,0.3);
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
        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); }
            .main { margin-left: 0; padding: 1.25rem; }
            .message-item { flex-wrap: wrap; }
            .message-actions { margin-top: .5rem; width: 100%; justify-content: flex-end; }
            .tabs { overflow-x: auto; }
            .tab-btn { padding: .75rem 1rem; font-size: .8rem; white-space: nowrap; }
            .modal { margin: 1rem; padding: 1.5rem; }
        }
    </style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">S</div>
        <div class="brand-text">SLSU GPTS <span>Student Portal</span></div>
    </div>
    <div class="nav-section-label">Main</div>
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item"><i class="fas fa-th-large nav-icon"></i> Dashboard</a>
        <a href="profile.php" class="nav-item"><i class="fas fa-user nav-icon"></i> My Profile</a>
        <a href="messages.php" class="nav-item active"><i class="fas fa-envelope nav-icon"></i> Messages <span class="nav-badge <?= $unread_count > 0 ? '' : 'zero' ?>"><?= $unread_count ?></span></a>
        <a href="notifications.php" class="nav-item"><i class="fas fa-bell nav-icon"></i> Notifications <span class="nav-badge <?= $unread_notif_count > 0 ? '' : 'zero' ?>"><?= $unread_notif_count ?></span></a>
        <a href="survey.php" class="nav-item"><i class="fas fa-poll nav-icon"></i> Tracer Survey</a>
        <a href="settings.php" class="nav-item"><i class="fas fa-cog nav-icon"></i> Settings</a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar"><i class="fas fa-user-graduate"></i></div>
            <div>
                <div class="user-name"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
                <div class="user-role">Graduate</div>
            </div>
        </div>
        <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>
<main class="main">
    <div class="page-header">
        <div class="breadcrumb">SLSU GPTS / <span>Messages</span></div>
        <h1>Messages</h1>
        <p><?= $unread_count ?> unread <?= $unread_count == 1 ? 'message' : 'messages' ?></p>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-inbox"></i> Messages from Admin</span>
            <?php if ($unread_count > 0 && $tab === 'messages'): ?>
                <a href="?mark_all_read=1" class="btn btn-mark-all" onclick="return confirm('Mark all messages as read?')"><i class="fas fa-check-double"></i> Mark all as read</a>
            <?php endif; ?>
        </div>
        
        <div class="tabs">
            <button class="tab-btn <?= $tab === 'messages' ? 'active' : '' ?>" onclick="switchTab('messages')">
                <i class="fas fa-envelope"></i> Messages 
                <?php if ($unread_count > 0): ?>
                    <span class="tab-badge"><?= $unread_count ?></span>
                <?php endif; ?>
            </button>
            <button class="tab-btn <?= $tab === 'inbox' ? 'active' : '' ?>" onclick="switchTab('inbox')">
                <i class="fas fa-reply"></i> Sent Replies
            </button>
        </div>

        <div class="tab-content <?= $tab === 'messages' ? 'active' : '' ?>" id="tab-messages">
            <?php if (empty($messages)): ?>
                <div class="empty-state">
                    <i class="fas fa-envelope-open"></i>
                    <p>No messages from admin yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $n): ?>
                    <div class="message-item <?= $n['is_read'] ? '' : 'unread' ?>" onclick="window.location.href='?view=<?= $n['id'] ?>&tab=messages'">
                        <div class="click-area">
                            <div class="message-icon">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <div class="message-content">
                                <h4>
                                    <?= htmlspecialchars($n['subject']) ?>
                                    <?php if (!$n['is_read']): ?>
                                        <span class="badge-unread">New</span>
                                    <?php endif; ?>
                                </h4>
                                <div class="message-preview"><?= htmlspecialchars($n['message']) ?></div>
                                <div class="sender-info">
                                    <i class="fas fa-user-shield"></i> From: Admin
                                </div>
                                <div class="status-time">
                                    <?php if ($n['is_read']): ?>
                                        <span class="read-status"><i class="fas fa-check-circle"></i> Read</span>
                                    <?php else: ?>
                                        <span class="unread-status"><i class="fas fa-circle"></i> Unread</span>
                                    <?php endif; ?>
                                    <span class="time"><i class="far fa-clock"></i> <?= time_ago($n['sent_at']) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="tab-content <?= $tab === 'inbox' ? 'active' : '' ?>" id="tab-inbox">
            <?php if (empty($replies)): ?>
                <div class="empty-state">
                    <i class="fas fa-reply-all"></i>
                    <p>No sent replies yet.</p>
                    <p style="font-size:.8rem;margin-top:.5rem;color:var(--gray);">When you reply to admin messages, they will appear here.</p>
                </div>
            <?php else: ?>
                <?php foreach ($replies as $n): ?>
                    <div class="message-item">
                        <div class="message-icon" style="background:rgba(201,168,76,.1);">
                            <i class="fas fa-reply reply-icon"></i>
                        </div>
                        <div class="message-content">
                            <h4>
                                <?= htmlspecialchars($n['subject']) ?>
                                <span style="font-size:.7rem;color:var(--gold);font-weight:400;margin-left:.5rem;">(Sent by you)</span>
                            </h4>
                            <div class="message-preview"><?= htmlspecialchars($n['message']) ?></div>
                            <div class="sender-info">
                                <i class="fas fa-user-graduate"></i> To: Admin
                            </div>
                            <div class="status-time">
                                <?php if ($n['is_read']): ?>
                                    <span class="read-status"><i class="fas fa-check-circle"></i> Admin has read your reply</span>
                                <?php else: ?>
                                    <span class="unread-status" style="color:var(--gold);"><i class="fas fa-clock"></i> Waiting for admin to read</span>
                                <?php endif; ?>
                                <span class="time"><i class="far fa-clock"></i> <?= time_ago($n['sent_at']) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php if ($show_modal && $view_message): ?>
<div class="modal-overlay show" id="viewModal">
    <div class="modal">
        <button class="modal-close" onclick="window.location.href='messages.php?tab=messages'">&times;</button>
        
        <h2><?= htmlspecialchars($view_message['subject']) ?></h2>
        <p class="modal-subtitle">Message from Admin</p>
        
        <div class="message-meta">
            <div class="meta-row">
                <span><span class="label">From:</span> Admin</span>
                <span><span class="label">Sent:</span> <?= date('F d, Y h:i A', strtotime($view_message['sent_at'])) ?></span>
                <span><span class="label">Status:</span> <span style="color:var(--green);"><i class="fas fa-check-circle"></i> Read</span></span>
            </div>
        </div>
        
        <div class="message-body">
            <?= nl2br(htmlspecialchars($view_message['message'])) ?>
        </div>
        
        <div class="reply-section">
            <div class="reply-title"><i class="fas fa-reply" style="color:var(--gold);"></i> Reply to Admin</div>
            <form method="POST" action="messages.php">
                <input type="hidden" name="message_id" value="<?= $view_message['id'] ?>">
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" value="RE: <?= htmlspecialchars($view_message['subject']) ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Your Reply</label>
                    <textarea name="message" placeholder="Type your reply here..." required></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" name="reply_message" class="btn-send"><i class="fas fa-paper-plane"></i> Send Reply</button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='messages.php?tab=messages'"><i class="fas fa-times"></i> Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Auto-show modal if it exists
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('viewModal');
    if (modal) {
        modal.classList.add('show');
    }
});

function switchTab(tab) {
    if (history.pushState) {
        var url = new URL(window.location);
        url.searchParams.set('tab', tab);
        url.searchParams.delete('view');
        history.pushState({}, '', url);
    }
    
    document.querySelectorAll('.tab-btn').forEach(function(btn) {
        btn.classList.remove('active');
    });
    
    document.querySelectorAll('.tab-content').forEach(function(content) {
        content.classList.remove('active');
    });
    
    var buttons = document.querySelectorAll('.tab-btn');
    if (tab === 'messages') {
        buttons[0].classList.add('active');
        document.getElementById('tab-messages').classList.add('active');
    } else if (tab === 'inbox') {
        buttons[1].classList.add('active');
        document.getElementById('tab-inbox').classList.add('active');
    }
    
    var markAllBtn = document.querySelector('.btn-mark-all');
    if (markAllBtn) {
        if (tab === 'messages') {
            markAllBtn.style.display = 'inline-block';
        } else {
            markAllBtn.style.display = 'none';
        }
    }
}

window.addEventListener('popstate', function() {
    var urlParams = new URLSearchParams(window.location.search);
    var tab = urlParams.get('tab') || 'messages';
    switchTab(tab);
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var modal = document.getElementById('viewModal');
        if (modal && modal.classList.contains('show')) {
            window.location.href = 'messages.php?tab=messages';
        }
    }
});

document.addEventListener('click', function(e) {
    var modal = document.getElementById('viewModal');
    if (modal && e.target === modal) {
        window.location.href = 'messages.php?tab=messages';
    }
});

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