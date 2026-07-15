<?php
require_once __DIR__ . '/bootstrap.php';
// API: Reset Password
// POST /public/api/reset-password.php
// Expects: { "token": "...", "new_password": "..." }

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/paths.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../src/lib/SupabaseClient.php';

use App\Lib\SupabaseClient;

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['token']) || empty($input['token'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Token is required']);
    exit;
}

if (!isset($input['new_password']) || empty($input['new_password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password is required']);
    exit;
}

$token = trim($input['token']);
$newPassword = $input['new_password'];

// Validate password strength
$validationErrors = validatePassword($newPassword);
if (!empty($validationErrors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password does not meet requirements']);
    exit;
}

try {
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

    // Get reset record
    $resets = safeSelect($supabase, 'password_resets', ['token' => 'eq.' . $token]);

    if (empty($resets) || !is_array($resets) || count($resets) === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
        exit;
    }

    $reset = $resets[0];

    // Validate token
    if ($reset['used']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'This token has already been used']);
        exit;
    }

    if (strtotime($reset['expires_at']) < time()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Token has expired']);
        exit;
    }

    $email = $reset['email'];

    error_log("Attempting password reset for email: $email");

    // Get user across tables and prefer actual auth user record when available
    $userProfiles = safeSelect($supabase, 'user_profiles', ['email' => 'eq.' . $email]);
    $members = safeSelect($supabase, 'members', ['email' => 'eq.' . $email]);
    $legacyUsers = safeSelect($supabase, 'users', ['email' => 'eq.' . $email]);

    if (!empty($legacyUsers) && is_array($legacyUsers) && count($legacyUsers) > 0) {
        $user = $legacyUsers[0];
        $userSource = 'users';
    } elseif (!empty($userProfiles) && is_array($userProfiles) && count($userProfiles) > 0) {
        $user = $userProfiles[0];
        $userSource = 'user_profiles';
    } elseif (!empty($members) && is_array($members) && count($members) > 0) {
        $user = $members[0];
        $userSource = 'members';
    } else {
        error_log("User not found in any table for email: $email");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    $userId = $user['id'] ?? $user['user_id'] ?? null;
    
    if (empty($userId)) {
        error_log("User ID not found in record for email: $email");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unable to process password reset']);
        exit;
    }
    
    error_log("Found user in $userSource table with ID: $userId for email: $email");

    // Update user password
    // Try Supabase Auth first, then fallback to legacy local password update
    $updateSuccess = false;
    
    try {
        error_log("Attempting to update password for user ID: $userId");
        
        $updateResult = $supabase->authUpdatePassword($userId, $newPassword);
        $updateSuccess = true;
        
        error_log("Password update via Supabase Auth: SUCCESS");
    } catch (\Exception $authUpdateError) {
        $authErrorMessage = $authUpdateError->getMessage();
        error_log("Supabase Auth password update failed: " . $authErrorMessage);

        if (!empty($legacyUsers) && is_array($legacyUsers) && count($legacyUsers) > 0) {
            error_log("Attempting legacy local users password update as fallback");

            try {
                $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                $updateLocalResult = $supabase->update('users', ['password' => $hashedPassword], $userId);

                if ($updateLocalResult) {
                    $updateSuccess = true;
                    error_log("Password update in local users table: SUCCESS");
                } else {
                    error_log("Password update in local users table: FAILED");
                }
            } catch (\Exception $localUpdateError) {
                error_log("Local password update exception: " . $localUpdateError->getMessage());
            }
        } else {
            throw $authUpdateError;
        }
    }

    // Mark token as used
    try {
        $markUsedResult = $supabase->update('password_resets', ['used' => true], $reset['id']);
        error_log("Mark token as used: " . ($markUsedResult ? 'SUCCESS' : 'FAILED'));
    } catch (\Exception $e) {
        error_log("Failed to mark token as used: " . $e->getMessage());
    }

    if ($updateSuccess) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Password has been reset successfully. You can now login with your new password.'
        ]);
    } else {
        error_log("Password update failed for user: $userId");
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update password. Please try again.'
        ]);
    }

} catch (\Exception $e) {
    error_log('Password reset error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while resetting your password'
    ]);
}

/**
 * Validate password strength
 * Returns array of errors (empty if valid)
 */
function validatePassword(string $password): array
{
    $errors = [];

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }

    if (!preg_match('/\d/', $password)) {
        $errors[] = 'Password must contain at least one digit';
    }

    return $errors;
}
