<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../../includes/supabase.php';
require_once __DIR__ . '/../../includes/config.php';

use App\Lib\Supabase;

$sb = new Supabase();

try {
    $memberId = $_GET['id'] ?? '';
    $scannedHash = $_GET['hash'] ?? '';

    if (!empty($scannedHash) && isset($GLOBALS['blockchain']) && $GLOBALS['blockchain'] instanceof \App\Lib\BlockchainService) {
        $verified = $GLOBALS['blockchain']->verifyDigitalId($scannedHash);
        echo json_encode(['success' => $verified, 'verified' => $verified, 'hash' => $scannedHash]);
        exit;
    }

    if (empty($memberId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Member ID required']);
        exit;
    }

    $result = $sb->from('members')
        ->select('id, full_name, email, member_type, payment_status, digital_id_url, institutions(name)')
        ->eq('id', $memberId)
        ->get(true);

    if ($result['error'] || empty($result['data'])) {
        http_response_code(404);
        echo json_encode(['error' => 'Member not found']);
        exit;
    }

    $member = $result['data'][0];

    echo json_encode([
        'success' => true,
        'member' => [
            'id' => $member['id'],
            'full_name' => $member['full_name'],
            'member_type' => $member['member_type'],
            'payment_status' => $member['payment_status'],
            'institution' => $member['institutions']['name'] ?? 'N/A',
            'digital_id_url' => $member['digital_id_url'],
            'short_id' => substr($member['id'], 0, 8),
        ],
    ]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
