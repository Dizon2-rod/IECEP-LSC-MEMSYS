<?php
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

$committeeRoles = [
    'committee_creatives', 'committee_documentation', 'committee_logistics',
    'committee_marketing', 'committee_registration', 'committee_technical',
];

try {
    switch ($action) {
        case 'tasks':
            if ($method === 'GET') {
                $user = $auth->requireRole($committeeRoles);
                $profile = $user['profile'];
                $committeeName = str_replace('committee_', '', $profile['role']);

                $result = $sb->from('committee_tasks')
                    ->select('*')
                    ->eq('committee_name', $committeeName)
                    ->order('created_at', false)
                    ->get(true, $user['jwt']);

                echo json_encode(['success' => true, 'data' => $result['data'] ?? []]);
            } elseif ($method === 'POST') {
                $user = $auth->requireRole($committeeRoles);
                $data = json_decode(file_get_contents('php://input'), true);
                $title = $data['title'] ?? '';
                $description = $data['description'] ?? '';
                $assignedTo = $data['assigned_to'] ?? null;
                $committeeName = str_replace('committee_', '', $user['profile']['role']);

                if (empty($title)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Title required']);
                    exit;
                }

                $result = $sb->from('committee_tasks')->insert([
                    'committee_name' => $committeeName,
                    'title' => $title,
                    'description' => $description,
                    'assigned_to' => $assignedTo,
                    'status' => 'pending',
                ], true);

                echo json_encode(['success' => true, 'data' => $result['data'][0] ?? null]);
            } elseif ($method === 'PUT') {
                $user = $auth->requireRole($committeeRoles);
                $data = json_decode(file_get_contents('php://input'), true);
                $taskId = $data['task_id'] ?? '';
                $status = $data['status'] ?? '';

                if (empty($taskId)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Task ID required']);
                    exit;
                }

                $updateData = array_filter([
                    'title' => $data['title'] ?? null,
                    'description' => $data['description'] ?? null,
                    'assigned_to' => $data['assigned_to'] ?? null,
                    'status' => $status ?: null,
                ], fn($v) => $v !== null && $v !== '');

                $sb->from('committee_tasks')->eq('id', $taskId)->update($updateData, true);
                echo json_encode(['success' => true]);
            } elseif ($method === 'DELETE') {
                $user = $auth->requireRole($committeeRoles);
                $data = json_decode(file_get_contents('php://input'), true);
                $taskId = $data['task_id'] ?? '';

                $sb->from('committee_tasks')->eq('id', $taskId)->delete(true);
                echo json_encode(['success' => true]);
            }
            break;

        case 'upload-asset':
            if ($method !== 'POST') { http_response_code(405); exit; }
            $user = $auth->requireRole($committeeRoles);
            $committeeName = str_replace('committee_', '', $user['profile']['role']);

            if (!isset($_FILES['file'])) {
                http_response_code(400);
                echo json_encode(['error' => 'No file uploaded']);
                exit;
            }

            $file = $_FILES['file'];
            $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
            $maxSize = 10 * 1024 * 1024;

            if (!in_array($file['type'], $allowedTypes)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid file type']);
                exit;
            }
            if ($file['size'] > $maxSize) {
                http_response_code(400);
                echo json_encode(['error' => 'File too large (max 10MB)']);
                exit;
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $storagePath = "{$committeeName}/" . time() . "_{$file['name']}";

            $uploadResult = $sb->storage()->upload('committee_assets', $storagePath, $file['tmp_name'], $file['type']);

            if ($uploadResult['error']) {
                http_response_code(500);
                echo json_encode(['error' => 'Upload failed', 'details' => $uploadResult['message']]);
                exit;
            }

            $publicUrl = $sb->storage()->getPublicUrl('committee_assets', $storagePath);

            echo json_encode(['success' => true, 'url' => $publicUrl, 'path' => $storagePath]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
