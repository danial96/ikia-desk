/* IKIA Desk service worker — its only job is browser push notifications while Desk is closed.
   No fetch handler and no caching on purpose: pages and API calls always go to the network. */

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
    let d = {};
    try { d = event.data ? event.data.json() : {}; }
    catch (e) { d = { body: event.data ? event.data.text() : '' }; }

    event.waitUntil((async () => {
        // Someone is looking at Desk right now: the in-page popup + sound already tell them.
        const wins = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        if (!d.always && wins.some((c) => c.visibilityState === 'visible' && c.focused)) return;

        await self.registration.showNotification(d.title || 'IKIA Desk', {
            body: d.body || '',
            icon: '/icon-192.png',
            badge: '/icon-192.png',
            tag: d.tag || undefined,
            renotify: !!d.tag,                       // same conversation again -> alert again, replace the old one
            data: { url: d.url || '/' },
        });
    })());
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL((event.notification.data && event.notification.data.url) || '/', self.location.origin).href;

    event.waitUntil((async () => {
        const wins = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const c of wins) {
            if (new URL(c.url).origin !== self.location.origin) continue;
            try {
                await c.focus();
                if (c.url !== target) await c.navigate(target);
                return;
            } catch (e) { /* fall through to a new window */ }
        }
        await self.clients.openWindow(target);
    })());
});
