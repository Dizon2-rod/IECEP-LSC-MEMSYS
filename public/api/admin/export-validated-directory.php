<?php
require_once __DIR__ . '/bootstrap.php';
/**
 * Export Validated Directory
 * 
 * GET endpoint for downloading validated member directory as Excel file
 * Includes all imported data with assigned membership IDs
 */

require_once __DIR__ . '/../../../includes/paths.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../portal/auth_check.php';

require_role(['admin', 'registration']);

// Don't set JSON header for file download
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_GET['csrf_token']) || !verify_csrf_token($_GET['csrf_token'])) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
    exit;
}

try {
    // Check if phpoffice/phpspreadsheet is installed
    if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        throw new Exception('PhpSpreadsheet library not installed');
    }

    $db = getDbConnection();
    $batch_id = $_GET['batch_id'] ?? null;

    if (!$batch_id) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'batch_id required']);
        exit;
    }

    // Fetch batch info
    $stmt = $db->prepare("SELECT * FROM upload_batches WHERE id = ?");
    $stmt->execute([$batch_id]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$batch) {
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Batch not found']);
        exit;
    }

    // Fetch all import rows for this batch grouped by sheet
    $stmt = $db->prepare("
        SELECT * FROM membership_directory_imports 
        WHERE batch_id = ? 
        ORDER BY sheet_name, row_index
    ");
    $stmt->execute([$batch_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Create new spreadsheet
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $spreadsheet->removeSheetByIndex(0); // Remove default sheet

    // Define sheets
    $sheets_data = [
        '1st Yr' => [],
        '2nd Yr' => [],
        '3rd Yr' => [],
        '4th Yr' => []
    ];

    // Group rows by sheet
    foreach ($rows as $row) {
        $sheet = $row['sheet_name'] ?? 'Other';
        if (!isset($sheets_data[$sheet])) {
            $sheets_data[$sheet] = [];
        }
        $sheets_data[$sheet][] = $row;
    }

    // Create sheets and populate data
    foreach ($sheets_data as $sheet_name => $sheet_rows) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($sheet_name);

        // Headers
        $headers = ['#', 'Name', 'Birthday', 'Address', 'Cellphone #', 'Email Address', '1x1 picture', 'e-signature', 'ID Number', 'Status'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
        }

        // Style header row
        $headerStyle = $sheet->getStyle('A1:J1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID);
        $headerStyle->getFill()->getStartColor()->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_LIGHTGRAY);

        // Populate data rows
        $row_num = 2;
        foreach ($sheet_rows as $data) {
            $sheet->setCellValueByColumnAndRow(1, $row_num, $data['member_number'] ?? '');
            $sheet->setCellValueByColumnAndRow(2, $row_num, $data['name'] ?? '');
            $sheet->setCellValueByColumnAndRow(3, $row_num, $data['birthday'] ?? '');
            $sheet->setCellValueByColumnAndRow(4, $row_num, $data['address'] ?? '');
            $sheet->setCellValueByColumnAndRow(5, $row_num, $data['phone'] ?? '');
            $sheet->setCellValueByColumnAndRow(6, $row_num, $data['email'] ?? '');
            $sheet->setCellValueByColumnAndRow(7, $row_num, $data['picture_url'] ?? '');
            $sheet->setCellValueByColumnAndRow(8, $row_num, $data['signature_url'] ?? '');
            $sheet->setCellValueByColumnAndRow(9, $row_num, $data['assigned_membership_id'] ?? '');
            
            // Status
            $status = $data['is_valid'] ? ($data['assigned_membership_id'] ? 'Assigned' : 'Pending') : 'Invalid';
            $sheet->setCellValueByColumnAndRow(10, $row_num, $status);

            $row_num++;
        }

        // Auto-size columns
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    // Generate filename
    $original_name = pathinfo($batch['file_name'], PATHINFO_FILENAME);
    $output_filename = "validated_" . $original_name . "_" . date('Y-m-d_His') . ".xlsx";

    // Output file
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $output_filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');

    // Log audit
    log_audit('member_directory_export', 'upload_batches', $batch_id, null, [
        'export_filename' => $output_filename
    ]);

    exit;

} catch (Exception $e) {
    header('Content-Type: application/json');
    error_log("Export validated directory error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to generate export file'
    ]);
    exit;
}

function log_audit($action, $table_name, $record_id, $old_data = null, $new_data = null) {
    if (function_exists('log_audit')) {
        call_user_func('log_audit', $action, $table_name, $record_id, $old_data, $new_data);
    }
}

function getDbConnection() {
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
