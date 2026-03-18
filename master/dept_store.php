<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || (int)$_SESSION['role'] > 2) { header('Location: ../index.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php?tab=departments'); exit; }

$code        = strtoupper(trim($_POST['code']        ?? ''));
$name        = trim($_POST['name']        ?? '');
$head_id     = !empty($_POST['head_id'])  ? (int)$_POST['head_id'] : null;
$description = trim($_POST['description'] ?? '');
$is_active   = isset($_POST['is_active'])  ? 1 : 0;

if (!$code || !$name) { header('Location: index.php?tab=departments&error=failed'); exit; }

// Check duplicate code
$check = $pdo->prepare("SELECT id FROM departments WHERE code = :code");
$check->execute([':code' => $code]);
if ($check->fetch()) { header('Location: index.php?tab=departments&error=dept_exists'); exit; }

try {
    $stmt = $pdo->prepare("INSERT INTO departments (code, name, head_id, description, is_active, created_at, updated_at) VALUES (:code, :name, :head_id, :description, :is_active, NOW(), NOW())");
    $stmt->execute([':code' => $code, ':name' => $name, ':head_id' => $head_id, ':description' => $description, ':is_active' => $is_active]);
    header('Location: index.php?tab=departments&success=dept_added');
} catch (PDOException $e) {
    header('Location: index.php?tab=departments&error=failed');
}
exit;
