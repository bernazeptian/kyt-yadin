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
$password = $_POST['password'] ?? '';
$position = trim($_POST['position'] ?? '');
$role = (int) ($_POST['role'] ?? 4);
$is_active = isset($_POST['is_active']) ? 1 : 0;

if (!$employee_id || !$name || !$email || !$password) {
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
    header('Location: index.?tab=users&error=email_exists');
    exit;
}

try {
    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("
        INSERT INTO users (employee_id, name, email, password, position, role, is_active, created_at, updated_at)
        VALUES (:employee_id, :name, :email, :password, :position, :role, :is_active, NOW(), NOW())
    ");
    $stmt->execute([
        ':employee_id' => $employee_id,
        ':name' => $name,
        ':email' => $email,
        ':password' => $hashed,
        ':position' => $position ?: null,
        ':role' => $role,
        ':is_active' => $is_active,
    ]);
    header('Location: index?tab=users&success=user_added');
} catch (PDOException $e) {
    header('Location: index?tab=users&error=failed');
}
exit;
