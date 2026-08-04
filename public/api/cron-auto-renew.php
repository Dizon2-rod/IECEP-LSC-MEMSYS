<?php
/**
 * Auto-renewal Cron Job
 * This script should be run daily via cron to automatically renew memberships
 * Usage: php public/api/cron-auto-renew.php
 */

require_once __DIR__ . '/../includes/supabase.php';

$supabaseConfig = require __DIR__ . '/../includes/supabase.php';
$sb = new \App\Lib\SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);

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
    
    $today = date('Y-m-d');
    $sevenDaysLater = date('Y-m-d', strtotime('+7 days'));
    
    $expiringMembers = $sb->from('members')
        ->select('*,institutions(name)')
        ->gte('membership_expiry', $today)
        ->lte('membership_expiry', $sevenDaysLater)
        ->eq('auto_renewal', true)
        ->eq('membership_status', 'active')
        ->get(true);
    
    $expiringMembers = $expiringMembers['data'] ?? [];
    
    $renewedCount = 0;
    $failedCount = 0;
    
    foreach ($expiringMembers as $member) {
        try {
            $currentExpiry = new DateTime($member['membership_expiry']);
            $newExpiry = $currentExpiry->modify('+1 year')->format('Y-m-d');
            
            $sb->from('members')
                ->eq('id', $member['id'])
                ->update([
                    'membership_expiry' => $newExpiry,
                    'last_renewal_date' => date('Y-m-d'),
                    'payment_status' => true,
                    'updated_at' => date('Y-m-d H:i:s')
                ], true);
            
            $transactionId = generateUUID();
            $sb->from('transactions')->insert([
                'id' => $transactionId,
                'member_id' => $member['id'],
                'institution_id' => $member['institution_id'] ?? null,
                'type' => 'membership_fee',
                'amount' => getMembershipFee($member['member_type'] ?? 'new'),
                'status' => 'completed',
                'transaction_date' => date('Y-m-d'),
                'reference_number' => 'AUTO-' . strtoupper(substr($transactionId, 0, 8))
            ], true);
            
            $blockchainId = generateUUID();
            $transactionHash = hash('sha256', $member['id'] . $newExpiry . time());
            
            $sb->from('blockchain_records')->insert([
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
                'institution_id' => $member['institution_id'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ], true);
            
            $sb->from('audit_logs')->insert([
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
            ], true);
            
            $renewedCount++;
            logMessage("Renewed membership for member {$member['id']} ({$member['full_name']}) - New expiry: $newExpiry");
            
        } catch (Exception $e) {
            $failedCount++;
            logMessage("Failed to renew membership for member {$member['id']}: " . $e->getMessage());
        }
    }
    
    $notifyMembers = $sb->from('members')
        ->select('*,institutions(name)')
        ->gte('membership_expiry', $today)
        ->lte('membership_expiry', $sevenDaysLater)
        ->is('auto_renewal', false)
        ->eq('membership_status', 'active')
        ->get(true);
    
    $notifyMembers = $notifyMembers['data'] ?? [];
    
    foreach ($notifyMembers as $member) {
        logMessage("Notification needed for member {$member['id']} ({$member['full_name']}) - Auto-renewal disabled");
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

function getMembershipFee($memberType) {
    $fees = [
        'new' => 500,
        'renewal' => 400,
        'alumni' => 300
    ];
    return $fees[$memberType] ?? 500;
}