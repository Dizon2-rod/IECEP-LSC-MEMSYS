class NotificationCenter {
    constructor() {
        this.apiBase = '/IECEP-LSC-MEMSYS/public/api/notifications.php';
        this.bell = null;
        this.dropdown = null;
        this.count = null;
        this.notifications = [];
        this.init();
    }

    init() {
        document.addEventListener('DOMContentLoaded', () => {
            this.bell = document.getElementById('notificationBell');
            this.dropdown = document.getElementById('notificationDropdown');
            this.count = document.getElementById('notificationCount');

            if (!this.bell || !this.dropdown || !this.count) {
                return;
            }

            this.bell.addEventListener('click', () => {
                this.toggleDropdown();
                this.fetchNotifications();
            });

            document.addEventListener('click', (event) => this.closeDropdownIfOutside(event));
            this.dropdown.addEventListener('click', (event) => this.handleDropdownClick(event));
            this.fetchStats();
            this.fetchNotifications();
            this.registerPushSubscription();
        });
    }

    toggleDropdown() {
        this.dropdown.classList.toggle('open');
    }

    closeDropdownIfOutside(event) {
        if (!this.dropdown || !this.bell) {
            return;
        }
        if (!this.dropdown.contains(event.target) && !this.bell.contains(event.target)) {
            this.dropdown.classList.remove('open');
        }
    }

    async fetchNotifications() {
        try {
            const response = await fetch(`${this.apiBase}?action=list`, { credentials: 'same-origin' });
            const data = await response.json();
            if (data.success) {
                this.notifications = data.notifications || [];
                this.renderNotifications();
            }
        } catch (error) {
            console.error('Failed to load notifications:', error);
        }
    }

    async fetchStats() {
        try {
            const response = await fetch(`${this.apiBase}?action=stats`, { credentials: 'same-origin' });
            const data = await response.json();
            if (data.success && data.stats) {
                this.updateBadge(data.stats.unread || 0);
            }
        } catch (error) {
            console.error('Failed to load notification stats:', error);
        }
    }

    updateBadge(count) {
        this.count.textContent = count > 0 ? count : '';
        this.count.classList.toggle('visible', count > 0);
    }

    renderNotifications() {
        const list = this.dropdown.querySelector('.notification-list');
        if (!list) {
            return;
        }

        const unread = this.notifications.filter(item => !item.read);
        this.updateBadge(unread.length);

        const header = `
            <li class="notification-controls">
                <button type="button" class="notification-action mark-all-read">Mark all read</button>
                <button type="button" class="notification-action refresh-notifications">Refresh</button>
            </li>
        `;

        const body = this.notifications.length === 0 ? '<li class="notification-empty">No notifications yet.</li>' :
            this.notifications.map(item => {
                const status = item.read ? 'read' : 'unread';
                const title = item.title || 'Notification';
                const bodyText = item.message || item.body || '';
                const time = item.created_at ? new Date(item.created_at).toLocaleString() : 'Just now';
                return `
                    <li class="notification-item ${status}" data-id="${item.id}">
                        <div class="notification-title">${title}</div>
                        <div class="notification-body">${bodyText}</div>
                        <div class="notification-time">${time}</div>
                    </li>
                `;
            }).join('');

        list.innerHTML = header + body;
    }

    async handleDropdownClick(event) {
        const markAllBtn = event.target.closest('.mark-all-read');
        const refreshBtn = event.target.closest('.refresh-notifications');
        const notificationItem = event.target.closest('.notification-item');

        if (markAllBtn) {
            event.stopPropagation();
            await this.markAllRead();
            return;
        }

        if (refreshBtn) {
            event.stopPropagation();
            this.fetchNotifications();
            return;
        }

        if (notificationItem) {
            event.stopPropagation();
            const id = notificationItem.dataset.id;
            if (id) {
                await this.markRead(id);
            }
        }
    }

    async markRead(notificationId) {
        try {
            const response = await fetch(`${this.apiBase}?action=mark_read`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ notification_id: notificationId })
            });
            const data = await response.json();
            if (data.success) {
                this.notifications = this.notifications.map(item => {
                    if (item.id == notificationId) {
                        return { ...item, read: true };
                    }
                    return item;
                });
                this.renderNotifications();
                await this.fetchStats();
            }
        } catch (error) {
            console.error('Failed to mark notification read:', error);
        }
    }

    async markAllRead() {
        try {
            const response = await fetch(`${this.apiBase}?action=mark_all_read`, {
                method: 'POST',
                credentials: 'same-origin',
            });
            const data = await response.json();
            if (data.success) {
                this.notifications = this.notifications.map(item => ({ ...item, read: true }));
                this.renderNotifications();
                await this.fetchStats();
            }
        } catch (error) {
            console.error('Failed to mark all notifications read:', error);
        }
    }

    async registerPushSubscription() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            return;
        }

        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                return;
            }

            const vapidResponse = await fetch(`${this.apiBase}?action=vapid_key`, { credentials: 'same-origin' });
            const vapidData = await vapidResponse.json();
            if (!vapidData.success || !vapidData.vapid_public_key) {
                return;
            }

            const applicationServerKey = this.urlBase64ToUint8Array(vapidData.vapid_public_key);
            const newSubscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey
            });

            await fetch('/IECEP-LSC-MEMSYS/public/api/save-subscription.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    endpoint: newSubscription.endpoint,
                    keys: newSubscription.toJSON().keys,
                    browser: navigator.userAgent,
                    platform: navigator.platform,
                    metadata: {
                        language: navigator.language,
                        hostname: window.location.hostname,
                    }
                })
            });
        } catch (error) {
            console.warn('Push subscription registration failed:', error);
        }
    }

    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }
}

new NotificationCenter();
