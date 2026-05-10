<?php
/**
 * IECEP-LSC MEMSYS Deadline Reminder Cron Job
 * 
 * Checks for:
 * 1. Pending affiliations > 7 days old
 * 2. Upcoming events (next 3 days)
 * 3. Compliance at risk institutions
 * 
 * Protect with API_KEY header check
 * Call: curl -H "API-Key: your-secret-key" https://domain/public/api/cron-check-deadlines.php
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/paths.php';
require_once __DIR__ . '/../../src/lib/SupabaseClient.php';
require_once __DIR__ . '/../../includes/notification-helpers.php';

// Security check - verify API key header
$providedKey = $_SERVER['HTTP_API_KEY'] ?? $_GET['key'] ?? null;
$expectedKey = defined('CRON_SECRET') ? CRON_SECRET : (getenv('CRON_SECRET') ?: ($_ENV['CRON_SECRET'] ?? 'change-me'));

if (empty($providedKey) || $providedKey !== $expectedKey) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

// Set response headers
header('Content-Type: application/json');

$config = require __DIR__ . '/../../includes/supabase.php';
$supabase = new SupabaseClient($config['url'], $config['service_role_key']);

$notifications = [];
$errors = [];

try {
    // 1. Check pending affiliations > 7 days old
    try {
        $sevenDaysAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
        $oldAffiliations = $supabase->from('pending_affiliations')
            ->select('id, institution_id, requested_at, institutions(name)')
            ->eq('status', 'pending')
            ->lt('requested_at', $sevenDaysAgo)
            ->execute();
        
        if ($oldAffiliations) {
            foreach ($oldAffiliations as $affiliation) {
                // Create notification for registration committee
                $notifMsg = "Pending affiliation for {$affiliation['institutions']['name']} waiting for review (requested {$affiliation['requested_at']})";
                $notif = createNotification(
                    'Pending Affiliation Review',
                    $notifMsg,
                    'committee_registration',
                    'warning',
                    '/portal/admin/affiliations.php'
                );
                $notifications[] = $notif;
            }
        }
    } catch (Exception $e) {
        $errors[] = "Affiliation check failed: " . $e->getMessage();
    }
    
    // 2. Check upcoming events (next 3 days)
    try {
        $now = date('Y-m-d H:i:s');
        $inThreeDays = date('Y-m-d H:i:s', strtotime('+3 days'));
        
        $upcomingEvents = $supabase->from('events')
            ->select('id, title, event_date, institution_id')
            ->gte('event_date', $now)
            ->lte('event_date', $inThreeDays)
            ->execute();
        
        if ($upcomingEvents) {
            foreach ($upcomingEvents as $event) {
                $eventDateTime = date('M d, Y H:i', strtotime($event['event_date']));
                $notifMsg = "Upcoming event: {$event['title']} on {$eventDateTime}";
                
                // Notify all members
                $notif = createNotification(
                    'Event Reminder',
                    $notifMsg,
                    'member',
                    'info',
                    '/public/dashboard.php?tab=events'
                );
                $notifications[] = $notif;
            }
        }
    } catch (Exception $e) {
        $errors[] = "Event check failed: " . $e->getMessage();
    }
    
    // 3. Check compliance at risk institutions (< 40% participation)
    try {
        $schoolOfficers = $supabase->from('user_profiles')
            ->select('id, user_id, institution_id')
            ->eq('role', 'school_officer')
            ->execute();
        
        if ($schoolOfficers) {
            $currentYear = date('Y');
            
            foreach ($schoolOfficers as $officer) {
                // Get institution events
                $institutionEvents = $supabase->from('events')
                    ->select('id')
                    ->eq('institution_id', $officer['institution_id'])
                    ->gte('event_date', "{$currentYear}-01-01")
                    ->execute();
                
                if (empty($institutionEvents)) {
                    continue; // No events yet
                }
                
                // Get attendance count
                $attendance = $supabase->from('attendance')
                    ->select('id', ['count' => 'exact'])
                    ->in('event_id', array_column($institutionEvents, 'id'))
                    ->execute();
                
                $participationRate = count($institutionEvents) > 0 
                    ? (count($attendance) / count($institutionEvents)) * 100 
                    : 0;
                
                if ($participationRate < 40) {
                    $notifMsg = "Your institution's participation rate is {$participationRate}%. Please encourage members to attend events.";
                    $notif = createNotification(
                        'Low Compliance Alert',
                        $notifMsg,
                        'school_officer',
                        'danger',
                        '/portal/admin/compliance.php',
                        $officer['user_id']
                    );
                    $notifications[] = $notif;
                }
            }
        }
    } catch (Exception $e) {
        $errors[] = "Compliance check failed: " . $e->getMessage();
    }
    
    // Log execution
    error_log('[CRON] Deadline reminders executed at ' . date('Y-m-d H:i:s') . 
        ' - ' . count($notifications) . ' notifications created');
    
    echo json_encode([
        'success' => true,
        'timestamp' => date('c'),
        'notifications_created' => count($notifications),
        'errors' => $errors,
        'message' => 'Deadline reminder check completed'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('c')
    ]);
}
?>
