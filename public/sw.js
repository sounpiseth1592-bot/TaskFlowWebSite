const CACHE = 'taskflow-shell-v1';

self.addEventListener('install', event => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(keys.filter(key => key.startsWith('taskflow-shell-') && key !== CACHE).map(key => caches.delete(key)));
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);
    if (url.origin === self.location.origin && request.method === 'POST' && url.pathname === '/logout') {
        event.waitUntil(caches.delete(CACHE));
        return;
    }
    if (url.origin !== self.location.origin || request.method !== 'GET') return;

    if (request.mode === 'navigate' && url.pathname === '/') {
        event.respondWith((async () => {
            try {
                const response = await fetch(request);
                if (response.ok) await (await caches.open(CACHE)).put(request, response.clone());
                return response;
            } catch (error) {
                const cached = await caches.match(request);
                if (cached) return cached;
                throw error;
            }
        })());
        return;
    }

    if (/\.(?:css|js|svg|webmanifest)$/.test(url.pathname)) {
        event.respondWith((async () => {
            const cached = await caches.match(request);
            if (cached) return cached;
            const response = await fetch(request);
            if (response.ok) await (await caches.open(CACHE)).put(request, response.clone());
            return response;
        })());
    }
});
