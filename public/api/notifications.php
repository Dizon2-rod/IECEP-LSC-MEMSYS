<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../src/lib/Supabase.php';
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? 'list';
$sb = new \App\Lib\Supabase();
$userId = $_SESSION['user']['id'];

try {
    switch ($action) {
        case 'list':
            $result = $sb->from('notifications')
                ->select('*')
                ->or("user_id.eq.$userId,user_id.is.null")
                ->order('created_at', false)
                ->limit(50)
                ->get(true);

            $notifications = [];
            if (!$result['error']) {
                $notifications = $result['data'] ?? [];
            }

            echo json_encode(['success' => true, 'notifications' => $notifications]);
            break;

        case 'mark_read':
            $notificationId = $_POST['notification_id'] ?? '';
            if (empty($notificationId)) {
                throw new Exception('Notification ID is required');
            }

            $notificationResult = $sb->from('notifications')
                ->select('*')
                ->eq('id', $notificationId)
                ->eq('user_id', $userId)
                ->get(true);

            if ($notificationResult['error'] || empty($notificationResult['data'])) {
                throw new Exception('Notification not found');
            }

            $sb->from('notifications')
                ->eq('id', $notificationId)
                ->eq('user_id', $userId)
                ->update(['read' => true, 'read_at' => date('c')], true);

            echo json_encode(['success' => true]);
            break;

        case 'mark_all_read':
            $sb->from('notifications')
                ->or("user_id.eq.$userId,user_id.is.null")
                ->eq('read', false)
                ->update(['read' => true, 'read_at' => date('c')], true);

            echo json_encode(['success' => true]);
            break;

        case 'stats':
            $result = $sb->from('notifications')
                ->select('*')
                ->or("user_id.eq.$userId,user_id.is.null")
                ->order('created_at', false)
                ->get(true);

            $notifications = $result['error'] ? [] : ($result['data'] ?? []);
            $unread = array_filter($notifications, fn($item) => empty($item['read']) || $item['read'] === false);

            echo json_encode(['success' => true, 'stats' => ['total' => count($notifications), 'unread' => count($unread)]]);
            break;

        case 'vapid_key':
            $vapidPublic = getenv('VAPID_PUBLIC_KEY') ?: ($_ENV['VAPID_PUBLIC_KEY'] ?? null);
            echo json_encode(['success' => true, 'vapid_public_key' => $vapidPublic]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    error_log('Notifications API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>