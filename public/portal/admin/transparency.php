<?php
if (!isset($current_page)) { $current_page = basename(__FILE__, '.php'); }
require_once __DIR__ . '/../auth_check.php';
require_role(['admin', 'super_admin', 'eb_treasurer', 'eb_auditor']);

require_once __DIR__ . '/../../includes/db.php';

$db = Database::getInstance();

// Get comprehensive financial data
$currentYear = date('Y');
$currentMonth = date('Y-m');

// Monthly breakdown
$monthlyData = $db->fetchAll("SELECT 
    DATE_FORMAT(transaction_date, '%Y-%m') as month,
    COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), 0) as collected,
    COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) as pending,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count
    FROM transactions
    WHERE transaction_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
    ORDER BY month DESC");

// Institution breakdown
$institutionData = $db->fetchAll("SELECT 
    i.id, i.name, i.acronym,
    COALESCE(SUM(CASE WHEN t.status = 'completed' THEN t.amount ELSE 0 END), 0) as collected,
    COALESCE(SUM(CASE WHEN t.status = 'pending' THEN t.amount ELSE 0 END), 0) as pending,
    COUNT(DISTINCT CASE WHEN t.status = 'completed' THEN t.id END) as transactions
    FROM institutions i
    LEFT JOIN transactions t ON i.id = t.institution_id AND t.transaction_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
    GROUP BY i.id, i.name, i.acronym
    ORDER BY collected DESC");

// Recent audit trail
$auditTrail = $db->fetchAll("SELECT al.*, up.full_name as user_name
    FROM audit_logs al
    LEFT JOIN user_profiles up ON al.user_id = up.user_id
    WHERE al.action IN ('CREATE_TRANSACTION', 'UPDATE_TRANSACTION', 'VOID_TRANSACTION', 'CREATE_INVOICE')
    ORDER BY al.created_at DESC
    LIMIT 20");

// Blockchain verification status
$blockchainStats = $db->fetchOne("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN confirmed = 1 THEN 1 ELSE 0 END) as confirmed
    FROM blockchain_records
    WHERE entity_type IN ('transaction', 'invoice')");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transparency Dashboard - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/professional.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/font-awesome.css">
    <style>
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .summary-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }
        .summary-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-navy);
        }
        .summary-label {
            font-size: var(--font-size-sm);
            color: var(--gray-600);
            margin-top: 0.5rem;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        .data-table th {
            background: var(--primary-navy);
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: var(--font-weight-semibold);
        }
        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }
        .blockchain-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
        }
        .section {
            margin-bottom: 2rem;
        }
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary-navy);
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>Transparency Dashboard</h1>
                    <p class="text-gray">Admin view of financial activities with audit trail</p>
                </div>
                <button onclick="window.open('<?php echo BASE_URL; ?>/transparency.php', '_blank')" class="btn btn-secondary">
                    <i class="fas fa-external-link-alt"></i> View Public Report
                </button>
            </div>

            <div class="summary-cards">
                <div class="summary-card">
                    <div class="summary-value">₱<?php echo number_format(array_sum(array_column($monthlyData, 'collected')), 2); ?></div>
                    <div class="summary-label">Total Collected (12mo)</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value">₱<?php echo number_format(array_sum(array_column($monthlyData, 'pending')), 2); ?></div>
                    <div class="summary-label">Pending Collection</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value"><?php echo $blockchainStats['confirmed']; ?> / <?php echo $blockchainStats['total']; ?></div>
                    <div class="summary-label">Blockchain Verified</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value"><?php echo count($auditTrail); ?></div>
                    <div class="summary-label">Recent Audit Logs</div>
                </div>
            </div>

            <div class="section">
                <h2 class="section-title">Monthly Financial Breakdown</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Collected</th>
                            <th>Pending</th>
                            <th>Completed Tx</th>
                            <th>Pending Tx</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthlyData as $month): ?>
                        <tr>
                            <td><?php echo date('F Y', strtotime($month['month'] . '-01')); ?></td>
                            <td>₱<?php echo number_format($month['collected'], 2); ?></td>
                            <td>₱<?php echo number_format($month['pending'], 2); ?></td>
                            <td><?php echo number_format($month['completed_count']); ?></td>
                            <td><?php echo number_format($month['pending_count']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="section">
                <h2 class="section-title">Institution Breakdown</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Institution</th>
                            <th>Collected</th>
                            <th>Pending</th>
                            <th>Transactions</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($institutionData as $inst): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($inst['acronym'] ?: $inst['name']); ?></td>
                            <td>₱<?php echo number_format($inst['collected'], 2); ?></td>
                            <td>₱<?php echo number_format($inst['pending'], 2); ?></td>
                            <td><?php echo number_format($inst['transactions']); ?></td>
                            <td>
                                <?php if ($inst['pending'] > 0): ?>
                                    <span style="color: var(--warning);">Has Pending</span>
                                <?php else: ?>
                                    <span style="color: var(--success);">All Paid</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="section">
                <h2 class="section-title">Recent Audit Trail</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Entity</th>
                            <th>Blockchain</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditTrail as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                            <td><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></td>
                            <td><?php echo htmlspecialchars(str_replace('_', ' ', $log['action'])); ?></td>
                            <td><?php echo htmlspecialchars(substr($log['affected_entity_id'] ?? '', 0, 20)); ?></td>
                            <td>
                                <?php 
                                $hasBlockchain = $db->fetchOne("SELECT COUNT(*) as count FROM blockchain_records WHERE entity_id = ?", [$log['affected_entity_id']]);
                                if ($hasBlockchain['count'] > 0): ?>
                                    <span class="blockchain-badge"><i class="fas fa-check"></i> Verified</span>
                                <?php else: ?>
                                    <span style="color: var(--gray-500);">Not Verified</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
