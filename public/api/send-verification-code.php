<?php
/**
 * API Endpoint: Send Email Verification Code
 * Location: public/api/send-verification-code.php
 * 
 * Features:
 * - Rate-limited: 60-second cooldown per email
 * - Sends 6-digit cryptographic one-time verification code via SMTP (465 SSL / 587 TLS)
 * - Directly addresses user's submitted email ($email) - never hardcoded
 * - Server-side error logging with safe, generic client error messages
 * - Dual database support (Supabase PostgreSQL + XAMPP MySQL)
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Only POST requests are accepted.'
    ]);
    exit;
}

// Read input (supports JSON payload and form-data)
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}

$rawEmail = $input['email'] ?? '';
$email = strtolower(trim((string)$rawEmail));

// 1. Validate email format
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please provide a valid email address.'
    ]);
    exit;
}

// Load dependencies
require_once dirname(__DIR__, 2) . '/autoload.php';
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/src/lib/EmailService.php';
require_once dirname(__DIR__, 2) . '/src/lib/SupabaseClient.php';

use App\Lib\EmailService;
use App\Lib\SupabaseClient;

// Initialize Supabase client
$supabaseConfig = require dirname(__DIR__, 2) . '/includes/supabase.php';
$supabase = null;
try {
    if (!empty($supabaseConfig['url']) && !empty($supabaseConfig['anon_key'])) {
        $supabase = new SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);
    }
} catch (\Throwable $e) {
    error_log('[send-verification-code] Supabase init warning: ' . $e->getMessage());
}

// 2. Rate-Limiting: Check 60-second cooldown
$now = time();
$cooldownSeconds = 60;
$sessionCooldownKey = 'verification_sent_at_' . md5($email);
$cacheCooldownFile = sys_get_temp_dir() . '/memsys_send_' . md5($email) . '.txt';

// Check cache file cooldown first (persists across sessions / curl requests)
if (file_exists($cacheCooldownFile)) {
    $lastSent = (int)@file_get_contents($cacheCooldownFile);
    if (($now - $lastSent) < $cooldownSeconds) {
        $secondsRemaining = $cooldownSeconds - ($now - $lastSent);
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => "Please wait {$secondsRemaining} seconds before requesting another verification code.",
            'retry_after' => $secondsRemaining
        ]);
        exit;
    }
}

// Check session-level cooldown
if (isset($_SESSION[$sessionCooldownKey]) && ($now - $_SESSION[$sessionCooldownKey]) < $cooldownSeconds) {
    $secondsRemaining = $cooldownSeconds - ($now - $_SESSION[$sessionCooldownKey]);
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => "Please wait {$secondsRemaining} seconds before requesting another verification code.",
        'retry_after' => $secondsRemaining
    ]);
    exit;
}

// Check database-level cooldown for persistence across reloads / devices
if ($supabase) {
    try {
        $recentCodes = $supabase->select('email_verifications', [
            'email' => 'eq.' . $email,
            'order' => 'created_at.desc',
            'limit' => 1
        ]);

        if (is_array($recentCodes) && !empty($recentCodes[0]['created_at'])) {
            $lastCreatedAt = strtotime($recentCodes[0]['created_at']);
            $timeSinceLast = $now - $lastCreatedAt;
            if ($timeSinceLast < $cooldownSeconds) {
                $secondsRemaining = $cooldownSeconds - $timeSinceLast;
                http_response_code(429);
                echo json_encode([
                    'success' => false,
                    'message' => "Please wait {$secondsRemaining} seconds before requesting another verification code.",
                    'retry_after' => $secondsRemaining
                ]);
                exit;
            }
        }
    } catch (\Throwable $e) {
        error_log('[send-verification-code] Database cooldown check warning: ' . $e->getMessage());
    }
}

// 3. Generate 6-Digit Verification Code
try {
    $code = (string)random_int(100000, 999999);
} catch (\Throwable $e) {
    $code = str_pad((string)mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
}

// Generate UUID v4 for PostgreSQL / MySQL compatibility
$uuid = sprintf(
    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    mt_rand(0, 0xffff),
    mt_rand(0, 0x0fff) | 0x4000,
    mt_rand(0, 0x3fff) | 0x8000,
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
);

$expiresAt = date('Y-m-d H:i:s', $now + 600); // 10 minutes expiry

// 4. Store verification record in database
$savedInDb = false;

// 4A. Store in Supabase
if ($supabase) {
    try {
        // Attempt full insert with attempts column
        $supabaseData = [
            'id' => $uuid,
            'email' => $email,
            'code' => $code,
            'expires_at' => $expiresAt,
            'verified' => false,
            'attempts' => 0
        ];
        
        try {
            $supabase->insert('email_verifications', $supabaseData);
            $savedInDb = true;
        } catch (\Throwable $colErr) {
            // If the table exists but the 'attempts' column has not been added via SQL yet:
            if (strpos($colErr->getMessage(), 'attempts') !== false || strpos($colErr->getMessage(), 'PGRST204') !== false) {
                error_log('[send-verification-code] Supabase column attempts missing, falling back to base columns');
                unset($supabaseData['attempts']);
                $supabase->insert('email_verifications', $supabaseData);
                $savedInDb = true;
            } else {
                throw $colErr;
            }
        }
    } catch (\Throwable $se) {
        error_log('[send-verification-code] Supabase insert error: ' . $se->getMessage());
    }
}

// 4B. Optional local XAMPP MySQL PDO insert
try {
    $dbHost = env('DB_HOST', '127.0.0.1');
    $dbName = env('DB_NAME', 'iecep_lsc_memsys');
    $dbUser = env('DB_USER', 'root');
    $dbPass = env('DB_PASS', '');

    $pdo = new \PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_SILENT,
        \PDO::ATTR_TIMEOUT => 2
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO email_verifications (id, email, code, expires_at, verified, attempts, created_at)
        VALUES (:id, :email, :code, :expires_at, 0, 0, NOW())
    ");
    if ($stmt) {
        $stmt->execute([
            ':id' => $uuid,
            ':email' => $email,
            ':code' => $code,
            ':expires_at' => $expiresAt
        ]);
        $savedInDb = true;
    }
} catch (\Throwable $me) {
    // MySQL is optional when running on Supabase
}

// Keep in session as well for guaranteed instant validation
$_SESSION['verification_code_' . md5($email)] = $code;
$_SESSION['verification_expires_' . md5($email)] = $now + 600;
$_SESSION['verification_attempts_' . md5($email)] = 0;
$_SESSION['verification_id_' . md5($email)] = $uuid;

// 5. Send Verification Code via EmailService (SMTP 465 SSL / 587 TLS)
try {
    $emailService = new EmailService();
    
    // Ensure $email is passed directly to sendVerificationCode - never hardcoded
    $sent = $emailService->sendVerificationCode($email, $code);

    if ($sent) {
        // Set cooldown timestamp
        $_SESSION[$sessionCooldownKey] = $now;
        @file_put_contents($cacheCooldownFile, (string)$now);
        $cacheAttemptsFile = sys_get_temp_dir() . '/memsys_attempts_' . md5($email) . '.txt';
        @unlink($cacheAttemptsFile);

        echo json_encode([
            'success' => true,
            'message' => 'Verification code sent to your email. Please check your inbox and spam folder.'
        ]);
        exit;
    } else {
        error_log("[send-verification-code] EmailService failed to send verification code to: {$email}");
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Unable to send verification code at this time. Please check the email address and try again later.'
        ]);
        exit;
    }
} catch (\Throwable $ex) {
    // Server-side logging of raw exception
    error_log("[send-verification-code] Send exception for {$email}: " . $ex->getMessage());

    // Generic client-facing error message (do NOT expose raw SMTP/PHPMailer exception details)
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to send verification code at this time. Please try again later.'
    ]);
    exit;
}
