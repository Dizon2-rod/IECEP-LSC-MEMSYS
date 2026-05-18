<?php
/**
 * AUDIT LOGS VIEWER - Super Admin API
 * View detailed audit logs with advanced filtering
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
    switch ($action) {
        case 'list':
            handleList();
            break;
        case 'detail':
            handleDetail();
            break;
        case 'search':
            handleSearch();
            break;
        case 'timeline':
            handleTimeline();
            break;
        case 'user_actions':
            handleUserActions();
            break;
        case 'entity_changes':
            handleEntityChanges();
            break;
        case 'stats':
            handleStats();
            break;
        case 'export':
            handleExport();
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * List audit logs with advanced filtering
 */
function handleList() {
    global $supabase;
    
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 50;
    $offset = ($page - 1) * $limit;
    $user_id = $_GET['user_id'] ?? '';
    $action = $_GET['filter_action'] ?? '';
    $table = $_GET['table'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    
    try {
        $query = $supabase->from('audit_logs')->select('*');
        
        if ($user_id) $query->eq('user_id', $user_id);
        if ($action) $query->ilike('action', "%$action%");
        if ($table) $query->eq('table_name', $table);
        if ($date_from) $query->gte('created_at', $date_from);
        if ($date_to) $query->lte('created_at', $date_to);
        
        $response = $query->order('created_at', 'desc')
            ->range($offset, $offset + $limit - 1)
            ->execute();
        
        $countQuery = $supabase->from('audit_logs')->select('id', count: 'exact')->limit(1);
        if ($user_id) $countQuery->eq('user_id', $user_id);
        if ($action) $countQuery->ilike('action', "%$action%");
        if ($table) $countQuery->eq('table_name', $table);
        if ($date_from) $countQuery->gte('created_at', $date_from);
        if ($date_to) $countQuery->lte('created_at', $date_to);
        $countResult = $countQuery->execute();
        
        echo json_encode([
            'success' => true,
            'logs' => $response->data ?? [],
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
 * Get single audit log detail
 */
function handleDetail() {
    global $supabase;
    
    $log_id = $_GET['id'] ?? '';
    if (!$log_id) {
        http_response_code(400);
        return json_encode(['error' => 'Log ID required']);
    }
    
    try {
        $response = $supabase->from('audit_logs')
            ->select('*')
            ->eq('id', $log_id)
            ->single()
            ->execute();
        
        echo json_encode([
            'success' => true,
            'log' => $response->data
        ]);
    } catch (Exception $e) {
        http_response_code(404);
        echo json_encode(['error' => 'Log not found']);
    }
}

/**
 * Search audit logs
 */
function handleSearch() {
    global $supabase;
    
    $query_text = $_GET['q'] ?? '';
    if (strlen($query_text) < 2) {
        http_response_code(400);
        return json_encode(['error' => 'Search query too short']);
    }
    
    try {
        $response = $supabase->from('audit_logs')
            ->select('*')
            ->or("action.ilike.%$query_text%,record_id.ilike.%$query_text%")
            ->order('created_at', 'desc')
            ->limit(100)
            ->execute();
        
        echo json_encode([
            'success' => true,
            'results' => $response->data ?? [],
            'count' => count($response->data ?? [])
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Get timeline view of actions
 */
function handleTimeline() {
    global $supabase;
    
    $entity_id = $_GET['entity_id'] ?? '';
    $entity_type = $_GET['entity_type'] ?? '';
    
    try {
        $query = $supabase->from('audit_logs')->select('*');
        
        if ($entity_id) $query->eq('record_id', $entity_id);
        if ($entity_type) $query->eq('table_name', $entity_type);
        
        $response = $query->order('created_at', 'asc')->execute();
        
        echo json_encode([
            'success' => true,
            'timeline' => $response->data ?? []
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Get all actions by a specific user
 */
function handleUserActions() {
    global $supabase;
    
    $user_id = $_GET['user_id'] ?? '';
    if (!$user_id) {
        http_response_code(400);
        return json_encode(['error' => 'User ID required']);
    }
    
    $days = $_GET['days'] ?? 30;
    
    try {
        $response = $supabase->from('audit_logs')
            ->select('*')
            ->eq('user_id', $user_id)
            ->gte('created_at', date('Y-m-d H:i:s', strtotime("-$days days")))
            ->order('created_at', 'desc')
            ->execute();
        
        $logs = $response->data ?? [];
        
        // Group by action
        $grouped = [];
        foreach ($logs as $log) {
            $action = $log['action'] ?? 'unknown';
            if (!isset($grouped[$action])) {
                $grouped[$action] = [];
            }
            $grouped[$action][] = $log;
        }
        
        echo json_encode([
            'success' => true,
            'user_id' => $user_id,
            'actions_by_type' => $grouped,
            'total_actions' => count($logs)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Get changes to a specific entity
 */
function handleEntityChanges() {
    global $supabase;
    
    $entity_id = $_GET['entity_id'] ?? '';
    $entity_type = $_GET['entity_type'] ?? '';
    
    if (!$entity_id || !$entity_type) {
        http_response_code(400);
        return json_encode(['error' => 'entity_id and entity_type required']);
    }
    
    try {
        $response = $supabase->from('audit_logs')
            ->select('*')
            ->eq('record_id', $entity_id)
            ->eq('table_name', $entity_type)
            ->order('created_at', 'asc')
            ->execute();
        
        $changes = [];
        foreach ($response->data ?? [] as $log) {
            $changes[] = [
                'timestamp' => $log['created_at'],
                'action' => $log['action'],
                'changed_by' => $log['user_id'],
                'old_values' => $log['old_data'] ?? {},
                'new_values' => $log['new_data'] ?? {}
            ];
        }
        
        echo json_encode([
            'success' => true,
            'entity_id' => $entity_id,
            'entity_type' => $entity_type,
            'changes' => $changes
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Get audit statistics
 */
function handleStats() {
    global $supabase;
    
    try {
        // Last 30 days stats
        $response = $supabase->from('audit_logs')
            ->select('action, table_name')
            ->gte('created_at', date('Y-m-d H:i:s', strtotime('-30 days')))
            ->execute();
        
        $logs = $response->data ?? [];
        
        $stats = [
            'total_actions_30d' => count($logs),
            'actions_by_type' => [],
            'tables_modified' => [],
            'top_users' => []
        ];
        
        $action_counts = [];
        $table_counts = [];
        
        foreach ($logs as $log) {
            $action = $log['action'] ?? 'unknown';
            $table = $log['table_name'] ?? 'unknown';
            
            $action_counts[$action] = ($action_counts[$action] ?? 0) + 1;
            $table_counts[$table] = ($table_counts[$table] ?? 0) + 1;
        }
        
        $stats['actions_by_type'] = $action_counts;
        $stats['tables_modified'] = $table_counts;
        
        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Export audit logs
 */
function handleExport() {
    global $supabase;
    
    $format = $_GET['format'] ?? 'csv';
    $days = $_GET['days'] ?? 30;
    
    try {
        $response = $supabase->from('audit_logs')
            ->select('*')
            ->gte('created_at', date('Y-m-d H:i:s', strtotime("-$days days")))
            ->order('created_at', 'desc')
            ->limit(50000)
            ->execute();
        
        $logs = $response->data ?? [];
        
        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d') . '.csv"');
            
            $fp = fopen('php://output', 'w');
            fputcsv($fp, ['Timestamp', 'User ID', 'Action', 'Table', 'Record ID', 'IP Address', 'Details']);
            
            foreach ($logs as $log) {
                fputcsv($fp, [
                    $log['created_at'] ?? '',
                    $log['user_id'] ?? '',
                    $log['action'] ?? '',
                    $log['table_name'] ?? '',
                    $log['record_id'] ?? '',
                    $log['ip_address'] ?? '',
                    json_encode($log['details'] ?? [])
                ]);
            }
            
            fclose($fp);
            exit;
        }
        
        echo json_encode(['success' => true, 'message' => 'Export format not implemented']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
