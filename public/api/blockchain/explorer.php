<?php
/**
 * Blockchain Explorer API
 * Provides blockchain records with filtering and chain integrity verification
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
    
    $blockchain = $GLOBALS['blockchain'] ?? null;
    if (!$blockchain) {
        require_once __DIR__ . '/../../src/lib/BlockchainService.php';
        $blockchain = new \App\Lib\BlockchainService($db);
    }
    
    switch ($method) {
        case 'GET':
            if ($action === 'records') {
                // Get blockchain records with filtering
                $recordType = $_GET['record_type'] ?? '';
                $entityId = $_GET['entity_id'] ?? '';
                $limit = (int)($_GET['limit'] ?? 50);
                $offset = (int)($_GET['offset'] ?? 0);
                
                $filters = [];
                if (!empty($recordType)) {
                    $filters['record_type'] = 'eq.' . $recordType;
                }
                if (!empty($entityId)) {
                    $filters['entity_id'] = 'eq.' . $entityId;
                }
                $filters['order'] = 'created_at.desc';
                $filters['limit'] = $limit;
                $filters['offset'] = $offset;
                
                $records = $db->select('blockchain_records', $filters);
                
                // Get total count
                unset($filters['limit'], $filters['offset']);
                $countResult = $db->select('blockchain_records', array_merge($filters, ['select' => 'id']));
                $total = count($countResult);
                
                echo json_encode([
                    'success' => true,
                    'records' => $records,
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset
                ]);
                
            } elseif ($action === 'verify-chain') {
                // Verify blockchain chain integrity
                $recordType = $_GET['record_type'] ?? '';
                
                if (empty($recordType)) {
                    throw new Exception('record_type parameter is required');
                }
                
                $result = $blockchain->verifyChain($recordType);
                
                echo json_encode([
                    'success' => true,
                    'verification' => $result
                ]);
                
            } elseif ($action === 'verify-record') {
                // Verify a specific record
                $recordType = $_GET['record_type'] ?? '';
                $referenceId = $_GET['reference_id'] ?? '';
                
                if (empty($recordType) || empty($referenceId)) {
                    throw new Exception('record_type and reference_id parameters are required');
                }
                
                $isValid = $blockchain->verify($recordType, $referenceId);
                
                echo json_encode([
                    'success' => true,
                    'is_valid' => $isValid,
                    'record_type' => $recordType,
                    'reference_id' => $referenceId
                ]);
                
            } elseif ($action === 'statistics') {
                // Get blockchain statistics
                $stats = [];
                
                // Total records by type
                $recordTypes = ['transaction', 'membership_change', 'compliance_attendance', 'document_hash', 'affiliation_action', 'digital_id'];
                foreach ($recordTypes as $type) {
                    $count = count($db->select('blockchain_records', [
                        'record_type' => 'eq.' . $type
                    ]));
                    $stats['by_type'][$type] = $count;
                }
                
                // Total records
                $stats['total_records'] = count($db->select('blockchain_records', []));
                
                // Recent activity (last 7 days)
                $weekAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
                $recentRecords = $db->select('blockchain_records', [
                    'created_at' => 'gte.' . $weekAgo,
                    'order' => 'created_at.desc'
                ]);
                $stats['recent_activity'] = count($recentRecords);
                
                echo json_encode([
                    'success' => true,
                    'statistics' => $stats
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
