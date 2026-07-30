<?php
if (!isset($current_page)) { $current_page = basename(__FILE__, '.php'); }
require_once __DIR__ . '/../auth_check.php';
require_role(['admin', 'super_admin', 'eb_officer']);

require_once __DIR__ . '/../../../includes/db.php';

$db = Database::getInstance();

// Get decision insights
$insights = [];

// Insight 1: Membership growth trend
$membershipGrowth = $db->fetchAll("SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    COUNT(*) as new_members
    FROM members
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month");

$insights['membership_trend'] = [
    'title' => 'Membership Growth Trend',
    'description' => 'Track new member registrations over the last 6 months',
    'data' => $membershipGrowth,
    'recommendation' => count($membershipGrowth) > 0 && end($membershipGrowth)['new_members'] > $membershipGrowth[0]['new_members'] 
        ? 'Membership is growing. Consider increasing event capacity.' 
        : 'Membership growth is declining. Consider recruitment initiatives.'
];

// Insight 2: Financial health
$financialHealth = [
    'total_collected' => $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE status = 'completed' AND YEAR(transaction_date) = YEAR(NOW())")['total'],
    'total_pending' => $db->fetchOne("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE status = 'pending' AND YEAR(transaction_date) = YEAR(NOW())")['total'],
    'collection_rate' => 0
];

$totalExpected = $financialHealth['total_collected'] + $financialHealth['total_pending'];
$financialHealth['collection_rate'] = $totalExpected > 0 ? round(($financialHealth['total_collected'] / $totalExpected) * 100, 1) : 0;

$insights['financial_health'] = [
    'title' => 'Financial Health',
    'description' => 'Current year financial collection status',
    'data' => $financialHealth,
    'recommendation' => $financialHealth['collection_rate'] < 80 
        ? 'Collection rate is below 80%. Follow up on pending payments.' 
        : 'Financial health is good. Continue current collection practices.'
];

// Insight 3: Event participation
$eventParticipation = $db->fetchAll("SELECT 
    e.id,
    e.title,
    e.start_date,
    COUNT(DISTINCT ea.member_id) as attendance,
    (SELECT COUNT(*) FROM members WHERE membership_status = 'active') as total_active_members
    FROM events e
    LEFT JOIN event_attendees ea ON e.id = ea.event_id
    WHERE e.start_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
    GROUP BY e.id, e.title, e.start_date
    ORDER BY e.start_date DESC
    LIMIT 10");

foreach ($eventParticipation as &$event) {
    $event['participation_rate'] = $event['total_active_members'] > 0 
        ? round(($event['attendance'] / $event['total_active_members']) * 100, 1) 
        : 0;
}

$insights['event_participation'] = [
    'title' => 'Event Participation',
    'description' => 'Recent event attendance rates',
    'data' => $eventParticipation,
    'recommendation' => !empty($eventParticipation) && $eventParticipation[0]['participation_rate'] < 50 
        ? 'Recent event participation is low. Consider improving event promotion.' 
        : 'Event participation is healthy.'
];

// Insight 4: Compliance risks
$complianceRisks = $db->fetchAll("SELECT 
    i.name as institution_name,
    cs.compliance_score,
    cs.compliance_status,
    cs.last_checked
    FROM compliance_scores cs
    JOIN institutions i ON cs.institution_id = i.id
    WHERE cs.compliance_status IN ('at_risk', 'non_compliant')
    AND cs.year = YEAR(NOW())
    ORDER BY cs.compliance_score ASC");

$insights['compliance_risks'] = [
    'title' => 'Compliance Risks',
    'description' => 'Institutions requiring attention',
    'data' => $complianceRisks,
    'recommendation' => !empty($complianceRisks) 
        ? count($complianceRisks) . ' institution(s) need compliance intervention.' 
        : 'All institutions are compliant.'
];

// Insight 5: Pending renewals
$pendingRenewals = $db->fetchAll("SELECT 
    i.name as institution_name,
    COUNT(*) as pending_count
    FROM members m
    JOIN institutions i ON m.institution_id = i.id
    WHERE m.membership_expiry BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)
    AND m.membership_status = 'active'
    GROUP BY i.id, i.name
    ORDER BY pending_count DESC");

$insights['pending_renewals'] = [
    'title' => 'Pending Renewals',
    'description' => 'Memberships expiring within 30 days',
    'data' => $pendingRenewals,
    'recommendation' => !empty($pendingRenewals) 
        ? array_sum(array_column($pendingRenewals, 'pending_count')) . ' members need renewal reminders.' 
        : 'No immediate renewals pending.'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Decision Support - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/professional.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/font-awesome.css">
    <style>
        .insights-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
        }
        .insight-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
        }
        .insight-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        .insight-icon {
            font-size: 1.5rem;
            color: var(--primary-navy);
        }
        .insight-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--primary-navy);
        }
        .insight-description {
            font-size: var(--font-size-sm);
            color: var(--gray-600);
            margin-bottom: 1rem;
        }
        .insight-data {
            background: var(--gray-50);
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
        }
        .data-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--gray-200);
        }
        .data-row:last-child {
            border-bottom: none;
        }
        .data-label {
            color: var(--gray-600);
        }
        .data-value {
            font-weight: var(--font-weight-semibold);
        }
        .recommendation {
            padding: 1rem;
            border-radius: var(--radius-md);
            border-left: 4px solid var(--accent-gold);
            background: linear-gradient(135deg, #fffbeb 0%, #ffffff 100%);
        }
        .recommendation-title {
            font-weight: var(--font-weight-semibold);
            color: var(--primary-navy);
            margin-bottom: 0.5rem;
        }
        .recommendation-text {
            font-size: var(--font-size-sm);
            color: var(--gray-700);
        }
        .priority-high { border-left-color: var(--error); background: linear-gradient(135deg, #fef2f2 0%, #ffffff 100%); }
        .priority-medium { border-left-color: var(--warning); background: linear-gradient(135deg, #fffbeb 0%, #ffffff 100%); }
        .priority-low { border-left-color: var(--success); background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%); }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: var(--font-size-sm);
        }
        .data-table th {
            background: var(--gray-100);
            padding: 0.5rem;
            text-align: left;
            font-weight: var(--font-weight-semibold);
        }
        .data-table td {
            padding: 0.5rem;
            border-bottom: 1px solid var(--gray-200);
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>Decision Support</h1>
                    <p class="text-gray">Data-driven insights for chapter management</p>
                </div>
                <button onclick="refreshInsights()" class="btn btn-secondary">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>

            <div class="insights-grid">
                <!-- Membership Trend -->
                <div class="insight-card">
                    <div class="insight-header">
                        <i class="fas fa-users insight-icon"></i>
                        <div class="insight-title"><?php echo $insights['membership_trend']['title']; ?></div>
                    </div>
                    <div class="insight-description"><?php echo $insights['membership_trend']['description']; ?></div>
                    <div class="insight-data">
                        <?php if (!empty($insights['membership_trend']['data'])): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>New Members</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($insights['membership_trend']['data'] as $row): ?>
                                    <tr>
                                        <td><?php echo date('M Y', strtotime($row['month'] . '-01')); ?></td>
                                        <td><?php echo $row['new_members']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div style="text-align: center; color: var(--gray-600);">No data available</div>
                        <?php endif; ?>
                    </div>
                    <div class="recommendation <?php echo strpos($insights['membership_trend']['recommendation'], 'growing') !== false ? 'priority-low' : 'priority-medium'; ?>">
                        <div class="recommendation-title">Recommendation</div>
                        <div class="recommendation-text"><?php echo $insights['membership_trend']['recommendation']; ?></div>
                    </div>
                </div>

                <!-- Financial Health -->
                <div class="insight-card">
                    <div class="insight-header">
                        <i class="fas fa-chart-line insight-icon"></i>
                        <div class="insight-title"><?php echo $insights['financial_health']['title']; ?></div>
                    </div>
                    <div class="insight-description"><?php echo $insights['financial_health']['description']; ?></div>
                    <div class="insight-data">
                        <div class="data-row">
                            <span class="data-label">Total Collected</span>
                            <span class="data-value">₱<?php echo number_format($insights['financial_health']['data']['total_collected'], 2); ?></span>
                        </div>
                        <div class="data-row">
                            <span class="data-label">Total Pending</span>
                            <span class="data-value">₱<?php echo number_format($insights['financial_health']['data']['total_pending'], 2); ?></span>
                        </div>
                        <div class="data-row">
                            <span class="data-label">Collection Rate</span>
                            <span class="data-value"><?php echo $insights['financial_health']['data']['collection_rate']; ?>%</span>
                        </div>
                    </div>
                    <div class="recommendation <?php echo $insights['financial_health']['data']['collection_rate'] < 80 ? 'priority-high' : 'priority-low'; ?>">
                        <div class="recommendation-title">Recommendation</div>
                        <div class="recommendation-text"><?php echo $insights['financial_health']['recommendation']; ?></div>
                    </div>
                </div>

                <!-- Event Participation -->
                <div class="insight-card">
                    <div class="insight-header">
                        <i class="fas fa-calendar-check insight-icon"></i>
                        <div class="insight-title"><?php echo $insights['event_participation']['title']; ?></div>
                    </div>
                    <div class="insight-description"><?php echo $insights['event_participation']['description']; ?></div>
                    <div class="insight-data">
                        <?php if (!empty($insights['event_participation']['data'])): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>Attendance</th>
                                        <th>Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($insights['event_participation']['data'], 0, 5) as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(substr($row['title'], 0, 20)); ?>...</td>
                                        <td><?php echo $row['attendance']; ?></td>
                                        <td><?php echo $row['participation_rate']; ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div style="text-align: center; color: var(--gray-600);">No recent events</div>
                        <?php endif; ?>
                    </div>
                    <div class="recommendation <?php echo !empty($insights['event_participation']['data']) && $insights['event_participation']['data'][0]['participation_rate'] < 50 ? 'priority-medium' : 'priority-low'; ?>">
                        <div class="recommendation-title">Recommendation</div>
                        <div class="recommendation-text"><?php echo $insights['event_participation']['recommendation']; ?></div>
                    </div>
                </div>

                <!-- Compliance Risks -->
                <div class="insight-card">
                    <div class="insight-header">
                        <i class="fas fa-exclamation-triangle insight-icon"></i>
                        <div class="insight-title"><?php echo $insights['compliance_risks']['title']; ?></div>
                    </div>
                    <div class="insight-description"><?php echo $insights['compliance_risks']['description']; ?></div>
                    <div class="insight-data">
                        <?php if (!empty($insights['compliance_risks']['data'])): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Institution</th>
                                        <th>Score</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($insights['compliance_risks']['data'] as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['institution_name']); ?></td>
                                        <td><?php echo $row['compliance_score']; ?></td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $row['compliance_status'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div style="text-align: center; color: var(--gray-600);">No compliance issues</div>
                        <?php endif; ?>
                    </div>
                    <div class="recommendation <?php echo !empty($insights['compliance_risks']['data']) ? 'priority-high' : 'priority-low'; ?>">
                        <div class="recommendation-title">Recommendation</div>
                        <div class="recommendation-text"><?php echo $insights['compliance_risks']['recommendation']; ?></div>
                    </div>
                </div>

                <!-- Pending Renewals -->
                <div class="insight-card">
                    <div class="insight-header">
                        <i class="fas fa-clock insight-icon"></i>
                        <div class="insight-title"><?php echo $insights['pending_renewals']['title']; ?></div>
                    </div>
                    <div class="insight-description"><?php echo $insights['pending_renewals']['description']; ?></div>
                    <div class="insight-data">
                        <?php if (!empty($insights['pending_renewals']['data'])): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Institution</th>
                                        <th>Pending</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($insights['pending_renewals']['data'], 0, 5) as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['institution_name']); ?></td>
                                        <td><?php echo $row['pending_count']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div style="text-align: center; color: var(--gray-600);">No pending renewals</div>
                        <?php endif; ?>
                    </div>
                    <div class="recommendation <?php echo !empty($insights['pending_renewals']['data']) ? 'priority-medium' : 'priority-low'; ?>">
                        <div class="recommendation-title">Recommendation</div>
                        <div class="recommendation-text"><?php echo $insights['pending_renewals']['recommendation']; ?></div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function refreshInsights() {
            location.reload();
        }
    </script>
</body>
</html>
