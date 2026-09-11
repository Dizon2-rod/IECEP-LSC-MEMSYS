<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../portal/auth_check.php';
require_once __DIR__ . '/../../src/lib/DataSyncService.php';

header('Content-Type: application/json');

// Ensure only authorized administrators can trigger full system data synchronization
if (!require_role(['admin', 'super_admin'], false)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Administrator or Super Administrator access required to synchronize system data.']);
    exit;
}

$user = get_user_info();
$userId = $user['id'] ?? ($_SESSION['user']['id'] ?? 'Admin');

try {
    $config = require __DIR__ . '/../../includes/supabase.php';
    $supabase = new \App\Lib\SupabaseClient($config['url'], $config['service_role_key']);
    $syncService = new \App\Lib\DataSyncService($supabase);

    $result = $syncService->syncAll((string)$userId);

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    error_log("Global system sync error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'System synchronization encountered an error: ' . $e->getMessage(),
        'error_details' => $e->getTraceAsString()
    ]);
}
