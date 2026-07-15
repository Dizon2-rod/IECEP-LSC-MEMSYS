<?php
/**
 * Compliance Reports API
 * Generates PDF compliance reports for institutions
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware/auth.php';

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
                
                // Get institution details
                $institutions = $db->select('institutions', [
                    'id' => 'eq.' . $institutionId
                ]);
                
                if (empty($institutions)) {
                    throw new Exception('Institution not found');
                }
                
                $institution = $institutions[0];
                
                // Get compliance score
                $complianceScores = $db->select('compliance_scores', [
                    'institution_id' => 'eq.' . $institutionId,
                    'year' => 'eq.' . $year
                ]);
                
                $complianceData = $complianceScores[0] ?? null;
                
                // Get events attended
                $events = $db->select('events', [
                    'status' => 'eq.completed',
                    'order' => 'start_date.desc'
                ]);
                
                $attendedEvents = [];
                $hostedEvents = [];
                
                foreach ($events as $event) {
                    // Check if institution hosted this event
                    if ($event['institution_id'] === $institutionId) {
                        $hostedEvents[] = $event;
                    }
                    
                    // Check attendance
                    $attendances = $db->select('event_attendees', [
                        'event_id' => 'eq.' . $event['id']
                    ]);
                    
                    $members = $db->select('members', [
                        'institution_id' => 'eq.' . $institutionId
                    ]);
                    
                    $memberIds = array_column($members, 'id');
                    foreach ($attendances as $att) {
                        if (in_array($att['member_id'], $memberIds)) {
                            $attendedEvents[] = $event;
                            break;
                        }
                    }
                }
                
                // Get total members
                $totalMembers = count($members);
                
                // Calculate participation rate
                $uniqueAttendedEvents = array_unique($attendedEvents, SORT_REGULAR);
                $participationRate = $totalMembers > 0 ? (count($uniqueAttendedEvents) / count($events)) * 100 : 0;
                
                // Generate recommendations
                $recommendations = [];
                if ($participationRate < 40) {
                    $recommendations[] = "Increase participation in IECEP-LSC events to meet the 40% minimum requirement.";
                }
                if (count($hostedEvents) < 1) {
                    $recommendations[] = "Host at least one sanctioned event per academic year to maintain compliance.";
                }
                if ($participationRate >= 40 && count($hostedEvents) >= 1) {
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
                // Get compliance reports for all institutions
                $year = (int)($_GET['year'] ?? date('Y'));
                
                $institutions = $db->select('institutions', [
                    'status' => 'eq.active'
                ]);
                
                $reports = [];
                foreach ($institutions as $institution) {
                    $complianceScores = $db->select('compliance_scores', [
                        'institution_id' => 'eq.' . $institution['id'],
                        'year' => 'eq.' . $year
                    ]);
                    
                    $reports[] = [
                        'institution_id' => $institution['id'],
                        'institution_name' => $institution['name'],
                        'compliance' => $complianceScores[0] ?? null
                    ];
                }
                
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
                
                // Get report data
                $reportUrl = "/api/compliance/reports.php?action=institution-report&institution_id={$institutionId}&year={$year}";
                $reportResponse = file_get_contents(APP_URL . $reportUrl);
                $reportData = json_decode($reportResponse, true);
                
                if (!$reportData['success']) {
                    throw new Exception('Failed to generate report data');
                }
                
                $report = $reportData['report'];
                
                // Generate PDF using DOMPDF
                require_once __DIR__ . '/../../src/lib/pdf.php';
                $pdfService = new \App\Lib\PDFService();
                
                $html = generateComplianceReportHTML($report);
                $pdfPath = $pdfService->generatePDF($html, 'compliance-report-' . $report['institution']['name'] . '-' . $year);
                
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
    
    $statusColor = $compliance && $compliance['compliance_status'] === 'compliant' ? '#10b981' : '#f59e0b';
    $statusText = $compliance ? ucfirst($compliance['compliance_status']) : 'Not Evaluated';
    
    $html = "
    <div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;'>
        <div style='text-align: center; margin-bottom: 30px;'>
            <img src='{$logoUrl}' alt='IECEP-LSC Logo' style='height: 80px;'>
            <h1 style='color: #0B1D4A; margin: 10px 0;'>Compliance Report</h1>
            <p style='color: #6c757d; margin: 5px 0;'>{$report['year']} Academic Year</p>
        </div>
        
        <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px;'>
            <h2 style='color: #0B1D4A; margin-bottom: 15px;'>{$institution['name']}</h2>
            <p style='margin: 5px 0;'><strong>Status:</strong> <span style='color: {$statusColor}; font-weight: bold;'>{$statusText}</span></p>
            <p style='margin: 5px 0;'><strong>Address:</strong> {$institution['address'] ?? 'N/A'}</p>
            <p style='margin: 5px 0;'><strong>Contact:</strong> {$institution['contact_email'] ?? 'N/A'}</p>
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
                <td style='padding: 12px; border-bottom: 1px solid #ddd;'>Events Hosted</td>
                <td style='padding: 12px; border-bottom: 1px solid #ddd; text-align: right;'>{$stats['events_hosted']}</td>
                <td style='padding: 12px; border-bottom: 1px solid #ddd; text-align: right;'>≥1</td>
            </tr>
            <tr>
                <td style='padding: 12px; border-bottom: 1px solid #ddd;'>Events Attended</td>
                <td style='padding: 12px; border-bottom: 1px solid #ddd; text-align: right;'>{$stats['events_attended']}</td>
                <td style='padding: 12px; border-bottom: 1px solid #ddd; text-align: right;'>N/A</td>
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
        $html .= "<li style='margin-bottom: 10px;'>{$recommendation}</li>";
    }
    
    $html .= "</ul>
        
        <h3 style='color: #0B1D4A; margin-bottom: 15px;'>Hosted Events</h3>
        <table style='width: 100%; border-collapse: collapse; margin-bottom: 30px;'>";
    
    if (empty($report['hosted_events'])) {
        $html .= "<tr><td style='padding: 12px; text-align: center;'>No events hosted this year</td></tr>";
    } else {
        $html .= "<tr style='background-color: #0B1D4A; color: white;'>
            <th style='padding: 12px; text-align: left;'>Event Name</th>
            <th style='padding: 12px; text-align: left;'>Date</th>
            <th style='padding: 12px; text-align: left;'>Venue</th>
        </tr>";
        
        foreach ($report['hosted_events'] as $event) {
            $html .= "<tr>
                <td style='padding: 12px; border-bottom: 1px solid #ddd;'>{$event['title']}</td>
                <td style='padding: 12px; border-bottom: 1px solid #ddd;'>{$event['start_date']}</td>
                <td style='padding: 12px; border-bottom: 1px solid #ddd;'>{$event['venue'] ?? 'N/A'}</td>
            </tr>";
        }
    }
    
    $html .= "</table>
        
        <div style='text-align: center; color: #6c757d; font-size: 12px; margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd;'>
            <p>© {$report['year']} IECEP-LSC MEMSYS – All rights reserved</p>
            <p>Institute of Electronics Engineers of the Philippines – Laguna State Chapter</p>
            <p>Generated on: " . date('F j, Y, g:i a') . "</p>
        </div>
    </div>";
    
    return $html;
}
