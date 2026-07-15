<?php
/**
 * Fee Waiver Application API
 * Handles fee waiver applications and approvals
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
    
    switch ($method) {
        case 'POST':
            if ($action === 'apply') {
                // Submit fee waiver application
                $input = json_decode(file_get_contents('php://input'), true);
                
                $institutionId = $input['institution_id'] ?? '';
                $reason = $input['reason'] ?? '';
                $requestedAmount = $input['requested_amount'] ?? 0;
                $userId = $_SESSION['user']['id'] ?? '';
                
                if (empty($institutionId) || empty($reason)) {
                    throw new Exception('institution_id and reason are required');
                }
                
                // Create fee waiver record
                $waiverId = generateUUID();
                
                $result = $db->insert('fee_waivers', [
                    'id' => $waiverId,
                    'institution_id' => $institutionId,
                    'requested_by' => $userId,
                    'reason' => $reason,
                    'requested_amount' => $requestedAmount,
                    'status' => 'pending',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                // Create notification for treasurer
                $treasurers = $db->select('user_profiles', [
                    'role' => 'eq.treasurer'
                ]);
                
                foreach ($treasurers as $treasurer) {
                    $db->insert('notifications', [
                        'user_id' => $treasurer['id'],
                        'title' => 'New Fee Waiver Application',
                        'message' => "A new fee waiver application has been submitted for ₱{$requestedAmount}.",
                        'type' => 'info',
                        'action_url' => "/portal/treasurer/fee-waivers.php",
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
                
                echo json_encode([
                    'success' => true,
                    'waiver_id' => $waiverId,
                    'message' => 'Fee waiver application submitted successfully'
                ]);
                
            } elseif ($action === 'approve' || $action === 'reject') {
                // Approve or reject fee waiver
                $input = json_decode(file_get_contents('php://input'), true);
                
                $waiverId = $input['waiver_id'] ?? '';
                $notes = $input['notes'] ?? '';
                $approvedAmount = $input['approved_amount'] ?? 0;
                $userId = $_SESSION['user']['id'] ?? '';
                
                if (empty($waiverId)) {
                    throw new Exception('waiver_id is required');
                }
                
                // Check if user is treasurer or admin
                $userRole = $_SESSION['user']['role'] ?? '';
                if (!in_array($userRole, ['treasurer', 'admin', 'super_admin'])) {
                    throw new Exception('Unauthorized');
                }
                
                // Get waiver details
                $waiver = $db->select('fee_waivers', [
                    'id' => 'eq.' . $waiverId
                ]);
                
                if (empty($waiver)) {
                    throw new Exception('Fee waiver not found');
                }
                
                $waiverData = $waiver[0];
                $newStatus = $action === 'approve' ? 'approved' : 'rejected';
                
                // Update waiver
                $db->update('fee_waivers', [
                    'status' => $newStatus,
                    'reviewed_by' => $userId,
                    'reviewed_at' => date('Y-m-d H:i:s'),
                    'review_notes' => $notes,
                    'approved_amount' => $action === 'approve' ? $approvedAmount : 0
                ])->eq('id', $waiverId)->update();
                
                // Notify applicant
                $db->insert('notifications', [
                    'user_id' => $waiverData['requested_by'],
                    'title' => 'Fee Waiver ' . ucfirst($newStatus),
                    'message' => "Your fee waiver application has been {$newStatus}. " . ($notes ? "Note: {$notes}" : ''),
                    'type' => $action === 'approve' ? 'success' : 'warning',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                echo json_encode([
                    'success' => true,
                    'status' => $newStatus,
                    'message' => "Fee waiver {$newStatus} successfully"
                ]);
                
            } else {
                throw new Exception('Invalid action');
            }
            break;
            
        case 'GET':
            if ($action === 'list') {
                // Get fee waiver applications
                $status = $_GET['status'] ?? '';
                $institutionId = $_GET['institution_id'] ?? '';
                
                $filters = [];
                if (!empty($status)) {
                    $filters['status'] = 'eq.' . $status;
                }
                if (!empty($institutionId)) {
                    $filters['institution_id'] = 'eq.' . $institutionId;
                }
                $filters['order'] = 'created_at.desc';
                
                $waivers = $db->select('fee_waivers', $filters);
                
                // Get institution names and applicant names
                foreach ($waivers as &$waiver) {
                    $instData = $db->select('institutions', [
                        'id' => 'eq.' . $waiver['institution_id'],
                        'select' => 'name'
                    ]);
                    $waiver['institution_name'] = $instData[0]['name'] ?? 'Unknown';
                    
                    $userData = $db->select('user_profiles', [
                        'id' => 'eq.' . $waiver['requested_by'],
                        'select' => 'full_name'
                    ]);
                    $waiver['applicant_name'] = $userData[0]['full_name'] ?? 'Unknown';
                }
                
                echo json_encode([
                    'success' => true,
                    'waivers' => $waivers
                ]);
                
            } elseif ($action === 'detail') {
                // Get fee waiver details
                $waiverId = $_GET['waiver_id'] ?? '';
                
                if (empty($waiverId)) {
                    throw new Exception('waiver_id parameter is required');
                }
                
                $waivers = $db->select('fee_waivers', [
                    'id' => 'eq.' . $waiverId
                ]);
                
                if (empty($waivers)) {
                    throw new Exception('Fee waiver not found');
                }
                
                $waiver = $waivers[0];
                
                // Get related data
                $instData = $db->select('institutions', [
                    'id' => 'eq.' . $waiver['institution_id']
                ]);
                $waiver['institution'] = $instData[0] ?? null;
                
                echo json_encode([
                    'success' => true,
                    'waiver' => $waiver
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
