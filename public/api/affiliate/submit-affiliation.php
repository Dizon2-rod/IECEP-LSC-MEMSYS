<?php
require_once __DIR__ . '/bootstrap.php';
/**
 * Submit Affiliation API
 * Handles form submission, file uploads, and Excel parsing
 */

require_once __DIR__ . '/../../../includes/paths.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/csrf.php';

// Require PhpSpreadsheet
require_once __DIR__ . '/../../../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// CSRF validation
if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
    exit;
}

try {
    $db = getDbConnection();
    
    // Validate required fields
    $school_name = trim($_POST['school_name'] ?? '');
    $org_name = trim($_POST['org_name'] ?? '');
    $rep_name = trim($_POST['rep_name'] ?? '');
    $rep_email = trim($_POST['rep_email'] ?? '');
    
    if (empty($school_name) || empty($org_name) || empty($rep_name) || empty($rep_email)) {
        throw new Exception('All fields are required');
    }
    
    if (!filter_var($rep_email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }
    
    // Validate all 6 files are uploaded
    $required_files = [
        'letter_of_intent',
        'endorsement_letter',
        'constitution_bylaws',
        'officers_cv',
        'org_chart',
        'member_directory'
    ];
    
    foreach ($required_files as $file_key) {
        if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Missing or invalid file: $file_key");
        }
    }
    
    // Validate file sizes (max 10MB)
    foreach ($required_files as $file_key) {
        if ($_FILES[$file_key]['size'] > 10 * 1024 * 1024) {
            throw new Exception("File too large: $file_key (max 10MB)");
        }
    }
    
    // Validate file types
    $allowed_types = [
        'letter_of_intent' => ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'endorsement_letter' => ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'constitution_bylaws' => ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'officers_cv' => ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'org_chart' => ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/png', 'image/jpeg'],
        'member_directory' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel']
    ];
    
    foreach ($required_files as $file_key) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES[$file_key]['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime, $allowed_types[$file_key])) {
            throw new Exception("Invalid file type for $file_key");
        }
    }
    
    // Start transaction
    $db->beginTransaction();
    
    // Create application record
    $stmt = $db->prepare("
        INSERT INTO affiliation_applications (school_name, org_name, rep_name, rep_email, status, submitted_at)
        VALUES (?, ?, ?, ?, 'pending', NOW())
        RETURNING id
    ");
    $stmt->execute([$school_name, $org_name, $rep_name, $rep_email]);
    $application_id = $stmt->fetchColumn();
    
    // Create upload directory
    $upload_dir = __DIR__ . '/../../../storage/affiliations/' . $application_id;
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Upload and save each file
    foreach ($required_files as $file_key) {
        $file = $_FILES[$file_key];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $file_key . '_' . time() . '.' . $ext;
        $file_path = $upload_dir . '/' . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            throw new Exception("Failed to upload file: $file_key");
        }
        
        // Save to affiliation_documents table
        $stmt = $db->prepare("
            INSERT INTO affiliation_documents (application_id, document_type, filename, file_path, file_size, mime_type, uploaded_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file_path);
        finfo_close($finfo);
        
        $stmt->execute([
            $application_id,
            $file_key,
            $file['name'],
            $file_path,
            $file['size'],
            $mime
        ]);
    }
    
    // Parse Member Directory Excel
    $excel_file = $upload_dir . '/' . $file_key . '_' . time() . '.' . pathinfo($_FILES['member_directory']['name'], PATHINFO_EXTENSION);
    $spreadsheet = IOFactory::load($excel_file);
    
    $required_sheets = ['1st Yr', '2nd Yr', '3rd Yr', '4th Yr'];
    $sheet_names = $spreadsheet->getSheetNames();
    
    // Validate sheets exist
    foreach ($required_sheets as $required_sheet) {
        if (!in_array($required_sheet, $sheet_names)) {
            throw new Exception("Missing required sheet: $required_sheet");
        }
    }
    
    // Parse each sheet
    $total_rows = 0;
    foreach ($required_sheets as $sheet_name) {
        $worksheet = $spreadsheet->getSheetByName($sheet_name);
        $rows = $worksheet->toArray();
        
        // Skip header row
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            
            // Skip empty rows
            if (empty(array_filter($row))) continue;
            
            $full_name = trim($row[1] ?? '');
            $birthday = trim($row[2] ?? '');
            $address = trim($row[3] ?? '');
            $phone = trim($row[4] ?? '');
            $email = trim($row[5] ?? '');
            $picture = trim($row[6] ?? '');
            $signature = trim($row[7] ?? '');
            
            // Validate row
            $is_valid = true;
            $errors = [];
            
            if (empty($full_name)) {
                $is_valid = false;
                $errors[] = 'Name is required';
            }
            
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $is_valid = false;
                $errors[] = 'Valid email is required';
            }
            
            // Parse birthday
            $birthday_clean = null;
            if (!empty($birthday)) {
                try {
                    $date = new DateTime($birthday);
                    $birthday_clean = $date->format('Y-m-d');
                } catch (Exception $e) {
                    $errors[] = 'Invalid birthday format';
                }
            }
            
            // Insert into member_directory_imports
            $stmt = $db->prepare("
                INSERT INTO member_directory_imports (
                    application_id, sheet_name, row_index, full_name, birthday_clean, 
                    address, phone, email, picture_raw, signature_raw, 
                    is_valid, validation_errors
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $application_id,
                $sheet_name,
                $i,
                $full_name,
                $birthday_clean,
                $address,
                $phone,
                $email,
                $picture,
                $signature,
                $is_valid,
                implode('; ', $errors)
            ]);
            
            $total_rows++;
        }
    }
    
    // Commit transaction
    $db->commit();
    
    // Log audit
    log_audit('affiliation_submitted', 'affiliation_applications', $application_id, null, [
        'school_name' => $school_name,
        'org_name' => $org_name,
        'total_members' => $total_rows
    ]);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Application submitted successfully! You will be notified once reviewed.',
        'application_id' => $application_id,
        'total_members' => $total_rows
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("Submit affiliation error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
