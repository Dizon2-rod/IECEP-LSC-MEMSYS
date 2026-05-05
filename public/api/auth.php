<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

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

require_once __DIR__ . '/../../includes/supabase.php';
require_once __DIR__ . '/../../includes/middleware/auth.php';

use App\Lib\Supabase;
use App\Middleware\AuthMiddleware;

$sb = new Supabase();
$auth = new AuthMiddleware();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'login':
            if ($method !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }
            $data = json_decode(file_get_contents('php://input'), true);
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';

            if (empty($email) || empty($password)) {
                http_response_code(400);
                echo json_encode(['error' => 'Email and password required']);
                exit;
            }

            $result = $sb->auth()->signIn($email, $password);
            if ($result['error']) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid credentials', 'details' => $result['message']]);
                exit;
            }

            $jwt = $result['data']['access_token'] ?? '';
            $userId = $result['data']['user']['id'] ?? '';

            // Get profile
            $profileResult = $sb->from('user_profiles')
                ->select('*')
                ->eq('user_id', $userId)
                ->get(true, $jwt);

            $profile = null;
            if (!$profileResult['error'] && !empty($profileResult['data'])) {
                $profile = $profileResult['data'][0];
            }

            // Get member record
            $memberResult = $sb->from('members')
                ->select('*, institutions(id, name)')
                ->eq('user_id', $userId)
                ->get(true, $jwt);

            $member = null;
            if (!$memberResult['error'] && !empty($memberResult['data'])) {
                $member = $memberResult['data'][0];
            }

            echo json_encode([
                'success' => true,
                'access_token' => $jwt,
                'refresh_token' => $result['data']['refresh_token'] ?? '',
                'user' => [
                    'id' => $userId,
                    'email' => $email,
                    'profile' => $profile,
                    'member' => $member,
                ],
            ]);
            break;

        case 'change-password':
            if ($method !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }
            $user = $auth->requireAuth();
            $data = json_decode(file_get_contents('php://input'), true);
            $newPassword = $data['new_password'] ?? '';

            if (strlen($newPassword) < 8) {
                http_response_code(400);
                echo json_encode(['error' => 'Password must be at least 8 characters']);
                exit;
            }

            $result = $sb->auth()->updateUser($user['jwt'], [
                'password' => $newPassword,
            ]);

            if ($result['error']) {
                http_response_code(400);
                echo json_encode(['error' => $result['message']]);
                exit;
            }

            // Clear force_password_change flag
            $sb->from('user_profiles')
                ->eq('user_id', $user['user_id'])
                ->update(['force_password_change' => false], true);

            echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
            break;

        case 'context':
            if ($method !== 'GET') { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }
            $user = $auth->requireAuth();
            $profile = $auth->getUserProfile($user['user_id'], $user['jwt']);
            $member = $auth->getMemberRecord($user['user_id'], $user['jwt']);

            $role = $profile['role'] ?? 'member';
            $isOfficer = !in_array($role, ['member']);

            echo json_encode([
                'success' => true,
                'user_id' => $user['user_id'],
                'email' => $user['email'],
                'role' => $role,
                'full_name' => $profile['full_name'] ?? '',
                'force_password_change' => $profile['force_password_change'] ?? false,
                'member' => $member,
                'portals' => [
                    'member' => true,
                    'officer' => $isOfficer,
                ],
                'officer_type' => $isOfficer ? $role : null,
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
