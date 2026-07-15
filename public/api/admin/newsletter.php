<?php
/**
 * Newsletter System API
 * HTML newsletter composition and sending
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
                // Create new newsletter
                $input = json_decode(file_get_contents('php://input'), true);
                
                $subject = $input['subject'] ?? '';
                $content = $input['content'] ?? '';
                $targetAudience = $input['target_audience'] ?? 'all';
                $targetRoles = $input['target_roles'] ?? [];
                $targetInstitutions = $input['target_institutions'] ?? [];
                $sendImmediately = $input['send_immediately'] ?? false;
                $scheduledDate = $input['scheduled_date'] ?? '';
                $userId = $_SESSION['user']['id'] ?? '';
                
                if (empty($subject) || empty($content)) {
                    throw new Exception('subject and content are required');
                }
                
                $newsletterId = generateUUID();
                
                $result = $db->insert('newsletters', [
                    'id' => $newsletterId,
                    'subject' => $subject,
                    'content' => $content,
                    'target_audience' => $targetAudience,
                    'target_roles' => json_encode($targetRoles),
                    'target_institutions' => json_encode($targetInstitutions),
                    'status' => $sendImmediately ? 'sent' : 'draft',
                    'scheduled_date' => $scheduledDate,
                    'created_by' => $userId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'sent_at' => $sendImmediately ? date('Y-m-d H:i:s') : null
                ]);
                
                if ($sendImmediately) {
                    // Send newsletter immediately
                    sendNewsletter($newsletterId, $subject, $content, $targetAudience, $targetRoles, $targetInstitutions);
                }
                
                echo json_encode([
                    'success' => true,
                    'newsletter_id' => $newsletterId,
                    'message' => $sendImmediately ? 'Newsletter sent successfully' : 'Newsletter saved as draft'
                ]);
                
            } elseif ($action === 'send') {
                // Send a draft newsletter
                $input = json_decode(file_get_contents('php://input'), true);
                $newsletterId = $input['newsletter_id'] ?? '';
                
                if (empty($newsletterId)) {
                    throw new Exception('newsletter_id is required');
                }
                
                // Get newsletter details
                $newsletters = $db->select('newsletters', [
                    'id' => 'eq.' . $newsletterId
                ]);
                
                if (empty($newsletters)) {
                    throw new Exception('Newsletter not found');
                }
                
                $newsletter = $newsletters[0];
                
                // Parse target fields
                $targetRoles = is_string($newsletter['target_roles']) ? json_decode($newsletter['target_roles'], true) : [];
                $targetInstitutions = is_string($newsletter['target_institutions']) ? json_decode($newsletter['target_institutions'], true) : [];
                
                // Send newsletter
                sendNewsletter($newsletterId, $newsletter['subject'], $newsletter['content'], 
                    $newsletter['target_audience'], $targetRoles, $targetInstitutions);
                
                // Update status
                $db->update('newsletters', [
                    'status' => 'sent',
                    'sent_at' => date('Y-m-d H:i:s')
                ])->eq('id', $newsletterId)->update();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Newsletter sent successfully'
                ]);
                
            } else {
                throw new Exception('Invalid action');
            }
            break;
            
        case 'GET':
            if ($action === 'list') {
                // Get newsletters
                $status = $_GET['status'] ?? '';
                
                $filters = ['order' => 'created_at.desc'];
                if (!empty($status)) {
                    $filters['status'] = 'eq.' . $status;
                }
                
                $newsletters = $db->select('newsletters', $filters);
                
                // Get creator names
                foreach ($newsletters as &$newsletter) {
                    $userData = $db->select('user_profiles', [
                        'id' => 'eq.' . $newsletter['created_by'],
                        'select' => 'full_name'
                    ]);
                    $newsletter['creator_name'] = $userData[0]['full_name'] ?? 'Unknown';
                    
                    // Parse JSON fields
                    if (is_string($newsletter['target_roles'])) {
                        $newsletter['target_roles'] = json_decode($newsletter['target_roles'], true);
                    }
                    if (is_string($newsletter['target_institutions'])) {
                        $newsletter['target_institutions'] = json_decode($newsletter['target_institutions'], true);
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'newsletters' => $newsletters
                ]);
                
            } elseif ($action === 'detail') {
                // Get newsletter details
                $newsletterId = $_GET['newsletter_id'] ?? '';
                
                if (empty($newsletterId)) {
                    throw new Exception('newsletter_id parameter is required');
                }
                
                $newsletters = $db->select('newsletters', [
                    'id' => 'eq.' . $newsletterId
                ]);
                
                if (empty($newsletters)) {
                    throw new Exception('Newsletter not found');
                }
                
                $newsletter = $newsletters[0];
                
                // Parse JSON fields
                if (is_string($newsletter['target_roles'])) {
                    $newsletter['target_roles'] = json_decode($newsletter['target_roles'], true);
                }
                if (is_string($newsletter['target_institutions'])) {
                    $newsletter['target_institutions'] = json_decode($newsletter['target_institutions'], true);
                }
                
                echo json_encode([
                    'success' => true,
                    'newsletter' => $newsletter
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

function sendNewsletter($newsletterId, $subject, $content, $targetAudience, $targetRoles, $targetInstitutions) {
    global $db;
    
    // Get target recipients
    $recipients = [];
    
    if ($targetAudience === 'all') {
        // All active members
        $recipients = $db->select('user_profiles', [
            'membership_status' => 'eq.active'
        ]);
    } elseif (!empty($targetRoles)) {
        // Specific roles
        foreach ($targetRoles as $role) {
            $users = $db->select('user_profiles', [
                'role' => 'eq.' . $role
            ]);
            $recipients = array_merge($recipients, $users);
        }
    } elseif (!empty($targetInstitutions)) {
        // Specific institutions
        foreach ($targetInstitutions as $instId) {
            $members = $db->select('members', [
                'institution_id' => 'eq.' . $instId
            ]);
            $memberIds = array_column($members, 'id');
            
            foreach ($memberIds as $memberId) {
                $users = $db->select('user_profiles', [
                    'id' => 'eq.' . $memberId
                ]);
                $recipients = array_merge($recipients, $users);
            }
        }
    }
    
    // Remove duplicates
    $recipients = array_unique($recipients, SORT_REGULAR);
    
    // Send emails
    $emailService = new \App\Lib\EmailService();
    $sentCount = 0;
    
    foreach ($recipients as $recipient) {
        if (!empty($recipient['email'])) {
            $emailService->sendNewsletter(
                $recipient['email'],
                $subject,
                $content
            );
            $sentCount++;
        }
    }
    
    // Log sending
    $db->insert('newsletter_logs', [
        'id' => generateUUID(),
        'newsletter_id' => $newsletterId,
        'sent_count' => $sentCount,
        'sent_at' => date('Y-m-d H:i:s')
    ]);
    
    return $sentCount;
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
