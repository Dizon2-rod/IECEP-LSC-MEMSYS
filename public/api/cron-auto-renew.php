<?php
/**
 * Auto-renewal Cron Job
 * This script should be run daily via cron to automatically renew memberships
 * Usage: php public/api/cron-auto-renew.php
 */

require_once __DIR__ . '/../includes/db.php';

$db = Database::getInstance();

$logFile = __DIR__ . '/../../logs/auto-renew.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

try {
    logMessage("Starting auto-renewal process");
    
    // Get members whose membership expires within 7 days and have auto-renewal enabled
    $expiringMembers = $db->fetchAll("SELECT m.*, i.name as institution_name 
        FROM members m 
        LEFT JOIN institutions i ON m.institution_id = i.id
        WHERE m.membership_expiry IS NOT NULL 
        AND m.membership_expiry BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
        AND m.auto_renewal = 1
        AND m.membership_status = 'active'");
    
    $renewedCount = 0;
    $failedCount = 0;
    
    foreach ($expiringMembers as $member) {
        try {
            $db->beginTransaction();
            
            // Calculate new expiry date (1 year from current expiry)
            $currentExpiry = new DateTime($member['membership_expiry']);
            $newExpiry = $currentExpiry->modify('+1 year')->format('Y-m-d');
            
            // Update member
            $db->update('members', [
                'membership_expiry' => $newExpiry,
                'last_renewal_date' => date('Y-m-d'),
                'payment_status' => 1
            ], 'id = ?', [$member['id']]);
            
            // Create transaction record
            $transactionId = generateUUID();
            $db->insert('transactions', [
                'id' => $transactionId,
                'member_id' => $member['id'],
                'institution_id' => $member['institution_id'],
                'type' => 'membership_fee',
                'amount' => getMembershipFee($db, $member['member_type']),
                'status' => 'completed',
                'transaction_date' => date('Y-m-d'),
                'reference_number' => 'AUTO-' . strtoupper(substr($transactionId, 0, 8))
            ]);
            
            // Log to blockchain
            $blockchainId = generateUUID();
            $transactionHash = hash('sha256', $member['id'] . $newExpiry . time());
            
            $db->insert('blockchain_records', [
                'id' => $blockchainId,
                'entity_type' => 'membership_renewal',
                'entity_id' => $member['id'],
                'transaction_hash' => $transactionHash,
                'record_hash' => hash('sha256', json_encode([
                    'member_id' => $member['id'],
                    'old_expiry' => $member['membership_expiry'],
                    'new_expiry' => $newExpiry
                ])),
                'confirmed' => true,
                'institution_id' => $member['institution_id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Log to audit trail
            $db->insert('audit_logs', [
                'id' => generateUUID(),
                'user_id' => 'system',
                'action' => 'AUTO_RENEWAL',
                'details' => json_encode([
                    'member_id' => $member['id'],
                    'old_expiry' => $member['membership_expiry'],
                    'new_expiry' => $newExpiry
                ]),
                'affected_entity_type' => 'member',
                'affected_entity_id' => $member['id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $db->commit();
            
            $renewedCount++;
            logMessage("Renewed membership for member {$member['id']} ({$member['full_name']}) - New expiry: $newExpiry");
            
        } catch (Exception $e) {
            $db->rollback();
            $failedCount++;
            logMessage("Failed to renew membership for member {$member['id']}: " . $e->getMessage());
        }
    }
    
    // Send notifications for members with auto-renewal disabled
    $notifyMembers = $db->fetchAll("SELECT m.*, i.name as institution_name 
        FROM members m 
        LEFT JOIN institutions i ON m.institution_id = i.id
        WHERE m.membership_expiry IS NOT NULL 
        AND m.membership_expiry BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
        AND (m.auto_renewal = 0 OR m.auto_renewal IS NULL)
        AND m.membership_status = 'active'");
    
    foreach ($notifyMembers as $member) {
        logMessage("Notification needed for member {$member['id']} ({$member['full_name']}) - Auto-renewal disabled");
        // Here you would implement email notification logic
    }
    
    logMessage("Auto-renewal process completed. Renewed: $renewedCount, Failed: $failedCount, Notifications: " . count($notifyMembers));
    
} catch (Exception $e) {
    logMessage("Auto-renewal process failed: " . $e->getMessage());
    exit(1);
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

function getMembershipFee($db, $memberType) {
    // Default fee structure - can be configured in settings
    $fees = [
        'new' => 500,
        'renewal' => 400,
        'alumni' => 300
    ];
    return $fees[$memberType] ?? 500;
}
