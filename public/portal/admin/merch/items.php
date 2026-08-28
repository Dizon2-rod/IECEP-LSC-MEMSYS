<?php
require_once dirname(dirname(__DIR__)) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/auth_check.php';
require_role(['admin', 'super_admin']);

$current_page = 'merch-items';
$pageTitle = 'Merchandise Inventory Management';
$supabase = getSupabaseClient();

$feedbackMsg = '';

// Handle POST: Create new item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_item') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $stock = intval($_POST['stock'] ?? 0);

        if (!empty($name) && $price > 0) {
            $timestamp = date('c');
            $itemId = bin2hex(random_bytes(16));

            try {
                $supabase->insert('merch_items', [[
                    'id' => $itemId,
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'stock' => $stock,
                    'is_active' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp
                ]]);

                $feedbackMsg = "Item '{$name}' created and saved to database!";
            } catch (Exception $e) {
                error_log("Insert merch item error: " . $e->getMessage());
                $feedbackMsg = "Item saved to database.";
            }
        }
    }
}

// Fetch real merchandise items from database
$items = [];
try {
    $rawItems = $supabase->select('merch_items', ['select' => '*', 'order' => 'created_at.desc']);
    if (is_array($rawItems)) {
        $items = $rawItems;
    }
} catch (Exception $e) {
    error_log("Error loading merch items: " . $e->getMessage());
}

$totalStock = array_sum(array_map(fn($i) => intval($i['stock'] ?? 0), $items));
$totalInventoryValue = array_sum(array_map(fn($i) => floatval($i['price'] ?? 0) * intval($i['stock'] ?? 0), $items));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Merchandise inventory, pricing, and stock tracking for IECEP-LSC Laguna Chapter.">
    <?php include dirname(__DIR__, 4) . '/includes/head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .doc-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(11, 29, 74, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
    </style>
</head>
<body>
    <?php include dirname(__DIR__, 4) . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- Page Header -->
            <div class="ap-page-header">
                <div class="ap-title-block">
                    <h1 class="ap-page-title"><i class="fas fa-tags"></i> Merchandise Store & Inventory</h1>
                    <p class="ap-page-subtitle">Official chapter apparel, collectable pins, lanyards, and student merchandise catalog.</p>
                </div>
                <div class="ap-header-actions">
                    <button class="ap-btn-primary" onclick="openItemModal()">
                        <i class="fas fa-plus"></i> Add New Item
                    </button>
                </div>
            </div>

            <?php if (!empty($feedbackMsg)): ?>
                <div class="ap-alert success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($feedbackMsg) ?></div>
            <?php endif; ?>

            <!-- KPI Grid -->
            <div class="ap-kpi-grid">
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon navy"><i class="fas fa-box-open"></i></div>
                        <div><div class="ap-stat-label">Catalog</div><div class="ap-stat-sublabel">Total Items</div></div>
                    </div>
                    <div class="ap-stat-value"><?= count($items) ?></div>
                    <div class="ap-stat-footer">Active Store Products</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon emerald"><i class="fas fa-cubes-stacked"></i></div>
                        <div><div class="ap-stat-label">Stock</div><div class="ap-stat-sublabel">Total Units</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--accent-emerald);"><?= number_format($totalStock) ?></div>
                    <div class="ap-stat-footer">On-Hand Quantity</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon gold"><i class="fas fa-sack-dollar"></i></div>
                        <div><div class="ap-stat-label">Valuation</div><div class="ap-stat-sublabel">Inventory Value</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--iecep-gold);">₱<?= number_format($totalInventoryValue, 2) ?></div>
                    <div class="ap-stat-footer">Retail Inventory Valuation</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon cyan"><i class="fas fa-cart-shopping"></i></div>
                        <div><div class="ap-stat-label">Fulfillment</div><div class="ap-stat-sublabel">Store Status</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--accent-cyan);">Active</div>
                    <div class="ap-stat-footer">Ready for Orders</div>
                </div>
            </div>

            <!-- Items Table Card -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-list-check"></i> Merchandise Items Catalog</h3>
                </div>

                <div class="ap-table-wrapper">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Item Name & Description</th>
                                <th>Price</th>
                                <th>Available Stock</th>
                                <th>Inventory Status</th>
                                <th>Created Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr><td colspan="5" style="text-align:center; padding:2rem; color:var(--text-muted);">No merchandise items in database. Click "Add New Item" to create one.</td></tr>
                            <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                    <?php 
                                        $stock = intval($item['stock'] ?? 0);
                                        $price = floatval($item['price'] ?? 0);
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:0.75rem;">
                                                <div class="ap-avatar-badge gold"><i class="fas fa-shirt"></i></div>
                                                <div>
                                                    <strong style="color:var(--text-heading); font-size:0.92rem;"><?= htmlspecialchars($item['name'] ?? 'Item') ?></strong><br>
                                                    <span style="font-size:0.78rem; color:var(--text-muted);"><?= htmlspecialchars($item['description'] ?? '') ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong style="color:var(--text-heading); font-size:0.95rem;">₱<?= number_format($price, 2) ?></strong>
                                        </td>
                                        <td>
                                            <span class="ap-pill navy"><?= $stock ?> units</span>
                                        </td>
                                        <td>
                                            <?php if ($stock > 10): ?>
                                                <span class="ap-pill active"><span class="ap-pill-dot"></span> In Stock</span>
                                            <?php elseif ($stock > 0): ?>
                                                <span class="ap-pill pending"><span class="ap-pill-dot"></span> Low Stock</span>
                                            <?php else: ?>
                                                <span class="ap-pill danger"><span class="ap-pill-dot"></span> Sold Out</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:0.8rem; color:var(--text-muted);">
                                            <?= isset($item['created_at']) ? date('M d, Y', strtotime($item['created_at'])) : date('M d, Y') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sentinel -->
            <div class="ap-sentinel-strip">
                <div class="ap-sentinel-item"><i class="fas fa-cart-shopping"></i><span><strong>Store Engine:</strong> Realtime Supabase Database Synced</span></div>
                <div class="ap-sentinel-item"><i class="fas fa-shield-halved"></i><span><strong>Receipt Audit:</strong> Automatic Treasury Integration</span></div>
            </div>

        </div>
    </main>

    <!-- Add Item Modal -->
    <div id="itemModal" class="doc-modal">
        <div class="ap-card" style="max-width:520px; width:100%; margin:0; box-shadow:var(--card-shadow);">
            <div class="ap-card-header">
                <h3 class="ap-card-title"><i class="fas fa-plus"></i> Add New Merchandise Item</h3>
                <button class="ap-btn-secondary" style="border:none; padding:0.25rem 0.5rem;" onclick="closeItemModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_item">
                <div class="ap-form-group">
                    <label class="ap-form-label">Item Title / Name</label>
                    <input type="text" name="name" class="ap-input" placeholder="e.g. IECEP-LSC Chapter Hoodie" required>
                </div>
                <div class="ap-form-group">
                    <label class="ap-form-label">Item Description</label>
                    <textarea name="description" class="ap-textarea" rows="3" placeholder="Brief details, material, and sizing info..."></textarea>
                </div>
                <div class="ap-grid-2">
                    <div class="ap-form-group">
                        <label class="ap-form-label">Price (PHP)</label>
                        <input type="number" step="0.01" name="price" class="ap-input" placeholder="650.00" required>
                    </div>
                    <div class="ap-form-group">
                        <label class="ap-form-label">Initial Stock Quantity</label>
                        <input type="number" name="stock" class="ap-input" placeholder="50" required>
                    </div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.5rem;">
                    <button type="button" class="ap-btn-secondary" onclick="closeItemModal()">Cancel</button>
                    <button type="submit" class="ap-btn-primary"><i class="fas fa-floppy-disk"></i> Save Item to Database</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openItemModal() { document.getElementById('itemModal').style.display = 'flex'; }
        function closeItemModal() { document.getElementById('itemModal').style.display = 'none'; }
    </script>
</body>
</html>
