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
if (!$id) {
    header('Location: index?tab=users');
    exit;
}

// Cannot delete yourself
if ($id === (int) $_SESSION['user_id']) {
    header('Location: index?tab=users&error=self_delete');
    exit;
}

try {
    // Fetch user info BEFORE deleting
    $del_user = $pdo->prepare("SELECT name, employee_id FROM users WHERE id = :id");
    $del_user->execute([':id' => $id]);
    $del_user = $del_user->fetch();

    $pdo->prepare("DELETE FROM users WHERE id = :id")->execute([':id' => $id]);

    auditLog($pdo, 'USER_DELETED', 'master', $id, ($del_user['name'] ?? '') . ' (' . ($del_user['employee_id'] ?? '') . ')', '', '');

    header('Location: index?tab=users&success=user_deleted');
} catch (PDOException $e) {
    header('Location: index?tab=users&error=failed');
}
exit;