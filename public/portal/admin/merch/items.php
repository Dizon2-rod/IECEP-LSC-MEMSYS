<?php
require_once dirname(dirname(__DIR__)) . '/bootstrap.php';
require_once __DIR__ . '/auth_check.php';
require_role(['admin']);

$supabaseConfig = require INCLUDES_PATH . 'supabase.php';
$supabase = new \App\Lib\SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);
if (!empty($supabaseConfig['service_role_key'])) {
    $supabase->setServiceRoleKey($supabaseConfig['service_role_key']);
}

$action = $_GET['action'] ?? '';
$itemId = $_GET['id'] ?? '';
$successMessage = '';
$errorMessage = '';

if (!empty($_SESSION['merch_flash'])) {
    $flash = $_SESSION['merch_flash'];
    $successMessage = $flash['success'] ?? '';
    $errorMessage = $flash['error'] ?? '';
    unset($_SESSION['merch_flash']);
}

$items = [];
try {
    $result = $supabase->select('merch_items', ['order' => 'created_at.desc']);
    if (is_array($result) && isset($result[0]['id'])) {
        $items = $result;
    }
} catch (\Throwable $e) {
    $errorMessage = 'Failed to load items: ' . $e->getMessage();
}

if ($action === 'delete' && !empty($itemId)) {
    try {
        $supabase->delete('merch_items', $itemId);
        $_SESSION['merch_flash'] = ['success' => 'Item deleted successfully.'];
        header('Location: ' . BASE_URL . '/public/portal/admin/merch/items.php');
        exit;
    } catch (\Throwable $e) {
        $errorMessage = 'Failed to delete item: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merchandise Items | IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/styles.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        .portal-shell { display: flex; min-height: 100vh; }
        .portal-main { flex: 1; padding: 2rem; margin-left: 260px; }
        .portal-card { background: #fff; border-radius: 16px; box-shadow: 0 10px 30px rgba(11, 29, 74, 0.08); border: 1px solid #eef2f7; padding: 1.5rem; }
        .page-header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1.25rem; }
        .page-title { margin: 0; color: #0B1D4A; font-size: 1.55rem; font-weight: 700; }
        .btn-gold { background: linear-gradient(135deg, #D4AF37 0%, #C5A059 100%); color: #0B1D4A; border: none; border-radius: 999px; padding: 0.5rem 1.2rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-gold:hover { background: linear-gradient(135deg, #C5A059 0%, #D4AF37 100%); }
        .btn-gold-outline { border: 1px solid #D4AF37; color: #0B1D4A; background: transparent; border-radius: 999px; padding: 0.45rem 0.8rem; font-weight: 600; text-decoration: none; }
        .btn-gold-outline:hover { background: #D4AF37; color: #fff; }
        .btn-danger-outline { border: 1px solid #dc3545; color: #dc3545; background: transparent; border-radius: 999px; padding: 0.45rem 0.8rem; font-weight: 600; }
        .btn-danger-outline:hover { background: #dc3545; color: #fff; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.35rem; }
        .form-label { font-size: 0.9rem; font-weight: 600; color: #0B1D4A; }
        .form-control { border-radius: 10px; border: 1px solid #dbe3ef; padding: 0.7rem; font-size: 0.95rem; }
        .form-control:focus { outline: none; border-color: #D4AF37; box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2); }
        .table-responsive { overflow-x: auto; }
        .merch-thumb { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; border: 1px solid #e5e7eb; }
        .badge-pill { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.7rem; border-radius: 999px; font-size: 0.8rem; font-weight: 700; }
        .badge-active { background: rgba(16, 185, 129, 0.15); color: #059669; }
        .badge-inactive { background: rgba(107, 114, 191, 0.15); color: #4338ca; }
        .badge-stock-low { background: rgba(245, 158, 11, 0.15); color: #d97706; }
        .actions { display: flex; gap: 0.5rem; }
        .toggle-switch { position: relative; display: inline-block; width: 50px; height: 26px; }
        .toggle-switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; border-radius: 26px; transition: .3s; }
        .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; border-radius: 50%; transition: .3s; }
        .input:checked + .slider { background-color: #D4AF37; }
        .input:checked + .slider:before { transform: translateX(24px); }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.6); overflow-y: auto; }
        .modal-content { background: #fff; margin: 10% auto; border-radius: 16px; max-width: 600px; padding: 1.5rem; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .modal-title { color: #0B1D4A; font-size: 1.25rem; font-weight: 700; margin: 0; }
        .modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280; }
    </style>
</head>
<body>
<?php require_once dirname(__DIR__) . '/sidebar.php'; ?>
<div class="portal-shell">
    <?php require_once dirname(__DIR__) . '/header.php'; ?>
    <main class="portal-main">
        <div class="page-header">
            <h1 class="page-title">Merchandise Items</h1>
            <button class="btn-gold" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Add Item
            </button>
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
            <div class="table-responsive">
                <table style="width:100%;border-collapse:collapse;font-size:0.9rem;">
                    <thead>
                        <tr style="border-bottom:2px solid #e2e8f0;">
                            <th style="padding:0.75rem;text-align:left;color:#0B1D4A;font-weight:600">#</th>
                            <th style="padding:0.75rem;text-align:left;color:#0B1D4A;font-weight:600">Item</th>
                            <th style="padding:0.75rem;text-align:left;color:#0B1D4A;font-weight:600">Price</th>
                            <th style="padding:0.75rem;text-align:left;color:#0B1D4A;font-weight:600">Stock</th>
                            <th style="padding:0.75rem;text-align:left;color:#0B1D4A;font-weight:600">Status</th>
                            <th style="padding:0.75rem;text-align:right;color:#0B1D4A;font-weight:600">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr><td colspan="6" style="padding:2rem;text-align:center;color:#94a3b8">No merchandise items found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($items as $i => $item): ?>
                                <?php
                                    $isActive = ($item['is_active'] ?? true) ? 'true' : 'false';
                                    $stock = (int)($item['stock'] ?? 0);
                                    $statusBadge = $item['is_active'] ? 'badge-active' : 'badge-inactive';
                                    $statusText = $item['is_active'] ? 'Active' : 'Inactive';
                                    $stockBadge = $stock > 10 ? 'badge-active' : ($stock > 0 ? 'badge-stock-low' : 'badge-inactive');
                                    $stockText = "$stock in stock";
                                    $imageUrl = $item['image_url'] ?? '';
                                ?>
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:0.75rem"><?= $i + 1 ?></td>
                                    <td style="padding:0.75rem">
                                        <div style="display:flex;align-items:center;gap:0.75rem">
                                            <?php if ($imageUrl): ?>
                                                <img src="<?= h($imageUrl) ?>" alt="<?= h($item['name']) ?>" class="merch-thumb">
                                            <?php else: ?>
                                                <div class="merch-thumb" style="display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,rgba(11,29,74,0.08),rgba(212,175,55,0.16));">
                                                    <i class="fas fa-image" style="color:#0B1D4A"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <strong style="color:#0B1D4A"><?= h($item['name']) ?></strong>
                                                <div style="font-size:0.8rem;color:#6b7280"><?= h($item['description'] ?? '') ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding:0.75rem">₱<?= number_format((float)($item['price'] ?? 0), 2) ?></td>
                                    <td style="padding:0.75rem">
                                        <span class="badge-pill <?= $stockBadge ?>"><?= $stockText ?></span>
                                    </td>
                                    <td style="padding:0.75rem">
                                        <span class="badge-pill <?= $statusBadge ?>"><?= $statusText ?></span>
                                    </td>
                                    <td style="padding:0.75rem;text-align:right">
                                        <div class="actions">
                                            <a href="?action=edit&id=<?= $item['id'] ?>" class="btn-gold-outline"><i class="fas fa-edit"></i></a>
                                            <label class="toggle-switch">
                                                <input type="checkbox" onchange="toggleStatus('<?= $item['id'] ?>', this.checked)" <?= ($item['is_active'] ?? true) ? 'checked' : '' ?>>
                                                <span class="slider"></span>
                                            </label>
                                            <a href="?action=delete&id=<?= $item['id'] ?>" class="btn-danger-outline" onclick="return confirm('Delete this item?')"><i class="fas fa-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Add/Edit Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Add New Merch Item</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="addItemForm" method="POST" action="<?= BASE_URL ?>/public/api/merch-item.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <input type="hidden" name="action" value="add">
            <div class="form-grid" style="grid-template-columns:1fr">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Price (₱)</label>
                        <input type="number" name="price" class="form-control" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stock</label>
                        <input type="number" name="stock" class="form-control" min="0" value="0" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Image Upload</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>
            </div>
            <div style="display:flex;gap:1rem;margin-top:1.5rem;justify-content:flex-end">
                <button type="button" class="btn-gold-outline" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-gold">Save Item</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('addModal').style.display = 'flex';
}
function closeModal() {
    document.getElementById('addModal').style.display = 'none';
}
async function toggleStatus(itemId, isActive) {
    const resp = await fetch('<?= BASE_URL ?>/public/api/merch-item.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'csrf_token=' + encodeURIComponent('<?= $_SESSION['csrf_token'] ?? '' ?>') + '&action=toggle_status&id=' + encodeURIComponent(itemId) + '&is_active=' + (isActive ? '1' : '0')
    });
    const data = await resp.json();
    if (data.success) {
        location.reload();
    } else {
        alert(data.message || 'Failed to update status');
    }
}
document.querySelectorAll('.form-grid').forEach(grid => {
    grid.style.display = 'contents';
});
</style>
</body>
</html>
