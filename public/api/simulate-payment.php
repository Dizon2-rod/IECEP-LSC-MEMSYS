<?php
/**
 * Simulate GCash Payment
 * POST /api/simulate-payment.php
 * 
 * Simulates a GCash payment and generates server-side reference/receipt numbers.
 * Enforces rate limiting (3 attempts per session) and session-tied payment tokens.
 * 
 * SOURCE: Deliverable 3.2 - Simulate Payment API
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

// Load helpers
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../src/lib/SupabaseClient.php';

// Get Supabase client
$config = require __DIR__ . '/../../includes/supabase.php';
$supabase = new SupabaseClient($config['url'], $config['anon_key']);

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // 1. Parse JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // 2. Validate CSRF token
    if (!isset($input['csrf_token']) || !hash_equals(csrf_field_value(), $input['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    // 3. Check if fee calculation exists and is not expired (30 minutes)
    if (!isset($_SESSION['affiliation_fee_calc'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'fee_calculation_expired']);
        exit;
    }

    $calcTime = $_SESSION['affiliation_fee_calc']['timestamp'];
    if (time() - $calcTime > 1800) { // 30 minutes
        unset($_SESSION['affiliation_fee_calc']);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'fee_calculation_expired']);
        exit;
    }

    // 4. Check rate limit: max 3 simulation attempts per session
    if (!isset($_SESSION['payment_simulation_attempts'])) {
        $_SESSION['payment_simulation_attempts'] = 0;
    }

    $_SESSION['payment_simulation_attempts']++;

    if ($_SESSION['payment_simulation_attempts'] > 3) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'rate_limit_exceeded']);
        exit;
    }

    // 5. Generate server-side reference and receipt numbers
    $paymentReference = 'GCASH-' . date('Ymd') . '-' . str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    $receiptNumber = 'RCP-' . date('Y') . '-' . str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    $simulationToken = bin2hex(random_bytes(32));

    // 6. Create transaction record with pending status
    $feeCalc = $_SESSION['affiliation_fee_calc'];
    
    $transactionResult = $supabase->from('transactions')
        ->insert([
            'payment_method' => 'gcash_simulated',
            'payment_reference' => $paymentReference,
            'receipt_number' => $receiptNumber,
            'amount' => $feeCalc['total_fee'],
            'transaction_type' => 'affiliation_fee',
            'status' => 'pending',
            'created_at' => date('c')
        ])
        ->create(false);

    if (!$transactionResult) {
        throw new Exception('Failed to create transaction record');
    }

    $transactionId = $transactionResult['id'] ?? null;

    // 7. Store in session
    $_SESSION['affiliation_payment'] = [
        'timestamp' => time(),
        'payment_reference' => $paymentReference,
        'receipt_number' => $receiptNumber,
        'transaction_id' => $transactionId,
        'simulation_token' => $simulationToken,
        'total_fee' => $feeCalc['total_fee'],
        'member_count' => $feeCalc['member_count'],
        'affiliation_fee' => $feeCalc['affiliation_fee'],
        'operational_fee' => $feeCalc['operational_fee'],
        'membership_fees_total' => $feeCalc['membership_fees_total']
    ];

    // 8. Log to audit_logs
    audit_log(
        null,
        'payment_simulated',
        'affiliation_payment',
        null,
        null,
        ['payment_reference' => $paymentReference, 'total_fee' => $feeCalc['total_fee']]
    );

    // 9. Return response
    echo json_encode([
        'success' => true,
        'payment_reference' => $paymentReference,
        'receipt_number' => $receiptNumber,
        'total_fee' => round($feeCalc['total_fee'], 2),
        'message' => 'SIMULATION ONLY — No real money was charged.'
    ]);

} catch (Exception $e) {
    error_log('Payment simulation error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error during payment simulation']);
}
