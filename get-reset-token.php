<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/src/lib/SupabaseClient.php';

use App\Lib\SupabaseClient;

$config = require __DIR__ . '/includes/supabase.php';
$supabase = new SupabaseClient($config['url'], $config['service_role_key']);

// Get the email from URL parameter or use default
$email = $_GET['email'] ?? 'superadmin@iecep-lsc.test';

// Get the latest reset token for the test email
$resets = $supabase->select('password_resets', ['email' => 'eq.' . $email]);

if (!empty($resets) && is_array($resets)) {
    $reset = $resets[count($resets) - 1]; // Get the most recent
    echo 'Token: ' . $reset['token'] . "\n";
    echo 'Email: ' . $reset['email'] . "\n";
    echo 'Expires: ' . $reset['expires_at'] . "\n";
    echo 'Used: ' . ($reset['used'] ? 'Yes' : 'No') . "\n";
} else {
    echo 'No reset tokens found for this email' . "\n";
}
?>
