<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || (int) $_SESSION['role'] > 2) {
    header('Location: ../index');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index?tab=locations');
    exit;
}

csrf_verify();

$id = (int) $_POST['id'];
$code = strtoupper(trim($_POST['code'] ?? ''));
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$department_id = !empty($_POST['department_id']) ? (int) $_POST['department_id'] : null;
$is_active = isset($_POST['is_active']) ? 1 : 0;

if (!$id || !$code || !$name) {
    header('Location: index?tab=locations&error=failed');
    exit;
}

$check = $pdo->prepare("SELECT id FROM locations WHERE code = :code AND id != :id");
$check->execute([':code' => $code, ':id' => $id]);
if ($check->fetch()) {
    header('Location: index?tab=locations&error=loc_exists');
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE locations SET code = :code, name = :name, description = :description, department_id = :department_id, is_active = :is_active, updated_at = NOW() WHERE id = :id");
    $stmt->execute([':code' => $code, ':name' => $name, ':description' => $description, ':department_id' => $department_id, ':is_active' => $is_active, ':id' => $id]);
    header('Location: index?tab=locations&success=loc_updated');
} catch (PDOException $e) {
    header('Location: index?tab=locations&error=failed');
}
exit;
