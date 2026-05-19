<?php
require_once __DIR__ . '/bootstrap.php';
/**
 * public/api/admin/bulk-import.php
 * 
 * API endpoint for bulk user import
 * Handles CSV file upload and user creation
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../portal/auth_check.php';
require_once __DIR__ . '/../../includes/paths.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/helpers/validation.php';
require_once __DIR__ . '/../../src/lib/Supabase.php';

// Enforce admin role
if (!require_role(['admin'], false)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

// CSRF validation
require_csrf();

if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];
$user_id = $_SESSION['user']['id'] ?? null;

try {
    // Validate file upload
    $allowed_mimes = ['text/csv', 'text/plain', 'application/csv'];
    if (!validate_file_upload($file, $allowed_mimes, 5242880)) {
        throw new Exception('Invalid file. Please upload a CSV file under 5MB.');
    }
    
    // Parse CSV
    $csv = validate_csv_structure($file['tmp_name'], ['email', 'full_name', 'role']);
    if (!$csv) {
        throw new Exception('Invalid CSV structure. Required columns: email, full_name, role');
    }
    
    $supabase = new \App\Lib\Supabase();
    $import_batch_id = bin2hex(random_bytes(16));
    
    $imported = 0;
    $failed = 0;
    $errors = [];
    
    // Create batch record
    $batch_result = $supabase->from('temp_user_imports')->insert([
        'import_batch_id' => $import_batch_id,
        'email' => 'BATCH_MARKER',
        'full_name' => 'Batch Import',
        'role' => 'system',
        'status' => 'pending',
        'created_at' => date('c')
    ], true);
    
    if ($batch_result['error']) {
        throw new Exception('Failed to create import batch');
    }
    
    // Process each row
    foreach ($csv['data'] as $idx => $row) {
        $email = validate_email($row['email'] ?? '');
        $full_name = validate_string($row['full_name'] ?? '', 2, 255);
        $role = validate_string($row['role'] ?? '', 1, 50);
        
        $status = 'pending';
        $error_msg = null;
        
        // Validate
        if (!$email) {
            $status = 'failed';
            $error_msg = 'Invalid email format';
            $failed++;
        } elseif (!$full_name) {
            $status = 'failed';
            $error_msg = 'Invalid name (2-255 characters)';
            $failed++;
        } elseif (!$role) {
            $status = 'failed';
            $error_msg = 'Invalid role';
            $failed++;
        } else {
            // Check if email already exists
            $existing = $supabase->from('user_profiles')
                ->select('id')
                ->eq('email', $email)
                ->limit(1)
                ->get(true);
            
            if (!$existing['error'] && !empty($existing['data'])) {
                $status = 'failed';
                $error_msg = 'User with this email already exists';
                $failed++;
            } else {
                $status = 'pending';
                $imported++;
            }
        }
        
        // Store in temp table
        $import_result = $supabase->from('temp_user_imports')->insert([
            'import_batch_id' => $import_batch_id,
            'email' => $email ?: $row['email'],
            'full_name' => $full_name ?: $row['full_name'],
            'role' => $role ?: $row['role'],
            'status' => $status,
            'error_message' => $error_msg,
            'created_at' => date('c')
        ], true);
        
        if ($import_result['error']) {
            error_log("Import row error: " . json_encode($import_result));
        }
    }
    
    // Log audit event
    if (function_exists('audit_log')) {
        audit_log(
            'USER_BULK_IMPORT',
            'user_profiles',
            $import_batch_id,
            [],
            ['batch_id' => $import_batch_id, 'total' => count($csv['data']), 'ready' => $imported],
            "Bulk import: $imported ready, $failed errors"
        );
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => "Import processed: $imported ready to import, $failed errors",
        'data' => [
            'batch_id' => $import_batch_id,
            'imported' => $imported,
            'failed' => $failed,
            'total' => count($csv['data']),
            'errors' => $errors
        ]
    ]);
    
    // Clean up uploaded file
    unlink($file['tmp_name']);
    
} catch (Exception $e) {
    error_log("Bulk import error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
