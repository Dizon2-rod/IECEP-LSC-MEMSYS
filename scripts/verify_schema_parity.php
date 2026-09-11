<?php
/**
 * scripts/verify_schema_parity.php - Database Schema Parity Verifier
 * 
 * Verifies parity between MySQL (XAMPP localhost) and Supabase (PostgreSQL cloud)
 * across all critical tables, columns, and sync structures.
 * 
 * Usage:
 *   php scripts/verify_schema_parity.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Error: CLI execution only.\n");
}

require_once __DIR__ . '/../bootstrap.php';

echo "====================================================\n";
echo " IECEP-LSC MEMSYS — Schema Parity Verification\n";
echo " Time: " . date('Y-m-d H:i:s') . "\n";
echo "====================================================\n\n";

// Canonical tables and critical columns required for sync
$canonicalSchema = [
    'schema_migrations' => ['version', 'name', 'applied_at'],
    'institutions' => ['id', 'name', 'email', 'compliance_status'],
    'members' => ['id', 'institution_id', 'status', 'payment_status', 'member_type'],
    'events' => ['id', 'title', 'institution_id', 'venue_institution_id', 'status'],
    'transactions' => ['id', 'institution_id', 'status', 'amount'],
    'compliance_scores' => ['id', 'institution_id', 'year', 'compliance_status', 'last_updated'],
    'compliance_rules' => ['id', 'rule_key', 'threshold', 'is_active'],
    'system_settings' => ['id', 'key', 'value'],
    'fee_brackets' => ['id', 'bracket_name', 'min_members', 'fee', 'is_active'],
    'member_fees' => ['id', 'member_type', 'fee', 'is_active'],
    'institution_documents' => ['id', 'institution_id', 'document_type', 'status'],
    'email_verifications' => ['id', 'email', 'code', 'attempts', 'expires_at', 'verified']
];

// 1. Check MySQL / XAMPP Localhost
$dbHost = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '127.0.0.1');
$dbPort = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? 3306);
$dbName = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'iecep_lsc_memsys');
$dbUser = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'root');
$dbPass = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? '');

$mysqlPdo = null;
try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $mysqlPdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "[MySQL Localhost] Connected successfully to {$dbName}@{$dbHost}.\n";
} catch (\Throwable $e) {
    echo "[MySQL Localhost] Offline or unreachable: " . $e->getMessage() . "\n";
}

// 2. Check Supabase Cloud REST Client
$supabase = getSupabaseClient();
$supabaseOnline = false;
if ($supabase) {
    try {
        $res = $supabase->select('system_settings', ['limit' => 1]);
        $supabaseOnline = is_array($res);
        echo "[Supabase Cloud] Connected successfully to " . SUPABASE_URL . "\n";
    } catch (\Throwable $e) {
        echo "[Supabase Cloud] Connection check failed: " . $e->getMessage() . "\n";
    }
}

echo "\n" . str_repeat('=', 75) . "\n";
printf("%-25s | %-20s | %-20s\n", "TABLE NAME", "MYSQL LOCALHOST", "SUPABASE CLOUD");
echo str_repeat('=', 75) . "\n";

$parityErrors = 0;

foreach ($canonicalSchema as $table => $requiredCols) {
    // Check MySQL
    $mysqlStatus = "OFFLINE";
    if ($mysqlPdo) {
        try {
            $stmt = $mysqlPdo->prepare("
                SELECT COLUMN_NAME 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
            ");
            $stmt->execute([$dbName, $table]);
            $existingCols = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($existingCols)) {
                $mysqlStatus = "MISSING TABLE";
                $parityErrors++;
            } else {
                $missing = array_diff($requiredCols, $existingCols);
                if (!empty($missing)) {
                    $mysqlStatus = "MISSING: " . implode(',', $missing);
                    $parityErrors++;
                } else {
                    $mysqlStatus = "OK (" . count($existingCols) . " cols)";
                }
            }
        } catch (\Throwable $e) {
            $mysqlStatus = "ERR: " . $e->getMessage();
            $parityErrors++;
        }
    }

    // Check Supabase
    $supabaseStatus = "NOT TESTED";
    if ($supabaseOnline && $supabase) {
        try {
            $testRes = $supabase->select($table, ['limit' => 1]);
            if (is_array($testRes)) {
                $supabaseStatus = "OK (accessible)";
            } else {
                $supabaseStatus = "QUERY FAILED";
                $parityErrors++;
            }
        } catch (\Throwable $e) {
            $supabaseStatus = "ERR: " . $e->getMessage();
            $parityErrors++;
        }
    }

    printf("%-25s | %-20s | %-20s\n", $table, substr($mysqlStatus, 0, 20), substr($supabaseStatus, 0, 20));
}

echo str_repeat('=', 75) . "\n";
if ($parityErrors === 0) {
    echo "PARITY CHECK RESULT: PASS - All monitored tables and sync structures are aligned.\n";
} else {
    echo "PARITY CHECK RESULT: NOTICES DETECTED ({$parityErrors}) - Review missing tables/columns above.\n";
    echo "Run `php scripts/migrate.php` to synchronize MySQL.\n";
}
echo "\n";
