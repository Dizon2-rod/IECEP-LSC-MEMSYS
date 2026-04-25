<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PRO Portal - IECEP-LSC MEMSYS</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/portal.css">
    <link rel="stylesheet" href="/css/responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="portal-layout">
        <aside class="portal-sidebar" id="sidebar">
            <div class="portal-sidebar-header"><h2><span style="color:#F5A623">IECEP</span>-LSC</h2><p>PRO Portal</p></div>
            <nav class="portal-sidebar-nav">
                <a href="#" class="active" onclick="showTab('publish')"><span class="nav-icon">?</span> Publish Announcement</a>
                <a href="#" onclick="showTab('manage')"><span class="nav-icon">?</span> Manage Announcements</a>
                <a href="/portal/member/"><span class="nav-icon">?</span> Member Portal</a>
                <a href="#" onclick="Auth.logout()"><span class="nav-icon">?</span> Logout</a>
            </nav>
        </aside>
        <main class="portal-main">
            <header class="portal-topbar">
                <div class="portal-topbar-left"><button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('show')">?</button><h1 id="page-title">Publish Announcement</h1></div>
                <div class="portal-topbar-right"><div id="role-switcher"></div><div class="user-menu"><div class="user-avatar">P</div><div class="user-info"><div class="name" id="user-name">PRO</div><div class="role" id="user-role">PRO</div></div></div></div>
            </header>
            <div class="portal-content">
                <div id="tab-publish" class="tab-content active">
                    <div class="card">
                        <h3>Publish Public Announcement</h3>
                        <form onsubmit="ProPortal.createAnnouncement(event)">
                            <div class="form-group"><label>Title</label><input type="text" class="form-control" name="title" required></div>
                            <div class="form-group"><label>Content</label><textarea class="form-control" name="content" rows="4" required></textarea></div>
                            <button type="submit" class="btn btn-primary">Publish</button>
                        </form>
                    </div>
                </div>
                <div id="tab-manage" class="tab-content"><div id="pro-announcements"><div class="spinner"></div></div></div>
            </div>
        </main>
    </div>
    <script src="/js/app.js"></script>
    <script src="/js/auth.js"></script>
    <script src="/js/context-switcher.js"></script>
    <script src="/js/portal/pro.js"></script>
    <script>
        function showTab(tab) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.getElementById('tab-'+tab)?.classList.add('active');
            const titles = { publish:'Publish Announcement', manage:'Manage Announcements' };
            document.getElementById('page-title').textContent = titles[tab] || tab;
            if (tab === 'manage') ProPortal.loadAnnouncements();
            return false;
        }
        ProPortal.init();
    </script>
</body>
</html>
