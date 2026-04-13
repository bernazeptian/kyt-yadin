<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ── SMTP Configuration ──
define('MAIL_HOST', 'smtp-relay.brevo.com');
define('MAIL_PORT', 587);        // ← 587 for TLS, 465 for SSL
define('MAIL_ENCRYPTION', 'tls');      // ← tls or ssl
define('MAIL_USERNAME', 'a7eb4f001@smtp-brevo.com');
define('MAIL_PASSWORD', 'xsmtpsib-4ce0524faa9302764eefa1ff679e3ef0fcd584851de76b16918541d4f79abc37-l49k4N9z5DXoNHAQ');
define('MAIL_FROM_NAME', 'KYT Yadin System');
define('MAIL_FROM_EMAIL', 'noreply@yanmar.co.id');
define('APP_URL', 'http://kyt.yadin.com');


function sendMail(string $to, string $subject, string $body): bool
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->SMTPDebug = 2;
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port = MAIL_PORT;
        $mail->Timeout = 10;

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        echo '❌ PHPMailer Error: ' . $mail->ErrorInfo;
        return false;
    }
}