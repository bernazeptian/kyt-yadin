<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || (int) $_SESSION['role'] > 2) {
    header('Location: ../index');
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    header('Location: index?tab=locations');
    exit;
}

$check = $pdo->prepare("SELECT id FROM hiyari_reports WHERE location_id = :id LIMIT 1");
$check->execute([':id' => $id]);
if ($check->fetch()) {
    header('Location: index?tab=locations&error=in_use');
    exit;
}

try {
    $pdo->prepare("DELETE FROM locations WHERE id = :id")->execute([':id' => $id]);
    header('Location: index?tab=locations&success=loc_deleted');
} catch (PDOException $e) {
    header('Location: index?tab=locations&error=failed');
}
exit;
