<?php
require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../../includes/supabase.php';
require_once __DIR__ . '/../../src/lib/BlockchainService.php';

$supabase = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);
$blockchain = new \App\Lib\BlockchainService($supabase);

try {
    $memberId = trim($_GET['id'] ?? '');
    $scannedHash = trim($_GET['hash'] ?? '');

    if (!empty($scannedHash)) {
        // Verify by hash - check blockchain records
        $blockchainRecords = $supabase->select('blockchain_records', [
            'data_hash' => 'eq.' . $scannedHash,
            'limit' => 1,
        ]);
        
        if (!empty($blockchainRecords)) {
            $record = $blockchainRecords[0];
            $referenceId = $record['reference_id'] ?? null;
            
            // Try to find member by reference ID or membership ID
            $member = null;
            if ($referenceId) {
                $member = $supabase->select('members', [
                    'id' => 'eq.' . $referenceId,
                    'limit' => 1,
                ]);
                if (empty($member)) {
                    $member = $supabase->select('members', [
                        'membership_id' => 'eq.' . $referenceId,
                        'limit' => 1,
                    ]);
                }
            }
            
            if (!empty($member)) {
                $member = $member[0];
                $blockchain_verified = $blockchain->verify($record['record_type'], $referenceId);
                
                echo json_encode([
                    'success' => true,
                    'verified' => $blockchain_verified,
                    'hash' => $scannedHash,
                    'member' => [
                        'id' => $member['id'],
                        'full_name' => $member['full_name'],
                        'member_type' => $member['member_type'] ?? 'new',
                        'payment_status' => $member['payment_status'],
                        'institution' => $member['institution_id'] ?? 'N/A',
                        'digital_id_url' => $member['digital_id_url'] ?? null,
                        'digital_id_hash' => $member['digital_id_hash'] ?? null,
                        'short_id' => substr($member['id'], 0, 8),
                        'blockchain_verified' => $blockchain_verified,
                    ],
                ]);
                exit;
            }
        }
        echo json_encode(['success' => false, 'verified' => false, 'hash' => $scannedHash]);
        exit;
    }

    if (empty($memberId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Member ID required']);
        exit;
    }

    // Search by membership ID or member ID
    if (preg_match('/^IECEP-\d{4}-\d{4}$/', $memberId)) {
        $memberResult = $supabase->select('members', [
            'membership_id' => 'eq.' . $memberId,
            'limit' => 1,
        ]);
    } else {
        $memberResult = $supabase->select('members', [
            'id' => 'eq.' . $memberId,
            'limit' => 1,
        ]);
    }

    if (empty($memberResult)) {
        http_response_code(404);
        echo json_encode(['error' => 'Member not found']);
        exit;
    }

    $member = $memberResult[0];

    // Check blockchain verification for membership and digital_id
    $blockchain_verified = false;
    
    // Check membership blockchain record
    $membershipVerified = $blockchain->verify('membership', $member['id']);
    
    // Check digital_id blockchain record
    $digitalIdVerified = $blockchain->verify('digital_id', $member['id']);
    
    $blockchain_verified = $membershipVerified || $digitalIdVerified;

    echo json_encode([
        'success' => true,
        'member' => [
            'id' => $member['id'],
            'full_name' => $member['full_name'],
            'member_type' => $member['member_type'] ?? 'new',
            'payment_status' => $member['payment_status'],
            'institution' => $member['institution_id'] ?? 'N/A',
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
