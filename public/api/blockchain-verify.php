<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../autoload.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$recordType = $_GET['type'] ?? '';
$referenceId = $_GET['id'] ?? '';

if (empty($recordType) || empty($referenceId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'type and id parameters required']);
    exit;
}

$supabaseConfig = require __DIR__ . '/../../includes/supabase.php';
$supabase = new \App\Lib\SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);
$blockchain = new \App\Lib\BlockchainService($supabase);

try {
    // Get blockchain records for this reference
    $records = $supabase->select('blockchain_records', [
        'record_type' => 'eq.' . $recordType,
        'reference_id' => 'eq.' . $referenceId,
        'order' => 'created_at.desc',
        'limit' => 1
    ]);

    if (empty($records)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'verified' => false,
            'message' => 'No blockchain record found'
        ]);
        exit;
    }

    $record = $records[0];

    // Verify integrity
    $isValid = $blockchain->verify($recordType, $referenceId);

    // Verify chain integrity for the record type
    $chainVerification = $blockchain->verifyChain($recordType);

    echo json_encode([
        'success' => true,
        'verified' => $isValid && $chainVerification['valid'],
        'record' => [
            'type' => $record['record_type'],
            'reference_id' => $record['reference_id'],
            'data_hash' => $record['data_hash'],
            'previous_hash' => $record['previous_hash'],
            'created_at' => $record['created_at'],
            'metadata' => $record['metadata'] ?? null
        ],
        'chain_integrity' => [
            'valid' => $chainVerification['valid'],
            'total_records' => $chainVerification['total_records'],
            'tampered_count' => count($chainVerification['tampered'])
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'verified' => false,
        'message' => $e->getMessage()
    ]);
}
