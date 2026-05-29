<?php
session_start();
require_once '../config/db.php';
require_once '../config/mail.php';

if (!isset($_SESSION['user_id']) || (int)$_SESSION['role'] !== 1) {
    header('Location: ../index'); exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index?tab=users'); exit; }

// Fetch user
$user = $pdo->prepare("SELECT * FROM users WHERE id = :id AND email_sent = 0");
$user->execute([':id' => $id]);
$user = $user->fetch();

if (!$user) {
    header('Location: index?tab=users&error=already_sent'); exit;
}

// Regenerate token if expired
$token_expires = $user['setup_token_expires'];
$setup_token   = $user['setup_token'];

if (!$setup_token || strtotime($token_expires) < time()) {
    $setup_token   = bin2hex(random_bytes(32));
    $token_expires = date('Y-m-d H:i:s', strtotime('+7 days'));
    $pdo->prepare("UPDATE users SET setup_token = :token, setup_token_expires = :expires WHERE id = :id")
        ->execute([':token' => $setup_token, ':expires' => $token_expires, ':id' => $id]);
}

$setup_url  = APP_URL . '/auth/set_password?token=' . $setup_token;
$role_names = [1 => 'Super Admin', 2 => 'Admin', 3 => 'User'];
$role_label = $role_names[$user['role']] ?? 'User';

$welcome_body = "
<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#fff;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden'>
  <div style='background:#2563eb;padding:24px 32px'>
    <h1 style='color:#fff;margin:0;font-size:18px'>Welcome to YADIN Safety Report System</h1>
    <p style='color:rgba(255,255,255,0.85);margin:6px 0 0;font-size:13px'>Your account has been created by the administrator</p>
  </div>
  <div style='padding:28px 32px'>
    <p style='color:#333;font-size:14px;margin-top:0'>Hello <strong>" . htmlspecialchars($user['name']) . "</strong>,</p>
    <p style='color:#333;font-size:14px;'>Your account has been created on the YADIN Safety Report Management System. Click the button below to set your password and get started.</p>
    <table style='width:100%;border-collapse:collapse;font-size:14px;color:#444;margin:20px 0'>
      <tr style='background:#f0f4ff'><td style='padding:10px 14px;font-weight:bold;width:38%'>Employee ID</td><td style='padding:10px 14px'><strong>" . htmlspecialchars($user['employee_id']) . "</strong></td></tr>
      <tr><td style='padding:10px 14px;font-weight:bold'>Email</td><td style='padding:10px 14px'>" . htmlspecialchars($user['email']) . "</td></tr>
      <tr style='background:#f0f4ff'><td style='padding:10px 14px;font-weight:bold'>Position</td><td style='padding:10px 14px'>" . htmlspecialchars($user['position'] ?: '—') . "</td></tr>
      <tr><td style='padding:10px 14px;font-weight:bold'>Role</td><td style='padding:10px 14px'>{$role_label}</td></tr>
    </table>
    <p style='color:#555;font-size:13px;background:#fff3cd;padding:12px 16px;border-radius:8px;border-left:3px solid #f39c12;margin-bottom:20px;'>
      This link will expire in <strong>7 days</strong>. Please set your password before it expires.
    </p>
    <a href='{$setup_url}' style='display:inline-block;background:#2563eb;color:#fff;padding:14px 32px;border-radius:6px;text-decoration:none;font-weight:bold;font-size:15px;'>
      Set My Password →
    </a>
    <p style='color:#aaa;font-size:11px;margin-top:20px;word-break:break-all;'>
      If the button doesn't work, copy this link: {$setup_url}
    </p>
  </div>
  <div style='background:#f7f7f7;padding:16px 32px;font-size:12px;color:#999;border-top:1px solid #e0e0e0'>
    This is an automated notification from YADIN Safety Report Management System. Do not reply to this email.
  </div>
</div>";

$result = sendMail($user['email'], 'Welcome to YADIN Safety — Set Your Password', $welcome_body);

if ($result) {
    // Mark email as sent
    $pdo->prepare("UPDATE users SET email_sent = 1 WHERE id = :id")
        ->execute([':id' => $id]);

    auditLog($pdo, 'WELCOME_EMAIL_SENT', 'master', $id, $user['name'] . ' (' . $user['employee_id'] . ')', '', $user['email']);

    header('Location: index?tab=users&success=email_sent');
} else {
    header('Location: index?tab=users&error=email_failed');
}
exit;