<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
header('Content-Type: application/json');

$supabase = getSupabaseClient();
$secretKey = 'IECEP_LSC_ROTATING_QR_SECRET_2026';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? ($_POST['action'] ?? 'record_attendance');

// Parse JSON payload if sent as application/json
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!empty($data)) {
        $_POST = array_merge($_POST, $data);
    }
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
    }
}

// -------------------------------------------------------------
// 1. Generate 30-Second Dynamic Rolling Token for an Event
// -------------------------------------------------------------
if ($method === 'GET' && $action === 'generate_event_qr') {
    $eventId = trim($_GET['event_id'] ?? '');
    if (empty($eventId)) {
        echo json_encode(['success' => false, 'message' => 'Event ID is required']);
        exit;
    }

    $currentTime = time();
    $window = floor($currentTime / 30);
    $secondsLeft = 30 - ($currentTime % 30);
    $token = hash_hmac('sha256', "EVENT:{$eventId}:{$window}", $secretKey);

    $qrPayload = json_encode([
        'type' => 'event_attendance',
        'event_id' => $eventId,
        'window' => $window,
        'token' => $token
    ]);

    echo json_encode([
        'success' => true,
        'event_id' => $eventId,
        'window' => $window,
        'token' => $token,
        'qr_data' => $qrPayload,
        'seconds_left' => $secondsLeft,
        'server_time' => $currentTime
    ]);
    exit;
}

// -------------------------------------------------------------
// 2. Generate 30-Second Dynamic Rolling Token for an Individual Member
// -------------------------------------------------------------
if ($method === 'GET' && $action === 'generate_member_qr') {
    $memberId = trim($_GET['member_id'] ?? '');
    if (empty($memberId)) {
        echo json_encode(['success' => false, 'message' => 'Member ID is required']);
        exit;
    }

    $currentTime = time();
    $window = floor($currentTime / 30);
    $secondsLeft = 30 - ($currentTime % 30);
    $token = hash_hmac('sha256', "MEMBER:{$memberId}:{$window}", $secretKey);

    $qrPayload = json_encode([
        'type' => 'member_id_qr',
        'member_id' => $memberId,
        'window' => $window,
        'token' => $token
    ]);

    echo json_encode([
        'success' => true,
        'member_id' => $memberId,
        'window' => $window,
        'token' => $token,
        'qr_data' => $qrPayload,
        'seconds_left' => $secondsLeft,
        'server_time' => $currentTime
    ]);
    exit;
}

// -------------------------------------------------------------
// 3. Officer Scanning Student Dynamic 30-Second QR Code
// -------------------------------------------------------------
if ($method === 'POST' && $action === 'officer_scan_student') {
    $eventId = trim($_POST['event_id'] ?? '');
    $studentQr = trim($_POST['student_qr'] ?? ($_POST['token'] ?? ''));

    if (empty($eventId) || empty($studentQr)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Event ID and Student QR data are required.']);
        exit;
    }

    try {
        // Parse student QR payload
        $memberId = '';
        $scannedToken = '';
        $scannedWindow = 0;

        if (str_starts_with($studentQr, '{')) {
            $parsed = json_decode($studentQr, true);
            $memberId = $parsed['member_id'] ?? ($parsed['id'] ?? ($parsed['membership_id'] ?? ''));
            $scannedToken = $parsed['token'] ?? '';
            $scannedWindow = intval($parsed['window'] ?? 0);
        } else {
            $memberId = $studentQr;
        }

        // If a 30s token is present, validate rolling HMAC
        if (!empty($scannedToken) && !empty($memberId)) {
            $currentTime = time();
            $currentWindow = floor($currentTime / 30);
            $prevWindow = $currentWindow - 1;
            $nextWindow = $currentWindow + 1;

            $validCurrent = hash_hmac('sha256', "MEMBER:{$memberId}:{$currentWindow}", $secretKey);
            $validPrev = hash_hmac('sha256', "MEMBER:{$memberId}:{$prevWindow}", $secretKey);
            $validNext = hash_hmac('sha256', "MEMBER:{$memberId}:{$nextWindow}", $secretKey);

            $isValid = (
                hash_equals($validCurrent, $scannedToken) ||
                hash_equals($validPrev, $scannedToken) ||
                hash_equals($validNext, $scannedToken)
            );

            if (!$isValid) {
                echo json_encode([
                    'success' => false,
                    'expired' => true,
                    'message' => '❌ QR Code has expired (30-second rotation). Ask student to display their fresh live QR code.'
                ]);
                exit;
            }
        }

        // Find member in database
        $memberRecord = null;
        $resMem = $supabase->select('members', ['id' => 'eq.' . $memberId]);
        if (is_array($resMem) && !empty($resMem)) $memberRecord = $resMem[0];

        if (!$memberRecord) {
            $resMem2 = $supabase->select('members', ['membership_id' => 'eq.' . $memberId]);
            if (is_array($resMem2) && !empty($resMem2)) $memberRecord = $resMem2[0];
        }

        if (!$memberRecord) {
            $resMem3 = $supabase->select('members', ['student_number' => 'eq.' . $memberId]);
            if (is_array($resMem3) && !empty($resMem3)) $memberRecord = $resMem3[0];
        }

        if (!$memberRecord) {
            $resMem4 = $supabase->select('members', ['email' => 'eq.' . $memberId]);
            if (is_array($resMem4) && !empty($resMem4)) $memberRecord = $resMem4[0];
        }

        if (!$memberRecord) {
            echo json_encode([
                'success' => false,
                'message' => '❌ Member record not found in database for the scanned QR.'
            ]);
            exit;
        }

        $realMemberId = $memberRecord['id'];
        $studentName = $memberRecord['full_name'] ?? 'Student Member';
        $studentNumber = $memberRecord['student_number'] ?? 'N/A';
        $membershipId = $memberRecord['membership_id'] ?? 'Pending';
        $institutionId = $memberRecord['institution_id'] ?? null;

        // Fetch Event Details
        $eventData = $supabase->select('events', ['id' => 'eq.' . $eventId]);
        $event = (is_array($eventData) && !empty($eventData)) ? $eventData[0] : null;
        $eventTitle = $event['title'] ?? 'Chapter Event';

        // Check for duplicate attendance (Second scan fails!)
        $existing = $supabase->select('event_attendees', [
            'event_id' => 'eq.' . $eventId,
            'member_id' => 'eq.' . $realMemberId
        ]);

        if (is_array($existing) && !empty($existing)) {
            $prevCheckIn = $existing[0]['check_in_time'] ?? date('c');
            $formattedPrev = date('M d, Y h:i A', strtotime($prevCheckIn));
            echo json_encode([
                'success' => false,
                'already_recorded' => true,
                'message' => "⚠️ Check-in already exists! {$studentName} has already checked in at {$formattedPrev}.",
                'student' => [
                    'id' => $realMemberId,
                    'full_name' => $studentName,
                    'student_number' => $studentNumber,
                    'membership_id' => $membershipId,
                    'check_in_time' => $prevCheckIn,
                    'event_title' => $eventTitle
                ]
            ]);
            exit;
        }

        // Insert new check-in record
        $timestamp = date('c');
        $attId = bin2hex(random_bytes(16));

        $supabase->insert('event_attendees', [[
            'id' => $attId,
            'event_id' => $eventId,
            'member_id' => $realMemberId,
            'status' => 'present',
            'check_in_time' => $timestamp,
            'created_at' => $timestamp
        ]]);

        echo json_encode([
            'success' => true,
            'already_recorded' => false,
            'message' => "✅ Attendance Verified! {$studentName} is marked PRESENT.",
            'student' => [
                'id' => $realMemberId,
                'full_name' => $studentName,
                'student_number' => $studentNumber,
                'membership_id' => $membershipId,
                'check_in_time' => $timestamp,
                'event_title' => $eventTitle
            ]
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Check-in error: ' . $e->getMessage()]);
        exit;
    }
}

// -------------------------------------------------------------
// 4. Student Scanning Event Dynamic 30-Second QR Code
// -------------------------------------------------------------
if ($method === 'POST') {
    $eventId = trim($_POST['event_id'] ?? '');
    $scannedToken = trim($_POST['token'] ?? '');
    $userSession = $_SESSION['user'] ?? null;
    $memberId = $_POST['member_id'] ?? ($userSession['id'] ?? null);

    if (empty($eventId) || empty($scannedToken)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Event ID and rotating QR token are required.']);
        exit;
    }

    // Validate 30-second rotating event token (with +-1 window grace)
    $currentTime = time();
    $currentWindow = floor($currentTime / 30);
    $prevWindow = $currentWindow - 1;
    $nextWindow = $currentWindow + 1;

    $validCurrent = hash_hmac('sha256', "EVENT:{$eventId}:{$currentWindow}", $secretKey);
    $validPrev = hash_hmac('sha256', "EVENT:{$eventId}:{$prevWindow}", $secretKey);
    $validNext = hash_hmac('sha256', "EVENT:{$eventId}:{$nextWindow}", $secretKey);

    $isTokenValid = (
        hash_equals($validCurrent, $scannedToken) ||
        hash_equals($validPrev, $scannedToken) ||
        hash_equals($validNext, $scannedToken)
    );

    if (!$isTokenValid) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => '❌ QR Code has rotated (30s token expired). Please scan the current live QR code.',
            'expired' => true
        ]);
        exit;
    }

    try {
        // Resolve member record
        $memberRecord = null;
        if (!empty($memberId)) {
            $memRes = $supabase->select('members', ['id' => 'eq.' . $memberId]);
            if (is_array($memRes) && !empty($memRes)) $memberRecord = $memRes[0];
        }

        if (!$memberRecord && !empty($userSession['email'])) {
            $memRes2 = $supabase->select('members', ['email' => 'eq.' . $userSession['email']]);
            if (is_array($memRes2) && !empty($memRes2)) $memberRecord = $memRes2[0];
        }

        if (!$memberRecord) {
            echo json_encode(['success' => false, 'message' => 'Member profile not found in database.']);
            exit;
        }

        $realMemberId = $memberRecord['id'];
        $studentName = $memberRecord['full_name'] ?? 'Student Member';

        // Check duplicate attendance
        $existing = $supabase->select('event_attendees', [
            'event_id' => 'eq.' . $eventId,
            'member_id' => 'eq.' . $realMemberId
        ]);

        if (is_array($existing) && !empty($existing)) {
            $prevCheckIn = $existing[0]['check_in_time'] ?? date('c');
            $formattedPrev = date('M d, Y h:i A', strtotime($prevCheckIn));
            echo json_encode([
                'success' => false,
                'already_recorded' => true,
                'message' => "⚠️ Check-in already recorded! You were already marked present at {$formattedPrev}."
            ]);
            exit;
        }

        // Insert attendance
        $timestamp = date('c');
        $attId = bin2hex(random_bytes(16));

        $supabase->insert('event_attendees', [[
            'id' => $attId,
            'event_id' => $eventId,
            'member_id' => $realMemberId,
            'status' => 'present',
            'check_in_time' => $timestamp,
            'created_at' => $timestamp
        ]]);

        echo json_encode([
            'success' => true,
            'already_recorded' => false,
            'message' => "✅ Attendance Verified! {$studentName} is marked PRESENT.",
            'data' => [
                'student_name' => $studentName,
                'attended_at' => $timestamp,
                'status' => 'present'
            ]
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Attendance failed: ' . $e->getMessage()]);
        exit;
    }
}
