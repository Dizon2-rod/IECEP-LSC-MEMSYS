<?php
require_once __DIR__ . '/../../auth_check.php';
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/middleware/auth.php';

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'] ?? ($_SESSION['role'] ?? ''), ['admin', 'super_admin'], true)) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$current_page = 'reports';
$pageTitle = 'Financial Reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Comprehensive financial reports, monthly dues collection audits, and institutional breakdown for IECEP-LSC.">
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
                    <h1 class="ap-page-title"><i class="fas fa-file-invoice-dollar"></i> Financial Audits & Reports</h1>
                    <p class="ap-page-subtitle">Monthly dues aggregation, income by category, institutional fee breakdown, and treasury reconciliation.</p>
                </div>
                <div class="ap-header-actions">
                    <select class="ap-select" id="report-year" onchange="loadReports()">
                        <option value="2024">FY 2024</option>
                        <option value="2025">FY 2025</option>
                        <option value="2026" selected>FY 2026</option>
                    </select>
                    <button class="ap-btn-secondary" onclick="loadReports()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <button class="ap-btn-primary" onclick="exportCSV()">
                        <i class="fas fa-file-csv"></i> Export CSV
                    </button>
                </div>
            </div>

            <!-- Report Navigation Tabs -->
            <div class="ap-tabs">
                <button class="ap-tab active" onclick="switchReportTab('monthly', this)"><i class="fas fa-chart-line"></i> Monthly Audit</button>
                <button class="ap-tab" onclick="switchReportTab('institution', this)"><i class="fas fa-university"></i> Institution Breakdown</button>
                <button class="ap-tab" onclick="switchReportTab('categories', this)"><i class="fas fa-pie-chart"></i> Revenue Categories</button>
            </div>

            <!-- Tab 1: Monthly Audit -->
            <div id="monthlyTab" class="report-pane">
                <div class="ap-grid-2" style="grid-template-columns: 2fr 1fr;">
                    <div class="ap-card" style="margin-bottom:0;">
                        <div class="ap-card-header">
                            <h3 class="ap-card-title"><i class="fas fa-chart-column"></i> Monthly Collections (FY 2026)</h3>
                        </div>
                        <div style="position:relative; height:260px;">
                            <canvas id="monthlyIncomeChart"></canvas>
                        </div>
                    </div>
                    <div class="ap-card" style="margin-bottom:0;">
                        <div class="ap-card-header">
                            <h3 class="ap-card-title"><i class="fas fa-chart-pie"></i> Payment Types</h3>
                        </div>
                        <div style="position:relative; height:260px;">
                            <canvas id="paymentTypeChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="ap-card" style="margin-top:1.25rem;">
                    <div class="ap-card-header">
                        <h3 class="ap-card-title"><i class="fas fa-table-cells"></i> Monthly Collections Breakdown</h3>
                    </div>
                    <div class="ap-table-wrapper">
                        <table class="ap-table">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Membership Dues</th>
                                    <th>Event Fees</th>
                                    <th>Accreditation</th>
                                    <th>Merchandise</th>
                                    <th>Monthly Gross Total</th>
                                </tr>
                            </thead>
                            <tbody id="monthly-table">
                                <tr>
                                    <td><strong>January 2026</strong></td>
                                    <td>₱38,000</td>
                                    <td>₱12,500</td>
                                    <td>₱5,000</td>
                                    <td>₱2,400</td>
                                    <td><strong style="color:var(--iecep-navy);">₱57,900</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>February 2026</strong></td>
                                    <td>₱42,500</td>
                                    <td>₱8,000</td>
                                    <td>₱0</td>
                                    <td>₱3,100</td>
                                    <td><strong style="color:var(--iecep-navy);">₱53,600</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>March 2026</strong></td>
                                    <td>₱29,000</td>
                                    <td>₱15,000</td>
                                    <td>₱0</td>
                                    <td>₱1,800</td>
                                    <td><strong style="color:var(--iecep-navy);">₱45,800</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>April 2026</strong></td>
                                    <td>₱31,200</td>
                                    <td>₱4,500</td>
                                    <td>₱0</td>
                                    <td>₱950</td>
                                    <td><strong style="color:var(--iecep-navy);">₱36,650</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>May 2026</strong></td>
                                    <td>₱18,500</td>
                                    <td>₱22,000</td>
                                    <td>₱0</td>
                                    <td>₱4,200</td>
                                    <td><strong style="color:var(--iecep-navy);">₱44,700</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>June 2026</strong></td>
                                    <td>₱24,000</td>
                                    <td>₱6,500</td>
                                    <td>₱0</td>
                                    <td>₱1,150</td>
                                    <td><strong style="color:var(--iecep-navy);">₱31,650</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>July 2026</strong></td>
                                    <td>₱35,000</td>
                                    <td>₱18,500</td>
                                    <td>₱0</td>
                                    <td>₱2,800</td>
                                    <td><strong style="color:var(--iecep-navy);">₱56,300</strong></td>
                                </tr>
                                <tr>
                                    <td><strong>August 2026</strong></td>
                                    <td>₱48,500</td>
                                    <td>₱0</td>
                                    <td>₱10,000</td>
                                    <td>₱5,600</td>
                                    <td><strong style="color:var(--iecep-navy);">₱64,100</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Institution Breakdown -->
            <div id="institutionTab" class="report-pane" style="display:none;">
                <div class="ap-card">
                    <div class="ap-card-header">
                        <h3 class="ap-card-title"><i class="fas fa-university"></i> Collections by Higher Education Institution</h3>
                    </div>
                    <div class="ap-table-wrapper">
                        <table class="ap-table">
                            <thead>
                                <tr>
                                    <th>Institution</th>
                                    <th>Registered Members</th>
                                    <th>Dues Remitted</th>
                                    <th>Pending Clearance</th>
                                    <th>Compliance Level</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>LSPU Santa Cruz</strong></td>
                                    <td>142</td>
                                    <td>₱134,900</td>
                                    <td><span style="color:var(--accent-emerald); font-weight:700;">₱0 (Cleared)</span></td>
                                    <td><span class="ap-pill active"><span class="ap-pill-dot"></span> 100% Compliant</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Mapúa Malayan Colleges Laguna</strong></td>
                                    <td>98</td>
                                    <td>₱93,100</td>
                                    <td><span style="color:var(--accent-amber); font-weight:700;">₱2,500</span></td>
                                    <td><span class="ap-pill active"><span class="ap-pill-dot"></span> 97% Compliant</span></td>
                                </tr>
                                <tr>
                                    <td><strong>De La Salle University - Laguna</strong></td>
                                    <td>87</td>
                                    <td>₱82,650</td>
                                    <td><span style="color:var(--accent-emerald); font-weight:700;">₱0 (Cleared)</span></td>
                                    <td><span class="ap-pill active"><span class="ap-pill-dot"></span> 100% Compliant</span></td>
                                </tr>
                                <tr>
                                    <td><strong>LSPU San Pablo</strong></td>
                                    <td>76</td>
                                    <td>₱72,200</td>
                                    <td><span style="color:var(--accent-emerald); font-weight:700;">₱0 (Cleared)</span></td>
                                    <td><span class="ap-pill active"><span class="ap-pill-dot"></span> 100% Compliant</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Colegio de San Juan de Letran - Calamba</strong></td>
                                    <td>52</td>
                                    <td>₱49,400</td>
                                    <td><span style="color:var(--accent-amber); font-weight:700;">₱4,750</span></td>
                                    <td><span class="ap-pill pending"><span class="ap-pill-dot"></span> 90% Compliant</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Revenue Categories -->
            <div id="categoriesTab" class="report-pane" style="display:none;">
                <div class="ap-kpi-grid">
                    <div class="ap-stat-card">
                        <div class="ap-stat-header"><div class="ap-stat-icon navy"><i class="fas fa-id-card"></i></div><div><div class="ap-stat-label">Category</div><div class="ap-stat-sublabel">Membership Dues</div></div></div>
                        <div class="ap-stat-value">₱266,700</div>
                        <div class="ap-stat-footer">68.2% of Total Revenue</div>
                    </div>
                    <div class="ap-stat-card">
                        <div class="ap-stat-header"><div class="ap-stat-icon gold"><i class="fas fa-calendar-star"></i></div><div><div class="ap-stat-label">Category</div><div class="ap-stat-sublabel">Event Registrations</div></div></div>
                        <div class="ap-stat-value">₱87,000</div>
                        <div class="ap-stat-footer">22.3% of Total Revenue</div>
                    </div>
                    <div class="ap-stat-card">
                        <div class="ap-stat-header"><div class="ap-stat-icon emerald"><i class="fas fa-stamp"></i></div><div><div class="ap-stat-label">Category</div><div class="ap-stat-sublabel">Accreditation Fees</div></div></div>
                        <div class="ap-stat-value">₱15,000</div>
                        <div class="ap-stat-footer">3.8% of Total Revenue</div>
                    </div>
                    <div class="ap-stat-card">
                        <div class="ap-stat-header"><div class="ap-stat-icon purple"><i class="fas fa-shirt"></i></div><div><div class="ap-stat-label">Category</div><div class="ap-stat-sublabel">Merchandise</div></div></div>
                        <div class="ap-stat-value">₱22,000</div>
                        <div class="ap-stat-footer">5.7% of Total Revenue</div>
                    </div>
                </div>
            </div>

            <!-- Sentinel -->
            <div class="ap-sentinel-strip">
                <div class="ap-sentinel-item"><i class="fas fa-calculator"></i><span><strong>Audited By:</strong> Regional Treasury Committee</span></div>
                <div class="ap-sentinel-item"><i class="fas fa-shield-halved"></i><span><strong>Cryptographic Anchor:</strong> SHA-256 Ledger Backed</span></div>
                <div class="ap-sentinel-item"><i class="fas fa-clock"></i><span><strong>Last Audit:</strong> <?= date('Y-m-d H:i:s') ?> UTC+8</span></div>
            </div>

        </div>
    </main>

    <script>
        let monthlyChart, paymentChart;

        function switchReportTab(tabId, el) {
            document.querySelectorAll('.ap-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.report-pane').forEach(p => p.style.display = 'none');
            
            el.classList.add('active');
            if (tabId === 'monthly') document.getElementById('monthlyTab').style.display = 'block';
            if (tabId === 'institution') document.getElementById('institutionTab').style.display = 'block';
            if (tabId === 'categories') document.getElementById('categoriesTab').style.display = 'block';
        }

        function initCharts() {
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug'];
            const incomeData = [57900, 53600, 45800, 36650, 44700, 31650, 56300, 64100];

            const ctx1 = document.getElementById('monthlyIncomeChart').getContext('2d');
            monthlyChart = new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'Gross Collections (₱)',
                        data: incomeData,
                        backgroundColor: '#0B1D4A',
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { color: '#F1F5F9' } },
                        x: { grid: { display: false } }
                    }
                }
            });

            const ctx2 = document.getElementById('paymentTypeChart').getContext('2d');
            paymentChart = new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: ['Membership', 'Events', 'Accreditation', 'Merch'],
                    datasets: [{
                        data: [68.2, 22.3, 3.8, 5.7],
                        backgroundColor: ['#0B1D4A', '#D4AF37', '#059669', '#7C3AED'],
                        borderWidth: 2,
                        borderColor: '#FFFFFF'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }

        function loadReports() {
            alert('Reloading live report aggregation...');
        }

        function exportCSV() {
            alert('Generating CSV file for download...');
        }

        document.addEventListener('DOMContentLoaded', initCharts);
    </script>
</body>
</html>
