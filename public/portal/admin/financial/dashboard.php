<?php
require_once __DIR__ . '/../../auth_check.php';

require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/middleware/auth.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$current_page = 'dashboard';
$pageTitle = 'Financial Dashboard';
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
            <h1><i class="fas fa-chart-line"></i> <?= htmlspecialchars($pageTitle) ?></h1>
            <div class="d-flex gap-2">
                <select class="form-select w-auto" id="filter-year" onchange="loadFinancialData()">
                    <option value="2024">2024</option>
                    <option value="2025">2025</option>
                    <option value="2026" selected>2026</option>
                </select>
                <button class="btn btn-primary" onclick="loadFinancialData()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <button class="btn btn-secondary" onclick="downloadReport()">
                    <i class="fas fa-download"></i> Download Report
                </button>
            </div>
        </div>

        <div class="stats-grid mb-4">
            <div class="stat-card">
                <div class="stat-label">Total Income</div>
                <div class="stat-value" id="total-income">₱0</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-label">Pending Payments</div>
                <div class="stat-value" id="pending-payments">₱0</div>
            </div>
            <div class="stat-card success">
                <div class="stat-label">Total Members</div>
                <div class="stat-value" id="total-members">0</div>
            </div>
            <div class="stat-card info">
                <div class="stat-label">Total Institutions</div>
                <div class="stat-value" id="total-institutions">0</div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-8">
                <div class="content-card">
                    <h5 class="mb-3">Monthly Income</h5>
                    <div class="chart-container">
                        <canvas id="incomeChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="content-card">
                    <h5 class="mb-3">Income by Type</h5>
                    <div class="chart-container">
                        <canvas id="incomeTypeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-12">
                <div class="content-card">
                    <h5 class="mb-3">Per-Institution Income</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Institution</th>
                                    <th>Members</th>
                                    <th>Total Paid</th>
                                    <th>Pending</th>
                                    <th>Last Payment</th>
                                    <th>Actions</th>
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

        <div class="row">
            <div class="col-md-12">
                <div class="content-card">
                    <h5 class="mb-3">Per-Event Income</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
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
                                    <td colspan="5" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="fas fa-calendar fa-3x text-muted mb-3"></i>
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
                }
            } catch (error) {
                console.error('Error loading financial data:', error);
            }
        }

        function updateMetrics(data) {
            document.getElementById('total-income').textContent = '₱' + (data.total_income || 0).toLocaleString();
            document.getElementById('pending-payments').textContent = '₱' + (data.pending_payments || 0).toLocaleString();
            document.getElementById('total-members').textContent = data.total_members || 0;
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
        }

        function updateIncomeTypeChart(data) {
            const ctx = document.getElementById('incomeTypeChart').getContext('2d');

            if (incomeTypeChart) incomeTypeChart.destroy();

            const labels = Object.keys(data);
            const values = Object.values(data);

            incomeTypeChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: ['#0B1D4A', '#D4AF37', '#10b981', '#f59e0b']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        async function loadInstitutionTable(year) {
            try {
                const response = await fetch(`/api/treasurer/financial-reports.php?action=institutions&year=${year}`);
                const data = await response.json();

                if (data.success) {
                    displayInstitutionTable(data.institutions);
                }
            } catch (error) {
                console.error('Error loading institution data:', error);
            }
        }

        function displayInstitutionTable(institutions) {
            const tbody = document.getElementById('institution-table');
            tbody.innerHTML = '';

            if (!institutions || institutions.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center">No institution data available</td></tr>';
                return;
            }

            institutions.forEach(inst => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${escapeHtml(inst.name)}</td>
                    <td>${inst.member_count}</td>
                    <td>₱${(inst.total_paid || 0).toLocaleString()}</td>
                    <td>₱${(inst.pending || 0).toLocaleString()}</td>
                    <td>${inst.last_payment ? new Date(inst.last_payment).toLocaleDateString() : 'Never'}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="viewInstitution('${inst.id}')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        async function loadEventTable(year) {
            try {
                const response = await fetch(`/api/treasurer/financial-reports.php?action=events&year=${year}`);
                const data = await response.json();

                if (data.success) {
                    displayEventTable(data.events);
                }
            } catch (error) {
                console.error('Error loading event data:', error);
            }
        }

        function displayEventTable(events) {
            const tbody = document.getElementById('event-table');
            tbody.innerHTML = '';

            if (!events || events.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center">No event data available</td></tr>';
                return;
            }

            events.forEach(event => {
                const row = document.createElement('tr');
                const blockchainBadge = event.blockchain_verified
                    ? '<span class="badge badge-success"><i class="fas fa-shield-alt"></i> Verified</span>'
                    : '<span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> Unverified</span>';

                row.innerHTML = `
                    <td>${escapeHtml(event.name)}</td>
                    <td>${event.date ? new Date(event.date).toLocaleDateString() : 'N/A'}</td>
                    <td>₱${(event.total_income || 0).toLocaleString()}</td>
                    <td>${event.participant_count || 0}</td>
                    <td>${blockchainBadge}</td>
                `;
                tbody.appendChild(row);
            });
        }

        function viewInstitution(institutionId) {
            window.location.href = `/portal/admin/institutions/details.php?id=${institutionId}`;
        }

        function downloadReport() {
            alert('PDF download would be implemented here using DOMPDF');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadFinancialData();
        });
    </script>

    
</body>
</html>
