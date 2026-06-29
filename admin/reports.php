<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$db = db();

// Get unread notification count for badge
$unread_count = $db->query(
    "SELECT COUNT(*) FROM notifications WHERE recipient_type = 'admin' AND recipient_id = " . (int)$_SESSION['user_id'] . " AND is_read = 0 AND type != 'reply'"
)->fetchColumn();

// Get unread reply count for Messages badge
$unread_reply_count = $db->query(
    "SELECT COUNT(*) FROM notifications WHERE recipient_type = 'admin' AND recipient_id = " . (int)$_SESSION['user_id'] . " AND is_read = 0 AND type = 'reply'"
)->fetchColumn();

// Get stats
$total_graduates = $db->query('SELECT COUNT(*) FROM graduates WHERE is_active = 1')->fetchColumn();
$total_surveys = $db->query('SELECT COUNT(*) FROM tracer_records')->fetchColumn();
$employed_count = $db->query("SELECT COUNT(*) FROM tracer_records WHERE employment_status IN ('Employed','Self-employed')")->fetchColumn();
$unemployed_count = $db->query("SELECT COUNT(*) FROM tracer_records WHERE employment_status = 'Unemployed'")->fetchColumn();
$employment_rate = $total_surveys > 0 ? round(($employed_count / $total_surveys) * 100) : 0;

// Employment by program
$program_stats = $db->query("
    SELECT 
        p.code as program,
        COUNT(DISTINCT g.id) as total_graduates,
        COUNT(t.id) as total_surveys,
        SUM(CASE WHEN t.employment_status IN ('Employed','Self-employed') THEN 1 ELSE 0 END) as employed
    FROM programs p
    LEFT JOIN graduates g ON g.program_id = p.id AND g.is_active = 1
    LEFT JOIN tracer_records t ON t.graduate_id = g.id
    GROUP BY p.id
    ORDER BY p.code
")->fetchAll();

// Employment by year
$year_stats = $db->query("
    SELECT 
        g.batch_year,
        COUNT(DISTINCT g.id) as total_graduates,
        COUNT(t.id) as total_surveys,
        SUM(CASE WHEN t.employment_status IN ('Employed','Self-employed') THEN 1 ELSE 0 END) as employed
    FROM graduates g
    LEFT JOIN tracer_records t ON t.graduate_id = g.id
    WHERE g.batch_year IS NOT NULL
    GROUP BY g.batch_year
    ORDER BY g.batch_year DESC
")->fetchAll();

// Employment status breakdown
$status_stats = $db->query("
    SELECT 
        employment_status,
        COUNT(*) as count
    FROM tracer_records
    GROUP BY employment_status
    ORDER BY count DESC
")->fetchAll();

// Gender distribution
$gender_stats = $db->query("
    SELECT 
        gender,
        COUNT(*) as count
    FROM graduates
    WHERE is_active = 1 AND gender IS NOT NULL
    GROUP BY gender
")->fetchAll();

// Salary distribution
$salary_stats = $db->query("
    SELECT 
        monthly_salary_range,
        COUNT(*) as count
    FROM tracer_records
    WHERE monthly_salary_range IS NOT NULL
    GROUP BY monthly_salary_range
    ORDER BY count DESC
")->fetchAll();

$page_title = 'Reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports – SLSU GPTS</title>
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
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: var(--radius);
            padding: 1.25rem;
        }
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--white);
            line-height: 1;
        }
        .stat-label {
            font-size: .75rem;
            color: var(--gray);
            font-weight: 400;
        }
        .chart-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: var(--radius);
            overflow: hidden;
        }
        .card-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .card-title {
            font-weight: 700;
            font-size: .9rem;
            color: var(--white);
            display: flex;
            align-items: center;
            gap: .6rem;
        }
        .card-title i {
            color: var(--gold);
        }
        .card-body {
            padding: 1rem 1.5rem;
            overflow-x: auto;
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
        tr:hover td {
            background: #f9fafb;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            padding: .15rem .5rem;
            border-radius: 50px;
            font-size: .7rem;
            font-weight: 600;
        }
        .badge-success { background: rgba(76,175,80,.15); color: #4CAF50; }
        .badge-danger { background: rgba(224,86,86,.15); color: #E05656; }
        .badge-warning { background: rgba(251,191,36,.15); color: #D4A00A; }
        .badge-info { background: rgba(56,189,248,.15); color: #0EA5E9; }
        .badge-purple { background: rgba(139,92,246,.15); color: #8B5CF6; }
        .badge-secondary { background: rgba(107,114,128,.15); color: #6B7280; }
        .text-center { text-align: center; }
        .text-muted { color: var(--gray); }
        @media (max-width: 1024px) {
            .chart-grid { grid-template-columns: 1fr; }
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
        <a href="notifications.php" class="nav-item"><i class="fas fa-bell"></i> Notifications <span class="nav-badge <?= $unread_count > 0 ? '' : 'zero' ?>"><?= $unread_count ?></span></a>
    </nav>
    <div class="nav-section-label">Messages</div>
    <nav class="sidebar-nav">
        <a href="send_message.php" class="nav-item"><i class="fas fa-paper-plane"></i> Send Message</a>
        <a href="messages.php" class="nav-item"><i class="fas fa-envelope"></i> Inbox <span class="nav-badge <?= $unread_reply_count > 0 ? '' : 'zero' ?>"><?= $unread_reply_count ?></span></a>
    </nav>
    <div class="nav-section-label">System</div>
    <nav class="sidebar-nav">
        <a href="reports.php" class="nav-item active"><i class="fas fa-chart-bar"></i> Reports</a>
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
        <div class="breadcrumb">SLSU GPTS / <span>Reports</span></div>
        <h1>Reports & Analytics</h1>
        <p>Comprehensive overview of graduate data and employment outcomes.</p>
    </div>
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-value"><?= number_format($total_graduates) ?></div><div class="stat-label">Total Graduates</div></div>
        <div class="stat-card"><div class="stat-value"><?= number_format($total_surveys) ?></div><div class="stat-label">Survey Responses</div></div>
        <div class="stat-card"><div class="stat-value"><?= $employment_rate ?>%</div><div class="stat-label">Employment Rate</div></div>
        <div class="stat-card"><div class="stat-value"><?= number_format($unemployed_count) ?></div><div class="stat-label">Unemployed</div></div>
    </div>
    <div class="chart-grid">
        <div class="card">
            <div class="card-header"><span class="card-title"><i class="fas fa-graduation-cap"></i> Employment by Program</span></div>
            <div class="card-body">
                <table>
                    <thead><tr><th>Program</th><th>Graduates</th><th>Employed</th><th>Rate</th></tr></thead>
                    <tbody>
                        <?php foreach ($program_stats as $p): ?>
                            <?php $rate = $p['total_graduates'] > 0 ? round(($p['employed'] / $p['total_graduates']) * 100) : 0; ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['program']) ?></strong></td>
                                <td><?= $p['total_graduates'] ?></td>
                                <td><?= $p['employed'] ?></td>
                                <td><span class="badge <?= $rate >= 70 ? 'badge-success' : ($rate >= 40 ? 'badge-warning' : 'badge-danger') ?>"><?= $rate ?>%</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span class="card-title"><i class="fas fa-calendar"></i> Employment by Batch</span></div>
            <div class="card-body">
                <table>
                    <thead><tr><th>Batch</th><th>Graduates</th><th>Employed</th><th>Rate</th></tr></thead>
                    <tbody>
                        <?php foreach ($year_stats as $y): ?>
                            <?php $rate = $y['total_graduates'] > 0 ? round(($y['employed'] / $y['total_graduates']) * 100) : 0; ?>
                            <tr>
                                <td><strong><?= $y['batch_year'] ?></strong></td>
                                <td><?= $y['total_graduates'] ?></td>
                                <td><?= $y['employed'] ?></td>
                                <td><span class="badge <?= $rate >= 70 ? 'badge-success' : ($rate >= 40 ? 'badge-warning' : 'badge-danger') ?>"><?= $rate ?>%</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span class="card-title"><i class="fas fa-briefcase"></i> Employment Status</span></div>
            <div class="card-body">
                <table>
                    <thead><tr><th>Status</th><th>Count</th><th>%</th></tr></thead>
                    <tbody>
                        <?php foreach ($status_stats as $s): ?>
                            <?php $pct = $total_surveys > 0 ? round(($s['count'] / $total_surveys) * 100) : 0; ?>
                            <tr>
                                <td><span class="badge <?= get_badge_class($s['employment_status']) ?>"><?= htmlspecialchars($s['employment_status'] ?? 'Unknown') ?></span></td>
                                <td><?= number_format($s['count']) ?></td>
                                <td><?= $pct ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span class="card-title"><i class="fas fa-money-bill-wave"></i> Salary Distribution</span></div>
            <div class="card-body">
                <table>
                    <thead><tr><th>Salary Range</th><th>Count</th><th>%</th></tr></thead>
                    <tbody>
                        <?php foreach ($salary_stats as $s): ?>
                            <?php $pct = $total_surveys > 0 ? round(($s['count'] / $total_surveys) * 100) : 0; ?>
                            <tr>
                                <td><?= htmlspecialchars($s['monthly_salary_range'] ?? 'Not specified') ?></td>
                                <td><?= number_format($s['count']) ?></td>
                                <td><?= $pct ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
</body>
</html>