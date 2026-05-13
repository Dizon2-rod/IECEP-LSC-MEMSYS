<?php
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

    // Check if user exists in auth.users (generic message to prevent user enumeration)
    $users = $supabase->select('users', ['email' => 'eq.' . $email]);
    $userExists = !empty($users) && is_array($users) && count($users) > 0;

    if ($userExists) {
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

        $result = $supabase->insert('password_resets', $resetData);

        if ($result) {
            // Send password reset email
            try {
                $emailService = new EmailService();
                $resetLink = BASE_URL . '/public/reset-password.php?token=' . urlencode($token);
                
                $emailBody = "
                    <p>You requested a password reset for your IECEP-LSC account.</p>
                    <p>Click the link below to reset your password. This link will expire in 1 hour.</p>
                    <div style='text-align:center;margin:30px 0'>
                        <a href='{$resetLink}' style='background:#0A2F6C;color:white;padding:12px 32px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block'>Reset Password</a>
                    </div>
                    <p style='color:#666;font-size:0.9rem'>Or copy and paste this link in your browser:<br><span style='word-break:break-all;color:#0A2F6C'>{$resetLink}</span></p>
                    <p style='color:#999;font-size:0.85rem;margin-top:30px'>If you did not request this, please ignore this email. Your password will not be changed.</p>
                ";

                $emailService->sendNotification($email, 'Password Reset Request', $emailBody);
            } catch (Exception $e) {
                error_log('Password reset email failed: ' . $e->getMessage());
                // Don't fail the API call, but log the error
            }
        }
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
