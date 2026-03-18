<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || (int)$_SESSION['role'] > 2) { header('Location: ../index.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php?tab=departments'); exit; }

// Check if in use
$check = $pdo->prepare("SELECT id FROM hiyari_reports WHERE department_id = :id LIMIT 1");
$check->execute([':id' => $id]);
if ($check->fetch()) { header('Location: index.php?tab=departments&error=in_use'); exit; }

try {
    $pdo->prepare("DELETE FROM departments WHERE id = :id")->execute([':id' => $id]);
    header('Location: index.php?tab=departments&success=dept_deleted');
} catch (PDOException $e) {
    header('Location: index.php?tab=departments&error=failed');
}
exit;
