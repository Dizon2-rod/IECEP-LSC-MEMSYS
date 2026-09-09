<?php
require_once __DIR__ . '/bootstrap.php';
// Simple autoloader for IECEP-LSC MEMSYS

// Include Composer autoloader if available
$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
        return true;
    }

    // Support PSR-4 App\ mapping to src/
    if (strpos($class, 'App\\') === 0) {
        $relativeClass = substr($class, 4);
        $psr4File = __DIR__ . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($psr4File)) {
            require_once $psr4File;
            return true;
        }

        // Support lowercase directory/file names, e.g., App\Lib\CsvService -> src/lib/csv.php
        if (strtolower($relativeClass) === 'lib/csvservice') {
            $csvFile = __DIR__ . '/src/lib/csv.php';
            if (file_exists($csvFile)) {
                require_once $csvFile;
                return true;
            }
        }
    }
    
    // Check vendor directory for Composer packages
    $vendorFile = __DIR__ . '/vendor/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($vendorFile)) {
        require_once $vendorFile;
        return true;
    }
    
    return false;
});

// Load environment variables manually if .env file exists
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

// Constants will be defined in config.php
?>
