<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || (int) $_SESSION['role'] !== 1) {
    header('Location: ../index');
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    header('Location: index?tab=users');
    exit;
}

// Reset to default password: Welcome@1234
$default_password = 'Welcome@1234';
$hashed = password_hash($default_password, PASSWORD_BCRYPT);

try {
    $pdo->prepare("UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id")
        ->execute([':password' => $hashed, ':id' => $id]);
    header('Location: index?tab=users&success=pw_reset');
} catch (PDOException $e) {
    header('Location: index?tab=users&error=failed');
}
exit;
