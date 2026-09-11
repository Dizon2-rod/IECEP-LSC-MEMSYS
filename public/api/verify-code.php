<?php
/**
 * API Endpoint: Verify Email Verification Code
 * Location: public/api/verify-code.php
 * 
 * Features:
 * - Brute-force protection: Locks code after 5 incorrect attempts
 * - Expiration check (10 minutes)
 * - Updates verified status in database (Supabase PostgreSQL + XAMPP MySQL)
 * - Sets session verification status for secure form submission
 */

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
$code = trim((string)($input['code'] ?? ''));

// 1. Input Validation
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please provide a valid email address.'
    ]);
    exit;
}

if (empty($code) || !preg_match('/^[0-9]{6}$/', $code)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please enter the complete 6-digit verification code.'
    ]);
    exit;
}

// Load dependencies
require_once dirname(__DIR__, 2) . '/autoload.php';
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/src/lib/SupabaseClient.php';

use App\Lib\SupabaseClient;

// Initialize Supabase
$supabaseConfig = require dirname(__DIR__, 2) . '/includes/supabase.php';
$supabase = null;
try {
    if (!empty($supabaseConfig['url']) && !empty($supabaseConfig['anon_key'])) {
        $supabase = new SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);
    }
} catch (\Throwable $e) {
    error_log('[verify-code] Supabase init warning: ' . $e->getMessage());
}

$now = time();
$maxAttempts = 5;
$sessionAttemptsKey = 'verification_attempts_' . md5($email);
$sessionAttempts = (int)($_SESSION[$sessionAttemptsKey] ?? 0);
$cacheAttemptsFile = sys_get_temp_dir() . '/memsys_attempts_' . md5($email) . '.txt';
$fileAttempts = file_exists($cacheAttemptsFile) ? (int)@file_get_contents($cacheAttemptsFile) : 0;

$record = null;
$recordId = null;
$dbAttempts = 0;
$dbCode = null;
$isExpired = false;
$isVerified = false;

// 2. Fetch latest verification code for this email from Supabase
if ($supabase) {
    try {
        $results = $supabase->select('email_verifications', [
            'email' => 'eq.' . $email,
            'order' => 'created_at.desc',
            'limit' => 1
        ]);

        if (is_array($results) && !empty($results[0])) {
            $record = $results[0];
            $recordId = $record['id'] ?? null;
            $dbCode = trim((string)($record['code'] ?? ''));
            $dbAttempts = (int)($record['attempts'] ?? 0);
            $isVerified = (bool)($record['verified'] ?? false);

            if (!empty($record['expires_at'])) {
                $isExpired = strtotime($record['expires_at']) < $now;
            }
        }
    } catch (\Throwable $e) {
        error_log('[verify-code] Supabase select warning: ' . $e->getMessage());
    }
}

// 3. Fallback to MySQL if Supabase record wasn't found
if (!$record) {
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
            SELECT id, email, code, expires_at, verified, attempts 
            FROM email_verifications 
            WHERE email = :email 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        if ($stmt) {
            $stmt->execute([':email' => $email]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                $record = $row;
                $recordId = $row['id'];
                $dbCode = trim((string)$row['code']);
                $dbAttempts = (int)$row['attempts'];
                $isVerified = (bool)$row['verified'];
                $isExpired = strtotime($row['expires_at']) < $now;
            }
        }
    } catch (\Throwable $me) {
        // MySQL optional
    }
}

// 4. Session fallback if database records are pending
if (!$record && isset($_SESSION['verification_code_' . md5($email)])) {
    $dbCode = $_SESSION['verification_code_' . md5($email)];
    $dbAttempts = $sessionAttempts;
    $isExpired = ($now > ($_SESSION['verification_expires_' . md5($email)] ?? 0));
}

// If no code exists at all for this email
if ($dbCode === null) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'No verification code found for this email. Please request a new code.'
    ]);
    exit;
}

// Current effective attempts count
$currentAttempts = max($dbAttempts, $sessionAttempts, $fileAttempts);

// 5. Brute-Force Check: Attempt Limit
if ($currentAttempts >= $maxAttempts) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'locked' => true,
        'message' => 'Too many incorrect attempts. This code is locked. Please request a new code.'
    ]);
    exit;
}

// 6. Expiration Check
if ($isExpired) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'expired' => true,
        'message' => 'Verification code has expired. Please request a new code.'
    ]);
    exit;
}

// 7. Verification Code Comparison
if ($dbCode === $code) {
    // SUCCESS: Mark as verified
    $newAttempts = $currentAttempts + 1;
    $_SESSION[$sessionAttemptsKey] = 0;
    $_SESSION['verified_email'] = $email;
    $_SESSION['email_verified'] = true;
    @unlink($cacheAttemptsFile);

    // Update in Supabase
    if ($supabase && $recordId) {
        try {
            $updateData = [
                'verified' => true,
                'attempts' => $newAttempts
            ];
            try {
                $supabase->update('email_verifications', $updateData, $recordId);
            } catch (\Throwable $colErr) {
                // If 'attempts' column doesn't exist yet in Supabase schema:
                $supabase->update('email_verifications', ['verified' => true], $recordId);
            }
        } catch (\Throwable $se) {
            error_log('[verify-code] Supabase update verified error: ' . $se->getMessage());
        }
    }

    // Update in MySQL
    try {
        $dbHost = env('DB_HOST', '127.0.0.1');
        $dbName = env('DB_NAME', 'iecep_lsc_memsys');
        $pdo = new \PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", env('DB_USER', 'root'), env('DB_PASS', ''), [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_SILENT,
            \PDO::ATTR_TIMEOUT => 2
        ]);
        $stmt = $pdo->prepare("UPDATE email_verifications SET verified = 1, attempts = :att WHERE id = :id");
        if ($stmt && $recordId) {
            $stmt->execute([':att' => $newAttempts, ':id' => $recordId]);
        }
    } catch (\Throwable $me) {}

    echo json_encode([
        'success' => true,
        'message' => 'Email verified successfully!'
    ]);
    exit;
} else {
    // FAILURE: Increment attempts
    $newAttempts = $currentAttempts + 1;
    $_SESSION[$sessionAttemptsKey] = $newAttempts;
    @file_put_contents($cacheAttemptsFile, (string)$newAttempts);

    // Update attempts in Supabase
    if ($supabase && $recordId) {
        try {
            $supabase->update('email_verifications', ['attempts' => $newAttempts], $recordId);
        } catch (\Throwable $se) {
            // column attempts may not exist yet
        }
    }

    // Update attempts in MySQL
    try {
        $dbHost = env('DB_HOST', '127.0.0.1');
        $dbName = env('DB_NAME', 'iecep_lsc_memsys');
        $pdo = new \PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", env('DB_USER', 'root'), env('DB_PASS', ''), [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_SILENT,
            \PDO::ATTR_TIMEOUT => 2
        ]);
        $stmt = $pdo->prepare("UPDATE email_verifications SET attempts = :att WHERE id = :id");
        if ($stmt && $recordId) {
            $stmt->execute([':att' => $newAttempts, ':id' => $recordId]);
        }
    } catch (\Throwable $me) {}

    $attemptsLeft = max(0, $maxAttempts - $newAttempts);

    if ($attemptsLeft <= 0) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'locked' => true,
            'message' => 'Too many incorrect attempts. This code is locked. Please request a new code.',
            'attempts_left' => 0
        ]);
        exit;
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Invalid verification code. You have {$attemptsLeft} attempt" . ($attemptsLeft === 1 ? '' : 's') . " remaining.",
            'attempts_left' => $attemptsLeft
        ]);
        exit;
    }
}
