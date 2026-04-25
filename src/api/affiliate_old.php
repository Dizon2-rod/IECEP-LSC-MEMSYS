<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../lib/supabase.php';
require_once __DIR__ . '/../lib/EmailService.php';

use App\Lib\Supabase;
use App\Lib\EmailService;

$sb = new Supabase();
$email = new EmailService();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Helper function to get allowed MIME types for each document type
function getAllowedTypes($docKey) {
    $types = [
        'letter_of_intent' => ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/msword', 'image/jpeg', 'image/png'],
        'endorsement_letter' => ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/msword', 'image/jpeg', 'image/png'],
        'constitution_by_laws' => ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/msword'],
        'officers_cvs' => ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/msword'],
        'organizational_chart' => ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/msword', 'image/jpeg', 'image/png'],
        'member_directory' => ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/msword', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv']
    ];
    
    return $types[$docKey] ?? ['application/pdf'];
}

try {
    switch ($action) {
        case 'send-code':
            if ($method !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }
            $data = json_decode(file_get_contents('php://input'), true);
            $emailAddr = $data['email'] ?? '';

            if (empty($emailAddr) || !filter_var($emailAddr, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['error' => 'Valid email address required']);
                exit;
            }

            // Check if already affiliated or pending
            $existingInst = $sb->from('institutions')->select('id')->eq('email', $emailAddr)->get(true);
            $existingPending = $sb->from('pending_affiliations')->select('id')->eq('email', $emailAddr)->eq('status', 'pending_review')->get(true);

            if (!$existingInst['error'] && !empty($existingInst['data'])) {
                http_response_code(409);
                echo json_encode(['error' => 'This email is already registered as an affiliated institution']);
                exit;
            }
            if (!$existingPending['error'] && !empty($existingPending['data'])) {
                http_response_code(409);
                echo json_encode(['error' => 'A pending application already exists for this email']);
                exit;
            }

            // Generate 6-digit code
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = date('Y-m-d\TH:i:s\Z', time() + 600); // 10 minutes

            // Delete any existing codes for this email
            $sb->from('email_verifications')->eq('email', $emailAddr)->delete(true);

            // Insert new code
            $sb->from('email_verifications')->insert([
                'email' => $emailAddr,
                'code' => $code,
                'expires_at' => $expiresAt,
                'verified' => false,
            ], true);

            // Send email
            $sent = $email->sendVerificationCode($emailAddr, $code);
            if (!$sent) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to send verification email']);
                exit;
            }

            echo json_encode(['success' => true, 'message' => 'Verification code sent to ' . $emailAddr]);
            break;

        case 'verify-code':
            if ($method !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }
            $data = json_decode(file_get_contents('php://input'), true);
            $emailAddr = $data['email'] ?? '';
            $code = $data['code'] ?? '';

            if (empty($emailAddr) || empty($code)) {
                http_response_code(400);
                echo json_encode(['error' => 'Email and code required']);
                exit;
            }

            $result = $sb->from('email_verifications')
                ->select('*')
                ->eq('email', $emailAddr)
                ->eq('code', $code)
                ->eq('verified', 'false')
                ->order('created_at', false)
                ->limit(1)
                ->get(true);

            if ($result['error'] || empty($result['data'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid verification code']);
                exit;
            }

            $record = $result['data'][0];
            $expiresAt = strtotime($record['expires_at']);
            if (time() > $expiresAt) {
                http_response_code(400);
                echo json_encode(['error' => 'Code has expired. Please request a new one.']);
                exit;
            }

            // Mark as verified
            $sb->from('email_verifications')
                ->eq('id', $record['id'])
                ->update(['verified' => true], true);

            // Generate a temporary token for the submission step
            $token = bin2hex(random_bytes(32));
            echo json_encode(['success' => true, 'verified' => true, 'token' => $token, 'email' => $emailAddr]);
            break;

        case 'submit':
            if ($method !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }

            // Handle multipart form data
            $emailAddr = $_POST['contact_email'] ?? '';
            $institutionName = $_POST['institution_name'] ?? '';
            $address = $_POST['institution_address'] ?? '';
            $contactName = $_POST['contact_name'] ?? '';
            $contactPosition = $_POST['contact_position'] ?? '';
            $contactPhone = $_POST['contact_phone'] ?? '';
            $terms = $_POST['terms'] ?? '';

            // Validate required fields
            if (empty($emailAddr) || empty($institutionName) || empty($address) || 
                empty($contactName) || empty($contactPosition) || empty($terms)) {
                http_response_code(400);
                echo json_encode(['error' => 'All required fields must be filled']);
                exit;
            }

            // Verify email was verified
            $verifyResult = $sb->from('email_verifications')
                ->select('*')
                ->eq('email', $emailAddr)
                ->eq('verified', true)
                ->order('created_at', false)
                ->limit(1)
                ->get(true);

            if ($verifyResult['error'] || empty($verifyResult['data'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Email not verified. Please verify first.']);
                exit;
            }

            // Required documents
            $requiredDocs = [
                'letter_of_intent' => 'Letter of Intent',
                'endorsement_letter' => 'Endorsement Letter',
                'constitution_by_laws' => 'Constitution and By-Laws',
                'officers_cvs' => 'List of Officers with CVs',
                'organizational_chart' => 'Organizational Chart',
                'member_directory' => 'Member Directory'
            ];

            $uploadedDocuments = [];
            $missingDocuments = [];

            // Process each required document
            foreach ($requiredDocs as $docKey => $docName) {
                if (!isset($_FILES[$docKey]) || $_FILES[$docKey]['error'] !== UPLOAD_ERR_OK) {
                    $missingDocuments[] = $docName;
                    continue;
                }

                $file = $_FILES[$docKey];
                
                // Validate file size (10MB max)
                if ($file['size'] > 10 * 1024 * 1024) {
                    http_response_code(400);
                    echo json_encode(['error' => "File '{$file['name']}' exceeds 10MB limit"]);
                    exit;
                }

                // Validate file type based on document
                $allowedTypes = getAllowedTypes($docKey);
                $fileType = mime_content_type($file['tmp_name']);
                
                if (!in_array($fileType, $allowedTypes)) {
                    http_response_code(400);
                    echo json_encode(['error' => "Invalid file type for {$docName}. Allowed: " . implode(', ', array_map(function($type) {
                        return str_replace(['application/', 'image/'], '', $type);
                    }, $allowedTypes))]);
                    exit;
                }

                // Upload to Supabase Storage
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $timestamp = time();
                $storagePath = "{$emailAddr}_{$timestamp}/{$docKey}.{$ext}";

                $uploadResult = $sb->storage()->uploadBinary(
                    'pending_affiliations',
                    $storagePath,
                    file_get_contents($file['tmp_name']),
                    $fileType
                );

                if ($uploadResult['error']) {
                    http_response_code(500);
                    echo json_encode(['error' => "Failed to upload {$docName}", 'details' => $uploadResult['message']]);
                    exit;
                }

                $publicUrl = $sb->storage()->getPublicUrl('pending_affiliations', $storagePath);
                
                $uploadedDocuments[] = [
                    'type' => $docKey,
                    'name' => $docName,
                    'original_name' => $file['name'],
                    'url' => $publicUrl,
                    'storage_path' => $storagePath,
                    'size' => $file['size'],
                    'mime_type' => $fileType
                ];
            }

            // Check if any required documents are missing
            if (!empty($missingDocuments)) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required documents: ' . implode(', ', $missingDocuments)]);
                exit;
            }

            // Prepare contact person data
            $contactPerson = [
                'name' => $contactName,
                'position' => $contactPosition,
                'phone' => $contactPhone
            ];

            // Set validity dates (1 year from current date)
            $validFrom = date('Y-m-d');
            $validUntil = date('Y-m-d', strtotime('+1 year'));

            // Insert pending affiliation
            $result = $sb->from('pending_affiliations')->insert([
                'email' => $emailAddr,
                'institution_name' => $institutionName,
                'address' => $address,
                'contact_person' => json_encode($contactPerson),
                'documents' => json_encode($uploadedDocuments),
                'status' => 'pending_review',
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
            ], true);

            if ($result['error']) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to submit application', 'details' => $result['message']]);
                exit;
            }

            // Send confirmation email to applicant
            $email->sendAffiliationConfirmation($emailAddr, $institutionName);

            // Notify registration committee members
            $committeeMembers = $sb->from('user_profiles')
                ->select('user_id')
                ->eq('role', 'committee_registration')
                ->get(true);

            if (!$committeeMembers['error'] && !empty($committeeMembers['data'])) {
                $memberEmails = $sb->from('members')
                    ->select('email')
                    ->in('user_id', array_column($committeeMembers['data'], 'user_id'))
                    ->get(true);

                if (!$memberEmails['error'] && !empty($memberEmails['data'])) {
                    foreach ($memberEmails['data'] as $cm) {
                        $email->sendNotification($cm['email'], 'New Affiliation Application', 
                            "A new affiliation application has been submitted by <strong>{$institutionName}</strong>. Please review the submitted documents in the admin portal.");
                    }
                }
            }

            echo json_encode([
                'success' => true, 
                'message' => 'Affiliation request submitted successfully! Our team will review your application within 3-5 business days.'
            ]);
            break;

        case 'upload-document':
            if ($method !== 'POST') { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit; }

            if (!isset($_FILES['file'])) {
                http_response_code(400);
                echo json_encode(['error' => 'No file uploaded']);
                exit;
            }

            $file = $_FILES['file'];
            $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
            $maxSize = 10 * 1024 * 1024; // 10MB

            if (!in_array($file['type'], $allowedTypes)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid file type. Only PDF, JPEG, PNG allowed.']);
                exit;
            }

            if ($file['size'] > $maxSize) {
                http_response_code(400);
                echo json_encode(['error' => 'File too large. Maximum 10MB.']);
                exit;
            }

            $emailAddr = $_POST['email'] ?? '';
            $docType = $_POST['doc_type'] ?? 'document';

            if (empty($emailAddr)) {
                http_response_code(400);
                echo json_encode(['error' => 'Email required']);
                exit;
            }

            // Upload to Supabase Storage
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $storagePath = "{$emailAddr}/{$docType}_" . time() . ".{$ext}";

            $uploadResult = $sb->storage()->upload('pending_affiliations', $storagePath, $file['tmp_name'], $file['type']);

            if ($uploadResult['error']) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to upload file', 'details' => $uploadResult['message']]);
                exit;
            }

            $publicUrl = $sb->storage()->getPublicUrl('pending_affiliations', $storagePath);

            echo json_encode([
                'success' => true,
                'doc_type' => $docType,
                'url' => $publicUrl,
                'storage_path' => $storagePath,
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
