<?php
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

use App\Lib\SupabaseClient;
use App\Lib\EmailService;

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
 * Generate a secure temporary password that meets Supabase Auth requirements
 * Includes uppercase, lowercase, and numbers only for safer email copy/paste
 */
function generateTempPassword($length = 12) {
    $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $lowercase = 'abcdefghijklmnopqrstuvwxyz';
    $numbers = '0123456789';
    $allChars = $uppercase . $lowercase . $numbers;
    
    $password = '';
    
    // Ensure at least one character from each required category
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    
    // Fill the rest randomly
    for ($i = 3; $i < $length; $i++) {
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
                $blockchain->record('affiliation_action', $applicationId, [
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
        $passwordHash = password_hash($tempPassword, PASSWORD_BCRYPT);
        
        try {
            // Create user in Supabase Auth using admin endpoint
            error_log("Creating Supabase Auth user for: $email");
            
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

            if (empty($userId)) {
                throw new Exception('Failed to obtain Supabase Auth user id from signup result');
            }
            
            error_log("Supabase Auth user created with ID: $userId");
            
            // Create a matching profile record for the new school officer
            try {
                $profileData = [
                    'user_id' => $userId,
                    'role' => 'school_officer',
                    'full_name' => $contactPerson,
                    'membership_status' => 'active',
                    'force_password_change' => true, // Require password change on first login
                ];
                error_log("Creating user profile for user_id: $userId");
                $profileResult = $supabase->insert('user_profiles', $profileData);
                error_log("Profile creation result: " . json_encode($profileResult));
                if (empty($profileResult) || !is_array($profileResult) || !isset($profileResult[0]['id'])) {
                    throw new Exception('Failed to create user profile for approved affiliate');
                }
                error_log("Profile created successfully with ID: " . $profileResult[0]['id']);
            } catch (Exception $profileError) {
                error_log('Profile creation error: ' . $profileError->getMessage());
                throw new Exception('Failed to create user profile for approved affiliate');
            }

            // Update application with approval details
            $updateData = [
                'status' => 'approved',
                'approved_at' => date('c'),
                'portal_user_id' => $userId,
                'login_credentials_sent' => true
            ];
            
            $result = $supabase->update('pending_affiliations', $updateData, $applicationId);

            if (!$result) {
                // Rollback not needed - Supabase Auth handles user creation
                throw new Exception('Failed to update application status');
            }

            $blockchain = $GLOBALS['blockchain'] ?? null;
            if (isset($blockchain) && $blockchain instanceof \App\Lib\BlockchainService) {
                $blockchain->record('affiliation_action', $applicationId, [
                    'action' => $action,
                    'notes' => $_POST['notes'] ?? '',
                    'reviewed_by' => $_SESSION['user']['email'] ?? 'system',
                    'previous_status' => $currentStatus,
                    'new_status' => 'approved',
                ]);
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
            
            // Send credentials email
            $portalUrl = BASE_URL . '/login.php';
            
            error_log("=== EMAIL SENDING DEBUG ===");
            error_log("Recipient: $email");
            error_log("Institution: $institution");
            error_log("Temp password: $tempPassword");
            error_log("Portal URL: $portalUrl");
            
            try {
                $emailSent = $emailService->sendSchoolAccountCredentials($email, $institution, $tempPassword, $contactPerson, $portalUrl);
                error_log("Email send result: " . ($emailSent ? 'SUCCESS' : 'FAILED'));
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
                'message' => $responseMessage,
                'user_id' => $userId,
                'email_sent' => $emailSent,
                'credentials' => [
                    'email' => $email,
                    'temp_password' => $tempPassword
                ]
            ];
            
            if ($warning) {
                $response['warning'] = $warning;
            }
            
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
                $blockchain->record('affiliation_action', $applicationId, [
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
                $emailSent = $emailService->sendAffiliationRejected($applicantEmail, $institutionName, $reason);
                
                if (!$emailSent) {
                    error_log('Failed to send rejection email to: ' . $applicantEmail);
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


