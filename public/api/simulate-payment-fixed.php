<?php
require_once __DIR__ . '/bootstrap.php';
/**
 * GCash Simulator (Mock Payment - DEMONSTRATION ONLY)
 * POST /api/simulate-payment.php
 * 
 * ⚠️ IMPORTANT: This is a SIMULATION for demonstration purposes only.
 * NO real payment processing occurs. NO actual money will be deducted.
 */

session_start();
header('Content-Type: application/json');
ob_start();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // 1. Parse JSON input
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }
    
    // 2. Get fee data from input
    $totalFee = floatval($input['total_fee'] ?? 0);
    $affiliationFee = floatval($input['affiliation_fee'] ?? 0);
    $operationalFee = floatval($input['operational_fee'] ?? 800);
    $membershipTotal = floatval($input['membership_total'] ?? 0);
    $memberCount = intval($input['member_count'] ?? 0);
    $newMembers = intval($input['new_members'] ?? 0);
    $oldMembers = intval($input['old_members'] ?? 0);
    
    // 3. Validate required fields
    if ($totalFee <= 0 || $memberCount <= 0) {
        throw new Exception('Invalid fee data: totalFee=' . $totalFee . ', memberCount=' . $memberCount);
    }
    
    // 4. Store fee calculation in session
    $_SESSION['affiliation_fee_calc'] = [
        'timestamp' => time(),
        'member_count' => $memberCount,
        'new_members' => $newMembers,
        'old_members' => $oldMembers,
        'affiliation_fee' => $affiliationFee,
        'operational_fee' => $operationalFee,
        'membership_fees_total' => $membershipTotal,
        'total_fee' => $totalFee
    ];

    // 5. Check rate limit: max 3 simulation attempts per session
    if (!isset($_SESSION['payment_simulation_attempts'])) {
        $_SESSION['payment_simulation_attempts'] = 0;
    }

    $_SESSION['payment_simulation_attempts']++;

    if ($_SESSION['payment_simulation_attempts'] > 3) {
        throw new Exception('Rate limit exceeded');
    }

    // 6. Generate server-side reference and receipt numbers
    $paymentReference = 'GCASH-' . date('Ymd') . '-' . str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    $receiptNumber = 'RCP-' . date('Y') . '-' . str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    $simulationToken = bin2hex(random_bytes(32));

    // 7. Store in session
    $_SESSION['affiliation_payment'] = [
        'timestamp' => time(),
        'payment_reference' => $paymentReference,
        'receipt_number' => $receiptNumber,
        'simulation_token' => $simulationToken,
        'total_fee' => $totalFee,
        'member_count' => $memberCount,
        'affiliation_fee' => $affiliationFee,
        'operational_fee' => $operationalFee,
        'membership_fees_total' => $membershipTotal
    ];

    // 8. Clear buffer and return success
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'payment_reference' => $paymentReference,
        'receipt_number' => $receiptNumber,
        'total_fee' => round($totalFee, 2),
        'message' => '⚠️ SIMULATION ONLY — No real money was charged. This is a demonstration of the payment workflow.'
    ]);

} catch (Exception $e) {
    ob_end_clean();
    error_log('Payment simulation error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error during payment simulation',
        'details' => $e->getMessage()
    ]);
}
