<?php
$current_page = basename(__FILE__, '.php');
// Set JSON headers first
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Start session
session_start();

// Function to send JSON response and exit
function sendResponse($success, $message = '', $error = '') {
    $response = ['success' => $success];
    if ($message) $response['message'] = $message;
    if ($error) $response['error'] = $error;
    echo json_encode($response);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    sendResponse(false, '', 'Invalid request method');
}

try {
    // Load configuration first (defines APP_ENV and other constants)
    require_once __DIR__ . '/../../../includes/config.php';

    // Load Supabase configuration
    $configPath = __DIR__ . '/../../../includes/supabase.php';
    if (!file_exists($configPath)) {
        sendResponse(false, '', 'Supabase configuration not found');
    }
    $config = require $configPath;

    // Load Supabase client
    require_once __DIR__ . '/../../../src/lib/SupabaseClient.php';
    $supabase = new SupabaseClient($config['url'], $config['anon_key']);

    // Get POST data
    $applicationId = $_POST['application_id'] ?? '';
    $action = $_POST['action'] ?? '';
    $rejectionReason = $_POST['rejection_reason'] ?? '';
    $changesInstructions = $_POST['changes_instructions'] ?? '';

    if (empty($applicationId)) {
        sendResponse(false, '', 'Application ID is required');
    }

    // Fetch current application data
    $application = $supabase->select('pending_affiliations', ['id' => 'eq.' . $applicationId]);
    if (empty($application) || !is_array($application)) {
        sendResponse(false, '', 'Application not found');
    }
    $appData = $application[0];

    // ---------- APPROVE ----------
    if ($action === 'approve') {
        // Switch to service role key to bypass RLS for user creation
        $supabase->setServiceRoleKey($config['service_role_key']);

        // Check if user with this email already exists
        $existingUsers = $supabase->select('users', ['email' => 'eq.' . $appData['email']]);
        $userId = null;
        $isNewUser = false;
        $tempPassword = null;

        if (!empty($existingUsers) && is_array($existingUsers) && isset($existingUsers[0]['id'])) {
            // User already exists – reuse the account
            $userId = $existingUsers[0]['id'];
            $isNewUser = false;
            error_log("Existing user found for {$appData['email']}, reusing ID: $userId");

            // Update role to school_officer if necessary
            $supabase->update('users', [
                'role' => 'school_officer',
                'full_name' => $appData['contact_person'] ?? $existingUsers[0]['full_name'],
                'updated_at' => date('Y-m-d H:i:s')
            ], $userId);

            // Check if user_profiles exists, create if not
            $existingProfiles = $supabase->select('user_profiles', ['user_id' => 'eq.' . $userId]);
            if (empty($existingProfiles)) {
                error_log("Creating missing user_profiles for existing user_id=$userId");
                $profileData = [
                    'user_id'     => $userId,
                    'role'        => 'school_officer',
                    'full_name'   => $appData['contact_person'] ?? $existingUsers[0]['full_name'],
                    'school_name' => $appData['institution_name'],
                    'contact_phone' => $appData['contact_phone'] ?? null,
                    'address'     => $appData['address'] ?? null,
                    'created_at'  => date('Y-m-d H:i:s'),
                    'updated_at'  => date('Y-m-d H:i:s')
                ];
                $supabase->insert('user_profiles', $profileData);
            } else {
                // Update existing profile
                $supabase->update('user_profiles', [
                    'role' => 'school_officer',
                    'school_name' => $appData['institution_name'],
                    'updated_at' => date('Y-m-d H:i:s')
                ], $existingProfiles[0]['id']);
            }
        } else {
            // No existing user – create a new account
            $isNewUser = true;
            $tempPassword = substr(bin2hex(random_bytes(6)), 0, 12);
            $passwordHash = password_hash($tempPassword, PASSWORD_BCRYPT);

            // Generate UUID for new user
            $userId = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );

            $userData = [
                'id'          => $userId,
                'email'       => $appData['email'],
                'password'    => $passwordHash,
                'full_name'   => $appData['contact_person'] ?? $appData['institution_name'],
                'role'        => 'school_officer',
                'must_change_password' => 1,
                'is_active'   => 1,
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s')
            ];

            $userResult = $supabase->insert('users', $userData);
            if (!$userResult || !isset($userResult[0]['id'])) {
                sendResponse(false, '', 'Failed to create user account');
            }

            error_log("New user created: $userId for {$appData['email']}");

            // Create user_profiles record
            $profileData = [
                'user_id'     => $userId,
                'role'        => 'school_officer',
                'full_name'   => $appData['contact_person'] ?? $appData['institution_name'],
                'school_name' => $appData['institution_name'],
                'contact_phone' => $appData['contact_phone'] ?? null,
                'address'     => $appData['address'] ?? null,
                'force_password_change' => true,
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s')
            ];

            $profileResult = $supabase->insert('user_profiles', $profileData);
            if (!$profileResult) {
                error_log("Warning: Failed to create user_profiles for user_id=$userId");
                // Don't fail the whole process, but log it
            } else {
                error_log("User profile created for user_id=$userId");
            }
        }

        // Update the application record
        $supabase->update('pending_affiliations', [
            'status'       => 'approved',
            'approved_at'  => date('Y-m-d H:i:s'),
            'portal_user_id' => $userId,
            'login_credentials_sent' => 1,
            'updated_at'   => date('Y-m-d H:i:s')
        ], $applicationId);

        // Send appropriate email
        require_once __DIR__ . '/../../../src/lib/EmailService.php';
        $emailService = new \App\Lib\EmailService();

        if ($isNewUser) {
            // Send credentials email
            $emailService->sendSchoolAccountCredentials(
                $appData['email'],
                $appData['institution_name'],
                $tempPassword
            );
        } else {
            // Notify existing user of linked account
            $emailService->sendSchoolAffiliationLinked(
                $appData['email'],
                $appData['institution_name']
            );
        }

        $message = $isNewUser
            ? 'Application approved. User account created and credentials sent.'
            : 'Application approved. Existing account linked to school.';
        sendResponse(true, $message);
    }

    // ---------- REQUEST CHANGES ----------
    if ($action === 'request_changes') {
        if (empty($changesInstructions)) {
            sendResponse(false, '', 'Changes instructions are required');
        }

        // Attach instructions to the application documents
        $documents = json_decode($appData['documents'] ?? '{}', true) ?: [];
        $documents['changes_instructions'] = $changesInstructions;

        $supabase->update('pending_affiliations', [
            'status'       => 'changes_requested',
            'documents'    => json_encode($documents),
            'requested_at' => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s')
        ], $applicationId);

        // Try to send email (non‑blocking)
        try {
            require_once __DIR__ . '/../../../src/lib/EmailService.php';
            $emailService = new \App\Lib\EmailService();
            $emailService->sendChangesRequested($appData['email'], $appData['institution_name'], $changesInstructions);
        } catch (Exception $e) {
            error_log("Changes requested email failed: " . $e->getMessage());
        }

        sendResponse(true, 'Changes requested successfully.');
    }

    // ---------- REJECT ----------
    if ($action === 'reject') {
        if (empty($rejectionReason)) {
            sendResponse(false, '', 'Rejection reason is required');
        }

        $supabase->update('pending_affiliations', [
            'status'           => 'rejected',
            'rejection_reason' => $rejectionReason,
            'rejected_at'      => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s')
        ], $applicationId);

        // Try to send email (non‑blocking)
        try {
            require_once __DIR__ . '/../../../src/lib/EmailService.php';
            $emailService = new \App\Lib\EmailService();
            $emailService->sendAffiliationRejected($appData['email'], $appData['institution_name'], $rejectionReason);
        } catch (Exception $e) {
            error_log("Rejection email failed: " . $e->getMessage());
        }

        sendResponse(true, 'Application rejected successfully.');
    }

    // Fallback (should not reach here)
    sendResponse(false, '', 'Unknown action.');

} catch (Exception $e) {
    error_log("Affiliation action error: " . $e->getMessage());
    sendResponse(false, '', 'Server error: ' . $e->getMessage());
}
