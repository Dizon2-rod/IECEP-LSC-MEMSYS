<?php
require_once __DIR__ . '/../../bootstrap.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Lib\EmailService;

header('Content-Type: application/json');

// Check authentication and authorization
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Validate file upload
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['file'];
$fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Validate file type
if (!in_array($fileExt, ['csv', 'xlsx', 'xls'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type. Only CSV and XLSX files are allowed']);
    exit;
}

try {
    $supabase = supabase();
    if (!$supabase) {
        throw new Exception('Database connection failed');
    }

    $emailService = new EmailService();
    $members = [];
    $warnings = [];
    $errors = [];
    $successCount = 0;

    // Parse file based on extension
    if ($fileExt === 'csv') {
        $members = parseCsvFile($file['tmp_name']);
    } else {
        $members = parseExcelFile($file['tmp_name']);
    }

    if (empty($members)) {
        http_response_code(400);
        echo json_encode(['error' => 'No valid data found in file']);
        exit;
    }

    // Process each member
    foreach ($members as $index => $memberData) {
        $rowNumber = $index + 2; // +2 because index starts at 0 and row 1 is header

        // Validate required fields
        $fullName = trim($memberData['full_name'] ?? '');
        $email = trim($memberData['email'] ?? '');

        if (empty($fullName)) {
            $warnings[] = "Row $rowNumber: Missing full name";
            continue;
        }

        if (empty($email)) {
            $warnings[] = "Row $rowNumber: Missing email address";
            continue;
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $warnings[] = "Row $rowNumber: Invalid email format ($email)";
            continue;
        }

        // Check if email already exists
        $existingUser = $supabase->from('users')
            ->select('id')
            ->eq('email', $email)
            ->single();

        if ($existingUser && !isset($existingUser['error'])) {
            $warnings[] = "Row $rowNumber: Email already exists ($email)";
            continue;
        }

        try {
            // Generate member ID
            $memberId = generateMemberId($supabase);

            // Generate temporary password
            $tempPassword = generateTemporaryPassword();

            // Hash password
            $hashedPassword = password_hash($tempPassword, PASSWORD_BCRYPT);

            // Create user account
            $userResult = $supabase->from('users')->insert([
                'email' => $email,
                'password' => $hashedPassword,
                'role' => 'member',
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s')
            ]);

            if (isset($userResult['error'])) {
                $warnings[] = "Row $rowNumber: Failed to create user account for $email";
                continue;
            }

            // Get the created user ID
            $newUser = $supabase->from('users')
                ->select('id')
                ->eq('email', $email)
                ->single();

            if (!$newUser || isset($newUser['error'])) {
                $warnings[] = "Row $rowNumber: Could not retrieve created user ID for $email";
                continue;
            }

            $userId = $newUser['id'];

            // Create member profile
            $profileResult = $supabase->from('member_profiles')->insert([
                'user_id' => $userId,
                'member_id' => $memberId,
                'full_name' => $fullName,
                'email' => $email,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s')
            ]);

            if (isset($profileResult['error'])) {
                $warnings[] = "Row $rowNumber: Failed to create member profile for $fullName";
                continue;
            }

            // Send welcome email
            $emailSent = $emailService->sendMemberCredentials(
                $email,
                $fullName,
                $memberId,
                $tempPassword
            );

            if (!$emailSent) {
                $warnings[] = "Row $rowNumber: Member created but welcome email failed to send for $email";
            }

            $successCount++;

        } catch (Exception $e) {
            $warnings[] = "Row $rowNumber: Error processing member - " . $e->getMessage();
            error_log("Member import error for row $rowNumber: " . $e->getMessage());
        }
    }

    // Return response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => "Import completed. $successCount members created successfully.",
        'stats' => [
            'total_rows' => count($members),
            'successful' => $successCount,
            'warnings' => count($warnings)
        ],
        'warnings' => $warnings
    ]);

} catch (Exception $e) {
    error_log("Member import error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Import failed: ' . $e->getMessage()]);
}

/**
 * Parse CSV file and return array of members
 */
function parseCsvFile(string $filePath): array
{
    $members = [];
    $handle = fopen($filePath, 'r');

    if (!$handle) {
        throw new Exception('Could not open CSV file');
    }

    // Skip header row
    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        return [];
    }

    // Find column indices
    $fullNameIndex = null;
    $emailIndex = null;

    foreach ($header as $index => $column) {
        $column = strtolower(trim($column));
        if (strpos($column, 'name') !== false || strpos($column, 'full') !== false) {
            $fullNameIndex = $index;
        }
        if (strpos($column, 'email') !== false) {
            $emailIndex = $index;
        }
    }

    // Default to columns A and B if not found
    if ($fullNameIndex === null) $fullNameIndex = 0;
    if ($emailIndex === null) $emailIndex = 1;

    // Read data rows
    while (($row = fgetcsv($handle)) !== false) {
        if (empty(array_filter($row))) {
            continue; // Skip empty rows
        }

        $members[] = [
            'full_name' => $row[$fullNameIndex] ?? '',
            'email' => $row[$emailIndex] ?? ''
        ];
    }

    fclose($handle);
    return $members;
}

/**
 * Parse Excel file and return array of members
 */
function parseExcelFile(string $filePath): array
{
    $members = [];

    try {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        if (empty($rows)) {
            return [];
        }

        // Get header row
        $header = array_shift($rows);

        // Find column indices
        $fullNameIndex = null;
        $emailIndex = null;

        foreach ($header as $index => $column) {
            $column = strtolower(trim($column ?? ''));
            if (strpos($column, 'name') !== false || strpos($column, 'full') !== false) {
                $fullNameIndex = $index;
            }
            if (strpos($column, 'email') !== false) {
                $emailIndex = $index;
            }
        }

        // Default to columns A and B if not found
        if ($fullNameIndex === null) $fullNameIndex = 0;
        if ($emailIndex === null) $emailIndex = 1;

        // Read data rows
        foreach ($rows as $row) {
            if (empty(array_filter($row))) {
                continue; // Skip empty rows
            }

            $members[] = [
                'full_name' => $row[$fullNameIndex] ?? '',
                'email' => $row[$emailIndex] ?? ''
            ];
        }

    } catch (Exception $e) {
        throw new Exception('Failed to parse Excel file: ' . $e->getMessage());
    }

    return $members;
}

/**
 * Generate unique member ID in format MEM-YYYY-0001
 */
function generateMemberId($supabase): string
{
    $year = date('Y');
    $prefix = "MEM-$year-";

    // Get the highest counter for this year
    $result = $supabase->from('member_id_counter')
        ->select('counter')
        ->eq('year', $year)
        ->single();

    $counter = 1;

    if ($result && !isset($result['error'])) {
        $counter = $result['counter'] + 1;
        $supabase->from('member_id_counter')
            ->update(['counter' => $counter])
            ->eq('year', $year)
            ->execute();
    } else {
        // Create new counter for this year
        $supabase->from('member_id_counter')->insert([
            'year' => $year,
            'counter' => 1
        ]);
    }

    return $prefix . str_pad($counter, 4, '0', STR_PAD_LEFT);
}

/**
 * Generate temporary password
 */
function generateTemporaryPassword(): string
{
    $length = 12;
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
    $password = '';

    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[random_int(0, strlen($characters) - 1)];
    }

    return $password;
}
