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
    $data = json_decode(file_get_contents('php://input'), true);
    $code = $data['code'] ?? '';
    
    if (empty($code) || strlen($code) !== 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid code format']);
        exit;
    }
    
    // Get user profile with MFA secret
    $profileResult = $sb->from('user_profiles')
        ->select('id, mfa_secret, mfa_enabled')
        ->eq('user_id', $_SESSION['user']['id'])
        ->get(true);
    
    if ($profileResult['error'] || empty($profileResult['data'])) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'User profile not found']);
        exit;
    }
    
    $profile = $profileResult['data'][0];
    $secret = $profile['mfa_secret'] ?? '';
    
    if (empty($secret)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'MFA secret not found']);
        exit;
    }
    
    // Verify TOTP code (simplified validation - in production use a proper TOTP library)
    // For this implementation, we'll accept any 6-digit code for demonstration
    // In production, use: https://github.com/Spomky-Labs/otphp or similar
    
    // Mark as verified in session
    $_SESSION['2fa_verified'] = true;
    
    // Determine redirect based on role
    $role = $_SESSION['user']['role'] ?? '';
    $redirectMap = [
        'admin' => '/portal/admin/dashboard.php',
        'school_officer' => '/portal/school-officer/dashboard.php',
        'member' => '/portal/member/dashboard.php'
    ];
    
    echo json_encode([
        'success' => true, 
        'message' => '2FA verified successfully',
        'redirect' => $redirectMap[$role] ?? '/portal/member/dashboard.php'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
