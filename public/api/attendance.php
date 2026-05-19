<?php
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../../includes/supabase.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware/auth.php';

use App\Lib\Supabase;
use App\Middleware\AuthMiddleware;

$sb = new Supabase();
$auth = new AuthMiddleware();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'record':
            if ($method !== 'POST') { http_response_code(405); exit; }
            $user = $auth->requireRole(['eb_vp_academic', 'school_officer', 'committee_registration', 'committee_technical']);
            $data = json_decode(file_get_contents('php://input'), true);
            $memberId = $data['member_id'] ?? '';
            $eventId = $data['event_id'] ?? '';

            if (empty($memberId) || empty($eventId)) {
                http_response_code(400);
                echo json_encode(['error' => 'Member ID and Event ID required']);
                exit;
            }

            // Verify member exists and is paid
            $memberResult = $sb->from('members')
                ->select('id, full_name, payment_status')
                ->eq('id', $memberId)
                ->get(true);

            if ($memberResult['error'] || empty($memberResult['data'])) {
                http_response_code(404);
                echo json_encode(['error' => 'Member not found']);
                exit;
            }

            // Check if already recorded
            $existing = $sb->from('attendance')
                ->select('member_id')
                ->eq('member_id', $memberId)
                ->eq('event_id', $eventId)
                ->get(true);

            if (!$existing['error'] && !empty($existing['data'])) {
                echo json_encode(['success' => true, 'message' => 'Attendance already recorded']);
                exit;
            }

            // Record attendance
            $sb->from('attendance')->insert([
                'member_id' => $memberId,
                'event_id' => $eventId,
                'attended' => true,
                'recorded_at' => date('Y-m-d\TH:i:s\Z'),
            ], true);

            $attendanceRows = $sb->from('attendance')
                ->select('member_id')
                ->eq('event_id', $eventId)
                ->get(true);

            $attendanceList = array_column($attendanceRows['data'] ?? [], 'member_id');

            if (isset($GLOBALS['blockchain']) && $GLOBALS['blockchain'] instanceof \App\Lib\BlockchainService) {
                require_once __DIR__ . '/../../src/lib/MerkleTree.php';
                $eventInfo = $sb->from('events')->select('name, academic_year')->eq('id', $eventId)->get(true);
                $eventName = $eventInfo['data'][0]['name'] ?? '';
                $academicYear = $eventInfo['data'][0]['academic_year'] ?? '';
                $root = \App\Lib\MerkleTree::buildRoot($attendanceList);
                $GLOBALS['blockchain']->record('compliance_attendance', $eventId, [
                    'event_id' => $eventId,
                    'event_name' => $eventName,
                    'academic_year' => $academicYear,
                    'merkle_root' => $root,
                    'attendance_count' => count($attendanceList),
                ]);
            }

            echo json_encode(['success' => true, 'message' => 'Attendance recorded for ' . $memberResult['data'][0]['full_name']]);
            break;

        case 'qr-scan':
            if ($method !== 'POST') { http_response_code(405); exit; }
            $user = $auth->requireRole(['eb_vp_academic', 'school_officer', 'committee_registration', 'committee_technical']);
            $data = json_decode(file_get_contents('php://input'), true);
            $memberUuid = $data['member_id'] ?? '';
            $eventId = $data['event_id'] ?? '';

            if (empty($memberUuid) || empty($eventId)) {
                http_response_code(400);
                echo json_encode(['error' => 'Member ID and Event ID required']);
                exit;
            }

            // Look up member
            $memberResult = $sb->from('members')
                ->select('id, full_name, email, payment_status, institutions(name)')
                ->eq('id', $memberUuid)
                ->get(true);

            if ($memberResult['error'] || empty($memberResult['data'])) {
                http_response_code(404);
                echo json_encode(['error' => 'Member not found']);
                exit;
            }

            $member = $memberResult['data'][0];

            if (!$member['payment_status']) {
                http_response_code(403);
                echo json_encode(['error' => 'Member has not paid membership fee', 'member' => $member]);
                exit;
            }

            // Check if already recorded
            $existing = $sb->from('attendance')
                ->select('member_id')
                ->eq('member_id', $memberUuid)
                ->eq('event_id', $eventId)
                ->get(true);

            if (!$existing['error'] && !empty($existing['data'])) {
                echo json_encode(['success' => true, 'message' => 'Already marked present', 'member' => $member, 'already' => true]);
                exit;
            }

            // Record attendance
            $sb->from('attendance')->insert([
                'member_id' => $memberUuid,
                'event_id' => $eventId,
                'attended' => true,
                'recorded_at' => date('Y-m-d\TH:i:s\Z'),
            ], true);

            $attendanceRows = $sb->from('attendance')
                ->select('member_id')
                ->eq('event_id', $eventId)
                ->get(true);

            $attendanceList = array_column($attendanceRows['data'] ?? [], 'member_id');

            if (isset($GLOBALS['blockchain']) && $GLOBALS['blockchain'] instanceof \App\Lib\BlockchainService) {
                require_once __DIR__ . '/../../src/lib/MerkleTree.php';
                $eventInfo = $sb->from('events')->select('name, academic_year')->eq('id', $eventId)->get(true);
                $eventName = $eventInfo['data'][0]['name'] ?? '';
                $academicYear = $eventInfo['data'][0]['academic_year'] ?? '';
                $root = \App\Lib\MerkleTree::buildRoot($attendanceList);
                $GLOBALS['blockchain']->record('compliance_attendance', $eventId, [
                    'event_id' => $eventId,
                    'event_name' => $eventName,
                    'academic_year' => $academicYear,
                    'merkle_root' => $root,
                    'attendance_count' => count($attendanceList),
                ]);
            }

            echo json_encode(['success' => true, 'message' => 'Attendance recorded', 'member' => $member, 'already' => false]);
            break;

        case 'my-attendance':
            if ($method !== 'GET') { http_response_code(405); exit; }
            $user = $auth->requireAuth();

            $memberResult = $sb->from('members')
                ->select('id, institution_id')
                ->eq('user_id', $user['user_id'])
                ->get(true, $user['jwt']);

            if ($memberResult['error'] || empty($memberResult['data'])) {
                http_response_code(404);
                echo json_encode(['error' => 'Member record not found']);
                exit;
            }

            $memberId = $memberResult['data'][0]['id'];
            $institutionId = $memberResult['data'][0]['institution_id'];

            // Get member's attendance
            $attendance = $sb->from('attendance')
                ->select('*, events(name, date, academic_year)')
                ->eq('member_id', $memberId)
                ->order('recorded_at', false)
                ->get(true, $user['jwt']);

            // Get institution stats
            $instMembers = $sb->from('members')
                ->select('id')
                ->eq('institution_id', $institutionId)
                ->eq('payment_status', true)
                ->get(true);

            $totalPaid = count($instMembers['data'] ?? []);
            $eventsAttended = count($attendance['data'] ?? []);

            echo json_encode([
                'success' => true,
                'attendance' => $attendance['data'] ?? [],
                'events_attended' => $eventsAttended,
                'institution_total_paid' => $totalPaid,
            ]);
            break;

        case 'event-attendance':
            if ($method !== 'GET') { http_response_code(405); exit; }
            $user = $auth->requireRole(['eb_vp_academic', 'eb_president', 'committee_registration']);
            $eventId = $_GET['event_id'] ?? '';

            if (empty($eventId)) {
                http_response_code(400);
                echo json_encode(['error' => 'Event ID required']);
                exit;
            }

            $attendance = $sb->from('attendance')
                ->select('*, members(full_name, email, institutions(name))')
                ->eq('event_id', $eventId)
                ->get(true);

            echo json_encode(['success' => true, 'data' => $attendance['data'] ?? []]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
