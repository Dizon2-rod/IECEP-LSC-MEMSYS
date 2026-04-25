<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VP Internal Portal - IECEP-LSC MEMSYS</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/portal.css">
    <link rel="stylesheet" href="/css/responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="portal-layout">
        <aside class="portal-sidebar" id="sidebar">
            <div class="portal-sidebar-header"><h2><span style="color:#F5A623">IECEP</span>-LSC</h2><p>VP Internal Portal</p></div>
            <nav class="portal-sidebar-nav">
                <a href="#" class="active" onclick="showTab('compliance')"><span class="nav-icon">?</span> Compliance</a>
                <a href="/portal/member/"><span class="nav-icon">?</span> Member Portal</a>
                <a href="#" onclick="Auth.logout()"><span class="nav-icon">?</span> Logout</a>
            </nav>
        </aside>
        <main class="portal-main">
            <header class="portal-topbar">
                <div class="portal-topbar-left"><button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('show')">?</button><h1 id="page-title">Compliance</h1></div>
                <div class="portal-topbar-right"><div id="role-switcher"></div><div class="user-menu"><div class="user-avatar">V</div><div class="user-info"><div class="name" id="user-name">VP Internal</div><div class="role" id="user-role">VP Internal</div></div></div></div>
            </header>
            <div class="portal-content">
                <div id="tab-compliance" class="tab-content active">
                    <div class="page-header"><h2>Institution Compliance</h2><button class="btn btn-primary" onclick="VpInternalPortal.recalculate()">Recalculate</button></div>
                    <div id="compliance-table"><div class="spinner"></div></div>
                </div>
            </div>
        </main>
    </div>
    <script src="/js/app.js"></script>
    <script src="/js/auth.js"></script>
    <script src="/js/context-switcher.js"></script>
    <script src="/js/portal/vp-internal.js"></script>
    <script>
        function showTab(tab) { document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active')); document.getElementById('tab-'+tab)?.classList.add('active'); return false; }
        VpInternalPortal.init();
    </script>
</body>
</html>
