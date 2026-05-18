<?php
/**
 * ROLE PERMISSIONS MATRIX - Admin API
 * Manage role-based permissions
 * Created: May 17, 2026
 */

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/auth.php';

header('Content-Type: application/json');

// Verify admin access
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized']));
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'matrix';

try {
    switch ($method) {
        case 'GET':
            if ($action === 'matrix') {
                handleMatrix();
            } elseif ($action === 'role') {
                handleRolePermissions();
            } elseif ($action === 'permission') {
                handlePermissionRoles();
            } else {
                handleMatrix();
            }
            break;
        case 'POST':
            handleGrant();
            break;
        case 'DELETE':
            handleRevoke();
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Get full permissions matrix
 */
function handleMatrix() {
    global $supabase;
    
    try {
        $response = $supabase->from('role_permissions')
            ->select('*')
            ->order('role')
            ->order('permission')
            ->execute();
        
        $permissions = $response->data ?? [];
        
        // Group by role
        $matrix = [];
        foreach ($permissions as $perm) {
            $role = $perm['role'];
            if (!isset($matrix[$role])) {
                $matrix[$role] = [];
            }
            $matrix[$role][] = [
                'permission' => $perm['permission'],
                'description' => $perm['description'] ?? ''
            ];
        }
        
        echo json_encode([
            'success' => true,
            'matrix' => $matrix,
            'total_permissions' => count($permissions)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Get permissions for specific role
 */
function handleRolePermissions() {
    global $supabase;
    
    $role = $_GET['role'] ?? '';
    if (!$role) {
        http_response_code(400);
        return json_encode(['error' => 'Role required']);
    }
    
    try {
        $response = $supabase->from('role_permissions')
            ->select('*')
            ->eq('role', $role)
            ->order('permission')
            ->execute();
        
        echo json_encode([
            'success' => true,
            'role' => $role,
            'permissions' => $response->data ?? []
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Get roles with specific permission
 */
function handlePermissionRoles() {
    global $supabase;
    
    $permission = $_GET['permission'] ?? '';
    if (!$permission) {
        http_response_code(400);
        return json_encode(['error' => 'Permission required']);
    }
    
    try {
        $response = $supabase->from('role_permissions')
            ->select('*')
            ->eq('permission', $permission)
            ->execute();
        
        echo json_encode([
            'success' => true,
            'permission' => $permission,
            'roles' => array_column($response->data ?? [], 'role')
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Grant permission to role
 */
function handleGrant() {
    global $supabase;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data['role'] || !$data['permission']) {
        http_response_code(400);
        return json_encode(['error' => 'Role and permission required']);
    }
    
    try {
        $perm = [
            'role' => $data['role'],
            'permission' => $data['permission'],
            'description' => $data['description'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $response = $supabase->from('role_permissions')->insert([$perm])->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Permission granted'
        ]);
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'duplicate') !== false) {
            http_response_code(409);
            return json_encode(['error' => 'Permission already exists']);
        }
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Revoke permission from role
 */
function handleRevoke() {
    global $supabase;
    
    $perm_id = $_GET['id'] ?? '';
    if (!$perm_id) {
        http_response_code(400);
        return json_encode(['error' => 'Permission ID required']);
    }
    
    try {
        $supabase->from('role_permissions')->delete()->eq('id', $perm_id)->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Permission revoked'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
