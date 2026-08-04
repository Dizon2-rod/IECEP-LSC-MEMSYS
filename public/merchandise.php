<?php
require_once dirname(__DIR__) . '/bootstrap.php';

$supabaseConfig = require INCLUDES_PATH . 'supabase.php';
$supabase = new \App\Lib\SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);
if (!empty($supabaseConfig['service_role_key'])) {
    $supabase->setServiceRoleKey($supabaseConfig['service_role_key']);
}

$merchItems = [];
try {
    $result = $supabase->select('merch_items', ['is_active' => 'eq.true', 'stock' => 'gte.1']);
    if (is_array($result) && isset($result[0]['id'])) {
        $merchItems = $result;
        usort($merchItems, function ($a, $b) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });
    }
} catch (\Throwable $e) {
    error_log('Merchandise page error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IECEP-LSC Merchandise | Official Chapter Store</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/font-awesome.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/styles.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }

        /* Shopee-style Header */
        .shop-header {
            background: #0B1D4A;
            color: #fff;
            padding: 12px 16px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .shop-header-row {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .shop-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.3rem;
        }
        .shop-logo img { height: 40px; width: auto; }
        .shop-header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .shop-cart-link {
            position: relative;
            color: #fff;
            text-decoration: none;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .shop-cart-badge {
            position: absolute;
            top: -8px;
            right: -12px;
            background: #D4AF37;
            color: #0B1D4A;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
        .shop-cart-badge.hidden { display: none; }

        /* Shopee-style Banner */
        .shop-banner {
            background: linear-gradient(135deg, #0B1D4A 0%, #1A3A8A 100%);
            color: #fff;
            padding: 50px 20px;
            text-align: center;
        }
        .shop-banner h1 { font-size: 2.2rem; font-weight: 700; margin-bottom: 8px; }
        .shop-banner p { font-size: 1.05rem; opacity: 0.9; max-width: 640px; margin: 0 auto; }
        .shop-banner .gold-line {
            width: 60px;
            height: 3px;
            background: #D4AF37;
            margin: 16px auto;
        }

        /* Search & Filter Bar */
        .shop-toolbar {
            max-width: 1200px;
            margin: 24px auto 0;
            padding: 0 16px;
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .shop-search-box {
            flex: 1;
            position: relative;
        }
        .shop-search-box input {
            width: 100%;
            padding: 10px 16px 10px 44px;
            border: 1px solid #e0e0e0;
            border-radius: 24px;
            font-size: 0.9rem;
            outline: none;
        }
        .shop-search-box input:focus { border-color: #D4AF37; }
        .shop-search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 0.9rem;
        }

        /* Shopee-style Product Grid */
        .shop-product-grid {
            max-width: 1200px;
            margin: 24px auto;
            padding: 0 16px 40px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
        }
        @media (max-width: 640px) {
            .shop-product-grid { grid-template-columns: 2fr; gap: 12px; }
        }

        /* Shopee-style Product Card */
        .shop-product-card {
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            transition: box-shadow 0.2s;
            display: flex;
            flex-direction: column;
            cursor: pointer;
        }
        .shop-product-card:hover {
            box-shadow: 0 4px 12px rgba(11,29,74,0.12);
        }
        .shop-product-card-image {
            width: 100%;
            height: 180px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid #f0f0f0;
        }
        .shop-product-card img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .shop-product-card-placeholder {
            color: #cbd5e1;
            font-size: 2rem;
        }
        .shop-product-info {
            padding: 12px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .shop-product-name {
            font-size: 0.9rem;
            font-weight: 500;
            color: #0B1D4A;
            margin-bottom: 4px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.3;
        }
        .shop-product-desc {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-bottom: 8px;
            line-height: 1.2;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .shop-product-price-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .shop-product-price {
            font-size: 1.1rem;
            font-weight: 700;
            color: #D4AF37;
        }
        .shop-product-stock {
            font-size: 0.75rem;
            color: #64748b;
        }
        .shop-product-stock.low { color: #d97706; }
        .shop-add-to-cart {
            background: linear-gradient(135deg, #D4AF37 0%, #C5A059 100%);
            color: #0B1D4A;
            border: none;
            border-radius: 20px;
            padding: 8px 12px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            transition: all 0.2s;
        }
        .shop-add-to-cart:hover {
            background: linear-gradient(135deg, #C5A059 0%, #D4AF37 100%);
            transform: translateY(-1px);
        }
        .shop-add-to-cart:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* Empty State */
        .shop-empty {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        .shop-empty i { font-size: 3rem; margin-bottom: 16px; opacity: 0.3; }

        /* Cart Sidebar */
        .shop-cart-sidebar {
            position: fixed;
            top: 0;
            right: -420px;
            width: 380px;
            max-width: 95vw;
            height: 100vh;
            background: #fff;
            box-shadow: -4px 0 20px rgba(0,0,0,0.15);
            z-index: 200;
            display: flex;
            flex-direction: column;
            transition: right 0.3s ease;
            border-left: 3px solid #D4AF37;
        }
        .shop-cart-sidebar.active { right: 0; }
        .shop-cart-header {
            padding: 16px 20px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .shop-cart-header h3 { color: #0B1D4A; font-size: 1.1rem; }
        .shop-cart-close {
            background: none;
            border: none;
            font-size: 1.3rem;
            color: #999;
            cursor: pointer;
        }
        .shop-cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 16px 20px;
        }
        .shop-cart-item {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f5f5f5;
        }
        .shop-cart-item-image {
            width: 60px;
            height: 60px;
            border-radius: 4px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }
        .shop-cart-item-image img { width: 100%; height: 100%; object-fit: contain; }
        .shop-cart-item-details { flex: 1; }
        .shop-cart-item-name {
            font-size: 0.85rem;
            font-weight: 500;
            color: #0B1D4A;
            margin-bottom: 4px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .shop-cart-item-price { font-size: 0.85rem; color: #D4AF37; font-weight: 700; }
        .shop-cart-item-quantity {
            font-size: 0.75rem;
            color: #94a3b8;
        }
        .shop-cart-item-remove {
            background: none;
            border: none;
            color: #dc2626;
            font-size: 0.8rem;
            cursor: pointer;
            padding: 2px 6px;
        }
        .shop-cart-footer {
            padding: 16px 20px;
            border-top: 1px solid #f0f0f0;
        }
        .shop-cart-total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .shop-cart-total-label { font-size: 0.9rem; color: #64748b; }
        .shop-cart-total-value { font-size: 1.1rem; font-weight: 700; color: #0B1D4A; }
        .shop-checkout-btn {
            width: 100%;
            background: linear-gradient(135deg, #0B1D4A 0%, #1A3A8A 100%);
            color: #fff;
            border: none;
            border-radius: 24px;
            padding: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .shop-checkout-btn:hover { background: linear-gradient(135deg, #1A3A8A 0%, #0B1D4A 100%); }
        .shop-cart-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 100;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        .shop-cart-overlay.active { opacity: 1; visibility: visible; }

        /* Footer */
        .shop-footer {
            background: #0B1D4A;
            color: #94a3b8;
            padding: 30px 20px;
            text-align: center;
            font-size: 0.85rem;
        }
        .shop-footer a { color: #D4AF37; text-decoration: none; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <!-- Shopee-style Header -->
    <div class="shop-header">
        <div class="shop-header-row">
            <a href="<?= BASE_URL ?>/public/merchandise.php" class="shop-logo">
                <img src="<?= ASSETS_URL ?>/icons/iecep-logo.png" alt="IECEP-LSC Logo" onerror="this.style.display='none'">
                IECEP-LSC Merch
            </a>
            <div class="shop-header-actions">
                <a href="javascript:void(0)" class="shop-cart-link" onclick="openCart()">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Cart</span>
                    <span class="shop-cart-badge hidden" id="cartCount">0</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Shopee-style Banner -->
    <div class="shop-banner">
        <div class="gold-line"></div>
        <h1>IECEP-LSC Official Merchandise</h1>
        <p>Support the chapter and look great with official IECEP-LSC merchandise. All proceeds go directly to chapter activities and initiatives.</p>
    </div>

    <!-- Search & Filter -->
    <div class="shop-toolbar">
        <div class="shop-search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search merchandise..." oninput="filterProducts()">
        </div>
    </div>

    <!-- Product Grid -->
    <div class="shop-product-grid" id="productGrid">
        <?php if (!empty($merchItems)): ?>
            <?php foreach ($merchItems as $item): ?>
                <?php
                    $imageUrl = trim((string)($item['image_url'] ?? ''));
                    $itemName = htmlspecialchars($item['name'] ?? 'Untitled Item');
                    $itemDesc  = htmlspecialchars($item['description'] ?? '');
                    $itemPrice = number_format((float)($item['price'] ?? 0), 2);
                    $itemId    = $item['id'] ?? '';
                    $itemStock = (int)($item['stock'] ?? 0);
                    $stockText = $itemStock > 10 ? "$itemStock in stock" : ($itemStock > 0 ? "$itemStock left" : 'Out of stock');
                    $stockClass = $itemStock > 10 ? '' : ($itemStock > 0 ? 'low' : 'low');
                ?>
                <div class="shop-product-card" onclick="viewProduct('<?= $itemId ?>')" data-name="<?= $itemName ?>" data-category="">
                    <div class="shop-product-card-image">
                        <?php if ($imageUrl !== ''): ?>
                            <img src="<?= h($imageUrl) ?>" alt="<?= $itemName ?>">
                        <?php else: ?>
                            <div class="shop-product-card-placeholder">
                                <i class="fas fa-tshirt"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="shop-product-info">
                        <div class="shop-product-name" title="<?= $itemName ?>"><?= $itemName ?></div>
                        <p class="shop-product-desc"><?= $itemDesc ?></p>
                        <div class="shop-product-price-row">
                            <span class="shop-product-price">₱<?= $itemPrice ?></span>
                            <span class="shop-product-stock <?= $stockClass ?>"><?= $stockText ?></span>
                        </div>
                        <button class="shop-add-to-cart" onclick="addToCart(event, '<?= $itemId ?>', <?= json_encode($itemName) ?>, <?= (float)($item['price'] ?? 0) ?>, <?= $itemStock ?>, '<?= $imageUrl ?>')" <?= ($itemStock <= 0) ? 'disabled' : '' ?>>
                            <i class="fas fa-shopping-cart"></i> Add
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="shop-empty">
                <i class="fas fa-box-open"></i>
                <h3>No merchandise available at this time.</h3>
                <p style="margin-top: 8px">Check back soon for new items!</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <!-- Cart Sidebar -->
    <div class="shop-cart-overlay" id="cartOverlay" onclick="closeCart()"></div>
    <div class="shop-cart-sidebar" id="cartSidebar">
        <div class="shop-cart-header">
            <h3>Your Cart (<span id="cartItemCount">0</span>)</h3>
            <button class="shop-cart-close" onclick="closeCart()">&times;</button>
        </div>
        <div class="shop-cart-items" id="cartItems">
            <!-- Cart items will be populated by JavaScript -->
        </div>
        <div class="shop-cart-footer">
            <div class="shop-cart-total-row">
                <span class="shop-cart-total-label">Total</span>
                <span class="shop-cart-total-value" id="cartTotal">₱0.00</span>
            </div>
            <button class="shop-checkout-btn" id="checkoutBtn" onclick="checkout()">
                <i class="fas fa-credit-card"></i> Proceed to Checkout
            </button>
        </div>
    </div>

    <script>
        const cart = {
            items: JSON.parse(localStorage.getItem('mercep_cart') || '[]'),
            add(item) {
                const existing = this.items.find(i => i.id === item.id);
                if (existing) {
                    const newQty = existing.quantity + item.quantity;
                    if (newQty <= item.maxStock) {
                        existing.quantity = newQty;
                    } else {
                        existing.quantity = item.maxStock;
                    }
                } else {
                    this.items.push({ ...item, quantity: item.quantity });
                }
                this.save();
                this.update();
            },
            remove(id) {
                this.items = this.items.filter(i => i.id !== id);
                this.save();
                this.update();
            },
            clear() {
                this.items = [];
                this.save();
                this.update();
            },
            save() {
                localStorage.setItem('mercep_cart', JSON.stringify(this.items));
            },
            update() {
                const count = this.items.reduce((sum, i) => sum + i.quantity, 0);
                const total = this.items.reduce((sum, i) => sum + (i.price * i.quantity), 0);
                document.getElementById('cartCount').textContent = count;
                document.getElementById('cartCount').classList.toggle('hidden', count === 0);
                document.getElementById('cartItemCount').textContent = count;
                const itemsEl = document.getElementById('cartItems');
                if (this.items.length === 0) {
                    itemsEl.innerHTML = '<p style="text-align:center;color:#999;padding:20px">Your cart is empty.</p>';
                } else {
                    itemsEl.innerHTML = this.items.map(i => `
                        <div class="shop-cart-item">
                            <div class="shop-cart-item-image">
                                ${i.image ? `<img src="${i.image}" alt="${i.name}">` : '<i class="fas fa-tshirt" style="font-size:1.5rem;color:#cbd5e1"></i>'}
                            </div>
                            <div class="shop-cart-item-details">
                                <div class="shop-cart-item-name">${i.name}</div>
                                <div style="display:flex;align-items:center;gap:6px;margin-top:4px">
                                    <button onclick="cart.decrease('${i.id}')" style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:4px;width:24px;height:24px;font-size:0.7rem;cursor:pointer">−</button>
                                    <span style="font-size:0.8rem">${i.quantity}</span>
                                    <button onclick="cart.increase('${i.id}', ${i.maxStock})" style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:4px;width:24px;height:24px;font-size:0.7rem;cursor:pointer">+</button>
                                </div>
                                <div class="shop-cart-item-price">₱${(i.price * i.quantity).toFixed(2)}</div>
                            </div>
                            <button class="shop-cart-item-remove" onclick="cart.remove('${i.id}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `).join('');
                }
                document.getElementById('cartTotal').textContent = '₱' + total.toFixed(2);
                document.getElementById('checkoutBtn').disabled = this.items.length === 0;
            },
            decrease(id) {
                const item = this.items.find(i => i.id === id);
                if (item && item.quantity > 1) {
                    item.quantity--;
                }
                this.save();
                this.update();
            },
            increase(id, maxStock) {
                const item = this.items.find(i => i.id === id);
                if (item && item.quantity < maxStock) {
                    item.quantity++;
                }
                this.save();
                this.update();
            }
        };

        function filterProducts() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            document.querySelectorAll('.shop-product-card[data-name]').forEach(card => {
                const name = card.getAttribute('data-name').toLowerCase();
                card.style.display = name.includes(search) ? '' : 'none';
            });
        }

        function addToCart(event, id, name, price, maxStock, image) {
            event.stopPropagation();
            cart.add({ id, name, price, quantity: 1, maxStock, image });
        }

        function viewProduct(id) {
            window.location.href = '<?= BASE_URL ?>/public/order-merch.php?id=' + encodeURIComponent(id);
        }

        function openCart() {
            document.getElementById('cartSidebar').classList.add('active');
            document.getElementById('cartOverlay').classList.add('active');
        }

        function closeCart() {
            document.getElementById('cartSidebar').classList.remove('active');
            document.getElementById('cartOverlay').classList.remove('active');
        }

        function checkout() {
            if (cart.items.length === 0) return;
            // Redirect to order page for the first item (simple checkout)
            const firstItem = cart.items[0];
            const url = new URL('<?= BASE_URL ?>/public/order-merch.php', window.location.origin);
            url.searchParams.set('id', firstItem.id);
            if (cart.items.length > 1) {
                url.searchParams.set('cart', btoa(JSON.stringify(cart.items)));
            }
            window.location.href = url.toString();
        }

        // Initialize cart display
        cart.update();
    </script>
</body>
</html>
