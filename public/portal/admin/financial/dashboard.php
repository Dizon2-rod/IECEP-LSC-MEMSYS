<?php
require_once __DIR__ . '/../../auth_check.php';

require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/middleware/auth.php';

// Check if user is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

$current_page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Dashboard - IECEP-LSC MEMSYS</title>
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
        
        .metric-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .metric-label {
            color: #64748b;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        
        .chart-container {
            position: relative;
            height: 350px;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../../../includes/navbar.php'; ?>
    <?php include __DIR__ . '/../../../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <div class="row mb-4">
                <div class="col-md-12">
                    <h2 class="mb-3">Financial Dashboard</h2>
                    <div class="d-flex gap-2 mb-3">
                        <select class="form-select w-auto" id="filter-year" onchange="loadFinancialData()">
                            <option value="2024">2024</option>
                            <option value="2025">2025</option>
                            <option value="2026" selected>2026</option>
                        </select>
                        <button class="btn btn-primary" onclick="loadFinancialData()">
                            <i class="fas fa-sync-alt me-2"></i>Refresh
                        </button>
                        <button class="btn btn-secondary" onclick="downloadReport()">
                            <i class="fas fa-download me-2"></i>Download Report
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Key Metrics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="metric-card text-center">
                        <div class="metric-value" id="total-income">₱0</div>
                        <div class="metric-label">Total Income</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card text-center">
                        <div class="metric-value" id="pending-payments">₱0</div>
                        <div class="metric-label">Pending Payments</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card text-center">
                        <div class="metric-value" id="total-members">0</div>
                        <div class="metric-label">Total Members</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card text-center">
                        <div class="metric-value" id="total-institutions">0</div>
                        <div class="metric-label">Total Institutions</div>
                    </div>
                </div>
            </div>
            
            <!-- Monthly Income Chart -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="metric-card">
                        <h5 class="mb-3">Monthly Income</h5>
                        <div class="chart-container">
                            <canvas id="incomeChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="metric-card">
                        <h5 class="mb-3">Income by Type</h5>
                        <div class="chart-container">
                            <canvas id="incomeTypeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Per-Institution Income Table -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="metric-card">
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
                                    <!-- Data will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Per-Event Income Table -->
            <div class="row">
                <div class="col-md-12">
                    <div class="metric-card">
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
                                    <!-- Data will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
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
                        data: data.map(d => d.amount),
                        backgroundColor: '#0B1D4A'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
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
            
            institutions.forEach(inst => {
                const row = document.createElement('tr');
                const blockchainBadge = inst.blockchain_verified 
                    ? '<span class="badge bg-success ms-2"><i class="fas fa-shield-alt"></i> Verified</span>' 
                    : '<span class="badge bg-warning ms-2"><i class="fas fa-exclamation-triangle"></i> Unverified</span>';
                
                row.innerHTML = `
                    <td>${inst.name}</td>
                    <td>${inst.member_count}</td>
                    <td>₱${inst.total_paid.toLocaleString()}</td>
                    <td>₱${inst.pending.toLocaleString()}</td>
                    <td>${inst.last_payment ? new Date(inst.last_payment).toLocaleDateString() : 'Never'}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="viewDetails('${inst.id}')">
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
                    ? '<span class="badge bg-success"><i class="fas fa-shield-alt"></i> Verified</span>' 
                    : '<span class="badge bg-warning"><i class="fas fa-exclamation-triangle"></i> Unverified</span>';
                
                row.innerHTML = `
                    <td>${event.name}</td>
                    <td>${new Date(event.date).toLocaleDateString()}</td>
                    <td>₱${event.total_income.toLocaleString()}</td>
                    <td>${event.participant_count}</td>
                    <td>${blockchainBadge}</td>
                `;
                tbody.appendChild(row);
            });
        }
        
        function viewDetails(institutionId) {
            window.location.href = `/portal/admin/institutions/details.php?id=${institutionId}`;
        }
        
        function downloadReport() {
            alert('PDF download would be implemented here using DOMPDF');
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            loadFinancialData();
        });
    </script>
</body>
</html>
