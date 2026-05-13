<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/src/lib/SupabaseClient.php';

use App\Lib\SupabaseClient;

$config = require __DIR__ . '/includes/supabase.php';
$supabase = new SupabaseClient($config['url'], $config['service_role_key']);

echo "=== TESTING PASSWORD RESET API ===\n\n";

// Get the reset token
$resets = $supabase->select('password_resets', ['email' => 'eq.superadmin@iecep-lsc.test', 'used' => 'eq.false']);

if (!empty($resets) && is_array($resets)) {
    $reset = $resets[count($resets) - 1];
    $token = $reset['token'];
    $email = $reset['email'];
    
    echo "Token: $token\n";
    echo "Email: $email\n";
    echo "Reset ID: {$reset['id']}\n\n";
    
    // Check if user exists in user_profiles table
    echo "Checking user in user_profiles table...\n";
    $userProfiles = $supabase->select('user_profiles', ['email' => 'eq.' . $email]);
    var_dump($userProfiles);
    if (!empty($userProfiles) && is_array($userProfiles) && count($userProfiles) > 0) {
        echo "User found in user_profiles!\n";
        foreach ($userProfiles[0] as $key => $value) {
            echo "  $key: $value\n";
        }
    } else {
        echo "User NOT found in user_profiles\n";
    }

    // Check if user exists in members table
    echo "Checking user in members table...\n";
    $members = $supabase->select('members', ['email' => 'eq.' . $email]);
    if (!empty($members) && is_array($members) && count($members) > 0) {
        echo "User found in members! Details:\n";
        foreach ($members[0] as $key => $value) {
            echo "  $key: $value\n";
        }
    } else {
        echo "User NOT found in members\n";
    }
    
    echo "\n\nChecking user in users table...\n";
    $users = $supabase->select('users', ['email' => 'eq.' . $email]);
    if (!empty($users)) {
        echo "User found in users! Details:\n";
        foreach ($users[0] as $key => $value) {
            echo "  $key: $value\n";
        }
        
        $userId = $users[0]['id'];
        echo "\nAttempting to update password for user ID: $userId\n";
        
        try {
            $result = $supabase->authUpdatePassword($userId, 'TestPassword2026!');
            echo "Password update result:\n";
            var_dump($result);
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
    } else {
        echo "User NOT found in users table\n";
    }
} else {
    echo "No reset tokens found\n";
}
?>
