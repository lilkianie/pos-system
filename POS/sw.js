// Service Worker for POS System
const CACHE_NAME = 'pos-system-v1';
// Use relative paths for virtual host compatibility
const BASE_PATH = self.location.pathname.replace(/\/sw\.js$/, '');
const OFFLINE_URL = BASE_PATH + '/pos/index.php';

// Assets to cache for offline use
const STATIC_CACHE_URLS = [
    BASE_PATH + '/pos/index.php',
    BASE_PATH + '/assets/css/pos.css',
    BASE_PATH + '/assets/js/pos.js',
    BASE_PATH + '/assets/js/offline.js',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css',
    'https://code.jquery.com/jquery-3.7.1.min.js',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'
];

// Install event - cache static assets
self.addEventListener('install', (event) => {
    console.log('Service Worker installing...');
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            console.log('Caching static assets');
            return cache.addAll(STATIC_CACHE_URLS).catch(err => {
                console.log('Some assets failed to cache:', err);
            });
        })
    );
    self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
    console.log('Service Worker activating...');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

// Fetch event - serve from cache when offline
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }

    // Skip API calls - let them fail gracefully for offline handling
    if (url.pathname.includes('/api/')) {
        return;
    }

    // Try network first, fallback to cache
    event.respondWith(
        fetch(request)
            .then((response) => {
                // Clone the response
                const responseToCache = response.clone();
                
                // Cache successful responses
                if (response.status === 200) {
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(request, responseToCache);
                    });
                }
                
                return response;
            })
            .catch(() => {
                // Network failed, try cache
                return caches.match(request).then((response) => {
                    if (response) {
                        return response;
                    }
                    
                    // If it's a navigation request and we have an offline page
                    if (request.mode === 'navigate') {
                        return caches.match(OFFLINE_URL);
                    }
                    
                    // Return a basic response
                    return new Response('Offline - Content not available', {
                        status: 503,
                        headers: { 'Content-Type': 'text/plain' }
                    });
                });
            })
    );
});

// Background sync for offline transactions
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-offline-transactions') {
        event.waitUntil(syncOfflineTransactions());
    }
});

// Function to sync offline transactions
async function syncOfflineTransactions() {
    try {
        // This will be called when connection is restored
        // The actual sync logic is handled in pos.js
        console.log('Background sync triggered');
    } catch (error) {
        console.error('Background sync failed:', error);
    }
}

// Push notification support (optional)
self.addEventListener('push', (event) => {
    const data = event.data ? event.data.json() : {};
    const title = data.title || 'POS System';
    const options = {
        body: data.body || 'New notification',
        icon: '/POS/assets/images/icon-192x192.png',
        badge: '/POS/assets/images/icon-72x72.png'
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// Notification click handler
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(
        clients.openWindow('/POS/pos/index.php')
    );
});
