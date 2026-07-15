<?php
/**
 * Policy Compliance Checklist API
 * Track regulatory requirements per institution
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
            if ($action === 'create') {
                // Create new policy requirement
                $input = json_decode(file_get_contents('php://input'), true);
                
                $title = $input['title'] ?? '';
                $description = $input['description'] ?? '';
                $category = $input['category'] ?? '';
                $targetInstitutions = $input['target_institutions'] ?? [];
                $dueDate = $input['due_date'] ?? '';
                $userId = $_SESSION['user']['id'] ?? '';
                
                if (empty($title) || empty($category)) {
                    throw new Exception('title and category are required');
                }
                
                $policyId = generateUUID();
                
                $result = $db->insert('policy_compliance', [
                    'id' => $policyId,
                    'title' => $title,
                    'description' => $description,
                    'category' => $category,
                    'target_institutions' => json_encode($targetInstitutions),
                    'due_date' => $dueDate,
                    'created_by' => $userId,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                echo json_encode([
                    'success' => true,
                    'policy_id' => $policyId,
                    'message' => 'Policy requirement created successfully'
                ]);
                
            } elseif ($action === 'update-status') {
                // Update compliance status for an institution
                $input = json_decode(file_get_contents('php://input'), true);
                
                $policyId = $input['policy_id'] ?? '';
                $institutionId = $input['institution_id'] ?? '';
                $status = $input['status'] ?? '';
                $notes = $input['notes'] ?? '';
                $userId = $_SESSION['user']['id'] ?? '';
                
                if (empty($policyId) || empty($institutionId) || empty($status)) {
                    throw new Exception('policy_id, institution_id, and status are required');
                }
                
                // Check if compliance record exists
                $existing = $db->select('policy_compliance_status', [
                    'policy_id' => 'eq.' . $policyId,
                    'institution_id' => 'eq.' . $institutionId
                ]);
                
                if (!empty($existing)) {
                    // Update existing record
                    $db->update('policy_compliance_status', [
                        'status' => $status,
                        'notes' => $notes,
                        'updated_by' => $userId,
                        'updated_at' => date('Y-m-d H:i:s')
                    ])->eq('id', $existing[0]['id'])->update();
                } else {
                    // Create new compliance record
                    $db->insert('policy_compliance_status', [
                        'id' => generateUUID(),
                        'policy_id' => $policyId,
                        'institution_id' => $institutionId,
                        'status' => $status,
                        'notes' => $notes,
                        'updated_by' => $userId,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Compliance status updated successfully'
                ]);
                
            } else {
                throw new Exception('Invalid action');
            }
            break;
            
        case 'GET':
            if ($action === 'list') {
                // Get policy requirements
                $category = $_GET['category'] ?? '';
                $institutionId = $_GET['institution_id'] ?? '';
                
                $filters = ['order' => 'created_at.desc'];
                if (!empty($category)) {
                    $filters['category'] = 'eq.' . $category;
                }
                
                $policies = $db->select('policy_compliance', $filters);
                
                // Get creator names
                foreach ($policies as &$policy) {
                    $userData = $db->select('user_profiles', [
                        'id' => 'eq.' . $policy['created_by'],
                        'select' => 'full_name'
                    ]);
                    $policy['creator_name'] = $userData[0]['full_name'] ?? 'Unknown';
                    
                    // Parse target institutions
                    if (is_string($policy['target_institutions'])) {
                        $policy['target_institutions'] = json_decode($policy['target_institutions'], true);
                    }
                    
                    // Get compliance status for specific institution if provided
                    if (!empty($institutionId)) {
                        $status = $db->select('policy_compliance_status', [
                            'policy_id' => 'eq.' . $policy['id'],
                            'institution_id' => 'eq.' . $institutionId
                        ]);
                        $policy['compliance_status'] = $status[0] ?? null;
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'policies' => $policies
                ]);
                
            } elseif ($action === 'institution-status') {
                // Get compliance status for a specific institution
                $institutionId = $_GET['institution_id'] ?? '';
                
                if (empty($institutionId)) {
                    throw new Exception('institution_id parameter is required');
                }
                
                // Get all policies
                $policies = $db->select('policy_compliance', [
                    'order' => 'created_at.desc'
                ]);
                
                $complianceData = [];
                foreach ($policies as $policy) {
                    // Check if this policy applies to the institution
                    $targetInstitutions = is_string($policy['target_institutions']) 
                        ? json_decode($policy['target_institutions'], true) 
                        : $policy['target_institutions'];
                    
                    if (empty($targetInstitutions) || in_array($institutionId, $targetInstitutions)) {
                        // Get compliance status
                        $status = $db->select('policy_compliance_status', [
                            'policy_id' => 'eq.' . $policy['id'],
                            'institution_id' => 'eq.' . $institutionId
                        ]);
                        
                        $complianceData[] = [
                            'policy' => $policy,
                            'status' => $status[0] ?? null
                        ];
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'institution_id' => $institutionId,
                    'compliance_data' => $complianceData
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
