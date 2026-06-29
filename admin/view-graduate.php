<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header('Location: graduates.php');
    exit;
}

$db = db();

// Get unread notification count for badge
$unread_count = $db->query(
    "SELECT COUNT(*) FROM notifications WHERE recipient_type = 'admin' AND recipient_id = " . (int)$_SESSION['user_id'] . " AND is_read = 0 AND type != 'reply'"
)->fetchColumn();

// Get unread reply count for Messages badge
$unread_reply_count = $db->query(
    "SELECT COUNT(*) FROM notifications WHERE recipient_type = 'admin' AND recipient_id = " . (int)$_SESSION['user_id'] . " AND is_read = 0 AND type = 'reply'"
)->fetchColumn();

$graduate = $db->query("
    SELECT g.*, p.name as program_name, p.code as program_code, c.name as college_name
    FROM graduates g
    LEFT JOIN programs p ON g.program_id = p.id
    LEFT JOIN colleges c ON p.college_id = c.id
    WHERE g.id = $id
")->fetch();

if (!$graduate) {
    header('Location: graduates.php');
    exit;
}

// Get tracer records
$tracer = $db->query("SELECT * FROM tracer_records WHERE graduate_id = $id ORDER BY submitted_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Graduate – SLSU GPTS</title>
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
            margin-bottom: 1.5rem;
        }
        .card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
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
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .info-item {
            padding: .5rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .info-item strong {
            display: inline-block;
            width: 140px;
            color: var(--gray);
            font-weight: 500;
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
        .badge-danger { background: rgba(224,86,86,.15); color: #E05656; }
        .badge-warning { background: rgba(251,191,36,.15); color: #D4A00A; }
        .badge-info { background: rgba(56,189,248,.15); color: #0EA5E9; }
        .badge-secondary { background: rgba(107,114,128,.15); color: #6B7280; }
        .btn {
            padding: .5rem 1.5rem;
            background: var(--gold);
            color: var(--navy);
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: var(--transition);
        }
        .btn:hover {
            background: var(--gold-lt);
        }
        .btn-back {
            background: var(--gray);
            color: white;
        }
        .btn-back:hover {
            background: #6B7280;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            padding: .5rem .75rem;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--gray);
            border-bottom: 2px solid #e5e7eb;
            text-align: left;
            background: #f9fafb;
        }
        td {
            padding: .5rem .75rem;
            font-size: .85rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .text-center { text-align: center; }
        .text-muted { color: var(--gray); }
        .mt-2 { margin-top: 1rem; }
        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); }
            .main { margin-left: 0; padding: 1.25rem; }
            .info-grid { grid-template-columns: 1fr; }
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
        <a href="graduates.php" class="nav-item active"><i class="fas fa-user-graduate"></i> Graduates</a>
        <a href="tracer.php" class="nav-item"><i class="fas fa-map-marked-alt"></i> Tracer Records</a>
        <a href="notifications.php" class="nav-item"><i class="fas fa-bell"></i> Notifications <span class="nav-badge <?= $unread_count > 0 ? '' : 'zero' ?>"><?= $unread_count ?></span></a>
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
        <div class="breadcrumb">SLSU GPTS / <a href="graduates.php" style="color:var(--gold);text-decoration:none;">Graduates</a> / <span>View Graduate</span></div>
        <h1><?= htmlspecialchars($graduate['first_name'] . ' ' . $graduate['last_name']) ?></h1>
        <p>View graduate profile and tracer records.</p>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-user-graduate"></i> Profile Information</span>
            <div>
                <a href="edit_graduate.php?id=<?= $graduate['id'] ?>" class="btn" style="margin-right:.5rem;"><i class="fas fa-edit"></i> Edit</a>
                <a href="graduates.php" class="btn btn-back"><i class="fas fa-arrow-left"></i> Back</a>
            </div>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item"><strong>Student ID:</strong> <?= htmlspecialchars($graduate['student_id']) ?></div>
                <div class="info-item"><strong>Full Name:</strong> <?= htmlspecialchars($graduate['first_name'] . ' ' . ($graduate['middle_name'] ?? '') . ' ' . $graduate['last_name']) ?></div>
                <div class="info-item"><strong>Email:</strong> <?= htmlspecialchars($graduate['email']) ?></div>
                <div class="info-item"><strong>Gender:</strong> <?= htmlspecialchars($graduate['gender'] ?? '—') ?></div>
                <div class="info-item"><strong>Program:</strong> <?= htmlspecialchars($graduate['program_name'] ?? '—') ?> (<?= htmlspecialchars($graduate['program_code'] ?? '—') ?>)</div>
                <div class="info-item"><strong>College:</strong> <?= htmlspecialchars($graduate['college_name'] ?? '—') ?></div>
                <div class="info-item"><strong>Batch Year:</strong> <?= htmlspecialchars($graduate['batch_year'] ?? '—') ?></div>
                <div class="info-item"><strong>Contact Number:</strong> <?= htmlspecialchars($graduate['contact_number'] ?? '—') ?></div>
                <div class="info-item"><strong>Status:</strong> <span class="badge <?= $graduate['is_active'] ? 'badge-success' : 'badge-danger' ?>"><?= $graduate['is_active'] ? 'Active' : 'Inactive' ?></span></div>
                <div class="info-item"><strong>Email Verified:</strong> <span class="badge <?= $graduate['email_verified'] ? 'badge-success' : 'badge-warning' ?>"><?= $graduate['email_verified'] ? 'Yes' : 'No' ?></span></div>
                <div class="info-item"><strong>Registered:</strong> <?= date('M d, Y h:i A', strtotime($graduate['created_at'])) ?></div>
                <div class="info-item"><strong>Last Updated:</strong> <?= date('M d, Y h:i A', strtotime($graduate['updated_at'])) ?></div>
                <?php if (!empty($graduate['address'])): ?>
                    <div class="info-item" style="grid-column: 1 / -1;"><strong>Address:</strong> <?= htmlspecialchars($graduate['address']) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-map-marked-alt"></i> Tracer Records</span>
        </div>
        <div class="card-body">
            <?php if (empty($tracer)): ?>
                <p class="text-muted text-center" style="padding:1rem;">No tracer records found for this graduate.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Year</th>
                            <th>Status</th>
                            <th>Occupation</th>
                            <th>Employer</th>
                            <th>Salary</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tracer as $t): ?>
                            <tr>
                                <td><?= $t['survey_year'] ?></td>
                                <td><span class="badge <?= in_array($t['employment_status'], ['Employed', 'Self-employed']) ? 'badge-success' : 'badge-warning' ?>"><?= htmlspecialchars($t['employment_status'] ?? 'N/A') ?></span></td>
                                <td><?= htmlspecialchars($t['occupation'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($t['employer_name'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($t['monthly_salary_range'] ?? '—') ?></td>
                                <td style="font-size:.8rem;color:var(--gray)"><?= date('M d, Y', strtotime($t['submitted_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>