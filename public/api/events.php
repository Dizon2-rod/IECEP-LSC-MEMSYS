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
        require_role(['admin', 'super_admin', 'committee_registration']);
        require_csrf();

        $required = ['title', 'start_datetime', 'end_datetime', 'event_type'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "$field is required"]);
                exit;
            }
        }

        $data = [
            'title' => trim($_POST['title']),
            'description' => trim($_POST['description'] ?? ''),
            'event_type' => $_POST['event_type'],
            'start_datetime' => date('Y-m-d H:i:s', strtotime($_POST['start_datetime'])),
            'end_datetime' => date('Y-m-d H:i:s', strtotime($_POST['end_datetime'])),
            'location' => trim($_POST['location'] ?? ''),
            'is_online' => isset($_POST['is_online']) ? (bool)$_POST['is_online'] : false,
            'online_link' => trim($_POST['online_link'] ?? ''),
            'max_capacity' => !empty($_POST['max_capacity']) ? (int)$_POST['max_capacity'] : null,
            'registration_deadline' => !empty($_POST['registration_deadline']) ? date('Y-m-d H:i:s', strtotime($_POST['registration_deadline'])) : null,
            'fee' => floatval($_POST['fee'] ?? 0),
            'requires_payment' => isset($_POST['requires_payment']) ? (bool)$_POST['requires_payment'] : false,
            'status' => $_POST['status'] ?? 'draft',
            'created_by' => $_SESSION['user']['id'],
            'institution_id' => $_SESSION['user']['institution_id'] ?? null,
            'target_roles' => !empty($_POST['target_roles']) ? explode(',', $_POST['target_roles']) : null
        ];

        try {
            $event = $supabase->insert('events', $data);
            $eventId = is_array($event) && isset($event[0]['id']) ? $event[0]['id'] : ($event['id'] ?? null);

            if ($eventId) {
                $blockchain->record('event_created', $eventId, $data);
            }

            echo json_encode(['success' => true, 'event' => $event]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'update':
        require_role(['admin', 'super_admin', 'committee_registration']);
        require_csrf();

        $eventId = $_POST['event_id'] ?? '';
        if (empty($eventId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'event_id required']);
            exit;
        }

        $updateData = [];
        $allowedFields = ['title', 'description', 'event_type', 'start_datetime', 'end_datetime', 'location', 'is_online', 'online_link', 'max_capacity', 'registration_deadline', 'fee', 'requires_payment', 'status'];
        
        foreach ($allowedFields as $field) {
            if (isset($_POST[$field])) {
                $updateData[$field] = $_POST[$field];
            }
        }

        if (empty($updateData)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No fields to update']);
            exit;
        }

        try {
            $result = $supabase->update('events', $updateData, ['id' => $eventId]);
            $blockchain->record('event_updated', $eventId, $updateData);
            echo json_encode(['success' => true, 'event' => $result]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'delete':
        require_role(['admin', 'super_admin']);
        require_csrf();

        $eventId = $_POST['event_id'] ?? '';
        if (empty($eventId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'event_id required']);
            exit;
        }

        try {
            $supabase->delete('events', $eventId);
            $blockchain->record('event_deleted', $eventId, ['deleted_by' => $_SESSION['user']['id']]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'list':
        require_role(['admin', 'super_admin', 'committee_registration', 'member', 'school_officer']);

        $filters = [];
        if (isset($_GET['status'])) {
            $filters['status'] = 'eq.' . $_GET['status'];
        }
        if (isset($_GET['event_type'])) {
            $filters['event_type'] = 'eq.' . $_GET['event_type'];
        }
        if (isset($_GET['institution_id'])) {
            $filters['institution_id'] = 'eq.' . $_GET['institution_id'];
        }

        try {
            $events = $supabase->select('events', $filters);
            echo json_encode(['success' => true, 'events' => $events]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'get':
        require_role(['admin', 'super_admin', 'committee_registration', 'member', 'school_officer']);

        $eventId = $_GET['event_id'] ?? '';
        if (empty($eventId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'event_id required']);
            exit;
        }

        try {
            $event = $supabase->select('events', ['id' => 'eq.' . $eventId]);
            echo json_encode(['success' => true, 'event' => $event[0] ?? null]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
