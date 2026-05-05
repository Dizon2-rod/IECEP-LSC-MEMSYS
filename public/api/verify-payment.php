<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../../includes/supabase.php';

use App\Lib\Supabase;

$sb = new Supabase();

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
    }

    // Transaction verification now database-only
    echo json_encode([
        'success' => true,
        'transaction' => $transaction,
        'verified' => !empty($transaction),
    ]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
