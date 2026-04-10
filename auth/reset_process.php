<?php
session_start();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login');
    exit;
}

$token = $_POST['token'] ?? '';
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

if (!$token || !$password || !$password_confirm) {
    header('Location: reset?token=' . urlencode($token) . '&error=1');
    exit;
}

// Check passwords match
if ($password !== $password_confirm) {
    header('Location: reset?token=' . urlencode($token) . '&error=mismatch');
    exit;
}

// Validate token is still valid
$stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = :token AND reset_expires > NOW() AND is_active = 1 LIMIT 1");
$stmt->execute([':token' => $token]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: reset?token=' . urlencode($token));
    exit;
}

// Hash new password
$hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Update password and clear reset token
$stmt = $pdo->prepare("
    UPDATE users
    SET password = :password, reset_token = NULL, reset_expires = NULL, updated_at = NOW()
    WHERE id = :id
");
$stmt->execute([
    ':password' => $hashed,
    ':id' => $user['id'],
]);

// Redirect to login with success message
header('Location: login?reset=success');
exit;
