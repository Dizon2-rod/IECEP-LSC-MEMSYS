<?php
require_once __DIR__ . '/../../includes/lib/Blockchain.php';

header('Content-Type: application/json');

$storageFile = __DIR__ . '/../../storage/payments_chain.json';
$blockchain = new Blockchain(3, $storageFile);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET' && $action === 'verify') {
    $receiptId = $_GET['receipt_id'] ?? '';
    if (!$receiptId) {
        echo json_encode(['error' => 'Receipt ID required']);
        exit;
    }
    $block = $blockchain->findBlockByReceiptId($receiptId);
    if ($block) {
        echo json_encode([
            'found' => true,
            'block' => [
                'index' => $block->index,
                'hash' => $block->hash,
                'previousHash' => $block->previousHash,
                'data' => $block->data,
                'timestamp' => $block->timestamp
            ]
        ]);
    } else {
        echo json_encode(['found' => false]);
    }
    exit;
}

if ($method === 'GET' && $action === 'status') {
    $chain = $blockchain->getChain();
    $output = [];
    foreach ($chain as $block) {
        $output[] = [
            'index'        => $block->index,
            'hash'         => $block->hash,
            'previousHash' => $block->previousHash,
            'data'         => $block->data,
            'timestamp'    => $block->timestamp
        ];
    }
    echo json_encode([
        'chain' => $output,
        'valid' => $blockchain->isChainValid(),
        'length' => count($output)
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed or invalid action']);
