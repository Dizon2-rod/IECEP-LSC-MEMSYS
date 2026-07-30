<?php
require_once __DIR__ . '/../../auth_check.php';

if (!isset($current_page)) { $current_page = basename(__FILE__, '.php'); }
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/middleware/auth.php';

// Check if user is admin
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'] ?? '', ['admin', 'super_admin'])) {
    header('Location: /login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - IECEP-LSC MEMSYS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/design-tokens.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --primary-color: #0B1D4A;
            --secondary-color: #C49A00;
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
        
        .recommendation-card {
            border-left: 4px solid var(--secondary-color);
            padding: 1rem;
            margin-bottom: 1rem;
            background: #f8fafc;
            border-radius: 8px;
        }
        
        .priority-high { border-left-color: #ef4444; }
        .priority-medium { border-left-color: #f59e0b; }
        .priority-low { border-left-color: #10b981; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../../../includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <h2 class="mb-3">Analytics Dashboard</h2>
                <div class="d-flex gap-2 mb-3">
                    <select class="form-select w-auto" id="analytics-year" onchange="loadAnalytics()">
                        <option value="2024">2024</option>
                        <option value="2025">2025</option>
                        <option value="2026" selected>2026</option>
                    </select>
                    <button class="btn btn-primary" onclick="loadAnalytics()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh
                    </button>
                    <button class="btn btn-secondary" onclick="exportReport()">
                        <i class="fas fa-download me-2"></i>Export Report
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Key Metrics -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="metric-card text-center">
                    <div class="metric-value" id="total-members">0</div>
                    <div class="metric-label">Total Members</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="metric-card text-center">
                    <div class="metric-value" id="total-institutions">0</div>
                    <div class="metric-label">Institutions</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="metric-card text-center">
                    <div class="metric-value" id="total-events">0</div>
                    <div class="metric-label">Events</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="metric-card text-center">
                    <div class="metric-value" id="total-revenue">₱0</div>
                    <div class="metric-label">Revenue</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="metric-card text-center">
                    <div class="metric-value" id="compliant-institutions">0</div>
                    <div class="metric-label">Compliant</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="metric-card text-center">
                    <div class="metric-value" id="compliance-rate">0%</div>
                    <div class="metric-label">Compliance Rate</div>
                </div>
            </div>
        </div>
        
        <!-- Charts Row 1 -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="metric-card">
                    <h5 class="mb-3">Membership Growth</h5>
                    <div class="chart-container">
                        <canvas id="membershipChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-card">
                    <h5 class="mb-3">Event Participation</h5>
                    <div class="chart-container">
                        <canvas id="participationChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Row 2 -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="metric-card">
                    <h5 class="mb-3">Revenue Trends</h5>
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-card">
                    <h5 class="mb-3">Compliance Overview</h5>
                    <div class="chart-container">
                        <canvas id="complianceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Decision Support -->
        <div class="row">
            <div class="col-md-12">
                <div class="metric-card">
                    <h5 class="mb-3">Decision Support & Recommendations</h5>
                    <div id="recommendations">
                        <!-- Recommendations will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script>
        let membershipChart, revenueChart, participationChart, complianceChart;
        
        async function loadAnalytics() {
            const year = document.getElementById('analytics-year').value;
            
            try {
                const response = await fetch(`/api/admin/analytics.php?action=dashboard&year=${year}`);
                const data = await response.json();
                
                if (data.success) {
                    updateMetrics(data.key_metrics);
                    updateMembershipChart(data.membership_growth);
                    updateRevenueChart(data.revenue_trends);
                    updateParticipationChart(data.event_participation);
                    updateComplianceChart(data.compliance_overview);
                }
            } catch (error) {
                console.error('Error loading analytics:', error);
            }
            
            // Load decision support
            loadDecisionSupport();
        }
        
        function updateMetrics(metrics) {
            document.getElementById('total-members').textContent = metrics.total_members;
            document.getElementById('total-institutions').textContent = metrics.total_institutions;
            document.getElementById('total-events').textContent = metrics.total_events;
            document.getElementById('total-revenue').textContent = '₱' + metrics.total_revenue.toLocaleString();
            document.getElementById('compliant-institutions').textContent = metrics.compliant_institutions;
            document.getElementById('compliance-rate').textContent = metrics.compliance_rate + '%';
        }
        
        function updateMembershipChart(data) {
            const ctx = document.getElementById('membershipChart').getContext('2d');
            
            if (membershipChart) membershipChart.destroy();
            
            membershipChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.month_name),
                    datasets: [{
                        label: 'New Members',
                        data: data.map(d => d.new_members),
                        borderColor: '#0B1D4A',
                        backgroundColor: 'rgba(11, 29, 74, 0.1)',
                        fill: true,
                        tension: 0.4
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
        
        function updateRevenueChart(data) {
            const ctx = document.getElementById('revenueChart').getContext('2d');
            
            if (revenueChart) revenueChart.destroy();
            
            revenueChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.month_name),
                    datasets: [{
                        label: 'Revenue (₱)',
                        data: data.map(d => d.revenue),
                        backgroundColor: '#C49A00'
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
        
        function updateParticipationChart(data) {
            const ctx = document.getElementById('participationChart').getContext('2d');
            
            if (participationChart) participationChart.destroy();
            
            participationChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: data.slice(0, 5).map(d => d.event_name.substring(0, 15) + '...'),
                    datasets: [{
                        data: data.slice(0, 5).map(d => d.attendees),
                        backgroundColor: ['#0B1D4A', '#C49A00', '#10b981', '#f59e0b', '#ef4444']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
        
        function updateComplianceChart(data) {
            const ctx = document.getElementById('complianceChart').getContext('2d');
            
            if (complianceChart) complianceChart.destroy();
            
            const compliant = data.filter(d => d.compliance_status === 'compliant').length;
            const atRisk = data.filter(d => d.compliance_status === 'at_risk').length;
            const nonCompliant = data.filter(d => d.compliance_status === 'non_compliant').length;
            
            complianceChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Compliant', 'At Risk', 'Non-Compliant'],
                    datasets: [{
                        data: [compliant, atRisk, nonCompliant],
                        backgroundColor: ['#10b981', '#f59e0b', '#ef4444']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
        
        async function loadDecisionSupport() {
            try {
                const response = await fetch('/api/admin/analytics.php?action=decision-support');
                const data = await response.json();
                
                if (data.success) {
                    displayRecommendations(data.decision_support.recommendations);
                }
            } catch (error) {
                console.error('Error loading decision support:', error);
            }
        }
        
        function displayRecommendations(recommendations) {
            const container = document.getElementById('recommendations');
            container.innerHTML = '';
            
            if (recommendations.length === 0) {
                container.innerHTML = '<p class="text-muted">No immediate actions required.</p>';
                return;
            }
            
            recommendations.forEach(rec => {
                const card = document.createElement('div');
                card.className = `recommendation-card priority-${rec.priority}`;
                card.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">${rec.action}</h6>
                            <p class="mb-0 text-muted">${rec.description}</p>
                        </div>
                        <span class="badge bg-${rec.priority === 'high' ? 'danger' : 'warning'}">${rec.priority}</span>
                    </div>
                `;
                container.appendChild(card);
            });
        }
        
        function exportReport() {
            alert('PDF/CSV export would be implemented here using DOMPDF and CSV generation');
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            loadAnalytics();
        });
    </script>
</body>
</html>
