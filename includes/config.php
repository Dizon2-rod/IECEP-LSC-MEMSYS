<?php
require_once __DIR__ . '/../bootstrap.php';
// config.php - Application Configuration
// Load Composer autoloader if available (dependencies installed)
$composerAutoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// Load environment variables from .env file manually
if (!function_exists('loadEnv')) {
    function loadEnv($path)
    {
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

                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

if (!function_exists('env')) {
    function env(string $name, $default = null)
    {
        if (array_key_exists($name, $_ENV) && $_ENV[$name] !== null) {
            return $_ENV[$name];
        }
        if (array_key_exists($name, $_SERVER) && $_SERVER[$name] !== null) {
            return $_SERVER[$name];
        }
        return $default;
    }
}

if (!function_exists('validateEnv')) {
    function validateEnv(array $required): void
    {
        $missing = [];
        foreach ($required as $key) {
            $value = env($key, null);
            if ($value === null || $value === '') {
                $missing[] = $key;
            }
        }
        if (!empty($missing)) {
            $message = 'Missing required environment variables: ' . implode(', ', $missing);
            error_log($message);
            throw new RuntimeException($message);
        }
    }
}

loadEnv(__DIR__ . '/../../.env');

// Application Constants - Only define if not already defined by bootstrap.php
if (!defined('APP_NAME')) {
    define('APP_NAME', env('APP_NAME', 'IECEP-LSC-MEMSYS'));
}
if (!defined('APP_URL')) {
    define('APP_URL', rtrim(env('APP_URL', 'http://localhost/IECEP-LSC-MEMSYS'), '/'));
}
if (!defined('APP_ENV')) {
    define('APP_ENV', env('APP_ENV', 'development'));
}

// Supabase Configuration
if (!defined('SUPABASE_URL')) {
    define('SUPABASE_URL', env('SUPABASE_URL', ''));
}
if (!defined('SUPABASE_ANON_KEY')) {
    define('SUPABASE_ANON_KEY', env('SUPABASE_ANON_KEY', ''));
}
if (!defined('SUPABASE_SERVICE_ROLE_KEY')) {
    define('SUPABASE_SERVICE_ROLE_KEY', env('SUPABASE_SERVICE_ROLE_KEY', ''));
}

// Email Configuration
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', env('SMTP_HOST', 'smtp.gmail.com'));
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', (int)env('SMTP_PORT', 587));
}
if (!defined('SMTP_USERNAME')) {
    define('SMTP_USERNAME', env('SMTP_USERNAME', ''));
}
if (!defined('SMTP_PASSWORD')) {
    define('SMTP_PASSWORD', env('SMTP_PASSWORD', ''));
}
if (!defined('SMTP_FROM_NAME')) {
    define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'IECEP-LSC-MEMSYS'));
}
if (!defined('SMTP_FROM_EMAIL')) {
    define('SMTP_FROM_EMAIL', env('SMTP_FROM_EMAIL', ''));
}

// Security
if (!defined('JWT_SECRET')) {
    define('JWT_SECRET', env('JWT_SECRET', ''));
}
if (!defined('SESSION_LIFETIME')) {
    define('SESSION_LIFETIME', (int)env('SESSION_LIFETIME', 86400));
}
if (!defined('CRON_SECRET')) {
    define('CRON_SECRET', env('CRON_SECRET', ''));
}

// File Upload Configuration
if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', (int)env('MAX_FILE_SIZE', 5242880)); // 5MB
}
if (!defined('ALLOWED_FILE_TYPES')) {
    define('ALLOWED_FILE_TYPES', env('ALLOWED_FILE_TYPES', 'pdf,doc,docx,jpg,jpeg,png'));
}
if (!defined('ALLOWED_FILE_TYPES_ARRAY')) {
    define('ALLOWED_FILE_TYPES_ARRAY', array_filter(array_map('trim', explode(',', ALLOWED_FILE_TYPES))));
}

// Database Table Names
if (!defined('TABLE_USERS')) {
    define('TABLE_USERS', 'user_profiles');
}
if (!defined('TABLE_MEMBERS')) {
    define('TABLE_MEMBERS', 'members');
}
if (!defined('TABLE_INSTITUTIONS')) {
    define('TABLE_INSTITUTIONS', 'institutions');
}
if (!defined('TABLE_TRANSACTIONS')) {
    define('TABLE_TRANSACTIONS', 'transactions');
}
if (!defined('TABLE_EMAIL_VERIFICATIONS')) {
    define('TABLE_EMAIL_VERIFICATIONS', 'email_verifications');
}
if (!defined('TABLE_PENDING_MEMBERS')) {
    define('TABLE_PENDING_MEMBERS', 'pending_members');
}
if (!defined('TABLE_PENDING_AFFILIATIONS')) {
    define('TABLE_PENDING_AFFILIATIONS', 'pending_affiliations');
}
if (!defined('TABLE_ATTENDANCE')) {
    define('TABLE_ATTENDANCE', 'attendance');
}

// Validate critical environment values in production and warn in development
$requiredEnv = [
    'APP_NAME',
    'APP_URL',
    'APP_ENV',
    'SUPABASE_URL',
    'SUPABASE_ANON_KEY',
    'SUPABASE_SERVICE_ROLE_KEY',
    'SMTP_HOST',
    'SMTP_PORT',
    'SMTP_USERNAME',
    'SMTP_PASSWORD',
    'SMTP_FROM_EMAIL',
    'JWT_SECRET',
    'CRON_SECRET',
];

try {
    if (APP_ENV === 'production') {
        validateEnv($requiredEnv);
    } else {
        $missing = [];
        foreach ($requiredEnv as $key) {
            $value = env($key, null);
            if ($value === null || $value === '') {
                $missing[] = $key;
            }
        }
        if (!empty($missing)) {
            error_log('WARNING: Missing environment variables in development: ' . implode(', ', $missing));
        }
    }
} catch (Throwable $e) {
    if (APP_ENV === 'production') {
        http_response_code(500);
        echo '<pre>Configuration error: ' . htmlspecialchars($e->getMessage()) . '</pre>';
        exit;
    }
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
if (!class_exists('App\\Lib\\SupabaseClient')) {
    $supabaseClientPath = __DIR__ . '/../src/lib/SupabaseClient.php';
    if (file_exists($supabaseClientPath)) {
        require_once $supabaseClientPath;
    }
}

if (class_exists('App\\Lib\\SupabaseClient')) {
    $supabaseClient = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);
    
    // Initialize BlockchainService globally
    if (!isset($GLOBALS['blockchain'])) {
        $blockchainPath = __DIR__ . '/../src/lib/BlockchainService.php';
        if (file_exists($blockchainPath)) {
            require_once $blockchainPath;
            $GLOBALS['blockchain'] = new \App\Lib\BlockchainService($supabaseClient);
        }
    }
}

return $config;
