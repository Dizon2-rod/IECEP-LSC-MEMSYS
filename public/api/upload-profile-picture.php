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

// Member only access
$user = $auth->requireRole(['member']);

try {
    if (!isset($_FILES['profile_picture'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No file uploaded']);
        exit;
    }
    
    $file = $_FILES['profile_picture'];
    
    // Validate file
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    $maxSize = 2 * 1024 * 1024; // 2MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Only JPG and PNG allowed.']);
        exit;
    }
    
    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'File size exceeds 2MB limit.']);
        exit;
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Upload error: ' . $file['error']]);
        exit;
    }
    
    // Get member ID
    $memberResult = $sb->from('members')
        ->select('id')
        ->eq('user_id', $_SESSION['user']['id'])
        ->get(true);
    
    if ($memberResult['error'] || empty($memberResult['data'])) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Member not found']);
        exit;
    }
    
    $memberId = $memberResult['data'][0]['id'];
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = "member_{$memberId}_" . time() . ".{$extension}";
    
    // Upload to Supabase Storage
    $fileContent = file_get_contents($file['tmp_name']);
    $uploadResult = $sb->storage()->uploadBinary(
        'member-photos',
        $filename,
        $fileContent,
        $file['type']
    );
    
    if ($uploadResult['error']) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to upload file: ' . $uploadResult['message']]);
        exit;
    }
    
    // Get public URL
    $publicUrl = $sb->storage()->getPublicUrl('member-photos', $filename);
    
    // Update member record
    $updateResult = $sb->from('members')
        ->eq('id', $memberId)
        ->update(['picture_url' => $publicUrl], true);
    
    if ($updateResult['error']) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update member record']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'picture_url' => $publicUrl,
            'filename' => $filename
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
