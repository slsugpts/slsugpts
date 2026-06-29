<?php
// ============================================================
// includes/email.php – Email Functions
// ============================================================

require_once __DIR__ . '/config.php';

// Load PHPMailer manually (NO Composer)
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';
require_once __DIR__ . '/phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function send_email($to, $to_name, $subject, $message, $alt_message = '') {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        // Recipients
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($to, $to_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;
        $mail->AltBody = $alt_message ?: strip_tags($message);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email could not be sent. Error: {$mail->ErrorInfo}");
        return false;
    }
}

function send_notification_email($graduate_id, $subject, $message) {
    global $db;
    
    $grad = fetch("SELECT email, first_name, last_name FROM graduates WHERE id = ?", [$graduate_id]);
    if (!$grad) {
        return false;
    }
    
    $to = $grad['email'];
    $to_name = $grad['first_name'] . ' ' . $grad['last_name'];
    
    $html_message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: 'DM Sans', Arial, sans-serif; color: #1B5E20; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #E8F5E9; border-radius: 12px; }
            .header { text-align: center; padding: 20px 0; }
            .header h1 { color: #1B5E20; font-family: 'Playfair Display', serif; }
            .header h1 span { color: #C9A84C; }
            .content { background: white; padding: 30px; border-radius: 12px; border: 1px solid #C8E6C9; }
            .footer { text-align: center; padding: 20px 0; color: #2E7D32; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>SLSU <span>GPTS</span></h1>
                <p style='color: #2E7D32;'>Graduate Profiling & Tracer System</p>
            </div>
            <div class='content'>
                <p>Dear <strong>$to_name</strong>,</p>
                <br>
                $message
                <br><br>
                <p>Best regards,<br><strong>SLSU GPTS Team</strong></p>
            </div>
            <div class='footer'>
                &copy; " . date('Y') . " SLSU GPTS · Southern Luzon State University – Lucena Campus
            </div>
        </div>
    </html>
    ";
    
    return send_email($to, $to_name, $subject, $html_message);
}
?>
