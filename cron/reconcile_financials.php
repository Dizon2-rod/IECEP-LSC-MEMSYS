<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/lib/FinancialSyncService.php';
require_once __DIR__ . '/../src/lib/EmailService.php';

$cronSecret = $_SERVER['CRON_SECRET'] ?? getenv('CRON_SECRET') ?? 'change-this-secret';
$providedSecret = $_GET['secret'] ?? '';
if (php_sapi_name() !== 'cli' && $providedSecret !== $cronSecret) {
    http_response_code(403);
    exit('Unauthorized');
}

$config = require __DIR__ . '/../includes/supabase.php';
$supabase = new \App\Lib\SupabaseClient($config['url'], $config['service_role_key']);
$syncService = new \App\Lib\FinancialSyncService($supabase);
$emailService = new \App\Lib\EmailService();

try {
    $institutions = $supabase->select('institutions', ['select' => 'id,name', 'order' => 'name.asc']);
    $mismatches = [];
    $corrections = 0;
    $checked = 0;

    foreach (is_array($institutions) ? $institutions : [] as $institution) {
        $institutionId = (string)($institution['id'] ?? '');
        if ($institutionId === '') {
            continue;
        }
        $checked++;
        try {
            // Verify before sync
            $before = $syncService->verifyTotals($institutionId);

            if (empty($before['match'])) {
                $mismatches[] = ['institution' => $institution, 'details' => $before];

                // Log the mismatch detection
                $syncService->logAuditEntry(
                    $institutionId,
                    null,
                    'cron_mismatch_detected',
                    $before['actual'] ?? null,
                    $before['expected'] ?? null,
                    null
                );
            }

            // Sync (will also log correction if values differ)
            $result = $syncService->syncInstitutionTotals($institutionId, null, 'cron_reconciliation');
            if (!empty($result['corrections'])) {
                $corrections++;
            }
        } catch (Throwable $e) {
            error_log("Financial reconciliation failed for {$institutionId}: " . $e->getMessage());
        }
    }

    // Email notification if mismatches were found
    if (!empty($mismatches)) {
        $treasurers = $supabase->select('user_profiles', [
            'role' => 'in.(treasurer,eb_treasurer)',
            'select' => 'email,full_name'
        ]);
        $lines = [
            'Financial reconciliation found ' . count($mismatches) . ' institution mismatch(es):',
            'Institutions checked: ' . $checked,
            'Corrections applied: ' . $corrections,
            ''
        ];
        foreach ($mismatches as $item) {
            $lines[] = ($item['institution']['name'] ?? 'Institution') . ': ' . json_encode($item['details']['mismatches'] ?? []);
        }
        $body = nl2br(htmlspecialchars(implode("\n", $lines)));
        foreach (is_array($treasurers) ? $treasurers : [] as $treasurer) {
            if (!empty($treasurer['email'])) {
                $emailService->send((string)$treasurer['email'], 'Financial reconciliation mismatch alert', $body, implode("\n", $lines));
            }
        }
    }

    $summary = [
        'institutions_checked' => $checked,
        'mismatches_found' => count($mismatches),
        'corrections_applied' => $corrections
    ];
    echo '[' . date('Y-m-d H:i:s') . '] Financial reconciliation completed: ' . json_encode($summary) . PHP_EOL;
} catch (Throwable $e) {
    error_log('Financial reconciliation error: ' . $e->getMessage());
    echo '[' . date('Y-m-d H:i:s') . '] ERROR: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
