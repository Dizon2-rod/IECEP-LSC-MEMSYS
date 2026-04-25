<?php
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { 
    http_response_code(204); 
    exit; 
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/EmailService.php';
require_once __DIR__ . '/../lib/supabase.php';

use App\Lib\EmailService;
use App\Lib\Supabase;

$emailService = new EmailService();
$supabase = new Supabase();

function sendVerificationCode($email) {
    global $emailService, $supabase;

    try {
        // Generate 6-digit code
        $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store in session
        $_SESSION['verification_code'] = $code;
        $_SESSION['verification_email'] = $email;
        $_SESSION['code_sent_time'] = time();

        error_log("Attempting to send verification code to: " . $email);

        $result = $emailService->sendVerificationCode($email, $code);

        if ($result) {
            error_log("Email verification sent successfully to: " . $email);
        } else {
            error_log("Email verification failed to send to: " . $email);
        }

        return $result;
    } catch (Exception $e) {
        error_log("Email verification exception: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

function verifyCode($email, $code) {
    if(isset($_SESSION['verification_code']) && 
       isset($_SESSION['verification_email']) &&
       time() - $_SESSION['code_sent_time'] < 600) { // 10 minutes expiry
        
        if($_SESSION['verification_code'] === $code && $_SESSION['verification_email'] === $email) {
            unset($_SESSION['verification_code']);
            return true;
        }
    }
    return false;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'send':
            $email = filter_var($input['email'] ?? '', FILTER_SANITIZE_EMAIL);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'error' => 'Invalid email address']);
                exit;
            }

            if (sendVerificationCode($email)) {
                echo json_encode(['success' => true, 'message' => 'Verification code sent successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to send verification code']);
            }
            break;
            
        case 'verify':
            $email = filter_var($input['email'] ?? '', FILTER_SANITIZE_EMAIL);
            $code = $input['code'] ?? '';
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($code)) {
                echo json_encode(['success' => false, 'error' => 'Invalid email or code']);
                exit;
            }
            
            if (verifyCode($email, $code)) {
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
