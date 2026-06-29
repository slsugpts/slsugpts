<?php
// ============================================================
// test_email.php – Test Email Function
// ============================================================

require_once 'includes/db.php';
require_once 'includes/email.php';

echo "<h1>📧 Email Test</h1>";

// Test 1: Send to your email
$test_email = 'your_email@gmail.com'; // CHANGE THIS to your email
$test_name = 'Test User';
$subject = 'Test Email from SLSU GPTS';
$message = '<p>This is a test email to verify that the email system is working correctly.</p><p>If you received this, the email system is working!</p>';

echo "<strong>Test 1: Sending to your email...</strong><br>";
echo "To: $test_email<br>";

$result = send_email($test_email, $test_name, $subject, $message);

if ($result) {
    echo "✅ <strong style='color:green;'>Email sent successfully!</strong> Check your inbox (and spam folder).<br>";
} else {
    echo "❌ <strong style='color:red;'>Email failed to send.</strong> Check your configuration.<br>";
}

// Test 2: Send to a graduate in database
echo "<br><strong>Test 2: Sending to a graduate...</strong><br>";

$grad = fetch("SELECT id, email, first_name, last_name FROM graduates WHERE is_active = 1 LIMIT 1");

if ($grad) {
    echo "Graduate: " . $grad['first_name'] . " " . $grad['last_name'] . " (" . $grad['email'] . ")<br>";
    $result2 = send_notification_email($grad['id'], 'Test Notification from SLSU GPTS', '<p>This is a test notification from SLSU GPTS.</p><p>Your email system is working!</p>');
    if ($result2) {
        echo "✅ <strong style='color:green;'>Notification email sent!</strong><br>";
    } else {
        echo "❌ <strong style='color:red;'>Notification email failed.</strong><br>";
    }
} else {
    echo "❌ No graduates found in database.<br>";
}

// Show debug info
echo "<br><strong>Debug Info:</strong><br>";
echo "MAIL_HOST: " . MAIL_HOST . "<br>";
echo "MAIL_PORT: " . MAIL_PORT . "<br>";
echo "MAIL_USER: " . MAIL_USER . "<br>";
echo "MAIL_FROM_NAME: " . MAIL_FROM_NAME . "<br>";
?>