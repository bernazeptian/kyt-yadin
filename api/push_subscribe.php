<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['endpoint'])) {
    echo json_encode(['error' => 'Invalid data']); exit;
}

$uid      = (int)$_SESSION['user_id'];
$endpoint = $data['endpoint'];
$p256dh   = $data['keys']['p256dh'] ?? '';
$auth     = $data['keys']['auth']   ?? '';

try {
    $pdo->prepare("DELETE FROM push_subscriptions WHERE user_id = :uid")->execute([':uid' => $uid]);
    $pdo->prepare("
        INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, created_at)
        VALUES (:uid, :endpoint, :p256dh, :auth, NOW())
    ")->execute([':uid' => $uid, ':endpoint' => $endpoint, ':p256dh' => $p256dh, ':auth' => $auth]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
