<?php
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/role-config.php';

// Check if user is logged in and has treasurer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'eb_treasurer') {
    header('Location: ' . PORTAL_URL . '/login.php');
    exit;
}

$pageTitle = 'Financial Reports';
include '../../includes/dashboard-layout.php';

// Get financial data for charts
try {
    // Monthly income data for the last 12 months
    $monthlyIncome = $supabaseClient->rpc('get_monthly_income', [
        'months_back' => 12
    ]);

    // Payment status breakdown
    $statusBreakdown = $supabaseClient->from('transactions')
        ->select('status, amount')
        ->execute();

    $statusData = [];
    foreach ($statusBreakdown as $transaction) {
        $status = $transaction['status'];
        if (!isset($statusData[$status])) {
            $statusData[$status] = 0;
        }
        $statusData[$status] += (float) $transaction['amount'];
    }

} catch (Exception $e) {
    $monthlyIncome = [];
    $statusData = [];
    error_log('Error fetching financial data: ' . $e->getMessage());
}
?>

<div class="dashboard-container">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Financial Reports</h1>
                    <p class="text-muted">Comprehensive financial analytics and visualizations</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="refreshCharts()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh
                    </button>
                    <button class="btn btn-outline-secondary" onclick="exportReport()">
                        <i class="fas fa-download me-2"></i>Export PDF
                    </button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <?php
                $totalIncome = array_sum(array_column($monthlyIncome, 'income'));
                $completedPayments = $statusData['completed'] ?? 0;
                $pendingPayments = $statusData['pending'] ?? 0;
                $totalTransactions = array_sum($statusData);
                ?>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-dollar-sign fa-2x text-success mb-2"></i>
                            <h4 class="mb-0">₱<?= number_format($totalIncome, 2) ?></h4>
                            <small class="text-muted">Total Income</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle fa-2x text-primary mb-2"></i>
                            <h4 class="mb-0">₱<?= number_format($completedPayments, 2) ?></h4>
                            <small class="text-muted">Completed</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                            <h4 class="mb-0">₱<?= number_format($pendingPayments, 2) ?></h4>
                            <small class="text-muted">Pending</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-receipt fa-2x text-info mb-2"></i>
                            <h4 class="mb-0"><?= count($monthlyIncome) ?></h4>
                            <small class="text-muted">Transactions</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Monthly Income Trend</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="monthlyIncomeChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Payment Status Breakdown</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="statusBreakdownChart" width="200" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Reports Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Transaction Summary</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Income</th>
                                    <th>Transactions</th>
                                    <th>Average per Transaction</th>
                                    <th>Growth</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $previousIncome = 0;
                                foreach (array_reverse($monthlyIncome) as $month):
                                    $growth = $previousIncome > 0 ? (($month['income'] - $previousIncome) / $previousIncome) * 100 : 0;
                                    $avgPerTransaction = $month['transaction_count'] > 0 ? $month['income'] / $month['transaction_count'] : 0;
                                ?>
                                    <tr>
                                        <td><?= date('M Y', strtotime($month['month'])) ?></td>
                                        <td>₱<?= number_format($month['income'], 2) ?></td>
                                        <td><?= $month['transaction_count'] ?></td>
                                        <td>₱<?= number_format($avgPerTransaction, 2) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $growth >= 0 ? 'success' : 'danger' ?>">
                                                <?= $growth >= 0 ? '+' : '' ?><?= number_format($growth, 1) ?>%
                                            </span>
                                        </td>
                                    </tr>
                                <?php
                                    $previousIncome = $month['income'];
                                endforeach;
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Chart data
const monthlyData = <?= json_encode(array_reverse($monthlyIncome)) ?>;
const statusData = <?= json_encode($statusData) ?>;

// Monthly Income Chart
const monthlyCtx = document.getElementById('monthlyIncomeChart').getContext('2d');
const monthlyIncomeChart = new Chart(monthlyCtx, {
    type: 'line',
    data: {
        labels: monthlyData.map(item => {
            const date = new Date(item.month);
            return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        }),
        datasets: [{
            label: 'Monthly Income (₱)',
            data: monthlyData.map(item => item.income),
            borderColor: '#0B1D4A',
            backgroundColor: 'rgba(11, 29, 74, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: 'Monthly Income Trend'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '₱' + value.toLocaleString();
                    }
                }
            }
        }
    }
});

// Status Breakdown Chart
const statusCtx = document.getElementById('statusBreakdownChart').getContext('2d');
const statusBreakdownChart = new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: Object.keys(statusData).map(status => status.charAt(0).toUpperCase() + status.slice(1)),
        datasets: [{
            data: Object.values(statusData),
            backgroundColor: [
                '#28a745', // completed - green
                '#ffc107', // pending - yellow
                '#dc3545', // failed - red
                '#6c757d'  // cancelled - gray
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
            },
            title: {
                display: true,
                text: 'Payment Status Distribution'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = ((context.parsed / total) * 100).toFixed(1);
                        return context.label + ': ₱' + context.parsed.toLocaleString() + ' (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

function refreshCharts() {
    // Reload the page to refresh data
    location.reload();
}

function exportReport() {
    // Generate and download PDF report
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>IECEP-LSC Financial Report</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; border-bottom: 2px solid #0B1D4A; padding-bottom: 20px; margin-bottom: 30px; }
                .logo { font-size: 24px; font-weight: bold; color: #0B1D4A; }
                .summary { display: flex; justify-content: space-around; margin: 20px 0; }
                .summary-item { text-align: center; }
                .summary-value { font-size: 24px; font-weight: bold; color: #0B1D4A; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f8f9fa; }
                .footer { margin-top: 40px; text-align: center; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="logo">IECEP-LSC MEMSYS</div>
                <h2>Financial Report</h2>
                <p>Generated on ${new Date().toLocaleDateString()}</p>
            </div>

            <div class="summary">
                <div class="summary-item">
                    <div class="summary-value">₱${<?= json_encode(number_format($totalIncome, 2)) ?>}</div>
                    <div>Total Income</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value">₱${<?= json_encode(number_format($completedPayments, 2)) ?>}</div>
                    <div>Completed Payments</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value">${<?= json_encode(count($monthlyIncome)) ?>}</div>
                    <div>Transactions</div>
                </div>
            </div>

            <h3>Monthly Income Trend</h3>
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Income</th>
                        <th>Transactions</th>
                        <th>Average per Transaction</th>
                    </tr>
                </thead>
                <tbody>
                    ${monthlyData.map(month => `
                        <tr>
                            <td>${new Date(month.month).toLocaleDateString('en-US', { month: 'short', year: 'numeric' })}</td>
                            <td>₱${month.income.toLocaleString()}</td>
                            <td>${month.transaction_count}</td>
                            <td>₱${(month.transaction_count > 0 ? month.income / month.transaction_count : 0).toFixed(2)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>

            <div class="footer">
                <p>This report was generated by IECEP-LSC MEMSYS</p>
                <p>Confidential - For Internal Use Only</p>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}
</script>

<?php include '../../includes/footer.php'; ?>
