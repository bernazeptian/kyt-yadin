<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile');
    exit;
}

csrf_verify();

$uid   = (int) $_SESSION['user_id'];
$name  = trim($_POST['name']     ?? '');
$email = trim($_POST['email']    ?? '');
$position = trim($_POST['position'] ?? '');

if (!$name || !$email) {
    header('Location: profile?error=update_failed');
    exit;
}

// Check duplicate email excluding self
$check = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
$check->execute([':email' => $email, ':id' => $uid]);
if ($check->fetch()) {
    header('Location: profile?error=email_exists');
    exit;
}

try {
    $pdo->prepare("
        UPDATE users SET name = :name, email = :email, position = :position, updated_at = NOW()
        WHERE id = :id
    ")->execute([
        ':name'     => $name,
        ':email'    => $email,
        ':position' => $position ?: null,
        ':id'       => $uid,
    ]);

    // Update session
    $_SESSION['name']  = $name;
    $_SESSION['email'] = $email;

    header('Location: profile?success=profile_updated');
} catch (PDOException $e) {
    header('Location: profile?error=update_failed');
}
exit;
