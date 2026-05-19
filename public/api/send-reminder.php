<?php
require_once __DIR__ . '/bootstrap.php';
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../src/lib/SupabaseClient.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'eb_president', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (empty($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request body']);
    exit;
}

$institutionId = $payload['institution_id'] ?? null;
$type = $payload['type'] ?? 'reminder';
$targetUserId = $payload['user_id'] ?? null;
$title = trim($payload['title'] ?? 'IECEP-LSC Reminder');
$message = trim($payload['message'] ?? 'Please review the latest compliance update from IECEP-LSC.');
$link = $payload['link'] ?? '/portal/dashboard.php';

if (empty($title) || empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Title and message are required']);
    exit;
}

$config = require __DIR__ . '/../../includes/supabase.php';
$supabase = new App\Lib\SupabaseClient($config['url'], $config['service_role_key']);

try {
    $targets = [];

    if (!empty($targetUserId)) {
        $found = $supabase->select('user_profiles', ['id' => 'eq.' . $targetUserId]);
        if (is_array($found) && count($found) > 0) {
            $targets = [$found[0]];
        }
    } elseif (!empty($institutionId)) {
        $targets = $supabase->select('user_profiles', ['institution_id' => 'eq.' . $institutionId]);
    } else {
        $targets = $supabase->select('user_profiles', []);
    }

    $notifications = [];
    foreach ($targets as $target) {
        if (empty($target['id'])) {
            continue;
        }
        $notifications[] = [
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'user_id' => $target['id'],
            'sent_by' => $_SESSION['user_id'],
            'read' => false,
            'read_at' => null,
            'action_url' => $link,
            'created_at' => date('c')
        ];
    }

    if (!empty($notifications)) {
        $supabase->insert('notifications', $notifications);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Reminder sent successfully',
        'recipients' => count($notifications)
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    error_log('Send reminder error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to send reminder']);
    exit;
}
