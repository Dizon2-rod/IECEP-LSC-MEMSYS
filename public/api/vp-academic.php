<?php
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../../includes/supabase.php';
require_once __DIR__ . '/../../includes/middleware/auth.php';

use App\Lib\Supabase;
use App\Middleware\AuthMiddleware;

$sb = new Supabase();
$auth = new AuthMiddleware();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$config = include __DIR__ . '/../../includes/config.php';

try {
    switch ($action) {
        case 'list-events':
            if ($method !== 'GET') { http_response_code(405); exit; }
            $user = $auth->requireAuth();

            $academicYear = $_GET['academic_year'] ?? $config['academic_year'];
            $result = $sb->from('events')
                ->select('*')
                ->eq('academic_year', $academicYear)
                ->order('date', false)
                ->get(true, $user['jwt']);

            echo json_encode(['success' => true, 'data' => $result['data'] ?? []]);
            break;

        case 'create-event':
            if ($method !== 'POST') { http_response_code(405); exit; }
            $user = $auth->requireRole(['eb_vp_academic']);
            $data = json_decode(file_get_contents('php://input'), true);
            $name = $data['name'] ?? '';
            $description = $data['description'] ?? '';
            $date = $data['date'] ?? '';
            $academicYear = $data['academic_year'] ?? $config['academic_year'];

            if (empty($name) || empty($date)) {
                http_response_code(400);
                echo json_encode(['error' => 'Event name and date required']);
                exit;
            }

            $result = $sb->from('events')->insert([
                'name' => $name,
                'description' => $description,
                'date' => $date,
                'academic_year' => $academicYear,
                'created_by' => $user['user_id'],
            ], true);

            echo json_encode(['success' => true, 'data' => $result['data'][0] ?? null]);
            break;

        case 'update-event':
            if ($method !== 'PUT' && $method !== 'POST') { http_response_code(405); exit; }
            $user = $auth->requireRole(['eb_vp_academic']);
            $data = json_decode(file_get_contents('php://input'), true);
            $eventId = $data['event_id'] ?? '';

            if (empty($eventId)) {
                http_response_code(400);
                echo json_encode(['error' => 'Event ID required']);
                exit;
            }

            $updateData = array_filter([
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'date' => $data['date'] ?? null,
                'academic_year' => $data['academic_year'] ?? null,
            ], fn($v) => $v !== null);

            $result = $sb->from('events')
                ->eq('id', $eventId)
                ->update($updateData, true);

            echo json_encode(['success' => true, 'data' => $result['data'][0] ?? null]);
            break;

        case 'delete-event':
            if ($method !== 'DELETE' && $method !== 'POST') { http_response_code(405); exit; }
            $user = $auth->requireRole(['eb_vp_academic']);
            $data = json_decode(file_get_contents('php://input'), true);
            $eventId = $data['event_id'] ?? $_GET['event_id'] ?? '';

            if (empty($eventId)) {
                http_response_code(400);
                echo json_encode(['error' => 'Event ID required']);
                exit;
            }

            $sb->from('events')->eq('id', $eventId)->delete(true);
            echo json_encode(['success' => true, 'message' => 'Event deleted']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
