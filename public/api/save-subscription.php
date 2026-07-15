<?php
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

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
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../src/lib/Supabase.php';
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

try {
    require_csrf();

    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON payload']);
        exit;
    }

    $endpoint = trim($data['endpoint'] ?? '');
    if ($endpoint === '' || filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid subscription endpoint']);
        exit;
    }

    $supabase = new \App\Lib\Supabase();
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
    echo json_encode(['error' => 'Failed to save subscription']);
}
?>