<?php
/**
 * Merkle Tree Verification API
 * Verifies Merkle roots for batch attendance records
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware/auth.php';

header('Content-Type: application/json');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    $db = $GLOBALS['supabaseClient'] ?? null;
    if (!$db) {
        throw new Exception('Database connection not available');
    }
    
    require_once __DIR__ . '/../../src/lib/MerkleTree.php';
    
    switch ($method) {
        case 'POST':
            if ($action === 'verify-batch') {
                // Verify Merkle root for a batch of records
                $input = json_decode(file_get_contents('php://input'), true);
                
                $batchId = $input['batch_id'] ?? '';
                $records = $input['records'] ?? [];
                
                if (empty($batchId) || empty($records)) {
                    throw new Exception('batch_id and records are required');
                }
                
                // Calculate Merkle root from provided records
                $calculatedRoot = \App\Lib\MerkleTree::buildRoot($records);
                
                // Get stored Merkle root from blockchain
                $blockchainRecords = $db->select('blockchain_records', [
                    'record_type' => 'eq.compliance_attendance',
                    'reference_id' => 'eq.' . $batchId,
                    'order' => 'created_at.desc',
                    'limit' => 1
                ]);
                
                if (empty($blockchainRecords)) {
                    echo json_encode([
                        'success' => true,
                        'verified' => false,
                        'message' => 'No blockchain record found for this batch',
                        'calculated_root' => $calculatedRoot
                    ]);
                    exit;
                }
                
                $storedRecord = $blockchainRecords[0];
                $storedData = $storedRecord['data_json'];
                if (is_string($storedData)) {
                    $storedData = json_decode($storedData, true);
                }
                
                $storedRoot = $storedData['merkle_root'] ?? '';
                
                $isValid = hash_equals($calculatedRoot, $storedRoot);
                
                echo json_encode([
                    'success' => true,
                    'verified' => $isValid,
                    'calculated_root' => $calculatedRoot,
                    'stored_root' => $storedRoot,
                    'batch_id' => $batchId,
                    'record_count' => count($records)
                ]);
                
            } elseif ($action === 'calculate-root') {
                // Calculate Merkle root for a set of records
                $input = json_decode(file_get_contents('php://input'), true);
                $records = $input['records'] ?? [];
                
                if (empty($records)) {
                    throw new Exception('records array is required');
                }
                
                $merkleRoot = \App\Lib\MerkleTree::buildRoot($records);
                
                echo json_encode([
                    'success' => true,
                    'merkle_root' => $merkleRoot,
                    'record_count' => count($records)
                ]);
                
            } else {
                throw new Exception('Invalid action');
            }
            break;
            
        case 'GET':
            if ($action === 'batch-root') {
                // Get stored Merkle root for a batch
                $batchId = $_GET['batch_id'] ?? '';
                
                if (empty($batchId)) {
                    throw new Exception('batch_id parameter is required');
                }
                
                $blockchainRecords = $db->select('blockchain_records', [
                    'record_type' => 'eq.compliance_attendance',
                    'reference_id' => 'eq.' . $batchId,
                    'order' => 'created_at.desc',
                    'limit' => 1
                ]);
                
                if (empty($blockchainRecords)) {
                    throw new Exception('No blockchain record found for this batch');
                }
                
                $storedRecord = $blockchainRecords[0];
                $storedData = $storedRecord['data_json'];
                if (is_string($storedData)) {
                    $storedData = json_decode($storedData, true);
                }
                
                $merkleRoot = $storedData['merkle_root'] ?? '';
                
                echo json_encode([
                    'success' => true,
                    'merkle_root' => $merkleRoot,
                    'batch_id' => $batchId,
                    'created_at' => $storedRecord['created_at']
                ]);
                
            } else {
                throw new Exception('Invalid action');
            }
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
