<?php
session_start();
require_once '../config/db.php';
if (!isset($_SESSION['user_id']) || (int)$_SESSION['role'] > 2) { header('Location: ../index.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php?tab=departments'); exit; }

$id          = (int)($_POST['id']          ?? 0);
$code        = strtoupper(trim($_POST['code'] ?? ''));
$name        = trim($_POST['name']         ?? '');
$head_id     = !empty($_POST['head_id'])   ? (int)$_POST['head_id'] : null; // ← fix empty to null
$description = trim($_POST['description']  ?? '');
$is_active   = isset($_POST['is_active'])   ? 1 : 0;

if (!$id || !$code || !$name) { header('Location: index.php?tab=departments&error=failed'); exit; }

// Check duplicate code excluding self
$check = $pdo->prepare("SELECT id FROM departments WHERE code = :code AND id != :id");
$check->execute([':code' => $code, ':id' => $id]);
if ($check->fetch()) { header('Location: index.php?tab=departments&error=dept_exists'); exit; }

try {
    $stmt = $pdo->prepare("
        UPDATE departments 
        SET code = :code, name = :name, head_id = :head_id, 
            description = :description, is_active = :is_active, updated_at = NOW() 
        WHERE id = :id
    ");
    $stmt->execute([
        ':code'        => $code,
        ':name'        => $name,
        ':head_id'     => $head_id,
        ':description' => $description,
        ':is_active'   => $is_active,
        ':id'          => $id,
    ]);
    header('Location: index.php?tab=departments&success=dept_updated');
} catch (PDOException $e) {
    die('❌ Error: ' . $e->getMessage());
}
exit;