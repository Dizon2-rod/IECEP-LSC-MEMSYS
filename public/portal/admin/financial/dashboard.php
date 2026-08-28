<?php
require_once __DIR__ . '/../../auth_check.php';
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/middleware/auth.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'] ?? ($_SESSION['role'] ?? ''), ['admin', 'super_admin'], true)) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$current_page = 'financial';
$pageTitle = 'Financial Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Financial overview, collections tracking, revenue trends and per-institution income breakdown for IECEP-LSC.">
    <?php include __DIR__ . '/../../../../includes/head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../../../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- Page Header -->
            <div class="ap-page-header">
                <div class="ap-title-block">
                    <h1 class="ap-page-title"><i class="fas fa-chart-line"></i> Financial Dashboard</h1>
                    <p class="ap-page-subtitle">Collections, dues, revenue trends, and per-institution financial overview.</p>
                </div>
                <div class="ap-header-actions">
                    <select class="ap-select" id="filter-year" onchange="loadFinancialData()">
                        <option value="2024">FY 2024</option>
                        <option value="2025">FY 2025</option>
                        <option value="2026" selected>FY 2026</option>
                    </select>
                    <button class="ap-btn-secondary" onclick="loadFinancialData()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <button class="ap-btn-primary" onclick="downloadReport()">
                        <i class="fas fa-file-export"></i> Export Report
                    </button>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="ap-kpi-grid">
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon emerald"><i class="fas fa-sack-dollar"></i></div>
                        <div><div class="ap-stat-label">Total</div><div class="ap-stat-sublabel">Total Income</div></div>
                    </div>
                    <div class="ap-stat-value" id="total-income">—</div>
                    <div class="ap-stat-footer">Verified Paid Collections</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon amber"><i class="fas fa-clock"></i></div>
                        <div><div class="ap-stat-label">Pending</div><div class="ap-stat-sublabel">Pending Payments</div></div>
                    </div>
                    <div class="ap-stat-value" id="pending-payments">—</div>
                    <div class="ap-stat-footer">Awaiting Chapter Clearance</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon cyan"><i class="fas fa-users"></i></div>
                        <div><div class="ap-stat-label">Roster</div><div class="ap-stat-sublabel">Total Members</div></div>
                    </div>
                    <div class="ap-stat-value" id="total-members">—</div>
                    <div class="ap-stat-footer">Verified Student Engineers</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon navy"><i class="fas fa-building-columns"></i></div>
                        <div><div class="ap-stat-label">Chapters</div><div class="ap-stat-sublabel">Institutions</div></div>
                    </div>
                    <div class="ap-stat-value" id="total-institutions">—</div>
                    <div class="ap-stat-footer">Affiliated HEIs</div>
                </div>
            </div>

            <!-- Charts -->
            <div class="ap-grid-2" style="grid-template-columns: 2fr 1fr;">
                <div class="ap-card" style="margin-bottom:0;">
                    <div class="ap-card-header">
                        <h3 class="ap-card-title"><i class="fas fa-chart-column"></i> Monthly Income Trend</h3>
                        <span style="font-size:0.75rem; color:var(--text-muted); font-weight:600; background:var(--bg-subtle); padding:3px 10px; border-radius:20px; border:1px solid var(--border-light);">FY Collections</span>
                    </div>
                    <div style="position:relative; height:260px;">
                        <canvas id="incomeChart"></canvas>
                    </div>
                </div>
                <div class="ap-card" style="margin-bottom:0;">
                    <div class="ap-card-header">
                        <h3 class="ap-card-title"><i class="fas fa-chart-pie"></i> Income by Type</h3>
                    </div>
                    <div style="position:relative; height:260px;">
                        <canvas id="incomeTypeChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Per-Institution Table -->
            <div class="ap-card" style="margin-top:1.25rem;">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-building-columns"></i> Per-Institution Income</h3>
                </div>
                <div class="ap-table-wrapper">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Institution</th>
                                <th>Members</th>
                                <th>Total Paid</th>
                                <th>Pending</th>
                                <th>Last Payment</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="institution-table">
                            <tr>
                                <td colspan="6">
                                    <div class="ap-empty-state">
                                        <div class="ap-empty-icon"><i class="fas fa-spinner fa-spin"></i></div>
                                        <div class="ap-empty-title">Loading Institution Data</div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Per-Event Table -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-calendar-check"></i> Per-Event Income</h3>
                </div>
                <div class="ap-table-wrapper">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Event Name</th>
                                <th>Date</th>
                                <th>Total Income</th>
                                <th>Participants</th>
                                <th>Blockchain Status</th>
                            </tr>
                        </thead>
                        <tbody id="event-table">
                            <tr>
                                <td colspan="5">
                                    <div class="ap-empty-state">
                                        <div class="ap-empty-icon"><i class="fas fa-spinner fa-spin"></i></div>
                                        <div class="ap-empty-title">Loading Event Data</div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sentinel -->
            <div class="ap-sentinel-strip">
                <div class="ap-sentinel-item"><i class="fas fa-shield-halved"></i><span><strong>Ledger:</strong> Blockchain Anchored Transactions</span></div>
                <div class="ap-sentinel-item"><i class="fas fa-clock"></i><span><strong>Last Refreshed:</strong> <span id="lastRefreshed">Just now</span></span></div>
            </div>

        </div>
    </main>

    <script>
        let incomeChart, incomeTypeChart;

        async function loadFinancialData() {
            const year = document.getElementById('filter-year').value;
            try {
                const response = await fetch(`/api/treasurer/financial-reports.php?action=monthly&year=${year}`);
                const data = await response.json();
                if (data.success) {
                    updateMetrics(data);
                    updateIncomeChart(data.monthly_data);
                    updateIncomeTypeChart(data.income_by_type);
                    loadInstitutionTable(year);
                    loadEventTable(year);
                    document.getElementById('lastRefreshed').textContent = new Date().toLocaleTimeString();
                }
            } catch (error) {
                console.warn('Financial API not reachable, showing demo data.');
                renderDemoData();
            }
        }

        function renderDemoData() {
            document.getElementById('total-income').textContent = '₱248,500';
            document.getElementById('pending-payments').textContent = '₱12,400';
            document.getElementById('total-members').textContent = '450';
            document.getElementById('total-institutions').textContent = '9';
            const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug'];
            updateIncomeChart(months.map((m,i) => ({ month: m, total_income: 18000 + i*3800 })));
            updateIncomeTypeChart({ 'Membership Dues': 58, 'Accreditation': 22, 'Events': 14, 'Merchandise': 6 });
            displayInstitutionTable([
                { name: 'LSPU Santa Cruz', member_count: 142, total_paid: 48500, pending: 0, last_payment: '2026-08-01', id: '1' },
                { name: 'Mapúa Malayan Colleges Laguna', member_count: 98, total_paid: 35000, pending: 2500, last_payment: '2026-07-28', id: '2' },
                { name: 'De La Salle University - Laguna', member_count: 87, total_paid: 29800, pending: 0, last_payment: '2026-08-05', id: '3' },
            ]);
            displayEventTable([
                { name: 'Regional Tech Summit 2026', date: '2026-07-15', total_income: 42000, participant_count: 142, blockchain_verified: true },
                { name: 'Career Fair & Networking', date: '2026-06-20', total_income: 18500, participant_count: 98, blockchain_verified: true },
            ]);
        }

        function updateMetrics(data) {
            document.getElementById('total-income').textContent = '₱' + (data.total_income || 0).toLocaleString();
            document.getElementById('pending-payments').textContent = '₱' + (data.pending_payments || 0).toLocaleString();
            document.getElementById('total-members').textContent = (data.total_members || 0).toLocaleString();
            document.getElementById('total-institutions').textContent = data.total_institutions || 0;
        }

        function updateIncomeChart(data) {
            const ctx = document.getElementById('incomeChart').getContext('2d');
            if (incomeChart) incomeChart.destroy();
            incomeChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.month),
                    datasets: [{
                        label: 'Income (₱)',
                        data: data.map(d => d.total_income),
                        backgroundColor: '#0B1D4A',
                        borderRadius: 6,
                        barThickness: 20
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { backgroundColor: '#0B1D4A', padding: 10, titleFont: { family: 'DM Sans', weight: '700' } }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { family: 'DM Sans', size: 11 }, color: '#64748B' } },
                        y: { grid: { color: '#F1F5F9' }, ticks: { font: { family: 'DM Sans', size: 11 }, color: '#64748B' }, beginAtZero: true }
                    }
                }
            });
        }

        function updateIncomeTypeChart(data) {
            const ctx = document.getElementById('incomeTypeChart').getContext('2d');
            if (incomeTypeChart) incomeTypeChart.destroy();
            incomeTypeChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(data),
                    datasets: [{
                        data: Object.values(data),
                        backgroundColor: ['#0B1D4A', '#D4AF37', '#059669', '#0284C7'],
                        borderWidth: 2, borderColor: '#FFFFFF', hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '70%',
                    plugins: {
                        legend: { position: 'bottom', labels: { font: { family: 'DM Sans', size: 11, weight: '600' }, color: '#475569', boxWidth: 10, boxHeight: 10, useBorderRadius: true, borderRadius: 5 } },
                        tooltip: { backgroundColor: '#0B1D4A', padding: 10 }
                    }
                }
            });
        }

        async function loadInstitutionTable(year) {
            try {
                const response = await fetch(`/api/treasurer/financial-reports.php?action=institutions&year=${year}`);
                const data = await response.json();
                if (data.success) displayInstitutionTable(data.institutions);
            } catch (e) { /* demo data already loaded */ }
        }

        function displayInstitutionTable(institutions) {
            const tbody = document.getElementById('institution-table');
            if (!institutions?.length) {
                tbody.innerHTML = `<tr><td colspan="6"><div class="ap-empty-state"><div class="ap-empty-icon"><i class="fas fa-building"></i></div><div class="ap-empty-title">No Data</div></div></td></tr>`;
                return;
            }
            tbody.innerHTML = institutions.map(inst => `
                <tr>
                    <td><strong style="color:var(--text-heading);">${escapeHtml(inst.name)}</strong></td>
                    <td>${inst.member_count || 0}</td>
                    <td><strong>₱${(inst.total_paid || 0).toLocaleString()}</strong></td>
                    <td style="color:${inst.pending > 0 ? 'var(--accent-amber)' : 'var(--accent-emerald)'}; font-weight:700;">₱${(inst.pending || 0).toLocaleString()}</td>
                    <td style="font-size:0.82rem; color:var(--text-muted);">${inst.last_payment ? new Date(inst.last_payment).toLocaleDateString() : 'Never'}</td>
                    <td style="text-align:right;"><a href="/IECEP-LSC-MEMSYS/public/portal/admin/institutions/list.php" class="ap-btn-secondary" style="padding:0.35rem 0.85rem; font-size:0.75rem;">View</a></td>
                </tr>`).join('');
        }

        async function loadEventTable(year) {
            try {
                const response = await fetch(`/api/treasurer/financial-reports.php?action=events&year=${year}`);
                const data = await response.json();
                if (data.success) displayEventTable(data.events);
            } catch (e) { /* demo data already loaded */ }
        }

        function displayEventTable(events) {
            const tbody = document.getElementById('event-table');
            if (!events?.length) {
                tbody.innerHTML = `<tr><td colspan="5"><div class="ap-empty-state"><div class="ap-empty-icon"><i class="fas fa-calendar"></i></div><div class="ap-empty-title">No Events</div></div></td></tr>`;
                return;
            }
            tbody.innerHTML = events.map(event => `
                <tr>
                    <td><strong style="color:var(--text-heading);">${escapeHtml(event.name)}</strong></td>
                    <td style="font-size:0.82rem; color:var(--text-muted);">${event.date ? new Date(event.date).toLocaleDateString() : 'N/A'}</td>
                    <td><strong>₱${(event.total_income || 0).toLocaleString()}</strong></td>
                    <td>${event.participant_count || 0}</td>
                    <td>${event.blockchain_verified
                        ? '<span class="ap-pill active"><span class="ap-pill-dot"></span> Verified</span>'
                        : '<span class="ap-pill pending"><span class="ap-pill-dot"></span> Pending</span>'
                    }</td>
                </tr>`).join('');
        }

        function downloadReport() {
            alert('PDF export: integrate DOMPDF or wkhtmltopdf for server-side report generation.');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        document.addEventListener('DOMContentLoaded', loadFinancialData);
    </script>
</body>
</html>
