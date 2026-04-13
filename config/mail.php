<?php
// ── Brevo API Configuration ──
define('BREVO_API_KEY', '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'); // Replace with your Brevo API key
define('MAIL_FROM_NAME', 'KYT Yadin System');
define('MAIL_FROM_EMAIL', 'noreply@yanmar.co.id');
define('APP_URL', 'http://kyt.yadin.com');

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