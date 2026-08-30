/**
 * IECEP-LSC MEMSYS - Progressive Web App (PWA) Controller
 * Handles Service Worker lifecycle, Install Prompts, Offline status, and Push Subscriptions.
 */
const PWA = {
    vapidPublicKey: window.PWA_PUBLIC_VAPID_KEY || (window.IECEP_CONFIG ? window.IECEP_CONFIG.VAPID_PUBLIC_KEY : ''),
    deferredPrompt: null,

    async init() {
        if (!('serviceWorker' in navigator)) {
            console.log('[PWA] Service Worker not supported on this browser.');
            return;
        }

        // Register Service Worker
        window.addEventListener('load', async () => {
            try {
                let basePath = '';
                if (window.IECEP_CONFIG && window.IECEP_CONFIG.PUBLIC_URL) {
                    basePath = window.IECEP_CONFIG.PUBLIC_URL;
                } else if (window.location.pathname.includes('/IECEP-LSC-MEMSYS')) {
                    basePath = '/IECEP-LSC-MEMSYS/public';
                } else {
                    basePath = '/public';
                }

                // Determine appropriate app root scope
                let appScope = '/';
                if (window.IECEP_CONFIG && window.IECEP_CONFIG.APP_URL) {
                    try {
                        const parsed = new URL(window.IECEP_CONFIG.APP_URL);
                        const pName = parsed.pathname.replace(/\/+$/, '');
                        appScope = pName ? `${pName}/` : '/';
                    } catch (e) {
                        appScope = window.location.pathname.includes('/IECEP-LSC-MEMSYS') ? '/IECEP-LSC-MEMSYS/' : '/';
                    }
                } else {
                    appScope = window.location.pathname.includes('/IECEP-LSC-MEMSYS') ? '/IECEP-LSC-MEMSYS/' : '/';
                }

                const swUrl = `${basePath}/sw.js`;
                let reg;
                try {
                    reg = await navigator.serviceWorker.register(swUrl, { scope: appScope });
                } catch (scopeErr) {
                    // Fallback to registering without explicit scope (uses Service Worker file directory)
                    reg = await navigator.serviceWorker.register(swUrl);
                }
                console.log('[PWA] Service Worker registered with scope:', reg.scope);

                // If permission already granted, ensure push subscription
                if ('Notification' in window && Notification.permission === 'granted') {
                    const readyReg = await navigator.serviceWorker.ready;
                    await this.subscribeToPush(readyReg);
                }
            } catch (err) {
                console.warn('[PWA] Service Worker registration notice:', err);
            }
        });

        // Capture BeforeInstallPrompt for custom Install UI
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            this.deferredPrompt = e;
            console.log('[PWA] Capture beforeinstallprompt event');

            // Show any install buttons in the UI
            document.querySelectorAll('.pwa-install-btn, #pwaInstallBtn, #pwaFloatingInstall').forEach(btn => {
                btn.style.display = 'inline-flex';
                btn.onclick = (evt) => {
                    evt.preventDefault();
                    this.promptInstall();
                };
            });

            // Show floating install banner if not dismissed before
            if (!localStorage.getItem('iecep_pwa_banner_dismissed')) {
                this.showInstallBanner();
            }
        });

        window.addEventListener('appinstalled', () => {
            this.deferredPrompt = null;
            console.log('[PWA] Application successfully installed on device!');
            this.hideInstallBanner();
            this.showToast('IECEP-LSC App installed successfully!', 'success');
        });

        // Online & Offline Listeners
        window.addEventListener('online', () => {
            this.showToast('Network reconnected. You are back online!', 'success');
            this.updateOfflineIndicator(true);
        });

        window.addEventListener('offline', () => {
            this.showToast('You are offline. Cached pages remain accessible.', 'warning');
            this.updateOfflineIndicator(false);
        });

        document.addEventListener('DOMContentLoaded', () => {
            this.updateOfflineIndicator(navigator.onLine);
        });
    },

    async promptInstall() {
        if (!this.deferredPrompt) {
            alert('To install, use your browser menu (3 dots) and select "Install IECEP-LSC" or "Add to Home Screen".');
            return;
        }

        this.deferredPrompt.prompt();
        const { outcome } = await this.deferredPrompt.userChoice;
        console.log('[PWA] User choice:', outcome);
        this.deferredPrompt = null;
        this.hideInstallBanner();
    },

    showInstallBanner() {
        if (document.getElementById('pwaFloatingBanner')) return;

        const logoUrl = (window.IECEP_CONFIG && window.IECEP_CONFIG.ASSETS_URL) 
            ? `${window.IECEP_CONFIG.ASSETS_URL}/icons/iecep-logo.png`
            : (window.location.pathname.includes('/IECEP-LSC-MEMSYS') ? '/IECEP-LSC-MEMSYS/public/assets/icons/iecep-logo.png' : '/public/assets/icons/iecep-logo.png');

        const banner = document.createElement('div');
        banner.id = 'pwaFloatingBanner';
        banner.innerHTML = `
            <div style="position:fixed;bottom:20px;right:20px;z-index:99999;background:linear-gradient(135deg,#07122E 0%,#0B1D4A 100%);color:#FFFFFF;border:1px solid rgba(212,175,55,0.4);box-shadow:0 12px 35px rgba(0,0,0,0.35);border-radius:14px;padding:14px 18px;max-width:360px;display:flex;align-items:center;gap:14px;font-family:'DM Sans',sans-serif;animation:slideUp 0.4s ease-out;">
                <img src="${logoUrl}" style="width:42px;height:42px;border-radius:8px;border:1px solid #D4AF37;object-fit:contain;background:#0B1D4A;" alt="Logo">
                <div style="flex:1;">
                    <strong style="display:block;font-size:0.9rem;color:#F8E7A2;">Install IECEP-LSC App</strong>
                    <span style="font-size:0.75rem;color:rgba(255,255,255,0.8);line-height:1.3;display:block;">Fast offline access & instant updates on your home screen.</span>
                </div>
                <div style="display:flex;gap:6px;align-items:center;">
                    <button id="pwaBannerInstallBtn" style="background:#D4AF37;color:#0B1D4A;font-weight:700;font-size:0.78rem;border:none;padding:6px 12px;border-radius:6px;cursor:pointer;">Install</button>
                    <button id="pwaBannerCloseBtn" style="background:transparent;color:rgba(255,255,255,0.6);border:none;font-size:1.1rem;cursor:pointer;line-height:1;">&times;</button>
                </div>
            </div>
        `;
        document.body.appendChild(banner);

        document.getElementById('pwaBannerInstallBtn').onclick = () => this.promptInstall();
        document.getElementById('pwaBannerCloseBtn').onclick = () => {
            localStorage.setItem('iecep_pwa_banner_dismissed', 'true');
            this.hideInstallBanner();
        };
    },

    hideInstallBanner() {
        const banner = document.getElementById('pwaFloatingBanner');
        if (banner) banner.remove();
    },

    showToast(message, type = 'info') {
        const toast = document.createElement('div');
        const bg = type === 'success' ? '#059669' : (type === 'warning' ? '#D97706' : '#0B1D4A');
        toast.style.cssText = `
            position: fixed; top: 20px; right: 20px; z-index: 999999;
            background: ${bg}; color: #FFFFFF; font-family: 'DM Sans', sans-serif;
            font-size: 0.85rem; font-weight: 600; padding: 10px 18px; border-radius: 8px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.2);
            transition: opacity 0.3s ease;
        `;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    },

    updateOfflineIndicator(isOnline) {
        const indicator = document.getElementById('offline-status');
        if (indicator) {
            indicator.style.display = isOnline ? 'none' : 'block';
            indicator.textContent = isOnline ? 'Online' : 'Offline Mode Active';
        }
    },

    async subscribeToPush(registration) {
        if (!this.vapidPublicKey || !('PushManager' in window)) return;
        try {
            let subscription = await registration.pushManager.getSubscription();
            if (!subscription) {
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: this.urlBase64ToUint8Array(this.vapidPublicKey)
                });
            }
        } catch (err) {
            console.warn('[PWA] Push subscription notice:', err);
        }
    },

    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }
};

PWA.init();
