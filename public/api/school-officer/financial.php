<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/helpers/cbl_fee_calculator.php';

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
            $uploadDir = __DIR__ . '/../../../storage/payments/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            $fileName = uniqid() . '_' . basename($_FILES['proof_of_payment']['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['proof_of_payment']['tmp_name'], $targetPath)) {
                $proofUrl = '/storage/payments/' . $fileName;
            }
        }
        
        $query = "INSERT INTO financial_records (school_id, amount, payment_type, payment_status, proof_of_payment) 
                  VALUES ($1, $2, $3, 'Pending', $4) RETURNING id";
        $result = pg_query_params($conn, $query, [$schoolId, $amount, $paymentType, $proofUrl]);
        
        if ($result) {
            $row = pg_fetch_assoc($result);
            echo json_encode(['success' => true, 'record_id' => $row['id']]);
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
        
        $query = "SELECT * FROM financial_records WHERE school_id = $1 ORDER BY created_at DESC";
        $result = pg_query_params($conn, $query, [$schoolId]);
        
        $records = [];
        while ($row = pg_fetch_assoc($result)) {
            $records[] = $row;
        }
        
        echo json_encode(['records' => $records]);
        break;
        
    case 'calculate_fee':
        $memberCount = $_GET['member_count'] ?? 0;
        $bracket = getFeeBracket($memberCount);
        $total = calculateTotalFee($memberCount);
        
        echo json_encode([
            'member_count' => $memberCount,
            'bracket' => $bracket['bracket'],
            'affiliation_fee' => $bracket['affiliation'],
            'operational_fee' => 800,
            'total_fee' => $total
        ]);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
