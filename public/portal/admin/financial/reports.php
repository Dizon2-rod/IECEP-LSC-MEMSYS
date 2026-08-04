<?php
require_once __DIR__ . '/../../auth_check.php';

require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/middleware/auth.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
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
    <title><?= htmlspecialchars($pageTitle) ?> - IECEP-LSC</title>
    <?php include __DIR__ . '/../../../../includes/head-meta.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../../../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-file-alt"></i> <?= htmlspecialchars($pageTitle) ?></h1>
            <div class="d-flex gap-2">
                <select class="form-select w-auto" id="report-year" onchange="loadReports()">
                    <option value="2024">2024</option>
                    <option value="2025">2025</option>
                    <option value="2026" selected>2026</option>
                </select>
                <button class="btn btn-primary" onclick="loadReports()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <button class="btn btn-secondary" onclick="exportPDF()">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
                <button class="btn btn-secondary" onclick="exportCSV()">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
            </div>
        </div>

        <div class="content-card">
            <ul class="nav nav-tabs mb-4" id="reportTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#monthly" type="button">Monthly Report</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#annual" type="button">Annual Report</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#institution" type="button">Institution Breakdown</button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="monthly">
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <div class="content-card">
                                <h5 class="mb-3">Monthly Income</h5>
                                <div class="chart-container">
                                    <canvas id="monthlyIncomeChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="content-card">
                                <h5 class="mb-3">Payment Types</h5>
                                <div class="chart-container">
                                    <canvas id="paymentTypeChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="content-card">
                                <h5 class="mb-3">Monthly Details</h5>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Month</th>
                                                <th>Membership Fees</th>
                                                <th>Event Fees</th>
                                                <th>Donations</th>
                                                <th>Penalties</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody id="monthly-table">
                                            <tr>
                                                <td colspan="6" class="text-center py-4">
                                                    <div class="empty-state">
                                                        <i class="fas fa-spinner fa-spin fa-3x text-muted mb-3"></i>
                                                        <p class="text-muted">Loading...</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="annual">
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="content-card">
                                <h5 class="mb-3">Annual Income Trend</h5>
                                <div class="chart-container">
                                    <canvas id="annualTrendChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="content-card">
                                <h5 class="mb-3">Annual Summary</h5>
                                <div class="stats-grid">
                                    <div class="stat-card">
                                        <div class="stat-label">Total Income</div>
                                        <div class="stat-value" id="annual-total">₱0</div>
                                    </div>
                                    <div class="stat-card success">
                                        <div class="stat-label">Membership Fees</div>
                                        <div class="stat-value" id="annual-membership">₱0</div>
                                    </div>
                                    <div class="stat-card info">
                                        <div class="stat-label">Event Fees</div>
                                        <div class="stat-value" id="annual-events">₱0</div>
                                    </div>
                                    <div class="stat-card warning">
                                        <div class="stat-label">Other Income</div>
                                        <div class="stat-value" id="annual-other">₱0</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="institution">
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="content-card">
                                <h5 class="mb-3">Institution Income Distribution</h5>
                                <div class="chart-container">
                                    <canvas id="institutionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="content-card">
                                <h5 class="mb-3">Institution Details</h5>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Institution</th>
                                                <th>Members</th>
                                                <th>Membership Fees</th>
                                                <th>Event Fees</th>
                                                <th>Total Paid</th>
                                                <th>Compliance Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="institution-table">
                                            <tr>
                                                <td colspan="6" class="text-center py-4">
                                                    <div class="empty-state">
                                                        <i class="fas fa-building fa-3x text-muted mb-3"></i>
                                                        <p class="text-muted">Loading...</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let monthlyIncomeChart, paymentTypeChart, annualTrendChart, institutionChart;

        async function loadReports() {
            const year = document.getElementById('report-year').value;

            try {
                const response = await fetch(`/api/treasurer/financial-reports.php?action=monthly&year=${year}`);
                const data = await response.json();

                if (data.success) {
                    updateMonthlyReport(data);
                    loadAnnualReport(year);
                    loadInstitutionBreakdown(year);
                }
            } catch (error) {
                console.error('Error loading reports:', error);
            }
        }

        function updateMonthlyReport(data) {
            const ctx1 = document.getElementById('monthlyIncomeChart').getContext('2d');
            if (monthlyIncomeChart) monthlyIncomeChart.destroy();

            monthlyIncomeChart = new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: data.monthly_data.map(d => d.month),
                    datasets: [{
                        label: 'Income (₱)',
                        data: data.monthly_data.map(d => d.total_income),
                        backgroundColor: '#0B1D4A'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } }
                }
            });

            const ctx2 = document.getElementById('paymentTypeChart').getContext('2d');
            if (paymentTypeChart) paymentTypeChart.destroy();

            paymentTypeChart = new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(data.income_by_type),
                    datasets: [{
                        data: Object.values(data.income_by_type),
                        backgroundColor: ['#0B1D4A', '#D4AF37', '#10b981', '#f59e0b']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            const tbody = document.getElementById('monthly-table');
            tbody.innerHTML = '';

            data.monthly_data.forEach(m => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${m.month}</td>
                    <td>₱${(m.membership_fee || 0).toLocaleString()}</td>
                    <td>₱${(m.event_fee || 0).toLocaleString()}</td>
                    <td>₱${(m.donation || 0).toLocaleString()}</td>
                    <td>₱${(m.penalty || 0).toLocaleString()}</td>
                    <td><strong>₱${(m.total_income || 0).toLocaleString()}</strong></td>
                `;
                tbody.appendChild(row);
            });
        }

        async function loadAnnualReport(year) {
            try {
                const response = await fetch(`/api/treasurer/financial-reports.php?action=annual&year=${year}`);
                const data = await response.json();

                if (data.success) {
                    document.getElementById('annual-total').textContent = '₱' + (data.total_income || 0).toLocaleString();
                    document.getElementById('annual-membership').textContent = '₱' + (data.membership_fees || 0).toLocaleString();
                    document.getElementById('annual-events').textContent = '₱' + (data.event_fees || 0).toLocaleString();
                    document.getElementById('annual-other').textContent = '₱' + (data.other_income || 0).toLocaleString();

                    const ctx = document.getElementById('annualTrendChart').getContext('2d');
                    if (annualTrendChart) annualTrendChart.destroy();

                    annualTrendChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.monthly_trend.map(d => d.month),
                            datasets: [{
                                label: 'Income (₱)',
                                data: data.monthly_trend.map(d => d.amount),
                                borderColor: '#0B1D4A',
                                backgroundColor: 'rgba(11, 29, 74, 0.1)',
                                fill: true,
                                tension: 0.4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: { y: { beginAtZero: true } }
                        }
                    });
                }
            } catch (error) {
                console.error('Error loading annual report:', error);
            }
        }

        async function loadInstitutionBreakdown(year) {
            try {
                const response = await fetch(`/api/treasurer/financial-reports.php?action=institutions&year=${year}`);
                const data = await response.json();

                if (data.success) {
                    const ctx = document.getElementById('institutionChart').getContext('2d');
                    if (institutionChart) institutionChart.destroy();

                    institutionChart = new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: data.institutions.map(i => i.name),
                            datasets: [{
                                data: data.institutions.map(i => i.total_paid),
                                backgroundColor: data.institutions.map((_, i) => {
                                    const colors = ['#0B1D4A', '#D4AF37', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899'];
                                    return colors[i % colors.length];
                                })
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false
                        }
                    });

                    const tbody = document.getElementById('institution-table');
                    tbody.innerHTML = '';

                    data.institutions.forEach(inst => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${escapeHtml(inst.name)}</td>
                            <td>${inst.member_count}</td>
                            <td>₱${(inst.membership_fees || 0).toLocaleString()}</td>
                            <td>₱${(inst.event_fees || 0).toLocaleString()}</td>
                            <td><strong>₱${(inst.total_paid || 0).toLocaleString()}</strong></td>
                            <td><span class="badge ${inst.compliance_status === 'compliant' ? 'badge-success' : 'badge-warning'}">${escapeHtml(inst.compliance_status || 'N/A')}</span></td>
                        `;
                        tbody.appendChild(row);
                    });
                }
            } catch (error) {
                console.error('Error loading institution breakdown:', error);
            }
        }

        function exportPDF() {
            alert('PDF export would be implemented here using DOMPDF');
        }

        function exportCSV() {
            alert('CSV export would be implemented here');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadReports();
        });
    </script>

    
</body>
</html>
