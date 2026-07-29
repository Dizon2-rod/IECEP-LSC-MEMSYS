<?php
require_once __DIR__ . '/../auth_check.php';
require_role(['member']);

require_once __DIR__ . '/../../../includes/role-config.php';
require_once __DIR__ . '/../../../bootstrap.php';

$current_page = 'payments';

$user = get_user_info();
$member_id = $_SESSION['member_id'] ?? $user['member_id'] ?? null;

if (!$member_id) {
    header('Location: /login.php');
    exit;
}

$supabase = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);

// Fetch member's transaction history
try {
    $transactions = $supabase->select('transactions', [
        'member_id' => 'eq.' . $member_id,
        'order' => 'created_at.desc'
    ]);
} catch (Exception $e) {
    $transactions = [];
}

// Calculate totals
$totalPaid = 0;
$totalPending = 0;
foreach ($transactions as $tx) {
    if (($tx['status'] ?? '') === 'completed') {
        $totalPaid += floatval($tx['amount'] ?? 0);
    } else {
        $totalPending += floatval($tx['amount'] ?? 0);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/../../includes/head-meta.php'; ?>
    <title>Payment History - Member Portal</title>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="container py-5">
                <div class="mb-4">
                    <h1 class="h2 mb-2">Payment History</h1>
                    <p class="text-muted">View your transaction history and receipts</p>
                </div>

                <!-- Summary Cards -->
                <div class="grid-2 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="text-muted mb-2">Total Paid</h6>
                            <h3 class="text-success mb-0">₱<?= number_format($totalPaid, 2) ?></h3>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <h6 class="text-muted mb-2">Pending Payments</h6>
                            <h3 class="text-warning mb-0">₱<?= number_format($totalPending, 2) ?></h3>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Transaction History</h5>
                        
                        <?php if (empty($transactions)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                No payment history found.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Description</th>
                                            <th>Type</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $tx): ?>
                                            <tr>
                                                <td>
                                                    <?= date('M d, Y', strtotime($tx['created_at'] ?? '')) ?>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($tx['description'] ?? 'Payment') ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?= htmlspecialchars($tx['payment_type'] ?? 'N/A') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong>₱<?= number_format($tx['amount'] ?? 0, 2) ?></strong>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status = $tx['status'] ?? 'pending';
                                                    $statusClasses = [
                                                        'completed' => 'bg-success',
                                                        'pending' => 'bg-warning',
                                                        'failed' => 'bg-danger',
                                                        'cancelled' => 'bg-secondary'
                                                    ];
                                                    ?>
                                                    <span class="badge <?= $statusClasses[$status] ?? 'bg-secondary' ?>">
                                                        <?= ucfirst(htmlspecialchars($status)) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($status === 'completed'): ?>
                                                        <a href="/api/generate-receipt.php?transaction_id=<?= htmlspecialchars($tx['id'] ?? '') ?>" 
                                                           class="btn btn-sm btn-outline" 
                                                           target="_blank">
                                                            <i class="fas fa-receipt me-1"></i>Receipt
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
