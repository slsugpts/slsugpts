<?php
// ============================================================
// setup.php – Complete Database Setup
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';

echo "<h2>SLSU GPTS - Database Setup</h2>";

try {
    // Create database
    $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=" . DB_CHAR, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    echo "<p style='color:green'>✅ Database created</p>";
    
    // Connect to database
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHAR, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Drop existing tables for clean install
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $tables = ['audit_logs', 'tracer_records', 'notifications', 'graduates', 'programs', 'colleges', 'admins'];
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS $table");
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "<p style='color:orange'>⚠️ Clean install started</p>";

    // Create tables
    $pdo->exec("CREATE TABLE admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(50) DEFAULT 'admin',
        is_active TINYINT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<p style='color:green'>✅ admins table created</p>";

    $pdo->exec("CREATE TABLE colleges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) UNIQUE NOT NULL,
        code VARCHAR(20) UNIQUE NOT NULL,
        is_active TINYINT DEFAULT 1
    )");
    echo "<p style='color:green'>✅ colleges table created</p>";

    $pdo->exec("CREATE TABLE programs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        college_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        code VARCHAR(20) UNIQUE NOT NULL,
        is_active TINYINT DEFAULT 1,
        FOREIGN KEY(college_id) REFERENCES colleges(id) ON DELETE CASCADE
    )");
    echo "<p style='color:green'>✅ programs table created</p>";

    $pdo->exec("CREATE TABLE graduates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(50) UNIQUE NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        middle_name VARCHAR(100),
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        program_id INT,
        batch_year INT,
        gender VARCHAR(20),
        contact_number VARCHAR(20),
        address TEXT,
        profile_image VARCHAR(255),
        is_active TINYINT DEFAULT 1,
        email_verified TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY(program_id) REFERENCES programs(id) ON DELETE SET NULL
    )");
    echo "<p style='color:green'>✅ graduates table created</p>";

    $pdo->exec("CREATE TABLE tracer_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        graduate_id INT NOT NULL,
        survey_year INT NOT NULL,
        employment_status VARCHAR(50),
        occupation VARCHAR(100),
        employer_name VARCHAR(100),
        employer_address VARCHAR(255),
        monthly_salary_range VARCHAR(50),
        is_employed TINYINT DEFAULT 0,
        job_relevance VARCHAR(50),
        work_arrangement VARCHAR(50),
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(graduate_id) REFERENCES graduates(id) ON DELETE CASCADE
    )");
    echo "<p style='color:green'>✅ tracer_records table created</p>";

    $pdo->exec("CREATE TABLE notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recipient_type VARCHAR(20) NOT NULL,
        recipient_id INT NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type VARCHAR(50) DEFAULT 'general',
        is_read TINYINT DEFAULT 0,
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<p style='color:green'>✅ notifications table created</p>";

    $pdo->exec("CREATE TABLE audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_type VARCHAR(20) NOT NULL,
        user_id INT NOT NULL,
        action VARCHAR(50) NOT NULL,
        description TEXT,
        ip_address VARCHAR(45),
        user_agent VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<p style='color:green'>✅ audit_logs table created</p>";

    $pdo->exec("CREATE TABLE system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(50) UNIQUE NOT NULL,
        setting_value TEXT,
        setting_group VARCHAR(50) DEFAULT 'general',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "<p style='color:green'>✅ system_settings table created</p>";

    // Insert sample colleges
    $pdo->exec("INSERT INTO colleges (name, code) VALUES 
        ('College of Engineering', 'COE'),
        ('College of Education', 'COED'),
        ('College of Arts and Sciences', 'CAS'),
        ('College of Business and Accountancy', 'CBA'),
        ('College of Nursing', 'CON'),
        ('College of Agriculture', 'CA'),
        ('College of Computer Studies', 'CCS'),
        ('College of Criminal Justice', 'CCJ')
    ");
    echo "<p style='color:green'>✅ Colleges inserted</p>";

    // Get college IDs
    $stmt = $pdo->query("SELECT id FROM colleges WHERE code = 'COE'");
    $coe_id = $stmt->fetchColumn();
    $stmt = $pdo->query("SELECT id FROM colleges WHERE code = 'COED'");
    $coed_id = $stmt->fetchColumn();
    $stmt = $pdo->query("SELECT id FROM colleges WHERE code = 'CAS'");
    $cas_id = $stmt->fetchColumn();
    $stmt = $pdo->query("SELECT id FROM colleges WHERE code = 'CBA'");
    $cba_id = $stmt->fetchColumn();
    $stmt = $pdo->query("SELECT id FROM colleges WHERE code = 'CCS'");
    $ccs_id = $stmt->fetchColumn();

    // Insert sample programs
    $pdo->exec("INSERT INTO programs (college_id, name, code) VALUES 
        ($coe_id, 'Bachelor of Science in Computer Engineering', 'BSCpE'),
        ($coe_id, 'Bachelor of Science in Electrical Engineering', 'BSEE'),
        ($coe_id, 'Bachelor of Science in Civil Engineering', 'BSCE'),
        ($coe_id, 'Bachelor of Science in Mechanical Engineering', 'BSME'),
        ($coed_id, 'Bachelor of Secondary Education', 'BSEd'),
        ($coed_id, 'Bachelor of Elementary Education', 'BEEd'),
        ($coed_id, 'Bachelor of Physical Education', 'BPEd'),
        ($cas_id, 'Bachelor of Science in Biology', 'BSBio'),
        ($cas_id, 'Bachelor of Science in Mathematics', 'BSMath'),
        ($cas_id, 'Bachelor of Arts in English', 'BAEng'),
        ($cas_id, 'Bachelor of Science in Psychology', 'BSPsych'),
        ($cba_id, 'Bachelor of Science in Business Administration', 'BSBA'),
        ($cba_id, 'Bachelor of Science in Accountancy', 'BSA'),
        ($cba_id, 'Bachelor of Science in Marketing Management', 'BSMM'),
        ($ccs_id, 'Bachelor of Science in Computer Science', 'BSCS'),
        ($ccs_id, 'Bachelor of Science in Information Technology', 'BSIT')
    ");
    echo "<p style='color:green'>✅ Programs inserted</p>";

    // Create admin account
    $password = password_hash('admin123', PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO admins (name, email, password, role, is_active) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['Administrator', 'admin@slsu.edu.ph', $password, 'admin', 1]);
    echo "<p style='color:green'>✅ Admin account created</p>";

    // Create sample graduate accounts
    $password = password_hash('grad123', PASSWORD_BCRYPT);
    
    // Get program IDs
    $stmt = $pdo->query("SELECT id FROM programs WHERE code = 'BSCpE' LIMIT 1");
    $program_id = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("INSERT INTO graduates (student_id, first_name, last_name, email, password, program_id, batch_year, gender, is_active) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['2021-00001', 'Juan', 'Dela Cruz', 'student@slsu.edu.ph', $password, $program_id, 2021, 'Male', 1]);
    
    $stmt->execute(['2021-00002', 'Maria', 'Santos', 'maria.santos@slsu.edu.ph', $password, $program_id, 2021, 'Female', 1]);
    echo "<p style='color:green'>✅ Sample graduates created</p>";

    // Insert system settings
    $pdo->exec("INSERT INTO system_settings (setting_key, setting_value, setting_group) VALUES 
        ('site_name', 'SLSU GPTS', 'general'),
        ('site_tagline', 'Graduate Profiling and Tracer System', 'general'),
        ('maintenance_mode', '0', 'general'),
        ('survey_year', '2024', 'survey'),
        ('notification_email', 'noreply@slsu.edu.ph', 'email')
    ");
    echo "<p style='color:green'>✅ System settings inserted</p>";

    // Display success
    echo "<hr>";
    echo "<div style='background: #0A1628; color: #C9A84C; padding: 25px; border-radius: 12px; margin-top: 20px;'>";
    echo "<h2 style='margin-top:0; color: #C9A84C;'>✅ Setup Complete!</h2>";
    echo "<h3>Admin Credentials:</h3>";
    echo "<ul style='list-style: none; padding: 0;'>";
    echo "<li>📧 Email: <strong>admin@slsu.edu.ph</strong></li>";
    echo "<li>🔑 Password: <strong>admin123</strong></li>";
    echo "</ul>";
    echo "<h3>Graduate Credentials (Test):</h3>";
    echo "<ul style='list-style: none; padding: 0;'>";
    echo "<li>📧 Email: <strong>student@slsu.edu.ph</strong></li>";
    echo "<li>🔑 Password: <strong>grad123</strong></li>";
    echo "</ul>";
    echo "<p style='margin-top: 20px;'>";
    echo "<a href='login.php?role=admin' style='background: #C9A84C; color: #0A1628; padding: 10px 25px; border-radius: 50px; text-decoration: none; font-weight: bold; display: inline-block; margin-right: 10px;'>Admin Login →</a>";
    echo "<a href='login.php?role=student' style='background: transparent; color: #C9A84C; padding: 10px 25px; border-radius: 50px; text-decoration: none; font-weight: bold; border: 1px solid #C9A84C; display: inline-block;'>Student Login →</a>";
    echo "</p>";
    echo "</div>";

} catch (PDOException $e) {
    echo "<div style='background: #2d1b1b; color: #ff6b6b; padding: 20px; border-radius: 12px; margin-top: 20px;'>";
    echo "<h3 style='margin-top:0; color: #ff6b6b;'>❌ Error</h3>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Solutions:</strong></p>";
    echo "<ul>";
    echo "<li>Make sure MySQL is running in XAMPP</li>";
    echo "<li>Check DB credentials in <code>includes/config.php</code></li>";
    echo "<li>Make sure DB_HOST is <code>127.0.0.1</code></li>";
    echo "</ul>";
    echo "</div>";
}
?>