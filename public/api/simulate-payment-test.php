<?php
require_once __DIR__ . '/bootstrap.php';
// Minimal test version
session_start();
header('Content-Type: application/json');

// Capture all output
ob_start();

try {
    // Only allow POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }
    
    // Parse JSON input
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input');
    }
    
    // Get fee data
    $totalFee = floatval($input['total_fee'] ?? 0);
    $memberCount = intval($input['member_count'] ?? 0);
    
    if ($totalFee <= 0 || $memberCount <= 0) {
        throw new Exception('Invalid fee data');
    }
    
    // Generate mock payment data
    $paymentReference = 'GCASH-' . date('Ymd') . '-' . str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    $receiptNumber = 'RCP-' . date('Y') . '-' . str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    
    // Store in session
    $_SESSION['affiliation_payment'] = [
        'timestamp' => time(),
        'payment_reference' => $paymentReference,
        'receipt_number' => $receiptNumber,
        'total_fee' => $totalFee
    ];
    
    // Clear any buffered output
    ob_end_clean();
    
    // Return success
    echo json_encode([
        'success' => true,
        'payment_reference' => $paymentReference,
        'receipt_number' => $receiptNumber,
        'total_fee' => round($totalFee, 2),
        'message' => '⚠️ SIMULATION ONLY — No real money was charged.'
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
