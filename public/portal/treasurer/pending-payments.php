<?php
/**
 * Treasurer: Pending Payments Review
 * GET /portal/treasurer/pending-payments.php
 * 
 * Display list of pending affiliation payment verifications.
 * Restricted to treasurer, admin, and super_admin roles.
 * 
 * SOURCE: Deliverable 3.4 - Pending Payments Portal
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Load authentication and config
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../../src/lib/SupabaseClient.php';

// Verify role: treasurer, admin, or super_admin
require_role(['treasurer', 'admin', 'super_admin']);

// Get Supabase client
$config = require __DIR__ . '/../../includes/supabase.php';
$supabase = new SupabaseClient($config['url'], $config['anon_key']);

// Fetch pending payments
$pendingPayments = [];
try {
    $result = $supabase->from('pending_affiliations')
        ->select('*, institutions(name, contact_email)')
        ->eq('payment_status', 'pending_verification')
        ->order('created_at', 'desc')
        ->get();
    
    $pendingPayments = $result ?? [];
} catch (Exception $e) {
    error_log('Error fetching pending payments: ' . $e->getMessage());
}

$pageTitle = 'Pending Payment Verifications';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> – IECEP-LSC</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/dashboard.css">
    <style>
        :root {
            --navy: #0A2F6C;
            --gold: #F5A623;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }
        
        .dashboard-container { display: flex; min-height: 100vh; }
        .main-content { flex: 1; padding: 2rem; background: #f8fafc; }
        
        .disclaimer-banner {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 2rem;
            color: #92400e;
            display: flex;
            gap: 1rem;
        }
        
        .disclaimer-banner i { flex-shrink: 0; margin-top: 2px; }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--navy) 0%, #1e3a6e 100%);
            color: white;
            padding: 1.5rem;
            border-bottom: 2px solid var(--gold);
        }
        
        .card-header h2 { margin: 0; font-size: 1.25rem; }
        
        .table-wrapper {
            overflow-x: auto;
            padding: 1.5rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        
        th {
            background: #f1f5f9;
            border-bottom: 2px solid #e2e8f0;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--navy);
        }
        
        td {
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem;
        }
        
        tr:hover { background: #f8fafc; }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: var(--navy);
            color: white;
        }
        
        .btn-primary:hover {
            background: #1e3a6e;
        }
        
        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.875rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div style="max-width: 1200px;">
                <div style="margin-bottom: 2rem;">
                    <h1 style="margin: 0 0 0.5rem 0; color: var(--navy); font-size: 1.875rem;">
                        <i class="fas fa-receipt"></i> Pending Payment Verifications
                    </h1>
                    <p style="margin: 0; color: #64748b;">Review and verify affiliation payment simulations</p>
                </div>
                
                <!-- Disclaimer Banner -->
                <div class="disclaimer-banner">
                    <i class="fas fa-triangle-exclamation"></i>
                    <div>
                        <strong>Simulation Only:</strong> All GCash entries displayed here are simulated transactions. No real money has been transferred. These are for testing and verification purposes only.
                    </div>
                </div>
                
                <!-- Pending Payments Card -->
                <div class="card">
                    <div class="card-header">
                        <h2>Pending Verifications</h2>
                    </div>
                    
                    <div class="table-wrapper">
                        <?php if (empty($pendingPayments)): ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <h3>No Pending Verifications</h3>
                                <p>All affiliation payments have been processed.</p>
                            </div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Institution Name</th>
                                        <th>Contact Email</th>
                                        <th>Total Fee</th>
                                        <th>Receipt Number</th>
                                        <th>Submitted</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingPayments as $payment): ?>
                                    <tr>
                                        <td style="font-weight: 600; color: var(--navy);">
                                            <?php echo htmlspecialchars($payment['institution_name'] ?? ''); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($payment['rep_email'] ?? ''); ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <strong>₱<?php echo number_format($payment['total_fee'] ?? 0, 2); ?></strong>
                                        </td>
                                        <td>
                                            <code style="background: #f1f5f9; padding: 0.25rem 0.5rem; border-radius: 4px;">
                                                <?php echo htmlspecialchars($payment['receipt_number'] ?? 'N/A'); ?>
                                            </code>
                                        </td>
                                        <td>
                                            <?php 
                                                if (isset($payment['submitted_at'])) {
                                                    echo htmlspecialchars(date('M d, Y H:i', strtotime($payment['submitted_at'])));
                                                }
                                            ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-primary btn-sm" 
                                                    onclick="verifyPayment('<?php echo htmlspecialchars($payment['id']); ?>', '<?php echo htmlspecialchars($payment['institution_name'] ?? ''); ?>')">
                                                <i class="fas fa-check-circle"></i> Verify Payment
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        /**
         * Verify payment via AJAX
         */
        async function verifyPayment(pendingId, institutionName) {
            if (!confirm(`Verify payment for ${institutionName}?`)) {
                return;
            }

            const btn = event.target.closest('button');
            btn.disabled = true;
            btn.innerHTML = '<span class="loading-spinner"></span> Verifying...';

            try {
                const response = await fetch('/IECEP-LSC-MEMSYS/public/api/treasurer/verify-payment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        pending_affiliation_id: pendingId,
                        csrf_token: '<?php echo csrf_field_value(); ?>'
                    })
                });

                const data = await response.json();

                if (data.success) {
                    alert('Payment verified successfully. The application is now pending document review.');
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check-circle"></i> Verify Payment';
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Network error. Please try again.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check-circle"></i> Verify Payment';
            }
        }
    </script>
</body>
</html>
