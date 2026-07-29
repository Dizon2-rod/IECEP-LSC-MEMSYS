<?php
require_once __DIR__ . '/../../auth_check.php';

require_once __DIR__ . '/../../bootstrap.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
define('BASE_PUBLIC_URL', '/IECEP-LSC-MEMSYS/public');

// Authentication check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_PUBLIC_URL . '/login.php');
    exit;
}

// Role check - only school officers can access this
if (($_SESSION['role'] ?? '') !== 'school_officer') {
    header('Location: ' . BASE_PUBLIC_URL . '/portal/member/dashboard.php');
    exit;
}

require_once __DIR__ . '/../../../includes/auth_check.php';
require_role(['school_officer']);

require_once __DIR__ . '/../../../includes/role-config.php';
require_once __DIR__ . '/../../../includes/config.php';

$current_page = 'financial-reports';

// Get institution ID from session
$institution_id = $_SESSION['institution_id'] ?? null;

if (!$institution_id) {
    // Try to get from user profile
    $user_id = $_SESSION['user']['id'] ?? null;
    if ($user_id) {
        $supabase = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);
        $userProfile = $supabase->select('user_profiles', [
            'user_id' => 'eq.' . $user_id,
            'limit' => 1,
        ]);
        if (!empty($userProfile)) {
            $institution_id = $userProfile[0]['institution_id'] ?? null;
        }
    }
}

if (!$institution_id) {
    die('Error: Institution ID not found in session. Please contact support.');
}

// Fetch financial data
$supabase = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);

// Get institution details
$institution = $supabase->select('institutions', [
    'id' => 'eq.' . $institution_id,
    'limit' => 1,
]);
$institution = $institution[0] ?? null;

// Get member count
$members = $supabase->select('members', [
    'institution_id' => 'eq.' . $institution_id,
]);
$member_count = count($members);

// Get fee bracket
$fee_bracket = $supabase->select('fee_brackets', [
    'min_members' => 'lte.' . $member_count,
    'max_members' => 'gte.' . $member_count,
    'limit' => 1,
]);
$fee_per_member = $fee_bracket[0]['fee_per_member'] ?? 0;
$total_annual_fee = $fee_per_member * $member_count;

// Get transactions
$transactions = $supabase->select('transactions', [
    'institution_id' => 'eq.' . $institution_id,
    'order' => 'created_at.desc',
]);

$total_paid = 0;
foreach ($transactions as $tx) {
    if ($tx['status'] === 'paid') {
        $total_paid += $tx['amount'] ?? 0;
    }
}

$balance_due = $total_annual_fee - $total_paid;

// Get event payments
$event_payments = $supabase->select('transactions', [
    'institution_id' => 'eq.' . $institution_id,
    'type' => 'eq.event_fee',
    'order' => 'created_at.desc',
]);

include_once __DIR__ . '/../../../includes/head-meta.php';
?>
<div class="main-content">
    <div class="dashboard-header">
        <div class="header-content">
            <div>
                <h1>Financial Reports</h1>
                <p class="welcome-message">View your institution's financial status and payment history</p>
            </div>
        </div>
    </div>

    <!-- Fee Summary Cards -->
    <div class="stats-grid mb-4">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $member_count; ?></h3>
                <p>Total Members</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-tag"></i>
            </div>
            <div class="stat-content">
                <h3>₱<?php echo number_format($fee_per_member); ?></h3>
                <p>Fee Per Member</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-coins"></i>
            </div>
            <div class="stat-content">
                <h3>₱<?php echo number_format($total_annual_fee); ?></h3>
                <p>Total Annual Fee</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <h3>₱<?php echo number_format($total_paid); ?></h3>
                <p>Amount Paid</p>
            </div>
        </div>
        <div class="stat-card <?php echo $balance_due > 0 ? 'stat-warning' : 'stat-success'; ?>">
            <div class="stat-icon">
                <i class="fas fa-balance-scale"></i>
            </div>
            <div class="stat-content">
                <h3>₱<?php echo number_format($balance_due); ?></h3>
                <p>Balance Due</p>
            </div>
        </div>
    </div>

    <!-- Transaction History -->
    <div class="content-card mb-4">
        <h2><i class="fas fa-history me-2"></i>Transaction History</h2>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Receipt #</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Blockchain</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="7" class="text-center">No transactions found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $tx): ?>
                            <tr>
                                <td><?php echo date('M d, Y g:i A', strtotime($tx['created_at'] ?? 'now')); ?></td>
                                <td><?php echo htmlspecialchars($tx['receipt_number'] ?? 'N/A'); ?></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $tx['type'] ?? 'Unknown')); ?></td>
                                <td>₱<?php echo number_format($tx['amount'] ?? 0); ?></td>
                                <td>
                                    <span class="badge <?php echo $tx['status'] === 'paid' ? 'bg-success' : 'bg-warning'; ?>">
                                        <?php echo ucfirst($tx['status'] ?? 'Pending'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($tx['blockchain_hash'])): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-shield-alt"></i> Verified
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">
                                            <i class="fas fa-minus"></i> None
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($tx['status'] === 'paid'): ?>
                                        <a href="/portal/admin/financial/receipt.php?id=<?php echo $tx['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" target="_blank">
                                            <i class="fas fa-file-pdf"></i> Receipt
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Event Participation Fees -->
    <div class="content-card">
        <h2><i class="fas fa-calendar-alt me-2"></i>Event Participation Fees</h2>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Event</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Blockchain</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($event_payments)): ?>
                        <tr>
                            <td colspan="5" class="text-center">No event payments found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($event_payments as $tx): ?>
                            <tr>
                                <td><?php echo date('M d, Y g:i A', strtotime($tx['created_at'] ?? 'now')); ?></td>
                                <td><?php echo htmlspecialchars($tx['event_name'] ?? 'Event'); ?></td>
                                <td>₱<?php echo number_format($tx['amount'] ?? 0); ?></td>
                                <td>
                                    <span class="badge <?php echo $tx['status'] === 'paid' ? 'bg-success' : 'bg-warning'; ?>">
                                        <?php echo ucfirst($tx['status'] ?? 'Pending'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($tx['blockchain_hash'])): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-shield-alt"></i> Verified
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">
                                            <i class="fas fa-minus"></i> None
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.stat-warning {
    border-left: 4px solid #f59e0b;
}
.stat-success {
    border-left: 4px solid #22c55e;
}
</style>

<?php include_once __DIR__ . '/../../../includes/footer.php'; ?>

