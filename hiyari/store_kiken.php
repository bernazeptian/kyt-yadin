<?php
session_start();
require_once '../config/db.php';
require_once '../config/mail.php';
require_once '../config/notifications.php';

if (!isset($_SESSION['user_id'])) { header('Location: ../auth/login'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: create_kiken'); exit; }

csrf_verify();

$report_number = trim($_POST['report_number'] ?? '');
$report_date   = trim($_POST['report_date']   ?? '');
$department_id = (int)($_POST['department_id'] ?? 0);
$location_id   = (int)($_POST['location_id']  ?? 0);
$category      = trim($_POST['category']      ?? '');
$description   = trim($_POST['description']   ?? '');
$safe_action   = trim($_POST['safe_action']   ?? '');
$likelihood    = trim($_POST['likelihood']    ?? '');
$severity      = (int)($_POST['severity_val'] ?? $_POST['severity'] ?? 0);
$risk_level    = trim($_POST['risk_level']    ?? '');
$created_by    = $_SESSION['user_id'];
$report_type   = 'kiken';

$allowed_categories  = ['unsafe_action', 'unsafe_condition'];
$allowed_likelihoods = ['A','B','C','D','E'];
$allowed_risks       = ['low','medium','high','extreme'];

if (!$report_date || !$department_id || !$location_id || !$category || !$description || !$safe_action || !$likelihood || !$severity || !$risk_level) {
    header('Location: create_kiken?error=1'); exit;
}
if (!in_array($category, $allowed_categories) || !in_array($likelihood, $allowed_likelihoods) || !in_array($risk_level, $allowed_risks) || $severity < 1 || $severity > 5) {
    header('Location: create_kiken?error=1'); exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO hiyari_reports
            (report_type, report_number, report_date, department_id, location_id,
             category, description, safe_action, likelihood, severity, risk_level,
             status, created_by, created_at, updated_at)
        VALUES
            (:report_type, :report_number, :report_date, :department_id, :location_id,
             :category, :description, :safe_action, :likelihood, :severity, :risk_level,
             'pending_review', :created_by, NOW(), NOW())
    ");
    $stmt->execute([
        ':report_type'   => $report_type,
        ':report_number' => $report_number,
        ':report_date'   => $report_date,
        ':department_id' => $department_id,
        ':location_id'   => $location_id,
        ':category'      => $category,
        ':description'   => $description,
        ':safe_action'   => $safe_action,
        ':likelihood'    => $likelihood,
        ':severity'      => $severity,
        ':risk_level'    => $risk_level,
        ':created_by'    => $created_by,
    ]);

    $report_id = $pdo->lastInsertId();

    // ── Photo uploads ─────────────────────────────
    if (!empty($_FILES['photos']['name'][0])) {
        $upload_dir = dirname(__DIR__) . '/uploads/hiyari/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $allowed_ext = ['jpg','jpeg','png','gif','webp'];
        $uploaded = 0;
        foreach ($_FILES['photos']['tmp_name'] as $key => $tmp) {
            if ($uploaded >= 5) break;
            if ($_FILES['photos']['error'][$key] !== 0) continue;
            if ($_FILES['photos']['size'][$key] > 5 * 1024 * 1024) continue;
            $ext = strtolower(pathinfo($_FILES['photos']['name'][$key], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_ext)) continue;
            $filename = 'kiken_' . $report_id . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($tmp, $upload_dir . $filename)) {
                chmod($upload_dir . $filename, 0644);
                $pdo->prepare("INSERT INTO hiyari_report_photos (report_id, file_path, uploaded_by, created_at) VALUES (:rid, :fp, :uid, NOW())")
                    ->execute([':rid' => $report_id, ':fp' => 'uploads/hiyari/' . $filename, ':uid' => $created_by]);
                $uploaded++;
            }
        }
    }

    // ── Fetch full report data ─────────────────────
    $rStmt = $pdo->prepare("
        SELECT r.*, d.name AS dept_name, l.name AS loc_name, u.name AS reporter_name,
               d.head_id AS dept_head_id, l.pic_area_id
        FROM hiyari_reports r
        LEFT JOIN departments d ON r.department_id = d.id
        LEFT JOIN locations   l ON r.location_id   = l.id
        LEFT JOIN users       u ON r.created_by    = u.id
        WHERE r.id = :id
    ");
    $rStmt->execute([':id' => $report_id]);
    $report_data = $rStmt->fetch();

    $view_url    = APP_URL . '/hiyari/view?id=' . $report_id;
    $risk_colors = ['low'=>'#27ae60','medium'=>'#f39c12','high'=>'#e67e22','extreme'=>'#c0392b'];
    $risk_color  = $risk_colors[$risk_level] ?? '#333';
    $cat_map     = ['unsafe_action'=>'Unsafe Act','unsafe_condition'=>'Unsafe Condition'];

    $email_body = "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#fff;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden'>
      <div style='background:#f39c12;padding:24px 32px'>
        <h1 style='color:#fff;margin:0;font-size:18px'>⚠️ New Kiken Yochi Report Submitted</h1>
        <p style='color:rgba(255,255,255,0.85);margin:6px 0 0;font-size:13px'>YADIN Safety Report Management System</p>
      </div>
      <div style='padding:28px 32px'>
        <table style='width:100%;border-collapse:collapse;font-size:14px;color:#444;margin-bottom:24px'>
          <tr style='background:#fef9f0'><td style='padding:10px 14px;font-weight:bold;width:38%'>Report No.</td><td style='padding:10px 14px'>" . htmlspecialchars($report_data['report_number']) . "</td></tr>
          <tr><td style='padding:10px 14px;font-weight:bold'>Date</td><td style='padding:10px 14px'>" . date('d F Y', strtotime($report_data['report_date'])) . "</td></tr>
          <tr style='background:#fef9f0'><td style='padding:10px 14px;font-weight:bold'>Department</td><td style='padding:10px 14px'>" . htmlspecialchars($report_data['dept_name'] ?? '—') . "</td></tr>
          <tr><td style='padding:10px 14px;font-weight:bold'>Location</td><td style='padding:10px 14px'>" . htmlspecialchars($report_data['loc_name'] ?? '—') . "</td></tr>
          <tr style='background:#fef9f0'><td style='padding:10px 14px;font-weight:bold'>Category</td><td style='padding:10px 14px'>" . htmlspecialchars($cat_map[$category] ?? $category) . "</td></tr>
          <tr><td style='padding:10px 14px;font-weight:bold'>Risk Level</td><td style='padding:10px 14px'><strong style='color:{$risk_color}'>" . strtoupper($risk_level) . "</strong></td></tr>
          <tr style='background:#fef9f0'><td style='padding:10px 14px;font-weight:bold'>Reported By</td><td style='padding:10px 14px'>" . htmlspecialchars($report_data['reporter_name'] ?? '—') . "</td></tr>
          <tr><td style='padding:10px 14px;font-weight:bold;vertical-align:top'>Description</td><td style='padding:10px 14px'>" . htmlspecialchars(substr($report_data['description'], 0, 300)) . "</td></tr>
        </table>
        <a href='{$view_url}' style='display:inline-block;background:#f39c12;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;font-size:14px;'>View Report →</a>
      </div>
      <div style='background:#f7f7f7;padding:16px 32px;font-size:12px;color:#999;border-top:1px solid #e0e0e0'>
        This is an automated notification from YADIN Safety Report Management System.
      </div>
    </div>";

    $subject      = '⚠️ [KIKEN YOCHI] New Report ' . $report_data['report_number'] . ' — Pending Review';
    $notified_ids = [];

    // 1. Notify all SuperAdmin & Admin
    $admins = $pdo->query("SELECT id, name, email FROM users WHERE role <= 2 AND is_active = 1 AND email IS NOT NULL")->fetchAll();
    foreach ($admins as $a) {
        sendMail($a['email'], $subject, $email_body);
        createNotification($pdo, $a['id'], 'New Kiken Yochi Report', 'Report ' . $report_data['report_number'] . ' submitted — Pending Review', 'info', '/hiyari/view?id=' . $report_id);
        $notified_ids[] = $a['id'];
    }

    // 2. Notify PIC Dept
    if (!empty($report_data['dept_head_id']) && !in_array($report_data['dept_head_id'], $notified_ids)) {
        $dh = $pdo->prepare("SELECT id, name, email FROM users WHERE id = :id AND is_active = 1");
        $dh->execute([':id' => $report_data['dept_head_id']]);
        $dh = $dh->fetch();
        if ($dh && $dh['email']) {
            sendMail($dh['email'], $subject, $email_body);
            createNotification($pdo, $dh['id'], 'New Report in Your Department', 'Report ' . $report_data['report_number'] . ' submitted', 'info', '/hiyari/view?id=' . $report_id);
            $notified_ids[] = $dh['id'];
        }
    }

    // 3. Notify PIC Area
    if (!empty($report_data['pic_area_id']) && !in_array($report_data['pic_area_id'], $notified_ids)) {
        $pa = $pdo->prepare("SELECT id, name, email FROM users WHERE id = :id AND is_active = 1");
        $pa->execute([':id' => $report_data['pic_area_id']]);
        $pa = $pa->fetch();
        if ($pa && $pa['email']) {
            sendMail($pa['email'], $subject, $email_body);
            createNotification($pdo, $pa['id'], 'New Report in Your Area', 'Report ' . $report_data['report_number'] . ' submitted', 'info', '/hiyari/view?id=' . $report_id);
            $notified_ids[] = $pa['id'];
        }
    }

    // 4. If extreme, also notify President Director, Director, GM
    if ($risk_level === 'extreme') {
        $targets = ['President Director', 'Director', 'General Manager'];
        $ph      = implode(',', array_fill(0, count($targets), '?'));
        $leaders = $pdo->prepare("SELECT id, name, email FROM users WHERE position IN ($ph) AND is_active = 1 AND email IS NOT NULL");
        $leaders->execute($targets);
        foreach ($leaders->fetchAll() as $l) {
            if (!in_array($l['id'], $notified_ids)) {
                sendMail($l['email'], '🚨 [EXTREME RISK] ' . $subject, $email_body);
                $notified_ids[] = $l['id'];
            }
        }
    }

    header('Location: view?id=' . $report_id . '&success=1');
} catch (PDOException $e) {
    error_log('store_kiken error: ' . $e->getMessage());
    header('Location: create_kiken?error=db');
}
exit;
