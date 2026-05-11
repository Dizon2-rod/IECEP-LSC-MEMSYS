// Offline Manager for IECEP-LSC MEMSYS
class OfflineManager {
    constructor() {
        this.dbName = 'IECEP_MEMSYS_Offline';
        this.dbVersion = 1;
        this.db = null;
        this.isOnline = navigator.onLine;
        this.init();
    }

    async init() {
        await this.openDB();
        this.bindEvents();
        this.updateOnlineStatus();
        this.syncPendingData();
    }

    bindEvents() {
        window.addEventListener('online', () => this.handleOnline());
        window.addEventListener('offline', () => this.handleOffline());

        // Intercept fetch requests for offline handling
        this.interceptFetch();
    }

    async openDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);

            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                this.db = request.result;
                resolve();
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                // Store for pending API requests
                if (!db.objectStoreNames.contains('pendingRequests')) {
                    const pendingStore = db.createObjectStore('pendingRequests', { keyPath: 'id', autoIncrement: true });
                    pendingStore.createIndex('timestamp', 'timestamp');
                }

                // Store for cached data
                if (!db.objectStoreNames.contains('cachedData')) {
                    const cacheStore = db.createObjectStore('cachedData', { keyPath: 'key' });
                    cacheStore.createIndex('timestamp', 'timestamp');
                }

                // Store for user data
                if (!db.objectStoreNames.contains('userData')) {
                    db.createObjectStore('userData', { keyPath: 'key' });
                }
            };
        });
    }

    updateOnlineStatus() {
        this.isOnline = navigator.onLine;
        const statusElement = document.getElementById('connection-status');

        if (statusElement) {
            statusElement.className = this.isOnline ? 'online' : 'offline';
            statusElement.innerHTML = `
                <i class="fas fa-${this.isOnline ? 'wifi' : 'wifi-slash'}"></i>
                ${this.isOnline ? 'Online' : 'Offline'}
            `;
            statusElement.style.display = 'flex';
        }

        // Dispatch custom event
        window.dispatchEvent(new CustomEvent('connection-changed', {
            detail: { online: this.isOnline }
        }));
    }

    handleOnline() {
        this.updateOnlineStatus();
        this.syncPendingData();
        this.showNotification('Back online! Syncing data...', 'success');
    }

    handleOffline() {
        this.updateOnlineStatus();
        this.showNotification('You are offline. Changes will be synced when connection is restored.', 'warning');
    }

    interceptFetch() {
        const originalFetch = window.fetch;

        window.fetch = async (url, options = {}) => {
            if (!this.isOnline && this.isApiRequest(url)) {
                // Queue the request for later
                await this.queueRequest(url, options);
                return new Response(JSON.stringify({
                    success: false,
                    offline: true,
                    message: 'Request queued for offline sync'
                }), {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' }
                });
            }

            try {
                const response = await originalFetch(url, options);

                // Cache successful GET requests
                if (response.ok && (!options.method || options.method === 'GET')) {
                    this.cacheResponse(url, response.clone());
                }

                return response;
            } catch (error) {
                // Try to return cached response for GET requests
                if ((!options.method || options.method === 'GET') && this.isApiRequest(url)) {
                    const cached = await this.getCachedResponse(url);
                    if (cached) {
                        return cached;
                    }
                }
                throw error;
            }
        };
    }

    isApiRequest(url) {
        return url.includes('/api/') || url.includes('/IECEP-LSC-MEMSYS/public/api/');
    }

    async queueRequest(url, options) {
        const requestData = {
            url,
            method: options.method || 'GET',
            headers: options.headers || {},
            body: options.body || null,
            timestamp: Date.now()
        };

        await this.storeData('pendingRequests', requestData);
    }

    async syncPendingData() {
        if (!this.isOnline) return;

        const pendingRequests = await this.getAllData('pendingRequests');

        for (const request of pendingRequests) {
            try {
                const response = await fetch(request.url, {
                    method: request.method,
                    headers: request.headers,
                    body: request.body
                });

                if (response.ok) {
                    await this.deleteData('pendingRequests', request.id);
                } else {
                    console.error('Failed to sync request:', request.url, response.status);
                }
            } catch (error) {
                console.error('Error syncing request:', error);
            }
        }
    }

    async cacheResponse(url, response) {
        const data = await response.json();
        const cacheKey = btoa(url); // Simple encoding

        await this.storeData('cachedData', {
            key: cacheKey,
            url,
            data,
            timestamp: Date.now()
        });
    }

    async getCachedResponse(url) {
        const cacheKey = btoa(url);
        const cached = await this.getData('cachedData', cacheKey);

        if (cached && (Date.now() - cached.timestamp) < 3600000) { // 1 hour cache
            return new Response(JSON.stringify(cached.data), {
                status: 200,
                headers: { 'Content-Type': 'application/json' }
            });
        }

        return null;
    }

    async storeData(storeName, data) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.put(data);

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async getData(storeName, key) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.get(key);

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async getAllData(storeName) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.getAll();

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async deleteData(storeName, key) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.delete(key);

            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    async storeUserData(key, data) {
        await this.storeData('userData', { key, data, timestamp: Date.now() });
    }

    async getUserData(key) {
        const result = await this.getData('userData', key);
        return result ? result.data : null;
    }

    showNotification(message, type = 'info') {
        // Use existing notification system if available
        if (window.showToast) {
            window.showToast(message, type);
        } else {
            console.log(`[${type.toUpperCase()}] ${message}`);
        }
    }

    // Public API
    getConnectionStatus() {
        return this.isOnline;
    }

    async getPendingRequestsCount() {
        const pending = await this.getAllData('pendingRequests');
        return pending.length;
    }
}

// Initialize offline manager
window.OfflineManager = new OfflineManager();