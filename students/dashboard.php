<?php
require_once __DIR__ . '/../includes/auth.php';
require_graduate();

$db = db();
$user_id = $_SESSION['user_id'];

// Get graduate data
$graduate = $db->query("
    SELECT g.*, p.name as program_name, p.code as program_code
    FROM graduates g
    LEFT JOIN programs p ON g.program_id = p.id
    WHERE g.id = $user_id
")->fetch();

// Get tracer records
$tracer = $db->query("SELECT * FROM tracer_records WHERE graduate_id = $user_id ORDER BY submitted_at DESC LIMIT 1")->fetch();

// Get unread message count (only from admin)
$unread_count = $db->query("
    SELECT COUNT(*) FROM notifications 
    WHERE recipient_type = 'graduate' AND recipient_id = $user_id 
    AND is_read = 0 AND sender_type = 'admin'
")->fetchColumn();

// Get unread notification count (system notifications only)
$unread_notif_count = $db->query("
    SELECT COUNT(*) FROM notifications 
    WHERE recipient_type = 'graduate' AND recipient_id = $user_id 
    AND is_read = 0 AND (sender_type IS NULL OR sender_type = 'system')
")->fetchColumn();

// Get recent notifications (system notifications only - NOT messages from admin)
$notifications = $db->query("
    SELECT n.*, 
           CASE 
               WHEN n.sender_type = 'system' THEN 'System'
               WHEN n.sender_type = 'admin' THEN 'Admin'
               ELSE 'System'
           END as sender_name
    FROM notifications n
    WHERE n.recipient_type = 'graduate' AND n.recipient_id = $user_id 
    AND n.sender_type != 'admin'
    AND n.type NOT IN ('message', 'reply')
    ORDER BY n.sent_at DESC 
    LIMIT 5
")->fetchAll();

$has_survey = $tracer ? true : false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – SLSU GPTS</title>
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
            --green: #4CAF50;
            --red: #E05656;
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
            position: relative;
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
        .nav-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: #e74c3c;
            color: white;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 0.1rem 0.55rem;
            border-radius: 50px;
            min-width: 20px;
            height: 20px;
            margin-left: auto;
            line-height: 1;
        }
        .nav-badge.zero {
            background-color: transparent;
            color: var(--gray);
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
            font-weight: 700;
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
        .user-email {
            font-size: .7rem;
            color: var(--gray);
            margin-top: .1rem;
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
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            padding: 1.5rem;
            transition: var(--transition);
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }
        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: .5rem;
        }
        .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        .stat-icon.gold { background: rgba(201,168,76,.2); color: var(--gold); }
        .stat-icon.green { background: rgba(76,175,80,.2); color: var(--green); }
        .stat-icon.blue { background: rgba(56,189,248,.2); color: #0EA5E9; }
        .stat-label {
            font-size: .75rem;
            color: var(--gray);
            font-weight: 500;
        }
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--white);
            line-height: 1;
        }
        .stat-sub {
            font-size: .75rem;
            color: var(--gray);
            margin-top: .3rem;
        }
        .card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            overflow: hidden;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
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
            padding: 1.5rem;
        }
        .info-item {
            padding: .5rem 0;
            border-bottom: 1px solid #e5e7eb;
            color: var(--white);
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-item strong {
            display: inline-block;
            width: 140px;
            color: var(--gray);
            font-weight: 500;
        }
        .info-item .highlight {
            color: var(--gold);
            font-weight: 600;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            padding: .2rem .65rem;
            border-radius: 50px;
            font-size: .72rem;
            font-weight: 600;
        }
        .badge-success { background: rgba(76,175,80,.15); color: #4CAF50; }
        .badge-warning { background: rgba(251,191,36,.15); color: #D4A00A; }
        .badge-info { background: rgba(56,189,248,.15); color: #0EA5E9; }
        .badge-secondary { background: rgba(107,114,128,.15); color: #6B7280; }
        .btn {
            padding: .6rem 1.5rem;
            background: var(--gold);
            color: var(--navy);
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: var(--transition);
            font-size: .85rem;
        }
        .btn:hover {
            background: var(--gold-lt);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(201,168,76,0.3);
        }
        .btn-outline {
            background: transparent;
            border: 2px solid var(--gold);
            color: var(--gold);
        }
        .btn-outline:hover {
            background: var(--gold);
            color: var(--navy);
        }
        .notification-item {
            padding: .75rem 0;
            border-bottom: 1px solid #e5e7eb;
            color: var(--white);
        }
        .notification-item:last-child {
            border-bottom: none;
        }
        .notification-item.unread {
            border-left: 3px solid var(--gold);
            padding-left: 1rem;
        }
        .notification-item h4 {
            font-size: .9rem;
            color: var(--white);
        }
        .notification-item p {
            font-size: .85rem;
            color: var(--gray);
            margin: .2rem 0;
        }
        .notification-item .sender {
            font-size: .75rem;
            color: var(--gold);
            font-weight: 600;
            display: block;
            margin-top: .3rem;
        }
        .notification-item small {
            font-size: .75rem;
            color: var(--gray);
            display: block;
            margin-top: .2rem;
        }
        .notification-item .type-badge {
            font-size: .6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--gold);
            display: inline-block;
            margin-bottom: .2rem;
        }
        .text-center { text-align: center; }
        .text-muted { color: var(--gray); }
        .mt-2 { margin-top: 1rem; }
        .welcome-banner {
            background: linear-gradient(135deg, var(--navy), var(--navy-mid));
            border-radius: var(--radius);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid var(--card-border);
        }
        .welcome-banner h1 {
            color: var(--white);
            font-size: 1.8rem;
        }
        .welcome-banner p {
            color: var(--gray);
        }
        .survey-prompt {
            border: 2px solid var(--gold) !important;
            background: rgba(201,168,76,0.05) !important;
        }
        .survey-prompt .card-header {
            background: rgba(201,168,76,0.08) !important;
        }
        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); }
            .main { margin-left: 0; padding: 1.25rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 600px) {
            .stats-grid { grid-template-columns: 1fr; }
            .info-item strong { width: 100px; }
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
        <a href="dashboard.php" class="nav-item active"><i class="fas fa-th-large nav-icon"></i> Dashboard</a>
        <a href="profile.php" class="nav-item"><i class="fas fa-user nav-icon"></i> My Profile</a>
        <a href="messages.php" class="nav-item"><i class="fas fa-envelope nav-icon"></i> Messages <?php if ($unread_count > 0): ?><span class="nav-badge"><?= $unread_count ?></span><?php endif; ?></a>
        <a href="notifications.php" class="nav-item"><i class="fas fa-bell nav-icon"></i> Notifications <?php if ($unread_notif_count > 0): ?><span class="nav-badge"><?= $unread_notif_count ?></span><?php endif; ?></a>
        <a href="survey.php" class="nav-item"><i class="fas fa-poll nav-icon"></i> Tracer Survey</a>
        <a href="settings.php" class="nav-item"><i class="fas fa-cog nav-icon"></i> Settings</a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'G', 0, 1)) ?></div>
            <div>
                <div class="user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Graduate') ?></div>
                <div class="user-role">Graduate</div>
                <div class="user-email"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></div>
            </div>
        </div>
        <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</aside>
<main class="main">
    <div class="welcome-banner">
        <div class="breadcrumb">SLSU GPTS / <span>Dashboard</span></div>
        <h1>Welcome, <span style="color:var(--gold);"><?= htmlspecialchars($_SESSION['user_name']) ?></span>!</h1>
        <p>Manage your profile and complete your tracer survey.</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-label">Account Status</div>
                <div class="stat-icon <?= $graduate['is_active'] ? 'green' : 'gold' ?>"><i class="fas fa-user-check"></i></div>
            </div>
            <div class="stat-value"><?= $graduate['is_active'] ? 'Active' : 'Pending' ?></div>
            <div class="stat-sub">Your account is <?= $graduate['is_active'] ? 'active' : 'pending approval' ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-label">Program</div>
                <div class="stat-icon gold"><i class="fas fa-graduation-cap"></i></div>
            </div>
            <div class="stat-value" style="font-size:1.2rem;"><?= htmlspecialchars($graduate['program_code'] ?? 'N/A') ?></div>
            <div class="stat-sub"><?= htmlspecialchars($graduate['program_name'] ?? 'No program assigned') ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-label">Tracer Survey</div>
                <div class="stat-icon <?= $has_survey ? 'green' : 'blue' ?>"><i class="fas fa-poll-h"></i></div>
            </div>
            <div class="stat-value"><?= $has_survey ? 'Completed' : 'Pending' ?></div>
            <div class="stat-sub"><?= $has_survey ? 'Survey submitted' : 'Please complete your survey' ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-label">Messages</div>
                <div class="stat-icon gold"><i class="fas fa-envelope"></i></div>
            </div>
            <div class="stat-value"><?= $unread_count ?></div>
            <div class="stat-sub"><?= $unread_count > 0 ? 'You have unread messages' : 'No unread messages' ?></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-user"></i> Your Profile</span>
            <a href="profile.php" class="btn btn-outline" style="padding:.4rem 1rem;font-size:.8rem;"><i class="fas fa-edit"></i> Edit Profile</a>
        </div>
        <div class="card-body">
            <div class="info-item"><strong>Student ID:</strong> <span class="highlight"><?= htmlspecialchars($graduate['student_id']) ?></span></div>
            <div class="info-item"><strong>Full Name:</strong> <?= htmlspecialchars($graduate['first_name'] . ' ' . $graduate['last_name']) ?></div>
            <div class="info-item"><strong>Email:</strong> <?= htmlspecialchars($graduate['email']) ?></div>
            <div class="info-item"><strong>Program:</strong> <?= htmlspecialchars($graduate['program_name'] ?? 'N/A') ?> <span class="badge badge-secondary"><?= htmlspecialchars($graduate['program_code'] ?? 'N/A') ?></span></div>
            <div class="info-item"><strong>Batch Year:</strong> <?= htmlspecialchars($graduate['batch_year'] ?? 'N/A') ?></div>
        </div>
    </div>

    <?php if (!$has_survey): ?>
        <div class="card survey-prompt">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-poll" style="color:var(--gold);"></i> Complete Your Tracer Survey</span>
                <a href="survey.php" class="btn"><i class="fas fa-arrow-right"></i> Take Survey</a>
            </div>
            <div class="card-body">
                <p style="color:var(--white);">Please help us track graduate outcomes by completing the tracer survey. It only takes a few minutes!</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-bell"></i> Recent Notifications</span>
            <a href="notifications.php" style="color:var(--gold);text-decoration:none;font-size:.85rem;font-weight:600;">View All <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="card-body">
            <?php if (empty($notifications)): ?>
                <p class="text-muted text-center" style="padding:.5rem;">No notifications yet.</p>
            <?php else: ?>
                <?php foreach ($notifications as $n): ?>
                    <div class="notification-item <?= $n['is_read'] ? '' : 'unread' ?>">
                        <div class="type-badge"><?= ucfirst($n['type'] ?? 'General') ?></div>
                        <h4><?= htmlspecialchars($n['subject']) ?></h4>
                        <p><?= htmlspecialchars($n['message']) ?></p>
                        <span class="sender">From: <?= htmlspecialchars($n['sender_name'] ?? 'System') ?></span>
                        <small><?= time_ago($n['sent_at']) ?></small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>
