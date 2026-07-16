<?php
require_once __DIR__ . '/bootstrap.php';

$trackingCode = $_GET['code'] ?? '';

if (empty($trackingCode)) {
    http_response_code(400);
    exit;
}

require_once __DIR__ . '/../../includes/supabase.php';
require_once __DIR__ . '/../../includes/config.php';

try {
    $sb = new \App\Lib\Supabase(SUPABASE_URL, SUPABASE_ANON_KEY);
    
    // Update tracking record
    $result = $sb->from('email_tracking')
        ->eq('tracking_code', $trackingCode)
        ->update(['opened_at' => date('c')], true);
    
    // Return 1x1 transparent pixel
    header('Content-Type: image/gif');
    echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');
    
} catch (Exception $e) {
    // Still return pixel even if tracking fails
    header('Content-Type: image/gif');
    echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');
}
