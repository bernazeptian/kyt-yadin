<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']); exit;
}

$uid = (int)$_SESSION['user_id'];
$id  = (int)($_POST['id'] ?? 0);

if ($id) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid")
        ->execute([':id' => $id, ':uid' => $uid]);
} else {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid")
        ->execute([':uid' => $uid]);
}

echo json_encode(['success' => true]);
