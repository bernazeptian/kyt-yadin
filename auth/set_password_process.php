<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['set_password_user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login');
    exit;
}

$uid              = (int)$_SESSION['set_password_user_id'];
$new_password     = $_POST['new_password']     ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (strlen($new_password) < 8) {
    header('Location: set_password?error=short');
    exit;
}

if ($new_password !== $confirm_password) {
    header('Location: set_password?error=mismatch');
    exit;
}

try {
    $hashed = password_hash($new_password, PASSWORD_BCRYPT);
    $pdo->prepare("UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id")
        ->execute([':password' => $hashed, ':id' => $uid]);

    // Fetch user and log them in
    $user = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $user->execute([':id' => $uid]);
    $user = $user->fetch();

    // Clear set_password session
    unset($_SESSION['set_password_user_id']);

    // Log in the user
    session_regenerate_id(true);
    $_SESSION['user_id']     = (int)$user['id'];
    $_SESSION['employee_id'] = $user['employee_id'];
    $_SESSION['name']        = $user['name'];
    $_SESSION['email']       = $user['email'];
    $_SESSION['role']        = (int)$user['role'];

    header('Location: ../index?welcome=1');
} catch (PDOException $e) {
    header('Location: set_password?error=failed');
}
exit;
