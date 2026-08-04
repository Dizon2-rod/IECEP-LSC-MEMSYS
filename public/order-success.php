<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/../includes/csrf.php';

$orderInfo = $_SESSION['merch_order_success'] ?? null;
unset($_SESSION['merch_order_success']);

if (!$orderInfo) {
    header('Location: ' . BASE_URL);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Order Confirmation - IECEP-LSC MEMSYS</title>
    <?php include dirname(__DIR__) . '/includes/head-meta.php'; ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES) ?>/assets/css/styles.css">
    <style>
        body { background: #f5f7fb; min-height: 100vh; padding: 2rem 1rem; }
        .confirmation-wrapper { max-width: 500px; margin: 3rem auto; }
        .confirmation-card { background: var(--white); border-radius: 24px; box-shadow: 0 30px 70px rgba(11,29,74,0.1); border: 1px solid var(--neutral-200); max-width: 100%; width: 100%; overflow: hidden; }
        .confirmation-header { background: linear-gradient(135deg, var(--primary) 0%, #1A3A8A 100%); color: var(--white); padding: 2rem; text-align: center; }
        .confirmation-header .icon { font-size: 3rem; margin-bottom: 1rem; }
        .confirmation-header h1 { font-size: 1.6rem; font-weight: 700; }
        .confirmation-body { padding: 2rem; }
        .detail-row { display: flex; justify-content: space-between; padding: 0.7rem 0; border-bottom: 1px solid #f1f5f9; }
        .detail-label { color: var(--neutral-500); font-size: 0.9rem; }
        .detail-value { font-weight: 600; color: var(--primary); }
        .total-row { font-size: 1.2rem; font-weight: 700; color: var(--accent); }
        .back-home { text-align: center; margin-top: 1.5rem; }
        .back-home a { display: inline-flex; align-items: center; gap: 0.5rem; background: var(--primary); color: var(--white); text-decoration: none; padding: 0.6rem 1.5rem; border-radius: 999px; font-weight: 600; transition: all var(--transition-base); }
        .back-home a:hover { background: var(--accent); color: var(--primary); }
    </style>
</head>
<body>
    <div id="fb-root"></div>
    <script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v17.0"></script>

    <?php include dirname(__DIR__) . '/includes/navbar.php'; ?>
    <div class="confirmation-wrapper">
    <div class="confirmation-card">
        <div class="confirmation-header">
            <div class="icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Order Placed Successfully!</h1>
        </div>
        <div class="confirmation-body">
            <div class="detail-row">
                <span class="detail-label">Receipt Number</span>
                <span class="detail-value"><?= h($orderInfo['receipt_number'] ?? '') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Item</span>
                <span class="detail-value"><?= h($orderInfo['item_name'] ?? '') ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Quantity</span>
                <span class="detail-value"><?= h($orderInfo['quantity'] ?? 1) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Unit Price</span>
                <span class="detail-value">₱<?= number_format((float)($orderInfo['unit_price'] ?? 0), 2) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Order ID</span>
                <span class="detail-value"><?= substr($orderInfo['order_id'] ?? '', 0, 12) ?>…</span>
            </div>
            <div class="detail-row total-row">
                <span>Total</span>
                <span>₱<?= number_format((float)($orderInfo['total_amount'] ?? 0), 2) ?></span>
            </div>
            <div class="back-home">
                <a href="<?= BASE_URL ?>"><i class="fas fa-arrow-left"></i> Back to Homepage</a>
            </div>
        </div>
    </div>

    <?php include dirname(__DIR__) . '/includes/footer-new.php'; ?>
</body>
</html>
