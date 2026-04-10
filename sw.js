// ── Service Worker for Push Notifications ──
self.addEventListener('push', function(e) {
    const data = e.data ? e.data.json() : {};
    const title   = data.title   || 'KYT Yadin';
    const message = data.message || 'You have a new notification';
    const url     = data.url     || '/index';
    const icon    = data.icon    || '/assets/logo.png';

    e.waitUntil(
        self.registration.showNotification(title, {
            body:  message,
            icon:  icon,
            badge: icon,
            data:  { url },
            vibrate: [200, 100, 200],
        })
    );
});

self.addEventListener('notificationclick', function(e) {
    e.notification.close();
    e.waitUntil(
        clients.openWindow(e.notification.data.url || '/index')
    );
});
