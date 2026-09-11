<?php
/**
 * Compliance Check Cron Job
 * This script should be run daily via cron to check institutional compliance
 * Usage: php public/api/cron-compliance-check.php
 */

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../includes/config.php';

$sb = getSupabaseClient();
if (!$sb) {
    echo "Error: Database connection unavailable.\n";
    exit(1);
}

$blockchain = $GLOBALS['blockchain'] ?? new \App\Lib\BlockchainService($sb);
$complianceEngine = new \App\Lib\ComplianceEngine($sb, $blockchain);
$complianceRepo = \App\Lib\ComplianceRepository::getInstance($sb);

$logFile = __DIR__ . '/../../logs/compliance-check.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

try {
    logMessage("Starting centralized compliance check process");
    
    $today = new DateTime();
    $year = (int) $today->format('Y');

    // Run constitutional compliance calculations for all active chapters
    $results = $complianceEngine->calculateAll($year);

    $summary = $complianceRepo->getSummary($year);

    $compliantCount = $summary['compliant'];
    $atRiskCount = $summary['at_risk'];
    $nonCompliantCount = $summary['non_compliant'];

    foreach ($results as $instId => $info) {
        logMessage("Institution {$info['name']}: Score={$info['score']}%");
    }

    logMessage("Compliance check completed. Compliant: $compliantCount, At Risk: $atRiskCount, Non-Compliant: $nonCompliantCount");
    
    if (php_sapi_name() === 'cli') {
        echo "Compliance check completed successfully for year {$year}.\n";
        echo "Compliant: {$compliantCount} | At Risk: {$atRiskCount} | Non-Compliant: {$nonCompliantCount}\n";
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'year' => $year,
            'summary' => $summary,
            'results' => $results
        ]);
    }
    
} catch (Exception $e) {
    logMessage("Compliance check process failed: " . $e->getMessage());
    if (php_sapi_name() === 'cli') {
        echo "Error: " . $e->getMessage() . "\n";
    } else {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit(1);
}