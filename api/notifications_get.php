<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']); exit;
}

$uid   = (int)$_SESSION['user_id'];
$limit = (int)($_GET['limit'] ?? 10);

$stmt = $pdo->prepare("
    SELECT * FROM notifications
    WHERE user_id = :uid
    ORDER BY created_at DESC
    LIMIT :limit
");
$stmt->bindValue(':uid',   $uid,   PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$notifications = $stmt->fetchAll();

$unread = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = $uid AND is_read = 0")->fetchColumn();

echo json_encode([
    'notifications' => $notifications,
    'unread'        => $unread,
]);
