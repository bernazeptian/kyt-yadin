<?php
session_start();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login');
    exit;
}

$employee_id = trim($_POST['employee_id'] ?? '');
$password = $_POST['password'] ?? '';

if (!$employee_id || !$password) {
    header('Location: login?error=required');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE employee_id = :employee_id LIMIT 1");
$stmt->execute([':employee_id' => $employee_id]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    header('Location: login?error=invalid');
    exit;
}

if (!$user['is_active']) {
    header('Location: login?error=inactive');
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];
$_SESSION['employee_id'] = $user['employee_id'];
$_SESSION['name'] = $user['name'];
$_SESSION['email'] = $user['email'];
$_SESSION['role'] = (int) $user['role']; // ← cast to int always

header('Location: ../index');
exit;
