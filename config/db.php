<?php
date_default_timezone_set('Asia/Jakarta');

$host = 'localhost';
$db = 'kyt_yadin';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}

// ── CSRF helpers ─────────────────────────────────
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '" />';
}

function csrf_verify(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Invalid request. Please go back and try again.');
    }
}
function auditLog(PDO $pdo, string $action, string $module, int $target_id = 0, string $target_label = '', string $old_value = '', string $new_value = ''): void
{
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    $uname = $_SESSION['name'] ?? 'System';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '—';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '—', 0, 255);

    $pdo->prepare("
        INSERT INTO audit_logs (user_id, user_name, action, module, target_id, target_label, old_value, new_value, ip_address, user_agent, created_at)
        VALUES (:uid, :uname, :action, :module, :tid, :tlabel, :old, :new, :ip, :ua, NOW())
    ")->execute([
                ':uid' => $uid ?: null,
                ':uname' => $uname,
                ':action' => $action,
                ':module' => $module,
                ':tid' => $target_id ?: null,
                ':tlabel' => $target_label,
                ':old' => $old_value,
                ':new' => $new_value,
                ':ip' => $ip,
                ':ua' => $ua,
            ]);
}
function markCorrectiveActionsOverdue(PDO $pdo): void {
    try {
        // 1️⃣ Mark overdue corrective actions
        $pdo->prepare("
            UPDATE corrective_actions
            SET status = 'overdue', updated_at = NOW()
            WHERE status IN ('open', 'progress')
              AND due_date IS NOT NULL
              AND DATE(due_date) < CURDATE()
        ")->execute();

        // 2️⃣ Find reports where ALL actions are overdue
        $stmt = $pdo->prepare("
            SELECT r.id
            FROM hiyari_reports r
            WHERE r.status != 'closed'
              AND EXISTS (SELECT 1 FROM corrective_actions WHERE report_id = r.id)
              AND NOT EXISTS (
                  SELECT 1 FROM corrective_actions 
                  WHERE report_id = r.id 
                  AND status IN ('open', 'progress', 'done')
              )
        ");
        $stmt->execute();
        $overdue_reports = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 3️⃣ Mark those reports as overdue
        if (!empty($overdue_reports)) {
            foreach ($overdue_reports as $report_id) {
                $pdo->prepare("
                    UPDATE hiyari_reports
                    SET status = 'overdue', updated_at = NOW()
                    WHERE id = :id AND status != 'closed'
                ")->execute([':id' => $report_id]);
            }
        }
    } catch (PDOException $e) {
        error_log('markCorrectiveActionsOverdue error: ' . $e->getMessage());
    }
}
?>