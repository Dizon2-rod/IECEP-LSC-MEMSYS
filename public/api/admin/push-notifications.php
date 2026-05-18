<?php
/**
 * PUSH NOTIFICATIONS - Admin API
 * Manage and send push notifications to users
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
            } elseif ($action === 'subscriptions') {
                handleSubscriptions();
            } elseif ($action === 'stats') {
                handleStats();
            } else {
                handleList();
            }
            break;
        case 'POST':
            if ($action === 'send') {
                handleSend();
            } elseif ($action === 'broadcast') {
                handleBroadcast();
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Unknown action']);
            }
            break;
        case 'PUT':
            handleUpdateSubscription();
            break;
        case 'DELETE':
            handleDeleteSubscription();
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
 * List notification logs
 */
function handleList() {
    global $supabase;
    
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 20;
    $offset = ($page - 1) * $limit;
    
    try {
        $response = $supabase->from('notification_logs')
            ->select('*')
            ->order('created_at', 'desc')
            ->range($offset, $offset + $limit - 1)
            ->execute();
        
        $countResult = $supabase->from('notification_logs')
            ->select('id', count: 'exact')
            ->limit(1)
            ->execute();
        
        echo json_encode([
            'success' => true,
            'notifications' => $response->data ?? [],
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
 * List active push subscriptions
 */
function handleSubscriptions() {
    global $supabase;
    
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 50;
    $offset = ($page - 1) * $limit;
    
    try {
        $response = $supabase->from('push_subscriptions')
            ->select('*')
            ->eq('active', true)
            ->order('created_at', 'desc')
            ->range($offset, $offset + $limit - 1)
            ->execute();
        
        $countResult = $supabase->from('push_subscriptions')
            ->select('id', count: 'exact')
            ->eq('active', true)
            ->limit(1)
            ->execute();
        
        echo json_encode([
            'success' => true,
            'subscriptions' => $response->data ?? [],
            'page' => $page,
            'limit' => $limit,
            'total' => $countResult->count ?? 0
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Get notification statistics
 */
function handleStats() {
    global $supabase;
    
    try {
        // Total subscriptions
        $subsResult = $supabase->from('push_subscriptions')
            ->select('id', count: 'exact')
            ->eq('active', true)
            ->limit(1)
            ->execute();
        
        // Total notifications sent (last 24 hours)
        $notifResult = $supabase->from('notification_logs')
            ->select('sent_count, failed_count')
            ->gte('created_at', date('Y-m-d H:i:s', strtotime('-24 hours')))
            ->execute();
        
        $total_sent = 0;
        $total_failed = 0;
        foreach ($notifResult->data ?? [] as $log) {
            $total_sent += $log['sent_count'] ?? 0;
            $total_failed += $log['failed_count'] ?? 0;
        }
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'active_subscriptions' => $subsResult->count ?? 0,
                'notifications_sent_24h' => $total_sent,
                'notifications_failed_24h' => $total_failed,
                'success_rate' => $total_sent > 0 ? round(($total_sent / ($total_sent + $total_failed)) * 100, 2) : 0
            ]
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Send notification to specific user
 */
function handleSend() {
    global $supabase;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data['user_id'] || !$data['title'] || !$data['message']) {
        http_response_code(400);
        return json_encode(['error' => 'user_id, title, and message required']);
    }
    
    try {
        // Get user's subscriptions
        $subsResponse = $supabase->from('push_subscriptions')
            ->select('*')
            ->eq('user_id', $data['user_id'])
            ->eq('active', true)
            ->execute();
        
        $subscriptions = $subsResponse->data ?? [];
        
        if (empty($subscriptions)) {
            http_response_code(400);
            return json_encode(['error' => 'No active subscriptions for user']);
        }
        
        $sent_count = 0;
        $failed_count = 0;
        
        foreach ($subscriptions as $sub) {
            try {
                // Send push notification
                sendPushNotification($sub, $data['title'], $data['message']);
                $sent_count++;
            } catch (Exception $e) {
                $failed_count++;
            }
        }
        
        // Log notification
        $logEntry = [
            'title' => $data['title'],
            'body' => $data['message'],
            'data' => $data['data'] ?? [],
            'sent_count' => $sent_count,
            'failed_count' => $failed_count,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $supabase->from('notification_logs')->insert([$logEntry])->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Notification sent',
            'sent' => $sent_count,
            'failed' => $failed_count
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Broadcast notification to multiple/all users
 */
function handleBroadcast() {
    global $supabase;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data['title'] || !$data['message']) {
        http_response_code(400);
        return json_encode(['error' => 'title and message required']);
    }
    
    try {
        $role_filter = $data['role'] ?? '';
        $institution_filter = $data['institution_id'] ?? '';
        
        // Get target subscriptions
        $query = $supabase->from('push_subscriptions')
            ->select('*')
            ->eq('active', true);
        
        // Apply filters via user_profiles
        $response = $query->execute();
        $subscriptions = $response->data ?? [];
        
        $sent_count = 0;
        $failed_count = 0;
        
        foreach ($subscriptions as $sub) {
            try {
                sendPushNotification($sub, $data['title'], $data['message']);
                $sent_count++;
            } catch (Exception $e) {
                $failed_count++;
            }
        }
        
        // Log broadcast
        $logEntry = [
            'title' => $data['title'],
            'body' => $data['message'],
            'data' => ['broadcast' => true, 'role_filter' => $role_filter],
            'sent_count' => $sent_count,
            'failed_count' => $failed_count,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $supabase->from('notification_logs')->insert([$logEntry])->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Broadcast sent',
            'sent' => $sent_count,
            'failed' => $failed_count
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Update subscription status
 */
function handleUpdateSubscription() {
    global $supabase;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $sub_id = $_GET['id'] ?? '';
    
    if (!$sub_id) {
        http_response_code(400);
        return json_encode(['error' => 'Subscription ID required']);
    }
    
    try {
        $supabase->from('push_subscriptions')
            ->update(['active' => $data['active'] ?? true])
            ->eq('id', $sub_id)
            ->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Subscription updated'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Delete subscription
 */
function handleDeleteSubscription() {
    global $supabase;
    
    $sub_id = $_GET['id'] ?? '';
    if (!$sub_id) {
        http_response_code(400);
        return json_encode(['error' => 'Subscription ID required']);
    }
    
    try {
        $supabase->from('push_subscriptions')->delete()->eq('id', $sub_id)->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Subscription deleted'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Send push notification via Web Push API
 */
function sendPushNotification($subscription, $title, $message) {
    // This is a simplified implementation
    // Full implementation would use web-push library
    
    $payload = [
        'title' => $title,
        'body' => $message,
        'icon' => '/assets/icons/icon-192x192.png',
        'badge' => '/assets/icons/badge-72x72.png',
        'vibrate' => [100, 50, 100],
        'data' => [
            'timestamp' => time(),
            'url' => '/'
        ]
    ];
    
    // In production, use: use Minishlink\WebPush\WebPush;
    // $webPush = new WebPush();
    // $webPush->sendOneNotification($subscription, json_encode($payload));
    
    return true;
}
?>
