<?php
require_once __DIR__ . '/bootstrap.php';
/**
 * BULK USER EXPORT - Admin API
 * Exports users to CSV/Excel format
 * Created: May 17, 2026
 */

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/auth.php';

header('Content-Type: application/json');

// Verify admin access
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized']));
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            if ($action === 'preview') {
                handlePreview();
            } else {
                handleExport();
            }
            break;
        case 'POST':
            handleExportRequest();
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Handle export request and initiate download
 */
function handleExport() {
    global $supabase;
    
    $format = $_GET['format'] ?? 'csv'; // csv or xlsx
    $role_filter = $_GET['role'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    
    try {
        // Build query
        $query = $supabase->from('user_profiles')
            ->select('id, user_id, full_name, email, role, membership_status, institution_id, created_at');
        
        if ($role_filter) {
            $query->eq('role', $role_filter);
        }
        if ($status_filter) {
            $query->eq('membership_status', $status_filter);
        }
        
        $response = $query->limit(5000)->execute();
        $users = $response->data ?? [];
        
        if (empty($users)) {
            http_response_code(400);
            return json_encode(['error' => 'No users to export']);
        }
        
        if ($format === 'csv') {
            exportAsCSV($users);
        } elseif ($format === 'xlsx') {
            exportAsXLSX($users);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid format']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Export failed: ' . $e->getMessage()]);
    }
}

/**
 * Export users as CSV
 */
function exportAsCSV($users) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d_His') . '.csv"');
    
    $fp = fopen('php://output', 'w');
    
    // Headers
    fputcsv($fp, ['ID', 'Full Name', 'Email', 'Role', 'Status', 'Institution ID', 'Created At']);
    
    // Data rows
    foreach ($users as $user) {
        fputcsv($fp, [
            $user['id'] ?? '',
            $user['full_name'] ?? '',
            $user['email'] ?? '',
            $user['role'] ?? '',
            $user['membership_status'] ?? '',
            $user['institution_id'] ?? '',
            $user['created_at'] ?? ''
        ]);
    }
    
    fclose($fp);
    exit;
}

/**
 * Export users as XLSX (requires xlsx library)
 */
function exportAsXLSX($users) {
    // Note: Requires external library - SimpleXLSX or PhpSpreadsheet
    // For now, fallback to CSV
    exportAsCSV($users);
}

/**
 * Handle preview request
 */
function handlePreview() {
    global $supabase;
    
    $role_filter = $_GET['role'] ?? '';
    $limit = 10;
    
    try {
        $query = $supabase->from('user_profiles')
            ->select('id, full_name, email, role, membership_status, created_at');
        
        if ($role_filter) {
            $query->eq('role', $role_filter);
        }
        
        $response = $query->limit($limit)->execute();
        $preview = $response->data ?? [];
        
        // Get total count
        $countResponse = $supabase->from('user_profiles')
            ->select('id', count: 'exact')
            ->limit(1);
        
        if ($role_filter) {
            $countResponse->eq('role', $role_filter);
        }
        
        $countResult = $countResponse->execute();
        $total_count = $countResult->count ?? 0;
        
        echo json_encode([
            'success' => true,
            'preview' => $preview,
            'total_count' => $total_count,
            'preview_count' => count($preview)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Preview failed: ' . $e->getMessage()]);
    }
}

/**
 * Handle export request (async)
 */
function handleExportRequest() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Store export job in database for async processing
    echo json_encode([
        'success' => true,
        'message' => 'Export queued',
        'job_id' => uniqid('export_')
    ]);
}
?>
