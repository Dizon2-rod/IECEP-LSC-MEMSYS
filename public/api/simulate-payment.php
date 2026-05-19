<?php
require_once __DIR__ . '/bootstrap.php';
/**
 * GCash Simulator (Mock Payment - DEMONSTRATION ONLY)
 * POST /api/simulate-payment.php
 * 
 * ⚠️ IMPORTANT: This is a SIMULATION for demonstration purposes only.
 * NO real payment processing occurs. NO actual money will be deducted.
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/error.log');

// Start output buffering BEFORE session
while (ob_get_level()) ob_end_clean();
ob_start();

// Set headers IMMEDIATELY
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

@session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }
    
    // Validate CSRF token
    $csrfToken = $input['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        throw new Exception('Invalid CSRF token');
    }
    
    $totalFee = floatval($input['total_fee'] ?? 0);
    $affiliationFee = floatval($input['affiliation_fee'] ?? 0);
    $operationalFee = floatval($input['operational_fee'] ?? 800);
    $membershipTotal = floatval($input['membership_total'] ?? 0);
    $memberCount = intval($input['member_count'] ?? 0);
    $newMembers = intval($input['new_members'] ?? 0);
    $oldMembers = intval($input['old_members'] ?? 0);
    
    if ($totalFee <= 0 || $memberCount <= 0) {
        throw new Exception('Invalid fee data: totalFee=' . $totalFee . ', memberCount=' . $memberCount);
    }
    
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

    // Rate limiting with time-based reset (3 attempts per hour)
    $currentTime = time();
    $rateLimitWindow = 3600; // 1 hour
    $maxAttempts = 3;

    if (!isset($_SESSION['payment_simulation_attempts'])) {
        $_SESSION['payment_simulation_attempts'] = 0;
        $_SESSION['payment_simulation_first_attempt'] = $currentTime;
    }

    // Reset counter if window has passed
    if (isset($_SESSION['payment_simulation_first_attempt']) && 
        ($currentTime - $_SESSION['payment_simulation_first_attempt']) > $rateLimitWindow) {
        $_SESSION['payment_simulation_attempts'] = 0;
        $_SESSION['payment_simulation_first_attempt'] = $currentTime;
    }

    $_SESSION['payment_simulation_attempts']++;

    if ($_SESSION['payment_simulation_attempts'] > $maxAttempts) {
        throw new Exception('Rate limit exceeded. Please try again after ' . 
            round(($rateLimitWindow - ($currentTime - $_SESSION['payment_simulation_first_attempt'])) / 60) . 
            ' minutes.');
    }

    $paymentReference = 'GCASH-' . date('Ymd') . '-' . str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    $receiptNumber = 'RCP-' . date('Y') . '-' . str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    $simulationToken = bin2hex(random_bytes(32));

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

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'payment_reference' => $paymentReference,
        'receipt_number' => $receiptNumber,
        'total_fee' => round($totalFee, 2),
        'message' => '⚠️ SIMULATION ONLY — No real money was charged. This is a demonstration of the payment workflow.'
    ]);
    exit;

} catch (Exception $e) {
    ob_end_clean();
    error_log('Payment simulation error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
} catch (Throwable $t) {
    ob_end_clean();
    error_log('Payment simulation fatal error: ' . $t->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
    exit;
}
