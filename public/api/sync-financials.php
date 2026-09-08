<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../portal/auth_check.php';
require_once __DIR__ . '/../../src/lib/FinancialSyncService.php';

header('Content-Type: application/json');

if (!require_role(['admin', 'super_admin'], false)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$config = require __DIR__ . '/../../includes/supabase.php';
$supabase = new \App\Lib\SupabaseClient($config['url'], $config['service_role_key']);
$syncService = new \App\Lib\FinancialSyncService($supabase);

try {
    if ($action === 'sync-all') {
        $results = $syncService->syncAllInstitutions();
        $failed = array_values(array_filter($results, static fn(array $result): bool => isset($result['error'])));
        echo json_encode([
            'success' => empty($failed),
            'message' => empty($failed) ? 'All institution totals synchronized.' : 'Synchronization completed with errors.',
            'summary' => [
                'processed' => count($results),
                'failed' => count($failed),
                'results' => $results
            ]
        ]);
        exit;
    }

    if ($action === 'sync-institution') {
        $institutionId = trim((string)($_POST['institution_id'] ?? $_GET['institution_id'] ?? ''));
        if ($institutionId === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'institution_id is required']);
            exit;
        }

        $totals = $syncService->syncInstitutionTotals($institutionId);
        $verification = $syncService->verifyTotals($institutionId);
        echo json_encode([
            'success' => true,
            'message' => 'Institution totals synchronized.',
            'totals' => $totals,
            'verification' => $verification
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Use action=sync-all or action=sync-institution']);
} catch (Throwable $e) {
    error_log('Financial synchronization API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Financial synchronization failed.', 'details' => $e->getMessage()]);
}
