<?php
/**
 * Memorandum System API
 * Internal memorandum publishing and management
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
                // Create new memorandum
                $input = json_decode(file_get_contents('php://input'), true);
                
                $title = $input['title'] ?? '';
                $content = $input['content'] ?? '';
                $targetRoles = $input['target_roles'] ?? [];
                $targetInstitutions = $input['target_institutions'] ?? [];
                $isGlobal = $input['is_global'] ?? false;
                $userId = $_SESSION['user']['id'] ?? '';
                
                if (empty($title) || empty($content)) {
                    throw new Exception('title and content are required');
                }
                
                $memoId = generateUUID();
                
                $result = $db->insert('memoranda', [
                    'id' => $memoId,
                    'title' => $title,
                    'content' => $content,
                    'target_roles' => json_encode($targetRoles),
                    'target_institutions' => json_encode($targetInstitutions),
                    'is_global' => $isGlobal,
                    'created_by' => $userId,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                // Send notifications to target users
                if ($isGlobal) {
                    // Notify all active users
                    $users = $db->select('user_profiles', [
                        'membership_status' => 'eq.active'
                    ]);
                    
                    foreach ($users as $user) {
                        $db->insert('notifications', [
                            'user_id' => $user['id'],
                            'title' => 'New Memorandum Published',
                            'message' => "A new memorandum '{$title}' has been published.",
                            'type' => 'info',
                            'action_url' => "/portal/admin/memoranda.php",
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                } else {
                    // Notify specific roles
                    if (!empty($targetRoles)) {
                        foreach ($targetRoles as $role) {
                            $users = $db->select('user_profiles', [
                                'role' => 'eq.' . $role
                            ]);
                            
                            foreach ($users as $user) {
                                $db->insert('notifications', [
                                    'user_id' => $user['id'],
                                    'title' => 'New Memorandum Published',
                                    'message' => "A new memorandum '{$title}' has been published.",
                                    'type' => 'info',
                                    'action_url' => "/portal/admin/memoranda.php",
                                    'created_at' => date('Y-m-d H:i:s')
                                ]);
                            }
                        }
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'memo_id' => $memoId,
                    'message' => 'Memorandum created successfully'
                ]);
                
            } elseif ($action === 'update') {
                // Update memorandum
                $input = json_decode(file_get_contents('php://input'), true);
                
                $memoId = $input['memo_id'] ?? '';
                $title = $input['title'] ?? '';
                $content = $input['content'] ?? '';
                
                if (empty($memoId)) {
                    throw new Exception('memo_id is required');
                }
                
                $updateData = [];
                if (!empty($title)) $updateData['title'] = $title;
                if (!empty($content)) $updateData['content'] = $content;
                
                if (!empty($updateData)) {
                    $db->update('memoranda', $updateData)->eq('id', $memoId)->update();
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Memorandum updated successfully'
                ]);
                
            } else {
                throw new Exception('Invalid action');
            }
            break;
            
        case 'GET':
            if ($action === 'list') {
                // Get memoranda
                $filters = ['order' => 'created_at.desc'];
                $memoranda = $db->select('memoranda', $filters);
                
                // Get creator names
                foreach ($memoranda as &$memo) {
                    $userData = $db->select('user_profiles', [
                        'id' => 'eq.' . $memo['created_by'],
                        'select' => 'full_name'
                    ]);
                    $memo['creator_name'] = $userData[0]['full_name'] ?? 'Unknown';
                    
                    // Parse JSON fields
                    if (is_string($memo['target_roles'])) {
                        $memo['target_roles'] = json_decode($memo['target_roles'], true);
                    }
                    if (is_string($memo['target_institutions'])) {
                        $memo['target_institutions'] = json_decode($memo['target_institutions'], true);
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'memoranda' => $memoranda
                ]);
                
            } elseif ($action === 'detail') {
                // Get memorandum details
                $memoId = $_GET['memo_id'] ?? '';
                
                if (empty($memoId)) {
                    throw new Exception('memo_id parameter is required');
                }
                
                $memoranda = $db->select('memoranda', [
                    'id' => 'eq.' . $memoId
                ]);
                
                if (empty($memoranda)) {
                    throw new Exception('Memorandum not found');
                }
                
                $memo = $memoranda[0];
                
                // Parse JSON fields
                if (is_string($memo['target_roles'])) {
                    $memo['target_roles'] = json_decode($memo['target_roles'], true);
                }
                if (is_string($memo['target_institutions'])) {
                    $memo['target_institutions'] = json_decode($memo['target_institutions'], true);
                }
                
                echo json_encode([
                    'success' => true,
                    'memorandum' => $memo
                ]);
                
            } else {
                throw new Exception('Invalid action');
            }
            break;
            
        case 'DELETE':
            if ($action === 'delete') {
                // Delete memorandum
                $memoId = $_GET['memo_id'] ?? '';
                
                if (empty($memoId)) {
                    throw new Exception('memo_id parameter is required');
                }
                
                $db->delete('memoranda')->eq('id', $memoId)->delete();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Memorandum deleted successfully'
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
