<?php
require_once dirname(__DIR__) . '/../bootstrap.php';

$facebookPageUrl = 'https://www.facebook.com/IECEPLSC';
$merchItems = [];

try {
    $supabaseClient = getSupabaseClient();
    if ($supabaseClient) {
        $supabaseConfig = require INCLUDES_PATH . 'supabase.php';
        if (!empty($supabaseConfig['service_role_key'])) {
            $supabaseClient->setServiceRoleKey($supabaseConfig['service_role_key']);
        }

        $rawMerch = $supabaseClient->select('merch_items', [
            'is_active' => 'eq.true',
            'stock' => 'gte.1',
        ]);
        if (is_array($rawMerch) && isset($rawMerch[0]['id'])) {
            $merchItems = $rawMerch;
            usort($merchItems, function ($a, $b) {
                return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
            });
        } else {
            $merchItems = [];
        }
    }
} catch (Exception $e) {
    error_log('Merchandise page exception: ' . $e->getMessage());
    $merchItems = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IECEP‑LSC Merchandise | Official Chapter Store</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/font-awesome.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/styles.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; }
        .merch-banner {
            background: linear-gradient(135deg, #0B1D4A 0%, #1A3A8A 100%);
            color: #fff;
            padding: 4rem 1.5rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        .merch-banner h1 { font-size: 2.25rem; font-weight: 700; margin-bottom: 0.5rem; }
        .merch-banner p { color: #bfc9e2; font-size: 1.05rem; max-width: 700px; margin: 0 auto; }
        .merch-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1.5rem;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem 3rem;
        }
        @media (max-width: 640px) {
            .merch-grid { grid-template-columns: 1fr; }
        }
        .merch-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04), 0 4px 16px rgba(11,29,74,0.06), 0 12px 40px rgba(11,29,74,0.04);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid rgba(226,232,240,0.8);
            position: relative;
            height: 100%;
        }
        .merch-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 32px rgba(11,29,74,0.12);
        }
        .merch-card-image {
            width: 100%;
            height: 180px;
            overflow: hidden;
        }
        .merch-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .merch-card:hover .merch-card-image img {
            transform: scale(1.05);
        }
        .merch-card-image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(11,29,74,0.08) 0%, rgba(212,175,55,0.16) 100%);
            color: #0B1D4A;
            font-size: 2.5rem;
        }
        .merch-card-body { padding: 1.25rem 1.25rem 0.75rem; flex: 1; display: flex; flex-direction: column; }
        .merch-card-title { color: #0B1D4A; font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; }
        .merch-card-description { color: #64748b; font-size: 0.85rem; line-height: 1.4; margin-bottom: 0.75rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; flex: 1; }
        .merch-card-footer { display: flex; align-items: center; justify-content: space-between; margin-top: auto; padding-top: 0.5rem; }
        .merch-card-price { font-size: 1.3rem; font-weight: 700; color: #D4AF37; }
        .merch-card-btn { display: inline-flex; align-items: center; gap: 0.4rem; border: 1px solid #D4AF37; color: #0B1D4A; background: transparent; border-radius: 999px; padding: 0.45rem 1rem; font-weight: 600; text-decoration: none; font-size: 0.85rem; transition: all 0.2s ease; }
        .merch-card-btn:hover { background: #D4AF37; color: #fff; }
        .merch-card-btn i { font-size: 0.8rem; }
        .no-products { text-align: center; padding: 4rem 1rem; color: #6b7280; }
        .no-products i { font-size: 3rem; margin-bottom: 1rem; color: #cbd5e1; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <section class="merch-banner">
        <h1>IECEP‑LSC Merchandise</h1>
        <p>Support the chapter and look great with official IECEP-LSC merchandise. All proceeds go directly to chapter activities and initiatives.</p>
    </section>

    <section class="merch-store">
        <div class="merch-grid">
            <?php if (!empty($merchItems)): ?>
                <?php foreach ($merchItems as $item): ?>
                    <?php
                        $imageUrl = trim((string)($item['image_url'] ?? ''));
                        $itemName = htmlspecialchars($item['name'] ?? 'Untitled Item');
                        $itemDesc  = htmlspecialchars($item['description'] ?? '');
                        $itemPrice = number_format((float)($item['price'] ?? 0), 2);
                        $itemId    = $item['id'] ?? '';
                    ?>
                    <article class="merch-card">
                        <div class="merch-card-image">
                            <?php if ($imageUrl !== ''): ?>
                                <img src="<?= h($imageUrl) ?>" alt="<?= $itemName ?>" loading="lazy">
                            <?php else: ?>
                                <div class="merch-card-image-placeholder">
                                    <i class="fas fa-tshirt"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="merch-card-body">
                            <h3 class="merch-card-title"><?= $itemName ?></h3>
                            <p class="merch-card-description"><?= $itemDesc ?></p>
                            <div class="merch-card-footer">
                                <span class="merch-card-price">₱<?= $itemPrice ?></span>
                                <a href="<?= BASE_URL ?>/public/order-merch.php?id=<?= urlencode($itemId) ?>" class="merch-card-btn">
                                    Order Now <i class="fas fa-shopping-cart" aria-hidden="true"></i>
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-products">
                    <i class="fas fa-box-open"></i>
                    <h3>No merchandise available at this time.</h3>
                    <p style="margin-top:0.5rem">Check back soon for new items!</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
