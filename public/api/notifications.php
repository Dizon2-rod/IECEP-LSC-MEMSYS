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
                ->order('created_at', false)
                ->limit(50)
                ->get(true);

            $notifications = [];
            if (!$result['error']) {
                $allNotifications = $result['data'] ?? [];
                $notifications = array_values(array_filter($allNotifications, function ($item) use ($userId) {
                    return array_key_exists('recipient_id', $item) && ($item['recipient_id'] == $userId || $item['recipient_id'] === null);
                }));
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
                ->get(true);

            if ($notificationResult['error'] || empty($notificationResult['data'])) {
                throw new Exception('Notification not found');
            }

            $notification = $notificationResult['data'][0];
            if ($notification['recipient_id'] !== null && $notification['recipient_id'] !== $userId) {
                throw new Exception('Unauthorized');
            }

            $sb->from('notifications')
                ->update(['read' => true, 'read_at' => date('c')], true)
                ->eq('id', $notificationId);

            echo json_encode(['success' => true]);
            break;

        case 'mark_all_read':
            $result = $sb->from('notifications')
                ->select('*')
                ->order('created_at', false)
                ->get(true);

            $notificationsToMark = [];
            if (!$result['error']) {
                foreach ($result['data'] as $item) {
                    if (array_key_exists('recipient_id', $item) && ($item['recipient_id'] == $userId || $item['recipient_id'] === null) && empty($item['read'])) {
                        $notificationsToMark[] = $item['id'];
                    }
                }
            }

            foreach ($notificationsToMark as $notificationId) {
                $sb->from('notifications')
                    ->update(['read' => true, 'read_at' => date('c')], true)
                    ->eq('id', $notificationId);
            }

            echo json_encode(['success' => true]);
            break;

        case 'stats':
            $result = $sb->from('notifications')
                ->select('*')
                ->order('created_at', false)
                ->get(true);
            $allNotifications = [];
            if (!$result['error']) {
                $allNotifications = array_values(array_filter($result['data'] ?? [], function ($item) use ($userId) {
                    return array_key_exists('recipient_id', $item) && ($item['recipient_id'] == $userId || $item['recipient_id'] === null);
                }));
            }
            $unread = array_filter($allNotifications, function ($item) {
                return empty($item['read']) || $item['read'] === false;
            });

            echo json_encode(['success' => true, 'stats' => ['total' => count($allNotifications), 'unread' => count($unread)]]);
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