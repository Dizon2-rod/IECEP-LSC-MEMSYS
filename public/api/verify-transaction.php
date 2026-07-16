<?php
/**
 * Verify Transaction API
 * Public endpoint for verifying transaction hashes against the blockchain
 */

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');

try {
    $hash = $_GET['hash'] ?? '';
    
    if (empty($hash)) {
        http_response_code(400);
        echo json_encode([
            'valid' => false,
            'message' => 'Hash parameter is required'
        ]);
        exit;
    }
    
    $db = $GLOBALS['supabaseClient'] ?? null;
    if (!$db) {
        throw new Exception('Database connection not available');
    }
    
    // Search blockchain records for payment transactions with matching hash
    $blockchainRecords = $db->select('blockchain_records', [
        'record_type' => 'eq.payment',
        'data_hash' => 'eq.' . $hash,
        'limit' => 1
    ]);
    
    if (empty($blockchainRecords)) {
        echo json_encode([
            'valid' => false,
            'message' => 'Transaction not found on blockchain'
        ]);
        exit;
    }
    
    $record = $blockchainRecords[0];
    
    // Parse the data_json to get transaction details
    $data = json_decode($record['data_json'] ?? '{}', true);
    
    // Get additional transaction details if available
    $transactionDetails = [];
    if (!empty($data['receipt_number'])) {
        $transactions = $db->select('transactions', [
            'receipt_number' => 'eq.' . $data['receipt_number'],
            'limit' => 1
        ]);
        
        if (!empty($transactions)) {
            $tx = $transactions[0];
            
            // Get institution name (public info)
            $institutionName = 'Unknown';
            if (!empty($tx['institution_id'])) {
                $institutions = $db->select('institutions', [
                    'id' => 'eq.' . $tx['institution_id'],
                    'select' => 'name',
                    'limit' => 1
                ]);
                if (!empty($institutions)) {
                    $institutionName = $institutions[0]['name'] ?? 'Unknown';
                }
            }
            
            $transactionDetails = [
                'amount' => (float)($tx['amount'] ?? 0),
                'date' => $tx['created_at'] ?? $record['created_at'],
                'status' => $tx['status'] ?? 'paid',
                'type' => $tx['type'] ?? 'membership_fee',
                'institution_name' => $institutionName,
                'receipt_number' => $tx['receipt_number'] ?? ''
            ];
        }
    }
    
    // Return verification result with public information only
    echo json_encode([
        'valid' => true,
        'message' => 'Transaction verified on blockchain',
        'blockchain_record' => [
            'record_id' => $record['id'],
            'record_type' => $record['record_type'],
            'reference_id' => $record['reference_id'],
            'created_at' => $record['created_at'],
            'previous_hash' => $record['previous_hash']
        ],
        'transaction' => $transactionDetails,
        'verification_status' => 'verified'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'valid' => false,
        'message' => 'Verification failed: ' . $e->getMessage()
    ]);
}
