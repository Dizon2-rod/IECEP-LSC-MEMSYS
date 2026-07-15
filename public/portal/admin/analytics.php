<?php
require_once __DIR__ . '/../auth_check.php';
require_role(['admin', 'super_admin']);

require_once __DIR__ . '/../../includes/db.php';

$db = Database::getInstance();

// Get date range
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Get member statistics
$memberStats = [
    'total' => $db->fetchOne("SELECT COUNT(*) as count FROM members")['count'],
    'active' => $db->fetchOne("SELECT COUNT(*) as count FROM members WHERE membership_status = 'active'")['count'],
    'new_this_month' => $db->fetchOne("SELECT COUNT(*) as count FROM members WHERE created_at >= ?", [date('Y-m-01')])['count']
];

// Get financial statistics
$financialStats = [
    'total_collected' => $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE status = 'completed' AND transaction_date BETWEEN ? AND ?", [$startDate, $endDate])['total'],
    'pending_amount' => $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE status = 'pending' AND transaction_date BETWEEN ? AND ?", [$startDate, $endDate])['total'],
    'transaction_count' => $db->fetchOne("SELECT COUNT(*) as count FROM transactions WHERE transaction_date BETWEEN ? AND ?", [$startDate, $endDate])['count']
];

// Get event statistics
$eventStats = [
    'total_events' => $db->fetchOne("SELECT COUNT(*) as count FROM events WHERE start_date BETWEEN ? AND ?", [$startDate, $endDate])['count'],
    'total_attendance' => $db->fetchOne("SELECT COUNT(*) as count FROM event_attendees ea JOIN events e ON ea.event_id = e.id WHERE e.start_date BETWEEN ? AND ?", [$startDate, $endDate])['count']
];

// Get compliance statistics
$complianceStats = $db->fetchAll("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN compliance_status = 'compliant' THEN 1 ELSE 0 END) as compliant,
    SUM(CASE WHEN compliance_status = 'at_risk' THEN 1 ELSE 0 END) as at_risk,
    SUM(CASE WHEN compliance_status = 'non_compliant' THEN 1 ELSE 0 END) as non_compliant
    FROM compliance_scores
    WHERE year = ?", [date('Y')]);

$complianceStats = $complianceStats[0] ?? ['total' => 0, 'compliant' => 0, 'at_risk' => 0, 'non_compliant' => 0];

// Monthly trend data
$monthlyTrend = $db->fetchAll("SELECT 
    DATE_FORMAT(transaction_date, '%Y-%m') as month,
    COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), 0) as revenue,
    COUNT(*) as transactions
    FROM transactions
    WHERE transaction_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
    ORDER BY month");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/professional.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/font-awesome.css">
    <style>
        .date-filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            align-items: flex-end;
        }
        .filter-group {
            flex: 1;
        }
        .filter-group label {
            display: block;
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-medium);
            color: var(--gray-600);
            margin-bottom: 0.5rem;
        }
        .filter-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }
        .stat-card h3 {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-navy);
        }
        .stat-trend {
            font-size: var(--font-size-sm);
            margin-top: 0.5rem;
        }
        .stat-trend.positive { color: var(--success); }
        .stat-trend.negative { color: var(--error); }
        .chart-container {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
        }
        .chart-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-navy);
            margin-bottom: 1.5rem;
        }
        .simple-chart {
            display: flex;
            align-items: flex-end;
            gap: 0.5rem;
            height: 200px;
            padding-top: 1rem;
        }
        .chart-bar {
            flex: 1;
            background: var(--primary-navy);
            border-radius: var(--radius-md) var(--radius-md) 0 0;
            position: relative;
            transition: height 0.3s;
        }
        .chart-bar:hover {
            background: var(--accent-gold);
        }
        .chart-bar-label {
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: var(--font-size-xs);
            color: var(--gray-600);
        }
        .chart-bar-value {
            position: absolute;
            top: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-semibold);
        }
        .compliance-breakdown {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }
        .compliance-item {
            text-align: center;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
        }
        .compliance-item.compliant { background: var(--success-light); }
        .compliance-item.at_risk { background: var(--warning-light); }
        .compliance-item.non_compliant { background: var(--error-light); }
        .compliance-value {
            font-size: 2rem;
            font-weight: 700;
        }
        .compliance-item.compliant .compliance-value { color: var(--success-dark); }
        .compliance-item.at_risk .compliance-value { color: var(--warning-dark); }
        .compliance-item.non_compliant .compliance-value { color: var(--error-dark); }
    </style>
</head>
<body>
    <div class="container">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>Analytics Dashboard</h1>
                    <p class="text-gray">Overview of chapter performance metrics</p>
                </div>
                <button onclick="exportReport()" class="btn btn-secondary">
                    <i class="fas fa-download"></i> Export Report
                </button>
            </div>

            <div class="date-filters">
                <div class="filter-group">
                    <label>Start Date</label>
                    <input type="date" id="startDate" value="<?php echo htmlspecialchars($startDate); ?>">
                </div>
                <div class="filter-group">
                    <label>End Date</label>
                    <input type="date" id="endDate" value="<?php echo htmlspecialchars($endDate); ?>">
                </div>
                <div class="filter-group">
                    <button onclick="applyFilters()" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Members</h3>
                    <div class="stat-value"><?php echo number_format($memberStats['total']); ?></div>
                    <div class="stat-trend positive">
                        <i class="fas fa-arrow-up"></i> +<?php echo $memberStats['new_this_month']; ?> this month
                    </div>
                </div>
                <div class="stat-card">
                    <h3>Active Members</h3>
                    <div class="stat-value"><?php echo number_format($memberStats['active']); ?></div>
                    <div class="stat-trend">
                        <?php echo $memberStats['total'] > 0 ? round(($memberStats['active'] / $memberStats['total']) * 100, 1) : 0; ?>% of total
                    </div>
                </div>
                <div class="stat-card">
                    <h3>Revenue (Period)</h3>
                    <div class="stat-value">₱<?php echo number_format($financialStats['total_collected'], 2); ?></div>
                    <div class="stat-trend">
                        <?php echo $financialStats['transaction_count']; ?> transactions
                    </div>
                </div>
                <div class="stat-card">
                    <h3>Pending Collection</h3>
                    <div class="stat-value">₱<?php echo number_format($financialStats['pending_amount'], 2); ?></div>
                    <div class="stat-trend negative">
                        <i class="fas fa-exclamation-circle"></i> Requires attention
                    </div>
                </div>
            </div>

            <div class="chart-container">
                <h2 class="chart-title">Monthly Revenue Trend</h2>
                <div class="simple-chart">
                    <?php 
                    $maxRevenue = max(array_column($monthlyTrend, 'revenue')) ?: 1;
                    foreach ($monthlyTrend as $month): 
                        $height = ($month['revenue'] / $maxRevenue) * 100;
                        $monthLabel = date('M', strtotime($month['month'] . '-01'));
                    ?>
                    <div class="chart-bar" style="height: <?php echo $height; ?>%;">
                        <div class="chart-bar-value">₱<?php echo number_format($month['revenue'] / 1000, 0); ?>k</div>
                        <div class="chart-bar-label"><?php echo $monthLabel; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="chart-container">
                <h2 class="chart-title">Compliance Status</h2>
                <div class="compliance-breakdown">
                    <div class="compliance-item compliant">
                        <div class="compliance-value"><?php echo $complianceStats['compliant']; ?></div>
                        <div>Compliant</div>
                    </div>
                    <div class="compliance-item at_risk">
                        <div class="compliance-value"><?php echo $complianceStats['at_risk']; ?></div>
                        <div>At Risk</div>
                    </div>
                    <div class="compliance-item non_compliant">
                        <div class="compliance-value"><?php echo $complianceStats['non_compliant']; ?></div>
                        <div>Non-Compliant</div>
                    </div>
                </div>
            </div>

            <div class="chart-container">
                <h2 class="chart-title">Event Statistics</h2>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 2rem;">
                    <div style="text-align: center;">
                        <div class="stat-value"><?php echo $eventStats['total_events']; ?></div>
                        <div style="color: var(--gray-600);">Total Events</div>
                    </div>
                    <div style="text-align: center;">
                        <div class="stat-value"><?php echo $eventStats['total_attendance']; ?></div>
                        <div style="color: var(--gray-600);">Total Attendance</div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function applyFilters() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            
            const params = new URLSearchParams();
            if (startDate) params.set('start_date', startDate);
            if (endDate) params.set('end_date', endDate);
            
            window.location.href = '?' + params.toString();
        }

        function exportReport() {
            const data = {
                member_stats: <?php echo json_encode($memberStats); ?>,
                financial_stats: <?php echo json_encode($financialStats); ?>,
                event_stats: <?php echo json_encode($eventStats); ?>,
                compliance_stats: <?php echo json_encode($complianceStats); ?>,
                monthly_trend: <?php echo json_encode($monthlyTrend); ?>
            };
            
            const csv = [
                ['Analytics Report'],
                [''],
                ['Member Statistics'],
                ['Total Members', data.member_stats.total],
                ['Active Members', data.member_stats.active],
                ['New This Month', data.member_stats.new_this_month],
                [''],
                ['Financial Statistics'],
                ['Total Collected', data.financial_stats.total_collected],
                ['Pending Amount', data.financial_stats.pending_amount],
                ['Transaction Count', data.financial_stats.transaction_count],
                [''],
                ['Event Statistics'],
                ['Total Events', data.event_stats.total_events],
                ['Total Attendance', data.event_stats.total_attendance],
                [''],
                ['Compliance Statistics'],
                ['Compliant', data.compliance_stats.compliant],
                ['At Risk', data.compliance_stats.at_risk],
                ['Non-Compliant', data.compliance_stats.non_compliant]
            ].map(row => row.join(',')).join('\n');

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `analytics-report-${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>
