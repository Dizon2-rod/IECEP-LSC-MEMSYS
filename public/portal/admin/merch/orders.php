<?php
require_once dirname(dirname(__DIR__)) . '/auth_check.php';
require_role(['admin']);

$supabaseConfig = require INCLUDES_PATH . 'supabase.php';
$supabase = new \App\Lib\SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);
if (!empty($supabaseConfig['service_role_key'])) {
    $supabase->setServiceRoleKey($supabaseConfig['service_role_key']);
}

$successMessage = '';
$errorMessage = '';

if (!empty($_SESSION['merch_orders_flash'])) {
    $flash = $_SESSION['merch_orders_flash'];
    $successMessage = $flash['success'] ?? '';
    $errorMessage = $flash['error'] ?? '';
    unset($_SESSION['merch_orders_flash']);
}

$orders = [];
try {
    $result = $supabase->select('merch_orders', ['order' => 'created_at.desc']);
    if (is_array($result) && isset($result[0]['id'])) {
        $orders = $result;
    }
} catch (\Throwable $e) {
    $errorMessage = 'Failed to load orders: ' . $e->getMessage();
}

if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $targetId = $_GET['id'];
    $order = null;
    try {
        $result = $supabase->select('merch_orders', ["id" => "eq." . $targetId]);
        if (is_array($result) && !empty($result[0])) {
            $order = $result[0];
        }
    } catch (\Throwable $e) {
        $errorMessage = 'Failed to load order: ' . $e->getMessage();
    }
}

$order = $order ?? null;

if (isset($_POST['update_status']) && isset($_POST['order_id']) && isset($_POST['new_status'])) {
    $orderId = $_POST['order_id'];
    $newStatus = $_POST['new_status'];
    $transactionId = $_POST['transaction_id'] ?? null;

    try {
        $updateData = ['status' => $newStatus, 'updated_at' => date('Y-m-d\TH:i:s\Z')];
        $supabase->update('merch_orders', $updateData, $orderId);

        if ($newStatus === 'paid' && $transactionId) {
            $supabase->update('transactions', ['status' => 'paid', 'paid_at' => date('Y-m-d\TH:i:s\Z'), 'updated_at' => date('Y-m-d\TH:i:s\Z')], $transactionId);
            require_once SRC_PATH . 'lib/BlockchainService.php';
            $blockchain = new \App\Lib\BlockchainService($supabase);
            $blockchain->record(
                'payment',
                $orderId,
                [
                    'order_id' => $orderId,
                    'amount' => 0,
                    'status' => 'paid',
                    'type' => 'merch',
                ]
            );
        }

        $_SESSION['merch_orders_flash'] = ['success' => 'Order status updated to: ' . $newStatus];
        header('Location: ' . BASE_URL . '/public/portal/admin/merch/orders.php');
        exit;
    } catch (\Throwable $e) {
        $errorMessage = 'Failed to update order: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merchandise Orders | IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/styles.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        .portal-shell { display: flex; min-height: 100vh; }
        .portal-main { flex: 1; padding: 2rem; margin-left: 260px; }
        .portal-card { background: #fff; border-radius: 16px; box-shadow: 0 10px 30px rgba(11, 29, 74, 0.08); border: 1px solid #eef2f7; padding: 1.5rem; }
        .page-header { margin-bottom: 1.25rem; }
        .page-title { margin: 0; color: #0B1D4A; font-size: 1.55rem; font-weight: 700; }
        .badge-pill { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.7rem; border-radius: 999px; font-size: 0.8rem; font-weight: 700; }
        .badge-pending { background: rgba(245, 158, 11, 0.15); color: #d97706; }
        .badge-paid { background: rgba(16, 185, 129, 0.15); color: #059669; }
        .badge-shipped { background: rgba(59, 130, 246, 0.15); color: #2563eb; }
        .badge-delivered { background: rgba(16, 185, 129, 0.15); color: #059669; }
        .badge-cancelled { background: rgba(220, 38, 38, 0.15); color: #dc2626; }
        .actions { display: flex; gap: 0.5rem; }
        .btn-gold-outline { border: 1px solid #D4AF37; color: #0B1D4A; background: transparent; border-radius: 999px; padding: 0.35rem 0.8rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem; }
        .btn-gold-outline:hover { background: #D4AF37; color: #fff; }
        .table-responsive { overflow-x: auto; }
        .filter-bar { display: flex; gap: 1rem; margin-bottom: 1rem; align-items: center; }
        .filter-bar select, .filter-bar input { border-radius: 8px; border: 1px solid #dbe3ef; padding: 0.5rem 0.75rem; font-size: 0.9rem; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); overflow-y: auto; }
        .modal-content { background: #fff; margin: 10% auto; border-radius: 16px; max-width: 650px; padding: 1.5rem; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .modal-title { color: #0B1D4A; font-size: 1.25rem; font-weight: 700; margin: 0; }
        .modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280; }
        .detail-row { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #f1f5f9; }
        .detail-label { color: #64748b; font-size: 0.85rem; }
        .detail-value { font-weight: 600; color: #0B1D4A; }
    </style>
</head>
<body>
<?php require_once dirname(__DIR__) . '/sidebar.php'; ?>
<div class="portal-shell">
    <?php require_once dirname(__DIR__) . '/header.php'; ?>
    <main class="portal-main">
        <div class="page-header">
            <h1 class="page-title">Merchandise Orders</h1>
        </div>

        <?php if ($successMessage): ?>
            <div class="portal-card" style="background:rgba(16,185,129,0.1);border-color:rgba(16,185,129,0.3);margin-bottom:1rem;">
                <i class="fas fa-check-circle" style="color:#059669"></i> <?= h($successMessage) ?>
            </div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="portal-card" style="background:rgba(220,38,38,0.1);border-color:rgba(220,38,38,0.3);margin-bottom:1rem;">
                <i class="fas fa-exclamation-circle" style="color:#dc2626"></i> <?= h($errorMessage) ?>
            </div>
        <?php endif; ?>

        <div class="portal-card">
            <div class="filter-bar">
                <input type="date" id="dateFilter" placeholder="Filter by date...">
                <select id="statusFilter">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="paid">Paid</option>
                    <option value="shipped">Shipped</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="table-responsive">
                <table style="width:100%;border-collapse:collapse;font-size:0.9rem;">
                    <thead>
                        <tr style="border-bottom:2px solid #e2e8f0;">
                            <th style="padding:0.75rem;text-align:left;color:#0B1D4A;font-weight:600">ID</th>
                            <th style="padding:0.75rem;text-align:left;color:#0B1D4A;font-weight:600">Buyer</th>
                            <th style="padding:0.75rem;text-align:left;color:#0B1D4A;font-weight:600">Email</th>
                            <th style="padding:0.75rem;text-align:left;color:#0B1D4A;font-weight:600">Items</th>
                            <th style="padding:0.75rem;text-align:right;color:#0B1D4A;font-weight:600">Total</th>
                            <th style="padding:0.75rem;text-align:left;color:#0B1D4A;font-weight:600">Status</th>
                            <th style="padding:0.75rem;text-align:right;color:#0B1D4A;font-weight:600">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr><td colspan="7" style="padding:2rem;text-align:center;color:#94a3b8">No orders found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order):
                                $statusClass = 'badge-' . ($order['status'] ?? 'pending');
                                $items = json_decode($order['items'] ?? '[]', true) ?: [];
                                $itemNames = [];
                                foreach ($items as $item) {
                                    $itemNames[] = ($item['name'] ?? 'Unknown') . ' x' . ($item['quantity'] ?? 1);
                                }
                            ?>
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:0.75rem"><?= substr($order['id'] ?? '', 0, 8) ?>…</td>
                                    <td style="padding:0.75rem"><?= h($order['buyer_name'] ?? '') ?></td>
                                    <td style="padding:0.75rem"><?= h($order['buyer_email'] ?? '') ?></td>
                                    <td style="padding:0.75rem"><?= h(implode(', ', $itemNames)) ?></td>
                                    <td style="padding:0.75rem;text-align:right">₱<?= number_format((float)($order['total_amount'] ?? 0), 2) ?></td>
                                    <td style="padding:0.75rem"><span class="badge-pill <?= $statusClass ?>"><?= ucfirst($order['status'] ?? 'pending') ?></span></td>
                                    <td style="padding:0.75rem;text-align:right">
                                        <a href="?action=view&id=<?= $order['id'] ?>" class="btn-gold-outline"><i class="fas fa-eye"></i> View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($order ?? null): ?>
        <div class="portal-card" style="margin-top:1.5rem">
            <div class="modal-header">
                <h3 class="modal-title">Order Details: <?= substr($order['id'] ?? '', 0, 8) ?>…</h3>
                <a href="?action=list" class="modal-close"><i class="fas fa-times"></i></a>
            </div>
            <div class="detail-row"><span class="detail-label">Buyer Name</span><span class="detail-value"><?= h($order['buyer_name'] ?? '') ?></span></div>
            <div class="detail-row"><span class="detail-label">Buyer Email</span><span class="detail-value"><?= h($order['buyer_email'] ?? '') ?></span></div>
            <div class="detail-row"><span class="detail-label">Total Amount</span><span class="detail-value">₱<?= number_format((float)($order['total_amount'] ?? 0), 2) ?></span></div>
            <div class="detail-row"><span class="detail-label">Status</span><span class="detail-value"><?= ucfirst($order['status'] ?? '') ?></span></div>
            <div class="detail-row"><span class="detail-label">Order Date</span><span class="detail-value"><?= date('M j, Y g:i A', strtotime($order['created_at'] ?? 'now')) ?></span></div>
            <div class="detail-row"><span class="detail-label">Notes</span><span class="detail-value"><?= h($order['notes'] ?? '') ?: 'No notes' ?></span></div>
            <div class="detail-row"><span class="detail-label">Items (JSON)</span><span class="detail-value"><?= h(json_encode($order['items'] ?? [], JSON_PRETTY_PRINT)) ?></span></div>
            <?php if (isset($order['transaction_id']) && $order['transaction_id']): ?>
            <div class="detail-row"><span class="detail-label">Transaction ID</span><span class="detail-value"><?= h($order['transaction_id']) ?></span></div>
            <?php endif; ?>

            <hr style="margin:1.5rem 0;border:none;border-top:1px solid #e2e8f0">

            <?php if (($order['status'] ?? '') !== 'delivered' && ($order['status'] ?? '') !== 'cancelled'): ?>
            <form method="POST" action="" style="display:flex;gap:0.75rem;align-items:center">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                <input type="hidden" name="transaction_id" value="<?= $order['transaction_id'] ?? '' ?>">
                <select name="new_status" class="form-control" style="max-width:180px">
                    <option value="paid" <?= ($order['status'] ?? '') === 'paid' ? 'disabled selected' : '' ?>>Mark as Paid</option>
                    <option value="shipped" <?= ($order['status'] ?? '') === 'shipped' ? 'disabled selected' : '' ?>>Mark as Shipped</option>
                    <option value="delivered" <?= ($order['status'] ?? '') === 'delivered' ? 'disabled selected' : '' ?>>Mark as Delivered</option>
                    <option value="cancelled">Cancel Order</option>
                </select>
                <button type="submit" name="update_status" class="btn-gold" onclick="return confirm('Update order status?')">Update Status</button>
            </form>
            <?php else: ?>
            <p style="color:#6b7280;font-size:0.9rem">Order is final (Delivered/Cancelled). No further actions available.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </main>
</div>

<script>
document.getElementById('statusFilter').addEventListener('change', function() {
    window.location.href = '?status=' + encodeURIComponent(this.value);
});
document.getElementById('dateFilter').addEventListener('change', function() {
    window.location.href = '?date=' + encodeURIComponent(this.value);
});
</script>
</body>
</html>
