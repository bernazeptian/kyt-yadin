/* =============================================
   NOTIFICATIONS — in-app bell + browser push
   ============================================= */

const Notifications = {

  // ── Init ─────────────────────────────────
  async init() {
    this.bellBtn = document.getElementById('notifBtn');
    this.bellBadge = document.getElementById('notifBadge');
    this.dropdown = document.getElementById('notifDropdown');
    this.list = document.getElementById('notifList');
    this.markAllBtn = document.getElementById('markAllRead');

    if (!this.bellBtn) return;

    // Load notifications
    await this.load();

    // Poll every 30 seconds
    setInterval(() => this.load(), 30000);

    // Toggle dropdown
    this.bellBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      this.dropdown.classList.toggle('show');
      if (this.dropdown.classList.contains('show')) {
        this.load();
      }
    });

    // Close on outside click
    document.addEventListener('click', () => {
      this.dropdown.classList.remove('show');
    });

    // Mark all read
    if (this.markAllBtn) {
      this.markAllBtn.addEventListener('click', () => this.markAllRead());
    }

    // Register push notifications
    await this.registerPush();
  },

  // ── Load notifications from server ───────
  async load() {
    try {
      const res = await fetch('/api/notifications_get');
      const data = await res.json();

      // Update badge
      if (data.unread > 0) {
        this.bellBadge.textContent = data.unread > 99 ? '99+' : data.unread;
        this.bellBadge.style.display = 'flex';
      } else {
        this.bellBadge.style.display = 'none';
      }

      // Render list
      if (data.notifications.length === 0) {
        this.list.innerHTML = '<p class="notif-empty">No notifications yet</p>';
        return;
      }

      this.list.innerHTML = data.notifications.map(n => `
        <div class="notif-item ${n.is_read == 0 ? 'notif-item--unread' : ''}" 
             onclick="Notifications.markRead(${n.id}, '${n.url}')">
          <div class="notif-item__dot notif-item__dot--${n.type}"></div>
          <div class="notif-item__body">
            <div class="notif-item__title">${n.title}</div>
            <div class="notif-item__msg">${n.message}</div>
            <div class="notif-item__time">${this.timeAgo(n.created_at)}</div>
          </div>
        </div>
      `).join('');
    } catch (e) {
      console.error('Failed to load notifications:', e);
    }
  },

  // ── Mark single notification as read ─────
  async markRead(id, url) {
    await fetch('/api/notifications_read', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'id=' + id,
    });
    if (url) window.location.href = url;
    else await this.load();
  },

  // ── Mark all as read ─────────────────────
  async markAllRead() {
    await fetch('../api/notifications_read', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'id=0',
    });
    await this.load();
  },

  // ── Register Service Worker + Push ───────
  async registerPush() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
      console.log('Push not supported');
      return;
    }

    try {
      const reg = await navigator.serviceWorker.register('/sw.js');
      console.log('SW registered');

      const permission = await Notification.requestPermission();
      if (permission !== 'granted') {
        console.log('Push permission denied');
        return;
      }

      const sub = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: this.urlBase64ToUint8Array(
          'BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjZJbTnFSb0hnRkiD6sEKy2K0' // VAPID public key placeholder
        )
      });

      // Save subscription to server
      await fetch('/api/push_subscribe', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(sub),
      });

      console.log('Push subscribed!');
    } catch (e) {
      console.error('Push registration failed:', e);
    }
  },

  // ── Helper: time ago ─────────────────────
  timeAgo(dateStr) {
    const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return Math.floor(diff / 86400) + 'd ago';
  },

  // ── Helper: VAPID key convert ─────────────
  urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    return new Uint8Array([...raw].map(c => c.charCodeAt(0)));
  }
};

document.addEventListener('DOMContentLoaded', () => Notifications.init());
