<?php
/**
 * Diagnostic Script - Verify Error Fixes
 * Run this file to check if all fixes have been applied correctly
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>\n<html>\n<head>\n<title>IECEP-LSC Error Fix Diagnostic</title>\n";
echo "<style>body{font-family:Arial,sans-serif;max-width:1200px;margin:20px auto;padding:20px;}";
echo ".success{color:green;}.error{color:red;}.warning{color:orange;}";
echo "table{border-collapse:collapse;width:100%;margin:20px 0;}";
echo "th,td{border:1px solid #ddd;padding:12px;text-align:left;}";
echo "th{background:#0B1D4A;color:white;}</style>\n</head>\n<body>\n";

echo "<h1>🔧 IECEP-LSC Error Fix Diagnostic</h1>\n";
echo "<p>Generated: " . date('Y-m-d H:i:s') . "</p>\n";

$checks = [];

// Check 1: Session Management
echo "<h2>1. Session Management</h2>\n";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    $checks[] = ['Session Start', 'PASS', 'Session started successfully'];
} else {
    $checks[] = ['Session Start', 'INFO', 'Session already active'];
}

// Check 2: Required Files
echo "<h2>2. Required Files</h2>\n";
$requiredFiles = [
    'autoload.php' => __DIR__ . '/autoload.php',
    'includes/paths.php' => __DIR__ . '/includes/paths.php',
    'includes/config.php' => __DIR__ . '/includes/config.php',
    'src/config/config.php' => __DIR__ . '/src/config/config.php',
    'src/lib/SupabaseClient.php' => __DIR__ . '/src/lib/SupabaseClient.php',
    'public/portal/sidebar_admin.php' => __DIR__ . '/public/portal/sidebar_admin.php',
    'public/portal/sidebar.php' => __DIR__ . '/public/portal/sidebar.php',
];

foreach ($requiredFiles as $name => $path) {
    if (file_exists($path)) {
        $checks[] = [$name, 'PASS', 'File exists'];
    } else {
        $checks[] = [$name, 'FAIL', 'File missing'];
    }
}

// Check 3: Load paths and config
echo "<h2>3. Configuration Loading</h2>\n";
try {
    require_once __DIR__ . '/autoload.php';
    require_once __DIR__ . '/includes/paths.php';
    $checks[] = ['Autoload', 'PASS', 'Loaded successfully'];
} catch (Exception $e) {
    $checks[] = ['Autoload', 'FAIL', $e->getMessage()];
}

// Check 4: Constants
echo "<h2>4. Required Constants</h2>\n";
$requiredConstants = [
    'BASE_PATH',
    'BASE_URL',
    'PUBLIC_URL',
    'BASE_PUBLIC_URL',
    'ASSETS_URL',
    'PORTAL_URL',
    'SUPABASE_URL',
    'SUPABASE_ANON_KEY'
];

foreach ($requiredConstants as $constant) {
    if (defined($constant)) {
        $value = constant($constant);
        $displayValue = (strlen($value) > 50) ? substr($value, 0, 50) . '...' : $value;
        $checks[] = [$constant, 'PASS', $displayValue];
    } else {
        $checks[] = [$constant, 'FAIL', 'Not defined'];
    }
}

// Check 5: Supabase Connection
echo "<h2>5. Supabase Connection</h2>\n";
try {
    require_once __DIR__ . '/src/lib/SupabaseClient.php';
    $supabaseConfig = require __DIR__ . '/includes/supabase.php';
    $supabaseClient = new SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);
    $checks[] = ['Supabase Client', 'PASS', 'Client initialized'];
    
    // Try a simple query
    try {
        $result = $supabaseClient->select('pending_affiliations', ['limit' => '1']);
        $checks[] = ['Supabase Query', 'PASS', 'Database accessible'];
    } catch (Exception $e) {
        $checks[] = ['Supabase Query', 'WARNING', 'Query failed: ' . $e->getMessage()];
    }
} catch (Exception $e) {
    $checks[] = ['Supabase Client', 'FAIL', $e->getMessage()];
}

// Check 6: PHP Configuration
echo "<h2>6. PHP Configuration</h2>\n";
$checks[] = ['PHP Version', 'INFO', phpversion()];
$checks[] = ['Session Status', 'INFO', session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive'];
$checks[] = ['Error Reporting', 'INFO', error_reporting()];
$checks[] = ['Display Errors', 'INFO', ini_get('display_errors')];

// Display Results Table
echo "<h2>📊 Diagnostic Results</h2>\n";
echo "<table>\n";
echo "<tr><th>Check</th><th>Status</th><th>Details</th></tr>\n";

$passCount = 0;
$failCount = 0;
$warningCount = 0;

foreach ($checks as $check) {
    $class = 'info';
    if ($check[1] === 'PASS') {
        $class = 'success';
        $passCount++;
    } elseif ($check[1] === 'FAIL') {
        $class = 'error';
        $failCount++;
    } elseif ($check[1] === 'WARNING') {
        $class = 'warning';
        $warningCount++;
    }
    
    echo "<tr>";
    echo "<td>{$check[0]}</td>";
    echo "<td class='{$class}'><strong>{$check[1]}</strong></td>";
    echo "<td>{$check[2]}</td>";
    echo "</tr>\n";
}

echo "</table>\n";

// Summary
echo "<h2>📈 Summary</h2>\n";
echo "<p><span class='success'>✓ Passed: {$passCount}</span> | ";
echo "<span class='error'>✗ Failed: {$failCount}</span> | ";
echo "<span class='warning'>⚠ Warnings: {$warningCount}</span></p>\n";

if ($failCount === 0) {
    echo "<div style='background:#d4edda;border:1px solid #c3e6cb;padding:15px;border-radius:5px;margin:20px 0;'>";
    echo "<h3 style='color:#155724;margin:0;'>✅ All Critical Checks Passed!</h3>";
    echo "<p style='color:#155724;margin:10px 0 0 0;'>Your system is configured correctly. ";
    echo "Don't forget to run the SQL fix in Supabase if you haven't already.</p>";
    echo "</div>";
} else {
    echo "<div style='background:#f8d7da;border:1px solid #f5c6cb;padding:15px;border-radius:5px;margin:20px 0;'>";
    echo "<h3 style='color:#721c24;margin:0;'>❌ Some Checks Failed</h3>";
    echo "<p style='color:#721c24;margin:10px 0 0 0;'>Please review the failed checks above and fix the issues.</p>";
    echo "</div>";
}

// Next Steps
echo "<h2>📝 Next Steps</h2>\n";
echo "<ol>\n";
echo "<li>If all checks passed, run the SQL fix in Supabase SQL Editor:<br>";
echo "<code style='background:#f4f4f4;padding:10px;display:block;margin:10px 0;'>";
echo "See: database/fix_status_constraint.sql";
echo "</code></li>\n";
echo "<li>Restart Apache server</li>\n";
echo "<li>Clear browser cache</li>\n";
echo "<li>Test the application: <a href='index.php'>Go to Homepage</a></li>\n";
echo "<li>Monitor error log: <code>C:\\Users\\ADMIN\\Documents\\xampp\\apache\\logs\\error.log</code></li>\n";
echo "</ol>\n";

echo "<hr>\n";
echo "<p><small>Diagnostic completed at " . date('Y-m-d H:i:s') . "</small></p>\n";
echo "</body>\n</html>";
?>
