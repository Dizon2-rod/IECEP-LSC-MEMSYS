<?php
/**
 * Email Configuration Test Script
 * Run this to verify email sending works
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/src/lib/EmailService.php';

use App\Lib\EmailService;

echo "=== EMAIL CONFIGURATION TEST ===\n\n";

echo "SMTP Configuration:\n";
echo "Host: " . SMTP_HOST . "\n";
echo "Port: " . SMTP_PORT . "\n";
echo "Username: " . SMTP_USERNAME . "\n";
echo "Password: " . (strlen(SMTP_PASSWORD) > 0 ? str_repeat('*', strlen(SMTP_PASSWORD)) : 'NOT SET') . "\n";
echo "Password Length: " . strlen(SMTP_PASSWORD) . "\n";
echo "From Name: " . SMTP_FROM_NAME . "\n";
echo "From Email: " . SMTP_FROM_EMAIL . "\n\n";

// Check password format
$password = SMTP_PASSWORD;
if (strlen($password) === 16 && preg_match('/^[a-z0-9]{16}$/', $password)) {
    echo "✓ Password appears to be a valid Gmail App Password\n\n";
} else {
    echo "✗ WARNING: Password does not appear to be a Gmail App Password\n";
    echo "  Gmail App Passwords are exactly 16 characters (lowercase letters and digits only)\n";
    echo "  Current format: " . strlen($password) . " chars\n\n";
}

echo "=== SENDING TEST EMAIL ===\n\n";

try {
    $emailService = new EmailService();
    
    // Test email to the configured sender (send to self)
    $testEmail = SMTP_USERNAME;
    
    echo "Sending test email to: $testEmail\n";
    
    $result = $emailService->sendTestEmail($testEmail);
    
    if ($result) {
        echo "\n✓ SUCCESS: Test email sent successfully!\n";
        echo "Check your inbox at: $testEmail\n";
    } else {
        echo "\n✗ FAILED: Could not send test email\n";
        echo "Check error logs for details\n";
    }
    
} catch (Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
