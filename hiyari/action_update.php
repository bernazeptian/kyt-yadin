<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../auth/login'); exit; }

$id        = (int)($_GET['id']        ?? 0);
$report_id = (int)($_GET['report_id'] ?? 0);
$status    = $_GET['status'] ?? '';
$allowed   = ['open', 'progress', 'done'];
$uid       = (int)$_SESSION['user_id'];
$role      = (int)$_SESSION['role'];

if (!$id || !$report_id || !in_array($status, $allowed)) {
    header('Location: view?id=' . $report_id . '&error=1'); exit;
}

// Check access
$action = $pdo->prepare("SELECT * FROM corrective_actions WHERE id = :id AND report_id = :report_id");
$action->execute([':id' => $id, ':report_id' => $report_id]);
$action = $action->fetch();

if (!$action) { header('Location: view?id=' . $report_id . '&error=1'); exit; }

$is_admin         = $role <= 2;
$is_assigned_user = (int)$action['pic_user_id'] === $uid;

if (!$is_admin && !$is_assigned_user) {
    header('Location: view?id=' . $report_id . '&error=1'); exit;
}

// Only admin can reopen
if ($status === 'open' && !$is_admin) {
    header('Location: view?id=' . $report_id . '&error=1'); exit;
}

try {
    // ── Update corrective action status ──────
    $pdo->prepare("UPDATE corrective_actions SET status = :status, updated_at = NOW() WHERE id = :id")
        ->execute([':status' => $status, ':id' => $id]);

    // ── Auto update report status ─────────────
    if ($status === 'progress') {
        // Any action in progress → report becomes in_progress
        // But only if report is currently open
        $pdo->prepare("
            UPDATE hiyari_reports 
            SET status = 'in_progress', updated_at = NOW() 
            WHERE id = :id AND status = 'open'
        ")->execute([':id' => $report_id]);

    } elseif ($status === 'done') {
        // Check if ALL corrective actions are done
        $total = $pdo->prepare("SELECT COUNT(*) FROM corrective_actions WHERE report_id = :id");
        $total->execute([':id' => $report_id]);
        $total_count = (int)$total->fetchColumn();

        $done = $pdo->prepare("SELECT COUNT(*) FROM corrective_actions WHERE report_id = :id AND status = 'done'");
        $done->execute([':id' => $report_id]);
        $done_count = (int)$done->fetchColumn();

        if ($total_count > 0 && $total_count === $done_count) {
            // All actions done → report becomes closed
            $pdo->prepare("
                UPDATE hiyari_reports 
                SET status = 'closed', updated_at = NOW() 
                WHERE id = :id
            ")->execute([':id' => $report_id]);
        } else {
            // Some actions still pending → report stays in_progress
            $pdo->prepare("
                UPDATE hiyari_reports 
                SET status = 'in_progress', updated_at = NOW() 
                WHERE id = :id AND status = 'open'
            ")->execute([':id' => $report_id]);
        }

    } elseif ($status === 'open') {
        // Action reopened → report goes back to in_progress
        $pdo->prepare("
            UPDATE hiyari_reports 
            SET status = 'in_progress', updated_at = NOW() 
            WHERE id = :id AND status = 'closed'
        ")->execute([':id' => $report_id]);
    }

    header('Location: view?id=' . $report_id . '&success=action_updated');
} catch (PDOException $e) {
    header('Location: view?id=' . $report_id . '&error=1');
}
exit;