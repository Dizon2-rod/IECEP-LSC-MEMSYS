<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../portal/auth_check.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_role(['admin', 'super_admin', 'committee_registration'], false);
if (!in_array($_SESSION['user']['role'], ['admin', 'super_admin', 'committee_registration'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$db = Database::getInstance();

$data = json_decode(file_get_contents('php://input'), true);
$memberId = $data['member_id'] ?? null;
$customId = $data['custom_id'] ?? null;
$isOverride = !empty($customId);

if (empty($memberId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Member ID required']);
    exit;
}

try {
    $db->beginTransaction();

    // Get member info
    $member = $db->fetchOne("SELECT * FROM members WHERE id = ?", [$memberId]);
    if (!$member) {
        throw new Exception('Member not found');
    }

    // Check if member already has an ID
    if (!empty($member['membership_id'])) {
        throw new Exception('Member already has a membership ID');
    }

    $generatedId = '';
    $currentYear = date('Y');

    if ($isOverride) {
        // Manual override
        $generatedId = $customId;
        
        // Validate format
        if (!preg_match('/^IECEP-\d{4}-\d{4}$/', $generatedId)) {
            throw new Exception('Invalid ID format. Use IECEP-YYYY-XXXX');
        }
        
        // Check uniqueness
        $existing = $db->fetchOne("SELECT id FROM members WHERE membership_id = ?", [$generatedId]);
        if ($existing) {
            throw new Exception('Membership ID already exists');
        }
    } else {
        // Auto-generate
        // Get or create counter for current year
        $counter = $db->fetchOne("SELECT * FROM member_id_counter WHERE year = ?", [$currentYear]);
        
        if (!$counter) {
            $db->insert('member_id_counter', [
                'id' => generateUUID(),
                'year' => $currentYear,
                'last_number' => 0
            ]);
            $lastNumber = 0;
        } else {
            $lastNumber = $counter['last_number'];
        }
        
        // Increment counter
        $newNumber = $lastNumber + 1;
        $db->update('member_id_counter', ['last_number' => $newNumber], 'year = ?', [$currentYear]);
        
        // Generate ID: IECEP-YYYY-XXXX (4-digit padded number)
        $generatedId = sprintf('IECEP-%s-%04d', $currentYear, $newNumber);
    }

    // Update member with new ID
    $db->update('members', ['membership_id' => $generatedId], 'id = ?', [$memberId]);

    // Log to blockchain
    $blockchainId = generateUUID();
    $transactionHash = hash('sha256', $generatedId . time() . $memberId);
    
    $db->insert('blockchain_records', [
        'id' => $blockchainId,
        'entity_type' => 'membership_id',
        'entity_id' => $memberId,
        'transaction_hash' => $transactionHash,
        'record_hash' => hash('sha256', json_encode(['membership_id' => $generatedId, 'member_id' => $memberId])),
        'block_number' => null,
        'confirmed' => true,
        'institution_id' => $member['institution_id'],
        'created_at' => date('Y-m-d H:i:s')
    ]);

    // Log to audit trail
    $db->insert('audit_logs', [
        'id' => generateUUID(),
        'user_id' => $_SESSION['user']['id'],
        'action' => $isOverride ? 'MEMBERSHIP_ID_OVERRIDE' : 'MEMBERSHIP_ID_GENERATED',
        'details' => json_encode([
            'member_id' => $memberId,
            'generated_id' => $generatedId,
            'is_override' => $isOverride
        ]),
        'affected_entity_type' => 'member',
        'affected_entity_id' => $memberId,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'membership_id' => $generatedId,
        'blockchain_hash' => $transactionHash,
        'is_override' => $isOverride
    ]);

} catch (Exception $e) {
    $db->rollback();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

function generateUUID() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
