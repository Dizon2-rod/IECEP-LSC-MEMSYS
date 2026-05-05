<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        require_once __DIR__ . '/../../includes/config.php';
        $config = require __DIR__ . '/../../includes/supabase.php';
        require_once __DIR__ . '/../../src/lib/SupabaseClient.php';
        require_once __DIR__ . '/../../src/lib/EmailService.php';

        $supabase = new SupabaseClient($config['url'], $config['anon_key']);

        $applicationId = $_POST['application_id'];
        $status = $_POST['status'];
        $rejectionReason = $_POST['rejection_reason'] ?? '';
        $changesInstructions = $_POST['changes_instructions'] ?? '';

        $updateData = [
            'status' => $status,
            'reviewed_at' => date('Y-m-d H:i:s')
        ];

        if ($status === 'rejected' && !empty($rejectionReason)) {
            $updateData['rejection_reason'] = $rejectionReason;
        }

        if ($_POST['action'] === 'request_changes' && !empty($changesInstructions)) {
            $application = $supabase->select('pending_affiliations', ['id' => 'eq.' . $applicationId]);
            if (!empty($application) && is_array($application)) {
                $appData = $application[0];
                $documents = [];
                if (!empty($appData['documents'])) {
                    $documents = json_decode($appData['documents'], true) ?: [];
                }
                $documents['changes_instructions'] = $changesInstructions;
                $updateData['documents'] = json_encode($documents);
            }
        }

        $result = $supabase->update('pending_affiliations', $updateData, $applicationId);

        if ($result) {
            $application = $supabase->select('pending_affiliations', ['id' => 'eq.' . $applicationId]);

            if (!empty($application) && is_array($application)) {
                $appData = $application[0];
                
                // Initialize email service with error handling
                try {
                    error_log("Attempting to initialize EmailService...");
                    $emailService = new \App\Lib\EmailService();
                    error_log("EmailService initialized successfully");
                } catch (Exception $e) {
                    error_log("Failed to initialize EmailService: " . $e->getMessage());
                    error_log("EmailService initialization error details: " . $e->getTraceAsString());
                    // Don't throw exception to prevent approval from failing
                    $emailService = null;
                }
                
                // Only proceed with email sending if service was initialized
                if ($emailService) {

                if ($status === 'approved') {
                    // Generate secure temporary password
                    $tempPassword = substr(bin2hex(random_bytes(8)), 0, 12);
                    $hashedPassword = password_hash($tempPassword, PASSWORD_BCRYPT);
                    
                    $schoolEmail = $appData['email'];
                    $institutionName = $appData['institution_name'];
                    $contactPerson = $appData['contact_person'];
                    
                    error_log("Creating school officer account for approval - School Email: $schoolEmail, Institution: $institutionName, Application ID: $applicationId");

                    // Create user account in users table
                    $userData = [
                        'email' => $schoolEmail,
                        'password' => $hashedPassword,
                        'full_name' => $contactPerson,
                        'role' => 'school_officer',
                        'is_active' => true,
                        'must_change_password' => true,
                        'school_id' => $applicationId, // Link to the pending_affiliation record
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $userResult = $supabase->insert('users', $userData);
                    
                    if ($userResult && !empty($userResult) && isset($userResult[0]['id'])) {
                        $newUserId = $userResult[0]['id'];
                        error_log("School officer account created successfully with ID: $newUserId");
                        
                        // Update pending_affiliations record with user ID and approval details
                        $approvalData = [
                            'status' => 'approved',
                            'approved_at' => date('Y-m-d H:i:s'),
                            'portal_user_id' => $newUserId,
                            'login_credentials_sent' => true
                        ];
                        
                        $approvalResult = $supabase->update('pending_affiliations', $approvalData, $applicationId);
                        
                        if ($approvalResult) {
                            error_log("Application approved and linked to user account successfully");
                            
                            // Insert into affiliated_schools table if it exists
                            try {
                                $schoolData = [
                                    'name' => $institutionName,
                                    'address' => $appData['address'] ?? '',
                                    'contact_email' => $schoolEmail,
                                    'contact_person' => $contactPerson,
                                    'contact_phone' => $appData['contact_phone'] ?? '',
                                    'status' => 'active',
                                    'portal_user_id' => $newUserId,
                                    'created_at' => date('Y-m-d H:i:s'),
                                    'updated_at' => date('Y-m-d H:i:s')
                                ];
                                
                                $schoolResult = $supabase->insert('affiliated_schools', $schoolData);
                                if ($schoolResult) {
                                    error_log("School added to affiliated_schools table successfully");
                                } else {
                                    error_log("Warning: Failed to add school to affiliated_schools table, but approval still succeeded");
                                }
                            } catch (Exception $e) {
                                error_log("Warning: Could not add to affiliated_schools table: " . $e->getMessage());
                            }
                            
                            // Send credentials email with real account details
                            try {
                                error_log("Sending school account credentials to: $schoolEmail");
                                error_log("Email config: SMTP_HOST=" . SMTP_HOST . ", SMTP_USERNAME=" . SMTP_USERNAME);
                                error_log("Temporary password generated: $tempPassword");
                                
                                // First test Gmail connection
                                error_log("Testing Gmail SMTP connection before sending credentials...");
                                $connectionTest = $emailService->testGmailConnection();
                                error_log("Gmail connection test result: " . ($connectionTest ? 'SUCCESS' : 'FAILED'));
                                
                                if ($connectionTest) {
                                    // Send actual credentials email with contact person
                                    $contactPerson = $application['contact_person'] ?? '';
                                    $credentialsSent = $emailService->sendSchoolAccountCredentials($schoolEmail, $institutionName, $tempPassword, $contactPerson);
                                    error_log("Credentials email sent to $schoolEmail: " . ($credentialsSent ? 'SUCCESS' : 'FAILED'));
                                    
                                    if (!$credentialsSent) {
                                        error_log("WARNING: Credentials email failed to send, but account was created");
                                        error_log("EmailService Error Info: " . ($emailService ? $emailService->getErrorInfo() : 'Not available'));
                                        
                                        // Try sending a test email to verify email service
                                        try {
                                            error_log("Attempting to send test credentials email...");
                                            $testSent = $emailService->sendCredentialsTest($schoolEmail, $tempPassword);
                                            error_log("Test credentials email result: " . ($testSent ? 'SUCCESS' : 'FAILED'));
                                        } catch (Exception $testEx) {
                                            error_log("Test credentials email exception: " . $testEx->getMessage());
                                        }
                                    }
                                } else {
                                    error_log("CRITICAL: Gmail SMTP connection failed, cannot send credentials email");
                                    error_log("Account was created but email delivery failed");
                                    
                                    // Try fallback email service
                                    try {
                                        error_log("Attempting fallback email service...");
                                        $fallbackSent = $emailService->sendTestEmail($schoolEmail);
                                        error_log("Fallback email result: " . ($fallbackSent ? 'SUCCESS' : 'FAILED'));
                                    } catch (Exception $fallbackEx) {
                                        error_log("Fallback email exception: " . $fallbackEx->getMessage());
                                    }
                                }
                            } catch (Exception $e) {
                                error_log("Exception sending credentials email: " . $e->getMessage());
                                error_log("Exception details: " . $e->getTraceAsString());
                            }
                            
                            // Send approval confirmation email
                            try {
                                $approvalSent = $emailService->sendAffiliationApproved($schoolEmail, $institutionName);
                                error_log("Approval email sent to $schoolEmail: " . ($approvalSent ? 'SUCCESS' : 'FAILED'));
                                
                                if (!$approvalSent) {
                                    error_log("WARNING: Approval email failed to send, but application was approved");
                                }
                            } catch (Exception $e) {
                                error_log("Exception sending approval email: " . $e->getMessage());
                            }
                            
                        } else {
                            error_log("Failed to update application with approval details");
                            throw new Exception("Failed to update application with approval details");
                        }
                        
                    } else {
                        error_log("Failed to create school officer account");
                        throw new Exception("Failed to create school officer account");
                    }
                } elseif ($status === 'rejected') {
                    $emailService->sendAffiliationRejected($appData['email'], $appData['institution_name'], $rejectionReason);
                } elseif ($_POST['action'] === 'request_changes' && !empty($changesInstructions)) {
                    $emailService->sendChangesRequested($appData['email'], $appData['institution_name'], $changesInstructions, $appData);
                }
            }
            }

            echo json_encode(['success' => true, 'message' => 'Application updated successfully']);
            exit;
        }

        echo json_encode(['success' => false]);
        exit;
    } catch (Throwable $e) {
        error_log("affiliation_status.php Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

function generatePassword() {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    $password = '';
    for ($i = 0; $i < 12; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
exit;
?>
