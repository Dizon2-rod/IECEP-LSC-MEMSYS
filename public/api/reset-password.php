<?php
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

    // Get reset record
    $resets = $supabase->select('password_resets', ['token' => 'eq.' . $token]);

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

    // Get user from auth.users
    $users = $supabase->select('users', ['email' => 'eq.' . $email]);
    if (empty($users) || !is_array($users) || count($users) === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    $user = $users[0];
    $userId = $user['id'];

    // Hash the new password
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

    // Update user password in auth.users table
    $updateResult = $supabase->update('users', ['password' => $hashedPassword], ['id' => 'eq.' . $userId]);

    if ($updateResult) {
        // Mark token as used
        $supabase->update('password_resets', ['used' => true], ['token' => 'eq.' . $token]);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Password reset successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update password'
        ]);
    }

} catch (Exception $e) {
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
