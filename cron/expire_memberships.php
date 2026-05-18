<?php
/**
 * Membership Expiry Cron Job
 * Run daily: 0 3 * * * php /path/to/expire_memberships.php
 */

$cronSecret = $_SERVER['CRON_SECRET'] ?? getenv('CRON_SECRET') ?? 'change-this-secret';
$providedSecret = $_GET['secret'] ?? '';

if (php_sapi_name() !== 'cli' && $providedSecret !== $cronSecret) {
    http_response_code(403);
    die('Unauthorized');
}

require_once __DIR__ . '/../autoload.php';

$supabaseConfig = require __DIR__ . '/../includes/supabase.php';
$supabase = new \App\Lib\SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);

echo "[" . date('Y-m-d H:i:s') . "] Starting membership expiry check\n";

try {
    $today = date('Y-m-d');
    
    // Expire memberships
    $expiredMembers = $supabase->select('members', [
        'membership_expiry' => 'lt.' . $today,
        'status' => 'eq.active'
    ]);
    
    foreach ($expiredMembers as $member) {
        $supabase->update('members', ['status' => 'expired'], ['id' => $member['id']]);
        
        // Send notification
        $supabase->insert('notifications', [
            'user_id' => $member['user_id'],
            'title' => 'Membership Expired',
            'message' => 'Your membership has expired. Please renew to continue accessing member benefits.',
            'type' => 'warning',
            'action_url' => '/member/renew.php',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Expired " . count($expiredMembers) . " memberships\n";
    
    // Send reminders (30, 7, 1 days before expiry)
    $reminderDays = [30, 7, 1];
    
    foreach ($reminderDays as $days) {
        $reminderDate = date('Y-m-d', strtotime("+$days days"));
        
        $expiringMembers = $supabase->select('members', [
            'membership_expiry' => 'eq.' . $reminderDate,
            'status' => 'eq.active'
        ]);
        
        foreach ($expiringMembers as $member) {
            $supabase->insert('notifications', [
                'user_id' => $member['user_id'],
                'title' => 'Membership Expiring Soon',
                'message' => "Your membership will expire in $days days. Please renew to avoid service interruption.",
                'type' => 'reminder',
                'action_url' => '/member/renew.php',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        echo "[" . date('Y-m-d H:i:s') . "] Sent " . count($expiringMembers) . " reminders for $days-day expiry\n";
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Membership expiry check completed\n";
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
