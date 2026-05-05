<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../../includes/supabase.php';
require_once __DIR__ . '/../../includes/lib/csv.php';
require_once __DIR__ . '/../../includes/middleware/auth.php';

use App\Lib\Supabase;
use App\Lib\CsvService;
use App\Middleware\AuthMiddleware;

$sb = new Supabase();
$auth = new AuthMiddleware();
$csv = new CsvService();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'dashboard':
            if ($method !== 'GET') { http_response_code(405); exit; }
            $user = $auth->requireRole(['school_officer']);

            // Get officer's institution
            $memberResult = $sb->from('members')
                ->select('institution_id, institutions!inner(id, name, status, compliance_status, affiliation_fee_paid)')
                ->eq('user_id', $user['user_id'])
                ->get(true, $user['jwt']);

            if ($memberResult['error'] || empty($memberResult['data'])) {
                http_response_code(404);
                echo json_encode(['error' => 'Institution not found']);
                exit;
            }

            $institution = $memberResult['data'][0]['institutions'];
            $institutionId = $institution['id'];

            // Get member count
            $membersResult = $sb->from('members')
                ->select('id, payment_status')
                ->eq('institution_id', $institutionId)
                ->get(true);

            $totalMembers = count($membersResult['data'] ?? []);
            $paidMembers = count(array_filter($membersResult['data'] ?? [], fn($m) => $m['payment_status'] === true));

            // Get compliance
            $complianceResult = $sb->from('compliance_records')
                ->select('*')
                ->eq('institution_id', $institutionId)
                ->order('calculated_at', false)
                ->limit(1)
                ->get(true);

            $compliance = $complianceResult['data'][0] ?? null;

            // Get pending batches
            $batchesResult = $sb->from('member_upload_batches')
                ->select('*, pending_members(status)')
                ->eq('institution_id', $institutionId)
                ->order('uploaded_at', false)
                ->get(true);

            echo json_encode([
                'success' => true,
                'institution' => $institution,
                'stats' => [
                    'total_members' => $totalMembers,
                    'paid_members' => $paidMembers,
                    'unpaid_members' => $totalMembers - $paidMembers,
                ],
                'compliance' => $compliance,
                'batches' => $batchesResult['data'] ?? [],
            ]);
            break;

        case 'upload-members':
            if ($method !== 'POST') { http_response_code(405); exit; }
            $user = $auth->requireRole(['school_officer']);

            if (!isset($_FILES['csv_file'])) {
                http_response_code(400);
                echo json_encode(['error' => 'No CSV file uploaded']);
                exit;
            }

            $file = $_FILES['csv_file'];
            if ($file['type'] !== 'text/csv' && pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
                http_response_code(400);
                echo json_encode(['error' => 'Only CSV files allowed']);
                exit;
            }

            // Get officer's institution
            $memberResult = $sb->from('members')
                ->select('institution_id')
                ->eq('user_id', $user['user_id'])
                ->get(true, $user['jwt']);

            if ($memberResult['error'] || empty($memberResult['data'])) {
                http_response_code(404);
                echo json_encode(['error' => 'Institution not found']);
                exit;
            }

            $institutionId = $memberResult['data'][0]['institution_id'];

            // Parse CSV
            $parseResult = $csv->parse($file['tmp_name']);
            if ($parseResult['error']) {
                http_response_code(400);
                echo json_encode(['error' => $parseResult['message']]);
                exit;
            }

            $rows = $parseResult['data'];

            // Validate emails
            $emailErrors = $csv->validateEmails($rows);
            if (!empty($emailErrors)) {
                http_response_code(400);
                echo json_encode(['error' => 'Validation errors', 'details' => $emailErrors]);
                exit;
            }

            // Check for duplicate emails globally
            $emails = array_column($rows, 'email');
            $existingMembers = $sb->from('members')
                ->select('email')
                ->in('email', $emails)
                ->get(true);

            if (!$existingMembers['error'] && !empty($existingMembers['data'])) {
                $existingEmails = array_column($existingMembers['data'], 'email');
                http_response_code(409);
                echo json_encode(['error' => 'Some emails already exist', 'duplicates' => $existingEmails]);
                exit;
            }

            // Create batch
            $batchResult = $sb->from('member_upload_batches')->insert([
                'institution_id' => $institutionId,
                'file_name' => $file['name'],
                'status' => 'pending_approval',
            ], true);

            $batchId = $batchResult['data'][0]['id'] ?? '';

            // Insert pending members
            foreach ($rows as $row) {
                $sb->from('pending_members')->insert([
                    'batch_id' => $batchId,
                    'full_name' => $row['full_name'],
                    'email' => $row['email'],
                    'member_type' => $row['member_type'],
                    'year_level' => $row['year_level'],
                    'status' => 'pending',
                ], true);
            }

            echo json_encode(['success' => true, 'message' => 'Member list uploaded. Awaiting Registration Committee approval.', 'batch_id' => $batchId, 'count' => count($rows)]);
            break;

        case 'download-template':
            if ($method !== 'GET') { http_response_code(405); exit; }
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="member_template.csv"');
            echo $csv->generateTemplate();
            exit;

        case 'my-members':
            if ($method !== 'GET') { http_response_code(405); exit; }
            $user = $auth->requireRole(['school_officer']);

            $memberResult = $sb->from('members')
                ->select('institution_id')
                ->eq('user_id', $user['user_id'])
                ->get(true, $user['jwt']);

            $institutionId = $memberResult['data'][0]['institution_id'] ?? '';

            $members = $sb->from('members')
                ->select('id, full_name, email, member_type, payment_status, year_level, digital_id_url')
                ->eq('institution_id', $institutionId)
                ->order('full_name')
                ->get(true, $user['jwt']);

            echo json_encode(['success' => true, 'data' => $members['data'] ?? []]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
