<?php
/**
 * Compliance Deadline Reminders - Hosting Obligation
 * 
 * Schools must host at least one event per year.
 * Sends reminders on Dec 1 (30 days before year end) and Dec 16 (15 days before).
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../src/lib/Supabase.php';
require_once __DIR__ . '/../src/lib/EmailService.php';
require_once __DIR__ . '/../includes/audit.php';

// Verify cron secret
$cron_secret = $_GET['secret'] ?? $_ENV['CRON_SECRET'] ?? '';
if (empty($cron_secret) || $cron_secret !== ($_ENV['CRON_SECRET'] ?? getenv('CRON_SECRET'))) {
    http_response_code(403);
    die('Unauthorized');
}

$supabase = new Supabase();
$email = new EmailService();
$current_date = date('Y-m-d');
$current_month = (int)date('m');
$current_day = (int)date('d');
$current_year = (int)date('Y');

// Only run in December
if ($current_month !== 12) {
    echo json_encode(['success' => true, 'message' => 'Not December, skipping']);
    exit;
}

// Determine reminder type
$reminder_type = null;
if ($current_day >= 1 && $current_day <= 15) {
    $reminder_type = '30_day'; // First reminder
} elseif ($current_day >= 16 && $current_day <= 31) {
    $reminder_type = '15_day'; // Final reminder
} else {
    echo json_encode(['success' => true, 'message' => 'No reminder scheduled for this date']);
    exit;
}

try {
    // Get all active institutions
    $institutions = $supabase->select('institutions', 'id,name,contact_email', ['status' => 'active']);
    
    $reminders_sent = 0;
    $year_start = "{$current_year}-01-01";
    $year_end = "{$current_year}-12-31";
    
    foreach ($institutions as $institution) {
        // Count completed events hosted by this institution this year
        $events = $supabase->query(
            "SELECT COUNT(*) as count FROM events 
             WHERE institution_id = ? 
             AND status = 'completed' 
             AND start_datetime >= ? 
             AND start_datetime <= ?",
            [$institution['id'], $year_start, $year_end]
        );
        
        $event_count = $events[0]['count'] ?? 0;
        
        // If no events hosted, send reminder
        if ($event_count == 0) {
            // Get school officers
            $officers = $supabase->select('user_profiles', 'id,email,full_name', [
                'institution_id' => $institution['id'],
                'role' => 'school_officer'
            ]);
            
            // Prepare email content
            if ($reminder_type === '30_day') {
                $subject = 'Reminder: Annual Event Hosting Requirement';
                $message = "Dear {$institution['name']},\n\n";
                $message .= "This is a reminder that your institution has not yet hosted an event this year ({$current_year}).\n\n";
                $message .= "As per IECEP-LSC compliance requirements, each affiliated school must host at least one event per year.\n\n";
                $message .= "You have approximately 30 days remaining to schedule and complete an event to meet this requirement.\n\n";
                $message .= "Please log in to the portal to create and schedule an event.\n\n";
                $message .= "Best regards,\nIECEP-LSC Compliance Team";
            } else {
                $subject = 'URGENT: Annual Event Hosting Requirement - Final Reminder';
                $message = "Dear {$institution['name']},\n\n";
                $message .= "URGENT: Your institution has not yet hosted an event this year ({$current_year}).\n\n";
                $message .= "This is your final reminder. You have approximately 15 days remaining to meet the annual hosting requirement.\n\n";
                $message .= "Failure to host an event may affect your compliance score and affiliation status.\n\n";
                $message .= "Please take immediate action by logging in to the portal and scheduling an event.\n\n";
                $message .= "Best regards,\nIECEP-LSC Compliance Team";
            }
            
            // Send email to institution contact
            if (!empty($institution['contact_email'])) {
                $email->send($institution['contact_email'], $subject, $message);
            }
            
            // Send to school officers
            foreach ($officers as $officer) {
                if (!empty($officer['email'])) {
                    $email->send($officer['email'], $subject, $message);
                }
                
                // Create in-app notification
                $supabase->insert('notifications', [
                    'user_id' => $officer['id'],
                    'type' => 'reminder',
                    'title' => $subject,
                    'message' => $reminder_type === '30_day' 
                        ? 'Your institution has not hosted an event this year. Please schedule one within 30 days.'
                        : 'URGENT: Final reminder to host an event this year (15 days remaining).',
                    'institution_id' => $institution['id'],
                    'priority' => $reminder_type === '15_day' ? 'high' : 'normal'
                ]);
            }
            
            // Notify admins
            $admins = $supabase->select('user_profiles', 'id,email', ['role' => 'admin']);
            foreach ($admins as $admin) {
                $supabase->insert('notifications', [
                    'user_id' => $admin['id'],
                    'type' => 'reminder',
                    'title' => "Compliance Reminder Sent: {$institution['name']}",
                    'message' => "A {$reminder_type} hosting reminder was sent to {$institution['name']}.",
                    'institution_id' => $institution['id']
                ]);
            }
            
            // Audit log
            log_audit('compliance_reminder_sent', 'institutions', $institution['id'], null, [
                'reminder_type' => $reminder_type,
                'year' => $current_year,
                'event_count' => 0
            ]);
            
            $reminders_sent++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'reminder_type' => $reminder_type,
        'reminders_sent' => $reminders_sent,
        'date' => $current_date
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
