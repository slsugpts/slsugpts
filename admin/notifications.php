<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$db = db();
$user_id = $_SESSION['user_id'];

// Get unread count for badge (admin notifications only - no replies)
$unread_count = $db->query("SELECT COUNT(*) FROM notifications WHERE recipient_type = 'admin' AND recipient_id = $user_id AND is_read = 0 AND type != 'reply'")->fetchColumn();

// Get unread reply count for Messages badge
$unread_reply_count = $db->query("SELECT COUNT(*) FROM notifications WHERE recipient_type = 'admin' AND recipient_id = $user_id AND is_read = 0 AND type = 'reply'")->fetchColumn();

// Mark all as read if requested
if (isset($_GET['mark_all'])) {
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE recipient_type = 'admin' AND recipient_id = ? AND type != 'reply'")->execute([$user_id]);
    header('Location: notifications.php');
    exit;
}

// Mark single as read
if (isset($_GET['read']) && is_numeric($_GET['read'])) {
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND recipient_type = 'admin' AND recipient_id = ?")->execute([$_GET['read'], $user_id]);
    header('Location: notifications.php');
    exit;
}

// Delete notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $db->prepare("DELETE FROM notifications WHERE id = ? AND recipient_type = 'admin' AND recipient_id = ?")->execute([$_GET['delete'], $user_id]);
    header('Location: notifications.php');
    exit;
}

// Get notifications (excluding replies)
$notifications = $db->query("
    SELECT n.*, 
           CASE 
               WHEN n.sender_type = 'graduate' THEN CONCAT(g.first_name, ' ', g.last_name)
               ELSE n.sender_type 
           END as sender_name
    FROM notifications n
    LEFT JOIN graduates g ON n.sender_id = g.id AND n.sender_type = 'graduate'
    WHERE n.recipient_type = 'admin' AND n.recipient_id = $user_id 
    AND n.type != 'reply'
    ORDER BY n.sent_at DESC 
    LIMIT 50
")->fetchAll();

$unread_count = $db->query("SELECT COUNT(*) FROM notifications WHERE recipient_type = 'admin' AND recipient_id = $user_id AND is_read = 0 AND type != 'reply'")->fetchColumn();

// Get notification types for filtering
$notification_types = [
    'profile_update' => 'Profile Update',
    'survey_submit' => 'Survey Submit',
    'survey_update' => 'Survey Update',
    'account' => 'Account',
    'system' => 'System'
];
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
            color: var(--navy);
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
        .notification-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            transition: var(--transition);
        }
        .notification-item:hover {
            background: #f9fafb;
        }
        .notification-item.unread {
            background: rgba(201,168,76,.05);
            border-left: 3px solid var(--gold);
        }
        .notification-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .notification-icon.profile {
            background: rgba(52,152,219,.15);
            color: #2980B9;
        }
        .notification-icon.survey {
            background: rgba(46,204,113,.15);
            color: #27AE60;
        }
        .notification-icon.account {
            background: rgba(201,168,76,.15);
            color: var(--gold);
        }
        .notification-icon.system {
            background: rgba(155,89,182,.15);
            color: #8E44AD;
        }
        .notification-content {
            flex: 1;
        }
        .notification-content h4 {
            font-size: .9rem;
            color: var(--white);
            margin-bottom: .2rem;
        }
        .notification-content h4 .badge-type {
            font-size: .65rem;
            padding: 2px 8px;
            border-radius: 50px;
            font-weight: 600;
            margin-left: 8px;
        }
        .badge-type.profile {
            background: rgba(52,152,219,.12);
            color: #2980B9;
        }
        .badge-type.survey {
            background: rgba(46,204,113,.12);
            color: #27AE60;
        }
        .badge-type.account {
            background: rgba(201,168,76,.12);
            color: var(--gold);
        }
        .badge-type.system {
            background: rgba(155,89,182,.12);
            color: #8E44AD;
        }
        .notification-content p {
            font-size: .85rem;
            color: var(--gray);
            margin-bottom: .2rem;
            white-space: pre-wrap;
        }
        .notification-content .sender-info {
            font-size: .75rem;
            color: var(--gold);
            font-weight: 600;
            margin-top: .3rem;
        }
        .notification-content .sender-info i {
            margin-right: 4px;
        }
        .notification-content .read-status {
            font-size: .7rem;
            color: var(--green);
            margin-top: .2rem;
        }
        .notification-content .unread-status {
            font-size: .7rem;
            color: var(--red);
            margin-top: .2rem;
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
        }
        .notification-actions a {
            color: var(--gray);
            font-size: .8rem;
            text-decoration: none;
        }
        .notification-actions a:hover {
            color: var(--gold);
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
        .filter-bar {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-bar select {
            padding: .5rem 1rem;
            border: 1px solid #e5e7eb;
            border-radius: var(--radius);
            font-size: .85rem;
            background: white;
            color: #1F2937;
        }
        .filter-bar select:focus {
            border-color: var(--gold);
            outline: none;
        }
        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); }
            .main { margin-left: 0; padding: 1.25rem; }
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
        <a href="notifications.php" class="nav-item active"><i class="fas fa-bell"></i> Notifications <span class="nav-badge <?= $unread_count > 0 ? '' : 'zero' ?>"><?= $unread_count ?></span></a>
    </nav>
    <div class="nav-section-label">Messages</div>
    <nav class="sidebar-nav">
        <a href="send_message.php" class="nav-item"><i class="fas fa-paper-plane"></i> Send Message</a>
        <a href="messages.php" class="nav-item"><i class="fas fa-envelope"></i> Inbox <span class="nav-badge <?= $unread_reply_count > 0 ? '' : 'zero' ?>"><?= $unread_reply_count ?></span></a>
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
        <div class="breadcrumb">SLSU GPTS / <span>Notifications</span></div>
        <h1>System Notifications</h1>
        <p><?= $unread_count ?> unread notifications</p>
    </div>
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-bell"></i> All Notifications</span>
            <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
                <?php if ($unread_count > 0): ?>
                    <a href="?mark_all=1" style="color:var(--gold);text-decoration:none;">Mark all as read</a>
                <?php endif; ?>
                <span style="color:var(--gray);font-size:.8rem;"><?= count($notifications) ?> total</span>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <p>No system notifications found.</p>
                    <p style="font-size:.85rem;margin-top:.5rem;">Notifications will appear here when students update their profile or submit tracer surveys.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $n): ?>
                    <div class="notification-item <?= $n['is_read'] ? '' : 'unread' ?>">
                        <div class="notification-icon <?= $n['type'] ?>">
                            <i class="fas fa-<?= $n['type'] === 'profile_update' ? 'user-edit' : ($n['type'] === 'survey_submit' ? 'file-upload' : ($n['type'] === 'survey_update' ? 'edit' : 'info-circle')) ?>"></i>
                        </div>
                        <div class="notification-content">
                            <h4>
                                <?= htmlspecialchars($n['subject']) ?>
                                <span class="badge-type <?= $n['type'] ?>">
                                    <?= ucfirst(str_replace('_', ' ', $n['type'])) ?>
                                </span>
                            </h4>
                            <p><?= htmlspecialchars($n['message']) ?></p>
                            <?php if (!empty($n['sender_name']) && $n['sender_type'] === 'graduate'): ?>
                                <div class="sender-info"><i class="fas fa-user-graduate"></i> From: <?= htmlspecialchars($n['sender_name']) ?></div>
                            <?php endif; ?>
                            <?php if ($n['is_read']): ?>
                                <div class="read-status"><i class="fas fa-check-circle"></i> Read</div>
                            <?php else: ?>
                                <div class="unread-status"><i class="fas fa-circle"></i> Unread</div>
                            <?php endif; ?>
                            <small><?= time_ago($n['sent_at']) ?></small>
                        </div>
                        <div class="notification-actions">
                            <?php if (!$n['is_read']): ?>
                                <a href="?read=<?= $n['id'] ?>" title="Mark as read"><i class="fas fa-check"></i></a>
                            <?php endif; ?>
                            <a href="?delete=<?= $n['id'] ?>" title="Delete" onclick="return confirm('Delete this notification?')"><i class="fas fa-trash"></i></a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>