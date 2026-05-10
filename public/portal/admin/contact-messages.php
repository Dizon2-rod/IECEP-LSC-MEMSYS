<?php
$current_page = basename(__FILE__, '.php');
require_once __DIR__ . '/../auth_check.php';

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = array();
    session_unset();
    session_destroy();
    session_write_close();
    setcookie(session_name(), '', time() - 42000, '/');
    setcookie('PHPSESSID', '', time() - 42000, '/');
    header('Location: /IECEP-LSC-MEMSYS/login.php');
    exit;
}

require_role(['admin', 'eb_president']);

$user = get_user_info();

// Load Supabase configuration
require_once __DIR__ . '/../../../src/lib/SupabaseClient.php';
$config = require __DIR__ . '/../../../includes/supabase.php';
$supabase = new SupabaseClient($config['url'], $config['anon_key']);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $messageId = $_POST['message_id'];
    $status = $_POST['status'];
    try {
        $supabase->update('contact_messages', ['status' => $status], $messageId);
    } catch (Exception $e) {
        // Ignore errors
    }
}

// Fetch contact messages
try {
    $messages = $supabase->select('contact_messages', null, 'created_at', 'DESC');
} catch (Exception $e) {
    $messages = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Messages - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/dashboard.css">
    <style>
        :root {
            --navy: #0A2F6C;
            --navy-light: #1e4a8a;
            --gold: #F5A623;
            --gold-dark: #d48f1f;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-700: #334155;
            --gray-900: #0f172a;
        }
        
        .dashboard-content {
            padding: 48px;
            max-width: 1400px;
            margin: 0 auto;
            background: var(--gray-50);
            min-height: calc(100vh - 72px);
        }
        
        .main-content {
            margin-left: 260px;
            background: var(--gray-50);
        }
        
        .section-header {
            margin-bottom: 32px;
        }
        
        .section-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .section-header p {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 400;
        }
        
        .messages-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .message-card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--gray-200);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .message-card.unread {
            border-left: 4px solid var(--navy);
        }
        
        .message-card.read {
            border-left: 4px solid var(--gold);
        }
        
        .message-card.replied {
            border-left: 4px solid #10b981;
        }
        
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        
        .message-sender {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .message-sender strong {
            color: var(--gray-900);
            font-size: 1rem;
        }
        
        .message-sender span {
            color: #64748b;
            font-size: 0.875rem;
        }
        
        .message-meta {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.unread {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-badge.read {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-badge.replied {
            background: #d1fae5;
            color: #065f46;
        }
        
        .message-date {
            color: #94a3b8;
            font-size: 0.8rem;
        }
        
        .message-content {
            color: #475569;
            line-height: 1.6;
            margin-bottom: 16px;
            padding: 16px;
            background: var(--gray-50);
            border-radius: 8px;
        }
        
        .message-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.875rem;
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--gray-200);
            color: var(--gray-700);
        }
        
        .btn-outline:hover {
            background: var(--gray-100);
            border-color: var(--gray-300);
        }
        
        .empty-state {
            text-align: center;
            padding: 64px 24px;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--gray-200);
            margin-bottom: 16px;
        }
        
        .empty-state h3 {
            font-size: 1.25rem;
            color: var(--gray-900);
            margin-bottom: 8px;
        }
        
        @media (max-width: 768px) {
            .dashboard-content {
                padding: 24px;
            }
            
            .message-header {
                flex-direction: column;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="dashboard-header">
                <div class="header-content">
                    <div>
                        <h1>Contact Messages</h1>
                        <p class="welcome-message">View and manage messages from the contact form</p>
                    </div>
                    <div class="header-actions">
                        <div class="user-menu">
                            <img src="<?php echo $user['user_metadata']['avatar_url'] ?? '/IECEP-LSC-MEMSYS/public/assets/images/default-avatar.png'; ?>" alt="User Avatar" class="user-avatar">
                            <span><?php echo htmlspecialchars($user['user_metadata']['full_name'] ?? 'User'); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="dashboard-content">
                <div class="section-header">
                    <h2>Inbox</h2>
                    <p><?php echo count($messages); ?> message(s) received</p>
                </div>

                <?php if (empty($messages)): ?>
                <div class="empty-state">
                    <i class="fas fa-envelope-open"></i>
                    <h3>No messages yet</h3>
                    <p>Messages from the contact form will appear here.</p>
                </div>
                <?php else: ?>
                <div class="messages-list">
                    <?php foreach ($messages as $message): ?>
                    <div class="message-card <?php echo htmlspecialchars($message['status']); ?>">
                        <div class="message-header">
                            <div class="message-sender">
                                <strong><?php echo htmlspecialchars($message['name']); ?></strong>
                                <span><?php echo htmlspecialchars($message['email']); ?></span>
                            </div>
                            <div class="message-meta">
                                <span class="status-badge <?php echo htmlspecialchars($message['status']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($message['status'])); ?>
                                </span>
                                <span class="message-date">
                                    <?php echo date('M j, Y g:i A', strtotime($message['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="message-content">
                            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                        </div>
                        <div class="message-actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="message_id" value="<?php echo htmlspecialchars($message['id']); ?>">
                                <input type="hidden" name="status" value="read">
                                <button type="submit" class="btn btn-outline">
                                    <i class="fas fa-check"></i> Mark as Read
                                </button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="message_id" value="<?php echo htmlspecialchars($message['id']); ?>">
                                <input type="hidden" name="status" value="replied">
                                <button type="submit" class="btn btn-outline">
                                    <i class="fas fa-reply"></i> Mark as Replied
                                </button>
                            </form>
                            <a href="https://mail.google.com/mail/?view=cm&fs=1&to=<?php echo htmlspecialchars($message['email']); ?>" target="_blank" class="btn btn-outline">
                                <i class="fas fa-paper-plane"></i> Reply via Gmail
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>

