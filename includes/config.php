<?php
// config.php - Application Configuration
// Load Composer autoloader if available (dependencies installed)
$composerAutoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}
// Always load custom autoloader
require_once __DIR__ . '/../autoload.php';

// Load environment variables from .env file manually
if (!function_exists('loadEnv')) {
    function loadEnv($path) {
        if (!file_exists($path)) {
            return;
        }
        
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $name = trim($parts[0]);
                $value = trim($parts[1]);
                
                // Remove quotes if present
                if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                    (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }
                
                if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                    putenv(sprintf('%s=%s', $name, $value));
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }
}

loadEnv(__DIR__ . '/../../.env');

// Application Constants
if (!defined('APP_NAME')) {
    define('APP_NAME', $_ENV['APP_NAME'] ?? 'IECEP-LSC-MEMSYS');
    define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost/IECEP-LSC-MEMSYS');
    define('APP_ENV', $_ENV['APP_ENV'] ?? 'development');

    // Supabase Configuration
    define('SUPABASE_URL', $_ENV['SUPABASE_URL'] ?? 'https://kfvlbjvtwtxnpmmswadf.supabase.co');
    define('SUPABASE_ANON_KEY', $_ENV['SUPABASE_ANON_KEY'] ?? 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImtmdmxianZ0d3R4bnBtbXN3YWRmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzY0MDY0ODEsImV4cCI6MjA5MTk4MjQ4MX0.4o-RyygAaEnM61wfvc24xWGXMe3jVqZLPvh8bXUYxkg');
    define('SUPABASE_SERVICE_ROLE_KEY', $_ENV['SUPABASE_SERVICE_ROLE_KEY'] ?? 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImtmdmxianZ0d3R4bnBtbXN3YWRmIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc3NjQwNjQ4MSwiZXhwIjoyMDkxOTgyNDgxfQ.JEYE5nCvnxSZ9F1cfGe43e8CDE_CEcJYwANQuRa1Jnk');


    // Email Configuration
    define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
    define('SMTP_PORT', $_ENV['SMTP_PORT'] ?? 587);
    define('SMTP_USERNAME', $_ENV['SMTP_USERNAME'] ?? 'rasheddizon7@gmail.com');
    define('SMTP_PASSWORD', $_ENV['SMTP_PASSWORD'] ?? 'wqyvufrkrgoxfosk');
    define('SMTP_FROM_NAME', $_ENV['SMTP_FROM_NAME'] ?? 'IECEP-LSC-MEMSYS');
    define('SMTP_FROM_EMAIL', $_ENV['SMTP_FROM_EMAIL'] ?? 'rasheddizon7@gmail.com');

    // Security
    define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? 'default-secret-change-in-production');
    define('SESSION_LIFETIME', $_ENV['SESSION_LIFETIME'] ?? 86400); // 24 hours
    define('CRON_SECRET', $_ENV['CRON_SECRET'] ?? 'change-me');

    // File Upload Configuration
    define('MAX_FILE_SIZE', $_ENV['MAX_FILE_SIZE'] ?? 5242880); // 5MB
    define('ALLOWED_FILE_TYPES', $_ENV['ALLOWED_FILE_TYPES'] ?? 'pdf,doc,docx,jpg,jpeg,png');


    // Database Table Names
    define('TABLE_USERS', 'user_profiles');
    define('TABLE_MEMBERS', 'members');
    define('TABLE_INSTITUTIONS', 'institutions');
    define('TABLE_TRANSACTIONS', 'transactions');
    define('TABLE_EMAIL_VERIFICATIONS', 'email_verifications');
    define('TABLE_PENDING_MEMBERS', 'pending_members');
    define('TABLE_PENDING_AFFILIATIONS', 'pending_affiliations');
    define('TABLE_ATTENDANCE', 'attendance');
}

// Error Reporting
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../../logs/error.log');
}

// Session Configuration (only set if session is not active)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', APP_ENV === 'production');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
}

// Timezone
date_default_timezone_set('Asia/Manila');

$config = [
    'app_name' => APP_NAME,
    'app_url' => APP_URL,
    'app_env' => APP_ENV,
    'supabase' => [
        'url' => SUPABASE_URL,
        'anon_key' => SUPABASE_ANON_KEY,
        'service_role_key' => SUPABASE_SERVICE_ROLE_KEY
    ],
    'email' => [
        'host' => SMTP_HOST,
        'port' => SMTP_PORT,
        'username' => SMTP_USERNAME,
        'password' => SMTP_PASSWORD,
        'from_name' => SMTP_FROM_NAME,
        'from_email' => SMTP_FROM_EMAIL
    ],
    'security' => [
        'jwt_secret' => JWT_SECRET,
        'session_lifetime' => SESSION_LIFETIME
    ],
    'upload' => [
        'max_size' => MAX_FILE_SIZE,
        'allowed_types' => ALLOWED_FILE_TYPES
    ]
];

// Function to output frontend SUPABASE configuration as JavaScript
if (!function_exists('outputFrontendConfig')) {
    function outputFrontendConfig() {
        $config = [
            'SUPABASE_URL' => SUPABASE_URL,
            'SUPABASE_ANON_KEY' => SUPABASE_ANON_KEY,
            'APP_URL' => APP_URL,
            'APP_ENV' => APP_ENV,
            'PORTAL_URL' => PORTAL_URL ?? APP_URL . '/portal'
        ];
        ?>
        <script>
            // Frontend Configuration (populated from server config)
            window.IECEP_CONFIG = {
                SUPABASE_URL: '<?= htmlspecialchars(SUPABASE_URL, ENT_QUOTES) ?>',
                SUPABASE_ANON_KEY: '<?= htmlspecialchars(SUPABASE_ANON_KEY, ENT_QUOTES) ?>',
                APP_URL: '<?= htmlspecialchars(APP_URL, ENT_QUOTES) ?>',
                APP_ENV: '<?= htmlspecialchars(APP_ENV, ENT_QUOTES) ?>',
                PORTAL_URL: '<?= htmlspecialchars(PORTAL_URL ?? (APP_URL . '/portal'), ENT_QUOTES) ?>'
            };
        </script>
        <?php
    }
}

// Initialize SupabaseClient for global use
if (!class_exists('\App\Lib\SupabaseClient')) {
    require_once __DIR__ . '/../src/lib/SupabaseClient.php';
}
$supabaseClient = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);

// Initialize BlockchainService globally
if (!isset($GLOBALS['blockchain']) && isset($supabaseClient)) {
    require_once __DIR__ . '/../src/lib/BlockchainService.php';
    $GLOBALS['blockchain'] = new \App\Lib\BlockchainService($supabaseClient);
}

return $config;
