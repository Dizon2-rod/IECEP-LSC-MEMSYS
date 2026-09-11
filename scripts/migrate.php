<?php
/**
 * scripts/migrate.php - Database Migration Runner
 * 
 * Tracks executed migrations in the `schema_migrations` table.
 * Supports MySQL (XAMPP localhost) and reports Supabase cloud status.
 * 
 * Usage:
 *   php scripts/migrate.php [--target=mysql|status]
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Error: CLI execution only.\n");
}

require_once __DIR__ . '/../bootstrap.php';

$options = getopt('', ['target::', 'status::']);
$target = $options['target'] ?? 'mysql';
$isStatusOnly = isset($options['status']);

echo "====================================================\n";
echo " IECEP-LSC MEMSYS — Schema Migration Runner\n";
echo " Target: " . strtoupper($target) . "\n";
echo " Time:   " . date('Y-m-d H:i:s') . "\n";
echo "====================================================\n\n";

$migrationsDir = dirname(__DIR__) . '/database/migrations';
if (!is_dir($migrationsDir)) {
    die("Error: Migrations directory not found at $migrationsDir\n");
}

// 1. Connect to MySQL / XAMPP Localhost via PDO
$dbHost = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '127.0.0.1');
$dbPort = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? 3306);
$dbName = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'iecep_lsc_memsys');
$dbUser = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'root');
$dbPass = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? '');

$pdo = null;
try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Ensure database exists and select it
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbName}`");
} catch (PDOException $e) {
    echo "[MySQL Connection Warning] Could not connect to local MySQL: " . $e->getMessage() . "\n";
    echo "If using cloud Supabase only, migrations are applied via Supabase SQL Editor.\n";
}

if ($pdo) {
    // 2. Ensure schema_migrations table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `schema_migrations` (
            `version` VARCHAR(255) NOT NULL PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 3. Fetch already applied versions
    $stmt = $pdo->query("SELECT version, name, applied_at FROM `schema_migrations` ORDER BY version ASC");
    $appliedRows = $stmt->fetchAll();
    $appliedMap = [];
    foreach ($appliedRows as $row) {
        $appliedMap[$row['version']] = $row;
    }

    // 4. Scan migration files
    $files = scandir($migrationsDir);
    sort($files);

    // Group files by migration version (e.g., '002', '003', ..., '013')
    $versionFiles = [];
    foreach ($files as $f) {
        if (!preg_match('/^([0-9]{3})_([a-zA-Z0-9_\-]+?)(\.sql|_mysql\.sql)$/', $f, $m)) {
            continue;
        }
        $version = $m[1];
        $slug = $m[2];
        $isMysql = str_contains($f, '_mysql.sql');

        if (!isset($versionFiles[$version])) {
            $versionFiles[$version] = [
                'version' => $version,
                'name' => $slug,
                'default' => null,
                'mysql' => null
            ];
        }
        if ($isMysql) {
            $versionFiles[$version]['mysql'] = $f;
        } else {
            $versionFiles[$version]['default'] = $f;
        }
    }

    echo "Status of Migrations (MySQL / XAMPP):\n";
    echo str_repeat('-', 70) . "\n";
    printf("%-10s %-35s %-12s %s\n", "VERSION", "MIGRATION NAME", "STATUS", "APPLIED AT");
    echo str_repeat('-', 70) . "\n";

    $appliedCount = 0;
    foreach ($versionFiles as $ver => $meta) {
        $isApplied = isset($appliedMap[$ver]);
        $appliedAt = $isApplied ? $appliedMap[$ver]['applied_at'] : '-';
        $statusStr = $isApplied ? "[APPLIED]" : "[PENDING]";

        printf("%-10s %-35s %-12s %s\n", $ver, substr($meta['name'], 0, 34), $statusStr, $appliedAt);

        if (!$isApplied && !$isStatusOnly) {
            // Pick file to execute: prefer mysql variant if available
            $chosenFile = $meta['mysql'] ?? $meta['default'];
            if (!$chosenFile) continue;

            $filePath = $migrationsDir . '/' . $chosenFile;
            $sqlContent = file_get_contents($filePath);
            if ($sqlContent === false) continue;

            echo "  -> Executing: {$chosenFile}... ";
            try {
                // Execute multi-query script
                $pdo->exec($sqlContent);

                // Record in schema_migrations
                $ins = $pdo->prepare("INSERT IGNORE INTO `schema_migrations` (`version`, `name`, `applied_at`) VALUES (?, ?, NOW())");
                $ins->execute([$ver, $meta['name']]);

                echo "DONE\n";
                $appliedCount++;
            } catch (\Throwable $ex) {
                echo "FAILED: " . $ex->getMessage() . "\n";
            }
        }
    }

    echo str_repeat('-', 70) . "\n";
    echo "Execution Summary: {$appliedCount} pending migration(s) applied.\n\n";
}

echo "To synchronize Supabase PostgreSQL:\n";
echo "  Run database/migrations/013_schema_migrations_and_sync.sql in Supabase SQL Editor.\n";
echo "Done.\n";
