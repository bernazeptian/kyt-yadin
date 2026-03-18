<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || (int)$_SESSION['role'] > 2) { header('Location: ../index'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index'); exit; }

$report_id = (int)($_POST['report_id'] ?? 0);
$status    = $_POST['status'] ?? '';
$allowed   = ['open', 'in_progress', 'closed'];

if (!$report_id || !in_array($status, $allowed)) {
    header('Location: view?id=' . $report_id . '&error=1'); exit;
}

try {
    $pdo->prepare("UPDATE hiyari_reports SET status = :status, updated_at = NOW() WHERE id = :id")
        ->execute([':status' => $status, ':id' => $report_id]);
    header('Location: view?id=' . $report_id . '&success=status_updated');
} catch (PDOException $e) {
    header('Location: view?id=' . $report_id . '&error=1');
}
exit;
