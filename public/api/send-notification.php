<?php
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
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../src/lib/Supabase.php';

session_start();
$userRole = $_SESSION['user']['role'] ?? null;
$allowedRoles = ['admin', 'super_admin', 'eb_treasurer', 'committee_registration'];
if (!$userRole || !in_array($userRole, $allowedRoles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['title']) || !isset($data['body'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields: title, body']);
        exit;
    }

    $sb = new \App\Lib\Supabase();
    $subscriptionsResult = $sb->from('push_subscriptions')
        ->select('*')
        ->eq('active', true)
        ->get(true);

    $subscriptions = [];
    if (!$subscriptionsResult['error']) {
        $subscriptions = $subscriptionsResult['data'] ?? [];
    }

    $deliveryCount = 0;
    $payload = [
        'title' => $data['title'],
        'body' => $data['body'],
        'url' => $data['url'] ?? '/portal/dashboard.php',
        'icon' => $data['icon'] ?? '/IECEP-LSC-MEMSYS/public/assets/icons/icon-192.png',
        'badge' => $data['badge'] ?? '/IECEP-LSC-MEMSYS/public/assets/icons/icon-192.png'
    ];

    $vapidPublic = $_ENV['VAPID_PUBLIC_KEY'] ?? null;
    $vapidPrivate = $_ENV['VAPID_PRIVATE_KEY'] ?? null;
    $supportsWebPush = class_exists('Minishlink\\WebPush\\WebPush') && $vapidPublic && $vapidPrivate;

    if ($supportsWebPush) {
        $auth = [
            'VAPID' => [
                'subject' => $_ENV['APP_URL'] ?? 'mailto:no-reply@example.com',
                'publicKey' => $vapidPublic,
                'privateKey' => $vapidPrivate,
            ],
        ];

        $webPush = new \Minishlink\WebPush\WebPush($auth);

        foreach ($subscriptions as $sub) {
            $subData = $sub['data_json'] ?? null;
            $subscriptionPayload = is_string($subData) ? json_decode($subData, true) : $subData;
            if (!$subscriptionPayload || empty($subscriptionPayload['endpoint'])) {
                continue;
            }

            $webPush->sendNotification(
                $subscriptionPayload,
                json_encode($payload)
            );
            $deliveryCount++;
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $deliveryCount++;
            }
        }
    }

    $noticeData = [
        'title' => $data['title'],
        'body' => $data['body'],
        'url' => $payload['url'],
        'created_by' => $_SESSION['user']['id'] ?? null,
        'created_at' => date('c'),
    ];
    $sb->from('notifications')->insert($noticeData, true);

    $message = 'Notification queued for delivery';
    if ($deliveryCount > 0) {
        $message = sprintf('Notification sent to %d subscriptions', $deliveryCount);
    }

    echo json_encode(['success' => true, 'message' => $message, 'delivered' => $deliveryCount, 'subscriptions' => count($subscriptions)]);
} catch (Exception $e) {
    error_log('Send notification error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send notifications', 'details' => $e->getMessage()]);
}
?>