<?php
// ── Brevo API Configuration ──
$_env = parse_ini_file(__DIR__ . '/../.env') ?: [];
define('BREVO_API_KEY', $_env['BREVO_API_KEY'] ?? '');
define('MAIL_FROM_NAME', 'YADIN Safety Report Management System');
define('MAIL_FROM_EMAIL', 'noreply@yanmar.co.id');
define('APP_URL', 'http://safety-form.yadin.com');

/**
 * Send email via Brevo HTTP API (port 443 - no SMTP needed)
 */
function sendMail(string $to, string $subject, string $body): bool
{
  $payload = json_encode([
    'sender' => [
      'name' => MAIL_FROM_NAME,
      'email' => MAIL_FROM_EMAIL,
    ],
    'to' => [
      ['email' => $to]
    ],
    'subject' => $subject,
    'htmlContent' => $body,
  ]);

  $ch = curl_init('https://api.brevo.com/v3/smtp/email');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
      'accept: application/json',
      'api-key: ' . BREVO_API_KEY,
      'content-type: application/json',
    ],
    CURLOPT_TIMEOUT => 15,
  ]);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  curl_close($ch);

  if ($curlError) {
    echo '❌ cURL Error: ' . $curlError;
    return false;
  }

  if ($httpCode === 201) {
    return true;
  }

  echo '❌ Mail failed. HTTP ' . $httpCode . ': ' . $response;
  return false;
}

/**
 * Send extreme risk alert emails to President Director, General Manager, and Manager
 * Called when a new Hiyari Hatto report is submitted with risk_level = 'extreme'
 */
function sendExtremeRiskAlert(PDO $pdo, array $report): void
{
  // Target positions that must be notified
  $target_positions = ['President Director', 'General Manager', 'Manager'];

  // Build placeholders for IN clause
  $placeholders = implode(',', array_fill(0, count($target_positions), '?'));

  $stmt = $pdo->prepare("
        SELECT name, email
        FROM users
        WHERE position IN ($placeholders)
          AND is_active = 1
          AND email IS NOT NULL
          AND email != ''
    ");
  $stmt->execute($target_positions);
  $recipients = $stmt->fetchAll();

  if (empty($recipients)) {
    return; // No matching users found
  }

  $report_number = htmlspecialchars($report['report_number'] ?? '—');
  $report_date = isset($report['report_date']) ? date('d F Y', strtotime($report['report_date'])) : '—';
  $department = htmlspecialchars($report['dept_name'] ?? '—');
  $location = htmlspecialchars($report['loc_name'] ?? '—');
  $category_map = [
    'near_miss' => 'Near Miss',
    'unsafe_action' => 'Unsafe Act',
    'unsafe_condition' => 'Unsafe Condition',
  ];
  $category = $category_map[$report['category'] ?? ''] ?? ucfirst($report['category'] ?? '—');
  $description = htmlspecialchars($report['description'] ?? '—');
  $reporter = htmlspecialchars($report['reporter_name'] ?? '—');
  $view_url = APP_URL . '/hiyari/view?id=' . (int) ($report['id'] ?? 0);

  $subject = '[EXTREME RISK] Hiyari Hatto Report ' . $report_number . ' Requires Immediate Attention';

  $body = "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#fff;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden'>
      <div style='background:#c0392b;padding:24px 32px'>
        <h1 style='color:#fff;margin:0;font-size:20px'>EXTREME RISK — Immediate Action Required</h1>
        <p style='color:#f8c6c0;margin:6px 0 0;font-size:14px'>KYT Yadin Safety Management System</p>
      </div>
      <div style='padding:28px 32px'>
        <p style='color:#333;font-size:14px;margin-top:0'>
          A new Hiyari Hatto report has been submitted with an <strong style='color:#c0392b'>EXTREME risk level</strong>.
          Immediate review and action is required.
        </p>
        <table style='width:100%;border-collapse:collapse;font-size:14px;color:#444;margin-bottom:24px'>
          <tr style='background:#fdf2f2'>
            <td style='padding:10px 14px;font-weight:bold;width:38%'>Report Number</td>
            <td style='padding:10px 14px'>{$report_number}</td>
          </tr>
          <tr>
            <td style='padding:10px 14px;font-weight:bold'>Report Date</td>
            <td style='padding:10px 14px'>{$report_date}</td>
          </tr>
          <tr style='background:#fdf2f2'>
            <td style='padding:10px 14px;font-weight:bold'>Department</td>
            <td style='padding:10px 14px'>{$department}</td>
          </tr>
          <tr>
            <td style='padding:10px 14px;font-weight:bold'>Location</td>
            <td style='padding:10px 14px'>{$location}</td>
          </tr>
          <tr style='background:#fdf2f2'>
            <td style='padding:10px 14px;font-weight:bold'>Category</td>
            <td style='padding:10px 14px'>{$category}</td>
          </tr>
          <tr>
            <td style='padding:10px 14px;font-weight:bold'>Reported By</td>
            <td style='padding:10px 14px'>{$reporter}</td>
          </tr>
          <tr style='background:#fdf2f2'>
            <td style='padding:10px 14px;font-weight:bold;vertical-align:top'>Description</td>
            <td style='padding:10px 14px'>{$description}</td>
          </tr>
        </table>
        <a href='{$view_url}'
           style='display:inline-block;background:#c0392b;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:bold;font-size:14px'>
          View Full Report →
        </a>
      </div>
      <div style='background:#f7f7f7;padding:16px 32px;font-size:12px;color:#999;border-top:1px solid #e0e0e0'>
        This is an automated alert from the KYT Yadin Safety Management System. Do not reply to this email.
      </div>
    </div>
    ";

  foreach ($recipients as $recipient) {
    sendMail($recipient['email'], $subject, $body);
  }
}