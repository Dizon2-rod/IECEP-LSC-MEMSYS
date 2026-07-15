<?php
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth_check.php';
require_role(['admin']);

require_once __DIR__ . '/../../src/lib/BlockchainService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['record_type'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'record_type is required']);
    exit;
}

$recordType = $input['record_type'];

try {
    $supabase = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);
    $blockchain = new \App\Lib\BlockchainService($supabase);
    
    $result = $blockchain->verifyChain($recordType);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
