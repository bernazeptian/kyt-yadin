<?php
session_start();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login');
    exit;
}

csrf_verify();

$employee_id = trim($_POST['employee_id'] ?? '');
$password    = $_POST['password'] ?? '';

if (!$employee_id || !$password) {
    header('Location: login?error=required');
    exit;
}

// ── Rate limiting (5 attempts → 15-min block per employee_id) ──
$attempts_key = 'login_attempts_' . $employee_id;
$blocked_key  = 'login_blocked_until_' . $employee_id;

if (!empty($_SESSION[$blocked_key]) && time() < $_SESSION[$blocked_key]) {
    header('Location: login?error=ratelimit');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE employee_id = :employee_id LIMIT 1");
$stmt->execute([':employee_id' => $employee_id]);
$user = $stmt->fetch();

if (!$user['is_active']) {
    header('Location: login?error=inactive');
    exit;
}

// ── Detect first-time login (no password set) ──
if (is_null($user['password'])) {
    $_SESSION['set_password_user_id'] = (int)$user['id'];
    header('Location: set_password');
    exit;
}

// Normal password check
if (!password_verify($password, $user['password'])) {
    $attempts = ($_SESSION[$attempts_key] ?? 0) + 1;
    if ($attempts >= 5) {
        $_SESSION[$blocked_key] = time() + 900;
        unset($_SESSION[$attempts_key]);
        header('Location: login?error=ratelimit');
    } else {
        $_SESSION[$attempts_key] = $attempts;
        header('Location: login?error=invalid');
    }
    exit;
}

// Clear rate-limit state on success
unset($_SESSION[$attempts_key], $_SESSION[$blocked_key]);

session_regenerate_id(true);
$_SESSION['user_id']     = (int) $user['id'];
$_SESSION['employee_id'] = $user['employee_id'];
$_SESSION['name']        = $user['name'];
$_SESSION['email']       = $user['email'];
$_SESSION['role']        = (int) $user['role'];

header('Location: ../index');
exit;
