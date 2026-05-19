<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/lib/Blockchain.php';

$receiptId = $_GET['receipt_id'] ?? '';
$foundBlock = null;
$error = '';

if ($receiptId) {
    $storageFile = __DIR__ . '/../storage/payments_chain.json';
    if (!file_exists($storageFile)) {
        $error = 'Blockchain data not found.';
    } else {
        $blockchain = new Blockchain(3, $storageFile);
        $foundBlock = $blockchain->findBlockByReceiptId($receiptId);
        if (!$foundBlock) {
            $error = 'Receipt ID not found in the blockchain.';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Blockchain Payment Verification - IECEP-LSC MEMSYS</title>
    <style>
        body { font-family: 'Inter', sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f7fa; }
        .verified { background: #d4edda; color: #155724; padding: 20px; border-radius: 12px; border-left: 5px solid #28a745; }
        .not-found { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 12px; border-left: 5px solid #dc3545; }
        pre { background: #fff; padding: 15px; border-radius: 8px; overflow-x: auto; border: 1px solid #ddd; }
        input, button { padding: 10px 15px; border-radius: 8px; border: 1px solid #ccc; }
        button { background: #0A2F6C; color: white; cursor: pointer; }
    </style>
</head>
<body>
    <h1>🔗 Blockchain Payment Verification</h1>
    <form method="GET">
        <label>Enter Receipt ID:</label><br>
        <input type="text" name="receipt_id" value="<?= htmlspecialchars($receiptId) ?>" required style="width: 70%;">
        <button type="submit">Verify</button>
    </form>

    <?php if ($receiptId): ?>
        <?php if ($foundBlock): ?>
            <div class="verified">
                <h3>✅ Payment Verified on Blockchain</h3>
                <p><strong>Receipt ID:</strong> <?= htmlspecialchars($receiptId) ?></p>
                <p><strong>Block Index:</strong> <?= $foundBlock->index ?></p>
                <p><strong>Block Hash:</strong> <code><?= $foundBlock->hash ?></code></p>
                <p><strong>Previous Block Hash:</strong> <code><?= $foundBlock->previousHash ?></code></p>
                <p><strong>Timestamp:</strong> <?= $foundBlock->timestamp ?></p>
                <p><strong>Stored Data:</strong></p>
                <pre><?= json_encode($foundBlock->data, JSON_PRETTY_PRINT) ?></pre>
            </div>
        <?php else: ?>
            <div class="not-found">
                <h3>❌ Payment Not Found</h3>
                <p>No block contains receipt ID: <?= htmlspecialchars($receiptId) ?></p>
                <?php if ($error): ?><p><?= $error ?></p><?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>
