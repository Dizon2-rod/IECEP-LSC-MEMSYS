<?php
require_once __DIR__ . '/../bootstrap.php';
// Prevent multiple inclusions
if (defined('HEAD_META_INCLUDED')) return;
define('HEAD_META_INCLUDED', true);
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="manifest" href="<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES) ?>/manifest.json">
<link rel="icon" type="image/png" sizes="192x192" href="<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES) ?>/assets/icons/icon-192x192.png">
<link rel="icon" type="image/png" sizes="96x96" href="<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES) ?>/assets/icons/icon-96x96.png">
<link rel="icon" type="image/png" sizes="48x48" href="<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES) ?>/assets/icons/favicon.png">
<link rel="icon" type="image/png" sizes="32x32" href="<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES) ?>/assets/icons/favicon.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES) ?>/assets/icons/favicon.png">
<link rel="shortcut icon" href="<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES) ?>/favicon.ico">
<link rel="apple-touch-icon" sizes="180x180" href="<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES) ?>/assets/icons/apple-touch-icon.png">
<link rel="apple-touch-icon" sizes="192x192" href="<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES) ?>/assets/icons/icon-192x192.png">
<link rel="apple-touch-icon" sizes="512x512" href="<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES) ?>/assets/icons/icon-512x512.png">
<meta name="theme-color" content="#0B1D4A">
<meta name="msapplication-TileColor" content="#0B1D4A">
<meta name="msapplication-TileImage" content="<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES) ?>/assets/icons/icon-144x144.png">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="application-name" content="IECEP - LSC MEMSYS">
<meta name="apple-mobile-web-app-title" content="IECEP - LSC MEMSYS">
<?php if (function_exists('csrf_meta')) { echo csrf_meta(); } ?>
<script>
    window.IECEP_CONFIG = {
        SUPABASE_URL: '<?= htmlspecialchars(SUPABASE_URL, ENT_QUOTES) ?>',
        SUPABASE_ANON_KEY: '<?= htmlspecialchars(SUPABASE_ANON_KEY, ENT_QUOTES) ?>',
        APP_URL: '<?= htmlspecialchars(APP_URL, ENT_QUOTES) ?>',
        APP_ENV: '<?= htmlspecialchars(APP_ENV, ENT_QUOTES) ?>',
        PORTAL_URL: '<?= htmlspecialchars(PORTAL_URL, ENT_QUOTES) ?>',
        PUBLIC_URL: '<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES) ?>',
        ASSETS_URL: '<?= htmlspecialchars(ASSETS_URL, ENT_QUOTES) ?>',
        VAPID_PUBLIC_KEY: '<?= function_exists('env') ? htmlspecialchars(env('VAPID_PUBLIC_KEY', ''), ENT_QUOTES) : '' ?>'
    };
</script>
<!-- Supabase JS Client Library (for real-time subscriptions) -->
<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
<!-- Frontend Configuration -->
<?php if (function_exists('outputFrontendConfig')) { outputFrontendConfig(); } ?>
<!-- Realtime engine (must load before shim) -->
<script src="<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES) ?>/assets/js/realtime.js" defer></script>
<!-- Realtime shim (legacy compat + subscribeToAllRealTimeUpdates) -->
<script src="<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES) ?>/js/realtime.js" defer></script>
<script src="<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES) ?>/js/pwa.js" defer></script>
<?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
<script src="<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES) ?>/js/notifications.js" defer></script>
<script src="<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES) ?>/assets/js/offline-manager.js" defer></script>
<?php endif; ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="stylesheet" href="<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES) ?>/assets/css/font-awesome.css">
<link rel="stylesheet" href="<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES) ?>/assets/css/professional.css">
<link rel="stylesheet" href="<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES) ?>/css/portal-polish.css">
<link rel="stylesheet" href="<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES) ?>/assets/css/styles.css">
<script>
    window.iecepDarkModeReady = true;
    document.addEventListener('DOMContentLoaded', function() {
        const storedMode = localStorage.getItem('iecepDarkMode') || localStorage.getItem('memsys-dark-mode');
        const body = document.body;
        const toggleButtons = Array.from(document.querySelectorAll('#darkModeToggle, #sidebarDarkModeToggle, #dark-mode-toggle'));

        function setMode(dark) {
            if (dark) {
                body.classList.add('dark-mode');
            } else {
                body.classList.remove('dark-mode');
            }
            localStorage.setItem('iecepDarkMode', dark ? 'dark' : 'light');
            localStorage.setItem('memsys-dark-mode', dark ? 'true' : 'false');
            toggleButtons.forEach(button => {
                const icon = button.querySelector('i');
                if (!icon) return;
                icon.classList.toggle('fa-moon', !dark);
                icon.classList.toggle('fa-sun', dark);
            });
        }

        if (storedMode === 'dark' || storedMode === 'true') {
            setMode(true);
        }

        toggleButtons.forEach(button => {
            button.addEventListener('click', function() {
                const dark = !body.classList.contains('dark-mode');
                setMode(dark);
            });
        });
    });
</script>
<style>
    /* Design Tokens */
    :root {
        --primary: #0B1D4A;
        --primary-light: #1E3A6E;
        --accent: #D4AF37;
        --accent-hover: #B8960C;
        --neutral-100: #F8FAFC;
        --neutral-200: #E2E8F0;
        --neutral-300: #CBD5E1;
        --neutral-500: #64748B;
        --neutral-700: #334155;
        --neutral-900: #0F172A;
        --white: #FFFFFF;
        --text-dark: #0F172A;
        --space-2: 16px;
        --space-3: 24px;
        --space-4: 32px;
        --space-6: 48px;
        --space-8: 64px;
        --space-12: 96px;
        --radius-md: 8px;
        --radius-lg: 16px;
        --radius-xl: 24px;
        --radius-full: 9999px;
        --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
        --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
        --shadow-xl: 0 20px 25px rgba(0,0,0,0.1);
        --transition-base: 200ms ease-in-out;
    }

    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body { font-family: 'Inter', sans-serif; background: var(--white); color: var(--text-dark); line-height: 1.6; overflow-x: hidden; }
    *:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
    
    /* Responsive Container */
    .container { width: 100%; padding-right: 1rem; padding-left: 1rem; margin-right: auto; margin-left: auto; max-width: 1140px; }
    @media (min-width: 576px) { .container { max-width: 540px; } }
    @media (min-width: 768px) { .container { max-width: 720px; padding-right: 1.5rem; padding-left: 1.5rem; } }
    @media (min-width: 992px) { .container { max-width: 960px; } }
    @media (min-width: 1200px) { .container { max-width: 1140px; } }
    
    /* Responsive Images */
    img { max-width: 100%; height: auto; }
    
    /* Touch Targets */
    .btn, button, input[type="submit"], input[type="button"], .nav-link, .dropdown-item { 
        min-height: 48px; 
        min-width: 48px; 
        display: inline-flex; 
        align-items: center; 
        justify-content: center;
    }

    .install-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.7rem 1rem;
        border-radius: 9999px;
        border: 1px solid var(--primary);
        background: var(--white);
        color: var(--primary);
        font-weight: 700;
        cursor: pointer;
        transition: background-color var(--transition-base), color var(--transition-base), transform var(--transition-base);
    }

    .install-btn.hidden {
        display: none !important;
    }

    .network-status {
        position: fixed;
        right: 1rem;
        bottom: 1rem;
        z-index: 1100;
        padding: 0.65rem 1rem;
        border-radius: 9999px;
        background: rgba(11, 29, 74, 0.95);
        color: #FFFFFF;
        box-shadow: 0 12px 28px rgba(0,0,0,0.18);
        font-size: 0.85rem;
        backdrop-filter: blur(10px);
    }

    .offline-status {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.85rem;
        background: #DC2626;
        color: #FFFFFF;
        margin-top: 0.75rem;
    }
    
    /* Responsive Tables */
    .table-responsive { 
        overflow-x: auto; 
        -webkit-overflow-scrolling: touch; 
    }
    
    /* Mobile Menu Overlay */
    .mobile-menu-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: #FFFFFF;
        z-index: 99999;
        display: none;
        overflow-y: auto;
        opacity: 0;
        transition: opacity 0.25s ease;
    }
    .mobile-menu-overlay.active {
        display: block !important;
        opacity: 1;
    }
    
    /* Responsive Sidebar */
    .dashboard-container {
        display: flex;
        min-height: 100vh;
    }
    .sidebar {
        width: 260px;
        background: var(--primary);
        color: white;
        position: fixed;
        left: 0;
        top: 0;
        height: 100vh;
        overflow-y: auto;
        z-index: 1000;
        transform: translateX(0);
        transition: transform 0.3s ease;
    }
    .sidebar.mobile-hidden {
        transform: translateX(-100%);
    }
    .main-content {
        flex: 1;
        margin-left: var(--sidebar-width, 260px);
        transition: margin-left 0.3s ease;
    }
    .main-content.sidebar-expanded {
        margin-left: 0;
    }
    .sidebar-toggle {
        display: none;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 8px;
        padding: 0.5rem;
        cursor: pointer;
    }
    
    /* Responsive Grid Utilities */
    .grid-responsive {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
    }
    .grid-2 {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
    }
    .grid-3 {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
    }
    
    /* Responsive Typography */
    .text-responsive {
        font-size: clamp(1rem, 2.5vw, 1.25rem);
    }
    .title-responsive {
        font-size: clamp(1.5rem, 4vw, 2.5rem);
    }
    
    /* Accessibility */
    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }
    
    /* Print Styles */
    @media print {
        .sidebar, .sidebar-toggle, .mobile-menu-overlay {
            display: none !important;
        }
        .main-content {
            margin-left: 0 !important;
        }
    }

    /* Header & Navbar */
    .header { position: fixed; top: 0; left: 0; right: 0; background: rgba(255,255,255,0.97); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04); z-index: 1000; }
    .header-container { display: flex; align-items: center; justify-content: space-between; padding: 0.55rem 1.25rem; max-width: 1200px; margin: 0 auto; }
    .logo { display: flex; align-items: center; gap: 0.55rem; text-decoration: none; color: #0B1D4A; font-weight: 800; font-size: 1rem; letter-spacing: -0.01em; }
    .logo-img { width: 36px; height: 36px; object-fit: contain; }
    .nav { display: none; }
    @media (min-width: 768px) { .nav { display: flex; align-items: center; gap: 0.75rem; } }
    .nav-links { display: flex; align-items: center; gap: 0.35rem; list-style: none; margin: 0; padding: 0; }
    .nav-link { color: #334155; text-decoration: none; font-weight: 600; font-size: 0.88rem; padding: 0.4rem 0.75rem; border-radius: 8px; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 0.35rem; }
    .nav-link:hover { color: #0B1D4A; background: rgba(11, 29, 74, 0.05); }
    .btn-login { background: #0B1D4A; border: 1px solid #0B1D4A; color: #ffffff; padding: 0.4rem 1.15rem; border-radius: 999px; font-weight: 700; font-size: 0.85rem; text-decoration: none; cursor: pointer; transition: all 0.2s ease; display: inline-flex; align-items: center; }
    .btn-login:hover { background: #D4AF37; border-color: #D4AF37; color: #0B1D4A; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3); }
    .mobile-menu-btn { display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 3.5px; width: 28px; height: 28px; background: none; border: none; cursor: pointer; padding: 0; }
    @media (min-width: 768px) { .mobile-menu-btn { display: none; } }
    .mobile-menu-btn span { width: 18px; height: 2px; background: #0B1D4A; border-radius: 2px; }

    /* Dropdown - Professional Styling */
    .nav-item { position: relative; list-style: none; }
    .dropdown-menu { 
        position: absolute; 
        top: calc(100% + 4px); 
        left: 0; 
        background: #ffffff; 
        border: 1px solid #e2e8f0; 
        border-radius: 12px; 
        box-shadow: 0 10px 30px rgba(11, 29, 74, 0.12); 
        min-width: 220px; 
        opacity: 0; 
        visibility: hidden; 
        transform: translateY(-6px); 
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); 
        z-index: 1001; 
        padding: 6px; 
        list-style: none;
        margin: 0;
    }
    .dropdown-menu::before {
        content: '';
        position: absolute;
        top: -6px;
        left: 20px;
        width: 10px;
        height: 10px;
        background: #ffffff;
        border-left: 1px solid #e2e8f0;
        border-top: 1px solid #e2e8f0;
        transform: rotate(45deg);
    }
    .nav-item:hover > .dropdown-menu { opacity: 1; visibility: visible; transform: translateY(0); }
    .dropdown-menu li { list-style: none; }
    .dropdown-item { 
        display: block;
        padding: 8px 12px; 
        color: #334155; 
        text-decoration: none; 
        border-radius: 6px; 
        transition: all 0.18s ease; 
        white-space: nowrap; 
        font-size: 0.85rem;
        font-weight: 500;
    }
    .dropdown-item:hover { 
        background: rgba(11, 29, 74, 0.05); 
        color: #0B1D4A; 
    }
    .dropdown-item.disabled { color: var(--neutral-400); cursor: not-allowed; }
    .dropdown-item.disabled:hover { background: transparent; color: var(--neutral-400); }
    
    /* Nested dropdown - Professional */
    .dropdown-menu .nav-item { position: relative; list-style: none; }
    .dropdown-menu .dropdown-menu { 
        left: calc(100% + 4px); 
        top: 0; 
        margin-top: 0; 
        margin-left: 0;
    }
    .dropdown-menu .dropdown-menu::before {
        left: -6px;
        top: 14px;
        border-right: none;
        border-top: none;
        border-left: 1px solid #e2e8f0;
        border-bottom: 1px solid #e2e8f0;
    }
    .dropdown-menu .nav-item:hover > .dropdown-menu { opacity: 1; visibility: visible; transform: translateX(0); }

    /* Hero */
    .hero { 
        position: relative; 
        min-height: auto; 
        display: flex; 
        flex-direction: column; 
        align-items: center; 
        justify-content: flex-start; 
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 50%, var(--primary) 100%); 
        color: var(--white); 
        padding: 55px var(--space-4) 35px; 
        overflow: hidden; 
    }
    .hero-bg { position: absolute; inset: 0; background: url('public/uploads/features/1776563415_hero.png') center/cover no-repeat; z-index: 0; }
    .hero-overlay { position: absolute; inset: 0; background: linear-gradient(135deg, rgba(11, 29, 74, 0.6) 0%, rgba(30, 58, 110, 0.55) 50%, rgba(11, 29, 74, 0.6) 100%); z-index: 1; }
    .hero-content { position: relative; z-index: 10; text-align: center; max-width: 920px; margin: 0 auto 1.5rem; display: flex; flex-direction: column; justify-content: flex-start; }
    .hero-tagline { font-family: 'Times New Roman', serif; font-size: 2.1rem; font-weight: 700; color: var(--accent); font-style: italic; margin-bottom: 0.4rem; margin-top: 15px; }
    .hero-title { font-size: clamp(1.65rem, 3.4vw, 2.45rem); word-spacing: normal; font-weight: 700; line-height: 1.35; margin-bottom: 1.35rem; }
    .hero-subtitle { font-size: 1.1rem; opacity: 0.95; margin-bottom: 1.35rem; }
    .hero-buttons { display: flex; gap: 0.85rem; justify-content: center; flex-wrap: wrap; }
    .btn { display: inline-flex; align-items: center; gap: var(--space-2); padding: 0.7rem 1.45rem; font-size: 0.95rem; font-weight: 600; border-radius: var(--radius-full); border: none; cursor: pointer; text-decoration: none; transition: all var(--transition-base); }
    .btn-primary { background: var(--accent); color: var(--primary); }
    .btn-primary:hover { background: var(--accent-hover); transform: translateY(-2px); }
    .btn-outline { background: transparent; border: 2px solid var(--white); color: var(--white); }
    .btn-outline:hover { background: var(--white); color: var(--primary); }

    /* Responsive Schools inside Hero */
    .hero-schools { position: relative; z-index: 10; width: 100%; max-width: 960px; margin: 1.25rem auto 0; padding: 0.5rem 1rem; }
    .schools-grid { 
        display: grid; 
        grid-template-columns: repeat(4, 1fr); 
        gap: 1rem 1.25rem; 
        align-items: center; 
        justify-items: center; 
        max-width: 100%; 
    }
    @media (min-width: 640px) { 
        .schools-grid { 
            grid-template-columns: repeat(8, 1fr); 
            gap: 1.15rem; 
        } 
    }
    .schools-grid img { 
        width: 100%; 
        max-width: 54px; 
        height: 54px; 
        object-fit: contain; 
        filter: drop-shadow(0 2px 5px rgba(0,0,0,0.3)); 
        opacity: 0.95; 
        transition: transform 0.25s ease, filter 0.25s ease, opacity 0.25s ease; 
        background: transparent; 
        padding: 0; 
        border-radius: 0; 
        box-shadow: none; 
        border: none; 
    }
    @media (max-width: 480px) {
        .schools-grid img {
            max-width: 44px;
            height: 44px;
        }
    }
    .schools-grid img:hover { 
        transform: translateY(-3px) scale(1.12); 
        filter: drop-shadow(0 6px 12px rgba(0,0,0,0.45)); 
        opacity: 1; 
    }

    /* Section */
    .section { padding: var(--space-12) 0; }
    .section-title { font-size: clamp(1.75rem, 4vw, 2.5rem); font-weight: 800; color: var(--primary); text-align: center; margin-bottom: var(--space-2); position: relative; }
    .section-title::after { content: ''; display: block; width: 60px; height: 4px; background: linear-gradient(90deg, var(--accent), #F5A623); margin: var(--space-2) auto 0; border-radius: var(--radius-md); }
    .section-subtitle { text-align: center; color: var(--neutral-500); max-width: 600px; margin: 0 auto var(--space-8); font-size: 1.1rem; }

    /* Cards */
    .whats-new { background: var(--neutral-100); }
    .cards-grid { display: grid; grid-template-columns: 1fr; gap: 3rem; max-width: 1200px; margin: 0 auto; padding: 0 var(--space-2); }
    @media (min-width: 768px) { .cards-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (min-width: 1024px) { .cards-grid { grid-template-columns: repeat(3, 1fr); } }
    .card { background: var(--white); border-radius: var(--radius-lg); overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: all var(--transition-base); border: 1px solid var(--neutral-200); }
    .card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,0.12); }
    .card-image { height: 240px; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); display: flex; align-items: center; justify-content: center; color: var(--white); overflow: hidden; position: relative; }
    .card-image img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; display: block; }
    .card-image i { font-size: 3rem; opacity: 0.5; position: relative; z-index: 1; }
    .card-content { padding: var(--space-6); }
    .card-meta { display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-3); font-size: 0.85rem; }
    .card-date { color: var(--neutral-500); }
    .card-category { color: #0891B2; font-weight: 500; }
    .card-title { font-size: 1.25rem; font-weight: 600; color: var(--neutral-900); margin-bottom: var(--space-3); line-height: 1.4; }
    .card-text { color: var(--neutral-500); margin-bottom: var(--space-4); font-size: 1rem; line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    .card-link { color: #0891B2; text-decoration: none; font-weight: 600; font-size: 1rem; display: inline-flex; align-items: center; gap: var(--space-2); transition: all var(--transition-fast); }
    .card-link:hover { color: #0E7490; gap: var(--space-3); }
    .card-link i { font-size: 0.85rem; }

    /* Steps */
    .steps-grid { display: grid; grid-template-columns: 1fr; gap: var(--space-4); max-width: 1200px; margin: 0 auto; padding: 0 var(--space-2); }
    @media (min-width: 640px) { .steps-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (min-width: 992px) { .steps-grid { grid-template-columns: repeat(4, 1fr); } }
    .step-card { background: var(--white); border: 1px solid var(--neutral-200); border-radius: var(--radius-lg); padding: var(--space-6) var(--space-4); text-align: center; transition: all var(--transition-base); }
    .step-card:hover { border-color: var(--accent); box-shadow: var(--shadow-lg); transform: translateY(-4px); }
    .step-number { width: 64px; height: 64px; background: linear-gradient(135deg, var(--accent) 0%, #F5A623 100%); color: var(--primary); font-size: 1.75rem; font-weight: 800; border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; margin: 0 auto var(--space-4); }
    .step-card h3 { font-size: 1.125rem; font-weight: 700; color: var(--primary); margin-bottom: var(--space-2); }
    .step-card p { color: var(--neutral-500); font-size: 0.95rem; }

    /* Contact */
    .contact { background: linear-gradient(135deg, #0B1D4A 0%, #142a6b 100%); color: var(--white); padding: var(--space-12) 0; position: relative; overflow: hidden; }
    .contact::before { content: ''; position: absolute; inset: 0; background: url('/IECEP-LSC-MEMSYS/public/assets/icons/hero.png') center/cover no-repeat; opacity: 0.7; z-index: 0; }
    .contact::after { content: ''; position: absolute; inset: 0; background: rgba(11, 29, 74, 0.6); z-index: 1; }
    .contact-container { display: grid; grid-template-columns: 1fr; gap: var(--space-8); max-width: 1200px; margin: 0 auto; padding: 0 var(--space-2); position: relative; z-index: 2; }
    @media (min-width: 768px) { .contact-container { grid-template-columns: 1fr 1fr; padding: 0 var(--space-4); } }
    .contact-content { display: flex; flex-direction: column; justify-content: flex-end; }
    .contact-form { background: var(--white); border-radius: var(--radius-xl); padding: var(--space-6); color: var(--text-dark); }
    .form-group { margin-bottom: var(--space-4); }
    .form-label { display: block; margin-bottom: var(--space-2); font-weight: 600; color: var(--neutral-700); }
    .form-input, .form-textarea { width: 100%; padding: var(--space-3); border: none; border-bottom: 2px solid var(--neutral-300); border-radius: var(--radius-md); font-family: inherit; background: var(--neutral-100); transition: border-color var(--transition-base); }
    .form-input:focus, .form-textarea:focus { outline: none; border-color: var(--accent); background: var(--white); }
    .form-textarea { min-height: 120px; resize: vertical; }
    .form-submit { width: 100%; padding: var(--space-3); background: var(--accent); color: var(--primary); border: none; border-radius: var(--radius-md); font-weight: 700; cursor: pointer; transition: all var(--transition-base); }
    .form-submit:hover { background: var(--accent-hover); transform: translateY(-2px); }

    /* Alert */
    .alert { padding: var(--space-3); border-radius: var(--radius-md); margin-bottom: var(--space-4); display: flex; align-items: center; gap: var(--space-2); }
    .alert-success { background: #D4EDDA; border: 1px solid #C3E6CB; color: #155724; }
    .alert-error { background: #F8D7DA; border: 1px solid #F5C6CB; color: #721C24; }

    /* Footer */
    .footer { background: var(--neutral-900); color: var(--white); padding: 4rem 0 2rem; }
    .footer-grid { display: grid; grid-template-columns: 1fr; gap: 2.5rem; max-width: 1320px; margin: 0 auto; padding: 0 2rem; }
    @media (min-width: 640px) { .footer-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (min-width: 992px) { .footer-grid { grid-template-columns: 1.6fr 1fr 1fr 1.3fr; gap: 3rem; } }
    .footer-brand { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; }
    .footer-brand img { width: 44px; height: 44px; }
    .footer-col h4 { font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; color: var(--accent); }
    .footer-col p { color: var(--neutral-400); font-size: 0.88rem; line-height: 1.65; margin: 0; }
    .footer-links { list-style: none; padding: 0; margin: 0; }
    .footer-links li { margin-bottom: 0.55rem; }
    .footer-links a { color: var(--neutral-400); text-decoration: none; font-size: 0.88rem; transition: color 0.2s ease; }
    .footer-links a:hover { color: var(--accent); }
    .footer-social { display: flex; gap: 0.85rem; margin-bottom: 0.85rem; flex-wrap: wrap; }
    .footer-social a { color: var(--neutral-400); text-decoration: none; font-size: 0.88rem; display: inline-flex; align-items: center; gap: 0.45rem; transition: color 0.2s ease; }
    .footer-social a:hover { color: var(--accent); }
    .footer-bottom { text-align: center; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1.5rem; margin-top: 2.5rem; color: var(--neutral-500); font-size: 0.85rem; }

    /* Modal */
    .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); align-items: center; justify-content: center; z-index: 2000; padding: var(--space-4); }
    .modal.active { display: flex; }
    .modal-content { background: var(--white); max-width: 700px; width: 100%; max-height: 90vh; overflow-y: auto; border-radius: var(--radius-xl); padding: var(--space-6); position: relative; margin: 0 auto; }
    .modal-close { position: absolute; top: var(--space-4); right: var(--space-4); background: none; border: none; font-size: 1.5rem; color: var(--neutral-500); cursor: pointer; }
    .modal-title { font-size: 1.5rem; font-weight: 800; color: var(--primary); text-align: center; margin-bottom: var(--space-6); }

    /* Step Indicator */
    .step-indicator { display: flex; justify-content: center; align-items: center; gap: var(--space-4); margin-bottom: var(--space-6); }
    .step-indicator-item { display: flex; align-items: center; gap: var(--space-2); color: var(--neutral-500); font-weight: 500; }
    .step-indicator-item.active { color: var(--primary); font-weight: 700; }
    .step-indicator-item.completed { color: #22C55E; }
    .step-indicator-number { width: 40px; height: 40px; border-radius: var(--radius-full); border: 2px solid var(--neutral-300); display: flex; align-items: center; justify-content: center; font-weight: 700; }
    .step-indicator-item.active .step-indicator-number { border-color: var(--primary); background: var(--primary); color: var(--white); }
    .step-indicator-item.completed .step-indicator-number { border-color: #22C55E; background: #22C55E; color: var(--white); }
    .step-indicator-line { width: 60px; height: 2px; background: var(--neutral-300); }
    .step-indicator-item.completed + .step-indicator-line { background: #22C55E; }

    /* Verification Inputs */
    .verification-inputs { display: flex; gap: var(--space-2); justify-content: center; margin: var(--space-4) 0; }
    .verification-inputs input { width: 50px; height: 50px; text-align: center; font-size: 1.5rem; font-weight: 700; border: 2px solid var(--neutral-300); border-radius: var(--radius-md); background: var(--neutral-100); }
    .verification-inputs input:focus { outline: none; border-color: var(--primary); background: var(--white); }

    /* Spinner */
    .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid var(--neutral-300); border-top-color: var(--primary); border-radius: 50%; animation: spin 0.6s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Mobile Menu Specific Styles */
    .mobile-cta-btn {
        display: block;
        width: 100%;
        padding: 1rem;
        background: var(--accent);
        color: var(--primary);
        text-decoration: none;
        text-align: center;
        border-radius: var(--radius-md);
        font-weight: 600;
        margin-top: 1rem;
        transition: all var(--transition-base);
    }
    .mobile-cta-btn:hover {
        background: var(--accent-hover);
        transform: translateY(-2px);
    }
    
    .mobile-submenu {
        display: none;
        padding-left: 1rem;
        margin-top: 0.5rem;
    }
    
    .mobile-submenu li {
        margin-bottom: 0.5rem;
    }
    
    .mobile-submenu a {
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.9rem;
        padding: 0.5rem 0;
        display: block;
    }
    
    .mobile-submenu a:hover {
        color: var(--accent);
    }

    /* Mobile Responsiveness */
    @media (max-width: 575.98px) {
        .hero { padding: 80px 1rem 3rem; }
        .hero-tagline { font-size: 1.25rem; }
        .hero-title { font-size: 2rem; word-spacing: 0.5rem; }
        .hero-subtitle { font-size: 1rem; }
        .hero-buttons { flex-direction: column; width: 100%; gap: 1rem; }
        .hero-buttons .btn { width: 100%; justify-content: center; }
        .schools-grid { gap: 1rem; }
        .schools-grid img { width: 60px; height: 60px; }
        .cards-grid { gap: 1.5rem; }
        .steps-grid { gap: 1.5rem; }
        .step-card { padding: 2rem 1.5rem; }
        .step-number { width: 50px; height: 50px; font-size: 1.5rem; }
        .verification-inputs { gap: 0.5rem; }
        .verification-inputs input { width: 35px; height: 40px; font-size: 1.1rem; }
        .modal-content { margin: 1rem; max-width: calc(100% - 2rem); }
        .footer-grid { gap: 2rem; }
        
        /* Sidebar Mobile */
        .sidebar-toggle {
            display: block;
        }
        .sidebar {
            transform: translateX(-100%);
        }
        .sidebar.active {
            transform: translateX(0);
        }
        .main-content {
            margin-left: 0;
        }
        
        /* Forms Mobile */
        .contact-container { grid-template-columns: 1fr; }
        .form-input, .form-textarea { font-size: 16px; } /* Prevent zoom on iOS */
        
        /* Header Mobile */
        .header-container {
            padding: 1rem;
        }
        .logo-img {
            width: 40px;
            height: 40px;
        }
        .logo span {
            font-size: 1rem;
        }
    }
    
    @media (min-width: 576px) and (max-width: 767.98px) {
        .hero { padding: 90px 1.5rem 4rem; }
        .hero-tagline { font-size: 1.5rem; }
        .hero-title { font-size: 2.5rem; }
        .schools-grid img { width: 70px; height: 70px; }
        .verification-inputs input { width: 40px; height: 45px; font-size: 1.25rem; }
    }
    
    @media (min-width: 768px) and (max-width: 991.98px) {
        .hero { padding: 100px 2rem 5rem; }
        .hero-tagline { font-size: 1.75rem; }
        .hero-title { font-size: 2.75rem; }
        .schools-grid img { width: 75px; height: 75px; }
        .dropdown-menu { min-width: 220px; }
    }
    
    @media (min-width: 992px) and (max-width: 1199.98px) {
        .hero { padding: 110px 2rem 6rem; }
        .hero-tagline { font-size: 2rem; }
        .hero-title { font-size: 3rem; }
        .schools-grid img { width: 80px; height: 80px; }
    }
    
    @media (min-width: 1200px) {
        .hero { padding: 140px 2rem 6rem; }
        .hero-tagline { font-size: 2.5rem; }
        .hero-title { font-size: 3.5rem; }
        .schools-grid img { width: 85px; height: 85px; }
    }
</style>
