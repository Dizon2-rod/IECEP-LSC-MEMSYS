<?php
/**
 * Affiliate School Member Directory Upload API
 * 
 * Accepts Excel file upload with member data across 4 sheets
 * Parses, validates, and stores in database for admin review
 */

require_once __DIR__ . '/../../../includes/paths.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../portal/auth_check.php';

// Require school_officer role
require_role(['school_officer']);

// Set JSON response header
header('Content-Type: application/json');

// Check CSRF token
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
    exit;
}

// Check file upload
if (!isset($_FILES['directory_file']) || $_FILES['directory_file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['directory_file'];

// Validate file size (10 MB max)
$max_size = 10 * 1024 * 1024; // 10 MB
if ($file['size'] > $max_size) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File size exceeds 10 MB limit']);
    exit;
}

// Validate MIME type
$allowed_types = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'];
$file_mime = mime_content_type($file['tmp_name']);

if (!in_array($file_mime, $allowed_types)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only Excel (.xlsx, .xls) and CSV files are allowed']);
    exit;
}

try {
    // Check if phpoffice/phpspreadsheet is installed
    if (!class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
        throw new Exception('PhpSpreadsheet library not installed. Run: composer require phpoffice/phpspreadsheet');
    }

    $db = getDbConnection();
    
    // Get current user info from session
    $user_id = $_SESSION['user']['id'] ?? null;
    $school_name = $_SESSION['user']['school_name'] ?? 'Unknown School';
    $institution_id = $_SESSION['user']['institution_id'] ?? null;
    
    if (!$user_id || !$institution_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User institution information not found']);
        exit;
    }

    // Load spreadsheet
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
    
    // Define expected sheets
    $expected_sheets = ['1st Yr', '2nd Yr', '3rd Yr', '4th Yr'];
    
    // Create upload batch
    $batch_id = uniqid('batch_', true); // Unique batch ID
    $file_name = basename($file['name']);
    
    $stmt = $db->prepare("
        INSERT INTO upload_batches (id, institution_id, uploaded_by_user_id, file_name, status)
        VALUES (?, ?, ?, ?, 'pending')
    ");
    
    if (!$stmt->execute([$batch_id, $institution_id, $user_id, $file_name])) {
        throw new Exception('Failed to create upload batch');
    }

    $total_rows = 0;
    $import_errors = [];

    // Process each expected sheet
    foreach ($expected_sheets as $sheet_name) {
        $sheet = $spreadsheet->getSheetByName($sheet_name);
        if (!$sheet) {
            $import_errors[] = "Sheet '$sheet_name' not found";
            continue;
        }

        // Find header row (contains "Name" and "#")
        $header_row = null;
        $header_map = [];
        $max_row = $sheet->getHighestRow();

        for ($row = 1; $row <= min(10, $max_row); $row++) {
            $row_data = $sheet->getRowIterator($row, $row);
            foreach ($row_data as $r) {
                $cells = $r->getCellIterator();
                $found_name = false;
                $found_number = false;
                foreach ($cells as $cell) {
                    $value = strtolower(trim($cell->getValue() ?? ''));
                    if (strpos($value, 'name') !== false) $found_name = true;
                    if ($value === '#' || strpos($value, 'number') !== false) $found_number = true;
                }
                if ($found_name && $found_number) {
                    $header_row = $row;
                    break 2;
                }
            }
        }

        if ($header_row === null) {
            $import_errors[] = "Could not find header row in sheet '$sheet_name'";
            continue;
        }

        // Build header map from actual header row
        $header_cells = $sheet->getRowIterator($header_row, $header_row);
        foreach ($header_cells as $row) {
            $col_index = 1;
            foreach ($row->getCellIterator() as $cell) {
                $header_value = strtolower(trim($cell->getValue() ?? ''));
                // Map common variations
                if (in_array($header_value, ['#', 'number', 'member #'])) $header_map['number'] = $col_index;
                if (strpos($header_value, 'name') !== false) $header_map['name'] = $col_index;
                if (strpos($header_value, 'birthday') !== false || strpos($header_value, 'birthdate') !== false || strpos($header_value, 'dob') !== false) $header_map['birthday'] = $col_index;
                if (strpos($header_value, 'address') !== false) $header_map['address'] = $col_index;
                if (strpos($header_value, 'cellphone') !== false || strpos($header_value, 'phone') !== false || strpos($header_value, 'mobile') !== false) $header_map['phone'] = $col_index;
                if (strpos($header_value, 'email') !== false) $header_map['email'] = $col_index;
                if (strpos($header_value, 'picture') !== false || strpos($header_value, '1x1') !== false) $header_map['picture'] = $col_index;
                if (strpos($header_value, 'signature') !== false || strpos($header_value, 'e-signature') !== false) $header_map['signature'] = $col_index;
                if (strpos($header_value, 'id number') !== false || strpos($header_value, 'iecep id') !== false) $header_map['id_number'] = $col_index;
                $col_index++;
            }
        }

        // Process data rows
        $data_rows = $sheet->getRowIterator($header_row + 1);
        foreach ($data_rows as $row) {
            $cells = [];
            $col_index = 1;
            foreach ($row->getCellIterator() as $cell) {
                $cells[$col_index] = $cell->getValue();
                $col_index++;
            }

            // Skip empty rows
            if (empty(array_filter($cells))) {
                continue;
            }

            // Extract data from row
            $member_data = [
                'number' => $cells[$header_map['number'] ?? -1] ?? null,
                'name' => trim($cells[$header_map['name'] ?? -1] ?? ''),
                'birthday' => $cells[$header_map['birthday'] ?? -1] ?? null,
                'address' => trim($cells[$header_map['address'] ?? -1] ?? ''),
                'phone' => trim($cells[$header_map['phone'] ?? -1] ?? ''),
                'email' => strtolower(trim($cells[$header_map['email'] ?? -1] ?? '')),
                'picture' => trim($cells[$header_map['picture'] ?? -1] ?? ''),
                'signature' => trim($cells[$header_map['signature'] ?? -1] ?? ''),
                'existing_id' => $cells[$header_map['id_number'] ?? -1] ?? null,
            ];

            // Validate required fields
            $errors = [];
            if (empty($member_data['name'])) {
                $errors[] = 'Name is required';
            }
            if (empty($member_data['email'])) {
                $errors[] = 'Email is required';
            } elseif (!filter_var($member_data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format';
            }

            // Clean and validate phone
            $member_data['phone'] = clean_phone_number($member_data['phone']);
            if (!empty($member_data['phone']) && !validate_phone_number($member_data['phone'])) {
                $errors[] = 'Invalid phone number format (should be 11-13 digits)';
            }

            // Normalize birthday
            $birthday_result = normalize_birthday($member_data['birthday']);
            if ($birthday_result['error']) {
                $errors[] = 'Invalid birthday format: ' . $birthday_result['error'];
                $member_data['birthday'] = null;
            } else {
                $member_data['birthday'] = $birthday_result['date'];
            }

            // Check for invalid picture/signature
            $picture_errors = [];
            if (!empty($member_data['picture']) && has_invalid_file_indicator($member_data['picture'])) {
                $picture_errors[] = 'Picture has invalid data (broken link or #VALUE!)';
            }
            if (!empty($member_data['signature']) && has_invalid_file_indicator($member_data['signature'])) {
                $picture_errors[] = 'Signature has invalid data (broken link or #VALUE!)';
            }
            if (!empty($picture_errors)) {
                $errors[] = implode('; ', $picture_errors);
            }

            // Determine validity
            $is_valid = empty($errors);

            // Insert into membership_directory_imports
            $stmt = $db->prepare("
                INSERT INTO membership_directory_imports 
                (batch_id, sheet_name, row_index, name, birthday, address, phone, email, picture_url, signature_url, is_valid, validation_errors)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt->execute([
                $batch_id,
                $sheet_name,
                $total_rows + 1,
                $member_data['name'],
                $member_data['birthday'],
                $member_data['address'],
                $member_data['phone'],
                $member_data['email'],
                $member_data['picture'],
                $member_data['signature'],
                $is_valid ? 1 : 0,
                implode('; ', $errors)
            ])) {
                throw new Exception('Failed to insert import row: ' . $stmt->errorInfo()[2]);
            }

            $total_rows++;
        }
    }

    // Update batch with total rows
    $stmt = $db->prepare("UPDATE upload_batches SET total_rows = ? WHERE id = ?");
    $stmt->execute([$total_rows, $batch_id]);

    // Log audit
    log_audit('member_directory_upload', 'upload_batches', $batch_id, null, [
        'file' => $file_name,
        'total_rows' => $total_rows,
        'import_errors' => count($import_errors) > 0 ? $import_errors : null
    ]);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'batch_id' => $batch_id,
        'total_rows' => $total_rows,
        'import_errors' => count($import_errors) > 0 ? $import_errors : null,
        'message' => "Successfully uploaded $total_rows member records"
    ]);

} catch (Exception $e) {
    error_log("Member directory upload error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to process upload. Please check file format and try again.'
    ]);
    exit;
}

// Helper functions

function clean_phone_number($phone) {
    if (empty($phone)) return '';
    // Remove non-digits
    $cleaned = preg_replace('/\D/', '', $phone);
    return $cleaned;
}

function validate_phone_number($phone) {
    // Valid if 11-13 digits
    $digits = strlen(preg_replace('/\D/', '', $phone));
    return $digits >= 11 && $digits <= 13;
}

function normalize_birthday($birthday) {
    if (empty($birthday)) {
        return ['date' => null, 'error' => null];
    }

    $birthday = trim($birthday);

    // Try multiple formats
    $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'Y/m/d', 'Y-m-d H:i:s'];
    
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $birthday);
        if ($dt !== false && $dt->format($format) == $birthday) {
            return ['date' => $dt->format('Y-m-d'), 'error' => null];
        }
    }

    // Try PHP's strtotime as last resort
    $timestamp = @strtotime($birthday);
    if ($timestamp !== false) {
        $dt = new DateTime('@' . $timestamp);
        return ['date' => $dt->format('Y-m-d'), 'error' => null];
    }

    return ['date' => null, 'error' => 'Could not parse date format'];
}

function has_invalid_file_indicator($value) {
    if (empty($value)) return false;
    $value = strtoupper($value);
    
    // Check for common Excel error indicators
    $invalid_patterns = [
        '#VALUE!',
        '#REF!',
        '#NAME?',
        '#DIV/0!',
        '#N/A',
        '#NUM!',
        'ERROR',
        'GOOGLE.COM/FILE',
        'DOCS.GOOGLE.COM'
    ];
    
    foreach ($invalid_patterns as $pattern) {
        if (stripos($value, $pattern) !== false) {
            return true;
        }
    }
    
    return false;
}

function log_audit($action, $table_name, $record_id, $old_data = null, $new_data = null) {
    // Calls the audit.php function
    if (function_exists('log_audit')) {
        call_user_func('log_audit', $action, $table_name, $record_id, $old_data, $new_data);
    }
}

function getDbConnection() {
    // Get connection from config
    static $db = null;
    if ($db === null) {
        $db = new PDO(
            'pgsql:host=' . env('DB_HOST') . ';port=' . env('DB_PORT', 5432) . ';dbname=' . env('DB_NAME'),
            env('DB_USER'),
            env('DB_PASSWORD')
        );
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $db;
}
?>
