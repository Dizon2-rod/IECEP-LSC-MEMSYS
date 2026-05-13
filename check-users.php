<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/src/lib/SupabaseClient.php';

use App\Lib\SupabaseClient;

$config = require __DIR__ . '/includes/supabase.php';
$supabase = new SupabaseClient($config['url'], $config['service_role_key']);

echo "=== CHECKING USERS IN SYSTEM ===\n\n";

// Check user_profiles
echo "USERS IN user_profiles TABLE:\n";
$userProfiles = $supabase->select('user_profiles', []);
if (!empty($userProfiles) && is_array($userProfiles)) {
    foreach (array_slice($userProfiles, 0, 5) as $profile) {
        echo "- {$profile['email']} (Role: {$profile['role']})\n";
    }
    echo "Total: " . count($userProfiles) . " users\n";
} else {
    echo "No users found\n";
}

echo "\n\nUSERS IN members TABLE:\n";
$members = $supabase->select('members', []);
if (!empty($members) && is_array($members)) {
    foreach (array_slice($members, 0, 5) as $member) {
        echo "- {$member['email']}\n";
    }
    echo "Total: " . count($members) . " members\n";
} else {
    echo "No members found\n";
}

echo "\n\nUSERS IN users TABLE:\n";
$users = $supabase->select('users', []);
if (!empty($users) && is_array($users)) {
    foreach (array_slice($users, 0, 5) as $user) {
        echo "- {$user['email']} (ID: {$user['id']})\n";
    }
    echo "Total: " . count($users) . " users\n";
} else {
    echo "No users found\n";
}
?>
