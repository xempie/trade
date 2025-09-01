/**
 * Service Worker for Crypto Trading PWA
 * Handles caching and offline functionality
 */

const CACHE_NAME = 'crypto-trade-v1.0.0';
const urlsToCache = [
  '/trade/',
  '/trade/index.php',
  '/trade/style.css',
  '/trade/script.js',
  '/trade/manifest.json',
  '/trade/favicon-32x32.png'
];

// Install event - cache resources
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('PWA: Caching app shell');
        return cache.addAll(urlsToCache);
      })
      .then(() => {
        console.log('PWA: Service worker installed successfully');
        return self.skipWaiting();
      })
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            console.log('PWA: Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => {
      console.log('PWA: Service worker activated');
      return self.clients.claim();
    })
  );
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', event => {
  // Skip cross-origin requests
  if (!event.request.url.startsWith(self.location.origin)) {
    return;
  }

  // Skip API requests for fresh data
  if (event.request.url.includes('/api/')) {
    event.respondWith(
      fetch(event.request)
        .then(response => response)
        .catch(() => {
          // Return offline message for API requests
          return new Response(
            JSON.stringify({ 
              success: false, 
              error: 'Offline - please check your connection',
              offline: true 
            }),
            {
              headers: { 'Content-Type': 'application/json' },
              status: 503
            }
          );
        })
    );
    return;
  }

  // Cache first strategy for app shell
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // Return cached version or fetch from network
        return response || fetch(event.request)
          .then(fetchResponse => {
            // Don't cache authentication related requests
            if (event.request.url.includes('/auth/')) {
              return fetchResponse;
            }

            // Cache successful responses
            if (fetchResponse.status === 200) {
              const responseToCache = fetchResponse.clone();
              caches.open(CACHE_NAME)
                .then(cache => {
                  cache.put(event.request, responseToCache);
                });
            }
            return fetchResponse;
          });
      })
      .catch(() => {
        // Return offline fallback for navigation requests
        if (event.request.mode === 'navigate') {
          return caches.match('/trade/');
        }
        return new Response('Offline', { status: 503 });
      })
  );
});

// Background sync for when connection is restored
self.addEventListener('sync', event => {
  if (event.tag === 'background-sync') {
    console.log('PWA: Background sync triggered');
    event.waitUntil(
      // Perform background operations when online
      fetch('/trade/api/get_balance.php')
        .then(() => console.log('PWA: Background sync completed'))
        .catch(() => console.log('PWA: Background sync failed'))
    );
  }
});

// Push notification support
self.addEventListener('push', event => {
  if (event.data) {
    const data = event.data.json();
    const options = {
      body: data.body || 'New crypto trading alert',
      icon: '/trade/icons/icon-192x192.png',
      badge: '/trade/icons/icon-72x72.png',
      tag: 'crypto-alert',
      requireInteraction: true,
      actions: [
        {
          action: 'view',
          title: 'View',
          icon: '/trade/icons/icon-72x72.png'
        },
        {
          action: 'dismiss',
          title: 'Dismiss'
        }
      ]
    };

    event.waitUntil(
      self.registration.showNotification(data.title || 'Crypto Trade Alert', options)
    );
  }
});

// Handle notification clicks
self.addEventListener('notificationclick', event => {
  event.notification.close();
  
  if (event.action === 'view') {
    event.waitUntil(
      clients.openWindow('/trade/')
    );
  }
});