<?php
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
        ->order('created_at', false)
        ->limit(500)
        ->get(true);

    if ($response['error']) {
        throw new Exception($response['message'] ?? 'Unable to load financial records');
    }

    $transactions = $response['data'] ?? [];
    $statusBreakdown = [];
    $monthlyData = [];
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
        $summary['total_income'] += $amount;

        if (!isset($statusBreakdown[$status])) {
            $statusBreakdown[$status] = 0;
        }
        $statusBreakdown[$status] += $amount;

        if ($status === 'completed') {
            $summary['completed_amount'] += $amount;
        } elseif ($status === 'pending') {
            $summary['pending_amount'] += $amount;
        } elseif (in_array($status, ['failed', 'cancelled'], true)) {
            $summary['failed_amount'] += $amount;
        }

        $recordedAt = $transaction['created_at'] ?? $transaction['updated_at'] ?? null;
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

    foreach ($lastTwelveMonths as $monthEntry) {
        $monthlyData[] = $monthEntry;
    }

    $latestTransactions = array_slice($transactions, 0, 20);

    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'status_breakdown' => $statusBreakdown,
        'monthly_data' => $monthlyData,
        'latest_transactions' => $latestTransactions,
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error generating financial report', 'details' => $e->getMessage()]);
    exit;
}
?>