<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || (int) $_SESSION['role'] !== 1) {
  header('Location: ../index');
  exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: index?tab=users');
  exit;
}

csrf_verify();

$employee_id = trim($_POST['employee_id'] ?? '');
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$position = trim($_POST['position'] ?? '');
$role = (int) ($_POST['role'] ?? 3);
$is_active = isset($_POST['is_active']) ? 1 : 0;

if (!$employee_id || !$name || !$email) {
  header('Location: index?tab=users&error=failed');
  exit;
}

// Check duplicate employee_id
$check = $pdo->prepare("SELECT id FROM users WHERE employee_id = :emp_id");
$check->execute([':emp_id' => $employee_id]);
if ($check->fetch()) {
  header('Location: index?tab=users&error=emp_exists');
  exit;
}

// Check duplicate email
$check2 = $pdo->prepare("SELECT id FROM users WHERE email = :email");
$check2->execute([':email' => $email]);
if ($check2->fetch()) {
  header('Location: index?tab=users&error=email_exists');
  exit;
}

require_once '../config/mail.php';

try {
  $stmt = $pdo->prepare("
        INSERT INTO users (employee_id, name, email, password, position, role, is_active, created_at, updated_at)
        VALUES (:employee_id, :name, :email, NULL, :position, :role, :is_active, NOW(), NOW())
    ");
  $stmt->execute([
    ':employee_id' => $employee_id,
    ':name' => $name,
    ':email' => $email,
    ':position' => $position ?: null,
    ':role' => $role,
    ':is_active' => $is_active,
  ]);

  // ── Send welcome email ────────────────────────
  $login_url = APP_URL . '/auth/login';
  $role_names = [1 => 'Super Admin', 2 => 'Admin', 3 => 'User'];
  $role_label = $role_names[$role] ?? 'User';

  $welcome_body = "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#fff;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden'>
      <div style='background:#2563eb;padding:24px 32px'>
        <h1 style='color:#fff;margin:0;font-size:18px'>👋 Welcome to YADIN Safety Report System</h1>
        <p style='color:rgba(255,255,255,0.85);margin:6px 0 0;font-size:13px'>Your account has been created by the administrator</p>
      </div>
      <div style='padding:28px 32px'>
        <p style='color:#333;font-size:14px;margin-top:0'>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
        <p style='color:#333;font-size:14px;'>Your account has been created on the YADIN Safety Report Management System. Please log in and set your password to get started.</p>
        <table style='width:100%;border-collapse:collapse;font-size:14px;color:#444;margin:20px 0'>
          <tr style='background:#f0f4ff'><td style='padding:10px 14px;font-weight:bold;width:38%'>Employee ID</td><td style='padding:10px 14px'><strong>" . htmlspecialchars($employee_id) . "</strong></td></tr>
          <tr><td style='padding:10px 14px;font-weight:bold'>Email</td><td style='padding:10px 14px'>" . htmlspecialchars($email) . "</td></tr>
          <tr style='background:#f0f4ff'><td style='padding:10px 14px;font-weight:bold'>Position</td><td style='padding:10px 14px'>" . htmlspecialchars($position ?: '—') . "</td></tr>
          <tr><td style='padding:10px 14px;font-weight:bold'>Role</td><td style='padding:10px 14px'>{$role_label}</td></tr>
        </table>
        <p style='color:#555;font-size:13px;background:#fff3cd;padding:12px 16px;border-radius:8px;border-left:3px solid #f39c12;'>
          ⚠️ You have not set a password yet. When you first log in with your Employee ID, you will be asked to create one.
        </p>
        <a href='{$login_url}' style='display:inline-block;background:#2563eb;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;font-size:14px;margin-top:8px;'>
          Log In & Set Password →
        </a>
      </div>
      <div style='background:#f7f7f7;padding:16px 32px;font-size:12px;color:#999;border-top:1px solid #e0e0e0'>
        This is an automated notification from YADIN Safety Report Management System. Do not reply to this email.
      </div>
    </div>";

  sendMail($email, 'Welcome to YADIN Safety Report System — Set Your Password', $welcome_body);

  header('Location: index?tab=users&success=user_added');
} catch (PDOException $e) {
  header('Location: index?tab=users&error=failed');
}
exit;