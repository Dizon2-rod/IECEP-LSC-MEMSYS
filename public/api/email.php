<?php
// Suppress PHP errors to prevent HTML warnings in JSON response
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
require_once __DIR__ . '/../../src/lib/EmailService.php';

use App\Lib\EmailService;
use App\Lib\SupabaseClient;

$emailService = new EmailService();
$config = require __DIR__ . '/../../includes/supabase.php';
$supabase = new SupabaseClient($config['url'], $config['anon_key']);

function sendVerificationCode($email, $emailService, $supabase) {
    try {
        // Generate 6-digit code
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

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

        try {
            $supabase->insert('verification_codes', $verificationData);
        } catch (\Throwable $e) {
            error_log("Verification code DB store notice: " . $e->getMessage());
        }

        // Also store in session as backup
        $_SESSION['verification_code'] = $code;
        $_SESSION['verification_email'] = $email;
        $_SESSION['code_sent_time'] = time();

        // Send email
        $emailResult = $emailService->sendVerificationCode($email, $code);

        if ($emailResult) {
            error_log("Email verification sent successfully to: " . $email);
        } else {
            error_log("Email verification failed to send to: " . $email . " Error: " . $emailService->getLastError());
        }

        return [
            'sent' => $emailResult,
            'code' => $code,
            'error' => $emailService->getLastError()
        ];
    } catch (\Throwable $e) {
        error_log("Email verification exception: " . $e->getMessage());
        return [
            'sent' => false,
            'code' => null,
            'error' => $e->getMessage()
        ];
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
$rawBody = file_get_contents('php://input');
$jsonInput = json_decode($rawBody, true);
$input = is_array($jsonInput) ? array_merge($_POST, $jsonInput) : $_POST;
if (empty($input['email']) && !empty($_GET['email'])) {
    $input['email'] = $_GET['email'];
}
if (empty($input['code']) && !empty($_GET['code'])) {
    $input['code'] = $_GET['code'];
}

if ($method === 'POST') {
    $action = $_GET['action'] ?? ($input['action'] ?? '');
    
    switch ($action) {
        case 'send':
            $email = filter_var($input['email'] ?? '', FILTER_SANITIZE_EMAIL);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'error' => 'Invalid email address']);
                exit;
            }

            // 1. Check if email already exists in user_profiles
            $userProfile = $supabase->select('user_profiles', ['email' => 'eq.' . $email]);
            if (is_array($userProfile) && isset($userProfile[0]) && is_array($userProfile[0])) {
                $rawRole = $userProfile[0]['role'] ?? 'user';
                $roleMap = [
                    'admin'           => 'Administrator (Admin)',
                    'super_admin'     => 'Super Administrator',
                    'school_officer'  => 'School Officer',
                    'officer'         => 'School Officer',
                    'school_admin'    => 'School Officer',
                    'member'          => 'Student Member',
                    'student'         => 'Student Member',
                    'treasurer'       => 'Treasurer',
                    'auditor'         => 'Auditor',
                    'board_member'    => 'Executive Board Member',
                    'executive_board' => 'Executive Board Member'
                ];
                $formattedRole = $roleMap[strtolower($rawRole)] ?? ucwords(str_replace('_', ' ', $rawRole));
                
                echo json_encode([
                    'success' => false,
                    'error' => "This email is already registered in the system as a {$formattedRole}. Affiliation applications cannot use an existing account email."
                ]);
                exit;
            }

            // 2. Check if email exists in members table
            $existingMember = $supabase->select('members', ['email' => 'eq.' . $email]);
            if (is_array($existingMember) && isset($existingMember[0]) && is_array($existingMember[0])) {
                echo json_encode([
                    'success' => false,
                    'error' => "This email is already registered in the system as a Student Member. Affiliation applications cannot use an existing member email."
                ]);
                exit;
            }

            // 3. Check if active application exists in pending_affiliations
            $existingAff = $supabase->select('pending_affiliations', ['email' => 'eq.' . $email]);
            if (is_array($existingAff) && isset($existingAff[0]) && is_array($existingAff[0])) {
                $status = $existingAff[0]['status'] ?? '';
                if (in_array($status, ['pending', 'under_review'])) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'This email is already associated with an active affiliation application (Status: Under Review). Please contact support.'
                    ]);
                    exit;
                }
            }

            $result = sendVerificationCode($email, $emailService, $supabase);
            if ($result['sent']) {
                echo json_encode(['success' => true, 'message' => 'Verification code sent successfully! Please check your inbox and spam folder.']);
            } else {
                $appEnv = defined('APP_ENV') ? APP_ENV : 'development';
                if ($appEnv === 'development' || !empty($_GET['debug'])) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Verification code generated (Test mode fallback: ' . $result['code'] . ')',
                        'code' => $result['code'],
                        'email_error' => $result['error']
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Failed to send verification code email. ' . ($result['error'] ? '(' . $result['error'] . ')' : 'Please check your connection or contact support.')
                    ]);
                }
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
