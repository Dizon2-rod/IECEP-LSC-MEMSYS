<?php
require_once __DIR__ . '/../bootstrap.php';
$current_page = basename(__FILE__, '.php');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function sendResponse($success, $message = '', $error = '') {
    $response = ['success' => $success];
    if ($message) $response['message'] = $message;
    if ($error) $response['error'] = $error;
    echo json_encode($response);
    exit;
}

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
    sendResponse(false, '', 'Invalid request method');
}

try {
    require_once __DIR__ . '/../../../includes/config.php';
    $configPath = __DIR__ . '/../../../includes/supabase.php';
    if (!file_exists($configPath)) {
        sendResponse(false, '', 'Supabase configuration not found');
    }
    $config = require $configPath;
    require_once __DIR__ . '/../../../src/lib/SupabaseClient.php';
    $supabase = new SupabaseClient($config['url'], $config['anon_key']);

    $applicationId = $_POST['application_id'] ?? '';
    $action = $_POST['action'] ?? '';
    
    if (empty($applicationId)) {
        sendResponse(false, '', 'Application ID is required');
    }

    $application = $supabase->select('pending_affiliations', ['id' => 'eq.' . $applicationId]);
    if (empty($application)) {
        sendResponse(false, '', 'Application not found');
    }
    $appData = $application[0];

    // ---------- VERIFY DOCUMENT ----------
    if ($action === 'verify_document') {
        $docKey = $_POST['doc_key'] ?? '';
        if (empty($docKey)) sendResponse(false, '', 'Document key is required');

        $documents = json_decode($appData['documents'] ?? '[]', true) ?: [];
        $documents[$docKey]['verified'] = true;

        $supabase->update('pending_affiliations', [
            'documents' => json_encode($documents),
            'updated_at' => date('Y-m-d H:i:s')
        ], $applicationId);

        sendResponse(true, 'Document verified successfully');
    }

    // ---------- APPROVE ----------
    if ($action === 'approve') {
        // Bypass RLS using Service Role Key
        $supabase->setServiceRoleKey($config['service_role_key']);
        
        // Initialize Email Service
        require_once __DIR__ . '/../../../src/lib/EmailService.php';
        $emailService = new \App\Lib\EmailService();

        // 1. CREATE/UPDATE SCHOOL OFFICER ACCOUNT
        $existingUsers = $supabase->select('users', ['email' => 'eq.' . $appData['email']]);
        $userId = null;
        $tempPassword = bin2hex(random_bytes(6));
        $passwordHash = password_hash($tempPassword, PASSWORD_BCRYPT);

        if (!empty($existingUsers)) {
            $userId = $existingUsers[0]['id'];
            $supabase->update('users', [
                'role' => 'school_officer',
                'password' => $passwordHash,
                'must_change_password' => 1,
                'updated_at' => date('Y-m-d H:i:s')
            ], $userId);

            $existingProfiles = $supabase->select('user_profiles', ['user_id' => 'eq.' . $userId]);
            if (empty($existingProfiles)) {
                $supabase->insert('user_profiles', [
                    'user_id' => $userId,
                    'role' => 'school_officer',
                    'full_name' => $appData['contact_person'],
                    'school_name' => $appData['institution_name'],
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        } else {
            $userId = generateUUID();
            $supabase->insert('users', [
                'id' => $userId,
                'email' => $appData['email'],
                'password' => $passwordHash,
                'full_name' => $appData['contact_person'],
                'role' => 'school_officer',
                'must_change_password' => 1,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            $supabase->insert('user_profiles', [
                'user_id' => $userId,
                'role' => 'school_officer',
                'full_name' => $appData['contact_person'],
                'school_name' => $appData['institution_name'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }

        // 2. AUTO-CREATE MEMBER ACCOUNTS FROM CSV OR XLSX
        $memberDirectoryPath = $appData['member_directory'] ?? null;
        $membersCreated = 0;
        $memberErrors = [];

        if ($memberDirectoryPath) {
            // Convert stored relative path to absolute filesystem path
            $localPath = str_replace('/IECEP-LSC-MEMSYS/public/', __DIR__ . '/../../', $memberDirectoryPath);
            
            if (file_exists($localPath)) {
                $fileExtension = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
                $memberRows = []; // will hold ['name'=>'...','email'=>'...']

                // ---- CSV handling ----
                if ($fileExtension === 'csv') {
                    if (($handle = fopen($localPath, "r")) !== FALSE) {
                        $rowNum = 0;
                        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                            $rowNum++;
                            if ($rowNum == 1) continue; // skip header
                            $name = trim($data[0] ?? '');
                            $email = trim($data[1] ?? '');
                            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                $memberRows[] = ['name' => $name ?: 'Member', 'email' => $email];
                            }
                        }
                        fclose($handle);
                    } else {
                        $memberErrors[] = "Could not open CSV file.";
                    }
                }
                // ---- XLSX handling using PhpSpreadsheet ----
                elseif ($fileExtension === 'xlsx') {
                    require_once __DIR__ . '/../../../vendor/autoload.php';
                    try {
                        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($localPath);
                        $worksheet = $spreadsheet->getActiveSheet();
                        $rows = $worksheet->toArray();
                        if (count($rows) > 1) {
                            for ($i = 1; $i < count($rows); $i++) {
                                $row = $rows[$i];
                                $name = trim($row[0] ?? '');
                                $email = trim($row[1] ?? '');
                                if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                    $memberRows[] = ['name' => $name ?: 'Member', 'email' => $email];
                                }
                            }
                        }
                    } catch (Exception $e) {
                        $memberErrors[] = "XLSX parsing error: " . $e->getMessage();
                    }
                } else {
                    $memberErrors[] = "Unsupported file type. Only .csv or .xlsx allowed.";
                }

                // Create accounts for each valid member row
                foreach ($memberRows as $index => $member) {
                    $memberName = $member['name'];
                    $memberEmail = $member['email'];
                    
                    $memberId = "MEM-" . date('Y') . "-" . str_pad(($index + 1), 4, '0', STR_PAD_LEFT);
                    $memberTempPass = bin2hex(random_bytes(4)); // 8 chars
                    $mUserId = generateUUID();
                    
                    // Check if user already exists (by email)
                    $existingMember = $supabase->select('users', ['email' => 'eq.' . $memberEmail]);
                    if (!empty($existingMember)) {
                        $memberErrors[] = "Email $memberEmail already registered. Skipped.";
                        continue;
                    }
                    
                    // Insert user
                    $userInsert = $supabase->insert('users', [
                        'id' => $mUserId,
                        'email' => $memberEmail,
                        'password' => password_hash($memberTempPass, PASSWORD_BCRYPT),
                        'full_name' => $memberName,
                        'role' => 'member',
                        'must_change_password' => 1,
                        'is_active' => 1,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    if (!$userInsert) {
                        $memberErrors[] = "Failed to create user for $memberEmail";
                        continue;
                    }
                    
                    // Insert profile
                    $profileInsert = $supabase->insert('user_profiles', [
                        'user_id' => $mUserId,
                        'role' => 'member',
                        'full_name' => $memberName,
                        'school_name' => $appData['institution_name'],
                        'member_id' => $memberId,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    if (!$profileInsert) {
                        $memberErrors[] = "Failed to create profile for $memberEmail";
                        continue;
                    }
                    
                    // Send welcome email
                    try {
                        $emailService->sendMemberWelcomeEmail($memberEmail, $memberName, $memberId, $memberTempPass);
                    } catch (Exception $e) {
                        $memberErrors[] = "Email not sent to $memberEmail: " . $e->getMessage();
                    }
                    
                    $membersCreated++;
                }
            } else {
                $memberErrors[] = "Member directory file not found at: $localPath";
            }
        }

        // 3. UPDATE APPLICATION STATUS
        $supabase->update('pending_affiliations', [
            'status' => 'approved',
            'approved_at' => date('Y-m-d H:i:s'),
            'portal_user_id' => $userId,
            'login_credentials_sent' => 1,
            'updated_at' => date('Y-m-d H:i:s')
        ], $applicationId);

        // 4. SEND SCHOOL OFFICER CREDENTIALS
        $emailService->sendSchoolAccountCredentials(
            $appData['email'], 
            $appData['institution_name'], 
            $tempPassword,
            $appData['contact_person'] ?? ''
        );

        $successMessage = "Application approved. School officer account created. $membersCreated member accounts created.";
        if (!empty($memberErrors)) {
            $successMessage .= " Warnings: " . implode('; ', $memberErrors);
        }
        sendResponse(true, $successMessage);
    }

    // ---------- REQUEST CHANGES ----------
    if ($action === 'request_changes') {
        if (empty($_POST['changes_instructions'])) sendResponse(false, '', 'Instructions required');
        $documents = json_decode($appData['documents'] ?? '[]', true) ?: [];
        $documents['changes_instructions'] = $_POST['changes_instructions'];
        $supabase->update('pending_affiliations', [
            'status' => 'changes_requested',
            'documents' => json_encode($documents),
            'updated_at' => date('Y-m-d H:i:s')
        ], $applicationId);
        
        require_once __DIR__ . '/../../../src/lib/EmailService.php';
        $emailService = new \App\Lib\EmailService();
        $emailService->sendChangesRequested($appData['email'], $appData['institution_name'], $_POST['changes_instructions']);
        
        sendResponse(true, 'Changes requested successfully.');
    }

    // ---------- REJECT ----------
    if ($action === 'reject') {
        if (empty($_POST['rejection_reason'])) sendResponse(false, '', 'Reason required');
        $supabase->update('pending_affiliations', [
            'status' => 'rejected',
            'rejection_reason' => $_POST['rejection_reason'],
            'updated_at' => date('Y-m-d H:i:s')
        ], $applicationId);
        
        require_once __DIR__ . '/../../../src/lib/EmailService.php';
        $emailService = new \App\Lib\EmailService();
        $emailService->sendAffiliationRejected($appData['email'], $appData['institution_name'], $_POST['rejection_reason']);
        
        sendResponse(true, 'Application rejected successfully.');
    }

} catch (Exception $e) {
    sendResponse(false, '', 'Server error: ' . $e->getMessage());
}