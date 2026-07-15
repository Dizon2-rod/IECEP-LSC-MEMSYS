<?php
require_once __DIR__ . '/bootstrap.php';
/**
 * SYSTEM LOGS VIEWER - Admin API
 * Retrieves and filters system logs
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
 * List system logs with pagination
 */
function handleList() {
    global $supabase;
    
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 50;
    $offset = ($page - 1) * $limit;
    $category = $_GET['category'] ?? '';
    $level = $_GET['level'] ?? '';
    
    try {
        $query = $supabase->from('system_logs')
            ->select('*')
            ->order('created_at', 'desc');
        
        if ($category) {
            $query->eq('category', $category);
        }
        if ($level) {
            $query->eq('log_level', $level);
        }
        
        $response = $query->range($offset, $offset + $limit - 1)->execute();
        $logs = $response->data ?? [];
        
        // Get count
        $countQuery = $supabase->from('system_logs')->select('id', count: 'exact')->limit(1);
        if ($category) $countQuery->eq('category', $category);
        if ($level) $countQuery->eq('log_level', $level);
        $countResult = $countQuery->execute();
        
        echo json_encode([
            'success' => true,
            'logs' => $logs,
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
 * Get single log detail
 */
function handleDetail() {
    global $supabase;
    
    $log_id = $_GET['id'] ?? '';
    if (!$log_id) {
        http_response_code(400);
        return json_encode(['error' => 'Log ID required']);
    }
    
    try {
        $response = $supabase->from('system_logs')->select('*')->eq('id', $log_id)->single()->execute();
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
 * Search logs
 */
function handleSearch() {
    global $supabase;
    
    $query_text = $_GET['q'] ?? '';
    if (strlen($query_text) < 3) {
        http_response_code(400);
        return json_encode(['error' => 'Search query too short']);
    }
    
    try {
        $response = $supabase->from('system_logs')
            ->select('*')
            ->ilike('message', "%$query_text%")
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
 * Get log statistics
 */
function handleStats() {
    global $supabase;
    
    try {
        // Get logs from last 24 hours grouped by level
        $response = $supabase->from('system_logs')
            ->select('log_level')
            ->gte('created_at', date('Y-m-d H:i:s', strtotime('-24 hours')))
            ->execute();
        
        $logs = $response->data ?? [];
        
        $stats = [
            'INFO' => 0,
            'WARNING' => 0,
            'ERROR' => 0,
            'CRITICAL' => 0,
            'total_24h' => count($logs)
        ];
        
        foreach ($logs as $log) {
            $level = $log['log_level'] ?? 'INFO';
            if (isset($stats[$level])) {
                $stats[$level]++;
            }
        }
        
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
 * Export logs as CSV
 */
function handleExport() {
    global $supabase;
    
    $format = $_GET['format'] ?? 'csv';
    $days = $_GET['days'] ?? 7;
    
    try {
        $response = $supabase->from('system_logs')
            ->select('*')
            ->gte('created_at', date('Y-m-d H:i:s', strtotime("-$days days")))
            ->order('created_at', 'desc')
            ->limit(10000)
            ->execute();
        
        $logs = $response->data ?? [];
        
        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="system_logs_' . date('Y-m-d') . '.csv"');
            
            $fp = fopen('php://output', 'w');
            fputcsv($fp, ['Timestamp', 'Level', 'Category', 'Message', 'Details', 'User ID', 'IP']);
            
            foreach ($logs as $log) {
                fputcsv($fp, [
                    $log['created_at'] ?? '',
                    $log['log_level'] ?? '',
                    $log['category'] ?? '',
                    $log['message'] ?? '',
                    json_encode($log['details'] ?? []),
                    $log['user_id'] ?? '',
                    $log['ip_address'] ?? ''
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
