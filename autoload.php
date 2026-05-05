<?php
// Simple autoloader for IECEP-LSC MEMSYS

// Include Composer autoloader if available
$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $class) . '.php';
    
    // Check if file exists
    if (file_exists($file)) {
        require_once $file;
        return true;
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
