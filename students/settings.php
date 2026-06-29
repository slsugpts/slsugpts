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

// Get unread message count for badge
$unread_count = $db->query("
    SELECT COUNT(*) FROM notifications 
    WHERE recipient_type = 'graduate' AND recipient_id = $user_id 
    AND is_read = 0 AND sender_type = 'admin'
")->fetchColumn();

// Get unread notification count for badge (system notifications only)
$unread_notif_count = $db->query("
    SELECT COUNT(*) FROM notifications 
    WHERE recipient_type = 'graduate' AND recipient_id = $user_id 
    AND is_read = 0 AND (sender_type IS NULL OR sender_type = 'system')
")->fetchColumn();

$error = '';
$success = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'account';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (!$current_password || !$new_password || !$confirm_password) {
        $error = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Verify current password
        $stmt = $db->prepare("SELECT password FROM graduates WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($current_password, $user['password'])) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $db->prepare("UPDATE graduates SET password = ? WHERE id = ?")->execute([$hashed, $user_id]);
            $success = 'Password changed successfully!';
            
            send_notification(
                $user_id,
                'Password Changed',
                'Your password has been updated successfully.',
                'account',
                'system'
            );
        } else {
            $error = 'Current password is incorrect.';
        }
    }
}

// Handle notification preferences
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_preferences'])) {
    $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
    $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;
    
    // Check if preferences exist
    $check = $db->prepare("SELECT id FROM user_preferences WHERE user_id = ? AND user_type = 'graduate'");
    $check->execute([$user_id]);
    
    if ($check->fetch()) {
        $stmt = $db->prepare("
            UPDATE user_preferences SET 
                email_notifications = ?,
                push_notifications = ?,
                updated_at = NOW()
            WHERE user_id = ? AND user_type = 'graduate'
        ");
        $stmt->execute([$email_notifications, $push_notifications, $user_id]);
    } else {
        $stmt = $db->prepare("
            INSERT INTO user_preferences (user_id, user_type, email_notifications, push_notifications)
            VALUES (?, 'graduate', ?, ?)
        ");
        $stmt->execute([$user_id, $email_notifications, $push_notifications]);
    }
    
    $success = 'Preferences updated successfully!';
}

// Get current preferences
$prefs = $db->query("
    SELECT * FROM user_preferences 
    WHERE user_id = $user_id AND user_type = 'graduate'
")->fetch();

if (!$prefs) {
    $prefs = ['email_notifications' => 1, 'push_notifications' => 1];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings – SLSU GPTS</title>
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
        .settings-tabs {
            display: flex;
            gap: .5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            background: var(--card-bg);
            padding: .5rem;
            border-radius: var(--radius);
            border: 1px solid var(--card-border);
        }
        .settings-tab {
            padding: .6rem 1.5rem;
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--gray);
            font-weight: 600;
            font-size: .85rem;
            transition: var(--transition);
        }
        .settings-tab:hover {
            background: rgba(201,168,76,.1);
            color: var(--white);
        }
        .settings-tab.active {
            background: var(--gold);
            color: var(--navy);
        }
        .settings-tab i {
            margin-right: .5rem;
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
        .form-group {
            display: flex;
            flex-direction: column;
            gap: .3rem;
            margin-bottom: 1.25rem;
        }
        .form-group label {
            font-size: .8rem;
            font-weight: 600;
            color: var(--gray);
        }
        .form-group label .required {
            color: var(--red);
        }
        .form-group input, .form-group select {
            padding: .7rem 1rem;
            border: 1px solid #e5e7eb;
            border-radius: var(--radius);
            font-size: .9rem;
            background: white;
            color: #1F2937;
            transition: var(--transition);
        }
        .form-group input:focus, .form-group select:focus {
            border-color: var(--gold);
            outline: none;
            box-shadow: 0 0 0 3px rgba(201,168,76,0.15);
        }
        .form-group .help-text {
            font-size: .75rem;
            color: var(--gray);
        }
        .btn {
            padding: .7rem 2rem;
            background: var(--gold);
            color: var(--navy);
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-size: .9rem;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
        }
        .btn:hover {
            background: var(--gold-lt);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(201,168,76,0.3);
        }
        .btn-success {
            background: var(--green);
            color: white;
        }
        .btn-success:hover {
            background: #45a049;
            box-shadow: 0 4px 12px rgba(76,175,80,0.3);
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
        .toggle-group {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .75rem 0;
            border-bottom: 1px solid var(--card-border);
        }
        .toggle-group:last-child {
            border-bottom: none;
        }
        .toggle-group .toggle-info {
            flex: 1;
        }
        .toggle-group .toggle-info h4 {
            font-size: .9rem;
            color: var(--white);
        }
        .toggle-group .toggle-info p {
            font-size: .8rem;
            color: var(--gray);
        }
        .toggle-switch {
            position: relative;
            width: 48px;
            height: 26px;
            flex-shrink: 0;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #ccc;
            transition: var(--transition);
            border-radius: 26px;
        }
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: var(--transition);
            border-radius: 50%;
        }
        .toggle-switch input:checked + .toggle-slider {
            background: var(--green);
        }
        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(22px);
        }
        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); }
            .main { margin-left: 0; padding: 1.25rem; }
            .settings-tabs { flex-direction: column; }
            .settings-tab { text-align: center; }
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
        <a href="notifications.php" class="nav-item"><i class="fas fa-bell nav-icon"></i> Notifications <span class="nav-badge <?= $unread_notif_count > 0 ? '' : 'zero' ?>"><?= $unread_notif_count ?></span></a>
        <a href="survey.php" class="nav-item"><i class="fas fa-poll nav-icon"></i> Tracer Survey</a>
        <a href="settings.php" class="nav-item active"><i class="fas fa-cog nav-icon"></i> Settings</a>
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
    <div class="page-header">
        <div class="breadcrumb">SLSU GPTS / <span>Settings</span></div>
        <h1>Settings</h1>
        <p>Manage your account settings and preferences.</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
    <?php endif; ?>

    <div class="settings-tabs">
        <a href="?tab=account" class="settings-tab <?= $active_tab === 'account' ? 'active' : '' ?>">
            <i class="fas fa-user-cog"></i> Account
        </a>
        <a href="?tab=preferences" class="settings-tab <?= $active_tab === 'preferences' ? 'active' : '' ?>">
            <i class="fas fa-bell"></i> Notifications
        </a>
        <a href="?tab=security" class="settings-tab <?= $active_tab === 'security' ? 'active' : '' ?>">
            <i class="fas fa-shield-alt"></i> Security
        </a>
    </div>

    <?php if ($active_tab === 'account'): ?>
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-info-circle"></i> Account Information</span>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>Student ID</label>
                    <input type="text" value="<?= htmlspecialchars($graduate['student_id']) ?>" disabled style="background:#f3f4f6;cursor:not-allowed;">
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" value="<?= htmlspecialchars($graduate['email']) ?>" disabled style="background:#f3f4f6;cursor:not-allowed;">
                    <span class="help-text">Email cannot be changed. Please contact admin for assistance.</span>
                </div>
                <div class="form-group">
                    <label>Member Since</label>
                    <input type="text" value="<?= date('F d, Y', strtotime($graduate['created_at'])) ?>" disabled style="background:#f3f4f6;cursor:not-allowed;">
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($active_tab === 'preferences'): ?>
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-bell"></i> Notification Preferences</span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="toggle-group">
                        <div class="toggle-info">
                            <h4>Email Notifications</h4>
                            <p>Receive notifications via email for important updates.</p>
                        </div>
                        <div class="toggle-switch">
                            <input type="checkbox" id="email_notifications" name="email_notifications" <?= $prefs['email_notifications'] ? 'checked' : '' ?>>
                            <label for="email_notifications" class="toggle-slider"></label>
                        </div>
                    </div>
                    
                    <div class="toggle-group">
                        <div class="toggle-info">
                            <h4>Push Notifications</h4>
                            <p>Receive notifications within the portal.</p>
                        </div>
                        <div class="toggle-switch">
                            <input type="checkbox" id="push_notifications" name="push_notifications" <?= $prefs['push_notifications'] ? 'checked' : '' ?>>
                            <label for="push_notifications" class="toggle-slider"></label>
                        </div>
                    </div>
                    
                    <div style="margin-top:1.5rem;">
                        <button type="submit" name="update_preferences" class="btn btn-success"><i class="fas fa-save"></i> Save Preferences</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($active_tab === 'security'): ?>
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-key"></i> Change Password</span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label for="current_password">Current Password <span class="required">*</span></label>
                        <input type="password" id="current_password" name="current_password" placeholder="Enter your current password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password <span class="required">*</span></label>
                        <input type="password" id="new_password" name="new_password" placeholder="Enter new password (min 6 characters)" required>
                        <span class="help-text">Password must be at least 6 characters long.</span>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password <span class="required">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your new password" required>
                    </div>
                    <div style="margin-top:.5rem;">
                        <button type="submit" name="change_password" class="btn"><i class="fas fa-key"></i> Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</main>
</body>
</html>