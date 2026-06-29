<?php
// ============================================================
// login.php – Login Page
// ============================================================
require_once 'includes/auth.php';

if (is_logged_in()) {
    $dest = is_admin() ? 'admin/dashboard.php' : 'student/dashboard.php';
    header("Location: $dest");
    exit;
}

$role = $_GET['role'] ?? 'student';
$error = '';
$success = '';

if (isset($_GET['registered'])) {
    $success = 'Account created successfully! Please login.';
}

if (isset($_GET['error'])) {
    if ($_GET['error'] === 'unauthorized') {
        $error = 'Please login to access this page.';
    } else {
        $error = 'Invalid credentials.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $form_role = $_POST['role'] ?? 'student';
        $remember = isset($_POST['remember']);

        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            if ($form_role === 'admin') {
                $admin = find_admin_by_email($email);
                if ($admin && verify_password($password, $admin['password'])) {
                    login_admin($admin);
                    audit_log('LOGIN', 'Admin logged in');
                    $redirect = $_SESSION['redirect_after_login'] ?? BASE_URL . '/admin/dashboard.php';
                    unset($_SESSION['redirect_after_login']);
                    header("Location: $redirect");
                    exit;
                } else {
                    $error = 'Invalid email or password.';
                }
            } else {
                $grad = find_graduate_by_email($email);
                if ($grad && verify_password($password, $grad['password'])) {
                    login_graduate($grad);
                    audit_log('LOGIN', 'Graduate logged in');
                    $redirect = $_SESSION['redirect_after_login'] ?? BASE_URL . '/student/dashboard.php';
                    unset($_SESSION['redirect_after_login']);
                    header("Location: $redirect");
                    exit;
                } else {
                    $error = 'Invalid email or password.';
                }
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
    <title>Login – SLSU GPTS</title>
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
        .role-tabs {
            display: flex;
            gap: 0;
            background: rgba(255,255,255,0.1);
            border-radius: 50px;
            padding: .25rem;
            margin-bottom: 2.5rem;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .role-tab {
            flex: 1;
            text-align: center;
            padding: .65rem 1.25rem;
            border-radius: 50px;
            cursor: pointer;
            font-size: .88rem;
            font-weight: 500;
            color: rgba(255,255,255,0.7);
            transition: var(--transition);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
        }
        .role-tab.active {
            background: linear-gradient(135deg, var(--gold), var(--gold-lt));
            color: var(--navy);
            font-weight: 700;
        }
        .role-tab:hover:not(.active) {
            color: #ffffff;
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
        .form-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.75rem;
            gap: 1rem;
        }
        .checkbox-wrap {
            display: flex;
            align-items: center;
            gap: .5rem;
            cursor: pointer;
        }
        .checkbox-wrap input {
            accent-color: var(--gold);
            width: 15px;
            height: 15px;
            cursor: pointer;
        }
        .checkbox-wrap span {
            font-size: .84rem;
            color: rgba(255,255,255,0.7);
        }
        .forgot-link {
            font-size: .84rem;
            color: var(--gold);
            text-decoration: none;
            white-space: nowrap;
        }
        .forgot-link:hover { text-decoration: underline; }
        .btn-login {
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
        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 32px rgba(201,168,76,.4);
        }
        .btn-login:active { transform: translateY(0); }
        .divider {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 1.5rem 0;
            color: rgba(255,255,255,0.3);
            font-size: .8rem;
        }
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255,255,255,0.2);
        }
        .register-link {
            text-align: center;
            font-size: .88rem;
            color: rgba(255,255,255,0.7);
        }
        .register-link a {
            color: var(--gold);
            font-weight: 600;
            text-decoration: none;
        }
        .register-link a:hover { text-decoration: underline; }
        @media (max-width: 900px) {
            .login-wrapper { grid-template-columns: 1fr; }
            .login-left { display: none; }
            .login-right { padding: 2.5rem 2rem; }
        }
        @media (max-width: 480px) {
            .login-right { padding: 1.5rem; }
            .role-tabs { flex-direction: column; border-radius: 12px; gap: 4px; }
            .role-tab { border-radius: 8px; }
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
            <div class="role-tabs">
                <a href="login.php?role=student" class="role-tab <?= $role === 'student' ? 'active' : '' ?>"><i class="fas fa-user-graduate"></i> Graduate</a>
                <a href="login.php?role=admin" class="role-tab <?= $role === 'admin' ? 'active' : '' ?>"><i class="fas fa-user-shield"></i> Admin</a>
            </div>
            <div class="form-header">
                <h2><?= $role === 'admin' ? 'Admin Login' : 'Graduate Login' ?></h2>
                <p><?= $role === 'admin' ? 'Access the administration and management panel.' : 'Sign in to your graduate profile and survey portal.' ?></p>
            </div>
            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <form method="POST" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="role" value="<?= $role ?>">
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <div class="input-wrap">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" class="form-control" placeholder="you@example.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrap">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
                    </div>
                </div>
                <div class="form-footer">
                    <label class="checkbox-wrap"><input type="checkbox" name="remember"><span>Remember me</span></label>
                    <a href="forgot-password.php" class="forgot-link">Forgot password?</a>
                </div>
                <button type="submit" class="btn-login"><i class="fas fa-sign-in-alt"></i> Sign In as <?= $role === 'admin' ? 'Admin' : 'Graduate' ?></button>
            </form>
            <?php if ($role === 'student'): ?>
                <div class="divider">or</div>
                <p class="register-link">Don't have an account? <a href="register.php">Create one now</a></p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>