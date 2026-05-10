<?php
require_once __DIR__ . '/../auth_check.php';

// Allow eb_auditor
require_role(['eb_auditor']);

$user = get_user_info();
$role_display = get_role_display_name($user['role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor Dashboard - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="dashboard-header">
                <div class="header-content">
                    <div>
                        <h1>Auditor Dashboard</h1>
                        <p class="welcome-message">Welcome back, <?php echo htmlspecialchars($user['user_metadata']['full_name'] ?? $user['email']); ?> - <?php echo $role_display; ?></p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-outline">
                            <i class="fas fa-bell"></i>
                            <span class="badge">6</span>
                        </button>
                        <div class="user-menu">
                            <img src="<?php echo $user['user_metadata']['avatar_url'] ?? '/IECEP-LSC-MEMSYS/public/assets/images/default-avatar.png'; ?>" alt="User Avatar" class="user-avatar">
                            <span><?php echo htmlspecialchars($user['user_metadata']['full_name'] ?? 'User'); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <!-- Audit Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3 id="transactions-count">156</h3>
                            <p>Transactions</p>
                            <span class="stat-change positive">+24 this month</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;">
                            <i class="fas fa-check-double"></i>
                        </div>
                        <div class="stat-content">
                            <h3 id="verified-transactions-count">142</h3>
                            <p>Verified Transactions</p>
                            <span class="stat-change positive">91% verification rate</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(245, 166, 35, 0.1); color: var(--gold);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-content">
                            <h3>3</h3>
                            <p>Flagged Issues</p>
                            <span class="stat-change negative">Requires investigation</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(10, 47, 108, 0.1); color: var(--navy);">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3>98.5%</h3>
                            <p>System Integrity</p>
                            <span class="stat-change positive">+0.5% this month</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h2>Quick Actions</h2>
                    <div class="actions-grid">
                        <a href="/IECEP-LSC-MEMSYS/public/portal/auditor/verify-transactions.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-circle-check"></i>
                            </div>
                            <h3>Verify Transactions</h3>
                            <p>Review and verify transactions</p>
                        </a>
                        
                        <a href="/IECEP-LSC-MEMSYS/public/portal/auditor/audit-logs.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-history"></i>
                            </div>
                            <h3>Audit Logs</h3>
                            <p>View system audit history</p>
                        </a>
                        
                        <a href="/IECEP-LSC-MEMSYS/public/portal/auditor/discrepancies.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <h3>Flagged Discrepancies</h3>
                            <p>Investigate flagged issues</p>
                        </a>
                        
                        <a href="/IECEP-LSC-MEMSYS/public/portal/auditor/reports.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <h3>Audit Reports</h3>
                            <p>Generate audit reports</p>
                        </a>
                    </div>
                </div>

                <!-- Recent Audit Activity -->
                <div class="recent-activity">
                    <h2>Recent Audit Activity</h2>
                    <div class="activity-list">
                        <div class="activity-item">
                            <div class="activity-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;">
                                <i class="fas fa-circle-check"></i>
                            </div>
                            <div class="activity-content">
                                <p><strong>12 transactions</strong> verified successfully</p>
                                <span class="activity-time">1 hour ago</span>
                                <span class="verification-status">Verified</span>
                            </div>
                        </div>
                        
                        <div class="activity-item">
                            <div class="activity-icon" style="background: rgba(245, 166, 35, 0.1); color: var(--gold);">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="activity-content">
                                <p><strong>Discrepancy detected</strong> in membership fee transaction</p>
                                <span class="activity-time">3 hours ago</span>
                                <span class="verification-status flagged">Flagged</span>
                            </div>
                        </div>
                        
                        <div class="activity-item">
                            <div class="activity-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                                <i class="fas fa-link"></i>
                            </div>
                            <div class="activity-content">
                                <p><strong>New transaction entries</strong> from treasurer transactions</p>
                                <span class="activity-time">5 hours ago</span>
                                <span class="verification-status pending">Pending Review</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Audit Summary -->
                <div class="audit-summary">
                    <h2>Monthly Audit Summary</h2>
                    <div class="summary-grid">
                        <div class="summary-item">
                            <h3>Transactions Processed</h3>
                            <div class="summary-value">156</div>
                            <div class="summary-detail">+24 from last month</div>
                        </div>
                        <div class="summary-item">
                            <h3>Verification Rate</h3>
                            <div class="summary-value">91%</div>
                            <div class="summary-detail">Above target (85%)</div>
                        </div>
                        <div class="summary-item">
                            <h3>Issues Resolved</h3>
                            <div class="summary-value">8</div>
                            <div class="summary-detail">All critical issues resolved</div>
                        </div>
                        <div class="summary-item">
                            <h3>Compliance Score</h3>
                            <div class="summary-value">98.5%</div>
                            <div class="summary-detail">Excellent performance</div>
                        </div>
                    </div>
                </div>

                <!-- Real-Time Integration Script -->
                <script>
                // Auditor Dashboard Real-Time Updates
                document.addEventListener('DOMContentLoaded', function() {
                    // Listen for new transactions
                    window.addEventListener('realtime:transactions', function(event) {
                        const { action, new: newRecord } = event.detail;

                        if (action === 'INSERT') {
                            // New transaction added - update counters
                            updateTransactionsCount();
                        }
                    });

                    // Listen for new blockchain records (for audit trail)
                    window.addEventListener('realtime:blockchain_records', function(event) {
                        const { action, new: newRecord } = event.detail;

                        if (action === 'INSERT') {
                            // New blockchain record - could indicate new auditable activity
                            console.log('New blockchain record for audit:', newRecord);
                        }
                    });
                });

                function updateTransactionsCount() {
                    const countElement = document.getElementById('transactions-count');
                    if (countElement) {
                        const currentCount = parseInt(countElement.textContent.replace(/[^\d]/g, '')) || 0;
                        countElement.textContent = currentCount + 1;

                        // Highlight the element to show update
                        countElement.classList.add('highlight');
                        setTimeout(() => countElement.classList.remove('highlight'), 1000);
                    }
                }

                // Override default real-time handlers for auditor-specific behavior
                window.onNewTransaction = function(newTransaction) {
                    updateTransactionsCount();
                    console.log('New transaction for audit verification:', newTransaction);
                };

                window.onNewBlockchainRecord = function(newRecord) {
                    // Auditor might want to know about new blockchain records for integrity verification
                    console.log('Blockchain record added - audit trail updated:', newRecord);
                };
                </script>
            </div>
        </main>
    </div>
</body>
</html>
            </div>
        </main>
    </div>

    <script src="/IECEP-LSC-MEMSYS/public/js/dashboard.js"></script>
</body>
</html>
