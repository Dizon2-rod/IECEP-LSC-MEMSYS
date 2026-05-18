/**
 * Offline Sync Manager
 * Handles queuing and syncing of offline actions
 */

class OfflineSyncManager {
    constructor() {
        this.dbName = 'IECEP_MEMSYS_Offline';
        this.dbVersion = 1;
        this.storeName = 'pendingRequests';
        this.db = null;
    }

    async init() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);

            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                this.db = request.result;
                resolve();
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                if (!db.objectStoreNames.contains(this.storeName)) {
                    const store = db.createObjectStore(this.storeName, { keyPath: 'id', autoIncrement: true });
                    store.createIndex('timestamp', 'timestamp', { unique: false });
                    store.createIndex('url', 'url', { unique: false });
                    store.createIndex('priority', 'priority', { unique: false });
                }
            };
        });
    }

    async queueRequest(url, method, headers, body, tableName = null) {
        if (!this.db) await this.init();

        // Determine priority: transactions = 1 (high), others = 10 (normal)
        const priority = (tableName === 'transactions' || url.includes('/transaction')) ? 1 : 10;

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const store = transaction.objectStore(this.storeName);

            const request = store.add({
                url,
                method,
                headers: Object.fromEntries(headers.entries()),
                body,
                timestamp: Date.now(),
                retries: 0,
                priority,
                table_name: tableName
            });

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async getPendingRequests() {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readonly');
            const store = transaction.objectStore(this.storeName);
            const request = store.getAll();

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    async removeRequest(id) {
        if (!this.db) await this.init();

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([this.storeName], 'readwrite');
            const store = transaction.objectStore(this.storeName);
            const request = store.delete(id);

            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    async syncAll() {
        const pending = await this.getPendingRequests();
        
        // Sort by priority (ascending: 1 = high priority first, 10 = normal)
        pending.sort((a, b) => (a.priority || 10) - (b.priority || 10));
        
        const results = [];

        for (const item of pending) {
            try {
                const response = await fetch(item.url, {
                    method: item.method,
                    headers: new Headers(item.headers),
                    body: item.body
                });

                if (response.ok) {
                    await this.removeRequest(item.id);
                    results.push({ id: item.id, success: true, priority: item.priority });
                } else {
                    results.push({ id: item.id, success: false, error: 'HTTP ' + response.status });
                }
            } catch (error) {
                results.push({ id: item.id, success: false, error: error.message });
            }
        }

        return results;
    }

    async registerBackgroundSync() {
        if ('serviceWorker' in navigator && 'sync' in ServiceWorkerRegistration.prototype) {
            try {
                const registration = await navigator.serviceWorker.ready;
                await registration.sync.register('background-sync');
                console.log('[OfflineSync] Background sync registered');
            } catch (error) {
                console.error('[OfflineSync] Background sync registration failed:', error);
            }
        }
    }
}

// Global instance
const offlineSyncManager = new OfflineSyncManager();

// Initialize on page load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => offlineSyncManager.init());
} else {
    offlineSyncManager.init();
}

// Listen for online event to sync
window.addEventListener('online', async () => {
    console.log('[OfflineSync] Connection restored, syncing...');
    const results = await offlineSyncManager.syncAll();
    console.log('[OfflineSync] Sync results:', results);
    
    if (results.some(r => r.success)) {
        showToast('Offline changes synced successfully', 'success');
    }
});

// Intercept form submissions when offline
document.addEventListener('submit', async (event) => {
    if (!navigator.onLine) {
        const form = event.target;
        
        // Only queue if form has data-offline-sync attribute
        if (!form.hasAttribute('data-offline-sync')) {
            return;
        }

        event.preventDefault();

        const formData = new FormData(form);
        const url = form.action || window.location.href;
        const method = form.method.toUpperCase() || 'POST';
        const tableName = form.dataset.tableName || null; // Get table name from data-table-name attribute

        try {
            await offlineSyncManager.queueRequest(url, method, new Headers(), formData, tableName);
            await offlineSyncManager.registerBackgroundSync();
            
            showToast('Action queued. Will sync when online.', 'info');
        } catch (error) {
            console.error('[OfflineSync] Failed to queue request:', error);
            showToast('Failed to queue offline action', 'error');
        }
    }
});

// Helper to show toast notifications
function showToast(message, type = 'info') {
    if (typeof window.showToast === 'function') {
        window.showToast(message, type);
    } else {
        console.log(`[Toast ${type}] ${message}`);
    }
}
