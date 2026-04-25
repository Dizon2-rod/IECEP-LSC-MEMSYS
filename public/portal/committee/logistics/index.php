<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logistics Committee - IECEP-LSC MEMSYS</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/portal.css">
    <link rel="stylesheet" href="/css/responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="portal-layout">
        <aside class="portal-sidebar" id="sidebar">
            <div class="portal-sidebar-header"><h2><span style="color:#F5A623">IECEP</span>-LSC</h2><p>Logistics Committee</p></div>
            <nav class="portal-sidebar-nav">
                <a href="#" class="active" onclick="showTab('tasks')"><span class="nav-icon">?</span> Tasks</a>
                <a href="#" onclick="showTab('assets')"><span class="nav-icon">?</span> Assets</a>
                <a href="/portal/member/"><span class="nav-icon">?</span> Member Portal</a>
                <a href="#" onclick="Auth.logout()"><span class="nav-icon">?</span> Logout</a>
            </nav>
        </aside>
        <main class="portal-main">
            <header class="portal-topbar">
                <div class="portal-topbar-left"><button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('show')">?</button><h1 id="page-title">Tasks</h1></div>
                <div class="portal-topbar-right"><div id="role-switcher"></div><div class="user-menu"><div class="user-avatar">L</div><div class="user-info"><div class="name" id="user-name">Logistics</div><div class="role" id="user-role">Logistics Committee</div></div></div></div>
            </header>
            <div class="portal-content">
                <div id="tab-tasks" class="tab-content active">
                    <div class="card mb-3"><h3>Create Task</h3><form onsubmit="CommitteePortal.createTask(event)"><div class="form-group"><label>Title</label><input type="text" class="form-control" name="title" required></div><div class="form-group"><label>Description</label><textarea class="form-control" name="description"></textarea></div><button type="submit" class="btn btn-primary">Create</button></form></div>
                    <div id="committee-tasks"></div>
                </div>
                <div id="tab-assets" class="tab-content"><div class="card"><h3>Upload Asset</h3><form onsubmit="CommitteePortal.uploadAsset(event)"><div class="form-group"><label>File</label><input type="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp"></div><button type="submit" class="btn btn-primary">Upload</button></form></div></div>
            </div>
        </main>
    </div>
    <script src="/js/app.js"></script><script src="/js/auth.js"></script><script src="/js/context-switcher.js"></script><script src="/js/portal/committee.js"></script>
    <script>function showTab(t){document.querySelectorAll('.tab-content').forEach(e=>e.classList.remove('active'));document.getElementById('tab-'+t)?.classList.add('active');if(t==='tasks')CommitteePortal.loadTasks();return false;}CommitteePortal.init();</script>
</body>
</html>
