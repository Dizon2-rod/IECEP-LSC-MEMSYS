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
$userId = $_SESSION['user']['id'] ?? null;

try {
    if ($action === 'sync-all') {
        $results = $syncService->syncAllInstitutions($userId);
        $failed = array_values(array_filter($results, static fn(array $result): bool => isset($result['error'])));
        $corrected = array_values(array_filter($results, static fn(array $result): bool => !empty($result['corrections'])));
        echo json_encode([
            'success' => empty($failed),
            'message' => empty($failed) ? 'All institution totals synchronized.' : 'Synchronization completed with errors.',
            'summary' => [
                'processed' => count($results),
                'failed' => count($failed),
                'corrected' => count($corrected),
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

        $totals = $syncService->syncInstitutionTotals($institutionId, $userId);
        $verification = $syncService->verifyTotals($institutionId);
        echo json_encode([
            'success' => true,
            'message' => 'Institution totals synchronized.',
            'totals' => $totals,
            'verification' => $verification
        ]);
        exit;
    }

    if ($action === 'audit-log') {
        $institutionId = trim((string)($_GET['institution_id'] ?? ''));
        $limit = max(1, min(200, (int)($_GET['limit'] ?? 50)));
        $logs = $syncService->getAuditLog($institutionId ?: null, $limit);
        echo json_encode([
            'success' => true,
            'count' => count($logs),
            'logs' => $logs
        ]);
        exit;
    }

    if ($action === 'global-summary') {
        $summary = $syncService->getGlobalSummary();
        echo json_encode([
            'success' => true,
            'summary' => $summary
        ]);
        exit;
    }

    if ($action === 'verify-all') {
        $results = $syncService->verifyAllInstitutions();
        $mismatches = array_filter($results, static fn(array $r): bool => empty($r['match']));
        echo json_encode([
            'success' => true,
            'total' => count($results),
            'in_sync' => count($results) - count($mismatches),
            'mismatched' => count($mismatches),
            'results' => $results
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Use action=sync-all, sync-institution, audit-log, global-summary, or verify-all']);
} catch (Throwable $e) {
    error_log('Financial synchronization API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Financial synchronization failed.', 'details' => $e->getMessage()]);
}
