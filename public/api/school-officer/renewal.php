<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/helpers/cbl_fee_calculator.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

session_start();
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? null;

if (!$userId || $userRole !== 'school_officer') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

switch ($action) {
    case 'check_renewal_status':
        $schoolId = $_GET['school_id'] ?? null;
        
        if (!$schoolId) {
            http_response_code(400);
            echo json_encode(['error' => 'School ID required']);
            exit;
        }
        
        $query = "
            SELECT 
                affiliation_status,
                validity_expiry,
                last_renewal_date,
                CASE 
                    WHEN validity_expiry < CURRENT_DATE THEN 'expired'
                    WHEN validity_expiry BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL '30 days' THEN 'expiring_soon'
                    ELSE 'valid'
                END as renewal_urgency
            FROM school_profiles
            WHERE id = $1
        ";
        
        $result = pg_query_params($conn, $query, [$schoolId]);
        
        if ($result && $row = pg_fetch_assoc($result)) {
            echo json_encode($row);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'School not found']);
        }
        break;
        
    case 'submit_renewal':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        $schoolId = $_POST['school_id'] ?? null;
        $memberCount = $_POST['member_count'] ?? 0;
        
        if (!$schoolId) {
            http_response_code(400);
            echo json_encode(['error' => 'School ID required']);
            exit;
        }
        
        // Calculate renewal fee
        $totalFee = calculateTotalFee($memberCount);
        
        // Handle payment proof upload
        $proofUrl = null;
        if (isset($_FILES['proof_of_payment'])) {
            $uploadDir = __DIR__ . '/../../../storage/payments/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            $fileName = uniqid() . '_renewal_' . basename($_FILES['proof_of_payment']['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['proof_of_payment']['tmp_name'], $targetPath)) {
                $proofUrl = '/storage/payments/' . $fileName;
            }
        }
        
        // Create financial record for renewal
        $finQuery = "
            INSERT INTO financial_records (school_id, amount, payment_type, payment_status, proof_of_payment)
            VALUES ($1, $2, 'Affiliation', 'Pending', $3)
            RETURNING id
        ";
        $finResult = pg_query_params($conn, $finQuery, [$schoolId, $totalFee, $proofUrl]);
        
        if (!$finResult) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create payment record']);
            exit;
        }
        
        // Update school profile - set to Pending until payment verified
        $updateQuery = "
            UPDATE school_profiles
            SET affiliation_status = 'Pending',
                total_members = $1,
                last_renewal_date = CURRENT_DATE
            WHERE id = $2
        ";
        pg_query_params($conn, $updateQuery, [$memberCount, $schoolId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Renewal submitted. Awaiting payment verification.',
            'total_fee' => $totalFee
        ]);
        break;
        
    case 'approve_renewal':
        // For VP External / Treasurer after payment verification
        if (!in_array($userRole, ['eb_treasurer', 'eb_vp_external'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Insufficient permissions']);
            exit;
        }
        
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        $schoolId = $_POST['school_id'] ?? null;
        
        if (!$schoolId) {
            http_response_code(400);
            echo json_encode(['error' => 'School ID required']);
            exit;
        }
        
        // Set new expiry date (1 academic year from now)
        $query = "
            UPDATE school_profiles
            SET affiliation_status = 'Active',
                validity_expiry = CURRENT_DATE + INTERVAL '1 year'
            WHERE id = $1
        ";
        
        $result = pg_query_params($conn, $query, [$schoolId]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Renewal approved. Affiliation valid for 1 year.'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to approve renewal']);
        }
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
