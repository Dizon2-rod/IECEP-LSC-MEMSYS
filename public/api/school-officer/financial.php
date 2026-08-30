<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/helpers/cbl_fee_calculator.php';

if (!function_exists('uploadToSupabaseStorage')) {
    function uploadToSupabaseStorage(string $bucket, string $path, string $tmpFile, string $mimeType): ?string {
        $supabaseClient = getSupabaseClient();
        if ($supabaseClient && method_exists($supabaseClient, 'uploadFile')) {
            $url = $supabaseClient->uploadFile($bucket, $path, $tmpFile, $mimeType);
            if ($url) return $url;
        }
        return null;
    }
}

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

session_start();
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$supabaseConfig = require __DIR__ . '/../../../includes/supabase.php';
$supabase = new \App\Lib\SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);

switch ($action) {
    case 'upload_payment':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        $schoolId = $_POST['school_id'] ?? null;
        $amount = $_POST['amount'] ?? null;
        $paymentType = $_POST['payment_type'] ?? 'Affiliation';
        
        if (!$schoolId || !$amount) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }
        
        $proofUrl = null;
        if (isset($_FILES['proof_of_payment'])) {
            $mimeType = mime_content_type($_FILES['proof_of_payment']['tmp_name']) ?: $_FILES['proof_of_payment']['type'];
            $fileName = uniqid() . '_' . basename($_FILES['proof_of_payment']['name']);
            $proofUrl = uploadToSupabaseStorage('payments', 'proofs/' . $fileName, $_FILES['proof_of_payment']['tmp_name'], $mimeType);
        }
        
        $result = $supabase->insert('financial_records', [
            'school_id' => $schoolId,
            'amount' => $amount,
            'payment_type' => $paymentType,
            'payment_status' => 'Pending',
            'proof_of_payment' => $proofUrl,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($result) {
            $recordId = is_array($result) && isset($result[0]['id']) ? $result[0]['id'] : null;
            echo json_encode(['success' => true, 'record_id' => $recordId]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create record']);
        }
        break;
        
    case 'get_ledger':
        $schoolId = $_GET['school_id'] ?? null;
        
        if (!$schoolId) {
            http_response_code(400);
            echo json_encode(['error' => 'School ID required']);
            exit;
        }
        
        $records = $supabase->select('financial_records', ['school_id' => 'eq.' . $schoolId, 'order' => 'created_at.desc']);
        
        echo json_encode(['records' => $records ?? []]);
        break;
        
    case 'calculate_fee':
        $memberCount = $_GET['member_count'] ?? 0;
        $bracket = getFeeBracket($memberCount);
        $total = calculateTotalFee($memberCount);
        
        echo json_encode([
            'member_count' => $memberCount,
            'bracket' => $bracket['bracket'],
            'affiliation_fee' => $bracket['affiliation'],
            'operational_fee' => 800.00,
            'total_fee' => $total
        ]);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
