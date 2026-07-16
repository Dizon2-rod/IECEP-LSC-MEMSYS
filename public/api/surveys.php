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
        case 'create':
            if ($method !== 'POST') { http_response_code(405); exit; }
            $user = $auth->requireRole(['admin']);
            
            $data = json_decode(file_get_contents('php://input'), true);
            $title = $data['title'] ?? '';
            $description = $data['description'] ?? '';
            $questions = $data['questions'] ?? [];
            $eventId = $data['event_id'] ?? null;
            $targetRoles = $data['target_roles'] ?? ['member'];
            
            if (empty($title) || empty($questions)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Title and questions are required']);
                exit;
            }
            
            $result = $sb->from('surveys')->insert([
                'title' => $title,
                'description' => $description,
                'questions' => json_encode($questions),
                'event_id' => $eventId,
                'target_roles' => $targetRoles,
                'created_by' => $_SESSION['user']['id'] ?? null
            ], true);
            
            if ($result['error']) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $result['message']]);
                exit;
            }
            
            echo json_encode(['success' => true, 'data' => $result['data'][0]]);
            break;
            
        case 'list':
            if ($method !== 'GET') { http_response_code(405); exit; }
            $user = $auth->requireRole(['admin', 'member']);
            
            $eventId = $_GET['event_id'] ?? null;
            $query = $sb->from('surveys');
            
            if ($eventId) {
                $query = $query->eq('event_id', $eventId);
            }
            
            if ($user['role'] === 'member') {
                $query = $query->eq('is_active', true);
                $query = $query->contains('target_roles', 'member');
            }
            
            $result = $query->order('created_at', false)->get(true);
            
            if ($result['error']) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $result['message']]);
                exit;
            }
            
            echo json_encode(['success' => true, 'data' => $result['data'] ?? []]);
            break;
            
        case 'submit':
            if ($method !== 'POST') { http_response_code(405); exit; }
            $user = $auth->requireRole(['member']);
            
            $data = json_decode(file_get_contents('php://input'), true);
            $surveyId = $data['survey_id'] ?? '';
            $answers = $data['answers'] ?? [];
            $eventId = $data['event_id'] ?? null;
            
            if (empty($surveyId) || empty($answers)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Survey ID and answers are required']);
                exit;
            }
            
            // Get member ID from user profile
            $memberResult = $sb->from('members')
                ->select('id')
                ->eq('user_id', $_SESSION['user']['id'])
                ->get(true);
            
            if ($memberResult['error'] || empty($memberResult['data'])) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Member not found']);
                exit;
            }
            
            $memberId = $memberResult['data'][0]['id'];
            
            // Check if already submitted
            $existing = $sb->from('survey_responses')
                ->select('id')
                ->eq('survey_id', $surveyId)
                ->eq('member_id', $memberId)
                ->get(true);
            
            if (!$existing['error'] && !empty($existing['data'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Survey already submitted']);
                exit;
            }
            
            $result = $sb->from('survey_responses')->insert([
                'survey_id' => $surveyId,
                'member_id' => $memberId,
                'event_id' => $eventId,
                'answers' => json_encode($answers)
            ], true);
            
            if ($result['error']) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $result['message']]);
                exit;
            }
            
            echo json_encode(['success' => true, 'data' => $result['data'][0]]);
            break;
            
        case 'responses':
            if ($method !== 'GET') { http_response_code(405); exit; }
            $user = $auth->requireRole(['admin']);
            
            $surveyId = $_GET['survey_id'] ?? '';
            
            if (empty($surveyId)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Survey ID is required']);
                exit;
            }
            
            $result = $sb->from('survey_responses')
                ->select('*, members(full_name, email)')
                ->eq('survey_id', $surveyId)
                ->order('submitted_at', false)
                ->get(true);
            
            if ($result['error']) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $result['message']]);
                exit;
            }
            
            echo json_encode(['success' => true, 'data' => $result['data'] ?? []]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
