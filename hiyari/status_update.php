<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || (int)$_SESSION['role'] > 2) { header('Location: ../index'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index'); exit; }

csrf_verify();

$report_id = (int)($_POST['report_id'] ?? 0);
$status    = $_POST['status'] ?? '';
$allowed   = ['open', 'in_progress', 'closed'];

if (!$report_id || !in_array($status, $allowed)) {
    header('Location: view?id=' . $report_id . '&error=1'); exit;
}

try {
    // Get old status first
    $old = $pdo->prepare("SELECT status, report_number FROM hiyari_reports WHERE id = :id");
    $old->execute([':id' => $report_id]);
    $old = $old->fetch();

    $pdo->prepare("UPDATE hiyari_reports SET status = :status, updated_at = NOW() WHERE id = :id")
        ->execute([':status' => $status, ':id' => $report_id]);

    auditLog($pdo, 'STATUS_CHANGED', 'hiyari', $report_id, $old['report_number'] ?? '', $old['status'] ?? '', $status);

    header('Location: view?id=' . $report_id . '&success=status_updated');
} catch (PDOException $e) {
    header('Location: view?id=' . $report_id . '&error=1');
}
exit;