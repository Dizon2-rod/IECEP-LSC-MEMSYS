<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Treasurer Portal - IECEP-LSC MEMSYS</title>
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
                <p>Treasurer Portal</p>
            </div>
            <nav class="portal-sidebar-nav">
                <div class="portal-sidebar-section">Finance</div>
                <a href="#" class="active" onclick="showTab('pending')"><span class="nav-icon">?</span> Pending Payments</a>
                <a href="#" onclick="showTab('transactions')"><span class="nav-icon">?</span> Transactions</a>
                <a href="#" onclick="showTab('report')"><span class="nav-icon">?</span> Financial Report</a>
                <div class="portal-sidebar-section">Account</div>
                <a href="/portal/member/"><span class="nav-icon">?</span> Member Portal</a>
                <a href="#" onclick="Auth.logout()"><span class="nav-icon">?</span> Logout</a>
            </nav>
        </aside>

        <main class="portal-main">
            <header class="portal-topbar">
                <div class="portal-topbar-left">
                    <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('show')">?</button>
                    <h1 id="page-title">Pending Payments</h1>
                </div>
                <div class="portal-topbar-right">
                    <div id="role-switcher"></div>
                    <div class="user-menu">
                        <div class="user-avatar">T</div>
                        <div class="user-info">
                            <div class="name" id="user-name">Treasurer</div>
                            <div class="role" id="user-role">Treasurer</div>
                        </div>
                    </div>
                </div>
            </header>

            <div class="portal-content">
                <div id="tab-pending" class="tab-content active">
                    <div id="pending-payments"><div class="spinner"></div></div>
                </div>
                <div id="tab-transactions" class="tab-content">
                    <div id="transactions-list"><div class="spinner"></div></div>
                </div>
                <div id="tab-report" class="tab-content">
                    <div class="card">
                        <h3>Generate Financial Report</h3>
                        <p class="text-muted mb-2">Download a PDF report of all transactions.</p>
                        <button class="btn btn-primary" onclick="TreasurerPortal.generateReport()">Generate Report</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="/js/app.js"></script>
    <script src="/js/auth.js"></script>
    <script src="/js/context-switcher.js"></script>
    <script src="/js/portal/treasurer.js"></script>
    <script>
        function showTab(tab) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.getElementById('tab-' + tab)?.classList.add('active');
            const titles = { pending: 'Pending Payments', transactions: 'Transactions', report: 'Financial Report' };
            document.getElementById('page-title').textContent = titles[tab] || tab;
            if (tab === 'transactions') TreasurerPortal.loadTransactions();
            return false;
        }
        TreasurerPortal.init();
    </script>
</body>
</html>
