<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../autoload.php';

header('Content-Type: application/json');

// Rate limiting check
session_start();
$rateLimitKey = 'qr_checkin_' . ($_SESSION['user']['id'] ?? 'guest');
if (!isset($_SESSION[$rateLimitKey])) {
    $_SESSION[$rateLimitKey] = ['count' => 0, 'reset' => time() + 60];
}

if (time() > $_SESSION[$rateLimitKey]['reset']) {
    $_SESSION[$rateLimitKey] = ['count' => 0, 'reset' => time() + 60];
}

if ($_SESSION[$rateLimitKey]['count'] >= 10) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Rate limit exceeded. Please try again later.']);
    exit;
}

$_SESSION[$rateLimitKey]['count']++;

require_role(['admin', 'super_admin', 'committee_registration', 'school_officer']);
require_csrf();

$qrToken = $_POST['qr_token'] ?? '';
$eventId = $_POST['event_id'] ?? '';

if (empty($qrToken) || empty($eventId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'qr_token and event_id required']);
    exit;
}

$supabaseConfig = require __DIR__ . '/../../includes/supabase.php';
$supabase = new \App\Lib\SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);
$blockchain = new \App\Lib\BlockchainService($supabase);

try {
    // Find registration by QR token and event
    $registrations = $supabase->select('event_registrations', [
        'event_id' => 'eq.' . $eventId,
        'qr_token' => 'eq.' . $qrToken
    ]);

    if (empty($registrations)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Invalid QR code or event']);
        exit;
    }

    $registration = $registrations[0];

    if ($registration['status'] === 'cancelled') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Registration cancelled']);
        exit;
    }

    if (!empty($registration['checked_in_at'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Already checked in', 'checked_in_at' => $registration['checked_in_at']]);
        exit;
    }

    // Update registration
    $supabase->update('event_registrations', [
        'checked_in_at' => date('Y-m-d H:i:s'),
        'status' => 'attended'
    ], ['id' => $registration['id']]);

    // Record attendance
    $supabase->insert('attendance', [
        'event_id' => $eventId,
        'user_id' => $registration['user_id'],
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => 'checkin',
        'institution_id' => $_SESSION['user']['institution_id'] ?? null
    ]);

    // Blockchain record
    $blockchain->record('checkin', $registration['id'], [
        'event_id' => $eventId,
        'user_id' => $registration['user_id'],
        'checked_in_by' => $_SESSION['user']['id'],
        'timestamp' => date('Y-m-d H:i:s')
    ]);

    // Get user info
    $users = $supabase->select('user_profiles', ['id' => 'eq.' . $registration['user_id']]);
    $user = $users[0] ?? null;

    echo json_encode([
        'success' => true,
        'message' => 'Check-in successful',
        'user' => [
            'id' => $user['id'] ?? null,
            'name' => ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''),
            'email' => $user['email'] ?? null
        ],
        'checked_in_at' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
