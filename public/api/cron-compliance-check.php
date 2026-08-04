<?php
/**
 * Compliance Check Cron Job
 * This script should be run daily via cron to check institutional compliance
 * Usage: php public/api/cron-compliance-check.php
 */

require_once __DIR__ . '/../includes/supabase.php';

$supabaseConfig = require __DIR__ . '/../includes/supabase.php';
$sb = new \App\Lib\SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);

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
    
    $institutions = $sb->from('institutions')
        ->select('id,name')
        ->eq('status', 'active')
        ->get(true);
    
    $institutions = $institutions['data'] ?? [];
    
    $compliantCount = 0;
    $atRiskCount = 0;
    $nonCompliantCount = 0;
    
    foreach ($institutions as $institution) {
        try {
            $instId = $institution['id'];
            
            $memberCount = $sb->from('members')
                ->select('count', true)
                ->eq('institution_id', $instId)
                ->get(true);
            $memberCount = (int)($memberCount['data'][0]['count'] ?? 0);
            
            $eventCount = $sb->from('events')
                ->select('count', true)
                ->eq('institution_id', $instId)
                ->gte('start_date', $startDate)
                ->lte('start_date', $endDate)
                ->get(true);
            $eventCount = (int)($eventCount['data'][0]['count'] ?? 0);
            
            $totalEvents = $sb->from('events')
                ->select('count', true)
                ->gte('start_date', $startDate)
                ->lte('start_date', $endDate)
                ->get(true);
            $totalEvents = (int)($totalEvents['data'][0]['count'] ?? 0);
            
            $attendedEvents = $sb->from('event_attendees')
                ->select('*,events!inner(start_date)')
                ->gte('events.start_date', $startDate)
                ->lte('events.start_date', $endDate)
                ->get(true);
            
            $attendedCount = 0;
            $attendedMemberIds = [];
            foreach (($attendedEvents['data'] ?? []) as $attendee) {
                $attendedMemberIds[$attendee['member_id']] = true;
            }
            $attendedEvents = count($attendedMemberIds);
            
            $participationRate = $totalEvents > 0 ? round(($attendedEvents / $totalEvents) * 100, 1) : 0;
            
            $score = 0;
            $score += min(40, $memberCount * 2);
            $score += min(30, $eventCount * 10);
            $score += min(30, $participationRate * 0.3);
            $score = min(100, $score);
            
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
            
            $existingRecord = $sb->from('compliance_scores')
                ->select('id')
                ->eq('institution_id', $instId)
                ->eq('year', $startYear)
                ->get(true);
            
            if (!empty($existingRecord['data'])) {
                $sb->from('compliance_scores')
                    ->eq('id', $existingRecord['data'][0]['id'])
                    ->update([
                        'member_count' => $memberCount,
                        'events_hosted' => $eventCount,
                        'events_attended' => $attendedEvents,
                        'participation_rate' => $participationRate,
                        'compliance_score' => $score,
                        'compliance_status' => $status,
                        'last_checked' => date('Y-m-d H:i:s')
                    ], true);
            } else {
                $sb->from('compliance_scores')->insert([
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
                ], true);
            }
            
            logMessage("Institution {$institution['name']}: Score=$score, Status=$status");
            
        } catch (Exception $e) {
            logMessage("Failed to check compliance for institution {$institution['id']}: " . $e->getMessage());
        }
    }
    
    $nonCompliantInsts = $sb->from('compliance_scores')
        ->select('*,institutions(name)')
        ->eq('compliance_status', 'non_compliant')
        ->gte('last_checked', date('Y-m-d', strtotime('-1 day')))
        ->get(true);
    
    foreach (($nonCompliantInsts['data'] ?? []) as $inst) {
        logMessage("ALERT: Institution {$inst['institutions']['name']} is non-compliant (Score: {$inst['compliance_score']})");
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