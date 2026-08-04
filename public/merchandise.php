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

$categories = [];
foreach ($merchItems as $item) {
    $cat = $item['category'] ?? 'Uncategorized';
    if (!isset($categories[$cat])) {
        $categories[$cat] = 0;
    }
    $categories[$cat]++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>IECEP-LSC Merchandise | Official Chapter Store</title>
    <?php include dirname(__DIR__) . '/includes/head-meta.php'; ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES) ?>/assets/css/styles.css">
    <style>
        body { background: #f5f7fb; }

        /* Hero Section */
        .merch-hero {
            position: relative;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 50%, var(--primary) 100%);
            color: var(--white);
            padding: 4rem 1rem 3rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        .merch-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect width="100" height="100" fill="none" stroke="rgba(255,255,255,0.03)" stroke-width="1"/></svg>') repeat;
            opacity: 0.1;
            z-index: 0;
        }
        .merch-hero-content { position: relative; z-index: 1; max-width: 800px; margin: 0 auto; }
        .merch-hero h1 {
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            font-weight: 800;
            margin-bottom: 0.75rem;
            color: var(--white);
        }
        .merch-hero h1 .gold-accent { color: var(--accent); }
        .merch-hero p {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 650px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* Top Bar - Search + Cart */
        .merch-top-bar {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .merch-search-box {
            position: relative;
            flex: 1;
            min-width: 250px;
        }
        .merch-search-box input {
            width: 100%;
            padding: 10px 16px 10px 44px;
            border: 1px solid var(--neutral-300);
            border-radius: 24px;
            font-size: 0.9rem;
            outline: none;
            background: var(--white);
            transition: border-color var(--transition-base);
        }
        .merch-search-box input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(212,175,55,0.2);
        }
        .merch-search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--neutral-500);
            font-size: 0.9rem;
        }
        .merch-cart-trigger {
            background: var(--primary);
            color: var(--white);
            border: none;
            border-radius: 24px;
            padding: 10px 20px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all var(--transition-base);
            box-shadow: 0 4px 12px rgba(11,29,74,0.18);
        }
        .merch-cart-trigger:hover {
            background: linear-gradient(135deg, var(--accent) 0%, #C5A059 100%);
            color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(11,29,74,0.25);
        }
        .cart-icon-container { position: relative; display: inline-flex; align-items: center; }
        #cartCountTop {
            position: absolute; top: -6px; right: -6px; background: var(--accent); color: var(--primary);
            border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center;
            justify-content: center; font-size: 0.7rem; font-weight: 700;
        }
        .cart-icon-container .hidden { display: none !important; }

        /* Category Filters */
        .merch-categories {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem 1.5rem;
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            padding-bottom: 0.25rem;
        }
        .merch-categories::-webkit-scrollbar { height: 4px; }
        .merch-categories::-webkit-scrollbar-thumb { background: var(--neutral-300); border-radius: 2px; }
        .category-btn {
            background: var(--white);
            border: 1px solid var(--neutral-300);
            border-radius: 20px;
            padding: 6px 16px;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--neutral-700);
            cursor: pointer;
            white-space: nowrap;
            transition: all var(--transition-base);
        }
        .category-btn:hover {
            background: var(--neutral-100);
            color: var(--primary);
        }
        .category-btn.active {
            background: var(--primary);
            color: var(--white);
            border-color: var(--primary);
        }
        .category-btn .count {
            background: rgba(255,255,255,0.2);
            border-radius: 10px;
            padding: 0 6px;
            font-size: 0.7rem;
        }

        /* Product Grid */
        .shop-product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 24px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem 2rem;
        }
        @media (max-width: 640px) {
            .shop-product-grid { grid-template-columns: 1fr; }
        }

        .shop-product-card {
            background: var(--white);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            transition: all var(--transition-base);
            border: 1px solid var(--neutral-200);
        }
        .shop-product-card:hover {
            box-shadow: 0 12px 24px rgba(11,29,74,0.12);
            transform: translateY(-4px);
            border-color: var(--accent);
        }
        .shop-product-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: var(--accent);
            color: var(--primary);
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 700;
            z-index: 2;
        }
        .shop-product-card-image {
            width: 100%;
            height: 180px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 1px solid var(--neutral-200);
            position: relative;
        }
        .shop-product-card img { width: 100%; height: 100%; object-fit: contain; }
        .shop-product-card-placeholder { color: #cbd5e1; font-size: 2rem; }
        .shop-product-info { padding: 16px; flex: 1; display: flex; flex-direction: column; }
        .shop-product-name {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 4px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.3;
        }
        .shop-product-desc {
            font-size: 0.8rem;
            color: var(--neutral-500);
            margin-bottom: 10px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex: 1;
        }
        .shop-product-rating {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 8px;
        }
        .shop-product-rating i { color: #FFD700; font-size: 0.8rem; }
        .shop-product-rating span { font-size: 0.75rem; color: var(--neutral-500); }
        .shop-product-price-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .shop-product-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--accent);
        }
        .shop-product-stock {
            font-size: 0.75rem;
            color: var(--neutral-500);
        }
        .shop-product-stock.low { color: #d97706; }
        .shop-product-stock.out { color: #dc2626; }

        .shop-add-to-cart {
            width: 100%;
            background: linear-gradient(135deg, var(--accent) 0%, #C5A059 100%);
            color: var(--primary);
            border: none;
            border-radius: 12px;
            padding: 10px 12px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all var(--transition-base);
        }
        .shop-add-to-cart:hover {
            background: linear-gradient(135deg, #C5A059 0%, var(--accent) 100%);
            transform: translateY(-1px);
        }
        .shop-add-to-cart:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        .shop-empty {
            grid-column: 1 / -1;
            text-align: center;
            padding: 80px 20px;
            color: var(--neutral-500);
        }
        .shop-empty i { font-size: 3rem; margin-bottom: 16px; opacity: 0.3; }

        /* Cart Sidebar */
        .shop-cart-sidebar {
            position: fixed;
            top: 0;
            right: -420px;
            width: 400px;
            max-width: 95vw;
            height: 100vh;
            background: var(--white);
            box-shadow: -4px 0 20px rgba(0,0,0,0.15);
            z-index: 2000;
            display: flex;
            flex-direction: column;
            transition: right 0.3s ease;
            border-left: 3px solid var(--accent);
        }
        .shop-cart-sidebar.active { right: 0; }
        .shop-cart-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--neutral-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .shop-cart-header h3 { color: var(--primary); font-size: 1.1rem; margin: 0; }
        .shop-cart-close { background: none; border: none; font-size: 1.5rem; color: var(--neutral-500); cursor: pointer; }
        .shop-cart-items { flex: 1; overflow-y: auto; padding: 16px 20px; }
        .shop-cart-item { display: flex; gap: 12px; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #f5f5f5; }
        .shop-cart-item-image {
            width: 60px; height: 60px; border-radius: 4px; background: #f8f9fa;
            display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0;
        }
        .shop-cart-item-image img { width: 100%; height: 100%; object-fit: contain; }
        .shop-cart-item-image-placeholder { color: #cbd5e1; font-size: 1.5rem; }
        .shop-cart-item-details { flex: 1; }
        .shop-cart-item-name {
            font-size: 0.85rem; font-weight: 500; color: var(--primary); margin-bottom: 4px;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }
        .shop-cart-item-price { font-size: 0.85rem; color: var(--accent); font-weight: 700; }
        .shop-cart-item-quantity-controls { display: flex; align-items: center; gap: 6px; margin: 4px 0; }
        .shop-cart-item-quantity-controls button {
            background: var(--neutral-200); border: 1px solid var(--neutral-300); border-radius: 4px;
            width: 24px; height: 24px; font-size: 0.7rem; cursor: pointer;
        }
        .shop-cart-item-quantity-controls span { font-size: 0.8rem; min-width: 20px; text-align: center; }
        .shop-cart-item-remove {
            background: none; border: none; color: #dc2626; font-size: 0.8rem; cursor: pointer; padding: 2px 6px;
        }
        .shop-cart-footer { padding: 16px 20px; border-top: 1px solid var(--neutral-200); }
        .shop-cart-total-row { display: flex; justify-content: space-between; margin-bottom: 12px; }
        .shop-cart-total-label { font-size: 0.9rem; color: var(--neutral-500); }
        .shop-cart-total-value { font-size: 1.1rem; font-weight: 700; color: var(--primary); }
        .shop-checkout-btn {
            width: 100%; background: linear-gradient(135deg, var(--primary) 0%, #1A3A8A 100%);
            color: var(--white); border: none; border-radius: 24px; padding: 12px;
            font-size: 0.95rem; font-weight: 600; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 6px;
        }
        .shop-checkout-btn:hover { background: linear-gradient(135deg, #1A3A8A 0%, var(--primary) 100%); }
        .shop-cart-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); z-index: 100; opacity: 0; visibility: hidden; transition: all 0.3s ease;
        }
        .shop-cart-overlay.active { opacity: 1; visibility: visible; }

        /* Back to top */
        .back-to-top {
            position: fixed; bottom: 24px; right: 24px;
            width: 44px; height: 44px; border-radius: 50%;
            background: var(--primary); color: var(--white);
            border: none; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; opacity: 0; visibility: hidden;
            transition: all var(--transition-base); z-index: 100;
            box-shadow: 0 4px 12px rgba(11,29,74,0.18);
        }
        .back-to-top.visible { opacity: 1; visibility: visible; }
        .back-to-top:hover {
            background: var(--accent); color: var(--primary);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div id="fb-root"></div>
    <script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v17.0"></script>

    <?php include dirname(__DIR__) . '/includes/navbar.php'; ?>

    <!-- Hero Section -->
    <section class="merch-hero">
        <div class="merch-hero-content">
            <h1>IECEP‑LSC <span class="gold-accent">Official Store</span></h1>
            <p>Support the chapter and look great with official IECEP-LSC merchandise. All proceeds go directly to chapter activities and initiatives.</p>
        </div>
    </section>

    <!-- Top Bar: Search + Cart -->
    <div class="merch-top-bar">
        <div class="merch-search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search merchandise..." oninput="filterProducts()">
        </div>
        <button class="merch-cart-trigger" onclick="openCart()">
            <i class="fas fa-shopping-cart"></i>
            <span>Your Cart</span>
            <span class="cart-icon-container">
                <span id="cartCountTop" class="<?= empty($merchItems) ? 'hidden' : '' ?>">0</span>
            </span>
        </button>
    </div>

    <!-- Category Filters -->
    <div class="merch-categories" id="categoryFilters">
        <button class="category-btn active" onclick="filterCategory('all')">
            All Items <span class="count"><?= count($merchItems) ?></span>
        </button>
        <?php foreach ($categories as $cat => $count): ?>
            <button class="category-btn" onclick="filterCategory('<?= h($cat) ?>')">
                <?= h($cat) ?> <span class="count"><?= $count ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Product Grid -->
    <div class="shop-product-grid" id="productGrid">
        <?php if (!empty($merchItems)): ?>
            <?php foreach ($merchItems as $item): ?>
                <?php
                    $imageUrl = trim((string)($item['image_url'] ?? ''));
                    $itemName = htmlspecialchars($item['name'] ?? 'Untitled Item', ENT_QUOTES);
                    $itemId   = $item['id'] ?? '';
                    $itemPrice = (float)($item['price'] ?? 0);
                    $itemStock = (int)($item['stock'] ?? 0);
                    $itemDesc  = htmlspecialchars($item['description'] ?? '', ENT_QUOTES);
                    $itemCat   = $item['category'] ?? 'Uncategorized';
                    $rating    = (float)($item['rating'] ?? 4.5);
                ?>
                <div class="shop-product-card" onclick="viewProduct('<?= $itemId ?>')" data-name="<?= $itemName ?>" data-category="<?= $itemCat ?>">
                    <?php if ($itemStock <= 5 && $itemStock > 0): ?>
                        <span class="shop-product-badge">Low Stock</span>
                    <?php elseif ($itemStock <= 0): ?>
                        <span class="shop-product-badge" style="background:#dc2626">Out of Stock</span>
                    <?php endif; ?>
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
                        <div class="shop-product-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                            <span>(<?= $rating ?>)</span>
                        </div>
                        <div class="shop-product-price-row">
                            <span class="shop-product-price">₱<?= number_format($itemPrice, 2) ?></span>
                            <span class="shop-product-stock <?= $itemStock > 10 ? '' : ($itemStock > 0 ? 'low' : 'out') ?>">
                                <?= $itemStock > 10 ? "$itemStock in stock" : ($itemStock > 0 ? "$itemStock left" : 'Out of stock') ?>
                            </span>
                        </div>
                        <button class="shop-add-to-cart" onclick="addToCart(event, '<?= $itemId ?>', <?= json_encode($itemName) ?>, <?= $itemPrice ?>, <?= $itemStock ?>, '<?= $imageUrl ?>')" <?= ($itemStock <= 0) ? 'disabled' : '' ?>>
                            <i class="fas fa-shopping-cart"></i> Add to Cart
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

    <!-- Back to Top Button -->
    <button class="back-to-top" id="backToTop" onclick="scrollToTop()">
        <i class="fas fa-chevron-up"></i>
    </button>

    <!-- Cart Sidebar -->
    <div class="shop-cart-overlay" id="cartOverlay" onclick="closeCart()"></div>
    <div class="shop-cart-sidebar" id="cartSidebar">
        <div class="shop-cart-header">
            <h3>Your Cart (<span id="cartItemCount">0</span>)</h3>
            <button class="shop-cart-close" onclick="closeCart()">&times;</button>
        </div>
        <div class="shop-cart-items" id="cartItems">
            <p style="text-align:center;color:var(--neutral-500);padding:20px">Your cart is empty.</p>
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

    <?php include dirname(__DIR__) . '/includes/footer-new.php'; ?>

    <script>
        let cart = {
            items: JSON.parse(localStorage.getItem('mercep_cart') || '[]'),
            add(item) {
                const existing = this.items.find(i => i.id === item.id);
                if (existing) {
                    if (existing.quantity < item.maxStock) {
                        existing.quantity++;
                    } else {
                        return false;
                    }
                } else {
                    this.items.push({ ...item });
                }
                this.save();
                this.update();
                return true;
            },
            remove(id) {
                this.items = this.items.filter(i => i.id !== id);
                this.save();
                this.update();
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
            },
            save() {
                localStorage.setItem('mercep_cart', JSON.stringify(this.items));
            },
            update() {
                const count = this.items.reduce((sum, i) => sum + i.quantity, 0);
                const total = this.items.reduce((sum, i) => sum + (i.price * i.quantity), 0);
                const cartCountTop = document.getElementById('cartCountTop');
                if (cartCountTop) {
                    cartCountTop.textContent = count;
                    cartCountTop.classList.toggle('hidden', count === 0);
                }
                const itemCountEl = document.getElementById('cartItemCount');
                if (itemCountEl) itemCountEl.textContent = count;
                const totalEl = document.getElementById('cartTotal');
                if (totalEl) totalEl.textContent = '₱' + total.toFixed(2);
                const checkoutBtn = document.getElementById('checkoutBtn');
                if (checkoutBtn) checkoutBtn.disabled = this.items.length === 0;
                const itemsEl = document.getElementById('cartItems');
                if (this.items.length === 0) {
                    if (itemsEl) itemsEl.innerHTML = '<p style="text-align:center;color:var(--neutral-500);padding:20px">Your cart is empty.</p>';
                } else {
                    if (itemsEl) {
                        itemsEl.innerHTML = this.items.map(i => `
                            <div class="shop-cart-item">
                                <div class="shop-cart-item-image">
                                    ${i.image ? `<img src="${i.image}" alt="${i.name}">` : '<div class="shop-cart-item-image-placeholder"><i class="fas fa-tshirt"></i></div>'}
                                </div>
                                <div class="shop-cart-item-details">
                                    <div class="shop-cart-item-name">${i.name}</div>
                                    <div class="shop-cart-item-quantity-controls">
                                        <button onclick="cart.decrease('${i.id}')">&minus;</button>
                                        <span>${i.quantity}</span>
                                        <button onclick="cart.increase('${i.id}', ${i.maxStock})">&plus;</button>
                                    </div>
                                    <div class="shop-cart-item-price">₱${(i.price * i.quantity).toFixed(2)}</div>
                                </div>
                                <button class="shop-cart-item-remove" onclick="cart.remove('${i.id}')">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        `).join('');
                    }
                }
            }
        };

        function filterProducts() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            document.querySelectorAll('.shop-product-card[data-name]').forEach(card => {
                const name = card.getAttribute('data-name').toLowerCase();
                card.style.display = name.includes(search) ? '' : 'none';
            });
        }

        function filterCategory(cat) {
            document.querySelectorAll('.category-btn').forEach(btn => btn.classList.remove('active'));
            event.currentTarget.classList.add('active');
            document.querySelectorAll('.shop-product-card').forEach(card => {
                if (cat === 'all' || card.getAttribute('data-category') === cat) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function addToCart(event, id, name, price, maxStock, image) {
            event.stopPropagation();
            const added = cart.add({ id, name, price, quantity: 1, maxStock, image });
            if (!added) {
                alert('Max stock reached for this item!');
            }
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
            const firstItem = cart.items[0];
            const url = new URL('<?= BASE_URL ?>/public/order-merch.php', window.location.origin);
            url.searchParams.set('id', firstItem.id);
            if (cart.items.length > 1) {
                url.searchParams.set('cart', btoa(JSON.stringify(cart.items)));
            }
            window.location.href = url.toString();
        }

        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        window.addEventListener('scroll', () => {
            const btn = document.getElementById('backToTop');
            if (btn) {
                btn.classList.toggle('visible', window.scrollY > 300);
            }
        });

        cart.update();
    </script>
</body>
</html>