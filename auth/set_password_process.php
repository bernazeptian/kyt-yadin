<?php
session_start();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login');
    exit;
}

$token            = trim($_POST['token']            ?? '');
$new_password     = $_POST['new_password']          ?? '';
$confirm_password = $_POST['confirm_password']      ?? '';

if (!$token) {
    header('Location: login?error=invalid_token');
    exit;
}

// Validate token
$stmt = $pdo->prepare("
    SELECT * FROM users
    WHERE setup_token = :token
      AND setup_token_expires > NOW()
      AND password IS NULL
    LIMIT 1
");
$stmt->execute([':token' => $token]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: login?error=token_expired');
    exit;
}

if (strlen($new_password) < 8) {
    header('Location: set_password?token=' . urlencode($token) . '&error=short');
    exit;
}

if ($new_password !== $confirm_password) {
    header('Location: set_password?token=' . urlencode($token) . '&error=mismatch');
    exit;
}

try {
    $hashed = password_hash($new_password, PASSWORD_BCRYPT);

    // Save password and clear token
    $pdo->prepare("
        UPDATE users
        SET password = :password, setup_token = NULL, setup_token_expires = NULL, updated_at = NOW()
        WHERE id = :id
    ")->execute([':password' => $hashed, ':id' => $user['id']]);

    // Auto log in
    session_regenerate_id(true);
    $_SESSION['user_id']     = (int)$user['id'];
    $_SESSION['employee_id'] = $user['employee_id'];
    $_SESSION['name']        = $user['name'];
    $_SESSION['email']       = $user['email'];
    $_SESSION['role']        = (int)$user['role'];

    header('Location: ../index?welcome=1');
} catch (PDOException $e) {
    error_log('set_password_process error: ' . $e->getMessage());
    header('Location: set_password?token=' . urlencode($token) . '&error=failed');
}
exit;
