<?php
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../../includes/supabase.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware/auth.php';
require_once __DIR__ . '/../../includes/lib/EmailService.php';

use App\Lib\Supabase;
use App\Middleware\AuthMiddleware;
use App\Lib\EmailService;

$sb = new Supabase();
$auth = new AuthMiddleware();
$emailService = new EmailService();

// Admin only access
$user = $auth->requireRole(['admin']);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            if ($method !== 'POST') { http_response_code(405); exit; }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $subject = $data['subject'] ?? '';
            $htmlContent = $data['html_content'] ?? '';
            $recipientFilter = $data['recipient_filter'] ?? 'all'; // all, members, officers
            
            if (empty($subject) || empty($htmlContent)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Subject and content are required']);
                exit;
            }
            
            // Create email blast record
            $blastResult = $sb->from('email_blasts')->insert([
                'subject' => $subject,
                'html_content' => $htmlContent,
                'status' => 'draft',
                'created_by' => $_SESSION['user']['id'] ?? null
            ], true);
            
            if ($blastResult['error']) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $blastResult['message']]);
                exit;
            }
            
            $blastId = $blastResult['data'][0]['id'];
            
            echo json_encode(['success' => true, 'data' => ['blast_id' => $blastId]]);
            break;
            
        case 'send':
            if ($method !== 'POST') { http_response_code(405); exit; }
            
            $data = json_decode(file_get_contents('php://input'), true);
            $blastId = $data['blast_id'] ?? '';
            $recipientFilter = $data['recipient_filter'] ?? 'all';
            
            if (empty($blastId)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Blast ID is required']);
                exit;
            }
            
            // Get blast details
            $blastResult = $sb->from('email_blasts')
                ->select('*')
                ->eq('id', $blastId)
                ->get(true);
            
            if ($blastResult['error'] || empty($blastResult['data'])) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Email blast not found']);
                exit;
            }
            
            $blast = $blastResult['data'][0];
            
            // Get recipients based on filter
            $recipients = [];
            if ($recipientFilter === 'all') {
                $members = $sb->from('members')->select('id, email, full_name')->get(true);
                if (!$members['error']) {
                    $recipients = $members['data'] ?? [];
                }
            } elseif ($recipientFilter === 'members') {
                $members = $sb->from('members')->select('id, email, full_name')->get(true);
                if (!$members['error']) {
                    $recipients = $members['data'] ?? [];
                }
            } elseif ($recipientFilter === 'officers') {
                $officers = $sb->from('user_profiles')
                    ->select('id, full_name')
                    ->eq('role', 'school_officer')
                    ->get(true);
                if (!$officers['error']) {
                    foreach ($officers['data'] ?? [] as $officer) {
                        $user = $sb->from('auth.users')->select('email')->eq('id', $officer['id'])->get(true);
                        if (!$user['error'] && !empty($user['data'])) {
                            $recipients[] = [
                                'id' => $officer['id'],
                                'email' => $user['data'][0]['email'],
                                'full_name' => $officer['full_name']
                            ];
                        }
                    }
                }
            }
            
            // Update blast status
            $sb->from('email_blasts')->eq('id', $blastId)->update([
                'status' => 'sent',
                'sent_at' => date('c'),
                'recipient_count' => count($recipients)
            ], true);
            
            // Send emails with tracking
            $sentCount = 0;
            $trackingCodes = [];
            
            foreach ($recipients as $recipient) {
                $trackingCode = md5($blastId . $recipient['id'] . time());
                $trackingCodes[] = $trackingCode;
                
                // Add tracking pixel to HTML
                $trackedContent = $htmlContent . 
                    '<img src="' . APP_URL . '/api/track-email.php?code=' . $trackingCode . '" width="1" height="1" style="display:none;">';
                
                // Create tracking record
                $sb->from('email_tracking')->insert([
                    'email_blast_id' => $blastId,
                    'member_id' => $recipient['id'],
                    'tracking_code' => $trackingCode
                ], true);
                
                // Send email
                $emailSent = $emailService->sendEmail(
                    $recipient['email'],
                    $blast['subject'],
                    $trackedContent
                );
                
                if ($emailSent) {
                    $sentCount++;
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'sent_count' => $sentCount,
                    'total_recipients' => count($recipients)
                ]
            ]);
            break;
            
        case 'list':
            if ($method !== 'GET') { http_response_code(405); exit; }
            
            $result = $sb->from('email_blasts')
                ->select('*')
                ->order('created_at', false)
                ->get(true);
            
            if ($result['error']) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $result['message']]);
                exit;
            }
            
            echo json_encode(['success' => true, 'data' => $result['data'] ?? []]);
            break;
            
        case 'stats':
            if ($method !== 'GET') { http_response_code(405); exit; }
            
            $blastId = $_GET['blast_id'] ?? '';
            
            if (empty($blastId)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Blast ID is required']);
                exit;
            }
            
            // Get tracking stats
            $tracking = $sb->from('email_tracking')
                ->select('*')
                ->eq('email_blast_id', $blastId)
                ->get(true);
            
            if (!$tracking['error']) {
                $total = count($tracking['data'] ?? []);
                $opened = count(array_filter($tracking['data'] ?? [], fn($t) => !empty($t['opened_at'])));
                $clicked = count(array_filter($tracking['data'] ?? [], fn($t) => !empty($t['clicked_at'])));
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'total' => $total,
                        'opened' => $opened,
                        'clicked' => $clicked,
                        'open_rate' => $total > 0 ? round(($opened / $total) * 100, 2) : 0,
                        'click_rate' => $total > 0 ? round(($clicked / $total) * 100, 2) : 0
                    ]
                ]);
            } else {
                echo json_encode(['success' => true, 'data' => ['total' => 0, 'opened' => 0, 'clicked' => 0, 'open_rate' => 0, 'click_rate' => 0]]);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
