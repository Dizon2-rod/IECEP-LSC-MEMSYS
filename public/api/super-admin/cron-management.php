<?php
/**
 * Cron Job Management API
 * View and trigger scheduled tasks
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
    
    // Check if user is super admin
    $userRole = $_SESSION['user']['role'] ?? '';
    if (!in_array($userRole, ['super_admin', 'admin'])) {
        throw new Exception('Unauthorized access');
    }
    
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                // Get all cron jobs
                $cronJobs = [
                    [
                        'id' => 'check_affiliation_expiry',
                        'name' => 'Check Affiliation Expiry',
                        'description' => 'Checks for affiliations expiring in 30 days and sends reminders',
                        'schedule' => 'Daily',
                        'file' => 'cron/check_affiliation_expiry.php',
                        'last_run' => getLastRunTime('check_affiliation_expiry'),
                        'status' => 'active'
                    ],
                    [
                        'id' => 'compliance_deadline_reminders',
                        'name' => 'Compliance Deadline Reminders',
                        'description' => 'Sends reminders to institutions approaching compliance deadlines',
                        'schedule' => 'Weekly',
                        'file' => 'cron/compliance_deadline_reminders.php',
                        'last_run' => getLastRunTime('compliance_deadline_reminders'),
                        'status' => 'active'
                    ],
                    [
                        'id' => 'compliance_update',
                        'name' => 'Compliance Score Update',
                        'description' => 'Updates compliance scores based on event participation',
                        'schedule' => 'Daily',
                        'file' => 'cron/compliance_update.php',
                        'last_run' => getLastRunTime('compliance_update'),
                        'status' => 'active'
                    ],
                    [
                        'id' => 'expire_memberships',
                        'name' => 'Expire Memberships',
                        'description' => 'Expires memberships that have passed their expiry date',
                        'schedule' => 'Daily',
                        'file' => 'cron/expire_memberships.php',
                        'last_run' => getLastRunTime('expire_memberships'),
                        'status' => 'active'
                    ],
                    [
                        'id' => 'auto_renew',
                        'name' => 'Auto-Renew Memberships',
                        'description' => 'Automatically renews memberships with auto-renew enabled',
                        'schedule' => 'Daily',
                        'file' => 'api/cron-auto-renew.php',
                        'last_run' => getLastRunTime('auto_renew'),
                        'status' => 'active'
                    ],
                    [
                        'id' => 'compliance_check',
                        'name' => 'Compliance Check',
                        'description' => 'Runs compliance checks and alerts at-risk institutions',
                        'schedule' => 'Weekly',
                        'file' => 'api/cron-compliance-check.php',
                        'last_run' => getLastRunTime('compliance_check'),
                        'status' => 'active'
                    ]
                ];
                
                echo json_encode([
                    'success' => true,
                    'cron_jobs' => $cronJobs
                ]);
                
            } elseif ($action === 'logs') {
                // Get cron job execution logs
                $jobId = $_GET['job_id'] ?? '';
                
                $filters = ['order' => 'executed_at.desc', 'limit' => 50];
                if (!empty($jobId)) {
                    $filters['job_id'] = 'eq.' . $jobId;
                }
                
                $logs = $db->select('cron_logs', $filters);
                
                echo json_encode([
                    'success' => true,
                    'logs' => $logs
                ]);
                
            } else {
                throw new Exception('Invalid action');
            }
            break;
            
        case 'POST':
            if ($action === 'trigger') {
                // Manually trigger a cron job
                $input = json_decode(file_get_contents('php://input'), true);
                $jobId = $input['job_id'] ?? '';
                
                if (empty($jobId)) {
                    throw new Exception('job_id is required');
                }
                
                // Get job details
                $cronJobs = [
                    'check_affiliation_expiry' => 'cron/check_affiliation_expiry.php',
                    'compliance_deadline_reminders' => 'cron/compliance_deadline_reminders.php',
                    'compliance_update' => 'cron/compliance_update.php',
                    'expire_memberships' => 'cron/expire_memberships.php',
                    'auto_renew' => 'api/cron-auto-renew.php',
                    'compliance_check' => 'api/cron-compliance-check.php'
                ];
                
                if (!isset($cronJobs[$jobId])) {
                    throw new Exception('Invalid job_id');
                }
                
                $jobFile = $cronJobs[$jobId];
                $jobPath = __DIR__ . '/../../' . $jobFile;
                
                if (!file_exists($jobPath)) {
                    throw new Exception('Job file not found');
                }
                
                // Execute the job
                $startTime = microtime(true);
                $output = [];
                $returnCode = 0;
                
                exec('php ' . $jobPath . ' 2>&1', $output, $returnCode);
                
                $endTime = microtime(true);
                $duration = round($endTime - $startTime, 2);
                
                $success = $returnCode === 0;
                
                // Log execution
                $db->insert('cron_logs', [
                    'id' => generateUUID(),
                    'job_id' => $jobId,
                    'executed_at' => date('Y-m-d H:i:s'),
                    'duration' => $duration,
                    'success' => $success,
                    'output' => implode("\n", $output),
                    'triggered_by' => $_SESSION['user']['id'] ?? 'system'
                ]);
                
                echo json_encode([
                    'success' => true,
                    'job_id' => $jobId,
                    'success' => $success,
                    'duration' => $duration,
                    'output' => implode("\n", $output),
                    'message' => $success ? 'Job executed successfully' : 'Job execution failed'
                ]);
                
            } elseif ($action === 'toggle') {
                // Enable or disable a cron job
                $input = json_decode(file_get_contents('php://input'), true);
                $jobId = $input['job_id'] ?? '';
                $status = $input['status'] ?? 'active';
                
                if (empty($jobId)) {
                    throw new Exception('job_id is required');
                }
                
                // Update cron job status in database
                $existing = $db->select('cron_jobs', [
                    'job_id' => 'eq.' . $jobId
                ]);
                
                if (!empty($existing)) {
                    $db->update('cron_jobs', [
                        'status' => $status
                    ])->eq('id', $existing[0]['id'])->update();
                } else {
                    $db->insert('cron_jobs', [
                        'id' => generateUUID(),
                        'job_id' => $jobId,
                        'status' => $status,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => "Job {$status} successfully"
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

function getLastRunTime($jobId) {
    // In a real implementation, this would query the cron_logs table
    // For now, return a placeholder
    return date('Y-m-d H:i:s', strtotime('-1 day'));
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
