<?php
session_start();
require_once '../config/db.php';
require_once '../config/notifications.php';
require_once '../config/mail.php';

if (!isset($_SESSION['user_id']) || (int)$_SESSION['role'] > 2) {
    header('Location: ../auth/login'); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index'); exit;
}

csrf_verify();

$report_id   = (int)($_POST['report_id']   ?? 0);
$description = trim($_POST['description']  ?? '');
$assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
$due_date    = !empty($_POST['due_date'])    ? $_POST['due_date'] : null;

if (!$report_id || !$description) {
    header('Location: view?id=' . $report_id . '&error=1'); exit;
}

try {
    // INSERT first with null image_path
    $pdo->prepare("
        INSERT INTO corrective_actions (report_id, action_desc, pic_user_id, due_date, status, image_path, created_at, updated_at)
        VALUES (:report_id, :description, :assigned_to, :due_date, 'open', NULL, NOW(), NOW())
    ")->execute([
        ':report_id'   => $report_id,
        ':description' => $description,
        ':assigned_to' => $assigned_to,
        ':due_date'    => $due_date,
    ]);

    $action_id  = $pdo->lastInsertId();
    $image_path = null;

    // ── Handle image upload after INSERT ─────────
    if (!empty($_FILES['action_image']['name']) && $_FILES['action_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir  = dirname(__DIR__) . '/uploads/corrective_actions/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $allowed_ext = ['jpg','jpeg','png','gif','webp'];
        $ext         = strtolower(pathinfo($_FILES['action_image']['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $allowed_ext) && $_FILES['action_image']['size'] <= 5 * 1024 * 1024) {
            $filename = 'action_' . $action_id . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['action_image']['tmp_name'], $upload_dir . $filename)) {
                chmod($upload_dir . $filename, 0644);
                $image_path = 'uploads/corrective_actions/' . $filename;
                $pdo->prepare("UPDATE corrective_actions SET image_path = :img WHERE id = :id")
                    ->execute([':img' => $image_path, ':id' => $action_id]);
            }
        }
    }

    // ── Notify assigned user via bell + email ────
    if ($assigned_to) {
        $r = $pdo->prepare("
            SELECT r.report_number, u.name AS assignee_name, u.email AS assignee_email
            FROM hiyari_reports r
            LEFT JOIN users u ON u.id = :uid
            WHERE r.id = :id
        ");
        $r->execute([':id' => $report_id, ':uid' => $assigned_to]);
        $report = $r->fetch();

        $title   = 'New Corrective Action Assigned';
        $message = 'You have been assigned a corrective action for report ' . ($report['report_number'] ?? '') . ': ' . substr($description, 0, 80) . '...';
        $url     = '/hiyari/view?id=' . $report_id;

        createNotification($pdo, $assigned_to, $title, $message, 'warning', $url);

        // Email the assignee
        if (!empty($report['assignee_email'])) {
            $view_url  = APP_URL . '/hiyari/view?id=' . $report_id;
            $due_label = $due_date ? date('d F Y', strtotime($due_date)) : 'Not set';
            $email_body = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#fff;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden'>
              <div style='background:#e67e22;padding:24px 32px'>
                <h1 style='color:#fff;margin:0;font-size:18px'>Corrective Action Assigned to You</h1>
                <p style='color:rgba(255,255,255,0.85);margin:6px 0 0;font-size:13px'>YADIN Safety Report Management System</p>
              </div>
              <div style='padding:28px 32px'>
                <p style='color:#333;font-size:14px;'>Dear <strong>" . htmlspecialchars($report['assignee_name'] ?? '') . "</strong>,</p>
                <p style='color:#333;font-size:14px;'>A corrective action has been assigned to you for report <strong>" . htmlspecialchars($report['report_number'] ?? '') . "</strong>.</p>
                <table style='width:100%;border-collapse:collapse;font-size:14px;color:#444;margin:20px 0'>
                  <tr style='background:#fef9f0'><td style='padding:10px 14px;font-weight:bold;width:38%'>Action</td><td style='padding:10px 14px'>" . htmlspecialchars($description) . "</td></tr>
                  <tr><td style='padding:10px 14px;font-weight:bold'>Due Date</td><td style='padding:10px 14px'><strong>" . $due_label . "</strong></td></tr>
                </table>
                <a href='{$view_url}' style='display:inline-block;background:#e67e22;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;font-size:14px;'>View Report →</a>
              </div>
              <div style='background:#f7f7f7;padding:16px 32px;font-size:12px;color:#999;border-top:1px solid #e0e0e0'>
                This is an automated notification from YADIN Safety Report Management System.
              </div>
            </div>";
            sendMail($report['assignee_email'], 'Corrective Action Assigned — ' . ($report['report_number'] ?? ''), $email_body);
        }
    }

auditLog($pdo, 'ACTION_ADDED', 'hiyari', $report_id, 'Report #' . $report_id . ': ' . substr($description, 0, 50), '', '');

    header('Location: view?id=' . $report_id . '&success=action_added');
} catch (PDOException $e) {
    error_log('action_store error: ' . $e->getMessage());
    header('Location: view?id=' . $report_id . '&error=1');
}
exit;
