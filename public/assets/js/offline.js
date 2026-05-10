/**
 * Offline Functionality for IECEP-LSC Membership System
 * Handles offline detection, data queuing, and synchronization
 */

class OfflineManager {
    constructor() {
        this.isOnline = navigator.onLine;
        this.db = null;
        this.syncQueue = [];
        this.init();
    }

    async init() {
        this.setupEventListeners();
        this.updateConnectionStatus();

        // Initialize IndexedDB for offline storage
        await this.initIndexedDB();

        // Load queued requests
        await this.loadQueuedRequests();

        console.log('Offline manager initialized');
    }

    setupEventListeners() {
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.updateConnectionStatus();
            this.syncQueuedRequests();
            this.showOnlineNotification();
        });

        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.updateConnectionStatus();
            this.showOfflineNotification();
        });

        // Handle page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && this.isOnline) {
                this.syncQueuedRequests();
            }
        });
    }

    updateConnectionStatus() {
        const statusElement = document.getElementById('connection-status');
        if (statusElement) {
            statusElement.className = this.isOnline ? 'online' : 'offline';
            statusElement.innerHTML = `
                <i class="fas fa-${this.isOnline ? 'wifi' : 'wifi-slash'}"></i>
                ${this.isOnline ? 'Online' : 'Offline'}
            `;
            statusElement.style.display = 'flex';
        }

        // Update body class for styling
        document.body.classList.toggle('offline-mode', !this.isOnline);
    }

    showOnlineNotification() {
        if (window.toast) {
            window.toast.success('Connection restored', 'You are back online');
        }
    }

    showOfflineNotification() {
        if (window.toast) {
            window.toast.warning('You are offline', 'Some features may be limited');
        }
    }

    async initIndexedDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open('IECEP_Offline_DB', 1);

            request.onerror = () => {
                console.error('IndexedDB error:', request.error);
                reject(request.error);
            };

            request.onsuccess = (event) => {
                this.db = event.target.result;
                console.log('IndexedDB initialized');
                resolve();
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                // Create object stores
                if (!db.objectStoreNames.contains('queued_requests')) {
                    const store = db.createObjectStore('queued_requests', { keyPath: 'id', autoIncrement: true });
                    store.createIndex('timestamp', 'timestamp', { unique: false });
                    store.createIndex('endpoint', 'endpoint', { unique: false });
                }

                if (!db.objectStoreNames.contains('cached_data')) {
                    db.createObjectStore('cached_data', { keyPath: 'key' });
                }

                if (!db.objectStoreNames.contains('user_actions')) {
                    const store = db.createObjectStore('user_actions', { keyPath: 'id', autoIncrement: true });
                    store.createIndex('timestamp', 'timestamp', { unique: false });
                    store.createIndex('action', 'action', { unique: false });
                }
            };
        });
    }

    async queueRequest(endpoint, method, data, headers = {}) {
        if (this.isOnline) {
            // If online, try to send immediately
            try {
                return await this.sendRequest(endpoint, method, data, headers);
            } catch (error) {
                // If request fails, queue it
                console.log('Request failed, queuing for later:', error);
                return await this.addToQueue(endpoint, method, data, headers);
            }
        } else {
            // Offline, definitely queue
            return await this.addToQueue(endpoint, method, data, headers);
        }
    }

    async addToQueue(endpoint, method, data, headers = {}) {
        const request = {
            endpoint,
            method: method || 'GET',
            data,
            headers,
            timestamp: Date.now(),
            retryCount: 0
        };

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['queued_requests'], 'readwrite');
            const store = transaction.objectStore('queued_requests');
            const addRequest = store.add(request);

            addRequest.onsuccess = () => {
                this.syncQueue.push(request);
                console.log('Request queued:', request);
                resolve(request);
            };

            addRequest.onerror = () => {
                console.error('Failed to queue request:', addRequest.error);
                reject(addRequest.error);
            };
        });
    }

    async loadQueuedRequests() {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['queued_requests'], 'readonly');
            const store = transaction.objectStore('queued_requests');
            const index = store.index('timestamp');
            const request = index.openCursor();

            this.syncQueue = [];

            request.onsuccess = (event) => {
                const cursor = event.target.result;
                if (cursor) {
                    this.syncQueue.push(cursor.value);
                    cursor.continue();
                } else {
                    console.log(`Loaded ${this.syncQueue.length} queued requests`);
                    resolve();
                }
            };

            request.onerror = () => reject(request.error);
        });
    }

    async syncQueuedRequests() {
        if (!this.isOnline || this.syncQueue.length === 0) return;

        console.log(`Attempting to sync ${this.syncQueue.length} queued requests`);

        const successful = [];
        const failed = [];

        for (const queuedRequest of this.syncQueue) {
            try {
                await this.sendRequest(
                    queuedRequest.endpoint,
                    queuedRequest.method,
                    queuedRequest.data,
                    queuedRequest.headers
                );
                successful.push(queuedRequest.id);
            } catch (error) {
                console.error('Failed to sync request:', error);
                queuedRequest.retryCount = (queuedRequest.retryCount || 0) + 1;

                if (queuedRequest.retryCount < 3) {
                    failed.push(queuedRequest);
                } else {
                    // Remove after max retries
                    await this.removeFromQueue(queuedRequest.id);
                }
            }
        }

        // Remove successful requests
        for (const id of successful) {
            await this.removeFromQueue(id);
        }

        // Update failed requests
        this.syncQueue = failed;

        if (successful.length > 0) {
            if (window.toast) {
                window.toast.success(`${successful.length} offline actions synced successfully`);
            }
        }

        console.log(`Sync complete: ${successful.length} successful, ${failed.length} failed`);
    }

    async removeFromQueue(id) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['queued_requests'], 'readwrite');
            const store = transaction.objectStore('queued_requests');
            const request = store.delete(id);

            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    async sendRequest(endpoint, method, data, headers = {}) {
        const config = {
            method: method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                ...headers
            }
        };

        if (data && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
            config.body = JSON.stringify(data);
        }

        const response = await fetch(endpoint, config);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return await response.json();
    }

    // Cache data for offline access
    async cacheData(key, data) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['cached_data'], 'readwrite');
            const store = transaction.objectStore('cached_data');
            const request = store.put({ key, data, timestamp: Date.now() });

            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    async getCachedData(key) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['cached_data'], 'readonly');
            const store = transaction.objectStore('cached_data');
            const request = store.get(key);

            request.onsuccess = () => {
                const result = request.result;
                resolve(result ? result.data : null);
            };
            request.onerror = () => reject(request.error);
        });
    }

    // Track user actions for offline analytics
    async trackAction(action, data = {}) {
        const userAction = {
            action,
            data,
            timestamp: Date.now(),
            offline: !this.isOnline
        };

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['user_actions'], 'readwrite');
            const store = transaction.objectStore('user_actions');
            const request = store.add(userAction);

            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    // Get connection status
    isOnline() {
        return this.isOnline;
    }

    // Force sync
    async forceSync() {
        if (!this.isOnline) {
            throw new Error('Cannot sync while offline');
        }

        await this.syncQueuedRequests();
    }

    // Get sync status
    getSyncStatus() {
        return {
            isOnline: this.isOnline,
            queuedRequests: this.syncQueue.length,
            lastSync: localStorage.getItem('lastSync') || null
        };
    }
}

// Global offline manager instance
const offlineManager = new OfflineManager();

// Make it globally available
window.offlineManager = offlineManager;

// Enhanced fetch that automatically queues requests when offline
const originalFetch = window.fetch;
window.fetch = async function(...args) {
    if (!offlineManager.isOnline) {
        // For API calls, queue them
        if (args[0] && typeof args[0] === 'string' && args[0].includes('/api/')) {
            try {
                return await offlineManager.queueRequest(args[0], args[1]?.method, args[1]?.body, args[1]?.headers);
            } catch (error) {
                throw error;
            }
        }
    }

    // Otherwise, use original fetch
    return originalFetch.apply(this, args);
};

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        console.log('Offline functionality loaded');
    });
} else {
    console.log('Offline functionality loaded');
}