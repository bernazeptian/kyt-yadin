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

$id = (int) ($_POST['id'] ?? 0);
$employee_id = trim($_POST['employee_id'] ?? '');
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$position = trim($_POST['position'] ?? '');
$role = (int) ($_POST['role'] ?? 4);
$is_active = isset($_POST['is_active']) ? 1 : 0;

if (!$id || !$employee_id || !$name || !$email) {
    header('Location: index?tab=users&error=failed');
    exit;
}

// Check duplicate employee_id excluding self
$check = $pdo->prepare("SELECT id FROM users WHERE employee_id = :emp_id AND id != :id");
$check->execute([':emp_id' => $employee_id, ':id' => $id]);
if ($check->fetch()) {
    header('Location: index?tab=users&error=emp_exists');
    exit;
}

// Check duplicate email excluding self
$check2 = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
$check2->execute([':email' => $email, ':id' => $id]);
if ($check2->fetch()) {
    header('Location: index?tab=users&error=email_exists');
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE users
        SET employee_id = :employee_id, name = :name, email = :email,
            position = :position, role = :role, is_active = :is_active, updated_at = NOW()
        WHERE id = :id
    ");
    $stmt->execute([
        ':employee_id' => $employee_id,
        ':name' => $name,
        ':email' => $email,
        ':position' => $position ?: null,
        ':role' => $role,
        ':is_active' => $is_active,
        ':id' => $id,
    ]);

    auditLog($pdo, 'USER_UPDATED', 'master', $id, $name . ' (' . $employee_id . ')', '', '');

    header('Location: index?tab=users&success=user_updated');
} catch (PDOException $e) {
    header('Location: index?tab=users&error=failed');
}
exit;
