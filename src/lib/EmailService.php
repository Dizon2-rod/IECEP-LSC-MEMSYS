<?php
namespace App\Lib;

// Suppress PHP errors to prevent HTML warnings in JSON response
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../../vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private array $config;

    public function __construct()
    {
        // Use constants directly from config.php
        $this->config = [
            'app_env' => APP_ENV,
            'app_url' => APP_URL,
            'email' => [
                'host' => SMTP_HOST,
                'port' => SMTP_PORT,
                'username' => SMTP_USERNAME,
                'password' => SMTP_PASSWORD,
                'from_email' => SMTP_FROM_EMAIL,
                'from_name' => SMTP_FROM_NAME
            ]
        ];

        error_log('EmailService initialized with: ' . SMTP_USERNAME);
    }

    private function createMailer(): PHPMailer
    {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $this->config['email']['host'];
            $mail->Port = (int)$this->config['email']['port'];
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Username = $this->config['email']['username'];
            $mail->Password = $this->config['email']['password'];
            $mail->setFrom($this->config['email']['from_email'], $this->config['email']['from_name']);
            $mail->isHTML(true);
            
            // Enable debugging for development
            if ($this->config['app_env'] === 'development') {
                $mail->SMTPDebug = 0; // Set to 2 for detailed debugging
                $mail->Debugoutput = 'error_log';
            }
            
            return $mail;
        } catch (Exception $e) {
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

    public function sendCredentials(string $to, string $email, string $password): bool
    {
        try {
            $mail = $this->createMailer();
            $mail->addAddress($to);
            $loginUrl = $this->config['app_url'] . '/login.php';
            $mail->Subject = 'IECEP-LSC Membership Account Created';
            $mail->Body = "
                <div style='font-family:Inter,sans-serif;max-width:480px;margin:0 auto;padding:24px'>
                    <h2 style='color:#0A2F6C'>Welcome to IECEP-LSC!</h2>
                    <p>Your membership account has been created. Here are your login credentials:</p>
                    <table style='width:100%;border-collapse:collapse;margin:16px 0'>
                        <tr><td style='padding:8px;font-weight:bold;color:#0A2F6C'>Email:</td><td style='padding:8px'>{$email}</td></tr>
                        <tr><td style='padding:8px;font-weight:bold;color:#0A2F6C'>Password:</td><td style='padding:8px'>{$password}</td></tr>
                    </table>
                    <p style='color:#dc3545;font-weight:bold'>You must change your password on first login.</p>
                    <a href='{$loginUrl}' style='display:inline-block;padding:12px 24px;background:#F5A623;color:#fff;text-decoration:none;border-radius:8px;margin-top:12px'>Login Now</a>
                </div>";
            $mail->AltBody = "Your IECEP-LSC account has been created. Email: {$email}, Password: {$password}. Please change your password on first login. Login at: {$loginUrl}";
            return $mail->send();
        } catch (Exception $e) {
            error_log("Email error (credentials): " . $e->getMessage());
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
            return $mail->send();
        } catch (Exception $e) {
            error_log("Email error (notification): " . $e->getMessage());
            return false;
        }
    }

    public function sendAffiliationConfirmation(string $to, string $institutionName): bool
    {
        try {
            $mail = $this->createMailer();
            $mail->addAddress($to);
            $mail->Subject = 'IECEP-LSC Affiliation Application Received';
            $mail->Body = "
                <div style='font-family:Inter,sans-serif;max-width:480px;margin:0 auto;padding:24px'>
                    <h2 style='color:#0A2F6C'>Application Received!</h2>
                    <p>Thank you for submitting your affiliation application for <strong>{$institutionName}</strong>.</p>
                    <div style='background:#f8f9fa;padding:16px;border-radius:8px;margin:20px 0'>
                        <h4 style='color:#0A2F6C;margin-bottom:8px'>What happens next?</h4>
                        <ol style='margin:0;padding-left:20px;color:#343a40'>
                            <li>Our Registration Committee will review your application</li>
                            <li>All submitted documents will be verified</li>
                            <li>You will receive a decision within 3-5 business days</li>
                            <li>If approved, you'll receive further instructions for account setup</li>
                        </ol>
                    </div>
                    <p style='color:#6c757d;font-size:14px'>If you have any questions, please don't hesitate to contact us.</p>
                    <hr style='border:none;border-top:1px solid #dee2e6;margin:20px 0'>
                    <p style='font-size:12px;color:#6c757d;text-align:center'>Best regards,<br>IECEP-LSC Registration Committee</p>
                </div>";
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
}
