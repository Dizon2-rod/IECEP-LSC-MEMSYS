<?php
/**
 * Verify Affiliation Payment
 * POST /api/treasurer/verify-payment.php
 * 
 * Updates payment status from 'pending_verification' to 'verified'.
 * Generates receipt PDF and creates blockchain record stub.
 * Accessible only to treasurer, admin, and super_admin roles.
 * 
 * SOURCE: Deliverable 3.5 - Verify Payment API
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

// Load authentication and config
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../../src/lib/SupabaseClient.php';

// Verify role: treasurer, admin, or super_admin
require_role(['treasurer', 'admin', 'super_admin']);

// Get Supabase client (using anon key for now; could use service role for certain operations)
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

    // 3. Validate input
    if (empty($input['pending_affiliation_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing pending_affiliation_id']);
        exit;
    }

    $pendingAffiliationId = $input['pending_affiliation_id'];

    // 4. Fetch the pending affiliation record
    $affiliationResult = $supabase->from('pending_affiliations')
        ->select('*')
        ->eq('id', $pendingAffiliationId)
        ->single();

    if (!$affiliationResult) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Affiliation not found']);
        exit;
    }

    // 5. Verify it is in pending_verification status
    if ($affiliationResult['payment_status'] !== 'pending_verification') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Payment is not pending verification. Current status: ' . $affiliationResult['payment_status']
        ]);
        exit;
    }

    // 6. Update pending_affiliations.payment_status = 'verified'
    $updateAffiliationResult = $supabase->from('pending_affiliations')
        ->update(['payment_status' => 'verified'])
        ->eq('id', $pendingAffiliationId)
        ->update(false);

    if (!$updateAffiliationResult) {
        throw new Exception('Failed to update affiliation payment status');
    }

    // 7. Find and update linked transaction.status = 'completed'
    if (!empty($affiliationResult['total_fee'])) {
        $transactionResult = $supabase->from('transactions')
            ->select('*')
            ->eq('pending_affiliation_id', $pendingAffiliationId)
            ->eq('status', 'pending')
            ->single();

        if ($transactionResult && isset($transactionResult['id'])) {
            $supabase->from('transactions')
                ->update(['status' => 'completed'])
                ->eq('id', $transactionResult['id'])
                ->update(false);
        }
    }

    // 8. Generate receipt PDF (call generate-receipt.php internally)
    // For now, we just mark where the receipt would be stored
    // In production, you would call the generate-receipt endpoint or function
    // and store the file path in transactions.receipt_url

    // 9. Insert blockchain_records stub row
    $blockchainResult = $supabase->from('blockchain_records')
        ->insert([
            'record_type' => 'transaction',
            'reference_id' => $affiliationResult['id'] ?? null,
            'institution_id' => $affiliationResult['institution_id'] ?? null,
            'data_hash' => hash('sha256', json_encode($affiliationResult)),
            'block_index' => 0,
            'created_at' => date('c')
        ])
        ->create(false);

    // 10. Log to audit_logs
    $user = get_user_info();
    audit_log(
        $user['user_id'] ?? null,
        'payment_verified',
        'affiliation_payment',
        $pendingAffiliationId,
        null,
        ['payment_status' => 'verified']
    );

    // 11. Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Payment verified. Application is now pending document review.'
    ]);

} catch (Exception $e) {
    error_log('Payment verification error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error during payment verification']);
}
