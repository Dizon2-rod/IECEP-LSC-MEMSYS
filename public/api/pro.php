<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
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

try {
    switch ($action) {
        case 'announcements':
            if ($method === 'GET') {
                // Public: get published announcements for landing page
                $result = $sb->from('announcements')
                    ->select('id, title, content, sent_at')
                    ->order('sent_at', false)
                    ->limit(10)
                    ->get(true);

                echo json_encode(['success' => true, 'data' => $result['data'] ?? []]);
            } elseif ($method === 'POST') {
                $user = $auth->requireRole(['eb_pro_1', 'eb_pro_2']);
                $data = json_decode(file_get_contents('php://input'), true);
                $title = $data['title'] ?? '';
                $content = $data['content'] ?? '';

                if (empty($title) || empty($content)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Title and content required']);
                    exit;
                }

                $result = $sb->from('announcements')->insert([
                    'title' => $title,
                    'content' => $content,
                    'sent_by' => $user['user_id'],
                ], true);

                echo json_encode(['success' => true, 'data' => $result['data'][0] ?? null]);
            } elseif ($method === 'DELETE') {
                $user = $auth->requireRole(['eb_pro_1', 'eb_pro_2']);
                $data = json_decode(file_get_contents('php://input'), true);
                $id = $data['id'] ?? '';

                $sb->from('announcements')->eq('id', $id)->delete(true);
                echo json_encode(['success' => true]);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
