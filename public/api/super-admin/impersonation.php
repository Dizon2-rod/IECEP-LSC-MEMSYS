<?php
require_once __DIR__ . '/bootstrap.php';
/**
 * USER IMPERSONATION - Super Admin API
 * Allow super admins to impersonate users for troubleshooting
 * Created: May 17, 2026
 */

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/auth.php';

header('Content-Type: application/json');

// Verify super_admin access
if ($_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    exit(json_encode(['error' => 'Super Admin access required']));
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

try {
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                handleListSessions();
            } elseif ($action === 'active') {
                handleActiveSessions();
            } else {
                handleListSessions();
            }
            break;
        case 'POST':
            handleStartImpersonation();
            break;
        case 'PUT':
            handleEndImpersonation();
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
 * List all impersonation sessions
 */
function handleListSessions() {
    global $supabase;
    
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 50;
    $offset = ($page - 1) * $limit;
    
    try {
        $response = $supabase->from('impersonation_sessions')
            ->select('*')
            ->order('started_at', 'desc')
            ->range($offset, $offset + $limit - 1)
            ->execute();
        
        $countResult = $supabase->from('impersonation_sessions')
            ->select('id', count: 'exact')
            ->limit(1)
            ->execute();
        
        $sessions = $response->data ?? [];
        
        // Enrich with user information
        foreach ($sessions as &$session) {
            $session['admin_user'] = getUserInfo($session['admin_user_id']);
            $session['impersonated_user'] = getUserInfo($session['impersonated_user_id']);
            $session['duration'] = calculateDuration($session['started_at'], $session['ended_at']);
        }
        
        echo json_encode([
            'success' => true,
            'sessions' => $sessions,
            'page' => $page,
            'limit' => $limit,
            'total' => $countResult->count ?? 0,
            'pages' => ceil(($countResult->count ?? 0) / $limit)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Get active impersonation sessions
 */
function handleActiveSessions() {
    global $supabase;
    
    try {
        $response = $supabase->from('impersonation_sessions')
            ->select('*')
            ->is('ended_at', true)
            ->execute();
        
        $sessions = $response->data ?? [];
        
        foreach ($sessions as &$session) {
            $session['admin_user'] = getUserInfo($session['admin_user_id']);
            $session['impersonated_user'] = getUserInfo($session['impersonated_user_id']);
            $session['duration_so_far'] = calculateDuration($session['started_at'], null);
        }
        
        echo json_encode([
            'success' => true,
            'active_sessions' => $sessions,
            'count' => count($sessions)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Start impersonation session
 */
function handleStartImpersonation() {
    global $supabase;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data['impersonated_user_id']) {
        http_response_code(400);
        return json_encode(['error' => 'impersonated_user_id required']);
    }
    
    try {
        // Verify target user exists
        $userResponse = $supabase->from('user_profiles')
            ->select('*')
            ->eq('user_id', $data['impersonated_user_id'])
            ->single()
            ->execute();
        
        if (!$userResponse->data) {
            http_response_code(404);
            return json_encode(['error' => 'User not found']);
        }
        
        // Create impersonation session
        $session = [
            'admin_user_id' => $_SESSION['user_id'],
            'impersonated_user_id' => $data['impersonated_user_id'],
            'started_at' => date('Y-m-d H:i:s'),
            'actions_taken' => [],
            'notes' => $data['notes'] ?? null
        ];
        
        $response = $supabase->from('impersonation_sessions')
            ->insert([$session])
            ->execute();
        
        // Log this action
        $supabase->from('audit_logs')->insert([
            [
                'user_id' => $_SESSION['user_id'],
                'action' => 'START_IMPERSONATION',
                'table_name' => 'impersonation_sessions',
                'record_id' => $response->data[0]['id'] ?? '',
                'details' => [
                    'impersonated_user_id' => $data['impersonated_user_id'],
                    'reason' => $data['notes'] ?? 'No reason provided'
                ],
                'created_at' => date('Y-m-d H:i:s')
            ]
        ])->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Impersonation session started',
            'session' => $response->data[0] ?? null
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * End impersonation session
 */
function handleEndImpersonation() {
    global $supabase;
    
    $session_id = $_GET['session_id'] ?? '';
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$session_id) {
        http_response_code(400);
        return json_encode(['error' => 'session_id required']);
    }
    
    try {
        // Get session details
        $sessionResponse = $supabase->from('impersonation_sessions')
            ->select('*')
            ->eq('id', $session_id)
            ->single()
            ->execute();
        
        if (!$sessionResponse->data) {
            http_response_code(404);
            return json_encode(['error' => 'Session not found']);
        }
        
        $session = $sessionResponse->data;
        
        // End the session
        $supabase->from('impersonation_sessions')
            ->update([
                'ended_at' => date('Y-m-d H:i:s'),
                'actions_taken' => $session['actions_taken'] ?? []
            ])
            ->eq('id', $session_id)
            ->execute();
        
        // Log this action
        $supabase->from('audit_logs')->insert([
            [
                'user_id' => $_SESSION['user_id'],
                'action' => 'END_IMPERSONATION',
                'table_name' => 'impersonation_sessions',
                'record_id' => $session_id,
                'details' => [
                    'impersonated_user_id' => $session['impersonated_user_id'],
                    'reason' => $data['reason'] ?? 'Session ended'
                ],
                'created_at' => date('Y-m-d H:i:s')
            ]
        ])->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Impersonation session ended',
            'duration' => calculateDuration($session['started_at'], date('Y-m-d H:i:s'))
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Get user information
 */
function getUserInfo($user_id) {
    global $supabase;
    
    try {
        $response = $supabase->from('user_profiles')
            ->select('id, full_name, email, role')
            ->eq('user_id', $user_id)
            ->single()
            ->execute();
        
        return $response->data ?? ['id' => $user_id, 'name' => 'Unknown'];
    } catch (Exception $e) {
        return ['id' => $user_id, 'name' => 'Unknown'];
    }
}

/**
 * Calculate duration between two times
 */
function calculateDuration($start, $end = null) {
    $start_time = strtotime($start);
    $end_time = $end ? strtotime($end) : time();
    
    $diff = $end_time - $start_time;
    
    $hours = floor($diff / 3600);
    $minutes = floor(($diff % 3600) / 60);
    $seconds = $diff % 60;
    
    if ($hours > 0) {
        return sprintf('%dh %dm', $hours, $minutes);
    } elseif ($minutes > 0) {
        return sprintf('%dm %ds', $minutes, $seconds);
    } else {
        return sprintf('%ds', $seconds);
    }
}
?>
