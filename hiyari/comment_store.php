<?php
session_start();
require_once '../config/db.php';
require_once '../config/mail.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../auth/login'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index'); exit; }

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
        INSERT INTO report_comments (report_id, user_id, comment, created_at)
        VALUES (:report_id, :user_id, :comment, NOW())
    ")->execute([
        ':report_id' => $report_id,
        ':user_id'   => $uid,
        ':comment'   => $comment,
    ]);

    // Email notification to admin if comment is from staff/reporter
    if (!$is_admin) {
        $admins = $pdo->query("SELECT email, name FROM users WHERE role <= 2 AND is_active = 1")->fetchAll();
        $report_data = $pdo->prepare("SELECT report_number FROM hiyari_reports WHERE id = :id");
        $report_data->execute([':id' => $report_id]);
        $r = $report_data->fetch();

        foreach ($admins as $admin) {
            $link = APP_URL . '/hiyari/view?id=' . $report_id;
            $body = "
            <html><body style='font-family:sans-serif;padding:32px;max-width:480px;margin:auto'>
              <h3 style='color:#1a73e8'>New Comment on {$r['report_number']}</h3>
              <p><strong>{$_SESSION['name']}</strong> added a comment:</p>
              <blockquote style='border-left:3px solid #1a73e8;padding:8px 16px;color:#5f6368;margin:16px 0'>
                " . htmlspecialchars($comment) . "
              </blockquote>
              <a href='{$link}' style='display:inline-block;background:#1a73e8;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600'>View Report</a>
            </body></html>";
            sendMail($admin['email'], "New Comment — {$r['report_number']}", $body);
        }
    }

    header('Location: view?id=' . $report_id . '&success=comment_added');
} catch (PDOException $e) {
    header('Location: view?id=' . $report_id . '&error=1');
}
exit;