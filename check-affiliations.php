<?php
require_once __DIR__ . '/bootstrap.php';
/**
 * Web-based Affiliation Status Checker
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/src/lib/SupabaseClient.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Affiliation Status Check</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 8px; max-width: 1200px; margin: 0 auto; }
        h1 { color: #0A2F6C; }
        .ok { color: #10b981; }
        .error { color: #ef4444; }
        .warning { color: #f59e0b; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #0A2F6C; color: white; }
        .summary { background: #f8fafc; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #0A2F6C; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .btn:hover { background: #1e4a8a; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Affiliation Approval Status Check</h1>
        
<?php
try {
    $config = require __DIR__ . '/includes/supabase.php';
    $supabase = new SupabaseClient($config['url'], $config['service_role_key']);
    
    $affiliations = $supabase->select('pending_affiliations', ['status' => 'eq.approved']);
    
    if (empty($affiliations)) {
        echo '<p class="warning">No approved affiliations found.</p>';
        exit;
    }
    
    echo '<p>Found <strong>' . count($affiliations) . '</strong> approved affiliation(s).</p>';
    
    $issues = [];
    $ok = [];
    
    foreach ($affiliations as $affiliation) {
        $email = $affiliation['email'];
        $institutionName = $affiliation['institution_name'];
        
        $users = $supabase->select('users', ['email' => 'eq.' . $email]);
        
        if (empty($users)) {
            $issues[] = [
                'email' => $email,
                'institution' => $institutionName,
                'issue' => 'No user record found',
                'user_id' => null
            ];
            continue;
        }
        
        $userId = $users[0]['id'];
        $profiles = $supabase->select('user_profiles', ['user_id' => 'eq.' . $userId]);
        
        if (empty($profiles)) {
            $issues[] = [
                'email' => $email,
                'institution' => $institutionName,
                'user_id' => $userId,
                'issue' => 'Missing user_profiles record'
            ];
        } else {
            $ok[] = [
                'email' => $email,
                'institution' => $institutionName,
                'user_id' => $userId,
                'role' => $profiles[0]['role'] ?? 'N/A'
            ];
        }
    }
    
    if (!empty($ok)) {
        echo '<h2 class="ok">✅ Working Accounts (' . count($ok) . ')</h2>';
        echo '<table>';
        echo '<tr><th>Institution</th><th>Email</th><th>User ID</th><th>Role</th><th>Status</th></tr>';
        foreach ($ok as $item) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($item['institution']) . '</td>';
            echo '<td>' . htmlspecialchars($item['email']) . '</td>';
            echo '<td><small>' . htmlspecialchars($item['user_id']) . '</small></td>';
            echo '<td>' . htmlspecialchars($item['role']) . '</td>';
            echo '<td class="ok">Can login</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    
    if (!empty($issues)) {
        echo '<h2 class="error">❌ Accounts with Issues (' . count($issues) . ')</h2>';
        echo '<table>';
        echo '<tr><th>Institution</th><th>Email</th><th>User ID</th><th>Issue</th></tr>';
        foreach ($issues as $item) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($item['institution']) . '</td>';
            echo '<td>' . htmlspecialchars($item['email']) . '</td>';
            echo '<td><small>' . htmlspecialchars($item['user_id'] ?? 'N/A') . '</small></td>';
            echo '<td class="error">' . htmlspecialchars($item['issue']) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        
        echo '<div class="summary">';
        echo '<p class="warning"><strong>⚠️ Action Required:</strong></p>';
        echo '<p>These accounts cannot login. Click the button below to fix them:</p>';
        echo '<a href="fix-affiliations.php" class="btn">🔧 Fix All Issues</a>';
        echo '</div>';
    } else {
        echo '<div class="summary">';
        echo '<p class="ok"><strong>✅ All approved affiliations have complete user records!</strong></p>';
        echo '</div>';
    }
    
    echo '<div class="summary">';
    echo '<h3>Summary</h3>';
    echo '<p>Total Approved: <strong>' . count($affiliations) . '</strong></p>';
    echo '<p>Working: <strong class="ok">' . count($ok) . '</strong></p>';
    echo '<p>Issues: <strong class="error">' . count($issues) . '</strong></p>';
    echo '</div>';
    
} catch (Exception $e) {
    echo '<p class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>
        <p><a href="index.php" class="btn">← Back to Home</a></p>
    </div>
</body>
</html>
