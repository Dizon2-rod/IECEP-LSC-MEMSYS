<?php
/**
 * CONTACT MESSAGES - Admin API
 * Manage contact form submissions
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
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                handleList();
            } elseif ($action === 'detail') {
                handleDetail();
            } elseif ($action === 'stats') {
                handleStats();
            } else {
                handleList();
            }
            break;
        case 'PUT':
            handleMarkAsRead();
            break;
        case 'DELETE':
            handleDelete();
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
 * List contact messages with pagination
 */
function handleList() {
    global $supabase;
    
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 20;
    $offset = ($page - 1) * $limit;
    $status = $_GET['status'] ?? ''; // unread, read, replied
    
    try {
        $query = $supabase->from('contact_messages')
            ->select('*')
            ->order('created_at', 'desc');
        
        if ($status) {
            $query->eq('status', $status);
        }
        
        $response = $query->range($offset, $offset + $limit - 1)->execute();
        $messages = $response->data ?? [];
        
        // Get total count
        $countQuery = $supabase->from('contact_messages')->select('id', count: 'exact')->limit(1);
        if ($status) $countQuery->eq('status', $status);
        $countResult = $countQuery->execute();
        
        echo json_encode([
            'success' => true,
            'messages' => $messages,
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
 * Get single message detail
 */
function handleDetail() {
    global $supabase;
    
    $msg_id = $_GET['id'] ?? '';
    if (!$msg_id) {
        http_response_code(400);
        return json_encode(['error' => 'Message ID required']);
    }
    
    try {
        $response = $supabase->from('contact_messages')
            ->select('*')
            ->eq('id', $msg_id)
            ->single()
            ->execute();
        
        // Mark as read
        if ($response->data && $response->data['status'] === 'unread') {
            $supabase->from('contact_messages')
                ->update(['status' => 'read'])
                ->eq('id', $msg_id)
                ->execute();
        }
        
        echo json_encode([
            'success' => true,
            'message' => $response->data
        ]);
    } catch (Exception $e) {
        http_response_code(404);
        echo json_encode(['error' => 'Message not found']);
    }
}

/**
 * Get statistics
 */
function handleStats() {
    global $supabase;
    
    try {
        // Count by status
        $stats = [
            'unread' => 0,
            'read' => 0,
            'replied' => 0,
            'total' => 0
        ];
        
        foreach (['unread', 'read', 'replied'] as $status) {
            $response = $supabase->from('contact_messages')
                ->select('id', count: 'exact')
                ->eq('status', $status)
                ->limit(1)
                ->execute();
            $stats[$status] = $response->count ?? 0;
        }
        
        $stats['total'] = array_sum(array_slice($stats, 0, -1));
        
        // Get latest messages
        $latest = $supabase->from('contact_messages')
            ->select('*')
            ->order('created_at', 'desc')
            ->limit(5)
            ->execute();
        
        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'latest' => $latest->data ?? []
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Mark message as read/replied
 */
function handleMarkAsRead() {
    global $supabase;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $msg_id = $_GET['id'] ?? '';
    
    if (!$msg_id) {
        http_response_code(400);
        return json_encode(['error' => 'Message ID required']);
    }
    
    try {
        $status = $data['status'] ?? 'read'; // read or replied
        
        $response = $supabase->from('contact_messages')
            ->update(['status' => $status])
            ->eq('id', $msg_id)
            ->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Status updated'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Delete message
 */
function handleDelete() {
    global $supabase;
    
    $msg_id = $_GET['id'] ?? '';
    if (!$msg_id) {
        http_response_code(400);
        return json_encode(['error' => 'Message ID required']);
    }
    
    try {
        $supabase->from('contact_messages')->delete()->eq('id', $msg_id)->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Message deleted'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
