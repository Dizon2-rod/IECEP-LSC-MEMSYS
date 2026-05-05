<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../../includes/supabase.php';
require_once __DIR__ . '/../../includes/lib/EmailService.php';
require_once __DIR__ . '/../../includes/middleware/auth.php';

use App\Lib\Supabase;
use App\Lib\EmailService;
use App\Middleware\AuthMiddleware;

$sb = new Supabase();
$auth = new AuthMiddleware();
$emailSvc = new EmailService();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'send-announcement':
            if ($method !== 'POST') { http_response_code(405); exit; }
            $user = $auth->requireRole(['eb_secretary_general']);
            $data = json_decode(file_get_contents('php://input'), true);
            $title = $data['title'] ?? '';
            $content = $data['content'] ?? '';
            $sendEmail = $data['send_email'] ?? false;

            if (empty($title) || empty($content)) {
                http_response_code(400);
                echo json_encode(['error' => 'Title and content required']);
                exit;
            }

            $announcement = $sb->from('announcements')->insert([
                'title' => $title,
                'content' => $content,
                'sent_by' => $user['user_id'],
            ], true);

            $announcementId = $announcement['data'][0]['id'] ?? '';

            if ($sendEmail) {
                // Get all member emails
                $members = $sb->from('members')
                    ->select('email')
                    ->get(true);

                if (!$members['error'] && !empty($members['data'])) {
                    foreach ($members['data'] as $m) {
                        $emailSvc->sendAnnouncement($m['email'], $title, $content);
                    }
                }
            }

            echo json_encode(['success' => true, 'announcement_id' => $announcementId]);
            break;

        case 'announcements':
            if ($method !== 'GET') { http_response_code(405); exit; }
            $user = $auth->requireRole(['eb_secretary_general', 'eb_assistant_secretary']);

            $result = $sb->from('announcements')
                ->select('*, read_receipts(user_id, read_at)')
                ->order('sent_at', false)
                ->get(true, $user['jwt']);

            echo json_encode(['success' => true, 'data' => $result['data'] ?? []]);
            break;

        case 'mark-read':
            if ($method !== 'POST') { http_response_code(405); exit; }
            $user = $auth->requireAuth();
            $data = json_decode(file_get_contents('php://input'), true);
            $announcementId = $data['announcement_id'] ?? '';

            if (empty($announcementId)) {
                http_response_code(400);
                echo json_encode(['error' => 'Announcement ID required']);
                exit;
            }

            $sb->from('read_receipts')->insert([
                'announcement_id' => $announcementId,
                'user_id' => $user['user_id'],
                'read_at' => date('Y-m-d\TH:i:s\Z'),
            ], true);

            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
