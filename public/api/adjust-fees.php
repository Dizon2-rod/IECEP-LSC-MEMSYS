<?php
/**
 * Auto Fee Adjustment Logic
 * This script adjusts membership fees based on inflation and other factors
 * Can be called manually or via cron
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../portal/auth_check.php';

header('Content-Type: application/json');

require_role(['admin', 'super_admin', 'eb_treasurer']);

$db = Database::getInstance();

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? 'calculate';

function calculateFeeAdjustment($db, $inflationRate = 0.05) {
    // Get current fee structure from settings or use defaults
    $currentFees = [
        'new_member' => 500,
        'renewal' => 400,
        'alumni' => 300,
        'event_fee' => 100
    ];
    
    // Calculate adjusted fees
    $adjustedFees = [];
    foreach ($currentFees as $type => $fee) {
        $adjustedFees[$type] = round($fee * (1 + $inflationRate), -2); // Round to nearest 10
    }
    
    return [
        'current_fees' => $currentFees,
        'adjusted_fees' => $adjustedFees,
        'inflation_rate' => $inflationRate * 100,
        'effective_date' => date('Y-m-d', strtotime('+1 month'))
    ];
}

try {
    switch ($action) {
        case 'calculate':
            $inflationRate = floatval($data['inflation_rate'] ?? 0.05);
            $result = calculateFeeAdjustment($db, $inflationRate);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'apply':
            require_role(['admin', 'super_admin']);
            
            $adjustedFees = $data['adjusted_fees'] ?? [];
            $effectiveDate = $data['effective_date'] ?? date('Y-m-d', strtotime('+1 month'));
            
            $db->beginTransaction();
            
            // Store fee adjustment record
            $adjustmentId = generateUUID();
            $db->insert('fee_adjustments', [
                'id' => $adjustmentId,
                'adjustment_date' => date('Y-m-d'),
                'effective_date' => $effectiveDate,
                'old_fees' => json_encode($data['current_fees'] ?? []),
                'new_fees' => json_encode($adjustedFees),
                'reason' => $data['reason'] ?? 'Annual inflation adjustment',
                'created_by' => $_SESSION['user']['id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Log to audit trail
            $db->insert('audit_logs', [
                'id' => generateUUID(),
                'user_id' => $_SESSION['user']['id'],
                'action' => 'FEE_ADJUSTMENT',
                'details' => json_encode([
                    'adjustment_id' => $adjustmentId,
                    'new_fees' => $adjustedFees,
                    'effective_date' => $effectiveDate
                ]),
                'affected_entity_type' => 'fee_structure',
                'affected_entity_id' => $adjustmentId,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $db->commit();
            
            echo json_encode(['success' => true, 'message' => 'Fee adjustment applied successfully']);
            break;
            
        case 'history':
            $history = $db->fetchAll("SELECT fa.*, up.full_name as created_by_name 
                FROM fee_adjustments fa
                LEFT JOIN user_profiles up ON fa.created_by = up.user_id
                ORDER BY fa.adjustment_date DESC
                LIMIT 20");
            
            foreach ($history as &$record) {
                $record['old_fees'] = json_decode($record['old_fees'], true);
                $record['new_fees'] = json_decode($record['new_fees'], true);
            }
            
            echo json_encode(['success' => true, 'data' => $history]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
    }
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function generateUUID() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
