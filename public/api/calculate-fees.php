<?php
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

require_once __DIR__ . '/bootstrap.php';
/**
 * Calculate Affiliation Fees
 * POST /api/calculate-fees.php
 * 
 * Validates uploaded member directory file and calculates total affiliation fees.
 * Server-side logic ensures no client tampering with fee values.
 * 
 * SOURCE: Deliverable 3.1 - Calculate Fees API
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

// Load helpers
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../src/lib/SupabaseClient.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// Get Supabase client
$config = require __DIR__ . '/../../includes/supabase.php';
$supabase = new SupabaseClient($config['url'], $config['anon_key']);

// Handle GET request for synchronized settings/rates
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    if ($action === 'get_settings' || $action === 'rates') {
        $settings = \App\Lib\SettingsService::getInstance($supabase);
        echo json_encode([
            'success' => true,
            'settings' => $settings->getAllSettingsForSync(),
            'fee_brackets' => $settings->getFeeBrackets(),
            'member_fees' => $settings->getMemberFeesList(),
            'operational_fee' => $settings->getOperationalFee()
        ]);
        exit;
    }
}

// Only allow POST for calculation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // 1. Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals(csrf_field_value(), $_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }

    // 2. Validate file upload
    if (!isset($_FILES['member_directory'])) {
        echo json_encode(['success' => false, 'error' => 'No file uploaded']);
        exit;
    }

    $file = $_FILES['member_directory'];

    // 3. Validate file size (max 10 MB)
    $maxSize = 10 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'error' => 'File exceeds 10 MB limit']);
        exit;
    }

    // 4. Validate MIME type
    $allowedMimes = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'text/csv'
    ];
    
    if (!in_array($file['type'], $allowedMimes)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Please upload an Excel or CSV file']);
        exit;
    }

    // 5. Parse Excel file using PhpSpreadsheet
    $spreadsheet = IOFactory::load($file['tmp_name']);
    
    // 6. Look for sheets named '1st Yr', '2nd Yr', '3rd Yr', '4th Yr' (case-insensitive)
    $targetSheets = ['1st Yr', '2nd Yr', '3rd Yr', '4th Yr'];
    $sheetsToProcess = [];
    
    foreach ($spreadsheet->getSheetNames() as $sheetName) {
        if (in_array(strtolower($sheetName), array_map('strtolower', $targetSheets))) {
            $sheetsToProcess[] = $sheetName;
        }
    }
    
    // If no target sheets found, use first sheet
    if (empty($sheetsToProcess)) {
        $sheetsToProcess = [$spreadsheet->getSheetNames()[0]];
    }

    // 7. Process each sheet and count members
    // NOTE: member_type column stores fee category ('new', 'returning', 'honorary')
    //       status column stores membership validity ('active', 'inactive', 'suspended', 'pending')
    //       For fee calculation, we ONLY use member_type, NOT status
    $totalMembers = 0;
    $newMembers = 0;
    $returningMembers = 0;
    $honoraryMembers = 0;
    $memberTypeCounts = ['new' => 0, 'returning' => 0, 'honorary' => 0];

    foreach ($sheetsToProcess as $sheetName) {
        $sheet = $spreadsheet->getSheetByName($sheetName);
        $rows = $sheet->toArray();

        // Skip header row (row 0)
        for ($rowIndex = 1; $rowIndex < count($rows); $rowIndex++) {
            $row = $rows[$rowIndex];
            
            // Check if first column (name) is non-empty
            if (empty($row[0])) {
                continue;
            }

            $totalMembers++;

            // 8. Look for 'Member Type' column (case-insensitive)
            $memberType = 'new'; // Default
            
            if (isset($rows[0])) { // Header row
                $headerRow = $rows[0];
                $memberTypeColumn = null;
                
                foreach ($headerRow as $colIndex => $header) {
                    if (strtolower(trim($header)) === 'member type') {
                        $memberTypeColumn = $colIndex;
                        break;
                    }
                }

                // If Member Type column found, classify the row
                if ($memberTypeColumn !== null && isset($row[$memberTypeColumn])) {
                    $type = strtolower(trim($row[$memberTypeColumn]));
                    if (in_array($type, ['new', 'returning', 'honorary'])) {
                        $memberType = $type;
                    }
                }
            }

            $memberTypeCounts[$memberType]++;
        }
    }

    // 9. Calculate fees via centralized FeeCalculator
    require_once SRC_PATH . 'lib/FeeCalculator.php';
    $feeCalculator = new \App\Lib\FeeCalculator($supabase);
    $calculated = $feeCalculator->calculate($totalMembers, $memberTypeCounts);

    $affiliationFee = $calculated['affiliation_fee'];
    $operationalFee = $calculated['operational_fee'];
    $membershipFeesTotal = $calculated['membership_fees_total'];
    $totalFee = $calculated['total_fee'];

    // 10. Store in session with timestamp
    $_SESSION['affiliation_fee_calc'] = [
        'timestamp' => time(),
        'member_count' => $totalMembers,
        'new_members' => $memberTypeCounts['new'] ?? 0,
        'returning_members' => $memberTypeCounts['returning'] ?? 0,
        'honorary_members' => $memberTypeCounts['honorary'] ?? 0,
        'affiliation_fee' => $affiliationFee,
        'operational_fee' => $operationalFee,
        'membership_fees_total' => $membershipFeesTotal,
        'total_fee' => $totalFee
    ];

    // 11. Log to audit_logs
    audit_log(
        null,
        'affiliation_fee_calculated',
        'affiliation_payment',
        null,
        null,
        ['member_count' => $totalMembers, 'total_fee' => $totalFee]
    );

    // 12. Return calculated fees
    echo json_encode([
        'success' => true,
        'member_count' => $totalMembers,
        'new_members' => $memberTypeCounts['new'] ?? 0,
        'returning_members' => $memberTypeCounts['returning'] ?? 0,
        'honorary_members' => $memberTypeCounts['honorary'] ?? 0,
        'affiliation_fee' => round($affiliationFee, 2),
        'operational_fee' => round($operationalFee, 2),
        'membership_fees_total' => round($membershipFeesTotal, 2),
        'total_fee' => round($totalFee, 2)
    ]);

} catch (Exception $e) {
    error_log('Fee calculation error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error during fee calculation']);
}
