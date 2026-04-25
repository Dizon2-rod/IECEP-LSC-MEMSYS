<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../lib/supabase.php';
require_once __DIR__ . '/../middleware/auth.php';

use App\Lib\Supabase;
use App\Middleware\AuthMiddleware;

$sb = new Supabase();
$auth = new AuthMiddleware();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$config = include __DIR__ . '/../config/config.php';

try {
    switch ($action) {
        case 'status':
            if ($method !== 'GET') { http_response_code(405); exit; }
            $user = $auth->requireRole(['eb_president', 'eb_vp_internal', 'committee_registration', 'school_officer']);

            $profile = $user['profile'];

            if ($profile['role'] === 'school_officer') {
                // Get own institution's compliance
                $memberResult = $sb->from('members')
                    ->select('institution_id')
                    ->eq('user_id', $user['user_id'])
                    ->get(true, $user['jwt']);

                $institutionId = $memberResult['data'][0]['institution_id'] ?? '';

                $compliance = $sb->from('compliance_records')
                    ->select('*')
                    ->eq('institution_id', $institutionId)
                    ->order('calculated_at', false)
                    ->limit(1)
                    ->get(true);

                $members = $sb->from('members')
                    ->select('id, full_name, payment_status')
                    ->eq('institution_id', $institutionId)
                    ->get(true);

                $paidMembers = array_filter($members['data'] ?? [], fn($m) => $m['payment_status'] === true);

                // Get attendance for paid members
                $paidMemberIds = array_column($paidMembers, 'id');
                $attending = [];
                if (!empty($paidMemberIds)) {
                    $attendance = $sb->from('attendance')
                        ->select('member_id')
                        ->in('member_id', $paidMemberIds)
                        ->get(true);
                    $attending = array_unique(array_column($attendance['data'] ?? [], 'member_id'));
                }

                $membersList = [];
                foreach ($paidMembers as $pm) {
                    $membersList[] = [
                        'id' => $pm['id'],
                        'full_name' => $pm['full_name'],
                        'has_attended' => in_array($pm['id'], $attending),
                    ];
                }

                echo json_encode([
                    'success' => true,
                    'institution_id' => $institutionId,
                    'compliance' => $compliance['data'][0] ?? null,
                    'paid_members' => count($paidMembers),
                    'attending_members' => count($attending),
                    'members' => $membersList,
                ]);
            } else {
                // Admin view - all institutions
                $institutions = $sb->from('institutions')
                    ->select('id, name, compliance_status, affiliation_fee_paid')
                    ->neq('id', $config['executive_council_id'])
                    ->eq('status', 'active')
                    ->get(true);

                $complianceData = [];
                foreach ($institutions['data'] ?? [] as $inst) {
                    $comp = $sb->from('compliance_records')
                        ->select('*')
                        ->eq('institution_id', $inst['id'])
                        ->order('calculated_at', false)
                        ->limit(1)
                        ->get(true);

                    $complianceData[] = [
                        'institution_id' => $inst['id'],
                        'institution_name' => $inst['name'],
                        'affiliation_fee_paid' => $inst['affiliation_fee_paid'],
                        'compliance_status' => $inst['compliance_status'],
                        'latest_record' => $comp['data'][0] ?? null,
                    ];
                }

                echo json_encode(['success' => true, 'data' => $complianceData]);
            }
            break;

        case 'calculate':
            if ($method !== 'POST') { http_response_code(405); exit; }
            $user = $auth->requireRole(['eb_president', 'eb_vp_internal']);

            // Calculate compliance for all active institutions
            $institutions = $sb->from('institutions')
                ->select('id')
                ->neq('id', $config['executive_council_id'])
                ->eq('status', 'active')
                ->get(true);

            $academicYear = $config['academic_year'];

            foreach ($institutions['data'] ?? [] as $inst) {
                $institutionId = $inst['id'];

                // Count paid members
                $members = $sb->from('members')
                    ->select('id')
                    ->eq('institution_id', $institutionId)
                    ->eq('payment_status', true)
                    ->get(true);

                $totalPaid = count($members['data'] ?? []);

                if ($totalPaid === 0) {
                    $participationRate = 0;
                } else {
                    // Get events for current academic year
                    $events = $sb->from('events')
                        ->select('id')
                        ->eq('academic_year', $academicYear)
                        ->get(true);

                    $eventIds = array_column($events['data'] ?? [], 'id');
                    $paidMemberIds = array_column($members['data'] ?? [], 'id');

                    $attending = 0;
                    if (!empty($eventIds) && !empty($paidMemberIds)) {
                        // Count unique paid members who attended at least one event
                        foreach ($paidMemberIds as $pmId) {
                            $att = $sb->from('attendance')
                                ->select('id')
                                ->eq('member_id', $pmId)
                                ->eq('attended', true)
                                ->in('event_id', $eventIds)
                                ->limit(1)
                                ->get(true);

                            if (!$att['error'] && !empty($att['data'])) {
                                $attending++;
                            }
                        }
                    }

                    $participationRate = ($attending / $totalPaid) * 100;
                }

                $status = $participationRate >= 40 ? 'compliant' : ($participationRate >= 20 ? 'at_risk' : 'non_compliant');

                // Insert compliance record
                $sb->from('compliance_records')->insert([
                    'institution_id' => $institutionId,
                    'period_start' => date('Y-m-d', strtotime('first day of August')),
                    'period_end' => date('Y-m-d', strtotime('last day of July +1 year')),
                    'total_members' => $totalPaid,
                    'attending_members' => $attending ?? 0,
                    'participation_rate' => round($participationRate, 2),
                    'status' => $status,
                ], true);

                // Update institution status
                $sb->from('institutions')
                    ->eq('id', $institutionId)
                    ->update(['compliance_status' => $status], true);
            }

            echo json_encode(['success' => true, 'message' => 'Compliance calculated for all institutions']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
