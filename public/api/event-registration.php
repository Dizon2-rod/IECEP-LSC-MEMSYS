<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../autoload.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$supabaseConfig = require __DIR__ . '/../../includes/supabase.php';
$supabase = new \App\Lib\SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);
$blockchain = new \App\Lib\BlockchainService($supabase);

switch ($action) {
    case 'register':
        require_role(['member', 'admin', 'super_admin', 'school_officer']);
        require_csrf();

        $eventId = $_POST['event_id'] ?? '';
        $userId = $_SESSION['user']['id'];

        if (empty($eventId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'event_id required']);
            exit;
        }

        try {
            // Get event details
            $events = $supabase->select('events', ['id' => 'eq.' . $eventId]);
            if (empty($events)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Event not found']);
                exit;
            }
            $event = $events[0];

            // Check if already registered
            $existing = $supabase->select('event_registrations', [
                'event_id' => 'eq.' . $eventId,
                'user_id' => 'eq.' . $userId
            ]);

            if (!empty($existing)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Already registered for this event']);
                exit;
            }

            // Check capacity
            $regCount = count($supabase->select('event_registrations', [
                'event_id' => 'eq.' . $eventId,
                'status' => 'eq.registered'
            ]));

            $status = 'registered';
            if (!empty($event['max_capacity']) && $regCount >= $event['max_capacity']) {
                $status = 'waitlisted';
            }

            // Generate QR token
            $qrToken = bin2hex(random_bytes(32));

            // Insert registration
            $registration = $supabase->insert('event_registrations', [
                'event_id' => $eventId,
                'user_id' => $userId,
                'status' => $status,
                'qr_token' => $qrToken,
                'payment_status' => ($event['fee'] ?? 0) > 0 ? 'unpaid' : 'waived',
                'registered_at' => date('Y-m-d H:i:s')
            ]);

            // Record in blockchain
            $blockchain->record('event_registration', $eventId . '-' . $userId, [
                'event_id' => $eventId,
                'user_id' => $userId,
                'status' => $status
            ]);

            // Send notification
            $supabase->insert('notifications', [
                'user_id' => $userId,
                'title' => 'Event Registration Successful',
                'message' => "You have successfully registered for {$event['title']}",
                'type' => 'info',
                'created_at' => date('Y-m-d H:i:s')
            ]);

            echo json_encode([
                'success' => true,
                'registration' => $registration,
                'qr_token' => $qrToken,
                'status' => $status
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'cancel':
        require_role(['member', 'admin', 'super_admin', 'school_officer']);
        require_csrf();

        $eventId = $_POST['event_id'] ?? '';
        $userId = $_SESSION['user']['id'];

        if (empty($eventId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'event_id required']);
            exit;
        }

        try {
            $result = $supabase->update('event_registrations', 
                ['status' => 'cancelled'],
                ['event_id' => $eventId, 'user_id' => $userId]
            );

            $blockchain->record('event_cancellation', $eventId . '-' . $userId, [
                'event_id' => $eventId,
                'user_id' => $userId
            ]);

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'my_registrations':
        require_role(['member', 'admin', 'super_admin', 'school_officer']);

        $userId = $_SESSION['user']['id'];

        try {
            $registrations = $supabase->select('event_registrations', [
                'user_id' => 'eq.' . $userId
            ]);

            echo json_encode(['success' => true, 'registrations' => $registrations]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'list':
        require_role(['admin', 'super_admin', 'committee_registration']);

        $eventId = $_GET['event_id'] ?? '';
        if (empty($eventId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'event_id required']);
            exit;
        }

        try {
            $registrations = $supabase->select('event_registrations', [
                'event_id' => 'eq.' . $eventId
            ]);

            echo json_encode(['success' => true, 'registrations' => $registrations]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
