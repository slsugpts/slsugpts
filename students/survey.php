<?php
require_once __DIR__ . '/../includes/auth.php';
require_graduate();

$db = db();
$user_id = $_SESSION['user_id'];

// Check if already submitted
$existing = $db->query("SELECT * FROM tracer_records WHERE graduate_id = $user_id ORDER BY submitted_at DESC LIMIT 1")->fetch();

// Get unread message count for badge
$unread_count = $db->query("SELECT COUNT(*) FROM notifications WHERE recipient_type = 'graduate' AND recipient_id = $user_id AND is_read = 0 AND sender_type = 'admin'")->fetchColumn();

// Get unread notification count for badge (system notifications only)
$unread_notif_count = $db->query("SELECT COUNT(*) FROM notifications WHERE recipient_type = 'graduate' AND recipient_id = $user_id AND is_read = 0 AND (sender_type IS NULL OR sender_type = 'system')")->fetchColumn();

$error = '';
$success = '';
$is_edit = isset($_GET['edit']) && $_GET['edit'] == 1 && $existing;

// Handle update (edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_survey']) && $existing) {
    $employment_status = $_POST['employment_status'] ?? '';
    $occupation = trim($_POST['occupation'] ?? '');
    $employer_name = trim($_POST['employer_name'] ?? '');
    $employer_address = trim($_POST['employer_address'] ?? '');
    $monthly_salary_range = $_POST['monthly_salary_range'] ?? '';
    $job_relevance = $_POST['job_relevance'] ?? '';
    $work_arrangement = $_POST['work_arrangement'] ?? '';

    try {
        $stmt = $db->prepare("
            UPDATE tracer_records SET 
                employment_status = ?, occupation = ?, employer_name = ?, employer_address = ?,
                monthly_salary_range = ?, job_relevance = ?, work_arrangement = ?
            WHERE graduate_id = ? AND id = ?
        ");
        $stmt->execute([
            $employment_status, $occupation, $employer_name, $employer_address,
            $monthly_salary_range, $job_relevance, $work_arrangement,
            $user_id, $existing['id']
        ]);
        $success = 'Survey updated successfully!';
        audit_log('SURVEY_UPDATE', "Graduate #$user_id updated tracer survey");
        
        send_notification(
            $user_id,
            'Survey Updated',
            'Your tracer survey has been updated successfully.',
            'survey',
            'system'
        );
        
        $existing = $db->query("SELECT * FROM tracer_records WHERE graduate_id = $user_id ORDER BY submitted_at DESC LIMIT 1")->fetch();
        $is_edit = false;
    } catch (PDOException $e) {
        $error = 'Error updating survey: ' . $e->getMessage();
    }
}

// Handle new submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_survey']) && !$existing) {
    $employment_status = $_POST['employment_status'] ?? '';
    $occupation = trim($_POST['occupation'] ?? '');
    $employer_name = trim($_POST['employer_name'] ?? '');
    $employer_address = trim($_POST['employer_address'] ?? '');
    $monthly_salary_range = $_POST['monthly_salary_range'] ?? '';
    $job_relevance = $_POST['job_relevance'] ?? '';
    $work_arrangement = $_POST['work_arrangement'] ?? '';
    $survey_year = date('Y');

    try {
        $stmt = $db->prepare("
            INSERT INTO tracer_records (
                graduate_id, survey_year, employment_status, occupation,
                employer_name, employer_address, monthly_salary_range,
                job_relevance, work_arrangement
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id, $survey_year, $employment_status, $occupation,
            $employer_name, $employer_address, $monthly_salary_range,
            $job_relevance, $work_arrangement
        ]);

        $success = 'Survey submitted successfully! Thank you for your response.';
        audit_log('SURVEY', "Graduate #$user_id submitted tracer survey");
        
        send_notification(
            $user_id,
            'Survey Submitted',
            'Your tracer survey has been submitted successfully! Thank you for your response.',
            'survey',
            'system'
        );
        
        $existing = $db->query("SELECT * FROM tracer_records WHERE graduate_id = $user_id ORDER BY submitted_at DESC LIMIT 1")->fetch();
    } catch (PDOException $e) {
        $error = 'Error submitting survey: ' . $e->getMessage();
    }
}

function selected($field, $value, $data) {
    return ($data[$field] ?? '') == $value ? 'selected' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracer Survey – SLSU GPTS</title>
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
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .card:last-child {
            margin-bottom: 0;
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
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: .3rem;
        }
        .form-group label {
            font-size: .8rem;
            font-weight: 600;
            color: var(--gray);
            display: flex;
            align-items: center;
            gap: .3rem;
        }
        .form-group label .required {
            color: var(--red);
            font-weight: 700;
        }
        .form-group input, .form-group select, .form-group textarea {
            padding: .7rem 1rem;
            border: 1px solid #e5e7eb;
            border-radius: var(--radius);
            font-size: .9rem;
            background: white;
            color: #1F2937;
            transition: var(--transition);
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: var(--gold);
            outline: none;
            box-shadow: 0 0 0 3px rgba(201,168,76,0.15);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 60px;
        }
        .form-group select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%232E7D32' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            padding-right: 2.5rem;
        }
        .full-width {
            grid-column: 1 / -1;
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
        .btn-outline {
            background: transparent;
            border: 2px solid var(--gold);
            color: var(--gold);
        }
        .btn-outline:hover {
            background: var(--gold);
            color: var(--navy);
            box-shadow: 0 4px 12px rgba(201,168,76,0.3);
        }
        .btn-danger-outline {
            background: transparent;
            border: 2px solid var(--red);
            color: var(--red);
        }
        .btn-danger-outline:hover {
            background: var(--red);
            color: white;
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
        .alert-info {
            background: rgba(56,189,248,.15);
            border: 1px solid rgba(56,189,248,.3);
            color: var(--blue);
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
        .badge-info { background: rgba(56,189,248,.15); color: var(--blue); }
        .badge-secondary { background: rgba(107,114,128,.15); color: #6B7280; }
        .badge-gold { background: rgba(201,168,76,.2); color: var(--gold); }
        .info-item {
            padding: .5rem 0;
            border-bottom: 1px solid var(--card-border);
            color: var(--white);
            display: flex;
            flex-wrap: wrap;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-item strong {
            display: inline-block;
            width: 160px;
            color: var(--gray);
            font-weight: 500;
            flex-shrink: 0;
        }
        .info-item .value {
            color: var(--white);
        }
        .info-item .highlight {
            color: var(--gold);
            font-weight: 600;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .25rem 1rem;
        }
        .info-grid .info-item {
            border-bottom: none;
            padding: .3rem 0;
        }
        .section-divider {
            border-top: 2px solid var(--card-border);
            padding-top: 1.5rem;
            margin-top: .5rem;
        }
        .section-title {
            font-size: .85rem;
            font-weight: 700;
            color: var(--white);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .section-title i {
            color: var(--gold);
        }
        .btn-group {
            display: flex;
            gap: .75rem;
            flex-wrap: wrap;
        }
        .submission-date {
            color: var(--gold);
            font-weight: 600;
        }
        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); }
            .main { margin-left: 0; padding: 1.25rem; }
            .form-grid { grid-template-columns: 1fr; }
            .full-width { grid-column: 1; }
            .info-grid { grid-template-columns: 1fr; }
            .info-item strong { width: 130px; }
            .btn-group { flex-direction: column; }
            .btn-group .btn { justify-content: center; }
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
        <a href="survey.php" class="nav-item active"><i class="fas fa-poll nav-icon"></i> Tracer Survey</a>
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
        <div class="breadcrumb">SLSU GPTS / <span>Tracer Survey</span></div>
        <h1>Graduate Tracer Survey</h1>
        <p>Help us track graduate outcomes by completing this survey.</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
    <?php endif; ?>

    <?php if ($existing && !$is_edit): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>✅ Survey Already Completed</strong>
                <p style="margin-top:.3rem;">You submitted your tracer survey on <span class="submission-date"><?= date('M d, Y h:i A', strtotime($existing['submitted_at'])) ?></span>.</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fas fa-eye"></i> Your Submission</span>
                <button class="btn btn-outline" onclick="window.location.href='?edit=1'" style="padding:.4rem 1rem;font-size:.8rem;">
                    <i class="fas fa-edit"></i> Edit Survey
                </button>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item"><strong>Employment Status:</strong> <span class="value"><span class="badge badge-success"><?= htmlspecialchars($existing['employment_status'] ?? 'N/A') ?></span></span></div>
                    <div class="info-item"><strong>Occupation:</strong> <span class="value"><?= htmlspecialchars($existing['occupation'] ?? 'N/A') ?></span></div>
                    <div class="info-item"><strong>Employer Name:</strong> <span class="value"><?= htmlspecialchars($existing['employer_name'] ?? 'N/A') ?></span></div>
                    <div class="info-item"><strong>Employer Address:</strong> <span class="value"><?= htmlspecialchars($existing['employer_address'] ?? 'N/A') ?></span></div>
                    <div class="info-item"><strong>Salary Range:</strong> <span class="value"><span class="badge badge-gold"><?= htmlspecialchars($existing['monthly_salary_range'] ?? 'N/A') ?></span></span></div>
                    <div class="info-item"><strong>Work Arrangement:</strong> <span class="value"><span class="badge badge-info"><?= htmlspecialchars($existing['work_arrangement'] ?? 'N/A') ?></span></span></div>
                    <div class="info-item"><strong>Job Relevance:</strong> <span class="value"><span class="badge badge-secondary"><?= htmlspecialchars($existing['job_relevance'] ?? 'N/A') ?></span></span></div>
                    <div class="info-item"><strong>Submitted:</strong> <span class="value highlight"><?= date('M d, Y h:i A', strtotime($existing['submitted_at'])) ?></span></div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    <i class="fas fa-<?= $is_edit ? 'edit' : 'pencil-alt' ?>"></i>
                    <?= $is_edit ? 'Edit Tracer Survey' : 'Tracer Survey Form' ?>
                </span>
                <?php if ($is_edit): ?>
                    <a href="survey.php" class="btn btn-danger-outline" style="padding:.4rem 1rem;font-size:.8rem;">
                        <i class="fas fa-times"></i> Cancel Edit
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="survey_id" value="<?= $existing['id'] ?? 0 ?>">

                    <div class="section-title"><i class="fas fa-user"></i> Employment Status</div>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label for="employment_status">Employment Status <span class="required">*</span></label>
                            <select id="employment_status" name="employment_status" required>
                                <option value="">Select Status</option>
                                <option value="Employed" <?= selected('employment_status', 'Employed', $existing ?? []) ?>>Employed</option>
                                <option value="Self-employed" <?= selected('employment_status', 'Self-employed', $existing ?? []) ?>>Self-employed</option>
                                <option value="Unemployed" <?= selected('employment_status', 'Unemployed', $existing ?? []) ?>>Unemployed</option>
                                <option value="Freelancer" <?= selected('employment_status', 'Freelancer', $existing ?? []) ?>>Freelancer</option>
                                <option value="Contractual" <?= selected('employment_status', 'Contractual', $existing ?? []) ?>>Contractual</option>
                                <option value="Part-time" <?= selected('employment_status', 'Part-time', $existing ?? []) ?>>Part-time</option>
                                <option value="Not in labor force" <?= selected('employment_status', 'Not in labor force', $existing ?? []) ?>>Not in labor force</option>
                            </select>
                        </div>
                    </div>

                    <div class="section-divider">
                        <div class="section-title"><i class="fas fa-briefcase"></i> Job Details</div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="occupation">Occupation / Job Title</label>
                            <input type="text" id="occupation" name="occupation" placeholder="e.g. Software Engineer" value="<?= htmlspecialchars($existing['occupation'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="employer_name">Employer Name</label>
                            <input type="text" id="employer_name" name="employer_name" placeholder="e.g. Company Name" value="<?= htmlspecialchars($existing['employer_name'] ?? '') ?>">
                        </div>
                        <div class="form-group full-width">
                            <label for="employer_address">Employer Address</label>
                            <input type="text" id="employer_address" name="employer_address" placeholder="Company address" value="<?= htmlspecialchars($existing['employer_address'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="section-divider">
                        <div class="section-title"><i class="fas fa-money-bill"></i> Compensation & Arrangement</div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="monthly_salary_range">Monthly Salary Range</label>
                            <select id="monthly_salary_range" name="monthly_salary_range">
                                <option value="">Select Range</option>
                                <?php foreach (get_salary_ranges() as $range): ?>
                                    <option value="<?= $range ?>" <?= selected('monthly_salary_range', $range, $existing ?? []) ?>><?= $range ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="work_arrangement">Work Arrangement</label>
                            <select id="work_arrangement" name="work_arrangement">
                                <option value="">Select Arrangement</option>
                                <?php foreach (get_work_arrangements() as $arr): ?>
                                    <option value="<?= $arr ?>" <?= selected('work_arrangement', $arr, $existing ?? []) ?>><?= $arr ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="section-divider">
                        <div class="section-title"><i class="fas fa-graduation-cap"></i> Job Relevance</div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label for="job_relevance">Job Relevance to Degree</label>
                            <select id="job_relevance" name="job_relevance">
                                <option value="">Select Relevance</option>
                                <?php foreach (get_job_relevance_options() as $option): ?>
                                    <option value="<?= $option ?>" <?= selected('job_relevance', $option, $existing ?? []) ?>><?= $option ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="section-divider">
                        <div class="btn-group" style="margin-top:.5rem;">
                            <?php if ($is_edit): ?>
                                <button type="submit" name="update_survey" class="btn btn-success"><i class="fas fa-save"></i> Update Survey</button>
                                <a href="survey.php" class="btn btn-outline"><i class="fas fa-times"></i> Cancel</a>
                            <?php else: ?>
                                <button type="submit" name="submit_survey" class="btn btn-success"><i class="fas fa-paper-plane"></i> Submit Survey</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</main>
</body>
</html>