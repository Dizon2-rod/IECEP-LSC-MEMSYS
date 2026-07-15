<?php
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../src/lib/Supabase.php';
session_start();

$userRole = $_SESSION['user']['role'] ?? null;
$allowedRoles = ['eb_treasurer', 'admin', 'super_admin'];
if (!$userRole || !in_array($userRole, $allowedRoles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $sb = new \App\Lib\Supabase();
    $response = $sb->from('transactions')
        ->select('*')
        ->order('transaction_date', false)
        ->limit(1000)
        ->get(true);

    if ($response['error']) {
        throw new Exception($response['message'] ?? 'Unable to load financial records');
    }

    $transactions = $response['data'] ?? [];
    $statusTotals = [];
    $typeCounts = [];
    $summary = [
        'total_income' => 0,
        'transaction_count' => count($transactions),
        'completed_amount' => 0,
        'pending_amount' => 0,
        'failed_amount' => 0,
    ];

    $lastTwelveMonths = [];
    for ($i = 11; $i >= 0; $i--) {
        $monthKey = date('Y-m', strtotime(sprintf('-%d months', $i)));
        $lastTwelveMonths[$monthKey] = [
            'month' => $monthKey,
            'income' => 0,
            'transaction_count' => 0,
        ];
    }

    foreach ($transactions as $transaction) {
        $amount = floatval($transaction['amount'] ?? 0);
        $status = strtolower(trim($transaction['status'] ?? 'unknown'));
        $type = strtolower(trim($transaction['type'] ?? 'other'));
        $summary['total_income'] += $amount;

        $statusTotals[$status] = ($statusTotals[$status] ?? 0) + $amount;
        $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;

        if ($status === 'paid') {
            $summary['completed_amount'] += $amount;
        } elseif ($status === 'pending') {
            $summary['pending_amount'] += $amount;
        } elseif (in_array($status, ['failed', 'cancelled', 'refunded'], true)) {
            $summary['failed_amount'] += $amount;
        }

        $recordedAt = $transaction['transaction_date'] ?? $transaction['created_at'] ?? null;
        if ($recordedAt) {
            $monthKey = substr($recordedAt, 0, 7);
            if (!isset($lastTwelveMonths[$monthKey])) {
                $lastTwelveMonths[$monthKey] = [
                    'month' => $monthKey,
                    'income' => 0,
                    'transaction_count' => 0,
                ];
            }
            $lastTwelveMonths[$monthKey]['income'] += $amount;
            $lastTwelveMonths[$monthKey]['transaction_count'] += 1;
        }
    }

    $monthlyData = array_values($lastTwelveMonths);
    $latestTransactions = array_slice($transactions, 0, 20);

    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'total_income_by_status' => $statusTotals,
        'transaction_count_by_type' => $typeCounts,
        'monthly_income_data' => $monthlyData,
        'latest_transactions' => $latestTransactions,
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error generating financial report', 'details' => $e->getMessage()]);
    exit;
}
?>