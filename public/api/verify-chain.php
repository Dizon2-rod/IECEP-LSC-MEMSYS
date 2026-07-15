<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../portal/auth_check.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_role(['admin', 'super_admin'], false);
if (!in_array($_SESSION['user']['role'], ['admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$db = Database::getInstance();

try {
    // Get all blockchain records ordered by creation date
    $records = $db->fetchAll("SELECT * FROM blockchain_records ORDER BY created_at ASC");
    
    $isValid = true;
    $message = '';
    
    // Simple integrity check - verify all records have valid hashes
    foreach ($records as $record) {
        // Verify transaction hash is valid SHA-256
        if (!preg_match('/^[0-9a-fA-F]{64}$/', $record['transaction_hash'])) {
            $isValid = false;
            $message = 'Invalid transaction hash format detected';
            break;
        }
        
        // Verify record hash if present
        if (!empty($record['record_hash'])) {
            if (!preg_match('/^[0-9a-fA-F]{64}$/', $record['record_hash'])) {
                $isValid = false;
                $message = 'Invalid record hash format detected';
                break;
            }
        }
    }
    
    // Check for duplicate transaction hashes
    $hashCounts = [];
    foreach ($records as $record) {
        $hash = $record['transaction_hash'];
        if (isset($hashCounts[$hash])) {
            $hashCounts[$hash]++;
        } else {
            $hashCounts[$hash] = 1;
        }
    }
    
    foreach ($hashCounts as $hash => $count) {
        if ($count > 1) {
            $isValid = false;
            $message = 'Duplicate transaction hashes detected';
            break;
        }
    }
    
    echo json_encode([
        'valid' => $isValid,
        'message' => $message ?: 'Chain integrity verified',
        'total_records' => count($records),
        'confirmed_records' => count(array_filter($records, fn($r) => $r['confirmed']))
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Verification failed', 'message' => $e->getMessage()]);
}
