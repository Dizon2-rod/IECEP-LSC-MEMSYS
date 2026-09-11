<?php
/**
 * Compliance Reports API
 * Generates JSON and PDF compliance reports for institutions
 */

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/middleware/auth.php';

header('Content-Type: application/json');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    $db = $GLOBALS['supabaseClient'] ?? null;
    if (!$db) {
        throw new Exception('Database connection not available');
    }
    
    switch ($method) {
        case 'GET':
            if ($action === 'institution-report') {
                // Generate compliance report for a specific institution
                $institutionId = $_GET['institution_id'] ?? '';
                $year = (int)($_GET['year'] ?? date('Y'));
                
                if (empty($institutionId)) {
                    throw new Exception('institution_id parameter is required');
                }
                
                $instRepo = \App\Lib\InstitutionRepository::getInstance($db);
                $institution = $instRepo->getById($institutionId);
                
                if (empty($institution)) {
                    throw new Exception('Institution not found');
                }
                
                $compRepo = \App\Lib\ComplianceRepository::getInstance($db);
                $complianceData = $compRepo->getScoresForInstitution($institutionId, $year);
                
                $events = $db->select('events', [
                    'status' => 'eq.completed',
                    'order' => 'start_date.desc'
                ]) ?: [];
                
                $attendedEvents = [];
                $hostedEvents = [];
                
                foreach ($events as $event) {
                    // Check if institution hosted this event or served as venue
                    if ($event['institution_id'] === $institutionId || ($event['venue_institution_id'] ?? '') === $institutionId) {
                        $hostedEvents[] = $event;
                    }
                    
                    // Check attendance
                    $attendances = $db->select('event_attendees', [
                        'event_id' => 'eq.' . $event['id']
                    ]) ?: [];
                    
                    $members = $db->select('members', [
                        'institution_id' => 'eq.' . $institutionId
                    ]) ?: [];
                    
                    $memberIds = array_column($members, 'id');
                    foreach ($attendances as $att) {
                        if (in_array($att['member_id'], $memberIds)) {
                            $attendedEvents[] = $event;
                            break;
                        }
                    }
                }
                
                $members = $db->select('members', [
                    'institution_id' => 'eq.' . $institutionId
                ]) ?: [];
                $totalMembers = count($members);
                
                $uniqueAttendedEvents = array_unique($attendedEvents, SORT_REGULAR);
                $totalEventsCount = max(1, count($events));
                $participationRate = $totalMembers > 0 ? (count($uniqueAttendedEvents) / $totalEventsCount) * 100 : 0;
                
                // Recommendations based on settings
                $settings = \App\Lib\SettingsService::getInstance($db);
                $minPart = $settings->getMinParticipationRate();
                $recommendations = [];
                if ($participationRate < $minPart) {
                    $recommendations[] = "Increase participation in IECEP-LSC events to meet the {$minPart}% minimum requirement.";
                }
                if (count($hostedEvents) < 1) {
                    $recommendations[] = "Host at least one sanctioned event or serve as official venue per academic year to maintain compliance.";
                }
                if ($participationRate >= $minPart && count($hostedEvents) >= 1) {
                    $recommendations[] = "Maintain current participation and hosting levels to remain compliant.";
                }
                
                $reportData = [
                    'institution' => $institution,
                    'year' => $year,
                    'compliance' => $complianceData,
                    'statistics' => [
                        'total_members' => $totalMembers,
                        'participation_rate' => round($participationRate, 2),
                        'events_attended' => count($uniqueAttendedEvents),
                        'events_hosted' => count($hostedEvents),
                        'total_events' => count($events)
                    ],
                    'attended_events' => array_slice($uniqueAttendedEvents, 0, 10),
                    'hosted_events' => $hostedEvents,
                    'recommendations' => $recommendations
                ];
                
                echo json_encode([
                    'success' => true,
                    'report' => $reportData
                ]);
                
            } elseif ($action === 'all-institutions') {
                // Get compliance reports for all institutions via ComplianceRepository
                $year = (int)($_GET['year'] ?? date('Y'));
                $compRepo = \App\Lib\ComplianceRepository::getInstance($db);
                $reports = $compRepo->getAllScores($year);
                
                echo json_encode([
                    'success' => true,
                    'year' => $year,
                    'reports' => $reports
                ]);
                
            } elseif ($action === 'generate-pdf') {
                // Generate PDF for compliance report
                $institutionId = $_GET['institution_id'] ?? '';
                $year = (int)($_GET['year'] ?? date('Y'));
                
                if (empty($institutionId)) {
                    throw new Exception('institution_id parameter is required');
                }
                
                // Get report data directly
                $instRepo = \App\Lib\InstitutionRepository::getInstance($db);
                $institution = $instRepo->getById($institutionId);
                if (empty($institution)) {
                    throw new Exception('Institution not found');
                }
                
                $compRepo = \App\Lib\ComplianceRepository::getInstance($db);
                $complianceData = $compRepo->getScoresForInstitution($institutionId, $year);
                
                $members = $db->select('members', ['institution_id' => 'eq.' . $institutionId]) ?: [];
                $totalMembers = count($members);
                $participationRate = (float)($complianceData['participation_rate'] ?? 0);
                $hostedEventsCount = (int)($complianceData['hosted_event_count'] ?? 0);
                
                $settings = \App\Lib\SettingsService::getInstance($db);
                $minPart = $settings->getMinParticipationRate();
                $recommendations = [];
                if ($participationRate < $minPart) {
                    $recommendations[] = "Increase participation in IECEP-LSC events to meet the {$minPart}% minimum requirement.";
                }
                if ($hostedEventsCount < 1) {
                    $recommendations[] = "Host at least one sanctioned event or serve as official venue per academic year to maintain compliance.";
                }
                if ($participationRate >= $minPart && $hostedEventsCount >= 1) {
                    $recommendations[] = "Maintain current participation and hosting levels to remain compliant.";
                }

                $reportData = [
                    'institution' => $institution,
                    'year' => $year,
                    'compliance' => $complianceData,
                    'statistics' => [
                        'total_members' => $totalMembers,
                        'participation_rate' => round($participationRate, 2),
                        'events_attended' => 0,
                        'events_hosted' => $hostedEventsCount,
                        'total_events' => 0
                    ],
                    'attended_events' => [],
                    'hosted_events' => [],
                    'recommendations' => $recommendations
                ];
                
                // Generate PDF using DOMPDF
                require_once __DIR__ . '/../../src/lib/pdf.php';
                $pdfService = new \App\Lib\PDFService();
                
                $html = generateComplianceReportHTML($reportData);
                $pdfPath = $pdfService->generatePDF($html, 'compliance-report-' . ($institution['name'] ?? 'institution') . '-' . $year);
                
                echo json_encode([
                    'success' => true,
                    'pdf_path' => $pdfPath
                ]);
                
            } else {
                throw new Exception('Invalid action');
            }
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function generateComplianceReportHTML($report) {
    $logoUrl = APP_URL . '/public/assets/icons/iecep-logo.png';
    $institution = $report['institution'];
    $compliance = $report['compliance'];
    $stats = $report['statistics'];
    $institutionAddress = $institution['address'] ?? 'N/A';
    $institutionContact = $institution['contact_email'] ?? ($institution['email'] ?? 'N/A');
    
    $statusColor = $compliance && ($compliance['compliance_status'] ?? '') === 'compliant' ? '#10b981' : '#f59e0b';
    $statusText = $compliance ? ucfirst(str_replace('_', ' ', $compliance['compliance_status'] ?? '')) : 'Not Evaluated';
    
    $html = "
    <div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;'>
        <div style='text-align: center; margin-bottom: 30px;'>
            <img src='{$logoUrl}' alt='IECEP-LSC Logo' style='height: 80px;'>
            <h1 style='color: #0B1D4A; margin: 10px 0;'>Compliance Report</h1>
            <p style='color: #6c757d; margin: 5px 0;'>{$report['year']} Academic Year</p>
        </div>
        
        <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px;'>
            <h2 style='color: #0B1D4A; margin-bottom: 15px;'>" . htmlspecialchars($institution['name'] ?? '') . "</h2>
            <p style='margin: 5px 0;'><strong>Status:</strong> <span style='color: {$statusColor}; font-weight: bold;'>{$statusText}</span></p>
            <p style='margin: 5px 0;'><strong>Address:</strong> " . htmlspecialchars($institutionAddress) . "</p>
            <p style='margin: 5px 0;'><strong>Contact:</strong> " . htmlspecialchars($institutionContact) . "</p>
        </div>
        
        <h3 style='color: #0B1D4A; margin-bottom: 15px;'>Compliance Statistics</h3>
        <table style='width: 100%; border-collapse: collapse; margin-bottom: 30px;'>
            <tr style='background-color: #0B1D4A; color: white;'>
                <th style='padding: 12px; text-align: left;'>Metric</th>
                <th style='padding: 12px; text-align: right;'>Value</th>
                <th style='padding: 12px; text-align: right;'>Target</th>
            </tr>
            <tr>
                <td style='padding: 12px; border-bottom: 1px solid #ddd;'>Participation Rate</td>
                <td style='padding: 12px; border-bottom: 1px solid #ddd; text-align: right;'>{$stats['participation_rate']}%</td>
                <td style='padding: 12px; border-bottom: 1px solid #ddd; text-align: right;'>≥40%</td>
            </tr>
            <tr>
                <td style='padding: 12px; border-bottom: 1px solid #ddd;'>Events Hosted / Venue</td>
                <td style='padding: 12px; border-bottom: 1px solid #ddd; text-align: right;'>{$stats['events_hosted']}</td>
                <td style='padding: 12px; border-bottom: 1px solid #ddd; text-align: right;'>≥1</td>
            </tr>
            <tr>
                <td style='padding: 12px; border-bottom: 1px solid #ddd;'>Total Members</td>
                <td style='padding: 12px; border-bottom: 1px solid #ddd; text-align: right;'>{$stats['total_members']}</td>
                <td style='padding: 12px; border-bottom: 1px solid #ddd; text-align: right;'>N/A</td>
            </tr>
        </table>
        
        <h3 style='color: #0B1D4A; margin-bottom: 15px;'>Recommendations</h3>
        <ul style='margin-bottom: 30px;'>";
    
    foreach ($report['recommendations'] as $recommendation) {
        $html .= "<li style='margin-bottom: 10px;'>" . htmlspecialchars($recommendation) . "</li>";
    }
    
    $html .= "</ul>
        
        <div style='text-align: center; color: #6c757d; font-size: 12px; margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd;'>
            <p>© {$report['year']} IECEP-LSC MEMSYS – All rights reserved</p>
            <p>Institute of Electronics Engineers of the Philippines – Laguna Student Chapter</p>
            <p>Generated on: " . date('F j, Y, g:i a') . "</p>
        </div>
    </div>";
    
    return $html;
}
