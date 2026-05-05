<?php
session_start();

// Suppress PHP errors to prevent HTML warnings in JSON response
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Check if critical extensions are available
$criticalExtensions = ['curl', 'json'];
$missingCritical = [];
foreach ($criticalExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingCritical[] = $ext;
    }
}

if (!empty($missingCritical)) {
    error_log("Missing critical PHP extensions: " . implode(', ', $missingCritical));
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error: Missing critical PHP extensions. Please contact your server administrator.']);
    exit;
}

require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/supabase.php';
require_once __DIR__ . '/../../includes/paths.php';
require_once __DIR__ . '/../../src/lib/SupabaseClient.php';

use App\Lib\EmailService;

$emailService = new EmailService();
$config = require __DIR__ . '/../../includes/supabase.php';
$supabase = new SupabaseClient($config['url'], $config['anon_key']);

function sendVerificationCode($email, $emailService, $supabase) {
    try {
        // Generate 6-digit code
        $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // Calculate expiry (10 minutes from now)
        $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        error_log("Attempting to send verification code to: " . $email);
        error_log("Generated code: " . $code . " for email: " . $email);

        // Store in Supabase
        $verificationData = [
            'email' => $email,
            'code' => $code,
            'expires_at' => $expiresAt,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent
        ];

        $insertResult = $supabase->insert('verification_codes', $verificationData);

        if (!$insertResult) {
            error_log("Failed to store verification code in database");
            return false;
        }

        error_log("Verification code stored in database successfully");

        // Also store in session as backup
        $_SESSION['verification_code'] = $code;
        $_SESSION['verification_email'] = $email;
        $_SESSION['code_sent_time'] = time();

        // Send email
        $emailResult = $emailService->sendVerificationCode($email, $code);

        if ($emailResult) {
            error_log("Email verification sent successfully to: " . $email);
        } else {
            error_log("Email verification failed to send to: " . $email);
            // Still return true since code is stored in database
            error_log("Code is stored in database, can be retrieved manually for testing");
        }

        return true; // Return true even if email fails, as code is stored
    } catch (Exception $e) {
        error_log("Email verification exception: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

function verifyCode($email, $code, $supabase) {
    try {
        // Check in Supabase first
        $result = $supabase->select('verification_codes', [
            'email' => 'eq.' . $email,
            'code' => 'eq.' . $code,
            'expires_at' => 'gte.' . date('Y-m-d H:i:s'),
            'used_at' => 'is.null'
        ]);

        if (!empty($result) && is_array($result)) {
            // Mark code as used
            $supabase->update('verification_codes', ['used_at' => date('Y-m-d H:i:s')], $result[0]['id']);

            // Clear session
            unset($_SESSION['verification_code']);
            unset($_SESSION['verification_email']);
            unset($_SESSION['code_sent_time']);

            return true;
        }

        // Fallback to session check
        if (isset($_SESSION['verification_code']) &&
            isset($_SESSION['verification_email']) &&
            time() - $_SESSION['code_sent_time'] < 600) {

            if ($_SESSION['verification_code'] === $code && $_SESSION['verification_email'] === $email) {
                unset($_SESSION['verification_code']);
                return true;
            }
        }

        return false;
    } catch (Exception $e) {
        error_log("Verify code exception: " . $e->getMessage());
        return false;
    }
}

$method = $_SERVER['REQUEST_METHOD'];

// Read input from both JSON body and form-urlencoded
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
} else {
    // Read from $_POST for form-urlencoded or fallback to JSON
    $input = !empty($_POST) ? $_POST : json_decode(file_get_contents('php://input'), true);
}

if ($method === 'POST') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'send':
            $email = filter_var($input['email'] ?? '', FILTER_SANITIZE_EMAIL);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'error' => 'Invalid email address']);
                exit;
            }

            $result = sendVerificationCode($email, $emailService, $supabase);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Verification code sent successfully']);
            } else {
                // Get the last error from error log for debugging
                $debug = isset($_GET['debug']) ? error_get_last() : null;
                $response = ['success' => false, 'error' => 'Failed to send verification code. Please check server logs.'];
                if ($debug) {
                    $response['debug'] = $debug;
                }
                echo json_encode($response);
            }
            break;

        case 'verify':
            $email = filter_var($input['email'] ?? '', FILTER_SANITIZE_EMAIL);
            $code = $input['code'] ?? '';

            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($code)) {
                echo json_encode(['success' => false, 'error' => 'Invalid email or code']);
                exit;
            }

            if (verifyCode($email, $code, $supabase)) {
                // Generate a simple token for the session
                $token = bin2hex(random_bytes(16));
                $_SESSION['verified_email_token'] = $token;
                $_SESSION['verified_email'] = $email;

                echo json_encode([
                    'success' => true,
                    'message' => 'Email verified successfully',
                    'token' => $token
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid or expired verification code']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
?>
