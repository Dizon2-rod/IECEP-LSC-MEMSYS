<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HEI Portal - IECEP-LSC MEMSYS</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/portal.css">
    <link rel="stylesheet" href="/css/responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="portal-layout">
        <aside class="portal-sidebar" id="sidebar">
            <div class="portal-sidebar-header">
                <h2><span style="color:#F5A623">IECEP</span>-LSC</h2>
                <p>HEI Officer Portal</p>
            </div>
            <nav class="portal-sidebar-nav">
                <div class="portal-sidebar-section">Main</div>
                <a href="#" class="active" onclick="showTab('dashboard')"><span class="nav-icon">?</span> Dashboard</a>
                <a href="#" onclick="showTab('members')"><span class="nav-icon">?</span> Members</a>
                <a href="#" onclick="showTab('upload')"><span class="nav-icon">?</span> Upload Members</a>
                <a href="#" onclick="showTab('compliance')"><span class="nav-icon">?</span> Compliance</a>
                <div class="portal-sidebar-section">Account</div>
                <a href="/portal/member/" ><span class="nav-icon">?</span> Member Portal</a>
                <a href="#" onclick="Auth.logout()"><span class="nav-icon">?</span> Logout</a>
            </nav>
        </aside>

        <main class="portal-main">
            <header class="portal-topbar">
                <div class="portal-topbar-left">
                    <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('show')">?</button>
                    <h1 id="page-title">Dashboard</h1>
                </div>
                <div class="portal-topbar-right">
                    <div id="role-switcher"></div>
                    <div class="user-menu">
                        <div class="user-avatar">O</div>
                        <div class="user-info">
                            <div class="name" id="user-name">Officer</div>
                            <div class="role" id="user-role">School Officer</div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="portal-content">
                <div id="tab-dashboard" class="tab-content active">
                    <div class="stats-grid">
                        <div class="stat-card"><div class="stat-icon primary">?</div><div class="stat-info"><h4 id="institution-name">-</h4><p>Institution</p></div></div>
                        <div class="stat-card"><div class="stat-icon primary">?</div><div class="stat-info"><h4 id="stat-total">0</h4><p>Total Members</p></div></div>
                        <div class="stat-card"><div class="stat-icon success">?</div><div class="stat-info"><h4 id="stat-paid">0</h4><p>Paid Members</p></div></div>
                        <div class="stat-card"><div class="stat-icon warning">?</div><div class="stat-info"><h4 id="stat-unpaid">0</h4><p>Unpaid</p></div></div>
                    </div>
                    <div class="card">
                        <h3>Compliance Status</h3>
                        <p id="compliance-status" class="mt-1">Loading...</p>
                    </div>
                </div>

                <div id="tab-members" class="tab-content">
                    <div id="members-list"><div class="spinner"></div></div>
                </div>

                <div id="tab-upload" class="tab-content">
                    <div class="card">
                        <h3>Upload Member List</h3>
                        <p class="text-muted mb-2">Download the CSV template, fill in your members, and upload it below.</p>
                        <button class="btn btn-outline mb-2" onclick="OfficerPortal.downloadTemplate()">Download CSV Template</button>
                        <form onsubmit="OfficerPortal.uploadMembers(event)">
                            <div class="form-group">
                                <label>CSV File</label>
                                <input type="file" class="form-control" accept=".csv" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Upload</button>
                        </form>
                    </div>
                </div>

                <div id="tab-compliance" class="tab-content">
                    <div class="card">
                        <h3>Institution Compliance</h3>
                        <p id="compliance-detail" class="mt-2">Loading...</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <nav class="bottom-nav">
        <div class="bottom-nav-items">
            <a href="#" class="bottom-nav-item active" onclick="showTab('dashboard')"><span class="icon">?</span>Dashboard</a>
            <a href="#" class="bottom-nav-item" onclick="showTab('members')"><span class="icon">?</span>Members</a>
            <a href="#" class="bottom-nav-item" onclick="showTab('upload')"><span class="icon">?</span>Upload</a>
            <a href="#" class="bottom-nav-item" onclick="showTab('compliance')"><span class="icon">?</span>Compliance</a>
        </div>
    </nav>

    <script src="/js/app.js"></script>
    <script src="/js/auth.js"></script>
    <script src="/js/context-switcher.js"></script>
    <script src="/js/portal/officer.js"></script>
    <script>
        function showTab(tab) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.getElementById('tab-' + tab)?.classList.add('active');
            const titles = { dashboard: 'Dashboard', members: 'Members', upload: 'Upload Members', compliance: 'Compliance' };
            document.getElementById('page-title').textContent = titles[tab] || tab;
            if (tab === 'members') OfficerPortal.loadMembers();
            return false;
        }
        OfficerPortal.init();
    </script>
</body>
</html>
