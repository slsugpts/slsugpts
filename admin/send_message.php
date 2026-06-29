<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
require_once __DIR__ . '/../includes/email.php';

$db = db();

// Get unread notification count for badge
$unread_count = $db->query(
    "SELECT COUNT(*) FROM notifications WHERE recipient_type = 'admin' AND recipient_id = " . (int)$_SESSION['user_id'] . " AND is_read = 0 AND type != 'reply'"
)->fetchColumn();

$unread_reply_count = $db->query(
    "SELECT COUNT(*) FROM notifications WHERE recipient_type = 'admin' AND recipient_id = " . (int)$_SESSION['user_id'] . " AND is_read = 0 AND type = 'reply'"
)->fetchColumn();

// Get graduates for dropdown
$graduates = $db->query("
    SELECT id, first_name, last_name, email, student_id 
    FROM graduates 
    WHERE is_active = 1 
    ORDER BY last_name
")->fetchAll();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient_id = (int)$_POST['recipient_id'];
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $send_email = isset($_POST['send_email']);
    
    if (!$recipient_id || !$subject || !$message) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            // Get recipient name
            $recipient = fetch("SELECT first_name, last_name, email FROM graduates WHERE id = ?", [$recipient_id]);
            
            // Save notification in database
            $stmt = $db->prepare("
                INSERT INTO notifications (recipient_type, recipient_id, subject, message, type, sender_type, sender_id)
                VALUES ('graduate', ?, ?, ?, 'message', 'admin', ?)
            ");
            $stmt->execute([$recipient_id, $subject, $message, $_SESSION['user_id']]);
            
            $email_status = '';
            
            // Send email if checkbox is checked
            if ($send_email) {
                $email_sent = send_notification_email($recipient_id, $subject, $message);
                $email_status = $email_sent ? '✅ Email notification sent.' : '❌ Email notification failed.';
            }
            
            $success = '✅ Message sent successfully! ' . $email_status;
            
        } catch (PDOException $e) {
            $error = 'Error sending message: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Message – SLSU GPTS</title>
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
        }
        .nav-badge.zero {
            display: none;
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
            max-width: 800px;
        }
        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            background: #fafbfc;
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
            margin-bottom: 1.25rem;
        }
        .form-group label {
            display: block;
            font-size: .78rem;
            font-weight: 600;
            color: var(--gray);
            margin-bottom: .4rem;
            letter-spacing: .02em;
        }
        .form-group label i {
            margin-right: .4rem;
            color: var(--gold);
            width: 16px;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: .7rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: var(--radius);
            font-size: .85rem;
            background: white;
            color: #1F2937;
            transition: var(--transition);
            font-family: 'DM Sans', sans-serif;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(201,168,76,0.15);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 150px;
        }
        .form-group .checkbox-group {
            display: flex;
            align-items: center;
            gap: .75rem;
        }
        .form-group .checkbox-group input {
            width: auto;
            padding: 0;
            height: 18px;
            width: 18px;
            accent-color: var(--gold);
        }
        .form-group .checkbox-group label {
            margin-bottom: 0;
            font-weight: 500;
            cursor: pointer;
        }
        .form-group .checkbox-group small {
            color: var(--gray);
            font-size: .75rem;
        }
        .btn-submit {
            padding: .7rem 2rem;
            background: linear-gradient(135deg, var(--gold), var(--gold-lt));
            color: var(--navy);
            border: none;
            border-radius: var(--radius);
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
            font-size: .85rem;
            font-family: 'DM Sans', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: .6rem;
            height: 44px;
            min-width: 140px;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(201,168,76,0.25);
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(201,168,76,0.35);
        }
        .btn-back {
            padding: .7rem 2rem;
            background: #e5e7eb;
            color: var(--gray);
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: .6rem;
            font-size: .85rem;
            font-family: 'DM Sans', sans-serif;
            height: 44px;
        }
        .btn-back:hover {
            background: #d1d5db;
        }
        .btn-group {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .alert {
            padding: .8rem 1.2rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: .8rem;
        }
        .alert-success {
            background: rgba(76,175,80,.12);
            border: 1px solid rgba(76,175,80,.25);
            color: var(--green);
        }
        .alert-danger {
            background: rgba(224,86,86,.12);
            border: 1px solid rgba(224,86,86,.25);
            color: var(--red);
        }
        .text-muted { color: var(--gray); }
        .mt-2 { margin-top: .5rem; }

        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); }
            .main { margin-left: 0; padding: 1.25rem; }
            .btn-group { flex-direction: column; }
            .btn-submit, .btn-back { width: 100%; justify-content: center; }
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
        <a href="send_message.php" class="nav-item active"><i class="fas fa-paper-plane"></i> Send Message</a>
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
        <div class="breadcrumb">SLSU GPTS / <span>Send Message</span></div>
        <h1>Send Message</h1>
        <p>Send a message to a graduate.</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-paper-plane"></i> Compose Message</span>
        </div>
        <div class="card-body">
            <form method="POST">
                <?= csrf_field() ?>
                
                <div class="form-group">
                    <label for="recipient_id"><i class="fas fa-user"></i> Recipient</label>
                    <select id="recipient_id" name="recipient_id" required>
                        <option value="">Select Graduate</option>
                        <?php foreach ($graduates as $g): ?>
                            <option value="<?= $g['id'] ?>">
                                <?= htmlspecialchars($g['first_name'] . ' ' . $g['last_name'] . ' (' . $g['student_id'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="subject"><i class="fas fa-heading"></i> Subject</label>
                    <input type="text" id="subject" name="subject" placeholder="Enter message subject" required>
                </div>
                
                <div class="form-group">
                    <label for="message"><i class="fas fa-comment"></i> Message</label>
                    <textarea id="message" name="message" placeholder="Type your message here..." required></textarea>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="send_email" name="send_email" value="1" checked>
                        <label for="send_email"><i class="fas fa-envelope"></i> Also send email notification</label>
                    </div>
                    <small class="text-muted" style="display:block; margin-top: .25rem; margin-left: 2.2rem;">
                        The recipient will receive an email copy of this message.
                    </small>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Send Message</button>
                    <a href="messages.php" class="btn-back"><i class="fas fa-times"></i> Cancel</a>
                </div>
            </form>
        </div>
    </div>
</main>
</body>
</html>