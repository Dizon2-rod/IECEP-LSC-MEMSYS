// IECEP-LSC MEMSYS - PWA Registration
const PWA = {
    vapidPublicKey: window.PWA_PUBLIC_VAPID_KEY || '',
    deferredPrompt: null,

    async init() {
        if (!('serviceWorker' in navigator)) {
            return;
        }

        window.addEventListener('load', async () => {
            try {
                const swUrl = '/IECEP-LSC-MEMSYS/public/sw.js';
                const reg = await navigator.serviceWorker.register(swUrl);
                console.log('SW registered:', reg.scope);

                if (Notification.permission === 'granted') {
                    const readyReg = await navigator.serviceWorker.ready;
                    await this.subscribeToPush(readyReg);
                }
            } catch (err) {
                console.warn('SW registration failed:', err);
            }
        });

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            this.deferredPrompt = e;

            const installBtn = document.getElementById('install-btn');
            if (installBtn) {
                installBtn.classList.remove('hidden');
                installBtn.addEventListener('click', async () => {
                    installBtn.classList.add('hidden');
                    this.deferredPrompt.prompt();
                    const { outcome } = await this.deferredPrompt.userChoice;
                    console.log('Install outcome:', outcome);
                    this.deferredPrompt = null;
                });
            }
        });

        window.addEventListener('appinstalled', () => {
            this.deferredPrompt = null;
            console.log('App installed');
        });

        window.addEventListener('online', () => this.updateOfflineIndicator(true));
        window.addEventListener('offline', () => this.updateOfflineIndicator(false));

        document.addEventListener('DOMContentLoaded', () => {
            this.updateOfflineIndicator(navigator.onLine);
        });
    },

    async requestNotificationPermission() {
        if (!('Notification' in window)) {
            return;
        }

        const permission = await Notification.requestPermission();
        if (permission === 'granted') {
            const readyReg = await navigator.serviceWorker.ready;
            await this.subscribeToPush(readyReg);
        }

        return permission;
    },

    async subscribeToPush(registration) {
        if (!this.vapidPublicKey) {
            console.warn('PWA public VAPID key is not configured. Push subscription skipped.');
            return;
        }

        if (!('PushManager' in window)) {
            console.warn('PushManager not supported in this browser.');
            return;
        }

        try {
            let subscription = await registration.pushManager.getSubscription();
            if (!subscription) {
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: this.urlBase64ToUint8Array(this.vapidPublicKey)
                });
            }

            if (subscription) {
                await this.sendSubscription(subscription);
            }
        } catch (err) {
            console.warn('Push subscription failed:', err);
        }
    },

    async sendSubscription(subscription) {
        try {
            await fetch('/IECEP-LSC-MEMSYS/public/api/save-subscription.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    endpoint: subscription.endpoint,
                    keys: subscription.toJSON().keys,
                    browser: navigator.userAgent,
                    platform: navigator.platform,
                    metadata: {
                        language: navigator.language,
                        userAgent: navigator.userAgent
                    }
                })
            });
        } catch (err) {
            console.warn('Failed to save subscription:', err);
        }
    },

    updateOfflineIndicator(isOnline) {
        const status = document.getElementById('offline-status');
        if (!status) {
            return;
        }

        status.classList.toggle('hidden', isOnline);
        status.textContent = isOnline ? 'Online' : 'Offline mode available';
        status.style.background = isOnline ? '#10B981' : '#DC2626';
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
