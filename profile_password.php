<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile');
    exit;
}

csrf_verify();

$uid              = (int) $_SESSION['user_id'];
$current_password = $_POST['current_password'] ?? '';
$new_password     = $_POST['new_password']     ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Fetch current password hash
$user = $pdo->prepare("SELECT password FROM users WHERE id = :id");
$user->execute([':id' => $uid]);
$user = $user->fetch();

if (!password_verify($current_password, $user['password'])) {
    header('Location: profile?error=invalid_password');
    exit;
}

if ($new_password !== $confirm_password) {
    header('Location: profile?error=password_mismatch');
    exit;
}

if (strlen($new_password) < 8) {
    header('Location: profile?error=password_short');
    exit;
}

try {
    $hashed = password_hash($new_password, PASSWORD_BCRYPT);
    $pdo->prepare("UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id")
        ->execute([':password' => $hashed, ':id' => $uid]);

    header('Location: profile?success=password_changed');
} catch (PDOException $e) {
    header('Location: profile?error=update_failed');
}
exit;
