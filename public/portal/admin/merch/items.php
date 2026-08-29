<?php
require_once __DIR__ . '/../../bootstrap.php';
$current_page = 'merch-items';

require_once __DIR__ . '/../../auth_check.php';
require_role(['admin', 'super_admin', 'treasurer', 'merchandise']);

$pageTitle = 'Merchandise Inventory Management';
$supabase = getSupabaseClient();

$feedbackMsg = '';
$feedbackType = 'success';

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

                $feedbackMsg = "🎉 Merchandise item '{$name}' created and added to store!";
                $feedbackType = 'success';
            } catch (Exception $e) {
                error_log("Insert merch item error: " . $e->getMessage());
                $feedbackMsg = "Error adding item: " . $e->getMessage();
                $feedbackType = 'warning';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Merchandise inventory, pricing, and stock tracking for IECEP-LSC Laguna Chapter.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-navy: #0B1D4A;
            --color-navy-hover: #152C6E;
            --color-blue: #2563EB;
            --color-gold: #D4AF37;
            --color-emerald: #059669;
            --color-amber: #D97706;
            --bg-page: #F8FAFC;
            --border-color: #E2E8F0;
            --shadow-card: 0 1px 3px 0 rgba(0, 0, 0, 0.04), 0 1px 2px -1px rgba(0, 0, 0, 0.04);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-page);
            color: #1E293B;
            margin: 0;
            padding: 0;
        }

        .dash-header-banner {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 0.85rem 1.25rem;
            margin-bottom: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.75rem;
            box-shadow: var(--shadow-card);
        }
        .dash-header-title {
            margin: 0 0 0.15rem;
            font-size: 1.25rem;
            font-weight: 800;
            color: #0F172A;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .dash-header-sub {
            margin: 0;
            font-size: 0.8rem;
            color: #64748B;
        }

        .btn-white {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.42rem 0.85rem;
            border-radius: 7px;
            font-size: 0.78rem;
            font-weight: 700;
            text-decoration: none;
            background: #FFFFFF;
            border: 1px solid #CBD5E1;
            color: #0F172A;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: all 0.18s ease;
        }
        .btn-white:hover {
            background: #F8FAFC;
            border-color: #94A3B8;
            transform: translateY(-1px);
        }

        .btn-primary-navy {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.42rem 0.95rem;
            border-radius: 7px;
            font-size: 0.78rem;
            font-weight: 800;
            text-decoration: none;
            background: var(--color-navy);
            border: 1px solid var(--color-navy);
            color: #FFFFFF !important;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(11, 29, 74, 0.15);
            transition: all 0.18s ease;
        }
        .btn-primary-navy:hover {
            background: var(--color-navy-hover);
            transform: translateY(-1px);
            color: #FDE047 !important;
        }

        .dash-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.65rem;
            margin-bottom: 0.85rem;
        }
        .dash-kpi-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.65rem 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: var(--shadow-card);
            min-width: 0;
        }
        .kpi-icon-pill {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            flex-shrink: 0;
        }
        .kpi-icon-pill.navy { background: rgba(11, 29, 74, 0.08); color: var(--color-navy); }
        .kpi-icon-pill.emerald { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }
        .kpi-icon-pill.gold { background: #FEF9C3; color: #B45309; border: 1px solid #FDE68A; }
        .kpi-icon-pill.amber { background: #FEF3C7; color: #D97706; border: 1px solid #FDE68A; }

        .kpi-val {
            font-size: 1.25rem;
            font-weight: 800;
            color: #0F172A;
            line-height: 1.1;
        }
        .kpi-lbl {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748B;
            margin-top: 1px;
        }

        .white-controls-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.65rem 0.95rem;
            margin-bottom: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.65rem;
            box-shadow: var(--shadow-card);
        }
        .search-input-field {
            padding: 0.45rem 0.75rem 0.45rem 2rem;
            border: 1px solid #CBD5E1;
            border-radius: 7px;
            font-size: 0.8rem;
            outline: none;
            width: 100%;
            box-sizing: border-box;
            background: #F8FAFC;
        }

        .ap-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
            margin-bottom: 1rem;
        }
        .ap-card-header {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #FFFFFF;
        }
        .ap-card-title {
            margin: 0;
            font-size: 0.88rem;
            font-weight: 800;
            color: #0F172A;
        }

        .ap-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
            text-align: left;
        }
        .ap-table th {
            background: #F8FAFC;
            color: #64748B;
            font-weight: 700;
            font-size: 0.72rem;
            padding: 0.55rem 0.85rem;
            border-bottom: 1px solid var(--border-color);
            text-transform: uppercase;
        }
        .ap-table td {
            padding: 0.65rem 0.85rem;
            border-bottom: 1px solid #F1F5F9;
            color: #334155;
            vertical-align: middle;
        }

        .doc-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(11, 29, 74, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
        }
        .doc-modal.active { display: flex; }
        .modal-inner-box {
            background: #FFFFFF;
            border-radius: 12px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.18);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        @media (max-width: 1024px) {
            .dash-kpi-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- 1. Header Banner -->
            <div class="dash-header-banner">
                <div>
                    <h1 class="dash-header-title">
                        <i class="fas fa-tags" style="color:var(--color-navy);"></i>
                        Merchandise Inventory & Chapter Store
                    </h1>
                    <p class="dash-header-sub">
                        Official chapter apparel, collectable pins, lanyards, and student merchandise catalog.
                    </p>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <a href="<?= PORTAL_URL ?>/admin/merch/orders.php" class="btn-white">
                        <i class="fas fa-box" style="color:var(--color-blue);"></i> Customer Orders
                    </a>
                    <button type="button" class="btn-primary-navy" onclick="openItemModal()">
                        <i class="fas fa-plus" style="color:#FDE047;"></i> Add New Item
                    </button>
                </div>
            </div>

            <?php if (!empty($feedbackMsg)): ?>
                <div class="ap-alert <?= $feedbackType ?>" style="margin-bottom:0.85rem;">
                    <i class="fas fa-check-circle" style="font-size:1.2rem;"></i> 
                    <div><?= htmlspecialchars($feedbackMsg) ?></div>
                </div>
            <?php endif; ?>

            <!-- 2. KPI Grid -->
            <div class="dash-kpi-grid">
                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill navy"><i class="fas fa-boxes-stacked"></i></div>
                    <div>
                        <div class="kpi-val"><?= count($items) ?></div>
                        <div class="kpi-lbl">Total Catalog Items</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-cubes"></i></div>
                    <div>
                        <div class="kpi-val"><?= number_format($totalStock) ?></div>
                        <div class="kpi-lbl">Total Stock on Hand</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-sack-dollar"></i></div>
                    <div>
                        <div class="kpi-val" style="color:#B45309;">₱<?= number_format($totalInventoryValue, 2) ?></div>
                        <div class="kpi-lbl">Total Inventory Valuation</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill amber"><i class="fas fa-store"></i></div>
                    <div>
                        <div class="kpi-val">Active</div>
                        <div class="kpi-lbl">Store Status</div>
                    </div>
                </div>
            </div>

            <!-- 3. Search & Filter Bar -->
            <div class="white-controls-card">
                <div style="position:relative; flex:1; max-width:380px;">
                    <i class="fas fa-search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#94A3B8; font-size:0.8rem;"></i>
                    <input type="text" id="itemSearchInput" class="search-input-field" placeholder="Search item name, description, price..." onkeyup="filterItemsTable()">
                </div>
                <div style="font-size:0.75rem; font-weight:700; color:#64748B;">
                    Showing <?= count($items) ?> items in catalog
                </div>
            </div>

            <!-- 4. Table -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-shirt"></i> Merchandise Catalog & Stock Ledger</h3>
                </div>
                <div style="overflow-x:auto;">
                    <table class="ap-table" id="itemsTable">
                        <thead>
                            <tr>
                                <th>Item Name & Particulars</th>
                                <th>Unit Price</th>
                                <th>Stock on Hand</th>
                                <th>Stock Status</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:2.5rem; color:#64748B;">
                                        <i class="fas fa-store-slash" style="font-size:2rem; color:#CBD5E1; margin-bottom:0.5rem; display:block;"></i>
                                        <strong style="color:#0F172A; font-size:0.92rem;">No Merchandise Items in Catalog</strong>
                                        <p style="margin:0.25rem 0 0; font-size:0.78rem;">Click "+ Add New Item" to add official shirts, pins, or chapter lanyards.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $it): ?>
                                    <?php 
                                        $stock = intval($it['stock'] ?? 0);
                                        $price = floatval($it['price'] ?? 0);
                                    ?>
                                    <tr>
                                        <td>
                                            <strong style="color:#0F172A; font-size:0.84rem;"><?= htmlspecialchars($it['name'] ?? 'Item') ?></strong><br>
                                            <span style="font-size:0.72rem; color:#64748B;"><?= htmlspecialchars($it['description'] ?? 'Chapter merchandise') ?></span>
                                        </td>
                                        <td><strong style="color:#059669; font-size:0.82rem;">₱<?= number_format($price, 2) ?></strong></td>
                                        <td><strong><?= number_format($stock) ?></strong> units</td>
                                        <td>
                                            <?php if ($stock > 10): ?>
                                                <span class="ap-pill active"><span class="ap-pill-dot"></span> In Stock</span>
                                            <?php elseif ($stock > 0): ?>
                                                <span class="ap-pill pending">Low Stock</span>
                                            <?php else: ?>
                                                <span class="ap-pill" style="background:#FEE2E2; color:#DC2626; border:1px solid #FECACA;">Out of Stock</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="ap-pill active">Available</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- Create Item Modal -->
    <div id="itemModal" class="doc-modal">
        <div class="modal-inner-box">
            <div class="ap-card-header">
                <h3 class="ap-card-title"><i class="fas fa-tag"></i> Add Merchandise Item</h3>
                <button class="btn-white" style="border:none; padding:0.25rem 0.5rem;" onclick="closeItemModal()">&times;</button>
            </div>
            <form method="POST" style="padding:1.25rem;">
                <input type="hidden" name="action" value="create_item">
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Item Name</label>
                    <input type="text" name="name" class="ap-input" placeholder="e.g. IECEP-LSC Official Chapter Lanyard" required style="font-size:0.8rem;">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.65rem;">
                    <div class="ap-form-group">
                        <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Unit Price (₱)</label>
                        <input type="number" step="0.01" name="price" class="ap-input" placeholder="150.00" required style="font-size:0.8rem;">
                    </div>
                    <div class="ap-form-group">
                        <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Initial Stock</label>
                        <input type="number" name="stock" class="ap-input" value="50" required style="font-size:0.8rem;">
                    </div>
                </div>
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Description & Specifications</label>
                    <textarea name="description" class="ap-input" rows="2" placeholder="e.g. 1-inch woven lanyard with metal hook and safety breakaway." style="font-size:0.8rem;"></textarea>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:0.65rem; margin-top:1rem;">
                    <button type="button" class="btn-white" onclick="closeItemModal()">Cancel</button>
                    <button type="submit" class="btn-primary-navy"><i class="fas fa-save"></i> Save Item</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openItemModal() {
            document.getElementById('itemModal').classList.add('active');
        }
        function closeItemModal() {
            document.getElementById('itemModal').classList.remove('active');
        }

        function filterItemsTable() {
            const query = document.getElementById('itemSearchInput').value.toLowerCase();
            const table = document.getElementById('itemsTable');
            const trs = table.getElementsByTagName('tr');

            for (let i = 1; i < trs.length; i++) {
                const tr = trs[i];
                if (tr.children.length === 1 && tr.children[0].getAttribute('colspan')) continue;
                const text = tr.textContent.toLowerCase();
                tr.style.display = (text.indexOf(query) > -1) ? '' : 'none';
            }
        }
    </script>
</body>
</html>
