<?php
namespace App\Lib;

require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private array $config;
    private string $lastError = '';

    public function __construct()
    {
        // Use constants directly from config.php (global namespace)
        // Use defined() and constant() functions to safely get global constants
        $this->config = [
            'app_env' => defined('APP_ENV') ? constant('APP_ENV') : 'production',
            'app_url' => defined('APP_URL') ? constant('APP_URL') : '',
            'email' => [
                'host' => defined('SMTP_HOST') ? constant('SMTP_HOST') : 'smtp.gmail.com',
                'port' => defined('SMTP_PORT') ? constant('SMTP_PORT') : 587,
                'username' => defined('SMTP_USERNAME') ? constant('SMTP_USERNAME') : '',
                'password' => defined('SMTP_PASSWORD') ? constant('SMTP_PASSWORD') : '',
                'from_email' => defined('SMTP_FROM_EMAIL') ? constant('SMTP_FROM_EMAIL') : '',
                'from_name' => defined('SMTP_FROM_NAME') ? constant('SMTP_FROM_NAME') : 'IECEP-LSC-MEMSYS'
            ]
        ];

        $this->lastError = '';
        error_log('EmailService initialized with: ' . $this->config['email']['username']);

        if (!extension_loaded('openssl')) {
            error_log('CRITICAL: PHP OpenSSL extension is not loaded. Gmail SMTP email delivery will fail without openssl enabled.');
            $this->lastError = 'Missing PHP OpenSSL extension';
        }
    }

    private function createMailer(array $options = []): PHPMailer
    {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $options['host'] ?? $this->config['email']['host'];
            $mail->Port = (int)($options['port'] ?? $this->config['email']['port']);
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = $options['secure'] ?? PHPMailer::ENCRYPTION_STARTTLS;
            $mail->SMTPAutoTLS = $options['auto_tls'] ?? true;
            $mail->AuthType = $options['auth_type'] ?? 'LOGIN';
            $mail->Username = $this->config['email']['username'];
            $mail->Password = $this->config['email']['password'];
            $fromEmail = $this->config['email']['from_email'] ?: $this->config['email']['username'];
            $fromName = $this->config['email']['from_name'];
            $mail->setFrom($fromEmail, $fromName);
            $mail->addReplyTo($fromEmail, $fromName);
            $mail->CharSet = 'UTF-8';
            $mail->isHTML(true);
            
            // Validate Gmail App Password format
            $password = $this->config['email']['password'];
            if (strlen($password) < 16 || !preg_match('/^[a-z0-9]{16}$/', $password)) {
                error_log("WARNING: Gmail password does not appear to be an App Password. App Passwords are 16 characters long and contain only lowercase letters and numbers.");
                error_log("Please generate a Gmail App Password from Google Account Settings > Security > 2-Step Verification > App Passwords");
            }
            
            // Gmail-specific connection settings for better compatibility
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            
            // Set SMTP timeout
            $mail->Timeout = 30;
            $mail->SMTPKeepAlive = false;
            
            // Disable SMTP debugging to prevent HTML output in JSON responses
            $mail->SMTPDebug = 0;
            $mail->Debugoutput = 'error_log';
            
            error_log("EmailService: SMTP configured with host={$mail->Host}, port={$mail->Port}, secure={$mail->SMTPSecure}, user={$this->config['email']['username']}");
            
            return $mail;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            error_log('EmailService createMailer error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function sendVerificationCode(string $to, string $code): bool
    {
        try {
            $mail = $this->createMailer();
            $mail->addAddress($to);
            $mail->Subject = 'IECEP-LSC Email Verification Code';
            $mail->Body = "
                <div style='font-family:Inter,sans-serif;max-width:480px;margin:0 auto;padding:24px'>
                    <h2 style='color:#0A2F6C'>Email Verification</h2>
                    <p>Your IECEP-LSC verification code is:</p>
                    <div style='background:#f8f9fa;padding:20px;border-radius:8px;text-align:center;margin:20px 0'>
                        <span style='font-size:32px;font-weight:bold;color:#0A2F6C;letter-spacing:4px'>{$code}</span>
                    </div>
                    <p style='color:#6c757d'>This code expires in 10 minutes.</p>
                    <p style='color:#dc3545;font-size:14px'>If you didn't request this code, please ignore this email.</p>
                </div>";
            $mail->AltBody = "Your IECEP-LSC verification code is: {$code}. It expires in 10 minutes.";

            $result = $mail->send();
            error_log("Email verification sent to $to: " . ($result ? 'SUCCESS' : 'FAILED'));
            return $result;
        } catch (Exception $e) {
            error_log("Email verification error: " . $e->getMessage());
            return false;
        }
    }

    public function sendSchoolAccountCredentials(string $to, string $institutionName, string $password, string $contactPerson = '', string $loginUrl = null): bool
    {
        try {
            error_log("Preparing to send school account credentials email to: $to with password: $password");
            
            // Validate Gmail App Password format
            $emailPassword = $this->config['email']['password'];
            if (strlen($emailPassword) !== 16 || !preg_match('/^[a-z0-9]{16}$/', $emailPassword)) {
                error_log("WARNING: SMTP_PASSWORD does not appear to be a Gmail App Password. Gmail will reject it.");
                error_log("Please generate a Gmail App Password from Google Account Settings > Security > 2-Step Verification > App Passwords");
            }
            
            $mail = $this->createMailer();
            $mail->addAddress($to);
            $loginUrl = $loginUrl ?: $this->config['app_url'] . '/login.php';
            $logoUrl = $this->config['app_url'] . '/public/assets/icons/iecep-logo.png';
            $mail->Subject = 'IECEP-LSC Affiliation Approved – Your Portal Account Details';
            
            $mail->Body = '
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;">
    <div style="background-color: #0B1D4A; padding: 20px; text-align: center;">
        <img src="' . $logoUrl . '" alt="IECEP-LSC Logo" style="width: 60px; height: auto;">
        <h1 style="color: #C49A00; margin: 10px 0 0;">IECEP-LSC MEMSYS</h1>
    </div>
    <div style="padding: 30px;">
        <h2 style="color: #0B1D4A;">Affiliation Approved!</h2>
        <p>Dear ' . htmlspecialchars($contactPerson ?: 'Representative') . ',</p>
        <p>Congratulations! Your affiliation application for <strong>' . htmlspecialchars($institutionName) . '</strong> has been approved. You can now access the IECEP-LSC Member Portal.</p>
        
        <div style="background-color: #f8f8f8; border: 1px solid #e0e0e0; border-radius: 6px; padding: 20px; margin: 20px 0;">
            <h3 style="color: #0B1D4A; margin-top: 0;">Your Account Details</h3>
            <p><strong>Email (Username):</strong> ' . htmlspecialchars($to) . '</p>
            <p><strong>Temporary Password:</strong> <span style="font-family: monospace; background: #fff; padding: 4px 8px; border-radius: 4px;">' . htmlspecialchars($password) . '</span></p>
            <p><a href="' . $loginUrl . '" style="display: inline-block; background-color: #C49A00; color: #0B1D4A; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: bold;">Login to Portal</a></p>
        </div>

        <p><strong>Important:</strong> For security reasons, you must change your password immediately after logging in.</p>
        
        <p>If you have any questions, please contact the Registration Committee.</p>
        <p>Sincerely,<br>IECEP-LSC Registration Committee</p>
    </div>
    <div style="background-color: #f0f0f0; padding: 15px; text-align: center; font-size: 12px; color: #666;">
        &copy; 2025 IECEP-LSC MEMSYS &ndash; All rights reserved<br>
        Institute of Electronics Engineers of the Philippines – Laguna State Chapter
    </div>
</div>';
            
            $mail->AltBody = "IECEP-LSC Affiliation Approved – Your Portal Account Details\n\nDear " . ($contactPerson ?: 'Representative') . ",\n\nCongratulations! Your affiliation application for $institutionName has been approved. You can now access the IECEP-LSC Member Portal.\n\nYOUR ACCOUNT DETAILS:\n\nEmail (Username): $to\nTemporary Password: $password\n\nLogin URL: $loginUrl\n\nIMPORTANT: For security reasons, you must change your password immediately after logging in.\n\nIf you have any questions, please contact the Registration Committee.\n\nSincerely,\nIECEP-LSC Registration Committee\n\n© 2025 IECEP-LSC MEMSYS";
            
            $result = $mail->send();
            error_log("School account credentials email send result to $to: " . ($result ? 'SUCCESS' : 'FAILED'));
            
            if (!$result) {
                error_log("PHPMailer Error Info: " . $mail->ErrorInfo);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Email error (school credentials): " . $e->getMessage());
            if (isset($mail)) {
                error_log("PHPMailer Error Info: " . $mail->ErrorInfo);
            }
            return false;
        }
    }

    public function sendExistingAccountLinked(string $to, string $institutionName, string $contactPerson = ''): bool
    {
        try {
            error_log("Preparing to send existing account linked email to: $to");
            
            // Validate Gmail App Password format
            $emailPassword = $this->config['email']['password'];
            if (strlen($emailPassword) !== 16 || !preg_match('/^[a-z0-9]{16}$/', $emailPassword)) {
                error_log("WARNING: SMTP_PASSWORD does not appear to be a Gmail App Password. Gmail will reject it.");
                error_log("Please generate a Gmail App Password from Google Account Settings > Security > 2-Step Verification > App Passwords");
            }
            
            $mail = $this->createMailer();
            $mail->addAddress($to);
            $loginUrl = $this->config['app_url'] . '/login.php';
            $logoUrl = $this->config['app_url'] . '/public/assets/icons/iecep-logo.png';
            $mail->Subject = 'IECEP-LSC Affiliation Approved – School Access Updated';
            
            $mail->Body = '
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;">
    <div style="background-color: #0B1D4A; padding: 20px; text-align: center;">
        <img src="' . $logoUrl . '" alt="IECEP-LSC Logo" style="width: 60px; height: auto;">
        <h1 style="color: #C49A00; margin: 10px 0 0;">IECEP-LSC MEMSYS</h1>
    </div>
    <div style="padding: 30px;">
        <h2 style="color: #0B1D4A;">Affiliation Approved!</h2>
        <p>Dear ' . htmlspecialchars($contactPerson ?: 'Representative') . ',</p>
        <p>Your affiliation application for <strong>' . htmlspecialchars($institutionName) . '</strong> has been approved.</p>
        
        <div style="background-color: #f8f8f8; border: 1px solid #e0e0e0; border-radius: 6px; padding: 20px; margin: 20px 0;">
            <h3 style="color: #0B1D4A; margin-top: 0;">Account Linked Successfully</h3>
            <p>Your existing IECEP-LSC account (<strong>' . htmlspecialchars($to) . '</strong>) has been linked to this school. You can now access school-specific features using your current login credentials.</p>
            <p><a href="' . $loginUrl . '" style="display: inline-block; background-color: #C49A00; color: #0B1D4A; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: bold;">Login to Portal</a></p>
        </div>
        
        <p>If you have any questions, please contact the Registration Committee.</p>
        <p>Sincerely,<br>IECEP-LSC Registration Committee</p>
    </div>
    <div style="background-color: #f0f0f0; padding: 15px; text-align: center; font-size: 12px; color: #666;">
        &copy; 2025 IECEP-LSC MEMSYS &ndash; All rights reserved<br>
        Institute of Electronics Engineers of the Philippines – Laguna State Chapter
    </div>
</div>';
            
            $mail->AltBody = "IECEP-LSC Affiliation Approved – School Access Updated\n\nDear " . ($contactPerson ?: 'Representative') . ",\n\nYour affiliation application for $institutionName has been approved.\n\nYour existing IECEP-LSC account ($to) has been linked to this school. You can now access school-specific features using your current login credentials.\n\nLogin URL: $loginUrl\n\nIf you have any questions, please contact the Registration Committee.\n\nSincerely,\nIECEP-LSC Registration Committee\n\n© 2025 IECEP-LSC MEMSYS";
            
            $result = $mail->send();
            error_log("Existing account linked email send result to $to: " . ($result ? 'SUCCESS' : 'FAILED'));
            
            if (!$result) {
                error_log("PHPMailer Error Info: " . $mail->ErrorInfo);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Existing account linked email error: " . $e->getMessage());
            return false;
        }
    }

    public function sendCredentials(string $to, string $email, string $password): bool
    {
        try {
            error_log("Preparing to send credentials email to: $to");
            
            $mail = $this->createMailer();
            $mail->addAddress($to);
            $loginUrl = $this->config['app_url'] . '/login.php';
            $mail->Subject = 'IECEP-LSC Membership Account Created – Login Credentials';
            
            $logoUrl = $this->config['app_url'] . '/public/assets/icons/iecep-logo.png';
            
            $mail->Body = "
                <div style='font-family:Inter,sans-serif;max-width:600px;margin:0 auto;padding:0;background:#ffffff'>
                    <!-- Header with Logo -->
                    <div style='background:#0B1D4A;padding:24px;text-align:center;border-bottom:4px solid #C49A00'>
                        <img src='{$logoUrl}' alt='IECEP-LSC Logo' style='height:60px;margin-bottom:8px' onerror=\"this.style.display='none'; this.nextElementSibling.style.display='block';\">
                        <div style='color:#C49A00;font-size:18px;font-weight:bold;display:none'>IECEP-LSC</div>
                        <h1 style='color:#ffffff;margin:0;font-size:24px;font-weight:600'>Your Account is Ready!</h1>
                    </div>
                    
                    <!-- Main Content -->
                    <div style='padding:32px 24px'>
                        <div style='text-align:center;margin-bottom:32px'>
                            <div style='display:inline-flex;align-items:center;justify-content:center;width:80px;height:80px;background:#10b981;border-radius:50%;margin-bottom:16px'>
                                <i class='fas fa-user-check' style='color:#ffffff;font-size:32px'></i>
                            </div>
                            <h2 style='color:#0B1D4A;margin:0 0 8px 0;font-size:28px;font-weight:600'>Welcome to IECEP-LSC!</h2>
                            <p style='color:#64748b;margin:0;font-size:16px'>Your membership account has been successfully created</p>
                        </div>
                        
                        <!-- Login Credentials Card -->
                        <div style='background:#fef3c7;border:1px solid #f59e0b;border-radius:12px;padding:24px;margin:24px 0'>
                            <h3 style='color:#92400e;margin:0 0 16px 0;font-size:20px;font-weight:600;display:flex;align-items-center'>
                                <i class='fas fa-key' style='margin-right:12px;color:#f59e0b'></i>
                                Your Login Credentials
                            </h3>
                            <div style='background:#ffffff;border:1px solid #fbbf24;border-radius:8px;padding:20px'>
                                <div style='display:flex;align-items:center;margin-bottom:16px'>
                                    <i class='fas fa-envelope' style='color:#C49A00;margin-right:12px;width:20px'></i>
                                    <div>
                                        <p style='margin:0;color:#6b7280;font-size:14px'>Email Address</p>
                                        <p style='margin:4px 0 0 0;color:#0B1D4A;font-weight:600;font-size:16px'>{$email}</p>
                                    </div>
                                </div>
                                <div style='display:flex;align-items:center'>
                                    <i class='fas fa-lock' style='color:#C49A00;margin-right:12px;width:20px'></i>
                                    <div>
                                        <p style='margin:0;color:#6b7280;font-size:14px'>Password</p>
                                        <p style='margin:4px 0 0 0;color:#0B1D4A;font-weight:600;font-size:16px;font-family:monospace;background:#f8fafc;padding:8px 12px;border-radius:4px;display:inline-block'>{$password}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Security Notice -->
                        <div style='background:#fef2f2;border:1px solid #ef4444;border-radius:12px;padding:16px;margin:24px 0'>
                            <h4 style='color:#dc2626;margin:0 0 8px 0;font-size:16px;font-weight:600;display:flex;align-items-center'>
                                <i class='fas fa-exclamation-triangle' style='margin-right:8px;color:#ef4444'></i>
                                Important Security Notice
                            </h4>
                            <p style='color:#991b1b;margin:0;line-height:1.6'>You must change your password on first login for security purposes.</p>
                        </div>
                        
                        <!-- Login Button -->
                        <div style='text-align:center;margin:32px 0'>
                            <a href='{$loginUrl}' style='display:inline-block;padding:16px 32px;background:#0B1D4A;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;font-size:16px'>
                                <i class='fas fa-sign-in-alt' style='margin-right:8px'></i>
                                Login Now
                            </a>
                        </div>
                        
                        <!-- Help Section -->
                        <div style='background:#f1f5f9;border-radius:12px;padding:20px;margin:24px 0'>
                            <h3 style='color:#0B1D4A;margin:0 0 12px 0;font-size:18px;font-weight:600'>Need Help?</h3>
                            <p style='color:#475569;margin:0 0 12px 0'>If you have trouble logging in, please contact our support team:</p>
                            <div style='color:#475569;line-height:1.6'>
                                <p style='margin:4px 0'><i class='fas fa-envelope' style='color:#C49A00;margin-right:8px;width:16px'></i> Email: <a href='mailto:ieceplsc24@gmail.com' style='color:#0B1D4A;text-decoration:none;font-weight:500'>ieceplsc24@gmail.com</a></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div style='background:#0B1D4A;padding:24px;text-align:center;border-top:1px solid #1e3a8a'>
                        <p style='color:#94a3b8;margin:0 0 8px 0;font-size:12px'>© 2025 IECEP-LSC MEMSYS – All rights reserved</p>
                        <p style='color:#64748b;margin:0;font-size:11px'>Institute of Electronics Engineers of the Philippines – Laguna State Chapter</p>
                        <p style='color:#94a3b8;margin:8px 0 0 0;font-size:10px'>Membership Management System</p>
                    </div>
                </div>";
                
            $mail->AltBody = "IECEP-LSC Membership Account Created\n\nWelcome to IECEP-LSC!\n\nYour membership account has been successfully created. Here are your login credentials:\n\nEmail: {$email}\nPassword: {$password}\n\nIMPORTANT: You must change your password on first login for security purposes.\n\nLogin at: {$loginUrl}\n\nIf you have trouble logging in, please contact us at: ieceplsc24@gmail.com\n\n 2025 IECEP-LSC MEMSYS – All rights reserved";
            
            $result = $mail->send();
            error_log("Credentials email send result to $to: " . ($result ? 'SUCCESS' : 'FAILED'));
            
            // If PHPMailer fails, try fallback
            if (!$result) {
                error_log("PHPMailer failed, trying fallback email method");
                $fallbackResult = $this->sendFallbackEmail($to, $mail->Subject, $mail->Body);
                if ($fallbackResult) {
                    error_log("Fallback email succeeded");
                    return true;
                }
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Email error (credentials): " . $e->getMessage());
            error_log("Exception trace: " . $e->getTraceAsString());
            return false;
        }
    }

    public function sendAffiliationApproved(string $to, string $institutionName): bool
    {
        try {
            $mail = $this->createMailer();
            $mail->addAddress($to);
            $loginUrl = $this->config['app_url'] . '/login.php';
            $mail->Subject = 'IECEP-LSC Affiliation Approved';
            $mail->Body = "
                <div style='font-family:Inter,sans-serif;max-width:480px;margin:0 auto;padding:24px'>
                    <h2 style='color:#0A2F6C'>Affiliation Approved!</h2>
                    <p>Congratulations! <strong>{$institutionName}</strong> has been approved as an affiliated institution of IECEP-LSC.</p>
                    <p>Your school officer account has been created. Please check a separate email with your login credentials.</p>
                    <a href='{$loginUrl}' style='display:inline-block;padding:12px 24px;background:#F5A623;color:#fff;text-decoration:none;border-radius:8px;margin-top:12px'>Login Now</a>
                </div>";
            return $mail->send();
        } catch (Exception $e) {
            error_log("Email error (affiliation approved): " . $e->getMessage());
            return false;
        }
    }

    public function sendMemberRenewalConfirmation(string $to, string $memberName, string $membershipId, string $yearLevel = ''): bool
    {
        try {
            $mail = $this->createMailer();
            $mail->addAddress($to);
            $loginUrl = $this->config['app_url'] . '/login.php';
            $mail->Subject = 'IECEP-LSC Membership Renewal Confirmed';
            $mail->Body = "
                <div style='font-family:Inter,sans-serif;max-width:520px;margin:0 auto;padding:24px'>
                    <h2 style='color:#0A2F6C'>Membership Renewal Confirmed</h2>
                    <p>Dear " . htmlspecialchars($memberName) . ",</p>
                    <p>Your IECEP-LSC membership has been reviewed and renewed successfully.</p>
                    <div style='background:#f8fafc;border:1px solid #c7d2fe;padding:18px;border-radius:12px;margin:20px 0;'>
                        <p style='margin:0 0 8px;'><strong>Membership ID:</strong> " . htmlspecialchars($membershipId) . "</p>
                        " . (!empty($yearLevel) ? "<p style='margin:0;'><strong>Year Level:</strong> " . htmlspecialchars($yearLevel) . "</p>" : "") . "
                    </div>
                    <p>You can log in with your existing account credentials using the button below.</p>
                    <a href='" . $loginUrl . "' style='display:inline-block;padding:12px 24px;background:#F5A623;color:#0B1D4A;text-decoration:none;border-radius:8px;margin-top:12px;'>Go to Login</a>
                    <p style='margin-top:18px;color:#475569;'>If you did not request this renewal or if your account details are incorrect, please contact the Registration Committee immediately.</p>
                </div>";
            $mail->AltBody = "IECEP-LSC Membership Renewal Confirmed\n\nDear {$memberName},\n\nYour IECEP-LSC membership has been renewed successfully.\n\nMembership ID: {$membershipId}\n" . (!empty($yearLevel) ? "Year Level: {$yearLevel}\n" : "") . "\nLogin URL: {$loginUrl}\n\nIf you have questions, contact the Registration Committee.";
            return $mail->send();
        } catch (Exception $e) {
            error_log("Email error (membership renewal): " . $e->getMessage());
            return false;
        }
    }

    public function sendSchoolAffiliationLinked(string $to, string $institutionName): bool
    {
        try {
            error_log("Preparing to send school affiliation linked email to: $to");
            
            $mail = $this->createMailer();
            $mail->addAddress($to);
            $loginUrl = $this->config['app_url'] . '/login.php';
            $logoUrl = $this->config['app_url'] . '/public/assets/icons/iecep-logo.png';
            $mail->Subject = 'IECEP-LSC Affiliation Approved – School Access Updated';
            
            $mail->Body = '
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;">
    <div style="background-color: #0B1D4A; padding: 20px; text-align: center;">
        <img src="' . $logoUrl . '" alt="IECEP-LSC Logo" style="width: 60px; height: auto;">
        <h1 style="color: #C49A00; margin: 10px 0 0;">IECEP-LSC MEMSYS</h1>
    </div>
    <div style="padding: 30px;">
        <h2 style="color: #0B1D4A;">Affiliation Approved!</h2>
        <p>Dear ' . htmlspecialchars($institutionName) . ' Representative,</p>
        <p>Congratulations! Your affiliation application for <strong>' . htmlspecialchars($institutionName) . '</strong> has been approved.</p>
        
        <div style="background-color: #dbeafe; border: 1px solid #3b82f6; border-radius: 6px; padding: 20px; margin: 20px 0;">
            <h3 style="color: #1e40af; margin-top: 0;">Account Linked</h3>
            <p>Your existing IECEP-LSC account (<strong>' . htmlspecialchars($to) . '</strong>) has been linked to this school. You can now access school-specific features using your current login credentials.</p>
            <p style="margin: 20px 0;"><a href="' . $loginUrl . '" style="display: inline-block; background-color: #C49A00; color: #0B1D4A; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: bold;">Login to Portal</a></p>
        </div>
        
        <p>If you have any questions, please contact the Registration Committee at <a href="mailto:ieceplsc24@gmail.com">ieceplsc24@gmail.com</a>.</p>
        <p>Sincerely,<br><strong>IECEP-LSC Registration Committee</strong><br>Institute of Electronics Engineers of the Philippines – Laguna State Chapter</p>
    </div>
    <div style="background-color: #f0f0f0; padding: 15px; text-align: center; font-size: 12px; color: #666;">
        &copy; 2025 IECEP-LSC MEMSYS &ndash; All rights reserved<br>
        Institute of Electronics Engineers of the Philippines – Laguna State Chapter
    </div>
</div>';
                
            $mail->AltBody = "IECEP-LSC Affiliation Approved – School Access Updated\n\nDear $institutionName Representative,\n\nCongratulations! Your affiliation application has been approved.\n\nYour existing IECEP-LSC account ($to) has been linked to this school. You can now access school-specific features using your current login credentials.\n\nLogin URL: $loginUrl\n\nIf you have questions, contact us at: ieceplsc24@gmail.com\n\nSincerely,\nIECEP-LSC Registration Committee\n\n© 2025 IECEP-LSC MEMSYS";
            
            $result = $mail->send();
            error_log("School affiliation linked email send result to $to: " . ($result ? 'SUCCESS' : 'FAILED'));
            
            if (!$result) {
                error_log("PHPMailer Error Info: " . $mail->ErrorInfo);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Email error (school affiliation linked): " . $e->getMessage());
            return false;
        }
    }

    public function sendAffiliationRejected(string $to, string $institutionName, string $reason): bool
    {
        try {
            $mail = $this->createMailer();
            $mail->addAddress($to);
            $mail->Subject = 'IECEP-LSC Affiliation Application Update';
            $mail->Body = "
                <div style='font-family:Inter,sans-serif;max-width:480px;margin:0 auto;padding:24px'>
                    <h2 style='color:#0A2F6C'>Affiliation Update</h2>
                    <p>We regret to inform you that the affiliation application for <strong>{$institutionName}</strong> was not approved.</p>
                    <p><strong>Reason:</strong> {$reason}</p>
                    <p>You may reapply after addressing the concerns raised.</p>
                </div>";
            return $mail->send();
        } catch (Exception $e) {
            error_log("Email error (affiliation rejected): " . $e->getMessage());
            return false;
        }
    }

    public function sendAffiliationResubmitted(string $applicantEmail, string $institutionName, string $applicationId): bool
    {
        try {
            $mail = $this->createMailer();
            $adminEmail = 'ieceplsc24@gmail.com'; // Registration committee email
            $mail->addAddress($adminEmail);
            $mail->Subject = 'IECEP-LSC: Affiliation Application Resubmitted';
            $reviewUrl = $this->config['app_url'] . '/public/portal/admin/affiliations.php';
            $mail->Body = "
                <div style='font-family:Inter,sans-serif;max-width:600px;margin:0 auto;padding:24px'>
                    <h2 style='color:#0A2F6C'>Affiliation Application Resubmitted</h2>
                    <p>The affiliation application for <strong>{$institutionName}</strong> has been resubmitted with updated documents.</p>
                    <div style='background:#d1ecf1;border-left:4px solid #0c5460;padding:16px;border-radius:8px;margin:20px 0'>
                        <h4 style='color:#0c5460;margin-top:0'>Applicant Details</h4>
                        <p style='margin:8px 0'><strong>Email:</strong> {$applicantEmail}</p>
                        <p style='margin:8px 0'><strong>Application ID:</strong> {$applicationId}</p>
                    </div>
                    <p>Please review the updated application in the admin portal.</p>
                    <a href='{$reviewUrl}' style='display:inline-block;padding:12px 24px;background:#0A2F6C;color:#fff;text-decoration:none;border-radius:8px;margin:12px 0'>Review Application</a>
                    <hr style='border:none;border-top:1px solid #dee2e6;margin:20px 0'>
                    <p style='font-size:12px;color:#6c757d'>This is an automated notification from IECEP-LSC MEMSYS.</p>
                </div>";
            return $mail->send();
        } catch (Exception $e) {
            error_log("Email error (affiliation resubmitted): " . $e->getMessage());
            return false;
        }
    }

    public function sendChangesRequested(string $to, string $institutionName, string $instructions, array $applicationData = []): bool
    {
        try {
            $mail = $this->createMailer();
            $mail->addAddress($to);
            $mail->Subject = 'IECEP-LSC Affiliation Application - Changes Required';

            // Build submitted application details section
            $appDetailsHtml = '';
            if (!empty($applicationData)) {
                $fields = [
                    'institution_name' => 'Institution Name',
                    'address' => 'Institution Address',
                    'contact_person' => 'Contact Person',
                    'contact_position' => 'Contact Position',
                    'contact_phone' => 'Contact Phone',
                    'email' => 'Contact Email'
                ];
                $appDetailsHtml = "
                    <div style='background:#f8f9fa;border:1px solid #dee2e6;border-radius:8px;padding:16px;margin:20px 0'>
                        <h3 style='color:#0A2F6C;margin-top:0'>Your Submitted Application</h3>
                        <table style='width:100%;border-collapse:collapse'>";
                foreach ($fields as $key => $label) {
                    if (!empty($applicationData[$key])) {
                        $appDetailsHtml .= "
                            <tr>
                                <td style='padding:8px;font-weight:bold;color:#0A2F6C;width:40%;border-bottom:1px solid #e9ecef'>{$label}</td>
                                <td style='padding:8px;border-bottom:1px solid #e9ecef'>" . htmlspecialchars($applicationData[$key]) . "</td>
                            </tr>";
                    }
                }

                // Show submitted documents
                $docs = [];
                if (!empty($applicationData['documents'])) {
                    $docs = json_decode($applicationData['documents'], true) ?: [];
                }
                if (!empty($docs)) {
                    $docLabels = [
                        'moa' => 'Memorandum of Agreement',
                        'accreditation' => 'Accreditation Certificate',
                        'cor' => 'Certificate of Registration',
                        'school_registration' => 'School Registration Document',
                        'id_picture' => 'ID Picture'
                    ];
                    $appDetailsHtml .= "
                            <tr><td colspan='2' style='padding:12px 8px 4px;font-weight:bold;color:#0A2F6C;border-bottom:none'>Submitted Documents</td></tr>";
                    foreach ($docs as $docKey => $docValue) {
                        if ($docKey === 'changes_instructions') continue;
                        $label = $docLabels[$docKey] ?? ucfirst(str_replace('_', ' ', $docKey));
                        $appDetailsHtml .= "
                            <tr>
                                <td style='padding:8px;font-weight:bold;color:#0A2F6C;width:40%;border-bottom:1px solid #e9ecef'>{$label}</td>
                                <td style='padding:8px;border-bottom:1px solid #e9ecef;color:#198754'>&#10003; Submitted</td>
                            </tr>";
                    }
                }

                $appDetailsHtml .= "
                        </table>
                    </div>";
            }

            $applyUrl = $this->config['app_url'] . '/apply.php?resubmit=' . ($applicationData['id'] ?? '');

            $mail->Body = "
                <div style='font-family:Inter,sans-serif;max-width:600px;margin:0 auto;padding:24px'>
                    <h2 style='color:#0A2F6C'>Affiliation Application Update</h2>
                    <p>Thank you for submitting your affiliation application for <strong>{$institutionName}</strong>.</p>
                    
                    <div style='background:#fef3c7;border-left:4px solid #f59e0b;padding:16px;border-radius:8px;margin:20px 0'>
                        <h3 style='color:#92400e;margin-top:0'>Changes Required</h3>
                        <p style='color:#78350f;white-space:pre-wrap;line-height:1.6'>{$instructions}</p>
                    </div>
                    
                    {$appDetailsHtml}
                    
                    <p>Please make the necessary adjustments and resubmit your application with the updated documents.</p>
                    
                    <a href='{$applyUrl}' style='display:inline-block;padding:12px 24px;background:#0A2F6C;color:#fff;text-decoration:none;border-radius:8px;margin:12px 0'>Resubmit Application</a>
                    
                    <p style='color:#6c757d;font-size:14px'>If you have any questions about the requested changes, please contact the Registration Committee.</p>
                    
                    <hr style='border:none;border-top:1px solid #dee2e6;margin:20px 0'>
                    <p style='font-size:12px;color:#6c757d;text-align:center'>Best regards,<br>IECEP-LSC Registration Committee</p>
                </div>";
            return $mail->send();
        } catch (Exception $e) {
            error_log("Email error (changes requested): " . $e->getMessage());
            return false;
        }
    }

    public function sendAnnouncement(string $to, string $title, string $content): bool
    {
        try {
            $mail = $this->createMailer();
            $mail->addAddress($to);
            $mail->Subject = "IECEP-LSC: $title";
            $mail->Body = "
                <div style='font-family:Inter,sans-serif;max-width:480px;margin:0 auto;padding:24px'>
                    <h2 style='color:#0A2F6C'>{$title}</h2>
                    <div>{$content}</div>
                    <hr style='border:none;border-top:1px solid #dee2e6;margin:16px 0'>
                    <p style='font-size:12px;color:#6c757d'>This is an official announcement from IECEP-LSC.</p>
                </div>";
            return $mail->send();
        } catch (Exception $e) {
            error_log("Email error (announcement): " . $e->getMessage());
            return false;
        }
    }

    public function sendNotification(string $to, string $subject, string $body): bool
    {
        try {
            $mail = $this->createMailer();
            $mail->addAddress($to);
            $mail->Subject = "IECEP-LSC: $subject";
            $mail->Body = "
                <div style='font-family:Inter,sans-serif;max-width:480px;margin:0 auto;padding:24px'>
                    <h2 style='color:#0A2F6C'>{$subject}</h2>
                    <div>{$body}</div>
                </div>";
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

            $result = $mail->send();
            if (!$result) {
                $this->lastError = $mail->ErrorInfo;
                error_log("Email error (notification): " . $mail->ErrorInfo);
                if ($this->config['email']['host'] === 'smtp.gmail.com' && (int)$this->config['email']['port'] === 587) {
                    error_log("EmailService: retrying notification using implicit SSL on port 465");
                    return $this->sendNotificationViaAlternateTransport($to, $subject, $body);
                }
            }
            return $result;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            error_log("Email error (notification): " . $e->getMessage());
            return false;
        }
    }

    private function sendNotificationViaAlternateTransport(string $to, string $subject, string $body): bool
    {
        try {
            $mail = $this->createMailer([
                'host' => 'smtp.gmail.com',
                'port' => 465,
                'secure' => PHPMailer::ENCRYPTION_SMTPS,
                'auto_tls' => false,
                'auth_type' => 'LOGIN'
            ]);
            $mail->addAddress($to);
            $mail->Subject = "IECEP-LSC: $subject";
            $mail->Body = "
                <div style='font-family:Inter,sans-serif;max-width:480px;margin:0 auto;padding:24px'>
                    <h2 style='color:#0A2F6C'>{$subject}</h2>
                    <div>{$body}</div>
                </div>";
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

            $result = $mail->send();
            if (!$result) {
                $this->lastError = $mail->ErrorInfo;
                error_log("Email error (notification fallback 465): " . $mail->ErrorInfo);
            } else {
                error_log("EmailService: notification succeeded on fallback transport 465");
            }
            return $result;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            error_log("Email error (notification fallback 465): " . $e->getMessage());
            return false;
        }
    }

    public function sendAffiliationConfirmation(string $to, string $institutionName): bool
    {
        try {
            $mail = $this->createMailer();
            $mail->addAddress($to);
            $mail->Subject = 'IECEP-LSC Affiliation Application Received – Confirmation';
            
            $logoUrl = $this->config['app_url'] . '/public/assets/icons/iecep-logo.png';
            
            $mail->Body = "
                <div style='font-family:Inter,sans-serif;max-width:600px;margin:0 auto;padding:0;background:#ffffff'>
                    <!-- Header with Logo -->
                    <div style='background:#0B1D4A;padding:24px;text-align:center;border-bottom:4px solid #C49A00'>
                        <img src='{$logoUrl}' alt='IECEP-LSC Logo' style='height:60px;margin-bottom:8px' onerror=\"this.style.display='none'; this.nextElementSibling.style.display='block';\">
                        <div style='color:#C49A00;font-size:18px;font-weight:bold;display:none'>IECEP-LSC</div>
                        <h1 style='color:#ffffff;margin:0;font-size:24px;font-weight:600'>Affiliation Application Received</h1>
                    </div>
                    
                    <!-- Main Content -->
                    <div style='padding:32px 24px'>
                        <div style='text-align:center;margin-bottom:32px'>
                            <div style='display:inline-flex;align-items:center;justify-content:center;width:80px;height:80px;background:#10b981;border-radius:50%;margin-bottom:16px'>
                                <i class='fas fa-check' style='color:#ffffff;font-size:32px'></i>
                            </div>
                            <h2 style='color:#0B1D4A;margin:0 0 8px 0;font-size:28px;font-weight:600'>Thank You!</h2>
                            <p style='color:#64748b;margin:0;font-size:16px'>Your affiliation application has been successfully submitted</p>
                        </div>
                        
                        <!-- Institution Info Card -->
                        <div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:20px;margin:24px 0'>
                            <h3 style='color:#0B1D4A;margin:0 0 12px 0;font-size:18px;font-weight:600'>Application Details</h3>
                            <div style='display:flex;align-items:center;color:#475569'>
                                <i class='fas fa-university' style='color:#C49A00;margin-right:12px;font-size:20px'></i>
                                <div>
                                    <p style='margin:0;font-weight:600'>{$institutionName}</p>
                                    <p style='margin:4px 0 0 0;font-size:14px;color:#64748b'>Affiliation Application</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Next Steps -->
                        <div style='background:#fef3c7;border:1px solid #f59e0b;border-radius:12px;padding:20px;margin:24px 0'>
                            <h3 style='color:#92400e;margin:0 0 16px 0;font-size:18px;font-weight:600;display:flex;align-items:center'>
                                <i class='fas fa-clock' style='margin-right:12px;color:#f59e0b'></i>
                                What Happens Next?
                            </h3>
                            <ol style='margin:0;padding-left:20px;color:#78350f;line-height:1.8'>
                                <li style='margin-bottom:8px'><strong>Review Process:</strong> Our Registration Committee will thoroughly review your application and all submitted documents</li>
                                <li style='margin-bottom:8px'><strong>Timeline:</strong> You will receive a decision within 3-5 business days</li>
                                <li style='margin-bottom:8px'><strong>Approval:</strong> If approved, you'll receive further instructions for account setup and next steps</li>
                                <li><strong>Communication:</strong> All updates will be sent to this email address</li>
                            </ol>
                        </div>
                        
                        <!-- Contact Info -->
                        <div style='background:#f1f5f9;border-radius:12px;padding:20px;margin:24px 0'>
                            <h3 style='color:#0B1D4A;margin:0 0 12px 0;font-size:18px;font-weight:600'>Need Help?</h3>
                            <p style='color:#475569;margin:0 0 12px 0'>If you have any questions about your application, feel free to contact us:</p>
                            <div style='color:#475569;line-height:1.6'>
                                <p style='margin:4px 0'><i class='fas fa-envelope' style='color:#C49A00;margin-right:8px;width:16px'></i> Email: <a href='mailto:ieceplsc24@gmail.com' style='color:#0B1D4A;text-decoration:none;font-weight:500'>ieceplsc24@gmail.com</a></p>
                                <p style='margin:4px 0'><i class='fas fa-phone' style='color:#C49A00;margin-right:8px;width:16px'></i> Phone: Contact through email for inquiries</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div style='background:#0B1D4A;padding:24px;text-align:center;border-top:1px solid #1e3a8a'>
                        <p style='color:#94a3b8;margin:0 0 8px 0;font-size:12px'>© 2025 IECEP-LSC MEMSYS – All rights reserved</p>
                        <p style='color:#64748b;margin:0;font-size:11px'>Institute of Electronics Engineers of the Philippines – Laguna State Chapter</p>
                        <p style='color:#94a3b8;margin:8px 0 0 0;font-size:10px'>Membership Management System</p>
                    </div>
                </div>";
                
            $mail->AltBody = "IECEP-LSC Affiliation Application Received\n\nThank you for submitting your affiliation application for {$institutionName}.\n\nWhat happens next:\n1. Our Registration Committee will review your application\n2. You will receive a decision within 3-5 business days\n3. If approved, you'll receive further instructions for account setup\n\nIf you have questions, contact us at: ieceplsc24@gmail.com\n\n© 2025 IECEP-LSC MEMSYS – All rights reserved";
            
            return $mail->send();
        } catch (Exception $e) {
            error_log("Email error (affiliation confirmation): " . $e->getMessage());
            return false;
        }
    }

    public function sendContactForm(string $name, string $email, string $subject, string $message): bool
    {
        try {
            $mail = $this->createMailer();
            $mail->addAddress('ieceplsc24@gmail.com');
            $mail->addReplyTo($email, $name);
            $mail->Subject = "IECEP-LSC Contact Form: {$subject}";
            
            $mail->Body = "
                <div style='font-family:Inter,sans-serif;max-width:600px;margin:0 auto;padding:24px;background:#f8f9fa;border-radius:12px'>
                    <h2 style='color:#0A2F6C;margin-bottom:20px'>New Contact Form Submission</h2>
                    <table style='width:100%;border-collapse:collapse;margin:16px 0;background:white;border-radius:8px;overflow:hidden'>
                        <tr><td style='padding:8px;font-weight:bold;color:#0A2F6C;background:#e9ecef'>Name:</td><td style='padding:8px'>{$name}</td></tr>
                        <tr><td style='padding:8px;font-weight:bold;color:#0A2F6C;background:#e9ecef'>Email:</td><td style='padding:8px'>{$email}</td></tr>
                        <tr><td style='padding:8px;font-weight:bold;color:#0A2F6C;background:#e9ecef'>Subject:</td><td style='padding:8px'>{$subject}</td></tr>
                    </table>
                    <div style='background:white;padding:16px;border-radius:8px;margin-top:16px'>
                        <h4 style='color:#0A2F6C;margin-bottom:8px'>Message:</h4>
                        <p style='color:#343a40;line-height:1.6'>{$message}</p>
                    </div>
                    <hr style='border:none;border-top:1px solid #dee2e6;margin:20px 0'>
                    <p style='font-size:12px;color:#6c757d'>This message was sent from the IECEP-LSC contact form.</p>
                </div>";
            
            $mail->AltBody = "New Contact Form Submission\n\nName: {$name}\nEmail: {$email}\nSubject: {$subject}\n\nMessage: {$message}";
            
            return $mail->send();
        } catch (Exception $e) {
            error_log("Email error (contact form): " . $e->getMessage());
            return false;
        }
    }
    
    private function sendFallbackEmail(string $to, string $subject, string $body): bool
    {
        try {
            error_log("Attempting fallback email to: $to");
            $headers = [
                'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>',
                'Reply-To: ' . SMTP_FROM_EMAIL,
                'Content-Type: text/html; charset=UTF-8',
                'MIME-Version: 1.0'
            ];
            
            $result = mail($to, $subject, $body, implode("\r\n", $headers));
            error_log("Fallback email result: " . ($result ? 'SUCCESS' : 'FAILED'));
            return $result;
        } catch (Exception $e) {
            error_log("Fallback email error: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendTestEmail(string $to): bool
    {
        try {
            error_log("Sending test email to: $to");
            
            $mail = $this->createMailer();
            $mail->addAddress($to);
            $mail->Subject = 'IECEP-LSC Email Service Test';
            
            $mail->Body = "
                <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;background:#ffffff'>
                    <div style='background:#0B1D4A;padding:20px;text-align:center;border-bottom:4px solid #C49A00'>
                        <h1 style='color:#ffffff;margin:0;font-size:24px;font-weight:600'>IECEP-LSC MEMSYS</h1>
                        <p style='color:#C49A00;margin:8px 0 0 0;font-size:16px'>Email Service Test</p>
                    </div>
                    
                    <div style='padding:30px 20px'>
                        <h2 style='color:#0B1D4A;margin:0 0 16px 0;font-size:20px;font-weight:600'>Test Email</h2>
                        <p style='color:#475569;margin:0 0 16px 0;line-height:1.6'>
                            This is a test email to verify that the IECEP-LSC email service is working correctly.
                        </p>
                        <p style='color:#475569;margin:0 0 16px 0;line-height:1.6'>
                            If you received this email, it means the email service is functioning properly and can send
                            notifications for affiliation applications.
                        </p>
                        <div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin:20px 0'>
                            <h3 style='color:#0B1D4A;margin:0 0 8px 0;font-size:16px;font-weight:600'>Test Details:</h3>
                            <ul style='color:#475569;margin:0;padding-left:20px;line-height:1.6'>
                                <li>Sent to: $to</li>
                                <li>Sent at: " . date('Y-m-d H:i:s') . "</li>
                                <li>Service: IECEP-LSC Email Service</li>
                                <li>Status: Working Correctly</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div style='background:#0B1D4A;padding:20px;text-align:center;border-top:1px solid #1e3a8a'>
                        <p style='color:#94a3b8;margin:0 0 8px 0;font-size:12px'>© 2025 IECEP-LSC MEMSYS – All rights reserved</p>
                        <p style='color:#64748b;margin:0;font-size:11px'>Institute of Electronics Engineers of the Philippines – Laguna State Chapter</p>
                    </div>
                </div>";
            
            $mail->AltBody = "IECEP-LSC Email Service Test\n\nThis is a test email to verify that the IECEP-LSC email service is working correctly.\n\nIf you received this email, it means the email service is functioning properly and can send notifications for affiliation applications.\n\nTest Details:\n- Sent to: $to\n- Sent at: " . date('Y-m-d H:i:s') . "\n- Service: IECEP-LSC Email Service\n- Status: Working Correctly\n\n© 2025 IECEP-LSC MEMSYS – All rights reserved";
            
            $result = $mail->send();
            error_log("Test email send result to $to: " . ($result ? 'SUCCESS' : 'FAILED'));
            
            if (!$result) {
                error_log("PHPMailer Error Info: " . $mail->ErrorInfo);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Test email error: " . $e->getMessage());
            error_log("Exception trace: " . $e->getTraceAsString());
            return false;
        }
    }
    
    public function testGmailConnection(): bool
    {
        try {
            error_log("Testing Gmail SMTP connection...");
            
            // Validate Gmail App Password format
            $emailPassword = $this->config['email']['password'];
            if (strlen($emailPassword) !== 16 || !preg_match('/^[a-z0-9]{16}$/', $emailPassword)) {
                error_log("CRITICAL: Gmail App Password format is invalid. This will cause authentication to fail.");
                error_log("Current password length: " . strlen($emailPassword) . " (should be 16)");
                error_log("Password format: " . preg_match('/^[a-z0-9]{16}$/', $emailPassword) ? 'VALID' : 'INVALID');
                return false;
            }
            
            $mail = $this->createMailer();
            $mail->addAddress($this->config['email']['from_email']);
            $mail->Subject = 'Gmail SMTP Connection Test - IECEP-LSC';
            $mail->Body = 'This is a test to verify Gmail SMTP connection is working for IECEP-LSC MEMSYS.';
            
            $result = $mail->send();
            error_log("Gmail SMTP connection test result: " . ($result ? 'SUCCESS' : 'FAILED'));
            
            if (!$result) {
                error_log("Gmail SMTP Error Info: " . $mail->ErrorInfo);
                error_log("SMTP Host: " . $this->config['email']['host']);
                error_log("SMTP Port: " . $this->config['email']['port']);
                error_log("SMTP Username: " . $this->config['email']['username']);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Gmail SMTP connection test error: " . $e->getMessage());
            error_log("Exception trace: " . $e->getTraceAsString());
            return false;
        }
    }
    
    public function sendCredentialsTest(string $to, string $password): bool
    {
        try {
            error_log("Testing credentials email to: $to");
            
            $mail = $this->createMailer();
            $mail->addAddress($to);
            $loginUrl = $this->config['app_url'] . '/login.php';
            $mail->Subject = 'TEST: IECEP-LSC Account Credentials';
            
            $mail->Body = '
<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;">
    <div style="background-color: #0B1D4A; padding: 20px; text-align: center;">
        <h1 style="color: #C49A00; margin: 10px 0 0;">IECEP-LSC MEMSYS</h1>
    </div>
    <div style="padding: 30px;">
        <h2 style="color: #0B1D4A;">TEST EMAIL - Account Credentials</h2>
        <p>This is a test email to verify that credentials are being sent properly.</p>
        
        <div style="background-color: #fef3c7; border: 2px solid #f59e0b; border-radius: 6px; padding: 20px; margin: 20px 0;">
            <h3 style="color: #92400e; margin-top: 0;">TEST Account Details</h3>
            <p style="margin: 10px 0;"><strong>Email (Username):</strong><br><span style="font-size: 16px; color: #0B1D4A;">' . htmlspecialchars($to) . '</span></p>
            <p style="margin: 10px 0;"><strong>Temporary Password:</strong><br><span style="font-family: Consolas, Monaco, monospace; background: #fff; padding: 8px 12px; border-radius: 4px; font-size: 16px; color: #0B1D4A; border: 1px solid #e0e0e0; display: inline-block;">' . htmlspecialchars($password) . '</span></p>
            <p style="margin: 20px 0;"><a href="' . $loginUrl . '" style="display: inline-block; background-color: #C49A00; color: #0B1D4A; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: bold;">TEST Login to Portal</a></p>
        </div>
        
        <p style="color: #666;">This is a test email to verify the credentials email system is working.</p>
    </div>
</div>';
            
            $result = $mail->send();
            error_log("Test credentials email result: " . ($result ? 'SUCCESS' : 'FAILED'));
            
            if (!$result) {
                error_log("Test credentials email error: " . $mail->ErrorInfo);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Test credentials email exception: " . $e->getMessage());
            return false;
        }
    }
    
    public function getErrorInfo(): string
    {
        return $this->lastError ?: 'No error information available';
    }

    /**
     * Send push notification to user subscriptions.
     *
     * @param string $userId
     * @param string $title
     * @param string $body
     * @param array $data
     * @return bool
     */
    public function sendPushNotification(string $userId, string $title, string $body, array $data = []): bool
    {
        require_once __DIR__ . '/../../vendor/autoload.php'; // Assuming minishlink/web-push is installed

        $sb = new Supabase();
        $subscriptions = $sb->from('push_subscriptions')
            ->select('subscription')
            ->eq('user_id', $userId)
            ->get(true);

        if ($subscriptions['error'] || empty($subscriptions['data'])) {
            return false;
        }

        $auth = [
            'VAPID' => [
                'subject' => 'mailto:' . $this->config['email']['from_email'],
                'publicKey' => defined('VAPID_PUBLIC_KEY') ? constant('VAPID_PUBLIC_KEY') : '',
                'privateKey' => defined('VAPID_PRIVATE_KEY') ? constant('VAPID_PRIVATE_KEY') : '',
            ],
        ];

        $webPush = new \Minishlink\WebPush\WebPush($auth);

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'icon' => '/IECEP-LSC-MEMSYS/public/assets/icons/iecep-logo.png',
            'badge' => '/IECEP-LSC-MEMSYS/public/assets/icons/iecep-logo.png',
            'data' => $data,
            'url' => $data['url'] ?? '/portal/dashboard.php'
        ]);

        $success = true;
        foreach ($subscriptions['data'] as $sub) {
            $subscription = $sub['subscription'];
            if (is_string($subscription)) {
                $subscription = json_decode($subscription, true);
            }

            $result = $webPush->sendOneNotification($subscription, $payload);
            if (!$result->isSuccess()) {
                error_log('Push notification failed: ' . $result->getReason());
                $success = false;
            }
        }

        return $success;
    }
}
