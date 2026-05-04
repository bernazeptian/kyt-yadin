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

$code = strtoupper(trim($_POST['code'] ?? ''));
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$department_id = !empty($_POST['department_id']) ? (int) $_POST['department_id'] : null;
$pic_area_id   = !empty($_POST['pic_area_id'])   ? (int) $_POST['pic_area_id']   : null;
$is_active = isset($_POST['is_active']) ? 1 : 0;

if (!$code || !$name) {
    header('Location: index?tab=locations&error=failed');
    exit;
}

$check = $pdo->prepare("SELECT id FROM locations WHERE code = :code");
$check->execute([':code' => $code]);
if ($check->fetch()) {
    header('Location: index?tab=locations&error=loc_exists');
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO locations (code, name, description, department_id, pic_area_id, is_active, created_at, updated_at) VALUES (:code, :name, :description, :department_id, :pic_area_id, :is_active, NOW(), NOW())");
    $stmt->execute([':code' => $code, ':name' => $name, ':description' => $description, ':department_id' => $department_id, ':pic_area_id' => $pic_area_id, ':is_active' => $is_active]);
    header('Location: index?tab=locations&success=loc_added');
} catch (PDOException $e) {
    header('Location: index?tab=locations&error=failed');
}
exit;
