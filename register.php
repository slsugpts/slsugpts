<?php
require_once 'includes/auth.php';

if (is_logged_in()) {
    header('Location: student/dashboard.php');
    exit;
}

$error = '';
$success = '';

// Define campus options
$campuses = [
    'Lucban (Main) Campus' => 'Lucban (Main) Campus',
    'Lucena Campus' => 'Lucena Campus',
    'Tayabas Campus' => 'Tayabas Campus',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Invalid request.';
    } else {
        $student_id = trim($_POST['student_id'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $campus = trim($_POST['campus'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$student_id || !$first_name || !$last_name || !$email || !$campus || !$password) {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif (!array_key_exists($campus, $campuses)) {
            $error = 'Please select a valid campus.';
        } else {
            try {
                $db = db();
                $hashed = hash_password($password);
                $stmt = $db->prepare("INSERT INTO graduates (student_id, first_name, middle_name, last_name, email, campus, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$student_id, $first_name, $middle_name, $last_name, $email, $campus, $hashed]);
                $success = 'Account created! <a href="login.php">Login here</a>';
                audit_log('REGISTER', "New graduate registered: $email");
            } catch (PDOException $e) {
                $error = 'Email or Student ID already exists.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register - SLSU GPTS</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 2rem;
            position: relative;
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
        .register-box {
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(15px);
            padding: 40px;
            border-radius: 12px;
            width: 450px;
            max-width: 100%;
            border: 1px solid rgba(255,255,255,0.1);
            position: relative;
            z-index: 1;
            box-shadow: 0 8px 40px rgba(0,0,0,0.3);
        }
        h1 { 
            color: var(--gold); 
            text-align: center;
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            margin-bottom: .5rem;
        }
        .subtitle {
            text-align: center;
            color: rgba(255,255,255,0.8);
            font-size: .9rem;
            margin-bottom: 2rem;
        }
        input, select {
            width: 100%;
            padding: 12px 16px;
            margin: 8px 0;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            color: #ffffff;
            border-radius: 8px;
            box-sizing: border-box;
            font-family: 'DM Sans', sans-serif;
            font-size: .9rem;
            transition: var(--transition);
        }
        input:focus, select:focus {
            border-color: var(--gold);
            outline: none;
            box-shadow: 0 0 0 3px rgba(201,168,76,.15);
        }
        input::placeholder {
            color: rgba(255,255,255,0.4);
        }
        select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23C9A84C' stroke-width='2' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            cursor: pointer;
        }
        select option {
            background: #1B5E20;
            color: #ffffff;
        }
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--gold), var(--gold-lt));
            color: var(--navy);
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            font-size: 1rem;
            font-family: 'DM Sans', sans-serif;
            transition: var(--transition);
            margin-top: .5rem;
        }
        button:hover {
            background: var(--gold-lt);
            transform: translateY(-2px);
        }
        .error {
            color: #ff6b6b;
            text-align: center;
            padding: .8rem;
            background: rgba(224,86,86,.2);
            border-radius: 8px;
            margin: .5rem 0;
        }
        .success {
            color: #81c784;
            text-align: center;
            padding: .8rem;
            background: rgba(76,175,80,.2);
            border-radius: 8px;
            margin: .5rem 0;
        }
        .success a {
            color: var(--gold);
            font-weight: 600;
        }
        .text-center {
            text-align: center;
            margin-top: 1rem;
            color: rgba(255,255,255,0.7);
        }
        .text-center a {
            color: var(--gold);
            text-decoration: none;
            font-weight: 600;
        }
        .text-center a:hover {
            text-decoration: underline;
        }
        .back-link {
            display: inline-block;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-size: .85rem;
            margin-bottom: 1rem;
            transition: var(--transition);
        }
        .back-link:hover {
            color: #ffffff;
        }
        .back-link i {
            margin-right: .3rem;
        }

        .form-group {
            margin-bottom: 0.5rem;
        }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 500;
            color: rgba(255,255,255,0.8);
            margin-bottom: 2px;
        }

        .name-row {
            display: flex;
            gap: 10px;
        }
        .name-row .form-group {
            flex: 1;
        }

        @media (max-width: 480px) {
            .register-box {
                padding: 24px;
            }
            .name-row {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <div class="register-box">
        <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back</a>
        <h1>Register</h1>
        <p class="subtitle">Create your SLSU GPTS account</p>

        <?php if ($error): ?>
            <div class="error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            
            <div class="form-group">
                <input type="text" name="student_id" placeholder="Student ID" required value="<?= htmlspecialchars($_POST['student_id'] ?? '') ?>">
            </div>
            
            <div class="name-row">
                <div class="form-group">
                    <input type="text" name="first_name" placeholder="First Name" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <input type="text" name="middle_name" placeholder="Middle Name" value="<?= htmlspecialchars($_POST['middle_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <input type="text" name="last_name" placeholder="Last Name" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-group">
                <input type="email" name="email" placeholder="Email Address" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            
            <div class="form-group">
                <select name="campus" required>
                    <option value="">Select Campus</option>
                    <?php foreach ($campuses as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value) ?>" <?= (isset($_POST['campus']) && $_POST['campus'] === $value) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <input type="password" name="password" placeholder="Password (min 8 characters)" required>
            </div>
            
            <div class="form-group">
                <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            </div>
            
            <button type="submit"><i class="fas fa-user-plus"></i> Create Account</button>
        </form>

        <div class="text-center">
            Already have an account? <a href="login.php">Sign in</a>
        </div>
    </div>
</body>
</html>