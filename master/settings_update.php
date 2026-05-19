<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['role'] !== 1) {
    header('Location: ../index');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index?tab=settings');
    exit;
}

csrf_verify();

$days = $_POST['days'] ?? [];
$allowed = ['extreme', 'high', 'medium', 'low'];

try {
    foreach ($allowed as $level) {
        $d = max(1, min(365, (int) ($days[$level] ?? 7)));
        $pdo->prepare("
            INSERT INTO risk_config (risk_level, due_days) VALUES (:level, :days)
            ON DUPLICATE KEY UPDATE due_days = :days2
        ")->execute([':level' => $level, ':days' => $d, ':days2' => $d]);
    }
    header('Location: index?tab=settings&success=saved');
} catch (PDOException $e) {
    header('Location: index?tab=settings&error=failed');
}
exit;