/**
 * SaltNotify Service Worker
 * Web Push bildirimlerini alır ve gösterir.
 *
 * Kurulum:
 * 1. Bu dosyayı theme root'a kopyala: {theme}/sw-notify.js
 * 2. Frontend JS'de register et (aşağıdaki snippet'i kullan)
 *
 * Frontend register snippet:
 * ─────────────────────────────────────────────────────────
 * if ('serviceWorker' in navigator && 'PushManager' in window) {
 *     navigator.serviceWorker.register('/sw-notify.js')
 *         .then(reg => console.log('SW registered'))
 *         .catch(err => console.error('SW error', err));
 * }
 * ─────────────────────────────────────────────────────────
 *
 * @version 1.0.0
 */

'use strict';

// ─── PUSH EVENT ──────────────────────────────────────────────────────────────

self.addEventListener('push', function (event) {
    if (!event.data) return;

    let payload;
    try {
        payload = event.data.json();
    } catch (e) {
        payload = { title: 'Notification', body: event.data.text(), url: '/' };
    }

    const title   = payload.title || 'Notification';
    const options = {
        body:    payload.body  || '',
        icon:    payload.icon  || '/favicon.ico',
        badge:   payload.badge || '/favicon.ico',
        tag:     payload.tag   || 'sh-notify',
        data:    { url: payload.url || '/' },
        vibrate: [200, 100, 200],
        requireInteraction: false,
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// ─── NOTIFICATION CLICK ──────────────────────────────────────────────────────

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    const url = event.notification.data?.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            // Zaten açık bir sekme varsa focus et
            for (const client of clientList) {
                if (client.url === url && 'focus' in client) {
                    return client.focus();
                }
            }
            // Yoksa yeni sekme aç
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});

// ─── PUSH SUBSCRIPTION CHANGE ────────────────────────────────────────────────

self.addEventListener('pushsubscriptionchange', function (event) {
    // Subscription yenilendi — backend'e bildir
    event.waitUntil(
        self.registration.pushManager.subscribe(event.oldSubscription.options)
            .then(function (subscription) {
                return fetch('/wp-admin/admin-ajax.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body:    new URLSearchParams({
                        action:       'sh_notify_push_subscribe',
                        subscription: JSON.stringify(subscription),
                    }),
                });
            })
    );
});
