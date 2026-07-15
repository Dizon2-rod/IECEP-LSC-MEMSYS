<?php
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/supabase.php';
require_once __DIR__ . '/../../includes/lib/EmailService.php';

use App\Lib\Supabase;
use App\Lib\EmailService;

$supabase = new Supabase();
$emailService = new EmailService();
$action = $_GET['action'] ?? '';

if ($action === 'send-code') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit;
    }
    
    try {
        // Generate 6-digit code
        $code = sprintf("%06d", mt_rand(0, 999999));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        // Store in session for immediate verification
        session_start();
        $_SESSION['verification_code'] = $code;
        $_SESSION['verification_email'] = $email;
        $_SESSION['code_sent_time'] = time();
        
        // Try to store in Supabase (optional, for backup)
        try {
            $result = $supabase->insert('email_verifications', [
                'email' => $email,
                'code' => $code,
                'expires_at' => $expiresAt,
                'verified' => false
            ]);
        } catch (Exception $e) {
            error_log("Supabase insert failed: " . $e->getMessage());
            // Continue even if Supabase fails
        }
        
        // Send email
        $sent = $emailService->sendVerificationCode($email, $code);
        
        if ($sent) {
            echo json_encode(['success' => true, 'message' => 'Verification code sent successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send email. Please check SMTP configuration.']);
        }
        
    } catch (Exception $e) {
        error_log("Send code error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error occurred']);
    }
    exit;
}

if ($action === 'verify-code') {
    session_start();
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    $code = $input['code'] ?? '';
    
    if (empty($email) || empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Email and code are required']);
        exit;
    }
    
    try {
        // Check session-based verification
        if (isset($_SESSION['verification_code']) && 
            isset($_SESSION['verification_email']) &&
            time() - $_SESSION['code_sent_time'] < 600) { // 10 minutes expiry
            
            if ($_SESSION['verification_code'] === $code && $_SESSION['verification_email'] === $email) {
                // Clear session
                unset($_SESSION['verification_code']);
                unset($_SESSION['verification_email']);
                unset($_SESSION['code_sent_time']);
                
                echo json_encode(['success' => true, 'message' => 'Email verified successfully']);
                exit;
            }
        }
        
        // Fallback to Supabase check
        $result = $supabase->select('email_verifications', '*', 'email = eq.' . $email . ' AND verified = false AND expires_at > now()');
        
        if ($result && count($result) > 0) {
            $record = $result[0];
            if ($record['code'] === $code) {
                // Mark as verified
                $supabase->update('email_verifications', ['verified' => true], 'id = eq.' . $record['id']);
                echo json_encode(['success' => true, 'message' => 'Email verified successfully']);
                exit;
            }
        }
        
        echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code']);
        
    } catch (Exception $e) {
        error_log("Verify code error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error occurred']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
