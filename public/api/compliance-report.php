<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../portal/auth_check.php';

header('Content-Type: application/json');

require_role(['admin', 'super_admin', 'eb_auditor']);

$db = Database::getInstance();

$action = $_GET['action'] ?? 'get';
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$summaryOnly = isset($_GET['summary']) && $_GET['summary'] === '1';

switch ($action) {
    case 'get':
        $institutionId = $_GET['institution_id'] ?? null;

        if (empty($institutionId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'institution_id required']);
            exit;
        }

        try {
            $institution = $db->fetchOne("SELECT * FROM institutions WHERE id = ?", [$institutionId]);
            
            if (!$institution) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Institution not found']);
                exit;
            }

            // Get compliance metrics
            $memberCount = $db->fetchOne("SELECT COUNT(*) as count FROM members WHERE institution_id = ?", [$institutionId])['count'];
            $eventCount = $db->fetchOne("SELECT COUNT(*) as count FROM events WHERE institution_id = ? AND YEAR(start_date) = ?", [$institutionId, $year])['count'];
            
            $attendance = $db->fetchAll("SELECT COUNT(DISTINCT ea.event_id) as attended_events 
                FROM event_attendees ea
                JOIN members m ON ea.member_id = m.id
                JOIN events e ON ea.event_id = e.id
                WHERE m.institution_id = ? AND YEAR(e.start_date) = ?", [$institutionId, $year]);
            
            $attendedEvents = $attendance[0]['attended_events'] ?? 0;
            $totalEvents = $db->fetchOne("SELECT COUNT(*) as count FROM events WHERE YEAR(start_date) = ?", [$year])['count'];
            $participationRate = $totalEvents > 0 ? round(($attendedEvents / $totalEvents) * 100, 1) : 0;

            $report = [
                'institution_id' => $institutionId,
                'institution_name' => $institution['name'],
                'year' => $year,
                'member_count' => $memberCount,
                'events_hosted' => $eventCount,
                'events_attended' => $attendedEvents,
                'participation_rate' => $participationRate,
                'compliance_score' => calculateComplianceScore($memberCount, $eventCount, $participationRate),
                'status' => determineComplianceStatus($memberCount, $eventCount, $participationRate)
            ];

            echo json_encode(['success' => true, 'report' => $report]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'calculate':
        require_role(['admin', 'super_admin']);

        $institutionId = $_GET['institution_id'] ?? null;

        try {
            if ($institutionId) {
                $score = calculateComplianceForInstitution($db, $institutionId, $year);
                echo json_encode(['success' => true, 'score' => $score]);
            } else {
                $results = calculateAllCompliance($db, $year);
                echo json_encode(['success' => true, 'results' => $results]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'all':
        require_role(['admin', 'super_admin', 'eb_auditor']);

        try {
            $institutions = $db->fetchAll("SELECT id, name FROM institutions ORDER BY name");
            $reports = [];
            
            foreach ($institutions as $inst) {
                $report = calculateComplianceForInstitution($db, $inst['id'], $year);
                $reports[] = $report;
            }

            if ($summaryOnly) {
                $summary = [
                    'total_institutions' => count($institutions),
                    'active_institutions' => count(array_filter($institutions, fn($i) => $i['status'] === 'active')),
                    'total_members' => array_sum(array_column($reports, 'member_count')),
                    'average_participation' => count($reports) > 0 ? round(array_sum(array_column($reports, 'participation_rate')) / count($reports), 1) : 0
                ];
                echo json_encode(['success' => true, 'summary' => $summary, 'data' => $reports]);
            } else {
                echo json_encode(['success' => true, 'reports' => $reports]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function calculateComplianceScore($memberCount, $eventCount, $participationRate) {
    $score = 0;
    $score += min(40, $memberCount * 2); // Max 40 points for members
    $score += min(30, $eventCount * 10); // Max 30 points for events
    $score += min(30, $participationRate * 0.3); // Max 30 points for participation
    return min(100, $score);
}

function determineComplianceStatus($memberCount, $eventCount, $participationRate) {
    $score = calculateComplianceScore($memberCount, $eventCount, $participationRate);
    if ($score >= 75) return 'Compliant';
    if ($score >= 50) return 'At Risk';
    return 'Non-compliant';
}

function calculateComplianceForInstitution($db, $institutionId, $year) {
    $institution = $db->fetchOne("SELECT * FROM institutions WHERE id = ?", [$institutionId]);
    $memberCount = $db->fetchOne("SELECT COUNT(*) as count FROM members WHERE institution_id = ?", [$institutionId])['count'];
    $eventCount = $db->fetchOne("SELECT COUNT(*) as count FROM events WHERE institution_id = ? AND YEAR(start_date) = ?", [$institutionId, $year])['count'];
    
    $totalEvents = $db->fetchOne("SELECT COUNT(*) as count FROM events WHERE YEAR(start_date) = ?", [$year])['count'];
    $attendance = $db->fetchOne("SELECT COUNT(DISTINCT ea.event_id) as attended_events 
        FROM event_attendees ea
        JOIN members m ON ea.member_id = m.id
        JOIN events e ON ea.event_id = e.id
        WHERE m.institution_id = ? AND YEAR(e.start_date) = ?", [$institutionId, $year]);
    
    $attendedEvents = $attendance['attended_events'] ?? 0;
    $participationRate = $totalEvents > 0 ? round(($attendedEvents / $totalEvents) * 100, 1) : 0;

    return [
        'institution_id' => $institutionId,
        'institution_name' => $institution['name'],
        'year' => $year,
        'member_count' => $memberCount,
        'events_hosted' => $eventCount,
        'events_attended' => $attendedEvents,
        'participation_rate' => $participationRate,
        'compliance_score' => calculateComplianceScore($memberCount, $eventCount, $participationRate),
        'status' => determineComplianceStatus($memberCount, $eventCount, $participationRate)
    ];
}

function calculateAllCompliance($db, $year) {
    $institutions = $db->fetchAll("SELECT id, name FROM institutions ORDER BY name");
    $results = [];
    
    foreach ($institutions as $inst) {
        $results[] = calculateComplianceForInstitution($db, $inst['id'], $year);
    }
    
    return $results;
}
