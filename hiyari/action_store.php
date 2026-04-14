<?php
session_start();
require_once '../config/db.php';
require_once '../config/notifications.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['role'] > 2) {
    header('Location: ../auth/login');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index');
    exit;
}

$report_id = (int) ($_POST['report_id'] ?? 0);
$description = trim($_POST['description'] ?? '');
$assigned_to = !empty($_POST['assigned_to']) ? (int) $_POST['assigned_to'] : null;
$due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

if (!$report_id || !$description) {
    header('Location: view?id=' . $report_id . '&error=1');
    exit;
}

// ── Handle image upload (simple) ─────────────
$image_path = null;
if (!empty($_FILES['action_image']['name']) && $_FILES['action_image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../uploads/corrective_actions/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($_FILES['action_image']['name'], PATHINFO_EXTENSION));

    if (in_array($ext, $allowed_ext) && $_FILES['action_image']['size'] <= 5 * 1024 * 1024) {
        $filename = 'action_' . $report_id . '_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES['action_image']['tmp_name'], $upload_dir . $filename)) {
            $image_path = 'uploads/corrective_actions/' . $filename;
        }
    }
}

try {
    $pdo->prepare("
        INSERT INTO corrective_actions (report_id, action_desc, pic_user_id, due_date, status, image_path, created_at, updated_at)
        VALUES (:report_id, :description, :assigned_to, :due_date, 'open', :image_path, NOW(), NOW())
    ")->execute([
                ':report_id' => $report_id,
                ':description' => $description,
                ':assigned_to' => $assigned_to,
                ':due_date' => $due_date,
                ':image_path' => $image_path,
            ]);

    // ── Notify assigned user ──────────────────
    if ($assigned_to) {
        $r = $pdo->prepare("SELECT report_number FROM hiyari_reports WHERE id = :id");
        $r->execute([':id' => $report_id]);
        $report = $r->fetch();

        $title = 'New Corrective Action Assigned';
        $message = 'You have been assigned a corrective action for report ' . ($report['report_number'] ?? '') . ': ' . substr($description, 0, 80) . '...';
        $url = '/hiyari/view?id=' . $report_id;

        createNotification($pdo, $assigned_to, $title, $message, 'warning', $url);
        sendPushNotification($pdo, $assigned_to, $title, $message, $url);
    }

    header('Location: view?id=' . $report_id . '&success=action_added');
} catch (PDOException $e) {
    die('❌ Error: ' . $e->getMessage());
}
exit;