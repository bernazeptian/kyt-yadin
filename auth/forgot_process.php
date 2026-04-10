<?php
session_start();
require_once '../config/db.php';
require_once '../config/mail.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot');
    exit;
}

$email = trim($_POST['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: forgot?error=notfound');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email AND is_active = 1 LIMIT 1");
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: forgot?error=notfound');
    exit;
}

$token = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

$stmt = $pdo->prepare("UPDATE users SET reset_token = :token, reset_expires = :expires WHERE id = :id");
$stmt->execute([':token' => $token, ':expires' => $expires, ':id' => $user['id']]);

$reset_link = APP_URL . '/auth/reset?token=' . $token;
$body = "<p>Click here to reset: <a href='{$reset_link}'>{$reset_link}</a></p>";

// ← CHANGE THIS to show exact error instead of redirecting
$sent = sendMail($user['email'], 'Reset Your KYT Yadin Password', $body);

if (!$sent) {
    // sendMail() already echoed the PHPMailer error above
    die('❌ Mail failed. Check the error printed above or PHP error logs.');
}


header('Location: forgot?sent=1');
exit;