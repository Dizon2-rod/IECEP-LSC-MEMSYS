<?php
require_once dirname(__DIR__) . '/../bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';

$itemId = $_GET['id'] ?? '';
$supabaseConfig = require INCLUDES_PATH . 'supabase.php';
$supabase = new \App\Lib\SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);
if (!empty($supabaseConfig['service_role_key'])) {
    $supabase->setServiceRoleKey($supabaseConfig['service_role_key']);
}

$item = null;
if (!empty($itemId)) {
    try {
        $result = $supabase->select('merch_items', ["id" => "eq.$itemId"]);
        if (is_array($result) && !empty($result[0])) {
            $item = $result[0];
        }
    } catch (\Throwable $e) {
        error_log("Merch item fetch error: " . $e->getMessage());
    }
}

if (!$item) {
    http_response_code(404);
    echo "<h2>Item not found</h2><p><a href=\"" . BASE_URL . "\">Return to homepage</a></p>";
    exit;
}

$price = (float)($item['price'] ?? 0);
$stock = (int)($item['stock'] ?? 0);
$name = $item['name'] ?? 'Unknown Item';
$description = $item['description'] ?? '';
$imageUrl = $item['image_url'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order: <?= h($name) ?> - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/styles.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        .order-container { max-width: 520px; margin: 3rem auto; padding: 0 1rem; }
        .order-card { background: #fff; border-radius: 20px; box-shadow: 0 4px 20px rgba(11,29,74,0.08); border: 1px solid #eef2f7; padding: 2rem; }
        .order-header { text-align: center; margin-bottom: 1.5rem; }
        .order-header h1 { color: #0B1D4A; font-size: 1.4rem; font-weight: 700; }
        .order-header p { color: #6b7280; font-size: 0.95rem; margin-top: 0.5rem; }
        .product-image { width: 100%; height: 180px; border-radius: 12px; margin-bottom: 1.25rem; object-fit: cover; border: 1px solid #e5e7eb; }
        .product-image-placeholder { width: 100%; height: 180px; border-radius: 12px; margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, rgba(11,29,74,0.08), rgba(212,175,55,0.16)); color: #0B1D4A; font-size: 1.2rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.4rem; margin-bottom: 1rem; }
        .form-label { font-size: 0.85rem; font-weight: 600; color: #0B1D4A; }
        .form-control { border-radius: 10px; border: 1px solid #dbe3ef; padding: 0.7rem; font-size: 0.95rem; }
        .form-control:focus { outline: none; border-color: #D4AF37; box-shadow: 0 0 0 3px rgba(212,175,55,0.2); }
        .price-display { font-size: 1.5rem; font-weight: 700; color: #D4AF37; text-align: center; margin: 1rem 0; }
        .price-display .total-label { font-size: 0.85rem; color: #6b7280; }
        .price-display .total-value { font-size: 1.5rem; color: #0B1D4A; }
        .btn-order { width: 100%; background: linear-gradient(135deg, #D4AF37 0%, #C5A059 100%); color: #0B1D4A; border: none; border-radius: 999px; padding: 0.75rem; font-weight: 700; font-size: 1rem; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; }
        .btn-order:hover { background: linear-gradient(135deg, #C5A059 0%, #D4AF37 100%); }
        .btn-order:disabled { opacity: 0.6; cursor: not-allowed; }
        .back-link { display: inline-flex; align-items: center; gap: 0.3rem; color: #0B1D4A; text-decoration: none; font-weight: 500; font-size: 0.9rem; }
        .error-msg { background: rgba(220,38,38,0.1); border: 1px solid rgba(220,38,38,0.3); color: #dc2626; padding: 0.7rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; }
        .stock-warning { color: #d97706; font-size: 0.85rem; font-weight: 600; }
    </style>
</head>
<body>
    <div class="order-container">
        <a href="<?= BASE_URL ?>" class="back-link"><i class="fas fa-arrow-left"></i> Back to Home</a>
        <div class="order-card">
            <div class="order-header">
                <h1>Order Merchandise</h1>
                <p>Complete your order for <strong><?= h($name) ?></strong></p>
            </div>

            <?php if ($stock <= 0): ?>
                <div class="error-msg">This item is currently out of stock.</div>
            <?php elseif ($stock <= 5): ?>
                <div class="stock-warning">Only <?= $stock ?> left in stock!</div>
            <?php endif; ?>

            <?php if ($imageUrl): ?>
                <img src="<?= h($imageUrl) ?>" alt="<?= h($name) ?>" class="product-image">
            <?php else: ?>
                <div class="product-image-placeholder">
                    <i class="fas fa-tshirt" style="font-size:3rem"></i>
                </div>
            <?php endif; ?>

            <p style="color:#6b7280;font-size:0.95rem;margin-bottom:1.25rem"><?= h($description) ?></p>

            <form id="orderForm" method="POST" action="<?= BASE_URL ?>/public/api/order-merch.php">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="merch_item_id" value="<?= $item['id'] ?>">

                <div class="form-group">
                    <label class="form-label">Product Name</label>
                    <input type="text" value="<?= h($name) ?>" readonly class="form-control" style="background:#f8fafc">
                </div>

                <div class="form-group">
                    <label class="form-label">Quantity</label>
                    <input type="number" name="quantity" id="quantity" class="form-control" min="1" max="<?= $stock ?>" value="1" required>
                    <small style="color:#6b7280">Max available: <?= $stock ?></small>
                </div>

                <div class="form-group">
                    <label class="form-label">Unit Price</label>
                    <div class="price-display">
                        <span class="total-label">₱</span>
                        <span class="total-value" id="unitPrice"><?= number_format($price, 2) ?></span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Total Price</label>
                    <div class="price-display">
                        <span class="total-label">₱</span>
                        <span class="total-value" id="totalPrice"><?= number_format($price, 2) ?></span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Your Name *</label>
                    <input type="text" name="buyer_name" class="form-control" required placeholder="e.g., Juan Dela Cruz">
                </div>

                <div class="form-group">
                    <label class="form-label">Your Email *</label>
                    <input type="email" name="buyer_email" class="form-control" required placeholder="e.g., juandela.cruz@email.com">
                </div>

                <div class="form-group">
                    <label class="form-label">Notes (Optional)</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Special instructions, size preferences, etc..."></textarea>
                </div>

                <button type="submit" class="btn-order" id="orderBtn" <?= ($stock <= 0) ? 'disabled' : '' ?>>
                    <i class="fas fa-shopping-cart"></i> Place Order
                </button>
            </form>
        </div>
    </div>

    <script>
        const pricePerItem = <?= json_encode($price) ?>;
        const maxStock = <?= json_encode($stock) ?>;

        document.getElementById('quantity').addEventListener('input', updateTotal);
        document.getElementById('quantity').addEventListener('change', function() {
            if (this.value < 1) this.value = 1;
            if (this.value > maxStock) this.value = maxStock;
            updateTotal();
        });

        function updateTotal() {
            const qty = parseInt(document.getElementById('quantity').value) || 0;
            const total = pricePerItem * qty;
            document.getElementById('totalPrice').textContent = total.toFixed(2);
        }

        document.getElementById('orderForm').addEventListener('submit', async function(e) {
            const qty = parseInt(document.getElementById('quantity').value);
            if (qty < 1 || qty > maxStock) {
                e.preventDefault();
                alert('Please enter a valid quantity (1-' + maxStock + ')');
                return;
            }

            const btn = document.getElementById('orderBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

            const formData = new FormData(this);
            const resp = await fetch(this.action, {
                method: 'POST',
                body: new URLSearchParams(new FormData(this))
            });
            const data = await resp.json();

            if (data.success) {
                window.location.href = '<?= BASE_URL ?>/public/order-success.php?order_id=' + encodeURIComponent(data.order_id);
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-shopping-cart"></i> Place Order';
                alert(data.message || 'Order failed. Please try again.');
            }
        });
    </script>
</body>
</html>
