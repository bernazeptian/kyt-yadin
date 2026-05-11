<?php
session_start();
require_once '../config/db.php';
require_once '../config/mail.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../auth/login'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index'); exit; }

csrf_verify();

$report_id = (int)($_POST['report_id'] ?? 0);
$comment   = trim($_POST['comment']    ?? '');
$uid       = (int)$_SESSION['user_id'];
$role      = (int)$_SESSION['role'];

if (!$report_id || !$comment) {
    header('Location: view?id=' . $report_id . '&error=1'); exit;
}

// Check access — only reporter or admin
$stmt = $pdo->prepare("SELECT created_by FROM hiyari_reports WHERE id = :id");
$stmt->execute([':id' => $report_id]);
$report = $stmt->fetch();

if (!$report) { header('Location: index'); exit; }

$is_admin    = $role <= 2;
$is_reporter = $uid === (int)$report['created_by'];

// Check if assigned to corrective action on this report
$assigned = $pdo->prepare("SELECT COUNT(*) FROM corrective_actions WHERE report_id = :rid AND pic_user_id = :uid");
$assigned->execute([':rid' => $report_id, ':uid' => $uid]);
$is_assigned = (int)$assigned->fetchColumn() > 0;

if (!$is_admin && !$is_reporter && !$is_assigned) {
    header('Location: index'); exit;
}

try {
    $pdo->prepare("
        INSERT INTO hiyari_report_comments (report_id, user_id, comment, created_at)
        VALUES (:report_id, :user_id, :comment, NOW())
    ")->execute([
        ':report_id' => $report_id,
        ':user_id'   => $uid,
        ':comment'   => $comment,
    ]);

    // Fetch report info
    $report_data = $pdo->prepare("
        SELECT r.report_number, r.created_by,
               u.name AS reporter_name, u.email AS reporter_email
        FROM hiyari_reports r
        LEFT JOIN users u ON r.created_by = u.id
        WHERE r.id = :id
    ");
    $report_data->execute([':id' => $report_id]);
    $r = $report_data->fetch();

    $commenter_name = $_SESSION['name'] ?? 'Someone';
    $view_url       = APP_URL . '/hiyari/view?id=' . $report_id;
    $notif_title    = 'New Comment on ' . $r['report_number'];
    $notif_msg      = $commenter_name . ': ' . substr($comment, 0, 80) . (strlen($comment) > 80 ? '...' : '');
    $notif_url      = '/hiyari/view?id=' . $report_id;

    // ── Bell notification to ALL parties ─────────
    $notified_ids = [];

    // Notify admins via bell
    $admins = $pdo->query("SELECT id FROM users WHERE role <= 2 AND is_active = 1")->fetchAll();
    foreach ($admins as $a) {
        if ($a['id'] !== $uid) {
            createNotification($pdo, $a['id'], $notif_title, $notif_msg, 'info', $notif_url);
            $notified_ids[] = $a['id'];
        }
    }

    // Notify reporter via bell (if not already notified)
    $reporter_id = (int)$r['created_by'];
    if ($reporter_id && $reporter_id !== $uid && !in_array($reporter_id, $notified_ids)) {
        createNotification($pdo, $reporter_id, $notif_title, $notif_msg, 'info', $notif_url);
        $notified_ids[] = $reporter_id;
    }

    // Notify assigned users via bell
    $assigned_users = $pdo->prepare("
        SELECT DISTINCT pic_user_id FROM corrective_actions
        WHERE report_id = :rid AND pic_user_id IS NOT NULL
    ");
    $assigned_users->execute([':rid' => $report_id]);
    foreach ($assigned_users->fetchAll() as $au) {
        if ($au['pic_user_id'] !== $uid && !in_array($au['pic_user_id'], $notified_ids)) {
            createNotification($pdo, $au['pic_user_id'], $notif_title, $notif_msg, 'info', $notif_url);
            $notified_ids[] = $au['pic_user_id'];
        }
    }

    // ── Email ONLY to reporter ────────────────────
    if ($reporter_id && $reporter_id !== $uid && !empty($r['reporter_email'])) {
        $email_body = "
        <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#fff;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden'>
          <div style='background:#2563eb;padding:24px 32px'>
            <h1 style='color:#fff;margin:0;font-size:18px'>💬 New Comment on Your Report</h1>
            <p style='color:rgba(255,255,255,0.85);margin:6px 0 0;font-size:13px'>YADIN Safety Report Management System</p>
          </div>
          <div style='padding:28px 32px'>
            <p style='color:#333;font-size:14px;'>Dear <strong>" . htmlspecialchars($r['reporter_name']) . "</strong>,</p>
            <p style='color:#333;font-size:14px;'><strong>" . htmlspecialchars($commenter_name) . "</strong> added a comment on report <strong>" . htmlspecialchars($r['report_number']) . "</strong>:</p>
            <blockquote style='border-left:3px solid #2563eb;padding:10px 16px;color:#555;margin:16px 0;background:#f0f4ff;border-radius:0 8px 8px 0;'>
              " . nl2br(htmlspecialchars($comment)) . "
            </blockquote>
            <a href='{$view_url}' style='display:inline-block;background:#2563eb;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;font-size:14px;'>View Report →</a>
          </div>
          <div style='background:#f7f7f7;padding:16px 32px;font-size:12px;color:#999;border-top:1px solid #e0e0e0'>
            This is an automated notification from YADIN Safety Report Management System.
          </div>
        </div>";
        sendMail($r['reporter_email'], 'New Comment on Report ' . $r['report_number'], $email_body);
    }

    header('Location: view?id=' . $report_id . '&success=comment_added');
} catch (PDOException $e) {
    header('Location: view?id=' . $report_id . '&error=1');
}
exit;