<?php
session_start();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login');
    exit;
}

csrf_verify();

$employee_id = trim($_POST['employee_id'] ?? '');
$password = $_POST['password'] ?? '';

if (!$employee_id || !$password) {
    header('Location: login?error=required');
    exit;
}

// ── Rate limiting ─────────────────────────────
$attempts_key = 'login_attempts_' . $employee_id;
$blocked_key = 'login_blocked_until_' . $employee_id;

if (!empty($_SESSION[$blocked_key]) && time() < $_SESSION[$blocked_key]) {
    header('Location: login?error=ratelimit');
    exit;
}

// ── Fetch user ────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM users WHERE employee_id = :employee_id LIMIT 1");
$stmt->execute([':employee_id' => $employee_id]);
$user = $stmt->fetch();

// ── User not found ────────────────────────────
if (!$user) {
    auditLog($pdo, 'LOGIN_FAILED', 'auth', 0, $employee_id);
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

// ── Inactive account ──────────────────────────
if (!$user['is_active']) {
    auditLog($pdo, 'LOGIN_FAILED', 'auth', $user['id'], $user['name']);
    header('Location: login?error=inactive');
    exit;
}

// ── First-time login (no password set) ───────
if (is_null($user['password'])) {
    $_SESSION['set_password_user_id'] = (int) $user['id'];
    header('Location: set_password');
    exit;
}

// ── Wrong password ────────────────────────────
if (!password_verify($password, $user['password'])) {
    auditLog($pdo, 'LOGIN_FAILED', 'auth', $user['id'], $user['name']);
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

// ── Success ───────────────────────────────────
unset($_SESSION[$attempts_key], $_SESSION[$blocked_key]);

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['employee_id'] = $user['employee_id'];
$_SESSION['name'] = $user['name'];
$_SESSION['email'] = $user['email'];
$_SESSION['role'] = (int) $user['role'];

auditLog($pdo, 'LOGIN_SUCCESS', 'auth', $user['id'], $user['name']);

header('Location: ../index');
exit;