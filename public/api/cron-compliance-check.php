<?php
/**
 * Compliance Check Cron Job
 * This script should be run daily via cron to check institutional compliance
 * Usage: php public/api/cron-compliance-check.php
 */

require_once __DIR__ . '/../includes/db.php';

$db = Database::getInstance();

$logFile = __DIR__ . '/../../logs/compliance-check.log';
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
    logMessage("Starting compliance check process");
    
    // Get current academic year
    $today = new DateTime();
    $month = (int) $today->format('n');
    $year = (int) $today->format('Y');
    
    if ($month >= 7) {
        $startYear = $year;
        $endYear = $year + 1;
    } else {
        $startYear = $year - 1;
        $endYear = $year;
    }
    
    $startDate = "$startYear-07-01";
    $endDate = "$endYear-06-30";
    
    // Get all institutions
    $institutions = $db->fetchAll("SELECT id, name FROM institutions WHERE status = 'active'");
    
    $compliantCount = 0;
    $atRiskCount = 0;
    $nonCompliantCount = 0;
    
    foreach ($institutions as $institution) {
        try {
            $instId = $institution['id'];
            
            // Get member count
            $memberCount = $db->fetchOne("SELECT COUNT(*) as count FROM members WHERE institution_id = ?", [$instId])['count'];
            
            // Get events hosted in academic year
            $eventCount = $db->fetchOne("SELECT COUNT(*) as count FROM events WHERE institution_id = ? AND start_date >= ? AND start_date <= ?", [$instId, $startDate, $endDate])['count'];
            
            // Get total events in academic year
            $totalEvents = $db->fetchOne("SELECT COUNT(*) as count FROM events WHERE start_date >= ? AND start_date <= ?", [$startDate, $endDate])['count'];
            
            // Get events attended
            $attendedEvents = $db->fetchOne("SELECT COUNT(DISTINCT ea.event_id) as count
                FROM event_attendees ea
                JOIN members m ON ea.member_id = m.id
                JOIN events e ON ea.event_id = e.id
                WHERE m.institution_id = ? AND e.start_date >= ? AND e.start_date <= ?", [$instId, $startDate, $endDate])['count'];
            
            // Calculate participation rate
            $participationRate = $totalEvents > 0 ? round(($attendedEvents / $totalEvents) * 100, 1) : 0;
            
            // Calculate compliance score
            $score = 0;
            $score += min(40, $memberCount * 2); // Max 40 points for members
            $score += min(30, $eventCount * 10); // Max 30 points for events
            $score += min(30, $participationRate * 0.3); // Max 30 points for participation
            $score = min(100, $score);
            
            // Determine status
            if ($score >= 75) {
                $status = 'compliant';
                $compliantCount++;
            } elseif ($score >= 50) {
                $status = 'at_risk';
                $atRiskCount++;
            } else {
                $status = 'non_compliant';
                $nonCompliantCount++;
            }
            
            // Update or insert compliance record
            $existingRecord = $db->fetchOne("SELECT id FROM compliance_scores WHERE institution_id = ? AND year = ?", [$instId, $startYear]);
            
            if ($existingRecord) {
                $db->update('compliance_scores', [
                    'member_count' => $memberCount,
                    'events_hosted' => $eventCount,
                    'events_attended' => $attendedEvents,
                    'participation_rate' => $participationRate,
                    'compliance_score' => $score,
                    'compliance_status' => $status,
                    'last_checked' => date('Y-m-d H:i:s')
                ], 'id = ?', [$existingRecord['id']]);
            } else {
                $db->insert('compliance_scores', [
                    'id' => generateUUID(),
                    'institution_id' => $instId,
                    'year' => $startYear,
                    'member_count' => $memberCount,
                    'events_hosted' => $eventCount,
                    'events_attended' => $attendedEvents,
                    'participation_rate' => $participationRate,
                    'compliance_score' => $score,
                    'compliance_status' => $status,
                    'last_checked' => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            logMessage("Institution {$institution['name']}: Score=$score, Status=$status");
            
        } catch (Exception $e) {
            logMessage("Failed to check compliance for institution {$institution['id']}: " . $e->getMessage());
        }
    }
    
    // Check for non-compliant institutions and flag for review
    $nonCompliantInsts = $db->fetchAll("SELECT cs.*, i.name as institution_name 
        FROM compliance_scores cs
        JOIN institutions i ON cs.institution_id = i.id
        WHERE cs.compliance_status = 'non_compliant'
        AND cs.last_checked >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
    
    foreach ($nonCompliantInsts as $inst) {
        logMessage("ALERT: Institution {$inst['institution_name']} is non-compliant (Score: {$inst['compliance_score']})");
        // Here you would implement notification logic to chapter officers
    }
    
    logMessage("Compliance check completed. Compliant: $compliantCount, At Risk: $atRiskCount, Non-Compliant: $nonCompliantCount");
    
} catch (Exception $e) {
    logMessage("Compliance check process failed: " . $e->getMessage());
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
