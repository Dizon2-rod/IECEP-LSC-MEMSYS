<?php
/**
 * bootstrap.php - Centralized Bootstrap for IECEP-LSC MEMSYS
 * 
 * This file handles:
 * - Error reporting configuration
 * - Session initialization
 * - Autoloader registration
 * - Environment variable loading
 * - Constant definitions
 * - Supabase client initialization
 */

// ============================================================================
// 1. ERROR REPORTING (Always enable to catch issues)
// ============================================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// ============================================================================
// 2. SESSION INITIALIZATION
// ============================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================================
// 3. PROJECT ROOT AND BASIC PATHS
// ============================================================================
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__FILE__));
}

// ============================================================================
// 4. COMPOSER AUTOLOADER
// ============================================================================
$composerAutoload = PROJECT_ROOT . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// ============================================================================
// 5. CUSTOM AUTOLOADER (PSR-4 compliant)
// ============================================================================
spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $file = PROJECT_ROOT . '/src/' . str_replace('\\', '/', $class) . '.php';
    
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    
    return false;
});

// ============================================================================
// 6. ENVIRONMENT VARIABLES FROM .ENV FILE
// ============================================================================
if (file_exists(PROJECT_ROOT . '/.env')) {
    $lines = file(PROJECT_ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// ============================================================================
// 7. CONSTANT DEFINITIONS (File Paths)
// ============================================================================
if (!defined('BASE_PATH')) {
    define('BASE_PATH', PROJECT_ROOT);
}
if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', BASE_PATH . '/public');
}
if (!defined('SRC_PATH')) {
    define('SRC_PATH', BASE_PATH . '/src/');
}
if (!defined('INCLUDES_PATH')) {
    define('INCLUDES_PATH', BASE_PATH . '/includes/');
}
if (!defined('ASSETS_PATH')) {
    define('ASSETS_PATH', PUBLIC_PATH . '/assets/');
}
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', BASE_PATH . '/storage/');
}

// ============================================================================
// 8. CONSTANT DEFINITIONS (Web URLs)
// ============================================================================
if (!defined('APP_URL')) {
    define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost/IECEP-LSC-MEMSYS');
}
if (!defined('BASE_URL')) {
    define('BASE_URL', APP_URL);
}
if (!defined('PUBLIC_URL')) {
    define('PUBLIC_URL', BASE_URL . '/public');
}
if (!defined('PORTAL_URL')) {
    define('PORTAL_URL', PUBLIC_URL . '/portal');
}
if (!defined('ASSETS_URL')) {
    define('ASSETS_URL', PUBLIC_URL . '/assets');
}
if (!defined('API_URL')) {
    define('API_URL', PUBLIC_URL . '/api');
}
if (!defined('STORAGE_URL')) {
    define('STORAGE_URL', PUBLIC_URL . '/storage');
}

// ============================================================================
// 9. LOAD CONFIGURATION FILES
// ============================================================================
$configPath = INCLUDES_PATH . 'config.php';
if (!file_exists($configPath)) {
    error_log('[Bootstrap] FATAL: config.php not found at ' . $configPath);
    die('Configuration file not found: ' . $configPath);
}
require_once $configPath;

// ============================================================================
// 9.5. LOAD AUDIT HELPERS
// ============================================================================
$auditPath = INCLUDES_PATH . 'audit.php';
if (file_exists($auditPath)) {
    require_once $auditPath;
}

// ============================================================================
// 10. SUPABASE CLIENT (Singleton)
// ============================================================================
if (!function_exists('getSupabaseClient')) {
    $GLOBALS['supabase_client'] = null;
    
    function getSupabaseClient() {
        if ($GLOBALS['supabase_client'] === null) {
            try {
                $supabaseClientPath = SRC_PATH . 'lib/SupabaseClient.php';
                if (!file_exists($supabaseClientPath)) {
                    error_log('[Bootstrap] ERROR: SupabaseClient.php not found at ' . $supabaseClientPath);
                    return null;
                }
                require_once $supabaseClientPath;
                
                $supabaseConfigPath = INCLUDES_PATH . 'supabase.php';
                if (!file_exists($supabaseConfigPath)) {
                    error_log('[Bootstrap] ERROR: supabase.php not found at ' . $supabaseConfigPath);
                    return null;
                }
                $config = require $supabaseConfigPath;
                
                $GLOBALS['supabase_client'] = new \App\Lib\SupabaseClient(
                    $config['url'],
                    $config['anon_key']
                );
            } catch (Exception $e) {
                error_log("[Bootstrap] Supabase initialization error: " . $e->getMessage());
                return null;
            }
        }
        return $GLOBALS['supabase_client'];
    }
}

// ============================================================================
// 11. UTILITY FUNCTIONS
// ============================================================================

/**
 * Get absolute file path from relative path
 */
function get_path($relativePath) {
    return BASE_PATH . '/' . ltrim($relativePath, '/');
}

/**
 * Get web URL for a file
 */
function get_url($relativePath) {
    return PUBLIC_URL . '/' . ltrim($relativePath, '/');
}

/**
 * Get Supabase client instance
 */
function supabase() {
    return getSupabaseClient();
}

// ============================================================================
// 12. HELPER FUNCTIONS FOR COMMON OPERATIONS
// ============================================================================

/**
 * Safe array access with fallback
 */
function safe_get(&$array, $key, $default = null) {
    return isset($array[$key]) ? $array[$key] : $default;
}

/**
 * HTML escape
 */
function h($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Get current user ID
 */
function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 */
function current_user_role() {
    return $_SESSION['role'] ?? null;
}

// Bootstrap complete
error_log('[Bootstrap] IECEP-LSC MEMSYS bootstrap initialized successfully');
