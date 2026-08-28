<?php
require_once __DIR__ . '/../../auth_check.php';
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/middleware/auth.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'] ?? ($_SESSION['role'] ?? ''), ['admin', 'super_admin'], true)) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$current_page = 'transparency';
$pageTitle = 'Financial Transparency';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Public and internal financial transparency registry backed by blockchain cryptographic verification.">
    <?php include __DIR__ . '/../../../../includes/head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../../../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- Page Header -->
            <div class="ap-page-header">
                <div class="ap-title-block">
                    <h1 class="ap-page-title"><i class="fas fa-eye"></i> Financial Transparency Audit</h1>
                    <p class="ap-page-subtitle">Public-facing fund allocations, expenditure accountability, and cryptographic ledger verification records.</p>
                </div>
                <div class="ap-header-actions">
                    <a href="/IECEP-LSC-MEMSYS/public/portal/admin/financial/dashboard.php" class="ap-btn-secondary">
                        <i class="fas fa-chart-pie"></i> Treasury Dashboard
                    </a>
                    <a href="/IECEP-LSC-MEMSYS/public/transparency.php" target="_blank" class="ap-btn-primary">
                        <i class="fas fa-arrow-up-right-from-square"></i> Open Public Transparency Page
                    </a>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="ap-kpi-grid">
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon emerald"><i class="fas fa-sack-dollar"></i></div>
                        <div><div class="ap-stat-label">Inflow</div><div class="ap-stat-sublabel">Total Funds Collected</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--accent-emerald);">₱390,700</div>
                    <div class="ap-stat-footer">FY 2026–2027 audited income</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon rose"><i class="fas fa-money-bill-transfer"></i></div>
                        <div><div class="ap-stat-label">Outflow</div><div class="ap-stat-sublabel">Total Expenditures</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--accent-rose);">₱142,200</div>
                    <div class="ap-stat-footer">Audited event and admin costs</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon navy"><i class="fas fa-wallet"></i></div>
                        <div><div class="ap-stat-label">Treasury</div><div class="ap-stat-sublabel">Net Reserve Balance</div></div>
                    </div>
                    <div class="ap-stat-value">₱248,500</div>
                    <div class="ap-stat-footer">Liquid chapter funds</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon gold"><i class="fas fa-link"></i></div>
                        <div><div class="ap-stat-label">Audit</div><div class="ap-stat-sublabel">Blockchain Status</div></div>
                    </div>
                    <div class="ap-stat-value" style="font-size:1.4rem;">
                        <span class="ap-pill active"><span class="ap-pill-dot"></span> 100% Verified</span>
                    </div>
                    <div class="ap-stat-footer">SHA-256 Ledger Anchor</div>
                </div>
            </div>

            <!-- Transparency Overview Table -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-table-list"></i> Monthly Treasury Cash Flow Summary</h3>
                </div>
                <div class="ap-table-wrapper">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Funds Collected</th>
                                <th>Expenditures</th>
                                <th>Net Balance</th>
                                <th>Ledger Verification</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>January 2026</strong></td>
                                <td>₱57,900</td>
                                <td>₱18,200</td>
                                <td><strong style="color:var(--accent-emerald);">+₱39,700</strong></td>
                                <td><span class="ap-pill active"><span class="ap-pill-dot"></span> Verified</span></td>
                            </tr>
                            <tr>
                                <td><strong>February 2026</strong></td>
                                <td>₱53,600</td>
                                <td>₱21,400</td>
                                <td><strong style="color:var(--accent-emerald);">+₱32,200</strong></td>
                                <td><span class="ap-pill active"><span class="ap-pill-dot"></span> Verified</span></td>
                            </tr>
                            <tr>
                                <td><strong>March 2026</strong></td>
                                <td>₱45,800</td>
                                <td>₱14,500</td>
                                <td><strong style="color:var(--accent-emerald);">+₱31,300</strong></td>
                                <td><span class="ap-pill active"><span class="ap-pill-dot"></span> Verified</span></td>
                            </tr>
                            <tr>
                                <td><strong>April 2026</strong></td>
                                <td>₱36,650</td>
                                <td>₱12,000</td>
                                <td><strong style="color:var(--accent-emerald);">+₱24,650</strong></td>
                                <td><span class="ap-pill active"><span class="ap-pill-dot"></span> Verified</span></td>
                            </tr>
                            <tr>
                                <td><strong>May 2026</strong></td>
                                <td>₱44,700</td>
                                <td>₱28,900</td>
                                <td><strong style="color:var(--accent-emerald);">+₱15,800</strong></td>
                                <td><span class="ap-pill active"><span class="ap-pill-dot"></span> Verified</span></td>
                            </tr>
                            <tr>
                                <td><strong>June 2026</strong></td>
                                <td>₱31,650</td>
                                <td>₱9,400</td>
                                <td><strong style="color:var(--accent-emerald);">+₱22,250</strong></td>
                                <td><span class="ap-pill active"><span class="ap-pill-dot"></span> Verified</span></td>
                            </tr>
                            <tr>
                                <td><strong>July 2026</strong></td>
                                <td>₱56,300</td>
                                <td>₱22,800</td>
                                <td><strong style="color:var(--accent-emerald);">+₱33,500</strong></td>
                                <td><span class="ap-pill active"><span class="ap-pill-dot"></span> Verified</span></td>
                            </tr>
                            <tr>
                                <td><strong>August 2026</strong></td>
                                <td>₱64,100</td>
                                <td>₱15,000</td>
                                <td><strong style="color:var(--accent-emerald);">+₱49,100</strong></td>
                                <td><span class="ap-pill active"><span class="ap-pill-dot"></span> Verified</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Charts Grid -->
            <div class="ap-grid-2">
                <div class="ap-card" style="margin-bottom:0;">
                    <div class="ap-card-header">
                        <h3 class="ap-card-title"><i class="fas fa-chart-pie"></i> Expenditure Categories</h3>
                    </div>
                    <div style="position:relative; height:240px;">
                        <canvas id="expenditureChart"></canvas>
                    </div>
                </div>
                <div class="ap-card" style="margin-bottom:0;">
                    <div class="ap-card-header">
                        <h3 class="ap-card-title"><i class="fas fa-chart-column"></i> Revenue vs Outflow</h3>
                    </div>
                    <div style="position:relative; height:240px;">
                        <canvas id="revenueOutflowChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Sentinel -->
            <div class="ap-sentinel-strip">
                <div class="ap-sentinel-item"><i class="fas fa-hand-holding-dollar"></i><span><strong>Open Ledger Standard:</strong> Public Transparency Compliance</span></div>
                <div class="ap-sentinel-item"><i class="fas fa-shield-halved"></i><span><strong>Cryptographic Anchor:</strong> SHA-256 Proof of Reserve</span></div>
            </div>

        </div>
    </main>

    <script>
        function initTransparencyCharts() {
            const ctx1 = document.getElementById('expenditureChart').getContext('2d');
            new Chart(ctx1, {
                type: 'doughnut',
                data: {
                    labels: ['Event Logistics', 'Certificates & Awards', 'Regional Assembly', 'Operational Supplies'],
                    datasets: [{
                        data: [45, 20, 25, 10],
                        backgroundColor: ['#0B1D4A', '#D4AF37', '#0284C7', '#7C3AED'],
                        borderWidth: 2,
                        borderColor: '#FFFFFF'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });

            const ctx2 = document.getElementById('revenueOutflowChart').getContext('2d');
            new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: ['Q1 2026', 'Q2 2026', 'Q3 2026 (YTD)'],
                    datasets: [
                        { label: 'Funds Collected (₱)', data: [157300, 113000, 120400], backgroundColor: '#059669', borderRadius: 4 },
                        { label: 'Expenditures (₱)', data: [54100, 50300, 37800], backgroundColor: '#E11D48', borderRadius: 4 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, grid: { color: '#F1F5F9' } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        document.addEventListener('DOMContentLoaded', initTransparencyCharts);
    </script>
</body>
</html>
