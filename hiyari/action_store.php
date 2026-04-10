<?php
session_start();
require_once '../config/db.php';
require_once '../config/notifications.php';

if (!isset($_SESSION['user_id']) || (int)$_SESSION['role'] > 2) { header('Location: ../auth/login'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index'); exit; }

$report_id   = (int)($_POST['report_id']   ?? 0);
$description = trim($_POST['description']   ?? '');
$assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
$due_date    = !empty($_POST['due_date'])    ? $_POST['due_date'] : null;

if (!$report_id || !$description) {
    header('Location: view?id=' . $report_id . '&error=1'); exit;
}

try {
    $pdo->prepare("
        INSERT INTO corrective_actions (report_id, action_desc, pic_user_id, due_date, status, created_at, updated_at)
        VALUES (:report_id, :description, :assigned_to, :due_date, 'open', NOW(), NOW())
    ")->execute([
        ':report_id'   => $report_id,
        ':description' => $description,
        ':assigned_to' => $assigned_to,
        ':due_date'    => $due_date,
    ]);

    // ── Notify assigned user ──────────────────
    if ($assigned_to) {
        // Get report number
        $r = $pdo->prepare("SELECT report_number FROM hiyari_reports WHERE id = :id");
        $r->execute([':id' => $report_id]);
        $report = $r->fetch();

        $title   = 'New Corrective Action Assigned';
        $message = 'You have been assigned a corrective action for report ' . ($report['report_number'] ?? '') . ': ' . substr($description, 0, 80) . '...';
        $url     = '/hiyari/view?id=' . $report_id;

        // In-app notification
        createNotification($pdo, $assigned_to, $title, $message, 'warning', $url);

        // Browser push notification
        sendPushNotification($pdo, $assigned_to, $title, $message, $url);
    }

    header('Location: view?id=' . $report_id . '&success=action_added');
} catch (PDOException $e) {
    die('❌ Error: ' . $e->getMessage());
}
exit;