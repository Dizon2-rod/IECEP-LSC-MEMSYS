<?php
require_once __DIR__ . '/../bootstrap.php';
$current_page = basename(__FILE__, '.php');
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/role-config.php';
require_once '../../src/lib/SupabaseClient.php';

// Only allow administrators and presidents to access this page.
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'eb_president', 'super_admin'])) {
    header('Location: ' . PORTAL_URL . '/login.php');
    exit;
}

$message = '';
$messageType = 'success';

$config = require __DIR__ . '/../../includes/supabase.php';
$supabase = new App\Lib\SupabaseClient($config['url'], $config['service_role_key']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? 'System Announcement');
    $body = trim($_POST['body'] ?? '');
    $target = $_POST['target'] ?? 'all';
    $link = trim($_POST['link'] ?? '/portal/dashboard.php');
    $sendPush = isset($_POST['send_push']);

    if (empty($title) || empty($body)) {
        $message = 'Please provide a title and message.';
        $messageType = 'danger';
    } else {
        try {
            $userFilters = [];
            if ($target !== 'all') {
                $userFilters['role'] = 'eq.' . $target;
            }

            $users = $supabase->select('user_profiles', $userFilters);
            if (empty($users)) {
                $message = 'No users found for the selected target group.';
                $messageType = 'warning';
            } else {
                $notifications = [];
                foreach ($users as $user) {
                    $notifications[] = [
                        'title' => $title,
                        'message' => $body,
                        'type' => 'announcement',
                        'user_id' => $user['id'] ?? null,
                        'created_by' => $_SESSION['user_id'],
                        'link' => $link,
                        'created_at' => date('Y-m-d H:i:s'),
                        'read' => false
                    ];
                }

                $supabase->insert('notifications', $notifications);

                if ($sendPush) {
                    $payload = ['title' => $title, 'body' => $body, 'data' => ['link' => $link]];
                    $context = stream_context_create([
                        'http' => [
                            'method' => 'POST',
                            'header' => "Content-Type: application/json\r\n",
                            'content' => json_encode($payload),
                            'ignore_errors' => true
                        ]
                    ]);
                    @file_get_contents(APP_URL . '/public/api/send-notification.php', false, $context);
                }

                $message = 'Announcement sent to ' . count($users) . ' users.';
                $messageType = 'success';
            }
        } catch (Exception $e) {
            error_log('Admin push error: ' . $e->getMessage());
            $message = 'Unable to send announcement at this time.';
            $messageType = 'danger';
        }
    }
}

$pageTitle = 'Announcements & Push Notifications';
include '../../includes/dashboard-layout.php';
?>

<div class="dashboard-container">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Announcements & Push Notifications</h1>
                    <p class="text-muted">Broadcast messages, create targeted announcements, and trigger push notifications.</p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType === 'success' ? 'success' : ($messageType === 'warning' ? 'warning' : 'danger') ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="title" class="form-label">Announcement Title</label>
                            <input type="text" id="title" name="title" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="body" class="form-label">Message</label>
                            <textarea id="body" name="body" class="form-control" rows="5" required></textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="target" class="form-label">Target Group</label>
                                <select id="target" name="target" class="form-select">
                                    <option value="all">All Users</option>
                                    <option value="admin">Administrators</option>
                                    <option value="eb_president">EB President</option>
                                    <option value="eb_treasurer">Treasurer</option>
                                    <option value="eb_auditor">Auditor</option>
                                    <option value="eb_secretary_general">Secretary General</option>
                                    <option value="school_officer">School Officers</option>
                                    <option value="member">Members</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="link" class="form-label">Redirect Link</label>
                                <input type="text" id="link" name="link" class="form-control" value="/portal/dashboard.php">
                            </div>
                        </div>

                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" value="1" id="send_push" name="send_push">
                            <label class="form-check-label" for="send_push">
                                Also trigger browser push notifications for active subscriptions
                            </label>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Send Announcement
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include '../../includes/footer.php'; ?>

