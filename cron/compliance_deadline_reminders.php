<?php
require_once __DIR__ . '/bootstrap.php';
/**
 * Chapter Compliance Monitoring Reminders
 * 
 * Sends supportive chapter engagement and compliance monitoring notices to institutions
 * with low compliance (e.g. participation < 40% or no hosted/venue events yet).
 * Note: Compliance is for monitoring only and does not revoke school affiliations.
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
$email = new \App\Lib\EmailService();
$current_date = date('Y-m-d');
$current_year = (int)date('Y');

try {
    // Get all active institutions
    $institutions = $supabase->select('institutions', 'id,name,contact_email,compliance_status', ['status' => 'active']);
    
    $reminders_sent = 0;
    $year_start = "{$current_year}-01-01";
    $year_end = "{$current_year}-12-31";
    
    foreach ($institutions as $institution) {
        $instId = $institution['id'];
        $compStatus = strtolower($institution['compliance_status'] ?? 'compliant');
        
        // Check completed hosted events
        $events = $supabase->query(
            "SELECT COUNT(*) as count FROM events 
             WHERE (institution_id = ? OR venue_institution_id = ?)
             AND status = 'completed' 
             AND start_datetime >= ? 
             AND start_datetime <= ?",
            [$instId, $instId, $year_start, $year_end]
        );
        $hostingCredit = $events[0]['count'] ?? 0;
        
        // Target institutions needing monitoring assistance (low events or flagged at_risk/non_compliant)
        $needsAdvisory = ($hostingCredit == 0 || $compStatus === 'at_risk' || $compStatus === 'non_compliant');
        
        if ($needsAdvisory) {
            $officers = $supabase->select('user_profiles', 'id,email,full_name', [
                'institution_id' => $instId,
                'role' => 'school_officer'
            ]);
            
            $subject = "IECEP-LSC Chapter Compliance Advisory — {$institution['name']}";
            $message = "Dear {$institution['name']} Officers,\n\n";
            $message .= "This is a friendly chapter compliance monitoring reminder from the IECEP Laguna Student Chapter for Academic Year {$current_year}.\n\n";
            $message .= "Our records indicate that your chapter currently has low event participation or has not yet hosted/served as an official venue for a sanctioned event this year (CBL Art. V Sec. 3).\n\n";
            $message .= "Please note that compliance tracking is for institutional monitoring and engagement support, without punitive deadlines or automatic revocation. We encourage your officers to invite members to upcoming regional events, or coordinate with the Executive Board to host or serve as an official venue.\n\n";
            $message .= "Access your compliance scorecard: https://iecep-lsc.org/portal/school-officer/compliance/status.php\n\n";
            $message .= "Warm regards,\nIECEP-LSC Committee on Student Affairs & Chapter Governance";
            
            // Send email to institution contact
            if (!empty($institution['contact_email'])) {
                try {
                    $email->send($institution['contact_email'], $subject, $message);
                } catch (\Throwable $e) {}
            }
            
            // Send to school officers
            foreach ($officers as $officer) {
                if (!empty($officer['email'])) {
                    try {
                        $email->send($officer['email'], $subject, $message);
                    } catch (\Throwable $e) {}
                }
                
                // Create in-app notification
                $supabase->insert('notifications', [
                    'user_id' => $officer['id'],
                    'type' => 'reminder',
                    'title' => $subject,
                    'message' => "Friendly monitoring reminder: Your chapter currently has an advisory compliance standing. Consider participating in upcoming regional events or coordinating to host/serve as venue.",
                    'institution_id' => $instId,
                    'priority' => 'normal'
                ]);
            }
            
            // Audit log
            log_audit('compliance_monitoring_reminder_sent', 'institutions', $instId, null, [
                'year' => $current_year,
                'hosting_credit' => $hostingCredit,
                'compliance_status' => $compStatus
            ]);
            
            $reminders_sent++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'reminders_sent' => $reminders_sent,
        'date' => $current_date
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
