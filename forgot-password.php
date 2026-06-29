<?php
// ============================================================
// forgot-password.php – Password Reset Page
// ============================================================
require_once 'includes/auth.php';

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    $dest = is_admin() ? 'admin/dashboard.php' : 'student/dashboard.php';
    header("Location: $dest");
    exit;
}

$error = '';
$success = '';
$reset_done = false;

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($email) || empty($new_password) || empty($confirm_password)) {
            $error = 'Please fill in all fields.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            // Check if email exists
            $grad = find_graduate_by_email($email);
            $admin = find_admin_by_email($email);
            
            if ($grad) {
                // Update graduate password
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $updated = update_graduate_password($email, $hashed);
                if ($updated) {
                    audit_log('PASSWORD_RESET', 'Graduate password reset for: ' . $email);
                    $success = '✅ Password reset successful! Your account has been unlocked.';
                    $reset_done = true;
                    $_POST = [];
                } else {
                    $error = 'Failed to update password. Please try again.';
                }
            } elseif ($admin) {
                // Update admin password
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $updated = update_admin_password($email, $hashed);
                if ($updated) {
                    audit_log('PASSWORD_RESET', 'Admin password reset for: ' . $email);
                    $success = '✅ Password reset successful! Your account has been unlocked.';
                    $reset_done = true;
                    $_POST = [];
                } else {
                    $error = 'Failed to update password. Please try again.';
                }
            } else {
                $error = '❌ Email address not found. Please check and try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $reset_done ? 'Password Reset Successful' : 'Reset Password' ?> – SLSU GPTS</title>
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
            --radius: 12px;
            --transition: .3s ease;
        }
        body {
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            position: relative;
            overflow: hidden;
            background-image: url('slsubacks.jpg');
            background-size: cover;
            background-position: center center;
            background-attachment: fixed;
            background-repeat: no-repeat;
        }
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: rgba(27, 94, 32, 0.75);
            z-index: 0;
            pointer-events: none;
        }
        .login-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 100vh;
            width: 100%;
            position: relative;
            z-index: 1;
        }
        .login-left {
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(15px);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 4rem;
            border-right: 1px solid rgba(255,255,255,0.1);
            position: relative;
            overflow: hidden;
        }
        .brand-area { position: relative; }
        .logo-mark {
            width: 70px; height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--gold), var(--gold-lt));
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 900;
            color: var(--navy);
            margin-bottom: 1.5rem;
            box-shadow: 0 8px 30px rgba(201,168,76,.3);
        }
        .brand-area h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 900;
            color: #ffffff;
            line-height: 1.15;
            margin-bottom: .5rem;
            text-shadow: 0 2px 20px rgba(0,0,0,0.3);
        }
        .brand-area h1 span { color: var(--gold); }
        .brand-area p {
            color: rgba(255,255,255,0.85);
            font-size: .9rem;
            font-weight: 300;
            line-height: 1.7;
            max-width: 340px;
            margin-bottom: 2.5rem;
        }
        .feature-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: .85rem;
        }
        .feature-list li {
            display: flex;
            align-items: center;
            gap: .85rem;
            color: rgba(255,255,255,0.85);
            font-size: .88rem;
        }
        .feature-list li i {
            width: 32px; height: 32px;
            border-radius: 8px;
            background: rgba(201,168,76,.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gold);
            font-size: .8rem;
            flex-shrink: 0;
        }
        .back-link {
            margin-top: auto;
            padding-top: 3rem;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-size: .85rem;
            transition: color var(--transition);
        }
        .back-link:hover { color: #ffffff; }
        .login-right {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 4rem;
            background: rgba(255,255,255,0.06);
            backdrop-filter: blur(15px);
            animation: slideIn .5s ease both;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(30px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .form-header { margin-bottom: 2rem; }
        .form-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.75rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: .35rem;
            text-shadow: 0 2px 20px rgba(0,0,0,0.2);
        }
        .form-header h2 i {
            color: var(--gold);
        }
        .form-header p {
            color: rgba(255,255,255,0.8);
            font-size: .9rem;
            font-weight: 300;
        }
        .alert {
            padding: .9rem 1.2rem;
            border-radius: var(--radius);
            font-size: .88rem;
            display: flex;
            align-items: center;
            gap: .6rem;
            margin-bottom: 1.5rem;
        }
        .alert-error {
            background: rgba(224,86,86,.2);
            border: 1px solid rgba(224,86,86,.3);
            color: #ff6b6b;
        }
        .alert-success {
            background: rgba(76,175,80,.2);
            border: 1px solid rgba(76,175,80,.3);
            color: #81c784;
        }
        .form-group { margin-bottom: 1.35rem; }
        .form-label {
            display: block;
            font-size: .82rem;
            font-weight: 600;
            color: rgba(255,255,255,0.8);
            letter-spacing: .03em;
            margin-bottom: .5rem;
        }
        .input-wrap { position: relative; }
        .input-wrap i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.5);
            font-size: .9rem;
            pointer-events: none;
        }
        .form-control {
            width: 100%;
            padding: .85rem 1rem .85rem 2.8rem;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: var(--radius);
            color: #ffffff;
            font-family: 'DM Sans', sans-serif;
            font-size: .95rem;
            transition: var(--transition);
            outline: none;
        }
        .form-control:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(201,168,76,.15);
        }
        .form-control::placeholder { color: rgba(255,255,255,0.4); }
        .form-control[readonly] {
            opacity: 0.7;
            cursor: not-allowed;
        }
        .btn-primary {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--gold), var(--gold-lt));
            color: var(--navy);
            border: none;
            border-radius: var(--radius);
            font-family: 'DM Sans', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .6rem;
            box-shadow: 0 6px 24px rgba(201,168,76,.3);
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 32px rgba(201,168,76,.4);
        }
        .btn-primary:active { transform: translateY(0); }
        .btn-secondary {
            width: 100%;
            padding: 1rem;
            background: transparent;
            color: rgba(255,255,255,0.7);
            border: 2px solid rgba(255,255,255,0.15);
            border-radius: var(--radius);
            font-family: 'DM Sans', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .6rem;
            text-decoration: none;
            text-align: center;
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.08);
            border-color: var(--gold);
            color: #ffffff;
        }
        .password-hint {
            font-size: .78rem;
            color: rgba(255,255,255,0.6);
            margin-top: .4rem;
            padding-left: .5rem;
        }
        .password-hint i {
            color: var(--gold);
        }
        /* Success Page Styles */
        .success-page {
            text-align: center;
            padding: 2rem 0;
        }
        .success-page .success-icon {
            font-size: 4rem;
            color: var(--green);
            margin-bottom: 1rem;
            animation: bounceIn 0.6s ease;
        }
        @keyframes bounceIn {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            70% { transform: scale(0.9); }
            100% { transform: scale(1); }
        }
        .success-page h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            color: #ffffff;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 20px rgba(0,0,0,0.2);
        }
        .success-page p {
            color: rgba(255,255,255,0.8);
            font-size: 1rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        .success-page p i {
            color: var(--gold);
        }
        .success-page .btn-primary {
            max-width: 300px;
            margin: 0 auto;
        }
        @media (max-width: 900px) {
            .login-wrapper { grid-template-columns: 1fr; }
            .login-left { display: none; }
            .login-right { padding: 2.5rem 2rem; }
        }
        @media (max-width: 480px) {
            .login-right { padding: 1.5rem; }
        }
    </style>
</head>
<body>
<div class="login-wrapper">
    <div class="login-left">
        <div class="brand-area">
            <div class="logo-mark">S</div>
            <h1>SLSU <span>GPTS</span></h1>
            <p>Graduate Profiling &amp; Tracer System<br>Southern Luzon State University – Lucena Campus</p>
            <ul class="feature-list">
                <li><i class="fas fa-id-card"></i> Manage your graduate profile</li>
                <li><i class="fas fa-poll"></i> Complete annual tracer surveys</li>
                <li><i class="fas fa-bell"></i> Receive automated notifications</li>
                <li><i class="fas fa-chart-line"></i> Track employment outcomes</li>
                <li><i class="fas fa-lock"></i> Secure, role-based access</li>
            </ul>
        </div>
        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>
    </div>
    <div class="login-right">
        <div style="max-width:420px; width:100%; margin:0 auto;">
            
            <?php if (!$reset_done): ?>
            
            <!-- RESET PASSWORD FORM -->
            <div class="form-header">
                <h2><i class="fas fa-key"></i> Reset Password</h2>
                <p>Enter your email and new password to reset your account.</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST" novalidate>
                <?= csrf_field() ?>
                
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <div class="input-wrap">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" class="form-control" placeholder="your@email.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="new_password">New Password</label>
                    <div class="input-wrap">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Enter new password (min 6 chars)" required>
                    </div>
                    <div class="password-hint"><i class="fas fa-info-circle"></i> Minimum 6 characters</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password</label>
                    <div class="input-wrap">
                        <i class="fas fa-check-circle"></i>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-enter new password" required>
                    </div>
                </div>
                
                <button type="submit" class="btn-primary"><i class="fas fa-sync-alt"></i> Reset Password</button>
            </form>
            
            <br>
            
            <a href="login.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Back to Login</a>
            
            <?php else: ?>
            
            <!-- SUCCESS PAGE -->
            <div class="success-page">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2>Password Reset Successful!</h2>
                <p>
                    <i class="fas fa-unlock-alt"></i> 
                    Your account has been unlocked.<br>
                    You can now log in with your new password.
                </p>
                <a href="login.php" class="btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Back to Login
                </a>
            </div>
            
            <?php endif; ?>
            
        </div>
    </div>
</div>
</body>
</html>