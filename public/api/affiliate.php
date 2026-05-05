<?php
// Suppress PHP errors to prevent HTML warnings in JSON response
error_reporting(0);
ini_set('display_errors', 0);

// Clean any existing output buffer
if (ob_get_length()) ob_clean();

// Start output buffer to catch any unwanted output
ob_start();

// Ensure JSON response only
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Start session for verification storage
session_start();

require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../../includes/supabase.php';
require_once __DIR__ . '/../../includes/paths.php';

$emailService = new EmailService();
$action = $_GET['action'] ?? '';

if ($action === 'send-code') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';

    error_log("Send-code request received for email: $email");

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit;
    }

    try {
        // Check if email already exists in pending_affiliations
        require_once __DIR__ . '/../../src/lib/SupabaseClient.php';
        $config = require __DIR__ . '/../../includes/supabase.php';
        $supabase = new SupabaseClient($config['url'], $config['anon_key']);

        $existingEmail = $supabase->select('pending_affiliations', ['email' => 'eq.' . $email]);
        if (!empty($existingEmail) && is_array($existingEmail)) {
            $existingApp = $existingEmail[0];
            $status = $existingApp['status'] ?? '';

            if ($status === 'approved') {
                echo json_encode([
                    'success' => false,
                    'message' => 'This email is already registered with an approved affiliation. Please use a different email or contact support.',
                    'email_exists' => true,
                    'status' => 'approved'
                ]);
                exit;
            } else {
                // Email exists but not approved - provide the resubmit link
                echo json_encode([
                    'success' => false,
                    'message' => 'This email is already registered with a pending application.',
                    'email_exists' => true,
                    'resubmit_available' => true,
                    'application_id' => $existingApp['id'],
                    'current_status' => $status
                ]);
                exit;
            }
        }

        // Generate 6-digit code
        $code = sprintf("%06d", mt_rand(0, 999999));

        // Store in session for immediate verification
        $_SESSION['verification_code'] = $code;
        $_SESSION['verification_email'] = $email;
        $_SESSION['code_sent_time'] = time();

        error_log("Generated code: $code for email: $email");
        error_log("SMTP Config - Host: " . SMTP_HOST . ", Port: " . SMTP_PORT . ", User: " . SMTP_USERNAME);

        // Send email using EmailService
        $sent = $emailService->sendVerificationCode($email, $code);

        error_log("Email send result: " . ($sent ? 'SUCCESS' : 'FAILED'));

        if ($sent) {
            echo json_encode(['success' => true, 'message' => 'Verification code sent successfully']);
        } else {
            // Return code in response for testing since email is not configured
            error_log("Email failed, returning code in response for testing: $code");
            echo json_encode(['success' => true, 'message' => "Verification code: $code (Email not configured - use this code for testing)", 'code' => $code]);
        }

    } catch (Exception $e) {
        error_log("Send code error: " . $e->getMessage());
        error_log("Send code error trace: " . $e->getTraceAsString());
        echo json_encode(['success' => false, 'message' => 'Server error occurred: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'verify-code') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    $code = $input['code'] ?? '';

    if (empty($email) || empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Email and code are required']);
        exit;
    }

    try {
        error_log("Verifying code: $code for email: $email");

        // Check session-based verification
        if (isset($_SESSION['verification_code']) &&
            isset($_SESSION['verification_email']) &&
            time() - $_SESSION['code_sent_time'] < 600) { // 10 minutes expiry

            if ($_SESSION['verification_code'] === $code && $_SESSION['verification_email'] === $email) {
                // Clear session
                unset($_SESSION['verification_code']);
                unset($_SESSION['verification_email']);
                unset($_SESSION['code_sent_time']);

                echo json_encode(['success' => true, 'message' => 'Email verified successfully']);
                exit;
            }
        }

        echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code']);

    } catch (Exception $e) {
        error_log("Verify code error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error occurred']);
    }
    exit;
}

if ($action === 'submit') {
    error_log("Submit action received");

    // Handle FormData submission
    $contactEmail = $_POST['contact_email'] ?? '';
    $institutionName = $_POST['institution_name'] ?? '';
    $institutionAddress = $_POST['institution_address'] ?? '';
    $contactName = $_POST['contact_name'] ?? '';
    $contactPosition = $_POST['contact_position'] ?? '';
    $contactPhone = $_POST['contact_phone'] ?? '';
    $resubmitId = $_POST['resubmit_id'] ?? '';

    error_log("Form data - Email: $contactEmail, Institution: $institutionName, Resubmit ID: $resubmitId");

    if (empty($institutionName) || empty($institutionAddress) || empty($contactName) || empty($contactPhone) || empty($contactEmail)) {
        error_log("Validation failed - missing required fields");
        echo json_encode(['success' => false, 'error' => 'All required fields must be filled']);
        exit;
    }

    try {
        error_log("Affiliation application received from: $institutionName");

        // Load Supabase configuration
        require_once __DIR__ . '/../../src/lib/SupabaseClient.php';
        $config = require __DIR__ . '/../../includes/supabase.php';
        $supabase = new SupabaseClient($config['url'], $config['anon_key']);

        // Prepare documents array
        $documents = [];

        // Handle file uploads
        $documentFields = [
            'letter_of_intent' => ['name' => 'Letter of Intent', 'allowed' => ['application/pdf']],
            'endorsement_letter' => ['name' => 'Endorsement Letter', 'allowed' => ['application/pdf']],
            'constitution_by_laws' => ['name' => 'Constitution and By-Laws', 'allowed' => ['application/pdf']],
            'officers_cvs' => ['name' => 'List of Officers with CVs', 'allowed' => ['application/pdf']],
            'organizational_chart' => ['name' => 'Organizational Chart', 'allowed' => ['application/pdf']],
            'member_directory' => ['name' => 'Member Directory', 'allowed' => ['application/pdf', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv']]
        ];

        // Load existing documents if resubmitting
        $existingDocuments = [];
        if (!empty($resubmitId)) {
            try {
                $existingApp = $supabase->select('pending_affiliations', ['id' => 'eq.' . $resubmitId]);
                if (!empty($existingApp) && is_array($existingApp)) {
                    $existingDocs = json_decode($existingApp[0]['documents'], true) ?: [];
                    $existingDocuments = $existingDocs;
                    error_log("Loaded existing documents for resubmission: " . count($existingDocuments));
                } else {
                    error_log("No existing application found for resubmit ID: $resubmitId");
                }
            } catch (Exception $e) {
                error_log("Error loading existing documents: " . $e->getMessage());
            }
        }

        foreach ($documentFields as $fieldName => $fieldInfo) {
            if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$fieldName];
                $fileType = $file['type'];
                $fileName = $file['name'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                error_log("Processing file: $fieldName, Name: $fileName, Type: $fileType, Extension: $fileExtension");

                // Validate file type
                $allowedTypes = $fieldInfo['allowed'];
                $allowedExtensions = [];

                foreach ($allowedTypes as $type) {
                    if ($type === 'application/pdf') {
                        $allowedExtensions[] = 'pdf';
                    } elseif ($type === 'application/vnd.ms-excel') {
                        $allowedExtensions[] = 'xls';
                    } elseif ($type === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
                        $allowedExtensions[] = 'xlsx';
                    } elseif ($type === 'text/csv') {
                        $allowedExtensions[] = 'csv';
                    }
                }

                error_log("Allowed extensions for $fieldName: " . implode(', ', $allowedExtensions));

                if (!in_array($fileExtension, $allowedExtensions)) {
                    error_log("File type validation failed for $fieldName");
                    echo json_encode(['success' => false, 'error' => $fieldInfo['name'] . ' must be one of: ' . implode(', ', $allowedExtensions)]);
                    exit;
                }

                // Check file size (max 10MB)
                if ($file['size'] > 10 * 1024 * 1024) {
                    error_log("File size validation failed for $fieldName");
                    echo json_encode(['success' => false, 'error' => $fieldInfo['name'] . ' must be less than 10MB']);
                    exit;
                }

                $fileContent = file_get_contents($file['tmp_name']);
                $base64Content = base64_encode($fileContent);

                $documents[$fieldName] = [
                    'name' => $file['name'],
                    'type' => $file['type'],
                    'size' => $file['size'],
                    'content' => $base64Content
                ];

                error_log("File processed successfully: $fieldName");
            } else {
                // If resubmitting and no new file uploaded, keep existing document
                if (!empty($resubmitId) && isset($existingDocuments[$fieldName])) {
                    $documents[$fieldName] = $existingDocuments[$fieldName];
                    error_log("Keeping existing document for $fieldName");
                }
                error_log("File not uploaded or error for $fieldName: " . ($_FILES[$fieldName]['error'] ?? 'not set'));
            }
        }

        error_log("Total documents processed: " . count($documents));

        // Check if email already exists (only for new submissions, not resubmissions)
        if (empty($resubmitId)) {
            $existingEmail = $supabase->select('pending_affiliations', ['email' => 'eq.' . $contactEmail]);
            if (!empty($existingEmail) && is_array($existingEmail)) {
                $existingApp = $existingEmail[0];
                $status = $existingApp['status'] ?? '';

                if ($status === 'approved') {
                    echo json_encode(['success' => false, 'error' => 'This email is already registered with an approved affiliation. Please use a different email or contact support.']);
                    exit;
                } else {
                    // Email exists but not approved - provide the resubmit link
                    echo json_encode([
                        'success' => false,
                        'error' => 'You already have a pending affiliation application with this email.',
                        'resubmit_available' => true,
                        'application_id' => $existingApp['id'],
                        'current_status' => $status
                    ]);
                    exit;
                }
            }
        }

        // Prepare application data
        $applicationData = [
            'institution_name' => $institutionName,
            'address' => $institutionAddress,
            'contact_person' => $contactName,
            'contact_position' => $contactPosition,
            'contact_phone' => $contactPhone,
            'email' => $contactEmail,
            'documents' => json_encode($documents),
            'submitted_at' => date('c'),
            'created_at' => date('c'),
            'updated_at' => date('c'),
            'status' => 'pending'
        ];

        // Handle resubmission or new submission
        if (!empty($resubmitId)) {
            // Update existing record
            $applicationData['resubmitted_at'] = date('c');
            $applicationData['status'] = 'resubmitted';
            // Remove changes_instructions from documents
            if (!empty($existingDocuments['changes_instructions'])) {
                unset($existingDocuments['changes_instructions']);
            }
            $applicationData['documents'] = json_encode(array_merge($existingDocuments, $documents));

            $result = $supabase->update('pending_affiliations', $applicationData, $resubmitId);

            if ($result) {
                // Send notification to registration committee about resubmission
                $emailService->sendAffiliationResubmitted($contactEmail, $institutionName, $resubmitId);

                echo json_encode(['success' => true, 'message' => 'Application resubmitted successfully. The Registration Committee will review your updated application within 3-5 business days.']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update application']);
            }
        } else {
            // Insert new record
            $result = $supabase->insert('pending_affiliations', $applicationData);

            if ($result) {
                // Send confirmation email
                $emailService->sendAffiliationConfirmation($contactEmail, $institutionName);

                echo json_encode(['success' => true, 'message' => 'Application submitted successfully. The Registration Committee will review your application within 3-5 business days.']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to save application to database']);
            }
        }
    } catch (Exception $e) {
        error_log("Submit application error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Server error occurred: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);

// Clean buffer and send output
ob_end_flush();
?>
