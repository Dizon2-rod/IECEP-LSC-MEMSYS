<?php
/**
 * error-checker.php - Quick Error Checker
 * Run this to identify any remaining issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$errors = [];
$warnings = [];
$success = [];

$projectRoot = dirname(__FILE__);

// Check 1: Bootstrap loads
try {
    ob_start();
    require_once $projectRoot . '/bootstrap.php';
    ob_end_clean();
    $success[] = "Bootstrap loaded successfully";
} catch (Exception $e) {
    $errors[] = "Bootstrap error: " . $e->getMessage();
}

// Check 2: Constants defined
$requiredConstants = [
    'BASE_PATH',
    'BASE_URL',
    'PUBLIC_URL',
    'ASSETS_URL',
    'PORTAL_URL',
    'API_URL',
    'APP_URL',
    'APP_ENV',
    'SUPABASE_URL',
    'SUPABASE_ANON_KEY'
];

foreach ($requiredConstants as $const) {
    if (defined($const)) {
        $success[] = "Constant $const defined";
    } else {
        $errors[] = "Constant $const NOT defined";
    }
}

// Check 3: Functions available
$requiredFunctions = [
    'get_path',
    'get_url',
    'supabase',
    'is_logged_in',
    'h',
    'env'
];

foreach ($requiredFunctions as $func) {
    if (function_exists($func)) {
        $success[] = "Function $func available";
    } else {
        $errors[] = "Function $func NOT available";
    }
}

// Check 4: Classes available
$requiredClasses = [
    'App\\Lib\\SupabaseClient'
];

foreach ($requiredClasses as $class) {
    if (class_exists($class)) {
        $success[] = "Class $class available";
    } else {
        $errors[] = "Class $class NOT available";
    }
}

// Check 5: Critical files exist
$criticalFiles = [
    'includes/config.php',
    'includes/navbar.php',
    'includes/footer-new.php',
    'includes/head-meta.php',
    'includes/supabase.php',
    'src/lib/SupabaseClient.php',
    '.env'
];

foreach ($criticalFiles as $file) {
    $path = $projectRoot . '/' . $file;
    if (file_exists($path)) {
        $success[] = "File $file exists";
    } else {
        $errors[] = "File $file NOT found";
    }
}

// Check 6: Includes work
$testIncludes = [
    'includes/navbar.php',
    'includes/footer-new.php',
    'includes/head-meta.php'
];

foreach ($testIncludes as $file) {
    $path = $projectRoot . '/' . $file;
    if (file_exists($path)) {
        try {
            ob_start();
            include $path;
            ob_end_clean();
            $success[] = "Include $file works";
        } catch (Exception $e) {
            $errors[] = "Include $file error: " . $e->getMessage();
        }
    }
}

// Check 7: Error log readable
$logFile = $projectRoot . '/logs/error.log';
if (file_exists($logFile)) {
    if (is_readable($logFile)) {
        $success[] = "Error log is readable";
        $lines = file($logFile);
        $lastLines = array_slice($lines, -5);
        if (!empty($lastLines)) {
            $warnings[] = "Recent errors in log (last 5 lines)";
        }
    } else {
        $warnings[] = "Error log exists but not readable";
    }
} else {
    $warnings[] = "Error log not found";
}

// Check 8: .env file valid
$envFile = $projectRoot . '/.env';
if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    if (strpos($envContent, 'SUPABASE_URL') !== false) {
        $success[] = ".env file contains SUPABASE_URL";
    } else {
        $errors[] = ".env file missing SUPABASE_URL";
    }
    if (strpos($envContent, 'SUPABASE_ANON_KEY') !== false) {
        $success[] = ".env file contains SUPABASE_ANON_KEY";
    } else {
        $errors[] = ".env file missing SUPABASE_ANON_KEY";
    }
} else {
    $errors[] = ".env file not found";
}

// Display results
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error Checker - IECEP-LSC MEMSYS</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #0B1D4A;
            border-bottom: 3px solid #F5A623;
            padding-bottom: 10px;
        }
        h2 {
            color: #0B1D4A;
            margin-top: 30px;
            font-size: 1.3rem;
        }
        .section {
            margin: 20px 0;
        }
        .item {
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
            border-left: 4px solid #ddd;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }
        .warning {
            background-color: #fff3cd;
            color: #856404;
            border-left-color: #ffc107;
        }
        .summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 20px 0;
        }
        .summary-box {
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
            font-size: 1.2rem;
        }
        .summary-success {
            background-color: #d4edda;
            color: #155724;
        }
        .summary-error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .summary-warning {
            background-color: #fff3cd;
            color: #856404;
        }
        .status {
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-weight: bold;
            font-size: 1.1rem;
        }
        .status-ok {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        code {
            background-color: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Error Checker - IECEP-LSC MEMSYS</h1>
        
        <div class="summary">
            <div class="summary-box summary-success">
                ✓ <?php echo count($success); ?> Passed
            </div>
            <div class="summary-box summary-error">
                ✗ <?php echo count($errors); ?> Errors
            </div>
            <div class="summary-box summary-warning">
                ⚠ <?php echo count($warnings); ?> Warnings
            </div>
        </div>

        <?php if (empty($errors)): ?>
            <div class="status status-ok">
                ✓ All checks passed! Your application is ready to use.
            </div>
        <?php else: ?>
            <div class="status status-error">
                ✗ <?php echo count($errors); ?> error(s) found. Please review below.
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
        <div class="section">
            <h2>Errors (<?php echo count($errors); ?>)</h2>
            <?php foreach ($errors as $error): ?>
                <div class="item error">
                    <strong>✗</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($warnings)): ?>
        <div class="section">
            <h2>Warnings (<?php echo count($warnings); ?>)</h2>
            <?php foreach ($warnings as $warning): ?>
                <div class="item warning">
                    <strong>⚠</strong> <?php echo htmlspecialchars($warning); ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
        <div class="section">
            <h2>Passed Checks (<?php echo count($success); ?>)</h2>
            <?php foreach ($success as $item): ?>
                <div class="item success">
                    <strong>✓</strong> <?php echo htmlspecialchars($item); ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="section">
            <h2>Next Steps</h2>
            <ol>
                <li>If all checks passed, visit <a href="<?php echo defined('BASE_URL') ? BASE_URL : '/IECEP-LSC-MEMSYS'; ?>/board-of-trustees.php">board-of-trustees.php</a></li>
                <li>If errors exist, review them above and fix them</li>
                <li>Check <code>logs/error.log</code> for detailed error messages</li>
                <li>Run <a href="<?php echo defined('BASE_URL') ? BASE_URL : '/IECEP-LSC-MEMSYS'; ?>/diagnostic.php">diagnostic.php</a> for more details</li>
            </ol>
        </div>

        <div class="section">
            <h2>Useful Links</h2>
            <ul>
                <li><a href="<?php echo defined('BASE_URL') ? BASE_URL : '/IECEP-LSC-MEMSYS'; ?>/test-bootstrap.php">Bootstrap Test</a></li>
                <li><a href="<?php echo defined('BASE_URL') ? BASE_URL : '/IECEP-LSC-MEMSYS'; ?>/diagnostic.php">Full Diagnostic</a></li>
                <li><a href="<?php echo defined('BASE_URL') ? BASE_URL : '/IECEP-LSC-MEMSYS'; ?>/board-of-trustees.php">Board of Trustees Page</a></li>
            </ul>
        </div>
    </div>
</body>
</html>
