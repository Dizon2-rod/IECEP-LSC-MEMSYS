<?php
require_once __DIR__ . '/bootstrap.php';
// API: Request Password Reset
// POST /public/api/request-password-reset.php
// Expects: { "email": "user@example.com" }

header('Content-Type: application/json');

// Prevent session blocking
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/paths.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../src/lib/SupabaseClient.php';
require_once __DIR__ . '/../../src/lib/EmailService.php';

use App\Lib\SupabaseClient;
use App\Lib\EmailService;

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['email']) || empty($input['email'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}

$email = filter_var($input['email'], FILTER_SANITIZE_EMAIL);

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

try {
    // Initialize Supabase client
    $config = require __DIR__ . '/../../includes/supabase.php';
    $supabase = new SupabaseClient($config['url'], $config['service_role_key']);

    function safeSelect($supabase, $table, $filters) {
        $result = $supabase->select($table, $filters);
        if (!is_array($result)) {
            return [];
        }
        if (isset($result['message']) && isset($result['details'])) {
            return [];
        }
        if (array_keys($result) !== range(0, count($result) - 1)) {
            return [];
        }
        return $result;
    }

    error_log("Password reset request for email: $email");

    // Check if user exists - try multiple tables
    // First check user_profiles (primary user table)
    $users = safeSelect($supabase, 'user_profiles', ['email' => 'eq.' . $email]);
    
    if (empty($users) || count($users) === 0) {
        // Fallback: check members table
        error_log("User not found in user_profiles, checking members table");
        $users = safeSelect($supabase, 'members', ['email' => 'eq.' . $email]);
    }
    
    if (empty($users) || count($users) === 0) {
        // Fallback: check the legacy users table
        error_log("User not found in members, checking legacy users table");
        $users = safeSelect($supabase, 'users', ['email' => 'eq.' . $email]);
    }
    
    $userExists = !empty($users) && is_array($users) && count($users) > 0;

    error_log("User exists check result: " . ($userExists ? 'YES' : 'NO'));

    if ($userExists) {
        error_log("User found, proceeding with password reset for: $email");
        
        // Generate cryptographically secure token (64 hex chars)
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Store reset token in password_resets table
        $resetData = [
            'email' => $email,
            'token' => $token,
            'expires_at' => $expiresAt,
            'used' => false
        ];

        error_log("Attempting to store reset token in database");
        $result = $supabase->insert('password_resets', $resetData);

        if ($result) {
            error_log("Reset token stored successfully for: $email");
            
            // Send password reset email
            try {
                error_log("Attempting to send password reset email to: $email");
                
                $emailService = new EmailService();
                $baseUrl = defined('APP_URL') ? APP_URL : (defined('BASE_URL') ? BASE_URL : '');
                $resetLink = rtrim($baseUrl, '/') . '/public/reset-password.php?token=' . urlencode($token);
                
                $emailBody = "
                    <p>You requested a password reset for your IECEP-LSC account.</p>
                    <p>Click the link below to reset your password. This link will expire in 1 hour.</p>
                    <div style='text-align:center;margin:30px 0'>
                        <a href='{$resetLink}' style='background:#0A2F6C;color:white;padding:12px 32px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block'>Reset Password</a>
                    </div>
                    <p style='color:#666;font-size:0.9rem'>Or copy and paste this link in your browser:<br><span style='word-break:break-all;color:#0A2F6C'>{$resetLink}</span></p>
                    <p style='color:#999;font-size:0.85rem;margin-top:30px'>If you did not request this, please ignore this email. Your password will not be changed.</p>
                ";

                $emailSent = $emailService->sendNotification($email, 'Password Reset Request', $emailBody);
                
                if ($emailSent) {
                    error_log("Password reset email SENT SUCCESSFULLY to: $email");
                } else {
                    error_log("Password reset email FAILED to send to: $email");
                    error_log("Password reset email diagnostics: " . $emailService->getErrorInfo());
                    if (method_exists($emailService, 'testGmailConnection')) {
                        error_log("Password reset email: running Gmail connection test...");
                        $connectionTest = $emailService->testGmailConnection();
                        error_log("Password reset Gmail connection test result: " . ($connectionTest ? 'SUCCESS' : 'FAILED'));
                    }
                }
            } catch (Exception $e) {
                error_log('Password reset email exception: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
            }
        } else {
            error_log("Failed to store reset token in database for: $email");
        }
    } else {
        error_log("User not found in any user table for: $email");
    }

    // Always return success to prevent user enumeration
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'If an account exists with this email, a password reset link has been sent.'
    ]);

} catch (Exception $e) {
    error_log('Password reset request error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again later.'
    ]);
}
