<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../src/lib/SupabaseClient.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'eb_president', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

$institutionId = $_GET['id'] ?? '';
if (empty($institutionId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Institution ID is required']);
    exit;
}

$config = require __DIR__ . '/../../includes/supabase.php';
$supabase = new App\Lib\SupabaseClient($config['url'], $config['service_role_key']);

try {
    $institution = $supabase->select('institutions', ['id' => 'eq.' . $institutionId]);
    $institution = is_array($institution) && count($institution) > 0 ? $institution[0] : null;

    if (!$institution) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Institution not found']);
        exit;
    }

    $members = $supabase->select('user_profiles', ['institution_id' => 'eq.' . $institutionId]);
    $events = $supabase->select('events', ['institution_id' => 'eq.' . $institutionId]);
    $attendance = $supabase->select('attendance', ['institution_id' => 'eq.' . $institutionId]);

    $memberCount = is_array($members) ? count($members) : 0;
    $eventCount = is_array($events) ? count($events) : 0;
    $attendanceCount = is_array($attendance) ? count($attendance) : 0;

    $uniqueAttendees = [];
    if (is_array($attendance)) {
        foreach ($attendance as $row) {
            if (!empty($row['member_id'])) {
                $uniqueAttendees[$row['member_id']] = true;
            }
        }
    }

    $participationRate = $eventCount > 0 ? min(100, round((count($uniqueAttendees) / $eventCount) * 100, 1)) : 0;
    $latestActivity = null;
    if (is_array($events) && count($events) > 0) {
        $latestActivity = max(array_column($events, 'event_date'));
    }

    echo json_encode([
        'success' => true,
        'institution' => [
            'id' => $institution['id'],
            'name' => $institution['name'] ?? '',
            'status' => $institution['status'] ?? 'unknown',
            'address' => $institution['address'] ?? '',
            'member_count' => $memberCount,
            'event_count' => $eventCount,
            'attendance_count' => $attendanceCount,
            'participant_count' => count($uniqueAttendees),
            'participation_rate' => $participationRate,
            'latest_activity' => $latestActivity,
            'created_at' => $institution['created_at'] ?? null
        ]
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    error_log('Institution details error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to fetch institution details']);
    exit;
}
