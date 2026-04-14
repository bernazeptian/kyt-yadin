<?php
session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

// Only accept POST from same-origin AJAX requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']); exit;
}

if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']); exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['endpoint'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']); exit;
}

$uid      = (int) $_SESSION['user_id'];
$endpoint = $data['endpoint'];
$p256dh   = $data['keys']['p256dh'] ?? '';
$auth     = $data['keys']['auth']   ?? '';

// ── Validate inputs ───────────────────────────────
// endpoint must be a valid HTTPS URL, max 500 chars
if (!filter_var($endpoint, FILTER_VALIDATE_URL) ||
    !str_starts_with($endpoint, 'https://') ||
    strlen($endpoint) > 500) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid endpoint']); exit;
}

// p256dh is a base64url-encoded public key (~88 chars)
if (!preg_match('/^[A-Za-z0-9\-_+\/=]{10,200}$/', $p256dh)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid p256dh key']); exit;
}

// auth is a base64url-encoded secret (~24 chars)
if (!preg_match('/^[A-Za-z0-9\-_+\/=]{10,100}$/', $auth)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid auth key']); exit;
}

try {
    $pdo->prepare("DELETE FROM push_subscriptions WHERE user_id = :uid")
        ->execute([':uid' => $uid]);
    $pdo->prepare("
        INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, created_at)
        VALUES (:uid, :endpoint, :p256dh, :auth, NOW())
    ")->execute([':uid' => $uid, ':endpoint' => $endpoint, ':p256dh' => $p256dh, ':auth' => $auth]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
