<?php
/**
 * Member Messaging System API
 * Internal member-to-member messaging
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
    
    $userId = $_SESSION['user']['id'] ?? '';
    
    switch ($method) {
        case 'POST':
            if ($action === 'send') {
                // Send a message
                $input = json_decode(file_get_contents('php://input'), true);
                
                $recipientId = $input['recipient_id'] ?? '';
                $subject = $input['subject'] ?? '';
                $content = $input['content'] ?? '';
                $threadId = $input['thread_id'] ?? '';
                
                if (empty($recipientId) || empty($content)) {
                    throw new Exception('recipient_id and content are required');
                }
                
                // Create or get thread
                if (empty($threadId)) {
                    // Check if thread exists between these users
                    $existingThreads = $db->select('message_threads', [
                        'participant_1' => 'eq.' . $userId,
                        'participant_2' => 'eq.' . $recipientId
                    ]);
                    
                    if (empty($existingThreads)) {
                        $existingThreads = $db->select('message_threads', [
                            'participant_1' => 'eq.' . $recipientId,
                            'participant_2' => 'eq.' . $userId
                        ]);
                    }
                    
                    if (!empty($existingThreads)) {
                        $threadId = $existingThreads[0]['id'];
                    } else {
                        // Create new thread
                        $threadId = generateUUID();
                        $db->insert('message_threads', [
                            'id' => $threadId,
                            'participant_1' => $userId,
                            'participant_2' => $recipientId,
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    }
                }
                
                // Create message
                $messageId = generateUUID();
                
                $db->insert('messages', [
                    'id' => $messageId,
                    'thread_id' => $threadId,
                    'sender_id' => $userId,
                    'recipient_id' => $recipientId,
                    'subject' => $subject,
                    'content' => $content,
                    'is_read' => false,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                // Update thread
                $db->update('message_threads', [
                    'last_message_at' => date('Y-m-d H:i:s'),
                    'last_message_preview' => substr($content, 0, 100)
                ])->eq('id', $threadId)->update();
                
                // Create notification for recipient
                $db->insert('notifications', [
                    'user_id' => $recipientId,
                    'title' => 'New Message',
                    'message' => 'You have received a new message',
                    'type' => 'info',
                    'action_url' => "/portal/member/messages.php?thread={$threadId}",
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                echo json_encode([
                    'success' => true,
                    'message_id' => $messageId,
                    'thread_id' => $threadId,
                    'message' => 'Message sent successfully'
                ]);
                
            } elseif ($action === 'mark-read') {
                // Mark message as read
                $input = json_decode(file_get_contents('php://input'), true);
                $messageId = $input['message_id'] ?? '';
                
                if (empty($messageId)) {
                    throw new Exception('message_id is required');
                }
                
                $db->update('messages', [
                    'is_read' => true,
                    'read_at' => date('Y-m-d H:i:s')
                ])->eq('id', $messageId)->update();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Message marked as read'
                ]);
                
            } else {
                throw new Exception('Invalid action');
            }
            break;
            
        case 'GET':
            if ($action === 'threads') {
                // Get user's message threads
                $threads = $db->select('message_threads', [
                    'or' => "(participant_1.eq.{$userId},participant_2.eq.{$userId})",
                    'order' => 'last_message_at.desc'
                ]);
                
                // Get thread details
                foreach ($threads as &$thread) {
                    // Get other participant
                    $otherId = $thread['participant_1'] === $userId ? $thread['participant_2'] : $thread['participant_1'];
                    
                    $userData = $db->select('user_profiles', [
                        'id' => 'eq.' . $otherId
                    ]);
                    
                    $thread['other_participant'] = $userData[0] ?? null;
                    
                    // Get unread count
                    $messages = $db->select('messages', [
                        'thread_id' => 'eq.' . $thread['id'],
                        'recipient_id' => 'eq.' . $userId,
                        'is_read' => 'eq.false'
                    ]);
                    
                    $thread['unread_count'] = count($messages);
                }
                
                echo json_encode([
                    'success' => true,
                    'threads' => $threads
                ]);
                
            } elseif ($action === 'messages') {
                // Get messages in a thread
                $threadId = $_GET['thread_id'] ?? '';
                
                if (empty($threadId)) {
                    throw new Exception('thread_id parameter is required');
                }
                
                // Verify user is part of thread
                $thread = $db->select('message_threads', [
                    'id' => 'eq.' . $threadId
                ]);
                
                if (empty($thread) || 
                    ($thread[0]['participant_1'] !== $userId && $thread[0]['participant_2'] !== $userId)) {
                    throw new Exception('Unauthorized access to thread');
                }
                
                $messages = $db->select('messages', [
                    'thread_id' => 'eq.' . $threadId,
                    'order' => 'created_at.asc'
                ]);
                
                // Get sender details
                foreach ($messages as &$message) {
                    $userData = $db->select('user_profiles', [
                        'id' => 'eq.' . $message['sender_id']
                    ]);
                    $message['sender'] = $userData[0] ?? null;
                }
                
                echo json_encode([
                    'success' => true,
                    'messages' => $messages
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
