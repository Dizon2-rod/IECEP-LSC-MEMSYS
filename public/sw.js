// Service Worker for IECEP-LSC Membership System PWA
const CACHE_NAME = 'iecep-lsc-v1.0.0';
const STATIC_CACHE = 'iecep-lsc-static-v1.0.0';
const DYNAMIC_CACHE = 'iecep-lsc-dynamic-v1.0.0';

const BASE_PATH = self.location.pathname.replace(/\/sw\.js$/, '');
const buildUrl = (path) => {
    const normalized = path.startsWith('/') ? path.substring(1) : path;
    return `${BASE_PATH}/${normalized}`.replace(/([^:]\/)\/+/g, '$1');
};

// Resources to cache immediately
const STATIC_ASSETS = [
    buildUrl('/'),
    buildUrl('index.php'),
    buildUrl('login.php'),
    buildUrl('offline.html'),
    buildUrl('manifest.json'),
    buildUrl('assets/css/styles.css'),
    buildUrl('assets/css/professional.css'),
    buildUrl('assets/js/app.js'),
    buildUrl('assets/js/toast.js'),
    buildUrl('assets/js/offline.js'),
    buildUrl('assets/icons/icon-192x192.png'),
    buildUrl('assets/icons/icon-512x512.png'),
    buildUrl('assets/css/bootstrap.min.css'),
    buildUrl('assets/js/bootstrap.bundle.min.js'),
    buildUrl('assets/js/chart.js')
];

const OFFLINE_PAGE = buildUrl('offline.html');

// API endpoints that should be cached
const API_CACHE_PATTERNS = [
    new RegExp(`${BASE_PATH}/api/notifications`),
    new RegExp(`${BASE_PATH}/api/collaboration\\?action=list_posts`),
    new RegExp(`${BASE_PATH}/api/digital-id\\?action=get_digital_id`)
];

// Install event - cache static assets
self.addEventListener('install', event => {
    console.log('[SW] Installing service worker');
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                console.log('[SW] Caching static assets');
                return cache.addAll(STATIC_ASSETS);
            })
            .catch(error => {
                console.error('[SW] Error caching static assets:', error);
            })
    );
    self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    console.log('[SW] Activating service worker');
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
                        console.log('[SW] Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

// Fetch event - serve cached content when offline
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    // Handle API requests
    if (url.pathname.startsWith(`${BASE_PATH}/api/`)) {
        event.respondWith(handleApiRequest(request));
        return;
    }

    // Handle static assets
    if (STATIC_ASSETS.includes(url.pathname) || url.pathname.match(/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2)$/)) {
        event.respondWith(
            caches.match(request)
                .then(response => {
                    if (response) {
                        return response;
                    }
                    return fetch(request).then(response => {
                        // Cache successful responses
                        if (response.status === 200) {
                            const responseClone = response.clone();
                            caches.open(STATIC_CACHE).then(cache => {
                                cache.put(request, responseClone);
                            });
                        }
                        return response;
                    });
                })
                .catch(() => {
                    // Return offline fallback for HTML pages
                    if (request.headers.get('accept').includes('text/html')) {
                        return caches.match(OFFLINE_PAGE);
                    }
                })
        );
        return;
    }

    // Default network-first strategy for other requests
    event.respondWith(
        fetch(request)
            .then(response => {
                // Cache successful GET requests
                if (request.method === 'GET' && response.status === 200) {
                    const responseClone = response.clone();
                    caches.open(DYNAMIC_CACHE).then(cache => {
                        cache.put(request, responseClone);
                    });
                }
                return response;
            })
            .catch(() => {
                // Try cache for failed requests
                return caches.match(request)
                    .then(response => {
                        if (response) {
                            return response;
                        }
                        // Return offline page for navigation requests
                        if (request.mode === 'navigate') {
                            return caches.match('/offline.html');
                        }
                    });
            })
    );
});

// Handle API requests with special caching logic
async function handleApiRequest(request) {
    const url = new URL(request.url);

    // For read-only API calls, try cache first, then network
    if (request.method === 'GET' && API_CACHE_PATTERNS.some(pattern => pattern.test(url.pathname + url.search))) {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            // Return cached response and update in background
            fetch(request).then(response => {
                if (response.status === 200) {
                    const responseClone = response.clone();
                    caches.open(DYNAMIC_CACHE).then(cache => {
                        cache.put(request, responseClone);
                    });
                }
            }).catch(() => {
                // Ignore background update failures
            });
            return cachedResponse;
        }
    }

    // Network-first for API calls
    try {
        const response = await fetch(request);
        if (response.status === 200 && request.method === 'GET') {
            const responseClone = response.clone();
            caches.open(DYNAMIC_CACHE).then(cache => {
                cache.put(request, responseClone);
            });
        }
        return response;
    } catch (error) {
        // Try cache for failed API requests
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }

        // Return offline API response
        return new Response(
            JSON.stringify({
                success: false,
                message: 'You are currently offline. Please check your internet connection.',
                offline: true
            }),
            {
                status: 503,
                statusText: 'Service Unavailable',
                headers: { 'Content-Type': 'application/json' }
            }
        );
    }
}

// Background sync for offline actions
self.addEventListener('sync', event => {
    console.log('[SW] Background sync triggered:', event.tag);

    if (event.tag === 'background-sync') {
        event.waitUntil(syncOfflineActions());
    }
});

// Push notifications
self.addEventListener('push', event => {
    console.log('[SW] Push notification received');

    if (!event.data) {
        return;
    }

    const data = event.data.json();

    const options = {
        body: data.body,
        icon: '/assets/icons/icon-192x192.png',
        badge: '/assets/icons/icon-72x72.png',
        vibrate: [100, 50, 100],
        data: {
            url: data.url || '/portal/dashboard.php'
        },
        actions: [
            {
                action: 'view',
                title: 'View'
            },
            {
                action: 'dismiss',
                title: 'Dismiss'
            }
        ]
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'IECEP-LSC', options)
    );
});

// Handle notification clicks
self.addEventListener('notificationclick', event => {
    console.log('[SW] Notification clicked');

    event.notification.close();

    if (event.action === 'dismiss') {
        return;
    }

    const url = event.notification.data.url || '/portal/dashboard.php';

    event.waitUntil(
        clients.openWindow(url)
    );
});

// Periodic background sync (if supported)
self.addEventListener('periodicsync', event => {
    if (event.tag === 'update-content') {
        event.waitUntil(updateCachedContent());
    }
});

// Sync offline actions stored in IndexedDB
async function syncOfflineActions() {
    try {
        // This would integrate with offline.js to sync queued actions
        console.log('[SW] Syncing offline actions');

        // Get offline actions from IndexedDB
        const actions = await getOfflineActions();

        for (const action of actions) {
            try {
                await fetch(action.url, {
                    method: action.method,
                    headers: action.headers,
                    body: action.body
                });

                // Remove successful action from queue
                await removeOfflineAction(action.id);
            } catch (error) {
                console.error('[SW] Failed to sync action:', action.id, error);
                // Keep failed actions in queue for retry
            }
        }
    } catch (error) {
        console.error('[SW] Error syncing offline actions:', error);
    }
}

// Update cached content periodically
async function updateCachedContent() {
    console.log('[SW] Updating cached content');

    try {
        // Update critical API data
        const apiUrls = [
            '/api/notifications?action=list',
            '/api/collaboration?action=list_posts&page=1&per_page=10'
        ];

        for (const url of apiUrls) {
            try {
                const response = await fetch(url);
                if (response.status === 200) {
                    await caches.open(DYNAMIC_CACHE).then(cache => {
                        cache.put(url, response);
                    });
                }
            } catch (error) {
                console.error('[SW] Failed to update cache for:', url, error);
            }
        }
    } catch (error) {
        console.error('[SW] Error updating cached content:', error);
    }
}

// IndexedDB helpers for offline actions
function getOfflineActions() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('IECEP-LSC-Offline', 1);

        request.onerror = () => reject(request.error);
        request.onsuccess = () => {
            const db = request.result;
            const transaction = db.transaction(['actions'], 'readonly');
            const store = transaction.objectStore('actions');
            const getAllRequest = store.getAll();

            getAllRequest.onsuccess = () => resolve(getAllRequest.result);
            getAllRequest.onerror = () => reject(getAllRequest.error);
        };
    });
}

function removeOfflineAction(id) {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('IECEP-LSC-Offline', 1);

        request.onerror = () => reject(request.error);
        request.onsuccess = () => {
            const db = request.result;
            const transaction = db.transaction(['actions'], 'readwrite');
            const store = transaction.objectStore('actions');
            const deleteRequest = store.delete(id);

            deleteRequest.onsuccess = () => resolve();
            deleteRequest.onerror = () => reject(deleteRequest.error);
        };
    });
}

// Message handler for communication with main thread
self.addEventListener('message', event => {
    const { type, data } = event.data;

    switch (type) {
        case 'SKIP_WAITING':
            self.skipWaiting();
            break;

        case 'GET_VERSION':
            event.ports[0].postMessage({
                version: CACHE_NAME,
                staticCache: STATIC_CACHE,
                dynamicCache: DYNAMIC_CACHE
            });
            break;

        case 'CLEAR_CACHE':
            event.waitUntil(
                caches.keys().then(cacheNames => {
                    return Promise.all(
                        cacheNames.map(cacheName => {
                            if (cacheName.startsWith('iecep-lsc-')) {
                                return caches.delete(cacheName);
                            }
                        })
                    );
                }).then(() => {
                    event.ports[0].postMessage({ success: true });
                })
            );
            break;
    }
});