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

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['endpoint'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid subscription data']);
        exit;
    }

    $supabase = new \App\Lib\Supabase();
    $endpoint = trim($data['endpoint']);
    $userId = $_SESSION['user']['id'];

    $existing = $supabase->from('push_subscriptions')
        ->select('*')
        ->eq('endpoint', $endpoint)
        ->limit(1)
        ->get(true);

    $subscriptionData = [
        'user_id' => $userId,
        'endpoint' => $endpoint,
        'keys' => json_encode($data['keys'] ?? []),
        'browser' => $data['browser'] ?? '',
        'platform' => $data['platform'] ?? '',
        'metadata' => json_encode($data['metadata'] ?? []),
        'active' => true,
        'last_active' => date('c'),
        'created_at' => date('c')
    ];

    if (!$existing['error'] && !empty($existing['data'])) {
        $result = $supabase->from('push_subscriptions')
            ->eq('endpoint', $endpoint)
            ->update($subscriptionData, true);
    } else {
        $result = $supabase->from('push_subscriptions')
            ->insert($subscriptionData, true);
    }

    if ($result['error']) {
        throw new Exception($result['message'] ?? 'Failed to save subscription');
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('Push subscription save error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save subscription', 'details' => $e->getMessage()]);
}
?>