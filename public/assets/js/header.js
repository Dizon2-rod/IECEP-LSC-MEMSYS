// Header functionality for IECEP-LSC Membership System
class HeaderManager {
    constructor() {
        this.init();
    }

    init() {
        this.setupUserMenu();
        this.setupNotifications();
        this.setupSearch();
        this.setupMobileMenu();
        this.updateUserInfo();
        this.checkNewNotifications();
    }

    setupUserMenu() {
        const userMenuBtn = document.getElementById('user-menu-btn');
        const userDropdown = document.getElementById('user-dropdown');

        if (userMenuBtn && userDropdown) {
            userMenuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                userDropdown.classList.toggle('show');
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!userMenuBtn.contains(e.target) && !userDropdown.contains(e.target)) {
                    userDropdown.classList.remove('show');
                }
            });
        }
    }

    setupNotifications() {
        const notificationBtn = document.getElementById('notification-btn');
        const notificationDropdown = document.getElementById('notification-dropdown');

        if (notificationBtn && notificationDropdown) {
            notificationBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                notificationDropdown.classList.toggle('show');
                this.markNotificationsAsRead();
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!notificationBtn.contains(e.target) && !notificationDropdown.contains(e.target)) {
                    notificationDropdown.classList.remove('show');
                }
            });

            // Load notifications
            this.loadNotifications();
        }
    }

    setupSearch() {
        const searchInput = document.getElementById('header-search');
        const searchResults = document.getElementById('search-results');

        if (searchInput && searchResults) {
            let searchTimeout;

            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                const query = e.target.value.trim();

                if (query.length < 2) {
                    searchResults.classList.remove('show');
                    return;
                }

                searchTimeout = setTimeout(() => {
                    this.performSearch(query);
                }, 300);
            });

            // Close search results when clicking outside
            document.addEventListener('click', (e) => {
                if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                    searchResults.classList.remove('show');
                }
            });

            // Handle keyboard navigation
            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    searchResults.classList.remove('show');
                    searchInput.blur();
                }
            });
        }
    }

    setupMobileMenu() {
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');

        if (mobileMenuBtn && mobileMenu) {
            mobileMenuBtn.addEventListener('click', () => {
                mobileMenu.classList.toggle('show');
            });

            // Close mobile menu when clicking outside
            document.addEventListener('click', (e) => {
                if (!mobileMenuBtn.contains(e.target) && !mobileMenu.contains(e.target)) {
                    mobileMenu.classList.remove('show');
                }
            });
        }
    }

    updateUserInfo() {
        // Update user name and avatar in header
        const userNameElements = document.querySelectorAll('.user-name');
        const userAvatarElements = document.querySelectorAll('.user-avatar');

        // This would typically get user data from session/localStorage
        const userData = this.getCurrentUser();

        userNameElements.forEach(el => {
            el.textContent = userData.name || 'User';
        });

        userAvatarElements.forEach(el => {
            if (userData.avatar) {
                el.src = userData.avatar;
            } else {
                // Set default avatar with initials
                const initials = this.getInitials(userData.name || 'User');
                el.src = this.generateAvatarUrl(initials, userData.role);
            }
        });
    }

    async loadNotifications() {
        try {
            const response = await fetch('/api/notifications?action=list&limit=10');
            const data = await response.json();

            if (data.success) {
                this.renderNotifications(data.notifications);
                this.updateNotificationBadge(data.unread_count);
            }
        } catch (error) {
            console.error('Failed to load notifications:', error);
        }
    }

    renderNotifications(notifications) {
        const notificationList = document.getElementById('notification-list');

        if (!notificationList) return;

        if (notifications.length === 0) {
            notificationList.innerHTML = `
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i>
                    <p>No notifications</p>
                </div>
            `;
            return;
        }

        notificationList.innerHTML = notifications.map(notification => `
            <div class="notification-item ${notification.read ? '' : 'unread'}" data-id="${notification.id}">
                <div class="notification-icon">
                    <i class="fas ${this.getNotificationIcon(notification.type)}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${notification.title}</div>
                    <div class="notification-message">${notification.message}</div>
                    <div class="notification-time">${this.formatTime(notification.created_at)}</div>
                </div>
                ${!notification.read ? '<div class="notification-unread-dot"></div>' : ''}
            </div>
        `).join('');

        // Add click handlers
        notificationList.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', () => {
                const notificationId = item.dataset.id;
                this.handleNotificationClick(notificationId);
            });
        });
    }

    updateNotificationBadge(count) {
        const badge = document.getElementById('notification-badge');

        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }
    }

    async performSearch(query) {
        try {
            const response = await fetch(`/api/search?q=${encodeURIComponent(query)}&limit=5`);
            const data = await response.json();

            if (data.success) {
                this.renderSearchResults(data.results, query);
            }
        } catch (error) {
            console.error('Search failed:', error);
        }
    }

    renderSearchResults(results, query) {
        const searchResults = document.getElementById('search-results');

        if (!searchResults) return;

        if (results.length === 0) {
            searchResults.innerHTML = `
                <div class="search-empty">
                    <i class="fas fa-search"></i>
                    <p>No results found for "${query}"</p>
                </div>
            `;
            searchResults.classList.add('show');
            return;
        }

        searchResults.innerHTML = `
            ${results.map(result => `
                <a href="${result.url}" class="search-result-item">
                    <div class="search-result-icon">
                        <i class="fas ${this.getSearchResultIcon(result.type)}"></i>
                    </div>
                    <div class="search-result-content">
                        <div class="search-result-title">${this.highlightQuery(result.title, query)}</div>
                        <div class="search-result-meta">${result.type} • ${result.meta || ''}</div>
                    </div>
                </a>
            `).join('')}
            <div class="search-footer">
                <a href="/search?q=${encodeURIComponent(query)}">View all results</a>
            </div>
        `;

        searchResults.classList.add('show');
    }

    async markNotificationsAsRead() {
        try {
            await fetch('/api/notifications', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_all_read' })
            });

            // Update UI
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
                const dot = item.querySelector('.notification-unread-dot');
                if (dot) dot.remove();
            });

            this.updateNotificationBadge(0);
        } catch (error) {
            console.error('Failed to mark notifications as read:', error);
        }
    }

    async handleNotificationClick(notificationId) {
        try {
            // Mark as read
            await fetch('/api/notifications', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'mark_read',
                    notification_id: notificationId
                })
            });

            // Navigate to notification URL (if any)
            const notification = document.querySelector(`[data-id="${notificationId}"]`);
            if (notification) {
                // You could store URLs in notification data and navigate here
                // window.location.href = notification.dataset.url;
            }
        } catch (error) {
            console.error('Failed to handle notification click:', error);
        }
    }

    checkNewNotifications() {
        // Check for new notifications every 30 seconds
        setInterval(() => {
            this.loadNotifications();
        }, 30000);
    }

    // Utility methods
    getCurrentUser() {
        // This would typically get user data from session/localStorage/API
        return {
            name: 'John Doe', // Replace with actual user data
            role: 'member',
            avatar: null
        };
    }

    getInitials(name) {
        return name.split(' ').map(n => n[0]).join('').toUpperCase();
    }

    generateAvatarUrl(initials, role) {
        // Generate a simple colored avatar based on role
        const colors = {
            president: '#0B1D4A',
            vp: '#1a365d',
            treasurer: '#D4AF37',
            secretary: '#64748b',
            member: '#475569'
        };

        const color = colors[role] || colors.member;
        return `data:image/svg+xml,${encodeURIComponent(`
            <svg width="40" height="40" xmlns="http://www.w3.org/2000/svg">
                <circle cx="20" cy="20" r="20" fill="${color}"/>
                <text x="20" y="25" font-family="Arial" font-size="14" fill="white" text-anchor="middle">${initials}</text>
            </svg>
        `)}`;
    }

    getNotificationIcon(type) {
        const icons = {
            announcement: 'fa-bullhorn',
            payment: 'fa-credit-card',
            event: 'fa-calendar',
            message: 'fa-envelope',
            alert: 'fa-exclamation-triangle',
            success: 'fa-check-circle'
        };
        return icons[type] || 'fa-bell';
    }

    getSearchResultIcon(type) {
        const icons = {
            member: 'fa-user',
            institution: 'fa-university',
            event: 'fa-calendar',
            announcement: 'fa-bullhorn',
            page: 'fa-file'
        };
        return icons[type] || 'fa-search';
    }

    formatTime(timestamp) {
        const date = new Date(timestamp);
        const now = new Date();
        const diff = now - date;

        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);
        const days = Math.floor(diff / 86400000);

        if (minutes < 1) return 'Just now';
        if (minutes < 60) return `${minutes}m ago`;
        if (hours < 24) return `${hours}h ago`;
        if (days < 7) return `${days}d ago`;

        return date.toLocaleDateString();
    }

    highlightQuery(text, query) {
        if (!query) return text;
        const regex = new RegExp(`(${query})`, 'gi');
        return text.replace(regex, '<mark>$1</mark>');
    }
}

// Initialize header functionality when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.headerManager = new HeaderManager();
});

// Export for modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = HeaderManager;
}