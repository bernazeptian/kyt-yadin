<?php
session_start();
require_once '../config/db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login');
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: create');
    exit;
}

// ── Sanitize & validate inputs ──────────────────
$report_number = trim($_POST['report_number'] ?? '');
$report_date = trim($_POST['report_date'] ?? '');
$department_id = (int) ($_POST['department_id'] ?? 0);
$location_id = (int) ($_POST['location_id'] ?? 0);
$category = trim($_POST['category'] ?? '');
$description = trim($_POST['description'] ?? '');
$likelihood = trim($_POST['likelihood'] ?? '');  // A-E
$severity = (int) ($_POST['severity_val'] ?? 0);  // 1-5
$risk_level = trim($_POST['risk_level'] ?? ''); // low/medium/high/extreme
$created_by = $_SESSION['user_id'];

// Basic validation
if (!$report_date || !$department_id || !$location_id || !$category || !$description || !$likelihood || !$severity || !$risk_level) {
    header('Location: create?error=1');
    exit;
}

// Allowed values
$allowed_categories = ['near_miss', 'unsafe_act', 'unsafe_condition'];
$allowed_likelihoods = ['A', 'B', 'C', 'D', 'E'];
$allowed_risks = ['low', 'medium', 'high', 'extreme'];

if (
    !in_array($category, $allowed_categories) ||
    !in_array($likelihood, $allowed_likelihoods) ||
    !in_array($risk_level, $allowed_risks) ||
    $severity < 1 || $severity > 5
) {
    header('Location: create?error=1');
    exit;
}

try {
    // ── Insert report ───────────────────────────
    $stmt = $pdo->prepare("
        INSERT INTO hiyari_reports
            (report_number, report_date, department_id, location_id,
             category, description, likelihood, severity, risk_level,
             status, created_by, created_at, updated_at)
        VALUES
            (:report_number, :report_date, :department_id, :location_id,
             :category, :description, :likelihood, :severity, :risk_level,
             'open', :created_by, NOW(), NOW())
    ");

    $stmt->execute([
        ':report_number' => $report_number,
        ':report_date' => $report_date,
        ':department_id' => $department_id,
        ':location_id' => $location_id,
        ':category' => $category,
        ':description' => $description,
        ':likelihood' => $likelihood,
        ':severity' => $severity,
        ':risk_level' => $risk_level,
        ':created_by' => $created_by,
    ]);

    $report_id = $pdo->lastInsertId();

    // ── Handle photo uploads ─────────────────────
    if (!empty($_FILES['photos']['name'][0])) {
        $upload_dir = '../uploads/hiyari/';

        // Create upload folder if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        $max_photos = 5;
        $uploaded = 0;

        foreach ($_FILES['photos']['tmp_name'] as $key => $tmp) {
            if ($uploaded >= $max_photos)
                break;
            if ($_FILES['photos']['error'][$key] !== 0)
                continue;

            $file_size = $_FILES['photos']['size'][$key];
            $file_type = mime_content_type($tmp);

            // Validate type and size
            if (!in_array($file_type, $allowed_types))
                continue;
            if ($file_size > $max_size)
                continue;

            $ext = pathinfo($_FILES['photos']['name'][$key], PATHINFO_EXTENSION);
            $filename = 'hiyari_' . $report_id . '_' . uniqid() . '.' . strtolower($ext);
            $dest = $upload_dir . $filename;

            if (move_uploaded_file($tmp, $dest)) {
                $photoStmt = $pdo->prepare("
                    INSERT INTO hiyari_report_photos (report_id, file_path, uploaded_by, created_at)
                    VALUES (:report_id, :file_path, :uploaded_by, NOW())
                ");
                $photoStmt->execute([
                    ':report_id' => $report_id,
                    ':file_path' => 'uploads/hiyari/' . $filename,
                    ':uploaded_by' => $created_by,
                ]);
                $uploaded++;
            }
        }
    }

    // ── Extreme risk: email President Director, Director, GM ─────
    if ($risk_level === 'extreme') {
        require_once '../config/mail.php';

        $extremeTargets = ['President Director', 'Director', 'General Manager'];
        $placeholders = implode(',', array_fill(0, count($extremeTargets), '?'));
        $notifStmt = $pdo->prepare("SELECT name, email FROM users WHERE position IN ($placeholders) AND is_active = 1");
        $notifStmt->execute($extremeTargets);
        $notifUsers = $notifStmt->fetchAll();

        if (!empty($notifUsers)) {
            $reportUrl = APP_URL . '/hiyari/view?id=' . $report_id;
            $subject = '⚠️ EXTREME Risk Report Submitted – ' . $report_number;
            $body = "
                <p>Dear Leadership,</p>
                <p>An <strong>EXTREME</strong> risk hazard report has been submitted and requires your immediate attention.</p>
                <table style='border-collapse:collapse;font-size:14px;'>
                    <tr><td style='padding:4px 12px 4px 0;color:#666;'>Report No.</td><td><strong>" . htmlspecialchars($report_number) . "</strong></td></tr>
                    <tr><td style='padding:4px 12px 4px 0;color:#666;'>Date</td><td>" . htmlspecialchars($report_date) . "</td></tr>
                    <tr><td style='padding:4px 12px 4px 0;color:#666;'>Description</td><td>" . htmlspecialchars(substr($description, 0, 200)) . "...</td></tr>
                    <tr><td style='padding:4px 12px 4px 0;color:#666;'>Risk Level</td><td><span style='color:#dc2626;font-weight:bold;'>EXTREME</span></td></tr>
                </table>
                <p style='margin-top:16px;'>
                    <a href='{$reportUrl}' style='background:#dc2626;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;font-weight:bold;'>
                        View Report
                    </a>
                </p>
                <p style='color:#888;font-size:12px;margin-top:24px;'>This is an automated notification from KYT Yadin System.</p>
            ";

            foreach ($notifUsers as $nu) {
                sendMail($nu['email'], $subject, $body);
            }
        }
    }

    // ── Success: redirect to view page ──────────
    header('Location: view?id=' . $report_id . '&success=1');
    exit;

    // DEBUG — remove after fixing
    die('
    report_number: ' . $report_number . '<br>
    report_date: ' . $report_date . '<br>
    department_id: ' . $department_id . '<br>
    location_id: ' . $location_id . '<br>
    category: ' . $category . '<br>
    likelihood: ' . $likelihood . '<br>
    severity: ' . $severity . '<br>
    risk_level: ' . $risk_level . '<br>
    created_by: ' . $created_by . '<br>
');

} catch (PDOException $e) {
    die('❌ DB Error: ' . $e->getMessage());
}
?>