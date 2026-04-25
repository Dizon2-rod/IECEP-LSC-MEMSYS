// IECEP-LSC MEMSYS - PWA Registration
if ('serviceWorker' in navigator) {
    window.addEventListener('load', async () => {
        try {
            const reg = await navigator.serviceWorker.register('/sw.js');
            console.log('SW registered:', reg.scope);
        } catch (err) {
            console.log('SW registration failed:', err);
        }
    });
}

// Install prompt
let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;

    // Show install button if exists
    const installBtn = document.getElementById('install-btn');
    if (installBtn) {
        installBtn.classList.remove('hidden');
        installBtn.addEventListener('click', async () => {
            installBtn.classList.add('hidden');
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            console.log('Install outcome:', outcome);
            deferredPrompt = null;
        });
    }
});

window.addEventListener('appinstalled', () => {
    deferredPrompt = null;
    console.log('App installed');
});
