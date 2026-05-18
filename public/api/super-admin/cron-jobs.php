<?php
/**
 * CRON JOBS MANAGER - Super Admin API
 * Manage and monitor cron jobs
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
                handleList();
            } elseif ($action === 'detail') {
                handleDetail();
            } elseif ($action === 'logs') {
                handleLogs();
            } else {
                handleList();
            }
            break;
        case 'POST':
            handleCreate();
            break;
        case 'PUT':
            handleUpdate();
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
 * List all cron jobs
 */
function handleList() {
    global $supabase;
    
    try {
        $response = $supabase->from('cron_jobs')
            ->select('*')
            ->order('job_name')
            ->execute();
        
        $jobs = $response->data ?? [];
        
        // Add status information
        foreach ($jobs as &$job) {
            $job['status'] = determineCronStatus($job);
            $job['next_run_in'] = getTimeUntilNextRun($job['next_run_at']);
        }
        
        echo json_encode([
            'success' => true,
            'jobs' => $jobs,
            'total' => count($jobs)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Get single cron job detail
 */
function handleDetail() {
    global $supabase;
    
    $job_id = $_GET['id'] ?? '';
    if (!$job_id) {
        http_response_code(400);
        return json_encode(['error' => 'Job ID required']);
    }
    
    try {
        $response = $supabase->from('cron_jobs')
            ->select('*')
            ->eq('id', $job_id)
            ->single()
            ->execute();
        
        echo json_encode([
            'success' => true,
            'job' => $response->data
        ]);
    } catch (Exception $e) {
        http_response_code(404);
        echo json_encode(['error' => 'Job not found']);
    }
}

/**
 * Get cron job execution logs
 */
function handleLogs() {
    global $supabase;
    
    $job_id = $_GET['job_id'] ?? '';
    $limit = $_GET['limit'] ?? 20;
    
    if (!$job_id) {
        http_response_code(400);
        return json_encode(['error' => 'Job ID required']);
    }
    
    try {
        $response = $supabase->from('system_logs')
            ->select('*')
            ->eq('category', 'cron')
            ->ilike('message', "%$job_id%")
            ->order('created_at', 'desc')
            ->limit($limit)
            ->execute();
        
        echo json_encode([
            'success' => true,
            'logs' => $response->data ?? []
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Create new cron job
 */
function handleCreate() {
    global $supabase;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data['job_name'] || !$data['handler_file'] || !$data['schedule']) {
        http_response_code(400);
        return json_encode(['error' => 'Missing required fields']);
    }
    
    try {
        $job = [
            'job_name' => $data['job_name'],
            'handler_file' => $data['handler_file'],
            'schedule' => $data['schedule'],
            'is_enabled' => $data['is_enabled'] ?? true,
            'description' => $data['description'] ?? null,
            'next_run_at' => calculateNextRun($data['schedule']),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $response = $supabase->from('cron_jobs')->insert([$job])->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Cron job created',
            'job' => $response->data[0] ?? null
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Update cron job
 */
function handleUpdate() {
    global $supabase;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $job_id = $_GET['id'] ?? '';
    
    if (!$job_id) {
        http_response_code(400);
        return json_encode(['error' => 'Job ID required']);
    }
    
    try {
        $update = [];
        if (isset($data['is_enabled'])) $update['is_enabled'] = $data['is_enabled'];
        if (isset($data['schedule'])) $update['schedule'] = $data['schedule'];
        if (isset($data['description'])) $update['description'] = $data['description'];
        if (isset($data['handler_file'])) $update['handler_file'] = $data['handler_file'];
        
        if (isset($data['schedule'])) {
            $update['next_run_at'] = calculateNextRun($data['schedule']);
        }
        
        $response = $supabase->from('cron_jobs')
            ->update($update)
            ->eq('id', $job_id)
            ->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Cron job updated'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Delete cron job
 */
function handleDelete() {
    global $supabase;
    
    $job_id = $_GET['id'] ?? '';
    if (!$job_id) {
        http_response_code(400);
        return json_encode(['error' => 'Job ID required']);
    }
    
    try {
        $supabase->from('cron_jobs')->delete()->eq('id', $job_id)->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Cron job deleted'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

/**
 * Determine cron job status
 */
function determineCronStatus($job) {
    if (!$job['is_enabled']) {
        return 'disabled';
    }
    
    $next_run = strtotime($job['next_run_at']);
    $now = time();
    
    if ($next_run < $now) {
        return 'overdue';
    } elseif ($next_run - $now < 3600) { // Less than 1 hour
        return 'due_soon';
    }
    
    return 'normal';
}

/**
 * Calculate time until next run
 */
function getTimeUntilNextRun($next_run_at) {
    if (!$next_run_at) {
        return 'unknown';
    }
    
    $next = strtotime($next_run_at);
    $now = time();
    $diff = $next - $now;
    
    if ($diff < 0) {
        return 'overdue';
    }
    
    if ($diff < 60) {
        return $diff . ' seconds';
    } elseif ($diff < 3600) {
        return round($diff / 60) . ' minutes';
    } elseif ($diff < 86400) {
        return round($diff / 3600) . ' hours';
    } else {
        return round($diff / 86400) . ' days';
    }
}

/**
 * Calculate next run time based on schedule
 */
function calculateNextRun($schedule) {
    // Simple schedule format: "daily", "hourly", "weekly", or cron expression
    // For production, use a proper cron parser library
    
    $now = new DateTime();
    
    switch ($schedule) {
        case 'hourly':
            $now->modify('+1 hour');
            break;
        case 'daily':
            $now->modify('+1 day');
            break;
        case 'weekly':
            $now->modify('+1 week');
            break;
        case 'monthly':
            $now->modify('+1 month');
            break;
        default:
            // Assume it's a cron expression, add 5 minutes for next check
            $now->modify('+5 minutes');
    }
    
    return $now->format('Y-m-d H:i:s');
}
?>
