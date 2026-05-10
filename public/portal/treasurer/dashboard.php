<?php
require_once __DIR__ . '/../auth_check.php';

// Allow eb_treasurer
require_role(['eb_treasurer']);

$user = get_user_info();
$role_display = get_role_display_name($user['role']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Treasurer Dashboard - IECEP-LSC MEMSYS</title>
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
                        <h1>Treasurer Dashboard</h1>
                        <p class="welcome-message">Welcome back, <?php echo htmlspecialchars($user['user_metadata']['full_name'] ?? $user['email']); ?> - <?php echo $role_display; ?></p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-outline">
                            <i class="fas fa-bell"></i>
                            <span class="badge">4</span>
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
                <!-- Financial Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="stat-content">
                            <h3 id="total-balance">₱458,750</h3>
                            <p>Total Balance</p>
                            <span class="stat-change positive">+12% this month</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(245, 166, 35, 0.1); color: var(--gold);">
                            <i class="fas fa-arrow-down"></i>
                        </div>
                        <div class="stat-content">
                            <h3>₱125,400</h3>
                            <p>Collected This Month</p>
                            <span class="stat-change positive">+8% from last month</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                        <div class="stat-content">
                            <h3>₱32,150</h3>
                            <p>Expenses This Month</p>
                            <span class="stat-change negative">+15% from last month</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(10, 47, 108, 0.1); color: var(--navy);">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div class="stat-content">
                            <h3 id="transaction-count">156</h3>
                            <p>Transactions</p>
                            <span class="stat-change positive">+24 this month</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h2>Quick Actions</h2>
                    <div class="actions-grid">
                        <a href="/IECEP-LSC-MEMSYS/public/portal/treasurer/collect-fees.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <h3>Collect Fees</h3>
                            <p>Process membership and event fees</p>
                        </a>
                        
                        <a href="/IECEP-LSC-MEMSYS/public/portal/treasurer/transactions.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-exchange-alt"></i>
                            </div>
                            <h3>Manage Transactions</h3>
                            <p>View and manage all transactions</p>
                        </a>
                        
                        <a href="/IECEP-LSC-MEMSYS/public/portal/treasurer/reports.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <h3>Financial Reports</h3>
                            <p>Generate financial reports</p>
                        </a>
                        
                        <a href="/IECEP-LSC-MEMSYS/public/portal/marketing/dashboard.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <h3>Marketing Committee</h3>
                            <p>Manage merchandise and campaigns</p>
                        </a>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <div class="recent-activity">
                    <h2>Recent Transactions</h2>
                    <div class="activity-list">
                        <div class="activity-item">
                            <div class="activity-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;">
                                <i class="fas fa-arrow-down"></i>
                            </div>
                            <div class="activity-content">
                                <p><strong>Membership fees</strong> collected from 45 members</p>
                                <span class="activity-time">2 hours ago</span>
                                <span class="amount">₱22,500</span>
                            </div>
                        </div>
                        
                        <div class="activity-item">
                            <div class="activity-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                                <i class="fas fa-arrow-up"></i>
                            </div>
                            <div class="activity-content">
                                <p><strong>Event expenses</strong> for National Conference</p>
                                <span class="activity-time">5 hours ago</span>
                                <span class="amount">₱15,000</span>
                            </div>
                        </div>
                        
                        <div class="activity-item">
                            <div class="activity-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                                <i class="fas fa-link"></i>
                            </div>
                            <div class="activity-content">
                                <p><strong>Transaction</strong> verified</p>
                                <span class="activity-time">1 day ago</span>
                                <span class="amount">₱8,750</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Real-Time Integration Script -->
            <script>
            // Treasurer Dashboard Real-Time Updates
            document.addEventListener('DOMContentLoaded', function() {
                // Listen for new transactions
                window.addEventListener('realtime:transactions', function(event) {
                    const { action, new: newRecord } = event.detail;

                    if (action === 'INSERT') {
                        // New transaction added - update counters
                        updateTransactionCount();
                        updateTotalBalance(newRecord.amount);
                    }
                });
            });

            function updateTransactionCount() {
                const countElement = document.getElementById('transaction-count');
                if (countElement) {
                    const currentCount = parseInt(countElement.textContent.replace(/[^\d]/g, '')) || 0;
                    countElement.textContent = currentCount + 1;

                    // Highlight the element to show update
                    countElement.classList.add('highlight');
                    setTimeout(() => countElement.classList.remove('highlight'), 1000);
                }
            }

            function updateTotalBalance(amount) {
                const balanceElement = document.getElementById('total-balance');
                if (balanceElement && amount) {
                    const currentBalance = parseFloat(balanceElement.textContent.replace(/[^\d.,]/g, '').replace(',', '')) || 0;
                    const newBalance = currentBalance + parseFloat(amount);
                    balanceElement.textContent = '₱' + newBalance.toLocaleString('en-PH', { minimumFractionDigits: 0 });

                    // Highlight the element to show update
                    balanceElement.classList.add('highlight');
                    setTimeout(() => balanceElement.classList.remove('highlight'), 1000);
                }
            }

            // Override default real-time handlers for treasurer-specific behavior
            window.onNewTransaction = function(newTransaction) {
                updateTransactionCount();
                updateTotalBalance(newTransaction.amount);
                console.log('New transaction:', newTransaction);
            };
            </script>
        </main>
    </div>
</body>
</html>
        </main>
    </div>

    <script src="/IECEP-LSC-MEMSYS/public/js/dashboard.js"></script>
</body>
</html>
