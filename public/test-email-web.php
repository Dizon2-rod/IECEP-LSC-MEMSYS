<?php
/**
 * Web Email Test - Access via browser
 * URL: http://localhost/IECEP-LSC-MEMSYS/public/test-email-web.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../src/lib/EmailService.php';

use App\Lib\EmailService;

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Email Test - IECEP-LSC</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #0B1D4A; }
        .success { color: #16a34a; background: #dcfce7; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { color: #dc2626; background: #fee2e2; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { color: #0369a1; background: #e0f2fe; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .config { background: #f8fafc; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #0B1D4A; }
        pre { background: #1e293b; color: #e2e8f0; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 IECEP-LSC Email Service Test</h1>
        
        <div class="info">
            <strong>Testing email functionality for affiliation approval workflow</strong>
        </div>

        <h2>SMTP Configuration</h2>
        <div class="config">
            <strong>Host:</strong> <?php echo SMTP_HOST; ?><br>
            <strong>Port:</strong> <?php echo SMTP_PORT; ?><br>
            <strong>Username:</strong> <?php echo SMTP_USERNAME; ?><br>
            <strong>Password:</strong> <?php echo str_repeat('*', strlen(SMTP_PASSWORD)); ?> (<?php echo strlen(SMTP_PASSWORD); ?> chars)<br>
            <strong>From Name:</strong> <?php echo SMTP_FROM_NAME; ?><br>
            <strong>From Email:</strong> <?php echo SMTP_FROM_EMAIL; ?>
        </div>

        <?php
        // Check OpenSSL
        if (extension_loaded('openssl')) {
            echo '<div class="success">✓ OpenSSL extension is loaded</div>';
        } else {
            echo '<div class="error">✗ OpenSSL extension is NOT loaded - Email will fail!</div>';
        }

        // Check password format
        $password = SMTP_PASSWORD;
        if (strlen($password) === 16 && preg_match('/^[a-z0-9]{16}$/', $password)) {
            echo '<div class="success">✓ Password appears to be a valid Gmail App Password</div>';
        } else {
            echo '<div class="error">✗ Password does not appear to be a Gmail App Password (should be 16 lowercase letters/digits)</div>';
        }
        ?>

        <h2>Sending Test Email</h2>
        <?php
        try {
            $emailService = new EmailService();
            $testEmail = SMTP_USERNAME; // Send to self
            
            echo "<p>Attempting to send test email to: <strong>$testEmail</strong></p>";
            
            $result = $emailService->sendTestEmail($testEmail);
            
            if ($result) {
                echo '<div class="success">';
                echo '<strong>✓ SUCCESS!</strong><br>';
                echo "Test email sent successfully to: $testEmail<br>";
                echo 'Check your inbox. If you received it, the approval workflow emails will work.';
                echo '</div>';
            } else {
                echo '<div class="error">';
                echo '<strong>✗ FAILED</strong><br>';
                echo 'Could not send test email. Check error logs below.';
                echo '</div>';
            }
            
        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<strong>✗ ERROR:</strong> ' . htmlspecialchars($e->getMessage());
            echo '</div>';
            echo '<h3>Stack Trace:</h3>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        }
        ?>

        <h2>PHP Error Log (Last 20 lines)</h2>
        <pre><?php
        $logFile = ini_get('error_log');
        if (empty($logFile)) {
            $logFile = 'C:/xampp/apache/logs/error.log';
        }
        
        if (file_exists($logFile)) {
            $lines = file($logFile);
            $lastLines = array_slice($lines, -20);
            echo htmlspecialchars(implode('', $lastLines));
        } else {
            echo "Log file not found: $logFile";
        }
        ?></pre>

        <h2>Next Steps</h2>
        <div class="info">
            <strong>If test succeeded:</strong> Try approving an affiliation application - email should be sent automatically.<br>
            <strong>If test failed:</strong> Check the error log above for details. Common issues:
            <ul>
                <li>OpenSSL not enabled in php.ini</li>
                <li>Gmail App Password incorrect or has spaces</li>
                <li>Gmail account has 2FA disabled (required for App Passwords)</li>
                <li>Firewall blocking port 587</li>
            </ul>
        </div>
    </div>
</body>
</html>
