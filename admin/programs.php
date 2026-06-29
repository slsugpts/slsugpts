<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$db = db();

// Remove UNIQUE constraint from code column if it exists
try {
    $db->exec("ALTER TABLE programs DROP INDEX code");
} catch (PDOException $e) {
    // Index might not exist, continue
}

// Check if campus column exists, if not add it
try {
    $db->query("SELECT campus FROM programs LIMIT 1");
} catch (PDOException $e) {
    $db->exec("ALTER TABLE programs ADD COLUMN campus VARCHAR(100) DEFAULT 'Main Campus'");
}

// Check and add colleges if they don't exist
$collegesToAdd = [
    ['name' => 'College of Arts and Sciences', 'code' => 'CAS'],
    ['name' => 'College of Allied Medicine', 'code' => 'CAM'],
    ['name' => 'College of Engineering', 'code' => 'COE'],
    ['name' => 'College of Industrial Technology', 'code' => 'CIT'],
    ['name' => 'College of Teacher Education', 'code' => 'CTE']
];

try {
    foreach ($collegesToAdd as $college) {
        $stmt = $db->prepare("SELECT * FROM colleges WHERE code = ?");
        $stmt->execute([$college['code']]);
        $exists = $stmt->fetch();
        
        if (!$exists) {
            $insert = $db->prepare("INSERT INTO colleges (name, code) VALUES (?, ?)");
            $insert->execute([$college['name'], $college['code']]);
        }
    }
} catch (PDOException $e) {
    // Table might not exist yet or other error
}

// Get unread notification count for badge
$unread_count = $db->query(
    "SELECT COUNT(*) FROM notifications WHERE recipient_type = 'admin' AND recipient_id = " . (int)$_SESSION['user_id'] . " AND is_read = 0 AND type != 'reply'"
)->fetchColumn();

// Get unread reply count for Messages badge
$unread_reply_count = $db->query(
    "SELECT COUNT(*) FROM notifications WHERE recipient_type = 'admin' AND recipient_id = " . (int)$_SESSION['user_id'] . " AND is_read = 0 AND type = 'reply'"
)->fetchColumn();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_program'])) {
        try {
            $stmt = $db->prepare("INSERT INTO programs (college_id, name, code, campus) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_POST['college_id'], $_POST['name'], $_POST['code'], $_POST['campus']]);
            $success = "Program added successfully!";
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $error = "Error: Program code '{$_POST['code']}' already exists. Please use a unique code.";
            } else {
                $error = "Error adding program: " . $e->getMessage();
            }
        }
    }
    if (isset($_POST['toggle_program'])) {
        $stmt = $db->prepare("UPDATE programs SET is_active = !is_active WHERE id = ?");
        $stmt->execute([$_POST['program_id']]);
        $success = "Program status updated!";
    }
    if (isset($_POST['delete_program'])) {
        $stmt = $db->prepare("DELETE FROM programs WHERE id = ?");
        $stmt->execute([$_POST['program_id']]);
        $success = "Program deleted successfully!";
    }
}

// Get data
$colleges = $db->query("SELECT * FROM colleges ORDER BY name")->fetchAll();

// Get programs grouped by campus
$mainCampusPrograms = $db->query("
    SELECT p.*, c.name as college_name,
    COALESCE(p.campus, 'Main Campus') as campus_display
    FROM programs p 
    LEFT JOIN colleges c ON p.college_id = c.id 
    WHERE p.campus = 'Main Campus' OR p.campus IS NULL
    ORDER BY p.name
")->fetchAll();

$lucenaPrograms = $db->query("
    SELECT p.*, c.name as college_name,
    COALESCE(p.campus, 'Main Campus') as campus_display
    FROM programs p 
    LEFT JOIN colleges c ON p.college_id = c.id 
    WHERE p.campus = 'Lucena Campus'
    ORDER BY p.name
")->fetchAll();

$tayabasPrograms = $db->query("
    SELECT p.*, c.name as college_name,
    COALESCE(p.campus, 'Main Campus') as campus_display
    FROM programs p 
    LEFT JOIN colleges c ON p.college_id = c.id 
    WHERE p.campus = 'Tayabas Campus'
    ORDER BY p.name
")->fetchAll();

// Campus options
$campuses = ['Main Campus', 'Lucena Campus', 'Tayabas Campus'];
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'main';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programs – SLSU GPTS</title>
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
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
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
            overflow-x: auto;
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
        .badge-campus-main { background: rgba(201,168,76,.15); color: #C9A84C; }
        .badge-campus-lucena { background: rgba(52,152,219,.15); color: #2980B9; }
        .badge-campus-tayabas { background: rgba(155,89,182,.15); color: #8E44AD; }

        /* Improved Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.25rem;
            align-items: end;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: .4rem;
        }
        .form-group label {
            font-size: .78rem;
            font-weight: 600;
            color: var(--gray);
            letter-spacing: .02em;
        }
        .form-group label i {
            margin-right: .4rem;
            color: var(--gold);
            width: 16px;
        }
        .form-group input, .form-group select {
            padding: .7rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: var(--radius);
            font-size: .85rem;
            background: white;
            color: #1F2937;
            transition: var(--transition);
            font-family: 'DM Sans', sans-serif;
            width: 100%;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(201,168,76,0.15);
        }
        .form-group input::placeholder {
            color: #aab;
            font-weight: 300;
        }
        .form-group select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%232E7D32' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            padding-right: 2.5rem;
            cursor: pointer;
        }
        .form-group select option {
            padding: .5rem;
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
        .btn-submit:active {
            transform: translateY(0);
        }
        .btn-submit i {
            font-size: .9rem;
        }

        .btn {
            padding: .6rem 1.5rem;
            background: var(--gold);
            color: var(--navy);
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
        }
        .btn:hover {
            background: var(--gold-lt);
        }
        .btn-sm {
            padding: .35rem .75rem;
            font-size: .75rem;
            white-space: nowrap;
        }
        .btn-danger {
            background: var(--red);
            color: white;
        }
        .btn-danger:hover {
            background: #c0392b;
        }
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        .btn-delete:hover {
            background: #c82333;
        }
        .btn-group {
            display: flex;
            gap: 0.4rem;
            flex-wrap: nowrap;
            align-items: center;
        }
        .btn-group form {
            display: inline-block;
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
        .text-center { text-align: center; }
        .text-muted { color: var(--gray); }
        .mt-2 { margin-top: .5rem; }

        /* Tab styles */
        .tab-container {
            display: flex;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 1.5rem;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .tab-btn {
            padding: 0.75rem 1.5rem;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--gray);
            cursor: pointer;
            transition: var(--transition);
            font-family: 'DM Sans', sans-serif;
        }
        .tab-btn:hover {
            color: var(--white);
            background: rgba(27,94,32,.05);
        }
        .tab-btn.active {
            color: var(--gold);
            border-bottom-color: var(--gold);
        }
        .tab-btn .count {
            background: var(--gold);
            color: var(--navy);
            border-radius: 50px;
            padding: 0.1rem 0.6rem;
            font-size: 0.7rem;
            margin-left: 0.4rem;
        }
        .tab-panel {
            display: none;
        }
        .tab-panel.active {
            display: block;
        }
        .campus-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding: 0.75rem 1rem;
            background: #f9fafb;
            border-radius: var(--radius);
            border-left: 4px solid var(--gold);
        }
        .campus-header i {
            font-size: 1.2rem;
        }
        .campus-header .campus-name {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--white);
        }
        .campus-header .campus-count {
            color: var(--gray);
            font-size: 0.85rem;
            margin-left: auto;
        }

        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); }
            .main { margin-left: 0; padding: 1.25rem; }
            .form-grid { grid-template-columns: 1fr; }
            .btn-group { flex-wrap: wrap; }
            .tab-container { flex-direction: column; border-bottom: none; }
            .tab-btn { border-bottom: 2px solid #e5e7eb; }
            .btn-submit { width: 100%; }
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
        <a href="reports.php" class="nav-item"><i class="fas fa-chart-bar"></i> Reports</a>
        <a href="programs.php" class="nav-item active"><i class="fas fa-book"></i> Programs</a>
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
        <div class="breadcrumb">SLSU GPTS / <span>Programs</span></div>
        <h1>Manage Programs</h1>
        <p>Add and manage academic programs.</p>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?= $success ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <!-- Add Program -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-graduation-cap"></i> Add New Program</span>
            <span style="font-size: .75rem; color: var(--gray);"><i class="fas fa-info-circle"></i> Fill in all fields</span>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="program_college"><i class="fas fa-university"></i> College</label>
                        <select id="program_college" name="college_id" required>
                            <option value="">Select College</option>
                            <?php foreach ($colleges as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="program_name"><i class="fas fa-book"></i> Program Name</label>
                        <input type="text" id="program_name" name="name" placeholder="e.g. BS Computer Engineering" required>
                    </div>
                    <div class="form-group">
                        <label for="program_code"><i class="fas fa-tag"></i> Program Code</label>
                        <input type="text" id="program_code" name="code" placeholder="e.g. BSCpE" required>
                    </div>
                    <div class="form-group">
                        <label for="program_campus"><i class="fas fa-map-marker-alt"></i> Campus</label>
                        <select id="program_campus" name="campus" required>
                            <option value="">Select Campus</option>
                            <?php foreach ($campuses as $campus): ?>
                                <option value="<?= $campus ?>" <?= $campus == 'Main Campus' ? 'selected' : '' ?>><?= $campus ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="justify-content: end;">
                        <button type="submit" name="add_program" class="btn-submit">
                            <i class="fas fa-plus-circle"></i> Add Program
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Programs List with Tabs -->
    <div class="card">
        <div class="card-header">
            <span class="card-title"><i class="fas fa-list"></i> All Programs</span>
            <span style="font-size: .75rem; color: var(--gray);">Total: <?= count($mainCampusPrograms) + count($lucenaPrograms) + count($tayabasPrograms) ?></span>
        </div>
        <div class="card-body">
            <!-- Tab Navigation -->
            <div class="tab-container">
                <button class="tab-btn <?= $activeTab == 'main' ? 'active' : '' ?>" onclick="switchTab('main')">
                    <i class="fas fa-school"></i> Main Campus <span class="count"><?= count($mainCampusPrograms) ?></span>
                </button>
                <button class="tab-btn <?= $activeTab == 'lucena' ? 'active' : '' ?>" onclick="switchTab('lucena')">
                    <i class="fas fa-city"></i> Lucena Campus <span class="count"><?= count($lucenaPrograms) ?></span>
                </button>
                <button class="tab-btn <?= $activeTab == 'tayabas' ? 'active' : '' ?>" onclick="switchTab('tayabas')">
                    <i class="fas fa-tree"></i> Tayabas Campus <span class="count"><?= count($tayabasPrograms) ?></span>
                </button>
            </div>

            <!-- Main Campus Panel -->
            <div id="tab-main" class="tab-panel <?= $activeTab == 'main' ? 'active' : '' ?>">
                <div class="campus-header">
                    <i class="fas fa-school" style="color: #C9A84C;"></i>
                    <span class="campus-name">Main Campus</span>
                    <span class="campus-count"><?= count($mainCampusPrograms) ?> program(s)</span>
                </div>
                <?php if (empty($mainCampusPrograms)): ?>
                    <div class="text-center text-muted" style="padding:2rem;">No programs found for Main Campus.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Program Name</th>
                                <th>College</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mainCampusPrograms as $p): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['code']) ?></strong></td>
                                <td><?= htmlspecialchars($p['name']) ?></td>
                                <td><?= htmlspecialchars($p['college_name'] ?? '—') ?></td>
                                <td><span class="badge <?= $p['is_active'] ? 'badge-success' : 'badge-danger' ?>"><?= $p['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                <td>
                                    <div class="btn-group">
                                        <form method="POST">
                                            <input type="hidden" name="program_id" value="<?= $p['id'] ?>">
                                            <button type="submit" name="toggle_program" class="btn btn-sm <?= $p['is_active'] ? 'btn-danger' : 'btn' ?>">
                                                <i class="fas <?= $p['is_active'] ? 'fa-ban' : 'fa-check' ?>"></i>
                                                <?= $p['is_active'] ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this program? This action cannot be undone.');">
                                            <input type="hidden" name="program_id" value="<?= $p['id'] ?>">
                                            <button type="submit" name="delete_program" class="btn btn-sm btn-delete">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Lucena Campus Panel -->
            <div id="tab-lucena" class="tab-panel <?= $activeTab == 'lucena' ? 'active' : '' ?>">
                <div class="campus-header">
                    <i class="fas fa-city" style="color: #2980B9;"></i>
                    <span class="campus-name">Lucena Campus</span>
                    <span class="campus-count"><?= count($lucenaPrograms) ?> program(s)</span>
                </div>
                <?php if (empty($lucenaPrograms)): ?>
                    <div class="text-center text-muted" style="padding:2rem;">No programs found for Lucena Campus.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Program Name</th>
                                <th>College</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lucenaPrograms as $p): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['code']) ?></strong></td>
                                <td><?= htmlspecialchars($p['name']) ?></td>
                                <td><?= htmlspecialchars($p['college_name'] ?? '—') ?></td>
                                <td><span class="badge <?= $p['is_active'] ? 'badge-success' : 'badge-danger' ?>"><?= $p['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                <td>
                                    <div class="btn-group">
                                        <form method="POST">
                                            <input type="hidden" name="program_id" value="<?= $p['id'] ?>">
                                            <button type="submit" name="toggle_program" class="btn btn-sm <?= $p['is_active'] ? 'btn-danger' : 'btn' ?>">
                                                <i class="fas <?= $p['is_active'] ? 'fa-ban' : 'fa-check' ?>"></i>
                                                <?= $p['is_active'] ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this program? This action cannot be undone.');">
                                            <input type="hidden" name="program_id" value="<?= $p['id'] ?>">
                                            <button type="submit" name="delete_program" class="btn btn-sm btn-delete">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Tayabas Campus Panel -->
            <div id="tab-tayabas" class="tab-panel <?= $activeTab == 'tayabas' ? 'active' : '' ?>">
                <div class="campus-header">
                    <i class="fas fa-tree" style="color: #8E44AD;"></i>
                    <span class="campus-name">Tayabas Campus</span>
                    <span class="campus-count"><?= count($tayabasPrograms) ?> program(s)</span>
                </div>
                <?php if (empty($tayabasPrograms)): ?>
                    <div class="text-center text-muted" style="padding:2rem;">No programs found for Tayabas Campus.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Program Name</th>
                                <th>College</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tayabasPrograms as $p): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['code']) ?></strong></td>
                                <td><?= htmlspecialchars($p['name']) ?></td>
                                <td><?= htmlspecialchars($p['college_name'] ?? '—') ?></td>
                                <td><span class="badge <?= $p['is_active'] ? 'badge-success' : 'badge-danger' ?>"><?= $p['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                <td>
                                    <div class="btn-group">
                                        <form method="POST">
                                            <input type="hidden" name="program_id" value="<?= $p['id'] ?>">
                                            <button type="submit" name="toggle_program" class="btn btn-sm <?= $p['is_active'] ? 'btn-danger' : 'btn' ?>">
                                                <i class="fas <?= $p['is_active'] ? 'fa-ban' : 'fa-check' ?>"></i>
                                                <?= $p['is_active'] ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this program? This action cannot be undone.');">
                                            <input type="hidden" name="program_id" value="<?= $p['id'] ?>">
                                            <button type="submit" name="delete_program" class="btn btn-sm btn-delete">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
function switchTab(tab) {
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    window.history.pushState({}, '', url);

    document.querySelectorAll('.tab-panel').forEach(panel => {
        panel.classList.remove('active');
    });
    
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    document.getElementById('tab-' + tab).classList.add('active');
    
    document.querySelectorAll('.tab-btn').forEach(btn => {
        if (btn.textContent.toLowerCase().includes(tab.replace('-', ' '))) {
            btn.classList.add('active');
        }
    });
}
</script>
</body>
</html>