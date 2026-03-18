<?php
/**
 * Notification Helper
 * Creates in-app notifications and triggers browser push
 */

/**
 * Create a notification for a user
 */
function createNotification(PDO $pdo, int $user_id, string $title, string $message, string $type = 'info', string $url = ''): void {
    try {
        $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, url, created_at)
            VALUES (:user_id, :title, :message, :type, :url, NOW())
        ")->execute([
            ':user_id' => $user_id,
            ':title'   => $title,
            ':message' => $message,
            ':type'    => $type,
            ':url'     => $url,
        ]);
    } catch (PDOException $e) {
        error_log('Notification error: ' . $e->getMessage());
    }
}

/**
 * Get unread notification count for a user
 */
function getUnreadCount(PDO $pdo, int $user_id): int {
    try {
        return (int)$pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0")
            ->execute([':uid' => $user_id]) ? $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = $user_id AND is_read = 0")->fetchColumn() : 0;
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Send browser push notification via stored subscription
 */
function sendPushNotification(PDO $pdo, int $user_id, string $title, string $message, string $url = ''): void {
    try {
        $subs = $pdo->prepare("SELECT * FROM push_subscriptions WHERE user_id = :uid");
        $subs->execute([':uid' => $user_id]);
        $subscriptions = $subs->fetchAll();

        foreach ($subscriptions as $sub) {
            $payload = json_encode([
                'title'   => $title,
                'message' => $message,
                'url'     => $url,
                'icon'    => '/kyt-yadin/assets/logo.png',
            ]);

            $endpoint = $sub['endpoint'];
            $p256dh   = $sub['p256dh'];
            $auth     = $sub['auth'];

            // Send via curl
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'TTL: 86400',
                ],
                CURLOPT_TIMEOUT => 5,
            ]);
            curl_exec($ch);
            curl_close($ch);
        }
    } catch (PDOException $e) {
        error_log('Push notification error: ' . $e->getMessage());
    }
}
