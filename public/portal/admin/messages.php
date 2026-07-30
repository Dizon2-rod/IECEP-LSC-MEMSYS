<?php
if (!isset($current_page)) { $current_page = basename(__FILE__, '.php'); }
require_once __DIR__ . '/../auth_check.php';
require_role(['admin', 'super_admin']);

require_once __DIR__ . '/../../includes/db.php';

$db = Database::getInstance();

$userId = $_SESSION['user']['id'];

// Get conversations
$conversations = $db->fetchAll("SELECT 
    CASE 
        WHEN m.sender_id = ? THEN m.receiver_id 
        ELSE m.sender_id 
    END as other_user_id,
    CASE 
        WHEN m.sender_id = ? THEN up_receiver.full_name 
        ELSE up_sender.full_name 
    END as other_user_name,
    MAX(m.created_at) as last_message_time,
    COUNT(CASE WHEN m.receiver_id = ? AND m.read_at IS NULL THEN 1 END) as unread_count
    FROM messages m
    LEFT JOIN user_profiles up_sender ON m.sender_id = up_sender.user_id
    LEFT JOIN user_profiles up_receiver ON m.receiver_id = up_receiver.user_id
    WHERE (m.sender_id = ? OR m.receiver_id = ?)
    GROUP BY other_user_id, other_user_name
    ORDER BY last_message_time DESC", [$userId, $userId, $userId, $userId, $userId]);

// Get messages for selected conversation
$selectedConversation = $_GET['conversation'] ?? '';
$messages = [];
if (!empty($selectedConversation)) {
    $messages = $db->fetchAll("SELECT m.*, up_sender.full_name as sender_name, up_receiver.full_name as receiver_name
        FROM messages m
        LEFT JOIN user_profiles up_sender ON m.sender_id = up_sender.user_id
        LEFT JOIN user_profiles up_receiver ON m.receiver_id = up_receiver.user_id
        WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
        ORDER BY m.created_at ASC", [$userId, $selectedConversation, $selectedConversation, $userId]);
    
    // Mark messages as read
    $db->update('messages', ['read_at' => date('Y-m-d H:i:s')], 'receiver_id = ? AND sender_id = ? AND read_at IS NULL', [$userId, $selectedConversation]);
}

// Get all members for new message
$members = $db->fetchAll("SELECT up.user_id, up.full_name, i.name as institution_name 
    FROM user_profiles up
    LEFT JOIN members m ON up.user_id = m.user_id
    LEFT JOIN institutions i ON m.institution_id = i.id
    ORDER BY up.full_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/professional.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/font-awesome.css">
    <style>
        .page-actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .messages-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 1.5rem;
            height: calc(100vh - 250px);
        }
        .conversation-list {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow-y: auto;
        }
        .conversation-item {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
            cursor: pointer;
            transition: background 0.2s;
        }
        .conversation-item:hover {
            background: var(--gray-50);
        }
        .conversation-item.active {
            background: var(--primary-navy-light);
            border-left: 3px solid var(--accent-gold);
        }
        .conversation-name {
            font-weight: 600;
            color: var(--primary-navy);
        }
        .conversation-time {
            font-size: var(--font-size-xs);
            color: var(--gray-600);
        }
        .unread-badge {
            display: inline-block;
            background: var(--accent-gold);
            color: var(--primary-navy);
            padding: 0.125rem 0.5rem;
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            font-weight: 600;
        }
        .message-area {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
        }
        .message-header {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
            font-weight: 600;
        }
        .messages-list {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
        }
        .message {
            margin-bottom: 1rem;
            max-width: 70%;
        }
        .message.sent {
            margin-left: auto;
        }
        .message-bubble {
            padding: 0.75rem 1rem;
            border-radius: var(--radius-lg);
            position: relative;
        }
        .message.sent .message-bubble {
            background: var(--primary-navy);
            color: white;
        }
        .message.received .message-bubble {
            background: var(--gray-100);
            color: var(--gray-900);
        }
        .message-time {
            font-size: var(--font-size-xs);
            opacity: 0.7;
            margin-top: 0.25rem;
        }
        .message-input {
            padding: 1rem;
            border-top: 1px solid var(--gray-200);
        }
        .message-input form {
            display: flex;
            gap: 0.5rem;
        }
        .message-input input {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
        }
        .empty-state {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--gray-600);
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            max-width: 500px;
            width: 90%;
        }
        .form-group { margin-bottom: 1rem; }
        .form-group label {
            display: block;
            font-weight: var(--font-weight-medium);
            margin-bottom: 0.5rem;
        }
        .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>Messages</h1>
                    <p class="text-gray">Communicate with chapter members</p>
                </div>
                <button onclick="openNewMessageModal()" class="btn btn-primary">
                    <i class="fas fa-plus"></i> New Message
                </button>
            </div>

            <div class="messages-container">
                <div class="conversation-list">
                    <?php if (empty($conversations)): ?>
                        <div style="padding: 2rem; text-align: center; color: var(--gray-600);">
                            No conversations yet
                        </div>
                    <?php else: ?>
                        <?php foreach ($conversations as $conv): ?>
                            <div class="conversation-item <?php echo $selectedConversation === $conv['other_user_id'] ? 'active' : ''; ?>" 
                                 onclick="selectConversation('<?php echo $conv['other_user_id']; ?>')">
                                <div class="conversation-name">
                                    <?php echo htmlspecialchars($conv['other_user_name']); ?>
                                    <?php if ($conv['unread_count'] > 0): ?>
                                        <span class="unread-badge"><?php echo $conv['unread_count']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="conversation-time">
                                    <?php echo date('M j, g:i A', strtotime($conv['last_message_time'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="message-area">
                    <?php if (empty($selectedConversation)): ?>
                        <div class="empty-state">
                            Select a conversation to view messages
                        </div>
                    <?php else: ?>
                        <div class="message-header">
                            <?php echo htmlspecialchars($messages[0]['sender_id'] == $userId ? $messages[0]['receiver_name'] : $messages[0]['sender_name'] ?? 'Conversation'); ?>
                        </div>
                        <div class="messages-list" id="messagesList">
                            <?php foreach ($messages as $msg): ?>
                                <div class="message <?php echo $msg['sender_id'] == $userId ? 'sent' : 'received'; ?>">
                                    <div class="message-bubble">
                                        <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                                        <div class="message-time">
                                            <?php echo date('g:i A', strtotime($msg['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="message-input">
                            <form onsubmit="sendMessage(event)">
                                <input type="hidden" name="receiver_id" value="<?php echo $selectedConversation; ?>">
                                <input type="text" name="content" placeholder="Type a message..." required autocomplete="off">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- New Message Modal -->
    <div class="modal" id="newMessageModal">
        <div class="modal-content">
            <h2>New Message</h2>
            <form id="newMessageForm">
                <div class="form-group">
                    <label>Recipient</label>
                    <select name="receiver_id" required>
                        <option value="">Select Member</option>
                        <?php foreach ($members as $member): ?>
                            <option value="<?php echo $member['user_id']; ?>">
                                <?php echo htmlspecialchars($member['full_name']); ?>
                                <?php if ($member['institution_name']): ?>
                                    (<?php echo htmlspecialchars($member['institution_name']); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="content" required></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="closeNewMessageModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function selectConversation(userId) {
            window.location.href = '?conversation=' + userId;
        }

        function openNewMessageModal() {
            document.getElementById('newMessageModal').classList.add('active');
        }

        function closeNewMessageModal() {
            document.getElementById('newMessageModal').classList.remove('active');
        }

        document.getElementById('newMessageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            
            fetch('<?php echo BASE_URL; ?>/api/send-message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    closeNewMessageModal();
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            });
        });

        function sendMessage(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);
            
            fetch('<?php echo BASE_URL; ?>/api/send-message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    form.reset();
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            });
        }

        const messagesList = document.getElementById('messagesList');
        if (messagesList) {
            messagesList.scrollTop = messagesList.scrollHeight;
        }
    </script>
</body>
</html>
