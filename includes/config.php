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

// Application Constants
if (!defined('APP_NAME')) {
    define('APP_NAME', env('APP_NAME', 'IECEP-LSC-MEMSYS'));
    define('APP_URL', rtrim(env('APP_URL', 'http://localhost/IECEP-LSC-MEMSYS'), '/'));
    define('APP_ENV', env('APP_ENV', 'development'));

    // Supabase Configuration
    define('SUPABASE_URL', env('SUPABASE_URL', ''));
    define('SUPABASE_ANON_KEY', env('SUPABASE_ANON_KEY', ''));
    define('SUPABASE_SERVICE_ROLE_KEY', env('SUPABASE_SERVICE_ROLE_KEY', ''));

    // Email Configuration
    define('SMTP_HOST', env('SMTP_HOST', 'smtp.gmail.com'));
    define('SMTP_PORT', (int)env('SMTP_PORT', 587));
    define('SMTP_USERNAME', env('SMTP_USERNAME', ''));
    define('SMTP_PASSWORD', env('SMTP_PASSWORD', ''));
    define('SMTP_FROM_NAME', env('SMTP_FROM_NAME', 'IECEP-LSC-MEMSYS'));
    define('SMTP_FROM_EMAIL', env('SMTP_FROM_EMAIL', ''));

    // Security
    define('JWT_SECRET', env('JWT_SECRET', ''));
    define('SESSION_LIFETIME', (int)env('SESSION_LIFETIME', 86400));
    define('CRON_SECRET', env('CRON_SECRET', ''));

    // File Upload Configuration
    define('MAX_FILE_SIZE', (int)env('MAX_FILE_SIZE', 5242880)); // 5MB
    define('ALLOWED_FILE_TYPES', env('ALLOWED_FILE_TYPES', 'pdf,doc,docx,jpg,jpeg,png'));
    define('ALLOWED_FILE_TYPES_ARRAY', array_filter(array_map('trim', explode(',', ALLOWED_FILE_TYPES))));

    // Storage
    if (!defined('STORAGE_PATH')) {
        define('STORAGE_PATH', dirname(__DIR__) . '/storage');
    }
    if (!defined('STORAGE_URL')) {
        define('STORAGE_URL', APP_URL . '/storage');
    }

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
if (!defined('CSS_URL')) {
    define('CSS_URL', PUBLIC_URL . '/css');
}
if (!defined('JS_URL')) {
    define('JS_URL', PUBLIC_URL . '/js');
}
if (!defined('API_URL')) {
    define('API_URL', PUBLIC_URL . '/api');
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
