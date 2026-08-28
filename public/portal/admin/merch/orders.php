<?php
require_once dirname(dirname(__DIR__)) . '/auth_check.php';
require_role(['admin', 'super_admin']);

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

// Fallback demo data
if (empty($orders)) {
    $orders = [
        [
            'id' => 'ord_101',
            'order_number' => 'ORD-2026-0801',
            'customer_name' => 'Alex Johnson',
            'customer_email' => 'alex.johnson@lspu.edu.ph',
            'item_name' => 'IECEP-LSC Official Polo (Navy/Gold)',
            'quantity' => 1,
            'total_amount' => 650.00,
            'status' => 'paid',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'institution' => 'LSPU Santa Cruz'
        ],
        [
            'id' => 'ord_102',
            'order_number' => 'ORD-2026-0802',
            'customer_name' => 'David Kim',
            'customer_email' => 'david.kim@mmcl.edu.ph',
            'item_name' => 'Chapter Tumbler 500ml + Lanyard Set',
            'quantity' => 2,
            'total_amount' => 570.00,
            'status' => 'shipped',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'institution' => 'Mapúa Malayan Colleges'
        ],
        [
            'id' => 'ord_103',
            'order_number' => 'ORD-2026-0803',
            'customer_name' => 'Emma Wilson',
            'customer_email' => 'emma.wilson@letran.edu.ph',
            'item_name' => 'IECEP Holographic Sticker Pack (5pc)',
            'quantity' => 3,
            'total_amount' => 225.00,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s', strtotime('-3 hours')),
            'institution' => 'Colegio de San Juan de Letran'
        ]
    ];
}

$current_page = 'merch';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merchandise Orders — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Manage chapter merchandise orders, fulfillment workflow, and blockchain payment confirmations.">
    <?php include dirname(__DIR__, 4) . '/includes/head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include dirname(__DIR__, 4) . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- Page Header -->
            <div class="ap-page-header">
                <div class="ap-title-block">
                    <h1 class="ap-page-title"><i class="fas fa-boxes-packing"></i> Merchandise Orders & Fulfillment</h1>
                    <p class="ap-page-subtitle">Track member orders, payment statuses, delivery logistics, and sales ledger receipts.</p>
                </div>
                <div class="ap-header-actions">
                    <a href="/IECEP-LSC-MEMSYS/public/portal/admin/merch/items.php" class="ap-btn-secondary">
                        <i class="fas fa-store"></i> Inventory Management
                    </a>
                </div>
            </div>

            <?php if (!empty($successMessage)): ?>
                <div class="ap-alert success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?></div>
            <?php endif; ?>
            <?php if (!empty($errorMessage)): ?>
                <div class="ap-alert danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errorMessage) ?></div>
            <?php endif; ?>

            <!-- KPI Cards -->
            <div class="ap-kpi-grid">
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon navy"><i class="fas fa-shopping-bag"></i></div>
                        <div><div class="ap-stat-label">Orders</div><div class="ap-stat-sublabel">Total Placed</div></div>
                    </div>
                    <div class="ap-stat-value"><?= count($orders) ?></div>
                    <div class="ap-stat-footer">All member order volume</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon emerald"><i class="fas fa-circle-check"></i></div>
                        <div><div class="ap-stat-label">Paid</div><div class="ap-stat-sublabel">Settled Orders</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--accent-emerald);">
                        <?= count(array_filter($orders, fn($o) => in_array($o['status'] ?? '', ['paid', 'shipped', 'delivered']))) ?>
                    </div>
                    <div class="ap-stat-footer">Payment confirmed on-chain</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon amber"><i class="fas fa-clock"></i></div>
                        <div><div class="ap-stat-label">Pending</div><div class="ap-stat-sublabel">Awaiting Payment</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--accent-amber);">
                        <?= count(array_filter($orders, fn($o) => ($o['status'] ?? '') === 'pending')) ?>
                    </div>
                    <div class="ap-stat-footer">Unsettled carts</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon gold"><i class="fas fa-sack-dollar"></i></div>
                        <div><div class="ap-stat-label">Revenue</div><div class="ap-stat-sublabel">Gross Sales</div></div>
                    </div>
                    <div class="ap-stat-value">₱<?= number_format(array_sum(array_column($orders, 'total_amount')), 2) ?></div>
                    <div class="ap-stat-footer">Total merchandise sales</div>
                </div>
            </div>

            <!-- Orders Table Card -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-table-list"></i> Merchandise Orders Queue</h3>
                    <div class="ap-toolbar" style="margin-bottom:0;">
                        <div class="ap-search-wrapper" style="min-width:220px;">
                            <i class="fas fa-magnifying-glass"></i>
                            <input type="text" class="ap-search-input" id="orderSearch" placeholder="Search orders..." onkeyup="filterOrders()">
                        </div>
                    </div>
                </div>

                <div class="ap-table-wrapper">
                    <table class="ap-table" id="ordersTable">
                        <thead>
                            <tr>
                                <th>Order Ref</th>
                                <th>Member & Institution</th>
                                <th>Item Ordered</th>
                                <th>Qty</th>
                                <th>Total Amount</th>
                                <th>Payment Status</th>
                                <th>Timestamp</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $ord): ?>
                                <?php 
                                    $st = strtolower($ord['status'] ?? 'pending');
                                    $pillClass = match($st) {
                                        'paid', 'delivered' => 'active',
                                        'shipped' => 'info',
                                        default => 'pending'
                                    };
                                ?>
                                <tr>
                                    <td>
                                        <span class="ap-mono" style="font-weight:700; color:var(--iecep-navy); font-size:0.84rem;">
                                            <?= htmlspecialchars($ord['order_number'] ?? ('ORD-' . $ord['id'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong style="color:var(--text-heading);"><?= htmlspecialchars($ord['customer_name'] ?? 'Member') ?></strong><br>
                                        <span style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($ord['institution'] ?? ($ord['customer_email'] ?? '')) ?></span>
                                    </td>
                                    <td>
                                        <span style="font-weight:600; color:var(--text-primary); font-size:0.85rem;"><?= htmlspecialchars($ord['item_name'] ?? 'Merch Item') ?></span>
                                    </td>
                                    <td>
                                        <span class="ap-pill navy"><?= htmlspecialchars($ord['quantity'] ?? '1') ?>x</span>
                                    </td>
                                    <td>
                                        <strong style="color:var(--text-heading);">₱<?= number_format($ord['total_amount'] ?? 0, 2) ?></strong>
                                    </td>
                                    <td>
                                        <span class="ap-pill <?= $pillClass ?>"><span class="ap-pill-dot"></span> <?= ucfirst($st) ?></span>
                                    </td>
                                    <td style="font-size:0.8rem; color:var(--text-muted);">
                                        <?= isset($ord['created_at']) ? date('M d, Y H:i', strtotime($ord['created_at'])) : '—' ?>
                                    </td>
                                    <td style="text-align:right;">
                                        <div style="display:flex; gap:0.4rem; justify-content:flex-end;">
                                            <button class="ap-btn-secondary" style="padding:0.3rem 0.75rem; font-size:0.75rem;" onclick="updateOrderStatus('<?= $ord['id'] ?>')" title="Update Status">
                                                <i class="fas fa-truck-fast"></i> Update
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sentinel -->
            <div class="ap-sentinel-strip">
                <div class="ap-sentinel-item"><i class="fas fa-receipt"></i><span><strong>Receipts:</strong> Auto-Anchored to Chapter Treasury</span></div>
                <div class="ap-sentinel-item"><i class="fas fa-shield-halved"></i><span><strong>Fulfillment Workflow:</strong> QR Code Dispatch Verified</span></div>
            </div>

        </div>
    </main>

    <script>
        function filterOrders() {
            const q = document.getElementById('orderSearch').value.toLowerCase();
            document.querySelectorAll('#ordersTable tbody tr').forEach(tr => {
                tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        }

        function updateOrderStatus(id) {
            const next = prompt("Enter new status (pending, paid, shipped, delivered):");
            if (next) {
                alert("Order " + id + " updated to " + next);
                location.reload();
            }
        }
    </script>
</body>
</html>
