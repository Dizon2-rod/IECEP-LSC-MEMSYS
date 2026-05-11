// Notification System
class NotificationManager {
    constructor() {
        this.unreadCount = 0;
        this.notifications = [];
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadNotifications();
        this.requestNotificationPermission();
        this.registerPushSubscription();
        this.setupRealtimeUpdates();
    }

    bindEvents() {
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const markAllRead = document.getElementById('markAllRead');

        if (notificationBtn) {
            notificationBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleDropdown();
            });
        }

        if (markAllRead) {
            markAllRead.addEventListener('click', () => this.markAllAsRead());
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!notificationDropdown?.contains(e.target)) {
                this.hideDropdown();
            }
        });
    }

    toggleDropdown() {
        const dropdown = document.getElementById('notificationDropdown');
        if (dropdown) {
            dropdown.classList.toggle('show');
        }
    }

    hideDropdown() {
        const dropdown = document.getElementById('notificationDropdown');
        if (dropdown) {
            dropdown.classList.remove('show');
        }
    }

    async loadNotifications() {
        try {
            const response = await fetch('/IECEP-LSC-MEMSYS/public/api/notifications.php?action=list');
            const data = await response.json();

            if (data.success) {
                this.notifications = data.notifications;
                this.updateUnreadCount();
                this.renderNotifications();
            }
        } catch (error) {
            console.error('Failed to load notifications:', error);
        }
    }

    updateUnreadCount() {
        this.unreadCount = this.notifications.filter(n => !n.read).length;
        const badge = document.getElementById('notificationBadge');
        if (badge) {
            badge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
            badge.style.display = this.unreadCount > 0 ? 'flex' : 'none';
        }
    }

    renderNotifications() {
        const list = document.getElementById('notificationList');
        if (!list) return;

        if (this.notifications.length === 0) {
            list.innerHTML = '<div class="notification-item"><p>No notifications yet</p></div>';
            return;
        }

        list.innerHTML = this.notifications.map(notification => `
            <div class="notification-item ${!notification.read ? 'unread' : ''}" data-id="${notification.id}">
                <p>${this.escapeHtml(notification.message)}</p>
                <div class="time">${this.formatTime(notification.created_at)}</div>
            </div>
        `).join('');

        // Bind click events
        list.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', () => {
                const id = item.dataset.id;
                this.markAsRead(id);
            });
        });
    }

    async markAsRead(notificationId) {
        try {
            const response = await fetch('/IECEP-LSC-MEMSYS/public/api/notifications.php?action=mark_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notification_id: notificationId })
            });

            const data = await response.json();
            if (data.success) {
                const notification = this.notifications.find(n => n.id === notificationId);
                if (notification) {
                    notification.read = true;
                    this.updateUnreadCount();
                    this.renderNotifications();
                }
            }
        } catch (error) {
            console.error('Failed to mark notification as read:', error);
        }
    }

    async markAllAsRead() {
        try {
            const response = await fetch('/IECEP-LSC-MEMSYS/public/api/notifications.php?action=mark_all_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });

            const data = await response.json();
            if (data.success) {
                this.notifications.forEach(n => n.read = true);
                this.updateUnreadCount();
                this.renderNotifications();
            }
        } catch (error) {
            console.error('Failed to mark all notifications as read:', error);
        }
    }

    async requestNotificationPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            const permission = await Notification.requestPermission();
            if (permission === 'granted') {
                console.log('Notification permission granted');
            }
        }
    }

    async registerPushSubscription() {
        if ('serviceWorker' in navigator && 'PushManager' in window) {
            try {
                const registration = await navigator.serviceWorker.register('/IECEP-LSC-MEMSYS/public/sw.js');
                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: window.PWA_PUBLIC_VAPID_KEY
                });

                await fetch('/IECEP-LSC-MEMSYS/public/api/notifications.php?action=subscribe_push', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ subscription: subscription.toJSON() })
                });
            } catch (error) {
                console.error('Push subscription failed:', error);
            }
        }
    }

    setupRealtimeUpdates() {
        if (window.supabaseClient) {
            window.supabaseClient
                .channel('notifications')
                .on('postgres_changes', {
                    event: 'INSERT',
                    schema: 'public',
                    table: 'notifications',
                    filter: `user_id=eq.${window.currentUserId}`
                }, (payload) => {
                    this.notifications.unshift(payload.new);
                    this.updateUnreadCount();
                    this.renderNotifications();
                    this.showBrowserNotification(payload.new);
                })
                .subscribe();
        }
    }

    showBrowserNotification(notification) {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('IECEP-LSC MEMSYS', {
                body: notification.message,
                icon: '/IECEP-LSC-MEMSYS/public/assets/icons/icon-192.png',
                badge: '/IECEP-LSC-MEMSYS/public/assets/icons/icon-72.png'
            });
        }
    }

    formatTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = now - date;

        if (diff < 60000) return 'Just now';
        if (diff < 3600000) return `${Math.floor(diff / 60000)}m ago`;
        if (diff < 86400000) return `${Math.floor(diff / 3600000)}h ago`;
        return date.toLocaleDateString();
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('notificationBtn')) {
        new NotificationManager();
    }
});