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
// 2. Generate Member Unique / Dynamic Token
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
// 3. Officer Scanning Student Official ID QR (Check-In & Check-Out)
// -------------------------------------------------------------
if ($method === 'POST' && $action === 'officer_scan_student') {
    $eventId = trim($_POST['event_id'] ?? '');
    $studentQr = trim($_POST['student_qr'] ?? ($_POST['token'] ?? ''));
    $scanMode = strtolower(trim($_POST['scan_mode'] ?? 'check_in')); // 'check_in' or 'check_out'

    if (empty($eventId) || empty($studentQr)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Event ID and Student ID QR data are required.']);
        exit;
    }

    try {
        // Parse student QR payload (handles JSON, MEMSYS-ID:xxx, URLs, raw Membership IDs)
        $memberId = '';
        $scannedToken = '';

        if (str_starts_with($studentQr, '{')) {
            $parsed = json_decode($studentQr, true);
            $memberId = $parsed['member_id'] ?? ($parsed['id'] ?? ($parsed['membership_id'] ?? ''));
            $scannedToken = $parsed['token'] ?? '';
        } elseif (str_starts_with($studentQr, 'MEMSYS-ID:')) {
            $memberId = substr($studentQr, 10);
        } else {
            $memberId = $studentQr;
        }

        // Clean up URL if encoded
        if (filter_var($memberId, FILTER_VALIDATE_URL)) {
            $parts = parse_url($memberId);
            parse_str($parts['query'] ?? '', $query);
            if (!empty($query['id'])) $memberId = $query['id'];
            elseif (!empty($query['member_id'])) $memberId = $query['member_id'];
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
                'message' => "❌ Member record not found for ID '{$memberId}'. Please verify student directory."
            ]);
            exit;
        }

        $realMemberId = $memberRecord['id'];
        $studentName = $memberRecord['full_name'] ?? 'Student Member';
        $studentNumber = $memberRecord['student_number'] ?? 'N/A';
        $membershipId = $memberRecord['membership_id'] ?? 'Pending ID';

        // Fetch Event Details
        $eventData = $supabase->select('events', ['id' => 'eq.' . $eventId]);
        $event = (is_array($eventData) && !empty($eventData)) ? $eventData[0] : null;
        $eventTitle = $event['title'] ?? 'Chapter Event';

        // Check existing attendance record
        $existing = $supabase->select('event_attendees', [
            'event_id' => 'eq.' . $eventId,
            'member_id' => 'eq.' . $realMemberId
        ]);

        $attendeeRow = (is_array($existing) && !empty($existing)) ? $existing[0] : null;
        $timestamp = date('c');

        // ----------------- MODE: CHECK-IN -----------------
        if ($scanMode === 'check_in') {
            if ($attendeeRow && !empty($attendeeRow['check_in_time'])) {
                $prevCheckIn = date('M d, Y h:i A', strtotime($attendeeRow['check_in_time']));
                echo json_encode([
                    'success' => false,
                    'already_recorded' => true,
                    'scan_mode' => 'check_in',
                    'message' => "⚠️ Already Checked In! {$studentName} has already checked in at {$prevCheckIn}.",
                    'student' => [
                        'id' => $realMemberId,
                        'full_name' => $studentName,
                        'student_number' => $studentNumber,
                        'membership_id' => $membershipId,
                        'check_in_time' => $attendeeRow['check_in_time'],
                        'check_out_time' => $attendeeRow['check_out_time'] ?? null,
                        'event_title' => $eventTitle
                    ]
                ]);
                exit;
            }

            // Insert new check-in record
            $attId = bin2hex(random_bytes(16));
            $supabase->insert('event_attendees', [[
                'id' => $attId,
                'event_id' => $eventId,
                'member_id' => $realMemberId,
                'status' => 'attended',
                'check_in_time' => $timestamp,
                'created_at' => $timestamp
            ]]);

            echo json_encode([
                'success' => true,
                'already_recorded' => false,
                'scan_mode' => 'check_in',
                'message' => "✅ Check-In Verified! {$studentName} is marked PRESENT.",
                'student' => [
                    'id' => $realMemberId,
                    'full_name' => $studentName,
                    'student_number' => $studentNumber,
                    'membership_id' => $membershipId,
                    'check_in_time' => $timestamp,
                    'check_out_time' => null,
                    'event_title' => $eventTitle
                ]
            ]);
            exit;
        }

        // ----------------- MODE: CHECK-OUT -----------------
        if ($scanMode === 'check_out') {
            if (!$attendeeRow || empty($attendeeRow['check_in_time'])) {
                echo json_encode([
                    'success' => false,
                    'not_checked_in' => true,
                    'scan_mode' => 'check_out',
                    'message' => "⚠️ Cannot Check Out! {$studentName} has NOT checked in for this event yet."
                ]);
                exit;
            }

            if (!empty($attendeeRow['check_out_time'])) {
                $prevCheckOut = date('M d, Y h:i A', strtotime($attendeeRow['check_out_time']));
                echo json_encode([
                    'success' => false,
                    'already_recorded' => true,
                    'scan_mode' => 'check_out',
                    'message' => "⚠️ Already Checked Out! {$studentName} already checked out at {$prevCheckOut}.",
                    'student' => [
                        'id' => $realMemberId,
                        'full_name' => $studentName,
                        'student_number' => $studentNumber,
                        'membership_id' => $membershipId,
                        'check_in_time' => $attendeeRow['check_in_time'],
                        'check_out_time' => $attendeeRow['check_out_time'],
                        'event_title' => $eventTitle
                    ]
                ]);
                exit;
            }

            // Update with check_out_time
            $checkInTimestamp = strtotime($attendeeRow['check_in_time']);
            $checkOutTimestamp = strtotime($timestamp);
            $diffSeconds = max(0, $checkOutTimestamp - $checkInTimestamp);
            $hours = floor($diffSeconds / 3600);
            $minutes = floor(($diffSeconds % 3600) / 60);
            $durationStr = ($hours > 0 ? "{$hours}h " : "") . "{$minutes}m";

            $supabase->update('event_attendees', [
                'check_out_time' => $timestamp,
                'status' => 'attended'
            ], $attendeeRow['id']);

            echo json_encode([
                'success' => true,
                'already_recorded' => false,
                'scan_mode' => 'check_out',
                'message' => "🏁 Check-Out Recorded! {$studentName} checked out (Duration: {$durationStr}).",
                'duration' => $durationStr,
                'student' => [
                    'id' => $realMemberId,
                    'full_name' => $studentName,
                    'student_number' => $studentNumber,
                    'membership_id' => $membershipId,
                    'check_in_time' => $attendeeRow['check_in_time'],
                    'check_out_time' => $timestamp,
                    'duration' => $durationStr,
                    'event_title' => $eventTitle
                ]
            ]);
            exit;
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Attendance error: ' . $e->getMessage()]);
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
