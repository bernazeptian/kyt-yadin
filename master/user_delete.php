<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || (int)$_SESSION['role'] !== 1) { header('Location: ../index.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php?tab=users'); exit; }

// Cannot delete yourself
if ($id === (int)$_SESSION['user_id']) {
    header('Location: index.php?tab=users&error=self_delete'); exit;
}

try {
    $pdo->prepare("DELETE FROM users WHERE id = :id")->execute([':id' => $id]);
    header('Location: index.php?tab=users&success=user_deleted');
} catch (PDOException $e) {
    header('Location: index.php?tab=users&error=failed');
}
exit;
