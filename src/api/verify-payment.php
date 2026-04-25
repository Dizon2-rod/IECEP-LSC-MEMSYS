<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../lib/supabase.php';
require_once __DIR__ . '/../lib/blockchain.php';

use App\Lib\Supabase;
use App\Lib\BlockchainService;

$sb = new Supabase();
$blockchain = new BlockchainService();

try {
    $receiptId = $_GET['receipt_id'] ?? '';
    $txHash = $_GET['tx_hash'] ?? '';

    if (empty($receiptId) && empty($txHash)) {
        http_response_code(400);
        echo json_encode(['error' => 'Receipt ID or Transaction Hash required']);
        exit;
    }

    $transaction = null;

    if (!empty($receiptId)) {
        $result = $sb->from('transactions')
            ->select('*, institutions(name), members(full_name, email)')
            ->eq('receipt_id', $receiptId)
            ->get(true);

        if ($result['error'] || empty($result['data'])) {
            http_response_code(404);
            echo json_encode(['error' => 'Transaction not found']);
            exit;
        }

        $transaction = $result['data'][0];
        $txHash = $transaction['blockchain_tx_hash'];
    } elseif (!empty($txHash)) {
        $result = $sb->from('transactions')
            ->select('*, institutions(name), members(full_name, email)')
            ->eq('blockchain_tx_hash', $txHash)
            ->get(true);

        if (!$result['error'] && !empty($result['data'])) {
            $transaction = $result['data'][0];
        }
    }

    // Verify on blockchain
    $bcResult = ['verified' => false];
    if (!empty($txHash)) {
        $bcResult = $blockchain->verifyTransaction($txHash);
    }

    echo json_encode([
        'success' => true,
        'transaction' => $transaction,
        'blockchain' => $bcResult,
        'etherscan_url' => $txHash ? "https://sepolia.etherscan.io/tx/$txHash" : null,
    ]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
