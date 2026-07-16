<?php
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../../includes/supabase.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware/auth.php';

use App\Lib\Supabase;
use App\Middleware\AuthMiddleware;

$sb = new Supabase();
$auth = new AuthMiddleware();

// Admin only access
$user = $auth->requireRole(['admin']);

try {
    // Get user profile
    $profileResult = $sb->from('user_profiles')
        ->select('id')
        ->eq('user_id', $_SESSION['user']['id'])
        ->get(true);
    
    if ($profileResult['error'] || empty($profileResult['data'])) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User profile not found']);
        exit;
    }
    
    $profile = $profileResult['data'][0];
    
    // Disable MFA
    $updateResult = $sb->from('user_profiles')
        ->eq('id', $profile['id'])
        ->update(['mfa_enabled' => false, 'mfa_secret' => null], true);
    
    if ($updateResult['error']) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to disable 2FA']);
        exit;
    }
    
    echo json_encode(['success' => true, 'message' => '2FA disabled successfully']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
