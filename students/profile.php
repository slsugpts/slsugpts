<?php
require_once __DIR__ . '/../includes/auth.php';
require_graduate();

$db = db();
$user_id = $_SESSION['user_id'];

// Get graduate data with program info
$graduate = $db->query("
    SELECT g.*, p.name as program_name, p.code as program_code
    FROM graduates g
    LEFT JOIN programs p ON g.program_id = p.id
    WHERE g.id = $user_id
")->fetch();

$error = '';
$success = '';

// Check if edit mode is enabled via URL parameter
$edit_mode = isset($_GET['edit']) && $_GET['edit'] === 'true';

// Get ALL programs grouped by campus
$programs = $db->query("
    SELECT p.*, 
    COALESCE(p.campus, 'Main Campus') as campus_display
    FROM programs p 
    WHERE p.is_active = 1 
    ORDER BY FIELD(p.campus, 'Tayabas Campus', 'Lucena Campus', 'Main Campus'), p.name
")->fetchAll();

// Group programs by campus
$programs_by_campus = [];
foreach ($programs as $program) {
    $campus = $program['campus_display'] ?? 'Main Campus';
    $programs_by_campus[$campus][] = $program;
}

$years = get_years();

// Get unread message count for badge
$unread_count = $db->query("SELECT COUNT(*) FROM notifications WHERE recipient_type = 'graduate' AND recipient_id = $user_id AND is_read = 0 AND sender_type = 'admin'")->fetchColumn();

// Get unread notification count for badge (system notifications only)
$unread_notif_count = $db->query("SELECT COUNT(*) FROM notifications WHERE recipient_type = 'graduate' AND recipient_id = $user_id AND is_read = 0 AND (sender_type IS NULL OR sender_type = 'system')")->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $gender = $_POST['gender'] ?? '';
    
    $program_id = (int)($_POST['program_id'] ?? 0);
    if ($program_id === 0) {
        $program_id = null;
    }
    
    $batch_year = (int)($_POST['batch_year'] ?? 0);
    if ($batch_year === 0) {
        $batch_year = null;
    }

    if (!$first_name || !$last_name) {
        $error = 'First name and last name are required.';
    } else {
        try {
            $stmt = $db->prepare("
                UPDATE graduates SET 
                    first_name = ?, middle_name = ?, last_name = ?, gender = ?,
                    contact_number = ?, address = ?,
                    program_id = ?, batch_year = ?
                WHERE id = ?
            ");
            $stmt->execute([$first_name, $middle_name, $last_name, $gender, $contact_number, $address, $program_id, $batch_year, $user_id]);
            $success = 'Profile updated successfully!';
            $graduate = $db->query("
                SELECT g.*, p.name as program_name, p.code as program_code
                FROM graduates g
                LEFT JOIN programs p ON g.program_id = p.id
                WHERE g.id = $user_id
            ")->fetch();
            
            $full_name = trim($first_name . ' ' . $middle_name . ' ' . $last_name);
            $_SESSION['user_name'] = $full_name;
            
            send_notification(
                $user_id,
                'Profile Updated',
                'Your profile has been updated successfully.',
                'account',
                'system'
            );
            
            // Switch back to view mode after successful update
            $edit_mode = false;
            
        } catch (PDOException $e) {
            $error = 'Error updating profile: ' . $e->getMessage();
        }
    }
}

// Build full name for display
$full_name = trim($graduate['first_name'] . ' ' . ($graduate['middle_name'] ?? '') . ' ' . $graduate['last_name']);
$full_name = preg_replace('/\s+/', ' ', $full_name); // Remove extra spaces
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile – SLSU GPTS</title>
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
            --radius: 10px;
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
            padding: 1.5rem 2rem;
            background: #f8f9fa;
            min-height: 100vh;
        }
        .page-header {
            margin-bottom: 1.25rem;
        }
        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--white);
        }
        .page-header h1 span {
            color: var(--gold);
        }
        .page-header p {
            color: var(--gray);
            font-size: .85rem;
            margin-top: .2rem;
            font-weight: 300;
        }
        .breadcrumb {
            font-size: .75rem;
            color: var(--gray);
            margin-bottom: .3rem;
        }
        .breadcrumb span {
            color: var(--gold);
        }
        
        /* Layout: Full width for account info */
        .profile-container {
            max-width: 900px;
            margin: 0 auto;
        }
        .card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            margin-bottom: 1.25rem;
        }
        .card-header {
            padding: 0.85rem 1.25rem;
            border-bottom: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255,255,255,0.5);
        }
        .card-title {
            font-weight: 700;
            font-size: .85rem;
            color: var(--white);
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .card-title i {
            color: var(--gold);
            font-size: .9rem;
        }
        .card-body {
            padding: 1.25rem;
        }
        
        /* Account info display */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem 2rem;
        }
        .info-item {
            padding: 0.5rem 0;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            flex-direction: column;
            gap: 0.1rem;
        }
        .info-item .label {
            font-size: .7rem;
            font-weight: 600;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .info-item .value {
            font-size: .9rem;
            color: var(--white);
            font-weight: 500;
        }
        .info-item .value .highlight {
            color: var(--gold);
            font-weight: 700;
        }
        .info-item .value .badge {
            display: inline-block;
            padding: .15rem .6rem;
            border-radius: 50px;
            font-size: .7rem;
            font-weight: 600;
        }
        .badge-success { background: rgba(76,175,80,.15); color: #4CAF50; }
        .badge-warning { background: rgba(251,191,36,.15); color: #D4A00A; }
        .badge-secondary { background: rgba(107,114,128,.15); color: #6B7280; }
        .badge-gold { background: rgba(201,168,76,.2); color: var(--gold); }
        .badge-info { background: rgba(56,189,248,.15); color: #0EA5E9; }
        
        /* Form styling */
        .form-group {
            margin-bottom: 0.75rem;
        }
        .form-group label {
            display: block;
            font-size: .75rem;
            font-weight: 600;
            color: var(--gray);
            margin-bottom: .25rem;
            letter-spacing: .02em;
        }
        .form-group label .required {
            color: var(--red);
        }
        .form-group input, 
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: .6rem .75rem;
            border: 1px solid #e5e7eb;
            border-radius: var(--radius);
            font-size: .85rem;
            background: white;
            color: #1F2937;
            transition: var(--transition);
            font-family: 'DM Sans', sans-serif;
        }
        .form-group input:focus, 
        .form-group select:focus, 
        .form-group textarea:focus {
            border-color: var(--gold);
            outline: none;
            box-shadow: 0 0 0 3px rgba(201,168,76,0.12);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 60px;
        }
        .form-group select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%232E7D32' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right .75rem center;
            padding-right: 2rem;
            cursor: pointer;
        }
        .form-group select optgroup {
            font-weight: 700;
            color: var(--white);
            background: #f0f4f8;
            padding: 6px 10px;
        }
        .form-group select option {
            padding: 4px 10px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem 1rem;
        }
        .full-width {
            grid-column: 1 / -1;
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
            font-size: .85rem;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            font-family: 'DM Sans', sans-serif;
            text-decoration: none;
        }
        .btn:hover {
            background: var(--gold-lt);
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(201,168,76,0.25);
        }
        .btn-back {
            background: #6B7280;
            color: white;
        }
        .btn-back:hover {
            background: #4B5563;
            box-shadow: 0 3px 10px rgba(107,114,128,0.25);
        }
        .btn-edit {
            background: var(--gold);
            color: var(--navy);
            padding: .4rem 1rem;
            font-size: .75rem;
        }
        .btn-group {
            display: flex;
            gap: .75rem;
            flex-wrap: wrap;
            margin-top: .25rem;
        }
        .alert {
            padding: .6rem 1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: .5rem;
            font-size: .85rem;
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
        
        /* Campus badge colors */
        .campus-tag {
            display: inline-block;
            font-size: .6rem;
            padding: 1px 8px;
            border-radius: 50px;
            font-weight: 600;
            margin-left: 4px;
        }
        .campus-tag.tayabas { background: #8E44AD20; color: #8E44AD; }
        .campus-tag.lucena { background: #2980B920; color: #2980B9; }
        .campus-tag.main { background: #C9A84C20; color: #C9A84C; }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main { margin-left: 0; padding: 1.25rem; }
            .info-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
            .full-width { grid-column: 1; }
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
        <a href="profile.php" class="nav-item active"><i class="fas fa-user nav-icon"></i> My Profile</a>
        <a href="messages.php" class="nav-item"><i class="fas fa-envelope nav-icon"></i> Messages <span class="nav-badge <?= $unread_count > 0 ? '' : 'zero' ?>"><?= $unread_count ?></span></a>
        <a href="notifications.php" class="nav-item"><i class="fas fa-bell nav-icon"></i> Notifications <span class="nav-badge <?= $unread_notif_count > 0 ? '' : 'zero' ?>"><?= $unread_notif_count ?></span></a>
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
    <div class="page-header">
        <div class="breadcrumb">SLSU GPTS / <span>My Profile</span></div>
        <h1>My Profile</h1>
        <p>View and update your personal information.</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
    <?php endif; ?>

    <div class="profile-container">
        <!-- Account Information Card (Always Visible) -->
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-info-circle"></i> Account Info</span>
                <?php if (!$edit_mode): ?>
                    <a href="?edit=true" class="btn btn-edit">
                        <i class="fas fa-pencil-alt"></i> Edit Profile
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label">Student ID</span>
                        <span class="value highlight"><?= htmlspecialchars($graduate['student_id']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Full Name</span>
                        <span class="value"><strong><?= htmlspecialchars($full_name) ?></strong></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Email</span>
                        <span class="value"><?= htmlspecialchars($graduate['email']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Gender</span>
                        <span class="value"><?= htmlspecialchars($graduate['gender'] ?? 'Not specified') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Contact Number</span>
                        <span class="value"><?= htmlspecialchars($graduate['contact_number'] ?? 'Not provided') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Program</span>
                        <span class="value"><?= htmlspecialchars($graduate['program_name'] ?? 'N/A') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Program Code</span>
                        <span class="value"><span class="badge badge-secondary"><?= htmlspecialchars($graduate['program_code'] ?? 'N/A') ?></span></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Campus</span>
                        <span class="value"><span class="badge badge-gold"><?= htmlspecialchars($graduate['campus'] ?? 'N/A') ?></span></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Batch Year</span>
                        <span class="value"><?= htmlspecialchars($graduate['batch_year'] ?? 'N/A') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Address</span>
                        <span class="value"><?= htmlspecialchars($graduate['address'] ?? 'Not provided') ?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Status</span>
                        <span class="value"><span class="badge <?= $graduate['is_active'] ? 'badge-success' : 'badge-warning' ?>"><?= $graduate['is_active'] ? 'Active' : 'Pending' ?></span></span>
                    </div>
                    <div class="info-item">
                        <span class="label">Member Since</span>
                        <span class="value"><?= date('M d, Y', strtotime($graduate['created_at'])) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Profile Card (Only Shows in Edit Mode) -->
        <?php if ($edit_mode): ?>
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-user-edit"></i> Edit Profile</span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name <span class="required">*</span></label>
                            <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($graduate['first_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="middle_name">Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name" value="<?= htmlspecialchars($graduate['middle_name'] ?? '') ?>" placeholder="Enter middle name">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="last_name">Last Name <span class="required">*</span></label>
                            <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($graduate['last_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender">
                                <option value="">Select Gender</option>
                                <option value="Male" <?= $graduate['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= $graduate['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                <option value="Other" <?= $graduate['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="contact_number">Contact Number</label>
                            <input type="text" id="contact_number" name="contact_number" placeholder="e.g. 09123456789" value="<?= htmlspecialchars($graduate['contact_number'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="batch_year">Batch Year</label>
                            <select id="batch_year" name="batch_year">
                                <option value="0">Select Year</option>
                                <?php foreach ($years as $y): ?>
                                    <option value="<?= $y ?>" <?= ($graduate['batch_year'] ?? 0) == $y ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="program_id">Program <span class="required">*</span></label>
                            <select id="program_id" name="program_id" required>
                                <option value="0">Select Program</option>
                                <?php foreach ($programs_by_campus as $campus => $campus_programs): ?>
                                    <optgroup label="🏫 <?= htmlspecialchars($campus) ?>">
                                        <?php foreach ($campus_programs as $p): ?>
                                            <option value="<?= $p['id'] ?>" <?= ($graduate['program_id'] ?? 0) == $p['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($p['code'] . ' - ' . $p['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                            <small style="color: var(--gray); font-size: .7rem; margin-top: .25rem;">
                                <i class="fas fa-info-circle"></i> Programs are grouped by campus
                            </small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="address">Address</label>
                            <input type="text" id="address" name="address" placeholder="Enter your address" value="<?= htmlspecialchars($graduate['address'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group full-width">
                            <div class="btn-group">
                                <button type="submit" class="btn"><i class="fas fa-save"></i> Update Profile</button>
                                <a href="profile.php" class="btn btn-back"><i class="fas fa-times"></i> Cancel</a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>