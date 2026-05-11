<?php
session_start();
require_once '../config/db.php';
require_once '../config/mail.php';
require_once '../config/notifications.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../auth/login'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index'); exit; }

csrf_verify();

// Only SuperAdmin (Gunawan) can approve
if ((int)$_SESSION['role'] !== 1) {
    header('Location: index');
    exit;
}

$report_id   = (int)($_POST['report_id'] ?? 0);
$due_date    = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
$description = trim($_POST['description'] ?? '');
$safe_action = trim($_POST['safe_action'] ?? '');
$reviewer_id = (int)$_SESSION['user_id'];

$action = trim($_POST['action'] ?? 'approve'); // approve or reject
$reject_reason = trim($_POST['reject_reason'] ?? '');

if (!$report_id) { header('Location: index'); exit; }

// Fetch report
$stmt = $pdo->prepare("
    SELECT r.*, d.name AS dept_name, l.name AS loc_name,
           u.name AS reporter_name, u.email AS reporter_email,
           d.head_id AS dept_head_id, l.pic_area_id
    FROM hiyari_reports r
    LEFT JOIN departments d ON r.department_id = d.id
    LEFT JOIN locations   l ON r.location_id   = l.id
    LEFT JOIN users       u ON r.created_by    = u.id
    WHERE r.id = :id
");
$stmt->execute([':id' => $report_id]);
$report = $stmt->fetch();

if (!$report || $report['status'] !== 'pending_review') {
    header('Location: view?id=' . $report_id . '&error=already_reviewed');
    exit;
}

// ── Handle REJECT ─────────────────────────────
if ($action === 'reject') {
    try {
        $pdo->prepare("
            UPDATE hiyari_reports
            SET status = 'closed', reviewed_by = :reviewed_by, reviewed_at = NOW(), updated_at = NOW()
            WHERE id = :id
        ")->execute([':reviewed_by' => $reviewer_id, ':id' => $report_id]);

        // Notify reporter via bell
        createNotification($pdo, $report['created_by'], 'Report Rejected: ' . $report['report_number'],
            'Your report has been rejected' . ($reject_reason ? ': ' . substr($reject_reason, 0, 80) : ''),
            'warning', '/hiyari/view?id=' . $report_id);

        // Email reporter
        if (!empty($report['reporter_email'])) {
            $reject_body = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#fff;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden'>
              <div style='background:#e74c3c;padding:24px 32px'>
                <h1 style='color:#fff;margin:0;font-size:18px'>❌ Report Rejected</h1>
                <p style='color:rgba(255,255,255,0.85);margin:6px 0 0;font-size:13px'>YADIN Safety Report Management System</p>
              </div>
              <div style='padding:28px 32px'>
                <p style='color:#333;font-size:14px;'>Dear <strong>" . htmlspecialchars($report['reporter_name']) . "</strong>,</p>
                <p style='color:#333;font-size:14px;'>Your report <strong>" . htmlspecialchars($report['report_number']) . "</strong> has been reviewed and rejected.</p>
                " . ($reject_reason ? "<div style='background:#fdf2f2;padding:12px 16px;border-radius:8px;border-left:4px solid #e74c3c;margin:16px 0;font-size:14px;color:#555;'>
                  <strong>Reason:</strong> " . htmlspecialchars($reject_reason) . "</div>" : "") . "
                <a href='" . APP_URL . "/hiyari/view?id={$report_id}' style='display:inline-block;background:#e74c3c;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;font-size:14px;'>View Report →</a>
              </div>
              <div style='background:#f7f7f7;padding:16px 32px;font-size:12px;color:#999;border-top:1px solid #e0e0e0'>
                This is an automated notification from YADIN Safety Report Management System.
              </div>
            </div>";
            sendMail($report['reporter_email'], 'Report ' . $report['report_number'] . ' Rejected', $reject_body);
        }

        header('Location: view?id=' . $report_id . '&success=rejected');
    } catch (PDOException $e) {
        error_log('reject error: ' . $e->getMessage());
        header('Location: view?id=' . $report_id . '&error=reject_failed');
    }
    exit;
}

try {
    // Update report: set active, due_date, reviewed_by, and any edits
    $updateFields = [
        'status'      => 'active',
        'due_date'    => $due_date,
        'reviewed_by' => $reviewer_id,
        'reviewed_at' => date('Y-m-d H:i:s'),
        'updated_at'  => date('Y-m-d H:i:s'),
    ];
    if ($description) $updateFields['description'] = $description;
    if ($safe_action) $updateFields['safe_action']  = $safe_action;

    $pdo->prepare("
        UPDATE hiyari_reports
        SET status = 'open', due_date = :due_date,
            reviewed_by = :reviewed_by, reviewed_at = :reviewed_at,
            description = COALESCE(NULLIF(:description,''), description),
            safe_action = COALESCE(NULLIF(:safe_action,''), safe_action),
            updated_at = NOW()
        WHERE id = :id
    ")->execute([
        ':due_date'    => $due_date,
        ':reviewed_by' => $reviewer_id,
        ':reviewed_at' => date('Y-m-d H:i:s'),
        ':description' => $description,
        ':safe_action' => $safe_action,
        ':id'          => $report_id,
    ]);

    // Re-fetch updated report
    $stmt->execute([':id' => $report_id]);
    $report = $stmt->fetch();

    $view_url    = APP_URL . '/hiyari/view?id=' . $report_id;
    $type_label  = $report['report_type'] === 'hiyari' ? 'Hiyari Hatto' : 'Kiken Yochi';
    $type_color  = $report['report_type'] === 'hiyari' ? '#c0392b' : '#f39c12';
    $risk_colors = ['low'=>'#27ae60','medium'=>'#f39c12','high'=>'#e67e22','extreme'=>'#c0392b'];
    $risk_color  = $risk_colors[$report['risk_level']] ?? '#333';
    $cat_map     = ['near_miss'=>'Near Miss (Hiyari Hatto)','unsafe_action'=>'Unsafe Act','unsafe_condition'=>'Unsafe Condition'];

    // Build email body
    $email_body = "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#fff;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden'>
      <div style='background:{$type_color};padding:24px 32px'>
        <h1 style='color:#fff;margin:0;font-size:18px'>📋 {$type_label} Report Released — Action Required</h1>
        <p style='color:rgba(255,255,255,0.85);margin:6px 0 0;font-size:13px'>YADIN Safety Report Management System</p>
      </div>
      <div style='padding:28px 32px'>
        <p style='color:#333;font-size:14px;margin-top:0'>A safety report has been reviewed and released by management. Please take the necessary corrective actions.</p>
        <table style='width:100%;border-collapse:collapse;font-size:14px;color:#444;margin-bottom:24px'>
          <tr style='background:#f8f9fa'><td style='padding:10px 14px;font-weight:bold;width:38%'>Report No.</td><td style='padding:10px 14px'>" . htmlspecialchars($report['report_number']) . "</td></tr>
          <tr><td style='padding:10px 14px;font-weight:bold'>Type</td><td style='padding:10px 14px'>{$type_label}</td></tr>
          <tr style='background:#f8f9fa'><td style='padding:10px 14px;font-weight:bold'>Date</td><td style='padding:10px 14px'>" . date('d F Y', strtotime($report['report_date'])) . "</td></tr>
          <tr><td style='padding:10px 14px;font-weight:bold'>Department</td><td style='padding:10px 14px'>" . htmlspecialchars($report['dept_name'] ?? '—') . "</td></tr>
          <tr style='background:#f8f9fa'><td style='padding:10px 14px;font-weight:bold'>Location</td><td style='padding:10px 14px'>" . htmlspecialchars($report['loc_name'] ?? '—') . "</td></tr>
          <tr><td style='padding:10px 14px;font-weight:bold'>Category</td><td style='padding:10px 14px'>" . htmlspecialchars($cat_map[$report['category']] ?? $report['category']) . "</td></tr>
          <tr style='background:#f8f9fa'><td style='padding:10px 14px;font-weight:bold'>Risk Level</td><td style='padding:10px 14px'><strong style='color:{$risk_color}'>" . strtoupper($report['risk_level']) . "</strong></td></tr>
          " . ($due_date ? "<tr><td style='padding:10px 14px;font-weight:bold'>Due Date</td><td style='padding:10px 14px'><strong>" . date('d F Y', strtotime($due_date)) . "</strong></td></tr>" : "") . "
          <tr style='background:#f8f9fa'><td style='padding:10px 14px;font-weight:bold;vertical-align:top'>Description</td><td style='padding:10px 14px'>" . htmlspecialchars(substr($report['description'], 0, 300)) . "</td></tr>
        </table>
        <a href='{$view_url}' style='display:inline-block;background:{$type_color};color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;font-size:14px;'>View Full Report →</a>
      </div>
      <div style='background:#f7f7f7;padding:16px 32px;font-size:12px;color:#999;border-top:1px solid #e0e0e0'>
        This is an automated notification from YADIN Safety Report Management System.
      </div>
    </div>";

    $subject      = '📋 [' . strtoupper($report['report_type'] === 'hiyari' ? 'HIYARI HATTO' : 'KIKEN YOCHI') . '] Report ' . $report['report_number'] . ' Released — Action Required';
    $notified_ids = [];

    // 1. Notify all Admins
    $admins = $pdo->query("SELECT id, name, email FROM users WHERE role = 2 AND is_active = 1 AND email IS NOT NULL")->fetchAll();
    foreach ($admins as $a) {
        sendMail($a['email'], $subject, $email_body);
        createNotification($pdo, $a['id'], 'Report Released: ' . $report['report_number'], 'Report has been approved and is now active', 'success', '/hiyari/view?id=' . $report_id);
        $notified_ids[] = $a['id'];
    }

    // 2. Notify PIC Dept
    if (!empty($report['dept_head_id']) && !in_array($report['dept_head_id'], $notified_ids)) {
        $dh = $pdo->prepare("SELECT id, name, email FROM users WHERE id = :id AND is_active = 1");
        $dh->execute([':id' => $report['dept_head_id']]);
        $dh = $dh->fetch();
        if ($dh && $dh['email']) {
            sendMail($dh['email'], $subject, $email_body);
            createNotification($pdo, $dh['id'], 'Report Released in Your Department', $report['report_number'] . ' is now active', 'success', '/hiyari/view?id=' . $report_id);
            $notified_ids[] = $dh['id'];
        }
    }

    // 3. Notify PIC Area
    if (!empty($report['pic_area_id']) && !in_array($report['pic_area_id'], $notified_ids)) {
        $pa = $pdo->prepare("SELECT id, name, email FROM users WHERE id = :id AND is_active = 1");
        $pa->execute([':id' => $report['pic_area_id']]);
        $pa = $pa->fetch();
        if ($pa && $pa['email']) {
            sendMail($pa['email'], $subject, $email_body);
            createNotification($pdo, $pa['id'], 'Report Released in Your Area', $report['report_number'] . ' is now active', 'success', '/hiyari/view?id=' . $report_id);
            $notified_ids[] = $pa['id'];
        }
    }

    // 4. Notify leadership based on type/risk
    $notify_leadership = $report['report_type'] === 'hiyari' || $report['risk_level'] === 'extreme';
    if ($notify_leadership) {
        $targets = ['President Director', 'Director', 'General Manager'];
        $ph      = implode(',', array_fill(0, count($targets), '?'));
        $leaders = $pdo->prepare("SELECT id, name, email FROM users WHERE position IN ($ph) AND is_active = 1 AND email IS NOT NULL");
        $leaders->execute($targets);
        foreach ($leaders->fetchAll() as $l) {
            if (!in_array($l['id'], $notified_ids)) {
                sendMail($l['email'], $subject, $email_body);
                $notified_ids[] = $l['id'];
            }
        }
    }

    // 5. Notify Deputy Manager, Manager if Hiyari Hatto
    if ($report['report_type'] === 'hiyari') {
        $mgrs = ['Deputy Manager', 'Manager'];
        $ph   = implode(',', array_fill(0, count($mgrs), '?'));
        $mgr_users = $pdo->prepare("SELECT id, name, email FROM users WHERE position IN ($ph) AND is_active = 1 AND email IS NOT NULL");
        $mgr_users->execute($mgrs);
        foreach ($mgr_users->fetchAll() as $m) {
            if (!in_array($m['id'], $notified_ids)) {
                sendMail($m['email'], $subject, $email_body);
                $notified_ids[] = $m['id'];
            }
        }
    }

    header('Location: view?id=' . $report_id . '&success=approved');
} catch (PDOException $e) {
    error_log('approve error: ' . $e->getMessage());
    header('Location: view?id=' . $report_id . '&error=approve_failed');
}
exit;
