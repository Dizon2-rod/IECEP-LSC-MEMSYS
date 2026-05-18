<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../autoload.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$supabaseConfig = require __DIR__ . '/../../includes/supabase.php';
$supabase = new \App\Lib\SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);
$blockchain = new \App\Lib\BlockchainService($supabase);

switch ($action) {
    case 'create':
        require_role(['admin', 'super_admin', 'eb_secretary_general', 'committee_creatives']);
        require_csrf();

        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');

        if (empty($title) || empty($body)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'title and body required']);
            exit;
        }

        // Sanitize HTML content
        $body = htmlspecialchars($body, ENT_QUOTES, 'UTF-8');

        $data = [
            'title' => $title,
            'body' => $body,
            'target_roles' => !empty($_POST['target_roles']) ? explode(',', $_POST['target_roles']) : null,
            'target_institutions' => !empty($_POST['target_institutions']) ? explode(',', $_POST['target_institutions']) : null,
            'is_global' => isset($_POST['is_global']) ? (bool)$_POST['is_global'] : false,
            'scheduled_at' => !empty($_POST['scheduled_at']) ? date('Y-m-d H:i:s', strtotime($_POST['scheduled_at'])) : date('Y-m-d H:i:s'),
            'expires_at' => !empty($_POST['expires_at']) ? date('Y-m-d H:i:s', strtotime($_POST['expires_at'])) : null,
            'created_by' => $_SESSION['user']['id'],
            'created_at' => date('Y-m-d H:i:s')
        ];

        try {
            $announcement = $supabase->insert('announcements', $data);
            $announcementId = is_array($announcement) && isset($announcement[0]['id']) ? $announcement[0]['id'] : ($announcement['id'] ?? null);

            if ($announcementId) {
                $blockchain->record('announcement_created', $announcementId, $data);
            }

            echo json_encode(['success' => true, 'announcement' => $announcement]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'list':
        require_role(['admin', 'super_admin', 'member', 'school_officer', 'eb_secretary_general', 'committee_creatives']);

        $filters = [];
        $userId = $_SESSION['user']['id'];
        $userRole = $_SESSION['user']['role'];
        $institutionId = $_SESSION['user']['institution_id'] ?? null;

        // Get announcements that are:
        // 1. Global OR
        // 2. Targeted to user's role OR
        // 3. Targeted to user's institution
        try {
            $allAnnouncements = $supabase->select('announcements', []);
            $filtered = [];

            foreach ($allAnnouncements as $announcement) {
                $isGlobal = $announcement['is_global'] ?? false;
                $targetRoles = $announcement['target_roles'] ?? [];
                $targetInstitutions = $announcement['target_institutions'] ?? [];

                if ($isGlobal) {
                    $filtered[] = $announcement;
                    continue;
                }

                if (!empty($targetRoles) && in_array($userRole, $targetRoles)) {
                    $filtered[] = $announcement;
                    continue;
                }

                if (!empty($targetInstitutions) && $institutionId && in_array($institutionId, $targetInstitutions)) {
                    $filtered[] = $announcement;
                    continue;
                }
            }

            echo json_encode(['success' => true, 'announcements' => $filtered]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete':
        require_role(['admin', 'super_admin', 'eb_secretary_general']);
        require_csrf();

        $announcementId = $_POST['announcement_id'] ?? '';
        if (empty($announcementId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'announcement_id required']);
            exit;
        }

        try {
            $supabase->delete('announcements', $announcementId);
            $blockchain->record('announcement_deleted', $announcementId, ['deleted_by' => $_SESSION['user']['id']]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
