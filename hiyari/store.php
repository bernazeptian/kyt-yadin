<?php
session_start();
require_once '../config/db.php';
require_once '../config/mail.php';

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

csrf_verify();

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
$allowed_categories = ['near_miss', 'unsafe_action', 'unsafe_condition'];
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

    // ── Send Extreme Risk Alert ─────────────────
    if ($risk_level === 'extreme') {
        $reportStmt = $pdo->prepare("
            SELECT r.*, d.name AS dept_name, l.name AS loc_name, u.name AS reporter_name
            FROM hiyari_reports r
            LEFT JOIN departments d ON r.department_id = d.id
            LEFT JOIN locations l ON r.location_id = l.id
            LEFT JOIN users u ON r.created_by = u.id
            WHERE r.id = :id
        ");
        $reportStmt->execute([':id' => $report_id]);
        $report_data = $reportStmt->fetch();

        if ($report_data) {
            sendExtremeRiskAlert($pdo, $report_data);
        }
    }

    // ── Success: redirect to view page ──────────
    header('Location: view?id=' . $report_id . '&success=1');
    exit;

} catch (PDOException $e) {
    header('Location: create?error=db');
    exit;
}
?>