<?php
require_once __DIR__ . '/bootstrap.php';
/**
 * Compliance Update Cron Job
 * Run daily: 0 2 * * * php /path/to/compliance_update.php
 */

// Security: Check for cron secret
$cronSecret = $_SERVER['CRON_SECRET'] ?? getenv('CRON_SECRET') ?? 'change-this-secret';
$providedSecret = $_GET['secret'] ?? '';

if (php_sapi_name() !== 'cli' && $providedSecret !== $cronSecret) {
    http_response_code(403);
    die('Unauthorized');
}

require_once __DIR__ . '/../autoload.php';

$supabaseConfig = require __DIR__ . '/../includes/supabase.php';
$supabase = new \App\Lib\SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);
$blockchain = new \App\Lib\BlockchainService($supabase);
$complianceEngine = new \App\Lib\ComplianceEngine($supabase, $blockchain);

$year = (int)date('Y');

echo "[" . date('Y-m-d H:i:s') . "] Starting compliance update for year $year\n";

try {
    $results = $complianceEngine->calculateAll($year);
    
    echo "[" . date('Y-m-d H:i:s') . "] Processed " . count($results) . " institutions\n";
    
    foreach ($results as $institutionId => $result) {
        echo "  - {$result['name']}: {$result['score']}%\n";
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Compliance update completed successfully\n";
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
