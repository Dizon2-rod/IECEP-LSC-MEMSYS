<?php
require_once '../../includes/config.php';
require_once '../../includes/role-config.php';

// Check if user is logged in and has treasurer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'eb_treasurer') {
    header('Location: ' . PORTAL_URL . '/login.php');
    exit;
}

$pageTitle = 'Financial Reports';
include '../../includes/dashboard-layout.php';
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
                        <i class="fas fa-download me-2"></i>Export Report
                    </button>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-dollar-sign fa-2x text-success mb-2"></i>
                            <h4 class="mb-0" id="totalRevenue">₱0.00</h4>
                            <small class="text-muted">Total Income</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle fa-2x text-primary mb-2"></i>
                            <h4 class="mb-0" id="completedRevenue">₱0.00</h4>
                            <small class="text-muted">Completed Payments</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                            <h4 class="mb-0" id="pendingRevenue">₱0.00</h4>
                            <small class="text-muted">Pending Payments</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-receipt fa-2x text-info mb-2"></i>
                            <h4 class="mb-0" id="transactionCount">0</h4>
                            <small class="text-muted">Transactions</small>
                        </div>
                    </div>
                </div>
            </div>

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

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Monthly Transaction Summary</h5>
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
                            <tbody id="reportTableBody">
                                <tr>
                                    <td colspan="5" class="text-center text-muted">Loading report data...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="/IECEP-LSC-MEMSYS/public/assets/js/charts.js"></script>

<?php include '../../includes/footer.php'; ?>
