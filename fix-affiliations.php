<?php
require_once __DIR__ . '/bootstrap.php';
/**
 * Web-based Affiliation Migration Tool
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/src/lib/SupabaseClient.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix Affiliation Accounts</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 1200px; margin: 0 auto; }
        h1 { color: #0A2F6C; }
        .ok { color: #10b981; }
        .error { color: #ef4444; }
        .warning { color: #f59e0b; }
        .info { color: #3b82f6; }
        .log { background: #f8fafc; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #3b82f6; }
        .success-log { border-left-color: #10b981; }
        .error-log { border-left-color: #ef4444; }
        .summary { background: #f8fafc; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #0A2F6C; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .btn:hover { background: #1e4a8a; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Fix Affiliation Accounts</h1>
        
<?php
try {
    $config = require __DIR__ . '/includes/supabase.php';
    $supabase = new SupabaseClient($config['url'], $config['service_role_key']);
    
    echo '<p class="info">Starting migration process...</p>';
    
    $affiliations = $supabase->select('pending_affiliations', ['status' => 'eq.approved']);
    
    if (empty($affiliations)) {
        echo '<p class="warning">No approved affiliations found.</p>';
        exit;
    }
    
    echo '<p>Found <strong>' . count($affiliations) . '</strong> approved affiliation(s).</p><hr>';
    
    $fixed = 0;
    $skipped = 0;
    $errors = 0;
    
    foreach ($affiliations as $affiliation) {
        $email = $affiliation['email'];
        $institutionName = $affiliation['institution_name'];
        
        echo '<div class="log">';
        echo '<strong>Processing:</strong> ' . htmlspecialchars($institutionName) . ' (' . htmlspecialchars($email) . ')<br>';
        
        $users = $supabase->select('users', ['email' => 'eq.' . $email]);
        
        if (empty($users)) {
            echo '<span class="warning">⚠️ No user found. Skipping.</span>';
            echo '</div>';
            $skipped++;
            continue;
        }
        
        $user = $users[0];
        $userId = $user['id'];
        
        echo 'User ID: <code>' . htmlspecialchars($userId) . '</code><br>';
        
        $profiles = $supabase->select('user_profiles', ['user_id' => 'eq.' . $userId]);
        
        if (!empty($profiles)) {
            echo '<span class="ok">✓ User profile already exists. Skipping.</span>';
            echo '</div>';
            $skipped++;
            continue;
        }
        
        echo 'Creating user_profiles record...<br>';
        
        $profileData = [
            'user_id'     => $userId,
            'role'        => 'school_officer',
            'full_name'   => $affiliation['contact_person'] ?? $institutionName,
            'school_name' => $institutionName,
            'contact_phone' => $affiliation['contact_phone'] ?? null,
            // 'address'     => $affiliation['address'] ?? null, // Removed until schema is updated
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s')
        ];
        
        $result = $supabase->insert('user_profiles', $profileData);
        
        if ($result) {
            echo '<span class="ok">✅ User profile created successfully!</span>';
            echo '</div>';
            $fixed++;
        } else {
            echo '<span class="error">❌ Failed to create user profile.</span>';
            echo '</div>';
            $errors++;
        }
    }
    
    echo '<hr>';
    echo '<div class="summary">';
    echo '<h2>Migration Complete</h2>';
    echo '<p>Fixed: <strong class="ok">' . $fixed . '</strong></p>';
    echo '<p>Skipped: <strong class="info">' . $skipped . '</strong></p>';
    echo '<p>Errors: <strong class="error">' . $errors . '</strong></p>';
    echo '<p>Total: <strong>' . count($affiliations) . '</strong></p>';
    echo '</div>';
    
    if ($fixed > 0) {
        echo '<div class="log success-log">';
        echo '<p class="ok"><strong>✅ Success!</strong></p>';
        echo '<p>Fixed ' . $fixed . ' account(s). These users can now login with their credentials.</p>';
        echo '</div>';
    }
    
} catch (Exception $e) {
    echo '<div class="log error-log">';
    echo '<p class="error"><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</div>';
}
?>
        <p>
            <a href="check-affiliations.php" class="btn">🔍 Check Status Again</a>
            <a href="index.php" class="btn">← Back to Home</a>
        </p>
    </div>
</body>
</html>
