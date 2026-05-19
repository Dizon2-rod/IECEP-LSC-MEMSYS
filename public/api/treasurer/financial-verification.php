<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/database.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

session_start();
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? null;

if (!$userId || $userRole !== 'eb_treasurer') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

switch ($action) {
    case 'get_pending_payments':
        $query = "SELECT fr.*, sp.school_name 
                  FROM financial_records fr
                  JOIN school_profiles sp ON fr.school_id = sp.id
                  WHERE fr.payment_status = 'Pending'
                  ORDER BY fr.created_at DESC";
        $result = pg_query($conn, $query);
        
        $payments = [];
        while ($row = pg_fetch_assoc($result)) {
            $payments[] = $row;
        }
        
        echo json_encode(['payments' => $payments]);
        break;
        
    case 'verify_payment':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        $recordId = $_POST['record_id'] ?? null;
        
        if (!$recordId) {
            http_response_code(400);
            echo json_encode(['error' => 'Record ID required']);
            exit;
        }
        
        $receiptUrl = null;
        if (isset($_FILES['official_receipt'])) {
            $uploadDir = __DIR__ . '/../../../storage/receipts/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            $fileName = uniqid() . '_' . basename($_FILES['official_receipt']['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['official_receipt']['tmp_name'], $targetPath)) {
                $receiptUrl = '/storage/receipts/' . $fileName;
            }
        }
        
        $query = "UPDATE financial_records 
                  SET payment_status = 'Verified', official_receipt_url = $1 
                  WHERE id = $2";
        $result = pg_query_params($conn, $query, [$receiptUrl, $recordId]);
        
        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to verify payment']);
        }
        break;
        
    case 'get_financial_summary':
        $query = "SELECT 
                    COUNT(*) as total_transactions,
                    SUM(CASE WHEN payment_status = 'Verified' THEN amount ELSE 0 END) as total_collected,
                    SUM(CASE WHEN payment_status = 'Pending' THEN amount ELSE 0 END) as pending_amount
                  FROM financial_records";
        $result = pg_query($conn, $query);
        
        if ($result) {
            $summary = pg_fetch_assoc($result);
            echo json_encode($summary);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to get summary']);
        }
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
