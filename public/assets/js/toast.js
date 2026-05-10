/**
 * Toast Notification System for IECEP-LSC Membership System
 * Provides user-friendly notifications with proper styling and accessibility
 */

class ToastManager {
    constructor() {
        this.container = null;
        this.toasts = [];
        this.init();
    }

    init() {
        // Create container if it doesn't exist
        if (!document.getElementById('toast-container')) {
            this.container = document.createElement('div');
            this.container.id = 'toast-container';
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        } else {
            this.container = document.getElementById('toast-container');
        }

        // Add styles if not already present
        this.addStyles();
    }

    addStyles() {
        if (document.getElementById('toast-styles')) return;

        const styles = document.createElement('style');
        styles.id = 'toast-styles';
        styles.textContent = `
            .toast-container {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                pointer-events: none;
                max-width: 400px;
            }

            .toast {
                background: white;
                border-radius: 8px;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
                padding: 16px 20px;
                margin-bottom: 12px;
                display: flex;
                align-items: flex-start;
                gap: 12px;
                pointer-events: auto;
                transform: translateX(100%);
                opacity: 0;
                transition: all 0.3s ease-in-out;
                border-left: 4px solid;
                position: relative;
                overflow: hidden;
            }

            .toast.show {
                transform: translateX(0);
                opacity: 1;
            }

            .toast.hide {
                transform: translateX(100%);
                opacity: 0;
            }

            .toast::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 3px;
                background: currentColor;
                animation: toast-progress 4s linear forwards;
            }

            .toast.success { border-left-color: #10b981; color: #059669; }
            .toast.error { border-left-color: #ef4444; color: #dc2626; }
            .toast.warning { border-left-color: #f59e0b; color: #d97706; }
            .toast.info { border-left-color: #3b82f6; color: #2563eb; }

            .toast-icon {
                flex-shrink: 0;
                width: 20px;
                height: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
                color: white;
                font-size: 12px;
            }

            .toast.success .toast-icon { background: #10b981; }
            .toast.error .toast-icon { background: #ef4444; }
            .toast.warning .toast-icon { background: #f59e0b; }
            .toast.info .toast-icon { background: #3b82f6; }

            .toast-content {
                flex: 1;
                min-width: 0;
            }

            .toast-title {
                font-weight: 600;
                font-size: 14px;
                margin-bottom: 4px;
                color: #0f172a;
            }

            .toast-message {
                font-size: 14px;
                line-height: 1.4;
                color: #64748b;
                margin: 0;
            }

            .toast-close {
                background: none;
                border: none;
                color: #94a3b8;
                cursor: pointer;
                padding: 4px;
                border-radius: 4px;
                transition: all 0.2s ease;
                flex-shrink: 0;
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 16px;
            }

            .toast-close:hover {
                background: #f1f5f9;
                color: #475569;
            }

            .toast-action {
                margin-top: 8px;
                padding-top: 8px;
                border-top: 1px solid #e2e8f0;
            }

            .toast-action .btn {
                font-size: 12px;
                padding: 6px 12px;
            }

            @keyframes toast-progress {
                from { width: 100%; }
                to { width: 0%; }
            }

            @media (max-width: 768px) {
                .toast-container {
                    left: 20px;
                    right: 20px;
                    max-width: none;
                }

                .toast {
                    padding: 12px 16px;
                }
            }
        `;
        document.head.appendChild(styles);
    }

    show(options) {
        const {
            title = '',
            message = '',
            type = 'info',
            duration = 4000,
            action = null,
            persistent = false
        } = options;

        const toast = document.createElement('div');
        toast.className = `toast ${type}`;

        const iconMap = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };

        toast.innerHTML = `
            <div class="toast-icon">${iconMap[type] || 'ℹ'}</div>
            <div class="toast-content">
                ${title ? `<div class="toast-title">${title}</div>` : ''}
                <div class="toast-message">${message}</div>
                ${action ? `<div class="toast-action">${action}</div>` : ''}
            </div>
            <button class="toast-close" aria-label="Close notification">×</button>
        `;

        // Add close button functionality
        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => this.hide(toast));

        // Add action button functionality if provided
        if (action) {
            const actionBtn = toast.querySelector('.toast-action .btn');
            if (actionBtn) {
                actionBtn.addEventListener('click', () => {
                    this.hide(toast);
                    // Action callback would be handled by the caller
                });
            }
        }

        this.container.appendChild(toast);
        this.toasts.push(toast);

        // Trigger animation
        setTimeout(() => toast.classList.add('show'), 10);

        // Auto-hide unless persistent
        if (!persistent && duration > 0) {
            setTimeout(() => this.hide(toast), duration);
        }

        return toast;
    }

    hide(toast) {
        if (!toast) return;

        toast.classList.add('hide');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
            this.toasts = this.toasts.filter(t => t !== toast);
        }, 300);
    }

    success(message, title = 'Success', options = {}) {
        return this.show({ ...options, type: 'success', title, message });
    }

    error(message, title = 'Error', options = {}) {
        return this.show({ ...options, type: 'error', title, message });
    }

    warning(message, title = 'Warning', options = {}) {
        return this.show({ ...options, type: 'warning', title, message });
    }

    info(message, title = 'Info', options = {}) {
        return this.show({ ...options, type: 'info', title, message });
    }

    clear() {
        this.toasts.forEach(toast => this.hide(toast));
    }
}

// Global toast instance
const toast = new ToastManager();

// Make it globally available
window.toast = toast;

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        // Initialize toast system
        console.log('Toast notification system initialized');
    });
} else {
    // DOM already loaded
    console.log('Toast notification system initialized');
}