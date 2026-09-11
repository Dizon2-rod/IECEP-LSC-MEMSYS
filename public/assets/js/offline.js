/**
 * Offline Functionality for IECEP-LSC Membership System
 * Handles offline detection, data queuing, IndexedDB migration, and synchronization
 */

class OfflineManager {
    constructor() {
        this.isOnline = navigator.onLine;
        this.db = null;
        this.syncQueue = [];
        this.migrationError = null;
        this.init();
    }

    async init() {
        this.setupEventListeners();
        this.updateConnectionStatus();

        // 1. Initialize IndexedDB with legacy migration safeguard
        await this.initIndexedDB();

        // 2. Load queued requests
        await this.loadQueuedRequests();

        // 3. Setup SW message listeners
        this.setupServiceWorkerListener();

        // 4. Initial settings sync if online
        if (this.isOnline) {
            this.refreshSettings();
        }

        this.updateSyncStatusIndicator();
        console.log('Offline manager initialized successfully');
    }

    setupEventListeners() {
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.updateConnectionStatus();
            this.syncQueuedRequests();
            this.refreshSettings();
            this.showOnlineNotification();
        });

        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.updateConnectionStatus();
            this.updateSyncStatusIndicator();
            this.showOfflineNotification();
        });

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && this.isOnline) {
                this.syncQueuedRequests();
            }
        });
    }

    setupServiceWorkerListener() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', (event) => {
                if (event.data?.type === 'MIGRATION_ERROR') {
                    this.migrationError = event.data.message;
                    this.updateSyncStatusIndicator();
                } else if (event.data?.type === 'SYNC_COMPLETE') {
                    this.loadQueuedRequests();
                    this.updateSyncStatusIndicator();
                }
            });
        }
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

        if (document.body) {
            document.body.classList.toggle('offline-mode', !this.isOnline);
        }

        this.updateSyncStatusIndicator();
    }

    updateSyncStatusIndicator() {
        const indicator = document.getElementById('sync-status-indicator');
        if (!indicator) return;

        if (this.migrationError) {
            indicator.className = 'sync-status error';
            indicator.innerHTML = `<i class="fas fa-exclamation-triangle" style="color:#ef4444;"></i> Migration notice: ${this.migrationError}`;
            indicator.style.display = 'inline-flex';
            return;
        }

        const count = this.syncQueue.length;
        if (!this.isOnline) {
            indicator.className = 'sync-status offline';
            indicator.innerHTML = `<i class="fas fa-cloud-slash" style="color:#f59e0b;"></i> Offline (${count} queued changes)`;
            indicator.style.display = 'inline-flex';
        } else if (count > 0) {
            indicator.className = 'sync-status syncing';
            indicator.innerHTML = `<i class="fas fa-sync fa-spin" style="color:#2563eb;"></i> Syncing ${count} queued change(s)...`;
            indicator.style.display = 'inline-flex';
        } else {
            indicator.className = 'sync-status synced';
            indicator.innerHTML = `<i class="fas fa-check-circle" style="color:#10b981;"></i> All changes synced`;
            indicator.style.display = 'inline-flex';
        }
    }

    showOnlineNotification() {
        if (window.toast) {
            window.toast.success('Connection restored', 'You are back online');
        }
    }

    showOfflineNotification() {
        if (window.toast) {
            window.toast.warning('You are offline', 'Changes will queue and synchronize automatically');
        }
    }

    // Legacy IndexedDB migration from IECEP_MEMSYS_Offline to IECEP_Offline_DB
    async migrateLegacyDatabase() {
        try {
            const hasLegacy = await new Promise((resolve) => {
                const req = indexedDB.open('IECEP_MEMSYS_Offline');
                req.onsuccess = (e) => {
                    const db = e.target.result;
                    const hasStore = db.objectStoreNames.contains('pendingRequests');
                    db.close();
                    resolve(hasStore);
                };
                req.onerror = () => resolve(false);
            });

            if (!hasLegacy) return;

            console.log('[OfflineManager] Migrating legacy IECEP_MEMSYS_Offline database...');

            const legacyItems = await new Promise((resolve, reject) => {
                const req = indexedDB.open('IECEP_MEMSYS_Offline');
                req.onerror = () => reject(req.error);
                req.onsuccess = (e) => {
                    const db = e.target.result;
                    if (!db.objectStoreNames.contains('pendingRequests')) {
                        db.close();
                        return resolve([]);
                    }
                    const tx = db.transaction(['pendingRequests'], 'readonly');
                    const store = tx.objectStore('pendingRequests');
                    const getAll = store.getAll();
                    getAll.onsuccess = () => {
                        db.close();
                        resolve(getAll.result || []);
                    };
                    getAll.onerror = () => {
                        db.close();
                        reject(getAll.error);
                    };
                };
            });

            if (legacyItems && legacyItems.length > 0) {
                // Ensure target store is open and write items
                await new Promise((resolve, reject) => {
                    const tx = this.db.transaction(['queued_requests'], 'readwrite');
                    const store = tx.objectStore('queued_requests');

                    for (const item of legacyItems) {
                        store.add({
                            mutation_id: item.mutation_id || item.id || ('legacy-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9)),
                            endpoint: item.endpoint || item.url || '',
                            method: item.method || 'POST',
                            data: item.data || item.body || null,
                            headers: item.headers || {},
                            queued_at: item.queued_at || (item.timestamp ? new Date(item.timestamp).toISOString() : new Date().toISOString()),
                            timestamp: item.timestamp || Date.now(),
                            retryCount: item.retryCount || 0
                        });
                    }

                    tx.oncomplete = () => resolve();
                    tx.onerror = () => reject(tx.error);
                });

                console.log(`[OfflineManager] Migrated ${legacyItems.length} records into IECEP_Offline_DB`);
            }

            // Delete legacy database only after verified copy
            await new Promise((resolve) => {
                const del = indexedDB.deleteDatabase('IECEP_MEMSYS_Offline');
                del.onsuccess = () => resolve();
                del.onerror = () => resolve();
                del.onblocked = () => resolve();
            });

        } catch (err) {
            console.error('[OfflineManager] Legacy migration failed:', err);
            this.migrationError = err.message || 'Legacy database migration error';
            this.updateSyncStatusIndicator();
        }
    }

    async initIndexedDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open('IECEP_Offline_DB', 1);

            request.onerror = () => {
                console.error('IndexedDB error:', request.error);
                reject(request.error);
            };

            request.onsuccess = async (event) => {
                this.db = event.target.result;
                console.log('IndexedDB IECEP_Offline_DB initialized');
                
                // Run legacy migration check
                await this.migrateLegacyDatabase();
                resolve();
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                if (!db.objectStoreNames.contains('queued_requests')) {
                    const store = db.createObjectStore('queued_requests', { keyPath: 'id', autoIncrement: true });
                    store.createIndex('timestamp', 'timestamp', { unique: false });
                    store.createIndex('endpoint', 'endpoint', { unique: false });
                    store.createIndex('mutation_id', 'mutation_id', { unique: false });
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
            try {
                return await this.sendRequest(endpoint, method, data, headers);
            } catch (error) {
                console.log('Request failed, queuing for later:', error);
                return await this.addToQueue(endpoint, method, data, headers);
            }
        } else {
            return await this.addToQueue(endpoint, method, data, headers);
        }
    }

    async addToQueue(endpoint, method, data, headers = {}) {
        const mutationId = (window.crypto && crypto.randomUUID) 
            ? crypto.randomUUID() 
            : ('mut-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9));

        const request = {
            mutation_id: mutationId,
            endpoint,
            method: method || 'GET',
            data,
            headers,
            queued_at: new Date().toISOString(),
            timestamp: Date.now(),
            retryCount: 0
        };

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['queued_requests'], 'readwrite');
            const store = transaction.objectStore('queued_requests');
            const addRequest = store.add(request);

            addRequest.onsuccess = (e) => {
                request.id = e.target.result;
                this.syncQueue.push(request);
                this.updateSyncStatusIndicator();
                console.log('Request queued with ID:', request.id, request);
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
                    this.updateSyncStatusIndicator();
                    resolve();
                }
            };

            request.onerror = () => reject(request.error);
        });
    }

    async syncQueuedRequests() {
        if (!this.isOnline || this.syncQueue.length === 0) return;

        console.log(`Attempting to sync ${this.syncQueue.length} queued requests (LWW order)`);
        this.updateSyncStatusIndicator();

        const successful = [];
        const failed = [];

        // Sort by queued_at / timestamp for deterministic chronological playback
        const queueToProcess = [...this.syncQueue].sort((a, b) => (a.timestamp || 0) - (b.timestamp || 0));

        for (const queuedRequest of queueToProcess) {
            try {
                await this.sendRequest(
                    queuedRequest.endpoint,
                    queuedRequest.method,
                    queuedRequest.data,
                    {
                        ...queuedRequest.headers,
                        'X-Queued-At': queuedRequest.queued_at || new Date(queuedRequest.timestamp).toISOString(),
                        'X-Mutation-ID': queuedRequest.mutation_id || String(queuedRequest.id)
                    }
                );
                successful.push(queuedRequest.id);
            } catch (error) {
                console.error('Failed to sync request:', error);
                queuedRequest.retryCount = (queuedRequest.retryCount || 0) + 1;

                if (queuedRequest.retryCount < 3) {
                    failed.push(queuedRequest);
                } else {
                    // Drop permanently failing request after max retries
                    await this.removeFromQueue(queuedRequest.id);
                }
            }
        }

        // Remove successful requests
        for (const id of successful) {
            await this.removeFromQueue(id);
        }

        this.syncQueue = failed;
        this.updateSyncStatusIndicator();

        if (successful.length > 0 && window.toast) {
            window.toast.success(`${successful.length} offline actions synced successfully`);
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
            config.body = typeof data === 'string' ? data : JSON.stringify(data);
        }

        const response = await fetch(endpoint, config);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return await response.json();
    }

    // Refresh dynamic settings from centralized source on reconnect
    async refreshSettings() {
        try {
            const basePath = (window.IECEP_CONFIG && window.IECEP_CONFIG.APP_URL) ? window.IECEP_CONFIG.APP_URL : '';
            const res = await fetch(`${basePath}/api/calculate-fees.php?action=get_settings`);
            if (res.ok) {
                const data = await res.json();
                if (data && data.success) {
                    await this.cacheData('system_settings', data.settings);
                    await this.cacheData('fee_brackets', data.fee_brackets);
                    await this.cacheData('member_fees', data.member_fees);
                    console.log('[OfflineManager] Centralized system settings refreshed and cached offline');
                }
            }
        } catch (e) {
            console.log('[OfflineManager] Settings refresh deferred:', e.message);
        }
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

    isOnline() {
        return this.isOnline;
    }

    async forceSync() {
        if (!this.isOnline) {
            throw new Error('Cannot sync while offline');
        }
        await this.syncQueuedRequests();
    }

    getSyncStatus() {
        return {
            isOnline: this.isOnline,
            queuedRequests: this.syncQueue.length,
            migrationError: this.migrationError,
            lastSync: localStorage.getItem('lastSync') || null
        };
    }
}

// Global offline manager instance
const offlineManager = new OfflineManager();
window.offlineManager = offlineManager;

// Enhanced fetch that automatically queues mutation requests when offline
const originalFetch = window.fetch;
window.fetch = async function(...args) {
    if (!offlineManager.isOnline) {
        if (args[0] && typeof args[0] === 'string' && args[0].includes('/api/')) {
            try {
                return await offlineManager.queueRequest(args[0], args[1]?.method, args[1]?.body, args[1]?.headers);
            } catch (error) {
                throw error;
            }
        }
    }
    return originalFetch.apply(this, args);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        console.log('Offline functionality ready');
    });
} else {
    console.log('Offline functionality ready');
}