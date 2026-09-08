<?php
require_once __DIR__ . '/bootstrap.php';
/**
 * Affiliation Review Action API
 * Handles approve, reject, and request_changes actions for affiliation applications
 */

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../../includes/supabase.php';
require_once __DIR__ . '/../../includes/paths.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../src/lib/SupabaseClient.php';
require_once __DIR__ . '/../../src/lib/EmailService.php';
require_once __DIR__ . '/../../src/lib/BlockchainService.php';
require_once __DIR__ . '/../../src/lib/csv.php';

use App\Lib\SupabaseClient;
use App\Lib\EmailService;
use App\Lib\BlockchainService;
use App\Lib\CsvService;

// Define BASE_PUBLIC_URL constant
if (!defined('BASE_PUBLIC_URL')) {
    define('BASE_PUBLIC_URL', '/IECEP-LSC-MEMSYS/public');
}

// Verify authentication and role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['registration', 'committee_registration', 'admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied. Registration Committee only.']);
    exit;
}

// Get database and email configuration
$config = require __DIR__ . '/../../includes/supabase.php';
$supabase = new SupabaseClient($config['url'], $config['service_role_key']);
$emailService = new EmailService();
$blockchain = new BlockchainService($supabase);
$GLOBALS['blockchain'] = $blockchain;

// Get POST data
$action = $_POST['action'] ?? '';
$applicationId = $_POST['id'] ?? '';

if (empty($action) || empty($applicationId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters: action and id']);
    exit;
}

// Validate action
$validActions = ['approve', 'reject', 'request_changes'];
if (!in_array($action, $validActions)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action. Must be: approve, reject, or request_changes']);
    exit;
}

// Fetch application data
try {
    $application = $supabase->select('pending_affiliations', ['id' => 'eq.' . $applicationId]);
    if (empty($application) || !is_array($application)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Application not found']);
        exit;
    }
    $application = $application[0];
} catch (Exception $e) {
    error_log('Error fetching application: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Check if application is already processed
$currentStatus = $application['status'] ?? 'pending';
if (in_array($currentStatus, ['approved', 'rejected'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'This application has already been ' . $currentStatus]);
    exit;
}

/**
 * Generate a secure temporary password (12 characters, mixed case, numbers, symbols)
 */
function generateTempPassword($length = 12) {
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $symbols = '!@#$%^&*()_+-=';
    $allChars = $uppercase . $lowercase . $numbers . $symbols;
    
    $password = '';
    
    // Ensure at least one character from each required category
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    $password .= $symbols[random_int(0, strlen($symbols) - 1)];
    
    // Fill the rest randomly
    for ($i = 4; $i < $length; $i++) {
        $password .= $allChars[random_int(0, strlen($allChars) - 1)];
    }
    
    return str_shuffle($password);
}

/**
 * Generate a secure edit token
 */
function generateEditToken() {
    return bin2hex(random_bytes(32)); // 64 character hex string
}

function downloadAffiliationFile(string $source, string $destination): bool
{
    if (is_file($source)) {
        return copy($source, $destination);
    }

    if (!preg_match('#^https?://#i', $source)) {
        $source = rtrim(BASE_URL, '/') . '/' . ltrim($source, '/');
    }

    $contents = false;
    if (function_exists('curl_init')) {
        $curl = curl_init($source);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30
        ]);
        $contents = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($status < 200 || $status >= 300) {
            $contents = false;
        }
    } else {
        $contents = @file_get_contents($source);
    }

    return $contents !== false && file_put_contents($destination, $contents) !== false;
}

function provisionInstitutionForApproval(SupabaseClient $supabase, array $application, string $institutionName, string $email, string $contactPerson): string
{
    $existing = $supabase->select('institutions', ['email' => 'eq.' . $email, 'limit' => 1]);
    if (!empty($existing[0]['id'])) {
        $institutionId = $existing[0]['id'];
        $supabase->update('institutions', [
            'name' => $institutionName,
            'contact_person' => $contactPerson,
            'contact_email' => $email,
            'status' => 'active',
            'updated_at' => date('c')
        ], $institutionId);
        return $institutionId;
    }

    $created = $supabase->insert('institutions', [
        'email' => $email,
        'name' => $institutionName,
        'contact_person' => $contactPerson,
        'contact_email' => $email,
        'address' => $application['institution_address'] ?? $application['address'] ?? null,
        'status' => 'active',
        'membership_count' => 0,
        'created_at' => date('c'),
        'updated_at' => date('c')
    ]);

    if (empty($created[0]['id'])) {
        throw new Exception('Failed to create institution record');
    }

    return $created[0]['id'];
}

function saveAndImportAffiliationDirectory(SupabaseClient $supabase, array $application, string $applicationId, string $institutionId, string $institutionName, string $userId): array
{
    $rawDocuments = $application['documents'] ?? [];
    $documents = is_array($rawDocuments) ? $rawDocuments : (json_decode((string)$rawDocuments, true) ?: []);
    $documentRows = [];
    $documentSources = [];

    try {
        $storedDocuments = $supabase->select('affiliation_documents', ['application_id' => 'eq.' . $applicationId]);
        foreach ($storedDocuments ?: [] as $storedDocument) {
            if (!empty($storedDocument['file_path'])) {
                $documentRows[] = $storedDocument;
                $documentSources[$storedDocument['document_type'] ?? $storedDocument['file_name']] = $storedDocument['file_path'];
            }
        }
    } catch (Throwable $e) {
        error_log('Affiliation document lookup notice: ' . $e->getMessage());
    }

    $documentTypes = [
        'letter_of_intent',
        'endorsement_letter',
        'constitution_by_laws',
        'officers_cvs',
        'organizational_chart',
        'member_directory'
    ];
    foreach ($documentTypes as $documentType) {
        $source = $documents[$documentType] ?? $application[$documentType] ?? null;
        if (is_string($source) && $source !== '' && !isset($documentSources[$documentType])) {
            $documentSources[$documentType] = $source;
        }
    }

    $institutionDirectory = PUBLIC_PATH . '/uploads/affiliations/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $institutionId);
    if (!is_dir($institutionDirectory) && !mkdir($institutionDirectory, 0755, true) && !is_dir($institutionDirectory)) {
        throw new Exception('Unable to create institution document directory');
    }

    $savedDirectoryPath = null;
    foreach ($documentSources as $documentType => $source) {
        if (!is_string($source) || $source === '') {
            continue;
        }

        $sourceName = basename(parse_url($source, PHP_URL_PATH) ?: $documentType);
        $sourceName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $sourceName) ?: ($documentType . '.dat');
        $destination = $institutionDirectory . '/' . $documentType . '_' . $sourceName;
        if (!downloadAffiliationFile($source, $destination)) {
            error_log("Unable to save affiliation document {$documentType} for application {$applicationId}");
            continue;
        }

        $relativePath = '/IECEP-LSC-MEMSYS/public/uploads/affiliations/' . basename($institutionDirectory) . '/' . basename($destination);
        try {
            $supabase->insert('institution_documents', [
                'institution_id' => $institutionId,
                'application_id' => $applicationId,
                'file_name' => $sourceName,
                'file_path' => $relativePath,
                'document_type' => $documentType,
                'uploaded_at' => date('c')
            ]);
        } catch (Throwable $e) {
            error_log('Institution document record notice: ' . $e->getMessage());
        }

        if ($documentType === 'member_directory') {
            $savedDirectoryPath = $destination;
        }
    }

    if (!$savedDirectoryPath || !is_file($savedDirectoryPath)) {
        throw new Exception('Member Directory file could not be saved for processing');
    }

    $parser = new CsvService();
    $parsed = $parser->parseMemberDirectory($savedDirectoryPath);
    if ($parsed['error']) {
        throw new Exception($parsed['message']);
    }

    $validMembers = $parsed['data'];
    foreach ($validMembers as $member) {
        $existing = $supabase->select('members', ['email' => 'eq.' . $member['email'], 'limit' => 1]);
        $memberType = !empty($existing[0]) ? 'returning' : ($member['member_type'] ?: 'new');
        $payload = [
            'institution_id' => $institutionId,
            'full_name' => $member['full_name'],
            'email' => $member['email'],
            'student_number' => $member['student_number'] ?: null,
            'course' => $member['course'] ?: null,
            'year_level' => $member['year_level'] ?: null,
            'membership_type' => 'student',
            'member_type' => $memberType,
            'payment_status' => 'pending',
            'status' => 'active',
            'membership_id' => 'AFF-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4))),
            'created_at' => date('c'),
            'updated_at' => date('c')
        ];

        if (!empty($existing[0]['id']) && ($existing[0]['institution_id'] ?? null) === $institutionId) {
            $supabase->update('members', $payload, $existing[0]['id']);
        } else {
            $supabase->insert('members', $payload);
        }
    }

    $memberCount = count($validMembers);
    $supabase->update('institutions', ['membership_count' => $memberCount, 'updated_at' => date('c')], $institutionId);

    try {
        $batchId = 'AFF-' . $applicationId;
        $supabase->insert('upload_batches', [
            'id' => $batchId,
            'institution_id' => $institutionId,
            'application_id' => $applicationId,
            'uploaded_by_user_id' => $userId,
            'file_name' => basename($savedDirectoryPath),
            'total_rows' => $memberCount,
            'validated_rows' => $memberCount,
            'status' => 'approved',
            'uploaded_at' => date('c')
        ]);
    } catch (Throwable $e) {
        error_log('Upload batch audit notice: ' . $e->getMessage());
    }

    try {
        $supabase->insert('audit_logs', [
            'action' => 'AFFILIATION_MEMBER_DIRECTORY_IMPORTED',
            'table_name' => 'members',
            'record_id' => (string)$institutionId,
            'new_data' => json_encode([
                'application_id' => $applicationId,
                'institution_id' => $institutionId,
                'member_count' => $memberCount,
                'file_path' => $savedDirectoryPath,
                'processed_by' => $userId,
                'timestamp' => date('c')
            ]),
            'performed_by' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Affiliation Approval API',
            'created_at' => date('c')
        ]);
    } catch (Throwable $e) {
        error_log('Member directory audit notice: ' . $e->getMessage());
    }

    return ['member_count' => $memberCount, 'directory_path' => $savedDirectoryPath];
}

// Process based on action
switch ($action) {
    case 'request_changes':
        $notes = trim($_POST['notes'] ?? '');
        
        if (empty($notes) || strlen($notes) < 20) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Committee notes required (minimum 20 characters)']);
            exit;
        }
        
        // Generate edit token
        $editToken = generateEditToken();
        
        // Update application
        $updateData = [
            'status' => 'changes_requested',
            'committee_notes' => $notes,
            'requested_at' => date('c'), // ISO 8601 format
            'edit_token' => $editToken
        ];
        
        try {
            $result = $supabase->update('pending_affiliations', $updateData, $applicationId);
            
            if (!$result) {
                throw new Exception('Failed to update application status');
            }
            
            $blockchain = $GLOBALS['blockchain'] ?? null;
            if (isset($blockchain) && $blockchain instanceof \App\Lib\BlockchainService) {
                $blockchain->record('affiliation', $applicationId, [
                    'action' => $action,
                    'notes' => $notes,
                    'reviewed_by' => $_SESSION['user']['email'] ?? 'system',
                    'previous_status' => $currentStatus,
                    'new_status' => 'changes_requested',
                ]);
            }
            
            // Send email to applicant
            $institutionName = $application['institution_name'] ?? 'Your Institution';
            $applicantEmail = $application['email'] ?? '';
            
            if (!empty($applicantEmail)) {
                // Build application data for email
                $appData = [
                    'id' => $applicationId,
                    'institution_name' => $application['institution_name'] ?? '',
                    'address' => $application['address'] ?? '',
                    'contact_person' => $application['contact_person'] ?? '',
                    'contact_position' => $application['contact_position'] ?? '',
                    'contact_phone' => $application['contact_phone'] ?? '',
                    'email' => $application['email'] ?? '',
                    'documents' => $application['documents'] ?? '{}'
                ];
                
                // Construct edit link
                $editLink = BASE_URL . '/edit-affiliation.php?token=' . urlencode($editToken);
                
                // Send change request email
                $emailSent = $emailService->sendChangesRequested($applicantEmail, $institutionName, $notes, $appData);
                
                if (!$emailSent) {
                    error_log('Failed to send change request email to: ' . $applicantEmail);
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Change request sent successfully. Applicant has been notified.',
                'edit_token' => $editToken
            ]);
            
        } catch (Exception $e) {
            error_log('Error in request_changes: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to process request: ' . $e->getMessage()]);
        }
        break;
        
    case 'approve':
        $email = $_POST['email'] ?? $application['email'] ?? '';
        $institution = $_POST['institution'] ?? $application['institution_name'] ?? '';
        $contactPerson = $_POST['contact_person'] ?? $application['contact_person'] ?? '';
        
        if (empty($email)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Applicant email is required']);
            exit;
        }
        
        // Generate temporary password (12 characters, secure format)
        $tempPassword = generateTempPassword(12);
        
        error_log("=== TEMP PASSWORD DEBUG ===");
        error_log("Generated password: $tempPassword");
        error_log("Password length: " . strlen($tempPassword));
        error_log("Has uppercase: " . (preg_match('/[A-Z]/', $tempPassword) ? 'yes' : 'no'));
        error_log("Has lowercase: " . (preg_match('/[a-z]/', $tempPassword) ? 'yes' : 'no'));
        error_log("Has numbers: " . (preg_match('/[0-9]/', $tempPassword) ? 'yes' : 'no'));
        
        try {
            // Create user in Supabase Auth using admin endpoint
            error_log("=== CREATING AUTH USER ===");
            error_log("Creating Supabase Auth user for: $email");
            
            $userId = null;
            try {
                $authResult = $supabase->authSignUp($email, $tempPassword, [
                    'full_name' => $contactPerson,
                    'role' => 'school_officer',
                    'must_change_password' => true
                ]);
                
                error_log("Auth signup result: " . json_encode($authResult));
                
                // Supabase admin create may return the user object directly or nested under 'user'/'data.user'
                $userId = $authResult['id'] 
                    ?? $authResult['user']['id'] ?? null 
                    ?? $authResult['data']['user']['id'] ?? null;
                
                error_log("Extracted user ID from authSignUp: $userId");
                if (empty($userId)) {
                    error_log("ERROR: Could not extract user ID from authSignUp response: " . json_encode($authResult));
                } else {
                    error_log("SUCCESS: User created in Supabase Auth with ID: $userId");
                }
            } catch (Exception $authError) {
                // Check if user already exists
                error_log("Auth signup error (may already exist): " . $authError->getMessage());
                error_log("Attempting to find existing user via admin API");
                
                // Try to find existing user using admin API
                try {
                    $existingUser = $supabase->authGetUserByEmail($email);
                    if (!empty($existingUser) && isset($existingUser['id'])) {
                        $userId = $existingUser['id'];
                        error_log("Found existing Supabase Auth user via admin API with ID: $userId");
                        
                        // Update password for existing user
                        try {
                            error_log("Updating password for existing user: $email, ID: $userId");
                            $supabase->authUpdatePassword($userId, $tempPassword);
                            error_log("Password updated successfully for user: $userId");
                        } catch (Exception $pwError) {
                            error_log("Failed to update password: " . $pwError->getMessage());
                            // Continue anyway - password update might not be critical if user can still login
                        }
                    } else {
                        error_log("ERROR: No existing user found for email: $email");
                    }
                } catch (Exception $lookupError) {
                    error_log("Failed to lookup existing user: " . $lookupError->getMessage());
                    // User lookup failed, throw original error
                    throw $authError;
                }
            }

            if (empty($userId)) {
                throw new Exception('Failed to obtain Supabase Auth user id from signup result');
            }
            
            error_log("User ID for approval: $userId");
            
            // CRITICAL: Verify user actually exists in auth.users
            error_log("Verifying user exists in Supabase Auth after creation/lookup");
            try {
                $verifyUser = $supabase->authGetUserById($userId);
                if (empty($verifyUser)) {
                    error_log("CRITICAL: User verification FAILED - user $userId not found in Supabase Auth!");
                    throw new Exception("User was not successfully created in Supabase Auth. Cannot proceed.");
                } else {
                    error_log("User verification PASSED - user $userId exists in Supabase Auth");
                }
            } catch (\Exception $verifyError) {
                error_log("User verification error: " . $verifyError->getMessage());
                // If verification query fails, continue anyway (may be permission issue)
            }
            
            // Create a matching profile record if not exists
            try {
                $existingProfile = $supabase->select('user_profiles', ['user_id' => 'eq.' . $userId]);
                if (empty($existingProfile)) {
                    $profileData = [
                        'user_id' => $userId,
                        'role' => 'school_officer',
                        'full_name' => $contactPerson,
                        'membership_status' => 'active',
                        'must_change_password' => true,
                        'force_password_change' => true,
                    ];
                    error_log("Creating user profile for user_id: $userId");
                    $profileResult = $supabase->insert('user_profiles', $profileData);
                    error_log("Profile creation result: " . json_encode($profileResult));
                } else {
                    error_log("User profile already exists for user_id: $userId");
                    // Ensure role and must_change_password are set
                    try {
                        $supabase->update('user_profiles', [
                            'role' => 'school_officer',
                            'must_change_password' => true,
                            'force_password_change' => true,
                        ], $existingProfile[0]['id'] ?? $existingProfile[0]['user_id']);
                    } catch (\Throwable $pe) {
                        error_log("Notice updating existing profile: " . $pe->getMessage());
                    }
                }
            } catch (Exception $profileError) {
                error_log('Profile creation error: ' . $profileError->getMessage());
                // Don't throw - profile may already exist
            }

            // Provision the institution before importing its submitted directory.
            $institutionId = provisionInstitutionForApproval($supabase, $application, $institution, $email, $contactPerson);
            $supabase->update('user_profiles', [
                'institution_id' => $institutionId,
                'email' => $email,
                'updated_at' => date('c')
            ], ['user_id' => 'eq.' . $userId]);

            $directoryImport = saveAndImportAffiliationDirectory(
                $supabase,
                $application,
                $applicationId,
                $institutionId,
                $institution,
                $userId
            );

            // Update application with approval details
            $updateData = [
                'status' => 'approved',
                'approved_at' => date('c'),
                'portal_user_id' => $userId,
                'login_credentials_sent' => true,
                'institution_id' => $institutionId,
                'member_count' => $directoryImport['member_count']
            ];
            
            $result = $supabase->update('pending_affiliations', $updateData, $applicationId);

            if (!$result) {
                // Rollback not needed - Supabase Auth handles user creation
                throw new Exception('Failed to update application status');
            }

            $blockchain = $GLOBALS['blockchain'] ?? null;
            if (isset($blockchain) && $blockchain instanceof \App\Lib\BlockchainService) {
                $blockchain->record('affiliation', $applicationId, [
                    'action' => $action,
                    'notes' => $_POST['notes'] ?? '',
                    'reviewed_by' => $_SESSION['user']['email'] ?? 'system',
                    'previous_status' => $currentStatus,
                    'new_status' => 'approved',
                    'user_id' => $userId,
                ]);
            }

            // Log action to audit_logs table
            try {
                $auditAdminId = $_SESSION['user']['id'] ?? null;
                $auditAdminEmail = $_SESSION['user']['email'] ?? ($_SESSION['email'] ?? 'admin');
                $supabase->insert('audit_logs', [
                    'action' => 'AFFILIATION_APPROVED',
                    'table_name' => 'pending_affiliations',
                    'record_id' => (string)$applicationId,
                    'new_data' => json_encode([
                        'application_id' => $applicationId,
                        'institution_name' => $institution,
                        'school_officer_email' => $email,
                        'school_officer_name' => $contactPerson,
                        'officer_user_id' => $userId,
                        'approved_by' => $auditAdminEmail,
                        'timestamp' => date('c')
                    ]),
                    'performed_by' => $auditAdminId,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Admin Panel',
                    'created_at' => date('c')
                ]);
                error_log("Audit log recorded for affiliation approval of application ID: $applicationId");
            } catch (\Throwable $auditError) {
                error_log("Failed to insert into audit_logs: " . $auditError->getMessage());
            }

            // Add to affiliated_schools table if exists
            try {
                $schoolData = [
                    'name' => $institution,
                    'facebook_url' => '',
                    'member_count' => 0,
                    'status' => 'active',
                    'created_at' => date('c'),
                    'updated_at' => date('c')
                ];
                $supabase->insert('affiliated_schools', $schoolData);
            } catch (Exception $e) {
                error_log('Note: Could not add to affiliated_schools (may already exist): ' . $e->getMessage());
            }
            
            // Send credentials email using sendSchoolOfficerCredentials
            $portalUrl = (defined('APP_URL') && !empty(APP_URL)) ? rtrim(APP_URL, '/') . '/login.php' : (BASE_URL . '/login.php');
            
            error_log("=== EMAIL SENDING DEBUG ===");
            error_log("Recipient: $email");
            error_log("Institution: $institution");
            error_log("Temp password: $tempPassword");
            error_log("Contact Person: $contactPerson");
            error_log("Portal URL: $portalUrl");
            
            try {
                $emailSent = $emailService->sendSchoolOfficerCredentials($email, $contactPerson, $tempPassword, $portalUrl, $institution);
                error_log("School officer credentials email send result: " . ($emailSent ? 'SUCCESS' : 'FAILED'));
            } catch (Exception $emailError) {
                error_log("Email exception: " . $emailError->getMessage());
                $emailSent = false;
            }
            
            $responseMessage = 'Application approved successfully!';
            $warning = null;
            
            if (!$emailSent) {
                error_log('WARNING: Failed to send credentials email to: ' . $email);
                $warning = 'Account created but email notification failed. Please manually send credentials.';
                $responseMessage = 'Application approved but email notification failed.';
            } else {
                error_log('SUCCESS: Credentials email sent to: ' . $email);
                $responseMessage = 'Application approved and credentials email sent successfully!';
            }
            
            $response = [
                'success' => true,
                'message' => $responseMessage . ' ' . $directoryImport['member_count'] . ' valid members imported.',
                'user_id' => $userId,
                'institution_id' => $institutionId,
                'member_count' => $directoryImport['member_count'],
                'email_sent' => $emailSent,
                'credentials' => [
                    'email' => $email,
                    'temp_password' => $tempPassword,
                    'instructions' => 'Use the email and password above to login at ' . $portalUrl
                ]
            ];
            
            if ($warning) {
                $response['warning'] = $warning;
            }
            
            error_log("=== APPROVAL SUCCESS ===");
            error_log("User created with email: $email");
            error_log("Temporary password sent: $tempPassword");
            error_log("User ID: $userId");
            error_log("Response: " . json_encode($response));
            
            echo json_encode($response);
            
        } catch (Exception $e) {
            error_log('Error in approve: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to approve application: ' . $e->getMessage()]);
        }
        break;
        
    case 'reject':
        $reason = trim($_POST['reason'] ?? '');
        
        if (empty($reason) || strlen($reason) < 10) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Rejection reason required (minimum 10 characters)']);
            exit;
        }
        
        try {
            // Update application
            $updateData = [
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'rejected_at' => date('c')
            ];
            
            $result = $supabase->update('pending_affiliations', $updateData, $applicationId);

            if (!$result) {
                throw new Exception('Failed to update application status');
            }

            $blockchain = $GLOBALS['blockchain'] ?? null;
            if (isset($blockchain) && $blockchain instanceof \App\Lib\BlockchainService) {
                $blockchain->record('affiliation', $applicationId, [
                    'action' => $action,
                    'notes' => $reason,
                    'reviewed_by' => $_SESSION['user']['email'] ?? 'system',
                    'previous_status' => $currentStatus,
                    'new_status' => 'rejected',
                ]);
            }

            // Send rejection email
            $institutionName = $application['institution_name'] ?? 'Your Institution';
            $applicantEmail = $application['email'] ?? '';
            
            if (!empty($applicantEmail)) {
                try {
                    $emailSent = $emailService->sendAffiliationRejected($applicantEmail, $institutionName, $reason);
                    if (!$emailSent) {
                        error_log('Failed to send rejection email to: ' . $applicantEmail);
                    }
                } catch (\Throwable $emEx) {
                    error_log('Exception sending rejection email to ' . $applicantEmail . ': ' . $emEx->getMessage());
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Application rejected. Applicant has been notified.'
            ]);
            
        } catch (Exception $e) {
            error_log('Error in reject: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to reject application: ' . $e->getMessage()]);
        }
        break;
}


