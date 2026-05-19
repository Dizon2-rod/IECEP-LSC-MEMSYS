<?php
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../../includes/supabase.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../src/lib/BlockchainService.php';

use App\Lib\Supabase;

$sb = new Supabase();
$blockchain = new BlockchainService();

try {
    $memberId = trim($_GET['id'] ?? '');
    $scannedHash = trim($_GET['hash'] ?? '');

    if (!empty($scannedHash) && isset($GLOBALS['blockchain']) && $GLOBALS['blockchain'] instanceof \App\Lib\BlockchainService) {
        $verified = $GLOBALS['blockchain']->verifyDigitalId($scannedHash);
        echo json_encode(['success' => true, 'verified' => $verified, 'hash' => $scannedHash]);
        exit;
    }

    if (empty($memberId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Member ID required']);
        exit;
    }

    $query = $sb->from('members')
        ->select('id, full_name, email, member_type, payment_status, digital_id_url, digital_id_hash, institutions(name)');

    if (preg_match('/^[0-9a-fA-F]{64}$/', $memberId)) {
        $query = $query->eq('digital_id_hash', $memberId);
    } else {
        $query = $query->eq('id', $memberId);
    }

    $result = $query->get(true);
    if ($result['error'] || empty($result['data'])) {
        http_response_code(404);
        echo json_encode(['error' => 'Member not found']);
        exit;
    }

    $member = $result['data'][0];
    
    // Check blockchain verification
    $blockchain_verified = false;
    try {
        $blockchain_records = $blockchain->getRecordsByReference('member_created', $member['id']);
        if (!empty($blockchain_records)) {
            $member_hash = hash('sha256', json_encode([
                'id' => $member['id'],
                'full_name' => $member['full_name'],
                'digital_id_hash' => $member['digital_id_hash']
            ]));
            foreach ($blockchain_records as $record) {
                if ($record['data_hash'] === $member_hash || $record['data_hash'] === $member['digital_id_hash']) {
                    $blockchain_verified = true;
                    break;
                }
            }
        }
    } catch (Exception $e) {
        // Blockchain check failed, continue without verification
    }
    
    echo json_encode([
        'success' => true,
        'member' => [
            'id' => $member['id'],
            'full_name' => $member['full_name'],
            'member_type' => $member['member_type'],
            'payment_status' => $member['payment_status'],
            'institution' => $member['institutions']['name'] ?? 'N/A',
            'digital_id_url' => $member['digital_id_url'] ?? null,
            'digital_id_hash' => $member['digital_id_hash'] ?? null,
            'short_id' => substr($member['id'], 0, 8),
            'blockchain_verified' => $blockchain_verified,
        ],
    ]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
