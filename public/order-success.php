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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/font-awesome.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/styles.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fb; color: #1f2937; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; }
        .confirmation-card { background: #fff; border-radius: 24px; box-shadow: 0 30px 70px rgba(11,29,74,0.1); border: 1px solid #eef2f7; max-width: 500px; width: 100%; overflow: hidden; }
        .confirmation-header { background: linear-gradient(135deg, #0B1D4A 0%, #1A3A8A 100%); color: #fff; padding: 2rem; text-align: center; }
        .confirmation-header .icon { font-size: 3rem; margin-bottom: 1rem; }
        .confirmation-header h1 { font-size: 1.6rem; font-weight: 700; }
        .confirmation-body { padding: 2rem; }
        .detail-row { display: flex; justify-content: space-between; padding: 0.7rem 0; border-bottom: 1px solid #f1f5f9; }
        .detail-label { color: #64748b; font-size: 0.9rem; }
        .detail-value { font-weight: 600; color: #0B1D4A; }
        .total-row { font-size: 1.2rem; font-weight: 700; color: #D4AF37; }
        .back-home { text-align: center; margin-top: 1.5rem; }
        .back-home a { display: inline-flex; align-items: center; gap: 0.5rem; background: #0B1D4A; color: #fff; text-decoration: none; padding: 0.6rem 1.5rem; border-radius: 999px; font-weight: 600; transition: all 0.2s; }
        .back-home a:hover { background: #D4AF37; color: #0B1D4A; }
    </style>
</head>
<body>
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
</body>
</html>
