// service-worker.js

// Cache name
const CACHE_NAME = 'notifications-v1';

// Install event
self.addEventListener('install', function(event) {
    self.skipWaiting();
});

// Activate event
self.addEventListener('activate', function(event) {
    event.waitUntil(clients.claim());
});

// Push event - for receiving notifications
self.addEventListener('push', function(event) {
    let data = {
        title: 'New Notification',
        body: 'You have a new notification',
        icon: '/assets/icon.png',
        badge: '/assets/badge.png',
        url: '/index.php?page=notifications'
    };

    try {
        if (event.data) {
            data = event.data.json();
        }
    } catch (e) {
        // If data is not JSON, use it as body
        data.body = event.data.text() || data.body;
    }

    const options = {
        body: data.body,
        icon: data.icon || '/assets/icon.png',
        badge: data.badge || '/assets/badge.png',
        vibrate: [200, 100, 200, 100, 200],
        sound: '/assets/notification.mp3',
        requireInteraction: true,
        tag: data.tag || 'notification',
        renotify: true,
        data: {
            url: data.url || '/index.php?page=notifications'
        },
        actions: [
            { action: 'view', title: 'View' },
            { action: 'dismiss', title: 'Dismiss' }
        ]
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

// Notification click event
self.addEventListener('notificationclick', function(event) {
    event.notification.close();

    if (event.action === 'view') {
        const url = event.notification.data.url || '/';
        event.waitUntil(
            clients.openWindow(url)
        );
    } else if (event.action === 'dismiss') {
        // Dismiss action - do nothing
    }
});

// Fetch event - for offline support
self.addEventListener('fetch', function(event) {
    event.respondWith(
        fetch(event.request).catch(function() {
            return new Response('Offline', {
                status: 503,
                statusText: 'Service Unavailable'
            });
        })
    );
});