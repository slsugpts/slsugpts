<?php
require_once __DIR__ . '/../includes/auth.php';
require_graduate();

$db = db();
$user_id = $_SESSION['user_id'];

// Get unread message count for badge (messages from admin)
$unread_count = $db->query("
    SELECT COUNT(*) FROM notifications 
    WHERE recipient_type = 'graduate' AND recipient_id = $user_id 
    AND is_read = 0 AND sender_type = 'admin'
")->fetchColumn();

// Get unread notification count for badge (system notifications only - NO messages)
$unread_notif_count = $db->query("
    SELECT COUNT(*) FROM notifications 
    WHERE recipient_type = 'graduate' AND recipient_id = $user_id 
    AND is_read = 0 
    AND sender_type != 'admin'
    AND type NOT IN ('message', 'reply')
")->fetchColumn();

// Handle viewing a single notification - mark as read immediately
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $view_notification_id = (int)$_GET['view'];
    
    // First, mark it as read
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND recipient_type = 'graduate' AND recipient_id = ?")->execute([$view_notification_id, $user_id]);
    
    // Then fetch the notification data
    $stmt = $db->prepare("
        SELECT n.*,
               CASE 
                   WHEN n.sender_type = 'system' THEN 'System'
                   WHEN n.sender_type = 'admin' THEN 'Admin'
                   WHEN n.sender_type = 'graduate' THEN 'You'
                   ELSE 'System'
               END as sender_name,
               CASE 
                   WHEN n.type = 'survey' THEN 'fas fa-poll'
                   WHEN n.type = 'account' THEN 'fas fa-user-cog'
                   WHEN n.type = 'announcement' THEN 'fas fa-bullhorn'
                   WHEN n.type = 'event' THEN 'fas fa-calendar-alt'
                   WHEN n.type = 'reminder' THEN 'fas fa-clock'
                   WHEN n.type = 'update' THEN 'fas fa-sync-alt'
                   ELSE 'fas fa-info-circle'
               END as icon_class,
               CASE 
                   WHEN n.type = 'survey' THEN '--blue'
                   WHEN n.type = 'account' THEN '--green'
                   WHEN n.type = 'announcement' THEN '--red'
                   WHEN n.type = 'event' THEN '--purple'
                   WHEN n.type = 'reminder' THEN '--orange'
                   WHEN n.type = 'update' THEN '--gold'
                   ELSE '--gray'
               END as icon_color
        FROM notifications n
        WHERE n.id = ? AND n.recipient_type = 'graduate' AND n.recipient_id = ?
    ");
    $stmt->execute([$view_notification_id, $user_id]);
    $view_notification = $stmt->fetch();
    
    // Redirect to remove the view parameter from URL but keep it in session for modal
    if ($view_notification) {
        // Store the notification data in session to show modal after redirect
        $_SESSION['view_notification'] = $view_notification;
        header('Location: notifications.php?show_modal=1');
        exit;
    }
}

// Check if we need to show the modal from session
$show_modal = isset($_GET['show_modal']) && isset($_SESSION['view_notification']);
if ($show_modal) {
    $view_notification = $_SESSION['view_notification'];
    // Clear session data after retrieving
    unset($_SESSION['view_notification']);
} else {
    $view_notification = null;
}

// Get all notifications (system only - NO messages or replies)
$notifications = $db->query("
    SELECT n.*,
           CASE 
               WHEN n.sender_type = 'system' THEN 'System'
               WHEN n.sender_type = 'admin' THEN 'Admin'
               WHEN n.sender_type = 'graduate' THEN 'You'
               ELSE 'System'
           END as sender_name,
           CASE 
               WHEN n.type = 'survey' THEN 'fas fa-poll'
               WHEN n.type = 'account' THEN 'fas fa-user-cog'
               WHEN n.type = 'announcement' THEN 'fas fa-bullhorn'
               WHEN n.type = 'event' THEN 'fas fa-calendar-alt'
               WHEN n.type = 'reminder' THEN 'fas fa-clock'
               WHEN n.type = 'update' THEN 'fas fa-sync-alt'
               ELSE 'fas fa-info-circle'
           END as icon_class,
           CASE 
               WHEN n.type = 'survey' THEN '--blue'
               WHEN n.type = 'account' THEN '--green'
               WHEN n.type = 'announcement' THEN '--red'
               WHEN n.type = 'event' THEN '--purple'
               WHEN n.type = 'reminder' THEN '--orange'
               WHEN n.type = 'update' THEN '--gold'
               ELSE '--gray'
           END as icon_color
    FROM notifications n
    WHERE n.recipient_type = 'graduate' AND n.recipient_id = $user_id 
    AND n.sender_type != 'admin'
    AND n.type NOT IN ('message', 'reply')
    ORDER BY n.sent_at DESC 
    LIMIT 50
")->fetchAll();

// Count unread notifications for badge (system only)
$total_unread = $db->query("
    SELECT COUNT(*) FROM notifications 
    WHERE recipient_type = 'graduate' AND recipient_id = $user_id 
    AND is_read = 0 
    AND sender_type != 'admin'
    AND type NOT IN ('message', 'reply')
")->fetchColumn();

// Mark all as read
if (isset($_GET['mark_all'])) {
    $db->prepare("
        UPDATE notifications SET is_read = 1 
        WHERE recipient_type = 'graduate' AND recipient_id = ? 
        AND is_read = 0 
        AND sender_type != 'admin'
        AND type NOT IN ('message', 'reply')
    ")->execute([$user_id]);
    header('Location: notifications.php');
    exit;
}

// Mark single as read
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND recipient_type = 'graduate' AND recipient_id = ?")->execute([$_GET['read'], $user_id]);
    header('Location: notifications.php');
    exit;
}

// Delete notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $db->prepare("DELETE FROM notifications WHERE id = ? AND recipient_type = 'graduate' AND recipient_id = ?")->execute([$_GET['delete'], $user_id]);
    header('Location: notifications.php');
    exit;
}

// Get fresh counts after updates
$unread_count = $db->query("
    SELECT COUNT(*) FROM notifications 
    WHERE recipient_type = 'graduate' AND recipient_id = $user_id 
    AND is_read = 0 AND sender_type = 'admin'
")->fetchColumn();

$unread_notif_count = $db->query("
    SELECT COUNT(*) FROM notifications 
    WHERE recipient_type = 'graduate' AND recipient_id = $user_id 
    AND is_read = 0 
    AND sender_type != 'admin'
    AND type NOT IN ('message', 'reply')
")->fetchColumn();

$total_unread = $unread_notif_count;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications – SLSU GPTS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --gold: #C9A84C;
            --gold-lt: #E8C97A;
            --navy: #E8F5E9;
            --navy-mid: #C8E6C9;
            --navy-lt: #A5D6A7;
            --white: #1B5E20;
            --gray: #2E7D32;
            --red: #E05656;
            --green: #4CAF50;
            --blue: #0EA5E9;
            --purple: #8B5CF6;
            --orange: #F59E0B;
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
            background: var(--navy);
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
            color: var(--navy);
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
            color: var(--navy);
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
        .notification-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--card-border);
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            transition: var(--transition);
            position: relative;
            cursor: pointer;
        }
        .notification-item:hover {
            background: rgba(255,255,255,0.5);
        }
        .notification-item.unread {
            background: rgba(201,168,76,.08);
            border-left: 3px solid var(--gold);
        }
        .notification-item.unread::before {
            content: '';
            position: absolute;
            top: 50%;
            right: 1rem;
            transform: translateY(-50%);
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--red);
            animation: pulse 2s infinite;
        }
        .notification-item .click-area {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            flex: 1;
            text-decoration: none;
            color: inherit;
        }
        .notification-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
            font-size: .85rem;
        }
        .notification-icon.icon-gold { background: var(--gold); }
        .notification-icon.icon-blue { background: var(--blue); }
        .notification-icon.icon-green { background: var(--green); }
        .notification-icon.icon-red { background: var(--red); }
        .notification-icon.icon-purple { background: var(--purple); }
        .notification-icon.icon-orange { background: var(--orange); }
        .notification-icon.icon-gray { background: var(--gray); }
        .notification-content {
            flex: 1;
            padding-right: 2rem;
        }
        .notification-content .notification-type {
            font-size: .6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--gold);
            margin-bottom: .2rem;
        }
        .notification-content h4 {
            font-size: .9rem;
            color: var(--white);
            margin-bottom: .2rem;
        }
        .notification-content .message-preview {
            font-size: .85rem;
            color: var(--gray);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.5;
        }
        .notification-content .sender-info {
            font-size: .75rem;
            color: var(--gold);
            font-weight: 600;
            display: block;
            margin-top: .3rem;
        }
        .notification-content .read-status {
            font-size: .7rem;
            display: inline-block;
            margin-top: .2rem;
            padding: 2px 10px;
            border-radius: 50px;
        }
        .notification-content .read-status.read {
            color: var(--green);
            background: rgba(76,175,80,.1);
        }
        .notification-content .read-status.unread {
            color: var(--red);
            background: rgba(224,86,86,.1);
        }
        .notification-content small {
            font-size: .75rem;
            color: var(--gray);
            display: block;
            margin-top: .2rem;
        }
        .notification-actions {
            display: flex;
            gap: .5rem;
            flex-shrink: 0;
            align-items: center;
        }
        .notification-actions a {
            color: var(--gray);
            font-size: .8rem;
            text-decoration: none;
            padding: 4px 6px;
            border-radius: 4px;
            transition: var(--transition);
        }
        .notification-actions a:hover {
            color: var(--gold);
            background: rgba(201,168,76,.1);
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
            color: var(--navy);
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
            max-width: 600px;
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
        .modal .modal-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }
        .modal .modal-icon.icon-gold { background: var(--gold); }
        .modal .modal-icon.icon-blue { background: var(--blue); }
        .modal .modal-icon.icon-green { background: var(--green); }
        .modal .modal-icon.icon-red { background: var(--red); }
        .modal .modal-icon.icon-purple { background: var(--purple); }
        .modal .modal-icon.icon-orange { background: var(--orange); }
        .modal .modal-icon.icon-gray { background: var(--gray); }
        .modal h2 {
            font-family: 'Playfair Display', serif;
            color: var(--white);
            margin-bottom: .25rem;
            font-size: 1.3rem;
        }
        .modal .modal-subtitle {
            color: var(--gray);
            font-size: .85rem;
            margin-bottom: 1.5rem;
        }
        .modal .modal-meta {
            background: #f9fafb;
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
        }
        .modal .modal-meta .meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem 1.5rem;
            font-size: .85rem;
            color: var(--gray);
        }
        .modal .modal-meta .meta-row span {
            display: flex;
            align-items: center;
            gap: .4rem;
        }
        .modal .modal-meta .meta-row .label {
            font-weight: 600;
            color: var(--white);
        }
        .modal .modal-body {
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
        .modal .modal-actions {
            display: flex;
            gap: .75rem;
            flex-wrap: wrap;
        }
        .modal .modal-actions .btn-close-modal {
            padding: .6rem 2rem;
            background: var(--gold);
            color: var(--navy);
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-size: .9rem;
        }
        .modal .modal-actions .btn-close-modal:hover {
            background: var(--gold-lt);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(201,168,76,0.3);
        }
        .modal .modal-actions .btn-delete-modal {
            padding: .6rem 2rem;
            background: var(--red);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-size: .9rem;
        }
        .modal .modal-actions .btn-delete-modal:hover {
            background: #c0392b;
        }
        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); }
            .main { margin-left: 0; padding: 1.25rem; }
            .notification-item.unread::before { display: none; }
            .notification-content { padding-right: 0; }
            .modal { margin: 1rem; padding: 1.5rem; }
            .notification-item { flex-wrap: wrap; }
            .notification-actions { margin-top: .5rem; width: 100%; justify-content: flex-end; }
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
        <a href="messages.php" class="nav-item"><i class="fas fa-envelope nav-icon"></i> Messages <span class="nav-badge <?= $unread_count > 0 ? '' : 'zero' ?>"><?= $unread_count ?></span></a>
        <a href="notifications.php" class="nav-item active"><i class="fas fa-bell nav-icon"></i> Notifications <span class="nav-badge <?= $unread_notif_count > 0 ? '' : 'zero' ?>"><?= $unread_notif_count ?></span></a>
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
        <div class="breadcrumb">SLSU GPTS / <span>Notifications</span></div>
        <h1>Notifications</h1>
        <p><?= $total_unread ?> unread notifications</p>
    </div>
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-bell"></i> All Notifications</span>
            <div>
                <?php if ($total_unread > 0): ?>
                    <a href="?mark_all=1" style="color:var(--gold);text-decoration:none;margin-right:1rem;font-weight:600;">Mark all as read</a>
                <?php endif; ?>
                <span style="color:var(--gray);font-size:.8rem;"><?= count($notifications) ?> total</span>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <p>No notifications found.</p>
                    <p style="font-size:.8rem;margin-top:.5rem;color:var(--gray);">You'll receive notifications for survey reminders, account updates, and system announcements.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $n): ?>
                    <div class="notification-item <?= $n['is_read'] ? '' : 'unread' ?>" onclick="window.location.href='?view=<?= $n['id'] ?>'">
                        <div class="click-area">
                            <div class="notification-icon icon-<?= str_replace('--', '', $n['icon_color']) ?>">
                                <i class="<?= $n['icon_class'] ?>"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-type"><?= ucfirst($n['type'] ?? 'General') ?></div>
                                <h4><?= htmlspecialchars($n['subject']) ?></h4>
                                <div class="message-preview"><?= htmlspecialchars($n['message']) ?></div>
                                <span class="sender-info">
                                    <i class="fas fa-<?= $n['sender_type'] === 'system' ? 'server' : 'user' ?>"></i>
                                    From: <?= htmlspecialchars($n['sender_name'] ?? 'System') ?>
                                </span>
                                
                                <?php if ($n['is_read']): ?>
                                    <span class="read-status read"><i class="fas fa-check-circle"></i> Read</span>
                                <?php else: ?>
                                    <span class="read-status unread"><i class="fas fa-circle"></i> Unread</span>
                                <?php endif; ?>
                                
                                <small><?= time_ago($n['sent_at']) ?></small>
                            </div>
                        </div>
                        <div class="notification-actions">
                            <?php if (!$n['is_read']): ?>
                                <a href="?read=<?= $n['id'] ?>" title="Mark as read"><i class="fas fa-check"></i></a>
                            <?php endif; ?>
                            <a href="?delete=<?= $n['id'] ?>" title="Delete" onclick="event.stopPropagation(); return confirm('Delete this notification?')"><i class="fas fa-trash"></i></a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php if ($show_modal && $view_notification): ?>
<div class="modal-overlay show" id="viewModal">
    <div class="modal">
        <button class="modal-close" onclick="window.location.href='notifications.php'">&times;</button>
        
        <div class="modal-icon icon-<?= str_replace('--', '', $view_notification['icon_color']) ?>">
            <i class="<?= $view_notification['icon_class'] ?>"></i>
        </div>
        
        <h2><?= htmlspecialchars($view_notification['subject']) ?></h2>
        <p class="modal-subtitle"><?= ucfirst($view_notification['type'] ?? 'General') ?> Notification</p>
        
        <div class="modal-meta">
            <div class="meta-row">
                <span><span class="label">From:</span> <?= htmlspecialchars($view_notification['sender_name'] ?? 'System') ?></span>
                <span><span class="label">Sent:</span> <?= date('F d, Y h:i A', strtotime($view_notification['sent_at'])) ?></span>
                <span><span class="label">Status:</span> <span style="color:var(--green);"><i class="fas fa-check-circle"></i> Read</span></span>
            </div>
        </div>
        
        <div class="modal-body">
            <?= nl2br(htmlspecialchars($view_notification['message'])) ?>
        </div>
        
        <div class="modal-actions">
            <button class="btn-close-modal" onclick="window.location.href='notifications.php'"><i class="fas fa-times"></i> Close</button>
            <a href="?delete=<?= $view_notification['id'] ?>" class="btn-delete-modal" onclick="return confirm('Delete this notification?')"><i class="fas fa-trash"></i> Delete</a>
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

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var modal = document.getElementById('viewModal');
        if (modal && modal.classList.contains('show')) {
            window.location.href = 'notifications.php';
        }
    }
});

document.addEventListener('click', function(e) {
    var modal = document.getElementById('viewModal');
    if (modal && e.target === modal) {
        window.location.href = 'notifications.php';
    }
});
</script>
</body>
</html>