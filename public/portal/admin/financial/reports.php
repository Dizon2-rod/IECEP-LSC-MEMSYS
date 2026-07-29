<?php
require_once __DIR__ . '/../../auth_check.php';

require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/middleware/auth.php';

// Check if user is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

$current_page = 'reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports - IECEP-LSC MEMSYS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/design-tokens.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --primary-color: #0B1D4A;
            --secondary-color: #D4AF37;
        }
        
        body {
            background-color: #f8fafc;
        }
        
        .metric-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .chart-container {
            position: relative;
            height: 350px;
            margin: 1rem 0;
        }
        
        .report-tabs .nav-link {
            color: #64748b;
        }
        
        .report-tabs .nav-link.active {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../../includes/header.php'; ?>
    <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <div class="row mb-4">
                <div class="col-md-12">
                    <h2 class="mb-3">Financial Reports</h2>
                    <div class="d-flex gap-2 mb-3">
                        <select class="form-select w-auto" id="report-year" onchange="loadReports()">
                            <option value="2024">2024</option>
                            <option value="2025">2025</option>
                            <option value="2026" selected>2026</option>
                        </select>
                        <button class="btn btn-primary" onclick="loadReports()">
                            <i class="fas fa-sync-alt me-2"></i>Refresh
                        </button>
                        <button class="btn btn-secondary" onclick="exportPDF()">
                            <i class="fas fa-file-pdf me-2"></i>Export PDF
                        </button>
                        <button class="btn btn-secondary" onclick="exportCSV()">
                            <i class="fas fa-file-csv me-2"></i>Export CSV
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Report Tabs -->
            <ul class="nav nav-tabs report-tabs mb-4" id="reportTabs" role="tablist">
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
                <!-- Monthly Report -->
                <div class="tab-pane fade show active" id="monthly">
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <div class="metric-card">
                                <h5 class="mb-3">Monthly Income</h5>
                                <div class="chart-container">
                                    <canvas id="monthlyIncomeChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="metric-card">
                                <h5 class="mb-3">Payment Types</h5>
                                <div class="chart-container">
                                    <canvas id="paymentTypeChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="metric-card">
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
                                            <!-- Data will be loaded here -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Annual Report -->
                <div class="tab-pane fade" id="annual">
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="metric-card">
                                <h5 class="mb-3">Annual Income Trend</h5>
                                <div class="chart-container">
                                    <canvas id="annualTrendChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="metric-card">
                                <h5 class="mb-3">Annual Summary</h5>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="text-center p-3 bg-light rounded">
                                            <div class="h4 mb-0" id="annual-total">₱0</div>
                                            <small class="text-muted">Total Income</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center p-3 bg-light rounded">
                                            <div class="h4 mb-0" id="annual-membership">₱0</div>
                                            <small class="text-muted">Membership Fees</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center p-3 bg-light rounded">
                                            <div class="h4 mb-0" id="annual-events">₱0</div>
                                            <small class="text-muted">Event Fees</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center p-3 bg-light rounded">
                                            <div class="h4 mb-0" id="annual-other">₱0</div>
                                            <small class="text-muted">Other Income</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Institution Breakdown -->
                <div class="tab-pane fade" id="institution">
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="metric-card">
                                <h5 class="mb-3">Institution Income Distribution</h5>
                                <div class="chart-container">
                                    <canvas id="institutionChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="metric-card">
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
                                            <!-- Data will be loaded here -->
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
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
            // Update monthly income chart
            const ctx1 = document.getElementById('monthlyIncomeChart').getContext('2d');
            if (monthlyIncomeChart) monthlyIncomeChart.destroy();
            
            monthlyIncomeChart = new Chart(ctx1, {
                type: 'bar',
                data: {
                    labels: data.monthly_data.map(d => d.month),
                    datasets: [{
                        label: 'Income (₱)',
                        data: data.monthly_data.map(d => d.amount),
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
            
            // Update payment type chart
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
            
            // Update monthly table
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
                    <td><strong>₱${m.amount.toLocaleString()}</strong></td>
                `;
                tbody.appendChild(row);
            });
        }
        
        async function loadAnnualReport(year) {
            try {
                const response = await fetch(`/api/treasurer/financial-reports.php?action=annual&year=${year}`);
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('annual-total').textContent = '₱' + data.total_income.toLocaleString();
                    document.getElementById('annual-membership').textContent = '₱' + data.membership_fees.toLocaleString();
                    document.getElementById('annual-events').textContent = '₱' + data.event_fees.toLocaleString();
                    document.getElementById('annual-other').textContent = '₱' + data.other_income.toLocaleString();
                    
                    // Update annual trend chart
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
                    // Update institution chart
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
                    
                    // Update institution table
                    const tbody = document.getElementById('institution-table');
                    tbody.innerHTML = '';
                    
                    data.institutions.forEach(inst => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${inst.name}</td>
                            <td>${inst.member_count}</td>
                            <td>₱${(inst.membership_fees || 0).toLocaleString()}</td>
                            <td>₱${(inst.event_fees || 0).toLocaleString()}</td>
                            <td><strong>₱${inst.total_paid.toLocaleString()}</strong></td>
                            <td><span class="badge bg-${inst.compliance_status === 'compliant' ? 'success' : 'warning'}">${inst.compliance_status}</span></td>
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
        
        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            loadReports();
        });
    </script>
</body>
</html>
