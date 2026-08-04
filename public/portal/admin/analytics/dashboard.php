<?php
require_once __DIR__ . '/../../auth_check.php';

if (!isset($current_page)) { $current_page = basename(__FILE__, '.php'); }
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/middleware/auth.php';

// Check if user is admin
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'] ?? '', ['admin', 'super_admin'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - IECEP-LSC MEMSYS</title>
    <?php include __DIR__ . '/../../../../includes/head-meta.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../../../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-chart-bar"></i> Analytics Dashboard</h1>
                <p class="text-muted">Comprehensive insights and decision support</p>
            </div>
            <div class="header-actions">
                <select class="form-select w-auto" id="analytics-year" onchange="loadAnalytics()">
                    <option value="2024">2024</option>
                    <option value="2025">2025</option>
                    <option value="2026" selected>2026</option>
                </select>
                <button class="btn btn-primary" onclick="loadAnalytics()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <button class="btn btn-secondary" onclick="exportReport()">
                    <i class="fas fa-download"></i> Export Report
                </button>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon icon-blue"><i class="fas fa-users"></i></div>
                <div class="stat-details">
                    <h3 id="total-members">0</h3>
                    <p>Total Members</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-indigo"><i class="fas fa-university"></i></div>
                <div class="stat-details">
                    <h3 id="total-institutions">0</h3>
                    <p>Institutions</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-gold"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-details">
                    <h3 id="total-events">0</h3>
                    <p>Events</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-emerald"><i class="fas fa-wallet"></i></div>
                <div class="stat-details">
                    <h3 id="total-revenue">₱0</h3>
                    <p>Revenue</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-navy"><i class="fas fa-check-circle"></i></div>
                <div class="stat-details">
                    <h3 id="compliant-institutions">0</h3>
                    <p>Compliant</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon icon-gold"><i class="fas fa-percentage"></i></div>
                <div class="stat-details">
                    <h3 id="compliance-rate">0%</h3>
                    <p>Compliance Rate</p>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-8">
                <div class="content-card">
                    <h5 class="mb-3">Membership Growth</h5>
                    <div class="chart-container">
                        <canvas id="membershipChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="content-card">
                    <h5 class="mb-3">Event Participation</h5>
                    <div class="chart-container">
                        <canvas id="participationChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-8">
                <div class="content-card">
                    <h5 class="mb-3">Revenue Trends</h5>
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="content-card">
                    <h5 class="mb-3">Compliance Overview</h5>
                    <div class="chart-container">
                        <canvas id="complianceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="content-card">
                    <h5 class="mb-3">Decision Support & Recommendations</h5>
                    <div id="recommendations">
                        <div class="empty-state">
                            <i class="fas fa-lightbulb"></i>
                            <p>Loading recommendations...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                    updateRecommendations(data.decision_support);
                }
            } catch (error) {
                console.error('Error loading analytics:', error);
            }
        }

        function updateMetrics(metrics) {
            document.getElementById('total-members').textContent = metrics.total_members || 0;
            document.getElementById('total-institutions').textContent = metrics.total_institutions || 0;
            document.getElementById('total-events').textContent = metrics.total_events || 0;
            document.getElementById('total-revenue').textContent = '₱' + (metrics.total_revenue || 0).toLocaleString();
            document.getElementById('compliant-institutions').textContent = metrics.compliant_institutions || 0;
            document.getElementById('compliance-rate').textContent = (metrics.compliance_rate || 0) + '%';
        }

        function updateMembershipChart(data) {
            const ctx = document.getElementById('membershipChart').getContext('2d');
            if (membershipChart) membershipChart.destroy();

            membershipChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.month_name),
                    datasets: [{
                        label: 'New Members',
                        data: data.map(d => d.new_members),
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

        function updateRevenueChart(data) {
            const ctx = document.getElementById('revenueChart').getContext('2d');
            if (revenueChart) revenueChart.destroy();

            revenueChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.month_name),
                    datasets: [{
                        label: 'Revenue (₱)',
                        data: data.map(d => d.revenue),
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

        function updateParticipationChart(data) {
            const ctx = document.getElementById('participationChart').getContext('2d');
            if (participationChart) participationChart.destroy();

            const events = data.slice(0, 5);
            participationChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: events.map(e => e.event_name),
                    datasets: [{
                        label: 'Attendees',
                        data: events.map(e => e.attendees),
                        backgroundColor: '#D4AF37'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: { legend: { display: false } },
                    scales: { x: { beginAtZero: true } }
                }
            });
        }

        function updateComplianceChart(data) {
            const ctx = document.getElementById('complianceChart').getContext('2d');
            if (complianceChart) complianceChart.destroy();

            const compliant = data.filter(c => c.compliance_status === 'compliant').length;
            const atRisk = data.filter(c => c.compliance_status === 'at_risk').length;
            const nonCompliant = data.filter(c => c.compliance_status === 'non_compliant').length;

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

        function updateRecommendations(data) {
            const container = document.getElementById('recommendations');
            
            if (!data || !data.recommendations || data.recommendations.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>No immediate actions required. System is running smoothly.</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = data.recommendations.map(rec => `
                <div class="recommendation-card priority-${rec.priority}">
                    <div style="display: flex; align-items: flex-start; gap: 1rem;">
                        <div style="width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: ${rec.priority === 'high' ? '#fee2e2' : rec.priority === 'medium' ? '#fef3c7' : '#d1fae5'}; color: ${rec.priority === 'high' ? '#ef4444' : rec.priority === 'medium' ? '#f59e0b' : '#10b981'};">
                            <i class="fas fa-${rec.priority === 'high' ? 'exclamation-circle' : rec.priority === 'medium' ? 'info-circle' : 'check-circle'}"></i>
                        </div>
                        <div>
                            <strong style="color: var(--portal-navy); display: block; margin-bottom: 0.25rem;">${rec.action}</strong>
                            <p style="color: var(--portal-text-muted); margin: 0; font-size: 0.9rem;">${rec.description}</p>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function exportReport() {
            alert('PDF export would be implemented here using DOMPDF');
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadAnalytics();
        });
    </script>
</body>
</html>
