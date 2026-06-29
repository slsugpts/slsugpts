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

// Search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
if ($search) {
    $search_param = "%$search%";
    $stmt = $db->prepare("
        SELECT g.*, p.code as program_code, p.name as program_name 
        FROM graduates g 
        LEFT JOIN programs p ON g.program_id = p.id 
        WHERE g.is_active = 1 
        AND (g.first_name LIKE ? OR g.last_name LIKE ? OR g.student_id LIKE ? OR g.email LIKE ?)
        ORDER BY g.created_at DESC
    ");
    $stmt->execute([$search_param, $search_param, $search_param, $search_param]);
    $graduates = $stmt->fetchAll();
} else {
    $graduates = $db->query("
        SELECT g.*, p.code as program_code, p.name as program_name 
        FROM graduates g 
        LEFT JOIN programs p ON g.program_id = p.id 
        WHERE g.is_active = 1 
        ORDER BY g.created_at DESC
    ")->fetchAll();
}

$total = count($graduates);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Graduates – SLSU GPTS</title>
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
            padding: 1.5rem;
            overflow-x: auto;
        }
        .total-badge {
            background: var(--gold);
            color: var(--navy);
            padding: .3rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: .8rem;
        }
        /* Search Bar */
        .search-bar {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .search-bar input {
            padding: .6rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: var(--radius);
            font-size: .85rem;
            background: white;
            color: #1F2937;
            min-width: 250px;
            transition: var(--transition);
            font-family: 'DM Sans', sans-serif;
        }
        .search-bar input:focus {
            border-color: var(--gold);
            outline: none;
            box-shadow: 0 0 0 3px rgba(201,168,76,0.12);
        }
        .search-bar input::placeholder {
            color: #aab;
        }
        .search-bar .btn-search {
            padding: .6rem 1.5rem;
            background: linear-gradient(135deg, var(--gold), var(--gold-lt));
            color: var(--navy);
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            font-family: 'DM Sans', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
        }
        .search-bar .btn-search:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(201,168,76,0.25);
        }
        .search-bar .btn-clear {
            padding: .6rem 1.5rem;
            background: #e5e7eb;
            color: var(--gray);
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            font-size: .85rem;
            transition: var(--transition);
            font-family: 'DM Sans', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
        }
        .search-bar .btn-clear:hover {
            background: #d1d5db;
        }
        .search-bar .result-text {
            color: var(--gray);
            font-size: .85rem;
            margin-left: .5rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            padding: .75rem 1rem;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--gray);
            border-bottom: 2px solid #e5e7eb;
            text-align: left;
            background: #f9fafb;
        }
        td {
            padding: .75rem 1rem;
            font-size: .87rem;
            border-bottom: 1px solid #e5e7eb;
        }
        tr:hover td {
            background: #f9fafb;
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
        .badge-info { background: rgba(56,189,248,.15); color: #0EA5E9; }
        .badge-secondary { background: rgba(107,114,128,.15); color: #6B7280; }
        .text-center { text-align: center; }
        .text-muted { color: var(--gray); }
        .mt-2 { margin-top: .5rem; }
        .mb-2 { margin-bottom: .5rem; }
        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); }
            .main { margin-left: 0; padding: 1.25rem; }
            .search-bar input { min-width: 100%; }
            .search-bar { flex-direction: column; width: 100%; }
            .search-bar .btn-search, .search-bar .btn-clear { width: 100%; justify-content: center; }
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
        <div class="breadcrumb">SLSU GPTS / <span>Graduates</span></div>
        <h1>Manage Graduates</h1>
        <p>View and search registered graduates.</p>
    </div>
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-user-graduate"></i> All Graduates <span class="total-badge"><?= $total ?></span></span>
            <div class="search-bar">
                <form method="GET" style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center;width:100%;">
                    <input type="text" name="search" placeholder="Search by name, ID, or email..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search</button>
                    <?php if ($search): ?>
                        <a href="graduates.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                    <span class="result-text"><?= $total ?> graduate(s) found</span>
                </form>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($graduates)): ?>
                <div class="text-center text-muted" style="padding:2rem;">
                    <i class="fas fa-users" style="font-size:2rem;display:block;margin-bottom:.5rem;opacity:.3;"></i>
                    <?php if ($search): ?>
                        No graduates found matching "<strong><?= htmlspecialchars($search) ?></strong>".
                    <?php else: ?>
                        No graduates found.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Student ID</th>
                            <th>Email</th>
                            <th>Program</th>
                            <th>Batch</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($graduates as $g): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($g['first_name'] . ' ' . $g['last_name']) ?></strong></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($g['student_id']) ?></span></td>
                            <td><?= htmlspecialchars($g['email']) ?></td>
                            <td><span class="badge badge-secondary"><?= htmlspecialchars($g['program_code'] ?? '—') ?></span></td>
                            <td><?= htmlspecialchars($g['batch_year'] ?? '—') ?></td>
                            <td><span class="badge <?= $g['is_active'] ? 'badge-success' : 'badge-danger' ?>"><?= $g['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                            <td>
                                <a href="view_graduate.php?id=<?= $g['id'] ?>" style="color:var(--gray);text-decoration:none;">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
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