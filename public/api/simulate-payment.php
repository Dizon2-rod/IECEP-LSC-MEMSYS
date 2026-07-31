<?php
require_once __DIR__ . '/bootstrap.php';

ini_set('error_log', __DIR__ . '/../../logs/error.log');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../src/lib/SupabaseClient.php';

$input = json_decode(file_get_contents('php://input'), true);

if ($input === null || !isset($input['csrf_token'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request: CSRF token required']);
    exit;
}

$csrfValid = validate_csrf($input['csrf_token']);

if (!$csrfValid) {
    error_log("CSRF validation failed: sent=" . substr($input['csrf_token'], 0, 20) . '... session=' . (isset($_SESSION['csrf_token']) ? substr($_SESSION['csrf_token'], 0, 20) . '...' : 'NOT SET') . ' session_id=' . session_id() . ' app_env=' . (defined('APP_ENV') ? APP_ENV : 'NOT SET'));

    if (!defined('APP_ENV') || APP_ENV !== 'production') {
        error_log("CSRF bypassed in development mode");
    } else {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}

$required = ['total_fee', 'affiliation_fee', 'operational_fee', 'membership_total', 'member_count'];
foreach ($required as $field) {
    if (!array_key_exists($field, $input)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing field: ' . $field]);
        exit;
    }
}

try {
    $totalFee        = floatval($input['total_fee']);
    $affiliationFee  = floatval($input['affiliation_fee']);
    $operationalFee  = floatval($input['operational_fee']);
    $membershipTotal = floatval($input['membership_total']);
    $memberCount     = intval($input['member_count']);
    $newMembers      = intval($input['new_members'] ?? 0);
    $oldMembers      = intval($input['old_members'] ?? 0);

    $receiptNumber = 'RCP-' . date('Y') . '-' . str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);

    $_SESSION['affiliation_payment'] = [
        'receipt_number'      => $receiptNumber,
        'total_fee'           => $totalFee,
        'affiliation_fee'     => $affiliationFee,
        'operational_fee'     => $operationalFee,
        'membership_total'    => $membershipTotal,
        'member_count'        => $memberCount,
        'new_members'         => $newMembers,
        'old_members'         => $oldMembers,
        'payment_method'      => 'gcash_simulated',
        'payment_date'        => date('Y-m-d H:i:s'),
        'is_simulated'        => true,
    ];

    $config = require __DIR__ . '/../../includes/supabase.php';
    $supabase = new SupabaseClient($config['url'], $config['service_role_key'] ?? $config['anon_key']);

    try {
        $supabase->insert('transactions', [
            'type'              => 'membership_fee',
            'transaction_type'  => 'affiliation_fee',
            'receipt_number'    => $receiptNumber,
            'amount'            => $totalFee,
            'payment_method'    => 'online_payment',
            'status'            => 'paid',
            'metadata'          => json_encode([
                'is_simulated'       => true,
                'simulated_method'   => 'gcash',
                'affiliation_fee'    => $affiliationFee,
                'operational_fee'    => $operationalFee,
                'membership_total'   => $membershipTotal,
                'member_count'       => $memberCount,
                'new_members'        => $newMembers,
                'old_members'        => $oldMembers,
            ]),
            'created_at'        => date('Y-m-d\TH:i:s\Z'),
        ]);
        error_log("Simulated payment transaction stored: $receiptNumber");
    } catch (\Exception $e) {
        error_log("Simulated payment - transaction store fallback: " . $e->getMessage());
    }

    error_log("Payment simulation completed for receipt: $receiptNumber, amount: $totalFee");

    echo json_encode([
        'success'         => true,
        'receipt_number'  => $receiptNumber,
        'message'         => 'GCash payment simulation successful. Receipt ' . $receiptNumber . ' generated.',
        'amount'          => $totalFee,
    ]);

} catch (\Exception $e) {
    error_log("Simulate payment error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred during payment simulation. Please try again.',
    ]);
}
