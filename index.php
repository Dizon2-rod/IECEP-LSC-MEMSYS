<?php
// ============================================================
//  Security & CORS Headers
// ============================================================
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header("Content-Security-Policy: script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdn.skypack.dev https://connect.facebook.net; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com https://fonts.gstatic.com; img-src 'self' data: https:; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; connect-src 'self' https://cdnjs.cloudflare.com https://fonts.googleapis.com https://fcm.googleapis.com https://updates.push.services.mozilla.com https://cdn.jsdelivr.net https://*.googleapis.com https://*.supabase.co wss://*.supabase.co https://connect.facebook.net https://www.facebook.com;");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';

$facebookPageUrl = 'https://www.facebook.com/IECEPLSC';
$featuredCards = [];
try {
    $supabaseClient = getSupabaseClient();
    if ($supabaseClient) {
        $settings = $supabaseClient->select('system_settings', [
            'key' => 'eq.facebook_page_url',
            'limit' => '1'
        ]);
        if (!empty($settings[0]['value'])) {
            $facebookPageUrl = $settings[0]['value'];
        }

        $supabaseConfig = require INCLUDES_PATH . 'supabase.php';
        if (!empty($supabaseConfig['service_role_key'])) {
            $supabaseClient->setServiceRoleKey($supabaseConfig['service_role_key']);
        }

        $rawCards = $supabaseClient->select('featured_cards');
        if (is_array($rawCards) && !empty($rawCards) && isset($rawCards[0]['is_active'])) {
            $featuredCards = array_values(array_filter($rawCards, function ($card) {
                return !empty($card['is_active']);
            }));
            usort($featuredCards, function ($left, $right) {
                $leftOrder = (int)($left['sort_order'] ?? 0);
                $rightOrder = (int)($right['sort_order'] ?? 0);
                if ($leftOrder !== $rightOrder) {
                    return $leftOrder <=> $rightOrder;
                }
                return strcmp(($right['created_at'] ?? ''), ($left['created_at'] ?? ''));
            });
        } elseif (is_array($rawCards) && !empty($rawCards) && isset($rawCards['message'])) {
            error_log('Featured cards query error: ' . ($rawCards['message'] ?? 'Unknown error'));
            $featuredCards = [];
        } else {
            error_log('Featured cards: no active cards found or empty table');
            $featuredCards = [];
        }
    }
} catch (Exception $e) {
    error_log('Featured cards exception: ' . $e->getMessage());
}

// ============================================================
//  Helper: check required PHP extensions
// ============================================================
function checkRequiredExtensions(array $extensions): ?string {
    $missing = array_filter($extensions, fn($e) => !extension_loaded($e));
    return empty($missing)
        ? null
        : 'Server configuration error: Missing PHP extensions: ' . implode(', ', $missing) . '. Please contact your server administrator.';
}

// ============================================================
//  AJAX Handlers  (POST with action param)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/error.log');
    ob_start();

    register_shutdown_function(function () {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR])) {
            error_log("FATAL ERROR: {$error['message']} in {$error['file']} on line {$error['line']}");
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Server fatal error: ' . $error['message']]);
        }
    });

    header('Content-Type: application/json');

    require_once __DIR__ . '/src/lib/SupabaseClient.php';

    $action = $_POST['action'];

    // ----------------------------------------------------------
    //  Action: send_code
    // ----------------------------------------------------------
    if ($action === 'send_code') {
        if ($err = checkRequiredExtensions(['curl', 'json', 'openssl', 'mbstring'])) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => $err]);
            exit;
        }

        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
            exit;
        }

        try {
            $supabaseConfig = require __DIR__ . '/includes/supabase.php';
            $supabaseClient = new SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);

            // 1. Check if email already exists in user_profiles (Admin, School Officer, Member, etc.)
            $userProfile = $supabaseClient->select('user_profiles', ['email' => 'eq.' . $email]);
            if (is_array($userProfile) && isset($userProfile[0]) && is_array($userProfile[0])) {
                $rawRole = $userProfile[0]['role'] ?? 'user';
                $roleMap = [
                    'admin'           => 'Administrator (Admin)',
                    'super_admin'     => 'Super Administrator',
                    'school_officer'  => 'School Officer',
                    'officer'         => 'School Officer',
                    'school_admin'    => 'School Officer',
                    'member'          => 'Student Member',
                    'student'         => 'Student Member',
                    'treasurer'       => 'Treasurer',
                    'auditor'         => 'Auditor',
                    'board_member'    => 'Executive Board Member',
                    'executive_board' => 'Executive Board Member'
                ];
                $formattedRole = $roleMap[strtolower($rawRole)] ?? ucwords(str_replace('_', ' ', $rawRole));
                
                ob_end_clean();
                echo json_encode([
                    'success' => false,
                    'message' => "This email is already registered in the system as a {$formattedRole}. Affiliation applications cannot use an existing account email."
                ]);
                exit;
            }

            // 2. Check if email exists in members table
            $existingMember = $supabaseClient->select('members', ['email' => 'eq.' . $email]);
            if (is_array($existingMember) && isset($existingMember[0]) && is_array($existingMember[0])) {
                ob_end_clean();
                echo json_encode([
                    'success' => false,
                    'message' => "This email is already registered in the system as a Student Member. Affiliation applications cannot use an existing member email."
                ]);
                exit;
            }

            // 3. Block active applications (pending or under_review)
            $existing = $supabaseClient->select('pending_affiliations', ['email' => 'eq.' . $email]);
            if (is_array($existing) && isset($existing[0]) && is_array($existing[0])) {
                $status = $existing[0]['status'] ?? '';
                if (in_array($status, ['pending', 'under_review'])) {
                    $message = 'This email is already associated with an active affiliation application (Status: Under Review). Please check your application status or contact the IECEP - LSC.';
                    ob_end_clean();
                    echo json_encode(['success' => false, 'message' => $message, 'resubmit_available' => true]);
                    exit;
                }
            }

            // Generate & store 6-digit code
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $cleanEmail = strtolower(trim($email));
            $_SESSION['verification_code']  = $code;
            $_SESSION['verification_email'] = $cleanEmail;
            $_SESSION['code_sent_time']     = time();

            try {
                $supabaseClient->insert('verification_codes', [
                    'email'      => $cleanEmail,
                    'code'       => $code,
                    'purpose'    => 'affiliation',
                    'expires_at' => date('c', time() + 600),
                    'used'       => false
                ]);
            } catch (Exception $e) {
                error_log("Supabase store fallback: " . $e->getMessage());
            }

            $emailSent  = false;
            $emailError = null;
            try {
                require_once __DIR__ . '/includes/config.php';
                require_once __DIR__ . '/src/lib/EmailService.php';
                $emailService = new \App\Lib\EmailService();
                $emailSent    = $emailService->sendVerificationCode($cleanEmail, $code);
                if (!$emailSent) {
                    $emailError = $emailService->getLastError() ?: 'SMTP connection error.';
                }
            } catch (Exception $e) {
                $emailError = $e->getMessage();
                error_log("Email send error: $emailError\n" . $e->getTraceAsString());
            }

            $response = ['success' => true, 'message' => 'Verification code sent to your email!'];
            if (!$emailSent) {
                $response['code']    = $code; // dev fallback for testability
                $response['message'] = $emailError
                    ? 'Verification code generated. Email delivery notice: ' . $emailError
                    : 'Verification code generated (email delivery pending - code shown for testing)';
            }

            ob_end_clean();
            echo json_encode($response);
        } catch (Exception $e) {
            error_log("send_code error: " . $e->getMessage());
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
        }
        exit;
    }

    // ----------------------------------------------------------
    //  Action: verify_code
    // ----------------------------------------------------------
    if ($action === 'verify_code') {
        if ($err = checkRequiredExtensions(['curl', 'json'])) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => $err]);
            exit;
        }

        $email    = strtolower(trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL)));
        $code     = trim($_POST['code'] ?? '');
        $verified = false;

        // 1. Session check
        if (
            isset($_SESSION['verification_code'], $_SESSION['verification_email']) &&
            time() - ($_SESSION['code_sent_time'] ?? 0) < 660 &&
            trim((string)$_SESSION['verification_code']) === $code &&
            strtolower(trim((string)$_SESSION['verification_email'])) === $email
        ) {
            unset($_SESSION['verification_code']);
            unset($_SESSION['verification_email']);
            unset($_SESSION['code_sent_time']);
            $verified = true;
        }

        // 2. Supabase fallback
        if (!$verified) {
            try {
                $supabaseConfig = require __DIR__ . '/includes/supabase.php';
                $supabaseClient = new SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);
                $records = $supabaseClient->select('verification_codes', [
                    'email' => 'eq.' . $email,
                    'code'  => 'eq.' . $code,
                    'order' => 'created_at.desc',
                    'limit' => 5
                ]);
                if (!empty($records) && is_array($records)) {
                    foreach ($records as $row) {
                        if (($row['code'] ?? '') === $code) {
                            $isUsed = !empty($row['used']) || !empty($row['used_at']);
                            if ($isUsed) {
                                continue;
                            }

                            $expiresAtStr = $row['expires_at'] ?? '';
                            $expiresTimestamp = !empty($expiresAtStr) ? strtotime($expiresAtStr) : 0;
                            $createdTimestamp = !empty($row['created_at']) ? strtotime($row['created_at']) : 0;

                            if (($expiresTimestamp > time() - 30) || ($createdTimestamp > 0 && (time() - $createdTimestamp) < 660)) {
                                try {
                                    $supabaseClient->update('verification_codes', ['used' => true], $row['id']);
                                } catch (\Throwable $ue) {
                                    error_log("Failed to update verification_codes used: " . $ue->getMessage());
                                }
                                unset($_SESSION['verification_code']);
                                unset($_SESSION['verification_email']);
                                unset($_SESSION['code_sent_time']);
                                $verified = true;
                                break;
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Supabase verify fallback: " . $e->getMessage());
            }
        }

        ob_end_clean();
        if ($verified) {
            $_SESSION['verified_email'] = $email;
            echo json_encode(['success' => true, 'message' => 'Email verified successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code.']);
        }
        exit;
    }

    // ----------------------------------------------------------
    //  Action: submit_affiliation
    // ----------------------------------------------------------
    if ($action === 'submit_affiliation') {
        if ($err = checkRequiredExtensions(['curl', 'json'])) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => $err]);
            exit;
        }

        try {
            require_once __DIR__ . '/includes/config.php';
            $supabaseConfig = require __DIR__ . '/includes/supabase.php';
            $supabaseClient = new SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);

            $contactEmail      = filter_var($_POST['contact_email'] ?? '', FILTER_SANITIZE_EMAIL);
            $institutionName   = trim($_POST['institution_name'] ?? '');
            $institutionAddress = trim($_POST['institution_address'] ?? '');
            $contactName       = trim($_POST['contact_name'] ?? '');
            $contactPosition   = trim($_POST['contact_position'] ?? '');
            $contactPhone      = trim($_POST['contact_phone'] ?? '');

            // Validate required fields
            if (!$contactEmail || !$institutionName || !$institutionAddress || !$contactName || !$contactPosition || !$contactPhone) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'All fields are required.']);
                exit;
            }

            if (!preg_match('/^09\d{9}$/', $contactPhone)) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Phone number must be 11 digits starting with 09.']);
                exit;
            }

            // Redirect to proper API endpoint for complete processing (file uploads handled there)
            ob_end_clean();
            echo json_encode([
                'success'  => true,
                'redirect' => BASE_URL . '/public/api/submit-affiliation.php',
                'message'  => 'Processing application...',
            ]);
        } catch (Exception $e) {
            error_log("submit_affiliation error: " . $e->getMessage());
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Failed to submit application: ' . $e->getMessage()]);
        }
        exit;
    }

    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

// ============================================================
//  Page Rendering Setup
// ============================================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/supabase.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

$contactSuccess = isset($_GET['contact']) && $_GET['contact'] === 'success';
$contactError   = isset($_GET['contact']) && $_GET['contact'] === 'error';

// Role-based redirect for logged-in users
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (strpos($currentPath, 'apply.php') === false) {
        $role = $_SESSION['user']['role'] ?? ($_SESSION['role'] ?? 'member');
        $redirectUrl = function_exists('get_role_dashboard_url')
            ? get_role_dashboard_url($role)
            : PORTAL_URL . '/member/dashboard.php';
        header('Location: ' . $redirectUrl);
        exit;
    }
}

// ============================================================
//  Fetch Member Statistics from Supabase
// ============================================================
require_once __DIR__ . '/src/lib/SupabaseClient.php';
$memberStats = ['total' => 0, 'new' => 0, 'old' => 0, 'schools' => 0];
try {
    $config = require __DIR__ . '/includes/supabase.php';
    $sb     = new SupabaseClient($config['url'], $config['anon_key']);

    // Total active members
    $allMembers = $sb->select('members');
    if (is_array($allMembers)) {
        $memberStats['total'] = count($allMembers);

        foreach ($allMembers as $member) {
            $type = strtolower(trim($member['member_type'] ?? $member['status'] ?? ''));
            if ($type === 'new') {
                $memberStats['new']++;
            } elseif (in_array($type, ['old', 'renewing', 'renewal'])) {
                $memberStats['old']++;
            }
        }
    }

    // Affiliated schools count
    $schools = $sb->select('pending_affiliations', ['status' => 'eq.approved']);
    if (is_array($schools)) {
        $memberStats['schools'] = count($schools);
    }
} catch (Exception $e) {
    error_log("Member stats error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>IECEP-LSC MEMSYS | Membership &amp; Affiliation Management System</title>
    <?php include __DIR__ . '/includes/head-meta.php'; ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(PUBLIC_URL, ENT_QUOTES) ?>/assets/css/styles.css">
    <style>
        /* ── Responsive overrides ─────────────────────────────────────────── */
        @media (max-width: 575.98px) {
            .modal-content { margin: 1rem; max-width: calc(100% - 2rem); max-height: 90vh; border-radius: var(--radius-lg); }
            #affiliateModal .modal-content { margin: auto !important; max-width: 95% !important; }
            .modal-title { font-size: 1.5rem; margin-bottom: 1.5rem; }
            .step-indicator { display: flex; flex-direction: row; align-items: center; justify-content: center; gap: 0.4rem; margin-bottom: 1.25rem; }
            .step-indicator-item { width: auto; justify-content: center; flex-direction: column; align-items: center; }
            .step-indicator-number { width: 32px; height: 32px; font-size: 0.85rem; }
            .step-indicator-item span { font-size: 0.68rem; text-align: center; }
            .step-indicator-line { flex: 1; max-width: 40px; min-width: 12px; height: 2px; transform: none; background: #e2e8f0; }
            .form-input, .form-textarea { font-size: 16px; }
            .verification-inputs { gap: 0.5rem; }
            .verification-inputs input { width: 40px; height: 45px; font-size: 1.25rem; }
        }

        @media (min-width: 576px) and (max-width: 767.98px) {
            .modal-content { margin: 2rem; max-width: calc(100% - 4rem); }
            #affiliateModal .modal-content { margin: auto !important; max-width: 90% !important; }
            .verification-inputs input { width: 45px; height: 50px; }
        }

        @media (hover: none) and (pointer: coarse) {
            .btn-large, .mobile-cta-login, .hero-btn { min-height: 44px; }
        }

        /* ── Member Stats Section ─────────────────────────────────────────── */
        .member-stats-section {
            background: linear-gradient(135deg, #0B1D4A 0%, #142a6b 100%);
            padding: 4rem 0;
        }

        .member-stats-section .section-title {
            color: #fff;
            text-align: center;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .member-stats-section .section-subtitle {
            color: rgba(255,255,255,0.7);
            text-align: center;
            font-size: 1rem;
            margin-bottom: 3rem;
        }

        .card-description {
            line-height: 1.6;
            font-size: 0.95rem;
            color: #334155;
            margin: 0 0 1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .stat-card {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 16px;
            padding: 2rem 1.5rem;
            text-align: center;
            backdrop-filter: blur(10px);
            transition: transform 0.3s ease, background 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
        }

        .stat-card.total::before   { background: linear-gradient(90deg, #C49A00, #D4AF37); }
        .stat-card.new::before     { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
        .stat-card.old::before     { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
        .stat-card.schools::before { background: linear-gradient(90deg, #10b981, #34d399); }

        .stat-card:hover {
            transform: translateY(-4px);
            background: rgba(255,255,255,0.13);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 1rem;
        }

        .stat-card.total   .stat-icon { background: rgba(196,154,0,0.2);  color: #D4AF37; }
        .stat-card.new     .stat-icon { background: rgba(59,130,246,0.2); color: #60a5fa; }
        .stat-card.old     .stat-icon { background: rgba(245,158,11,0.2); color: #fbbf24; }
        .stat-card.schools .stat-icon { background: rgba(16,185,129,0.2); color: #34d399; }

        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            color: #fff;
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: rgba(255,255,255,0.7);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            margin-top: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
        }

        .stat-card.new .stat-badge    { background: rgba(59,130,246,0.2); color: #93c5fd; }
        .stat-card.old .stat-badge    { background: rgba(245,158,11,0.2); color: #fcd34d; }
        .stat-card.total .stat-badge  { background: rgba(196,154,0,0.2);  color: #D4AF37; }
        .stat-card.schools .stat-badge{ background: rgba(16,185,129,0.2); color: #6ee7b7; }

        /* Counter animation */
        .stat-number[data-target] { transition: all 0.3s; }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .stat-number { font-size: 2.5rem; }
        }

        /* ── Section Header Pill ── */
        .section-badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(11, 29, 74, 0.06);
            color: #0B1D4A;
            padding: 0.35rem 1rem;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 0.5rem;
            border: 1px solid rgba(11, 29, 74, 0.12);
        }
        .section-badge-pill i {
            color: #D4AF37;
        }

        /* ── Section Header Typography (Responsive SaaS) ── */
        .section-heading {
            margin-bottom: 2rem;
        }
        .section-title {
            font-size: 2.2rem;
            font-weight: 800;
            color: #0B1D4A;
            margin-bottom: 0.65rem;
            font-family: 'Times New Roman', Arial, serif;
            line-height: 1.2;
        }
        .section-subtitle {
            max-width: 680px;
            margin: 0 auto;
            color: #64748b;
            font-size: 1rem;
            line-height: 1.6;
        }
        @media (max-width: 768px) {
            .section-heading {
                margin-bottom: 1.25rem !important;
            }
            .section-title {
                font-size: 1.35rem !important;
                margin-bottom: 0.35rem !important;
                line-height: 1.25 !important;
            }
            .section-subtitle {
                font-size: 0.78rem !important;
                line-height: 1.45 !important;
                padding: 0 0.5rem !important;
            }
        }

        /* ── What's New Card Enhancements ── */
        .whats-new {
            background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
            padding: 2.75rem 0 2.25rem;
            position: relative;
        }
        .featured-cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.25rem;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        @media (max-width: 768px) {
            .featured-cards-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
                gap: 0.35rem !important;
                padding: 0 0.25rem !important;
            }
            .featured-card {
                border-radius: 8px !important;
                border: 1px solid #E2E8F0 !important;
            }
            .featured-card-image {
                height: 145px !important;
            }
            .featured-card-image-placeholder {
                font-size: 1.75rem !important;
            }
            .featured-card-badge {
                font-size: 0.42rem !important;
                padding: 1px 3px !important;
                top: 4px !important;
                left: 4px !important;
                gap: 2px !important;
            }
            .featured-card-body {
                padding: 0.25rem 0.22rem 0.3rem !important;
            }
            .featured-card-meta {
                font-size: 0.42rem !important;
                margin-bottom: 0.1rem !important;
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 1px !important;
            }
            .featured-card-meta span {
                gap: 1px !important;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                max-width: 100%;
            }
            .featured-card-title {
                font-size: 0.52rem !important;
                line-height: 1.15 !important;
                margin: 0 0 0.1rem !important;
                font-weight: 700 !important;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
            .featured-card-description {
                font-size: 0.44rem !important;
                line-height: 1.2 !important;
                margin: 0 0 0.2rem !important;
                -webkit-line-clamp: 2 !important;
            }
            .featured-card-footer {
                padding-top: 0.15rem !important;
                gap: 0.1rem !important;
            }
            .btn-view-card {
                font-size: 0.44rem !important;
                padding: 0.15rem 0.2rem !important;
                border-radius: 3px !important;
                gap: 2px !important;
                white-space: nowrap;
            }
            .btn-link-card {
                font-size: 0.44rem !important;
                padding: 0.15rem 0.2rem !important;
                border-radius: 3px !important;
            }
        }
        @media (min-width: 640px) and (max-width: 991.98px) {
            .featured-cards-grid { grid-template-columns: repeat(3, 1fr); gap: 0.75rem; }
        }
        @media (min-width: 992px) {
            .featured-cards-grid { grid-template-columns: repeat(3, 1fr); }
        }
        .featured-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(11,29,74,0.05), 0 1px 3px rgba(0,0,0,0.03);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.35s cubic-bezier(0.25,0.46,0.45,0.94);
            border: 1px solid rgba(226,232,240,0.9);
            position: relative;
            cursor: pointer;
        }
        .featured-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 32px rgba(11,29,74,0.1), 0 0 0 2px rgba(212,175,55,0.4);
            border-color: rgba(212,175,55,0.5);
        }
        .featured-card-image {
            position: relative;
            width: 100%;
            height: 190px;
            overflow: hidden;
            background: #0B1D4A;
        }
        .featured-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.5s cubic-bezier(0.25,0.46,0.45,0.94);
        }
        .featured-card:hover .featured-card-image img {
            transform: scale(1.08);
        }
        .featured-card-image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0B1D4A 0%, #1E3A6E 50%, #0B1D4A 100%);
            color: rgba(255,255,255,0.7);
            font-size: 2.75rem;
            position: relative;
        }
        .featured-card-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            background: rgba(11, 29, 74, 0.85);
            backdrop-filter: blur(8px);
            color: #D4AF37;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 0.3rem 0.7rem;
            border-radius: 999px;
            border: 1px solid rgba(212, 175, 55, 0.35);
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 0.35rem;
            letter-spacing: 0.04em;
        }
        .featured-card-quick-view {
            position: absolute;
            inset: 0;
            background: rgba(11, 29, 74, 0.55);
            backdrop-filter: blur(2px);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 3;
        }
        .featured-card:hover .featured-card-quick-view {
            opacity: 1;
        }
        .btn-quick-preview {
            background: #fff;
            color: #0B1D4A;
            font-weight: 700;
            font-size: 0.82rem;
            padding: 0.5rem 1.1rem;
            border-radius: 999px;
            border: none;
            display: flex;
            align-items: center;
            gap: 0.45rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.25);
            transform: translateY(8px);
            transition: transform 0.3s ease, background 0.2s ease;
        }
        .featured-card:hover .btn-quick-preview {
            transform: translateY(0);
        }
        .btn-quick-preview:hover {
            background: #D4AF37;
            color: #0B1D4A;
        }
        .featured-card-body {
            padding: 1.15rem 1.15rem 1.25rem;
            display: flex;
            flex-direction: column;
            flex: 1;
            text-align: left;
            background: #fff;
        }
        .featured-card-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            font-size: 0.78rem;
            color: #64748b;
            margin-bottom: 0.5rem;
        }
        .featured-card-meta span {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        .featured-card-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #0B1D4A;
            margin: 0 0 0.45rem;
            line-height: 1.35;
            transition: color 0.2s ease;
        }
        .featured-card:hover .featured-card-title {
            color: #1E3A6E;
        }
        .featured-card-description {
            font-size: 0.88rem;
            color: #475569;
            line-height: 1.55;
            margin: 0 0 0.75rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex: 1;
        }
        .featured-card-footer {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            margin-top: auto;
            padding-top: 0.75rem;
            border-top: 1px solid #f1f5f9;
        }
        .btn-view-card {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            background: #f8fafc;
            color: #0B1D4A;
            font-weight: 700;
            font-size: 0.82rem;
            padding: 0.55rem 0.85rem;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-view-card:hover {
            background: #0B1D4A;
            color: #fff;
            border-color: #0B1D4A;
        }
        .btn-link-card {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: rgba(212, 175, 55, 0.15);
            color: #B8960C;
            border: 1px solid rgba(212, 175, 55, 0.3);
            transition: all 0.2s ease;
            text-decoration: none;
            flex-shrink: 0;
            font-size: 0.85rem;
        }
        .btn-link-card:hover {
            background: #D4AF37;
            color: #0B1D4A;
            transform: scale(1.05);
        }

        /* ── How to Affiliate Section Enhancements ── */
        .how-to-affiliate {
            background: #ffffff;
            padding: 2.75rem 0 3rem;
            position: relative;
        }
        .affiliate-steps-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            max-width: 1200px;
            margin: 0 auto 2rem;
        }
        @media (max-width: 768px) {
            .affiliate-steps-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
                gap: 0.3rem !important;
                margin: 0 auto 1.25rem !important;
                padding: 0 0.25rem !important;
            }
            .affiliate-step-card {
                padding: 0.45rem 0.3rem !important;
                border-radius: 8px !important;
            }
            .affiliate-step-header {
                margin-bottom: 0.25rem !important;
            }
            .step-badge-number {
                font-size: 0.52rem !important;
                padding: 1px 4px !important;
                letter-spacing: 0.02em !important;
                font-weight: 700 !important;
            }
            .affiliate-step-title {
                font-size: 0.65rem !important;
                margin: 0 0 0.15rem !important;
                line-height: 1.15 !important;
                font-weight: 700 !important;
            }
            .affiliate-step-desc {
                font-size: 0.54rem !important;
                line-height: 1.2 !important;
                color: #64748b !important;
                margin: 0 !important;
            }
        }
        @media (min-width: 640px) and (max-width: 1023px) {
            .affiliate-steps-grid { grid-template-columns: repeat(4, 1fr); gap: 0.65rem; }
        }
        @media (min-width: 1024px) {
            .affiliate-steps-grid { grid-template-columns: repeat(4, 1fr); }
        }
        .affiliate-step-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 1.35rem 1.25rem;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            transition: all 0.35s ease;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }
        .affiliate-step-card:hover {
            transform: translateY(-5px);
            border-color: #D4AF37;
            box-shadow: 0 12px 24px rgba(11, 29, 74, 0.07), 0 0 0 1px #D4AF37;
        }
        .affiliate-step-header {
            display: flex;
            align-items: center;
            width: 100%;
            margin-bottom: 0.75rem;
        }
        .step-badge-number {
            font-size: 0.78rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #0B1D4A;
            background: rgba(11, 29, 74, 0.07);
            padding: 0.3rem 0.75rem;
            border-radius: 999px;
            border: 1px solid rgba(11, 29, 74, 0.12);
            transition: all 0.25s ease;
        }
        .affiliate-step-card:hover .step-badge-number {
            background: #D4AF37;
            color: #0B1D4A;
            border-color: #D4AF37;
        }
        .affiliate-step-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0B1D4A;
            margin: 0 0 0.35rem;
            line-height: 1.3;
        }
        .affiliate-step-desc {
            font-size: 0.85rem;
            color: #64748b;
            line-height: 1.5;
            margin: 0 0 0.85rem;
            flex: 1;
        }
        .step-pill-feature {
            font-size: 0.72rem;
            font-weight: 600;
            color: #15803d;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            padding: 0.2rem 0.55rem;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
        }

        /* ── Affiliation Toolkit & Fee Hub (Compact Modal-Friendly Design) ── */
        .affiliate-toolkit-card {
            background: linear-gradient(145deg, #0B1D4A 0%, #0F172A 100%);
            border: 1px solid rgba(212, 175, 55, 0.25);
            border-radius: 14px;
            padding: 1.25rem;
            color: #fff;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            max-width: 100%;
            margin: 0 0 1.25rem 0;
            position: relative;
            box-sizing: border-box;
            overflow: hidden;
        }
        .affiliate-toolkit-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.25rem;
            position: relative;
            z-index: 2;
            width: 100%;
            box-sizing: border-box;
        }
        @media (min-width: 992px) {
            .affiliate-toolkit-grid { grid-template-columns: 1.15fr 0.85fr; }
        }
        .toolkit-checklist-title {
            font-size: 1rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.45rem;
            line-height: 1.3;
        }
        .toolkit-checklist-subtitle {
            color: rgba(255, 255, 255, 0.72);
            font-size: 0.78rem;
            margin-bottom: 0.75rem;
            line-height: 1.4;
        }
        .checklist-items-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.45rem;
        }
        @media (min-width: 520px) {
            .checklist-items-grid { grid-template-columns: repeat(2, 1fr); }
        }
        .checklist-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.09);
            border-radius: 8px;
            padding: 0.45rem 0.6rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.78rem;
            color: #f8fafc;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }
        .checklist-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(212, 175, 55, 0.3);
        }
        .checklist-item i {
            color: #D4AF37;
            font-size: 0.85rem;
            flex-shrink: 0;
            width: 14px;
            text-align: center;
        }
        .checklist-item strong {
            display: block;
            font-size: 0.76rem;
            line-height: 1.25;
            color: #F8FAFC;
        }
        .checklist-item .item-sub {
            font-size: 0.68rem;
            color: rgba(255, 255, 255, 0.65);
            margin-top: 1px;
            line-height: 1.2;
        }
        .toolkit-action-box {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(212, 175, 55, 0.22);
            border-radius: 12px;
            padding: 1rem 0.95rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            box-sizing: border-box;
        }
        .fee-rate-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.45rem 0.65rem;
            background: rgba(0, 0, 0, 0.25);
            border-radius: 6px;
            margin-bottom: 0.4rem;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .fee-rate-label {
            font-size: 0.76rem;
            color: rgba(255, 255, 255, 0.85);
        }
        .fee-rate-val {
            font-weight: 700;
            font-size: 0.88rem;
            color: #D4AF37;
        }
        .hei-brackets-container {
            margin-top: 0.85rem;
            padding-top: 0.75rem;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
        }
        .hei-brackets-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.4rem;
            margin-bottom: 0.55rem;
        }
        @media (min-width: 520px) {
            .hei-brackets-grid { grid-template-columns: repeat(4, 1fr); }
        }
        .hei-bracket-box {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.09);
            border-radius: 6px;
            padding: 0.4rem 0.25rem;
            text-align: center;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }
        .hei-bracket-box:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(212, 175, 55, 0.35);
        }
        .hei-bracket-range {
            font-size: 0.68rem;
            color: rgba(255, 255, 255, 0.75);
            margin-bottom: 0.1rem;
            line-height: 1.2;
        }
        .hei-bracket-fee {
            font-weight: 700;
            font-size: 0.85rem;
            color: #D4AF37;
        }
        .operational-fee-note {
            background: rgba(212, 175, 55, 0.09);
            border: 1px solid rgba(212, 175, 55, 0.2);
            border-radius: 6px;
            padding: 0.4rem 0.6rem;
            font-size: 0.72rem;
            color: #FEF08A;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            line-height: 1.35;
        }
        @media (max-width: 640px) {
            .affiliate-toolkit-card {
                padding: 0.85rem 0.75rem;
            }
            .toolkit-checklist-title {
                font-size: 0.92rem;
            }
            .toolkit-action-box {
                padding: 0.75rem 0.65rem;
            }
        }

        /* ── Contact Section Enhancements ── */
        .contact {
            background: linear-gradient(135deg, #0B1D4A 0%, #142a6b 100%);
            color: #fff;
            padding: 2.5rem 0 2.75rem;
            position: relative;
            overflow: hidden;
        }
        .contact-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.75rem;
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 1rem;
            position: relative;
            z-index: 2;
            align-items: center;
        }
        @media (min-width: 768px) {
            .contact-container {
                grid-template-columns: 1.1fr 0.9fr;
                gap: 2.5rem;
            }
        }
        .contact-content {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-start;
        }
        .contact-content h2 {
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 0.4rem;
            line-height: 1.25;
        }
        .contact-content p {
            color: rgba(255, 255, 255, 0.82);
            font-size: 0.92rem;
            line-height: 1.5;
            margin-bottom: 1.15rem;
            max-width: 480px;
        }
        .contact-info-pills {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
            width: 100%;
        }
        .contact-info-pill {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 8px;
            padding: 0.55rem 0.85rem;
            font-size: 0.82rem;
            color: #f8fafc;
            text-decoration: none;
            backdrop-filter: blur(4px);
            transition: all 0.2s ease;
        }
        .contact-info-pill:hover {
            background: rgba(255, 255, 255, 0.16);
            border-color: rgba(212, 175, 55, 0.45);
            color: #fff;
            transform: translateX(4px);
        }
        .contact-info-pill i {
            color: #D4AF37;
            font-size: 0.95rem;
            width: 18px;
            text-align: center;
            flex-shrink: 0;
        }
        .contact-form {
            background: #ffffff;
            border-radius: 16px;
            padding: 1.5rem 1.5rem;
            color: #0B1D4A;
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.2);
        }
        .contact-form h3 {
            font-size: 1.15rem;
            font-weight: 700;
            color: #0B1D4A;
            margin-bottom: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.45rem;
        }
        .contact-form .form-group {
            margin-bottom: 0.7rem;
        }
        .contact-form .form-label {
            display: block;
            margin-bottom: 0.2rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: #334155;
        }
        .contact-form .form-input,
        .contact-form .form-textarea {
            width: 100%;
            padding: 0.55rem 0.75rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.88rem;
            background: #f8fafc;
            transition: all 0.2s ease;
        }
        .contact-form .form-input:focus,
        .contact-form .form-textarea:focus {
            outline: none;
            border-color: #0B1D4A;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(11, 29, 74, 0.1);
        }
        .contact-form .form-textarea {
            min-height: 80px;
            height: 80px;
            resize: vertical;
        }
        .contact-form .form-submit {
            width: 100%;
            padding: 0.45rem 0.9rem;
            background: #D4AF37;
            color: #0B1D4A;
            border: none;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.82rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            margin-top: 0.25rem;
        }
        #ctaAffiliateBtn {
            padding: 0.45rem 0.9rem;
            font-size: 0.82rem;
            font-weight: 700;
            border-radius: 6px;
        }
        .contact-form .form-submit:hover {
            background: #e5be3e;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.35);
        }

        @media (max-width: 768px) {
            .contact {
                padding: 1.5rem 0 1.75rem !important;
            }
            .contact-container {
                display: grid !important;
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                gap: 0.5rem !important;
                padding: 0 0.35rem !important;
                align-items: stretch !important;
            }
            .contact-content {
                display: flex !important;
                flex-direction: column !important;
                justify-content: space-between !important;
                padding-right: 0.15rem !important;
            }
            .contact-content h2 {
                font-size: 0.95rem !important;
                margin-bottom: 0.2rem !important;
                font-weight: 700 !important;
                line-height: 1.2 !important;
            }
            .contact-content p {
                font-size: 0.56rem !important;
                line-height: 1.3 !important;
                margin-bottom: 0.45rem !important;
                color: rgba(255, 255, 255, 0.85) !important;
            }
            .contact-info-pills {
                gap: 0.25rem !important;
                margin-bottom: 0.45rem !important;
            }
            .contact-info-pill {
                padding: 0.25rem 0.35rem !important;
                font-size: 0.52rem !important;
                gap: 0.3rem !important;
                border-radius: 6px !important;
            }
            .contact-info-pill span {
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
            }
            .contact-info-pill i {
                font-size: 0.6rem !important;
                width: 12px !important;
            }
            #ctaAffiliateBtn {
                padding: 0.22rem 0.35rem !important;
                font-size: 0.48rem !important;
                font-weight: 700 !important;
                width: 100% !important;
                border-radius: 4px !important;
                text-align: center !important;
                justify-content: center !important;
                min-height: unset !important;
                min-width: unset !important;
            }
            .contact-form {
                padding: 0.55rem 0.45rem !important;
                border-radius: 10px !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: space-between !important;
            }
            .contact-form h3 {
                font-size: 0.72rem !important;
                margin-bottom: 0.35rem !important;
                gap: 0.2rem !important;
                font-weight: 700 !important;
            }
            .contact-form .form-group {
                margin-bottom: 0.25rem !important;
            }
            .contact-form .form-label {
                font-size: 0.52rem !important;
                margin-bottom: 0.1rem !important;
                font-weight: 600 !important;
            }
            .contact-form .form-input,
            .contact-form .form-textarea {
                padding: 0.25rem 0.35rem !important;
                font-size: 0.56rem !important;
                border-radius: 5px !important;
            }
            .contact-form .form-textarea {
                min-height: 40px !important;
                height: 40px !important;
            }
            .contact-form .form-submit {
                padding: 0.22rem 0.35rem !important;
                font-size: 0.48rem !important;
                font-weight: 700 !important;
                border-radius: 4px !important;
                margin-top: 0.1rem !important;
                min-height: unset !important;
                min-width: unset !important;
            }
        }

        /* ── What's New View Modal ── */
        #whatsNewViewModal {
            position: fixed;
            inset: 0;
            background: rgba(11, 29, 74, 0.45);
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
            z-index: 100000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            opacity: 0;
            transition: opacity 0.25s ease;
        }
        #whatsNewViewModal.active {
            display: flex;
            opacity: 1;
        }
        .whats-new-modal-dialog {
            background: #ffffff;
            border-radius: 24px;
            max-width: 680px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25), 0 0 0 1px rgba(0, 0, 0, 0.05);
            position: relative;
            transform: scale(0.95) translateY(15px);
            transition: transform 0.25s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            display: flex;
            flex-direction: column;
        }
        #whatsNewViewModal.active .whats-new-modal-dialog {
            transform: scale(1) translateY(0);
        }
        .wnm-banner {
            position: relative;
            width: 100%;
            height: 260px;
            background: #0B1D4A;
            overflow: hidden;
            border-radius: 24px 24px 0 0;
        }
        .wnm-banner img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .wnm-banner-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0B1D4A 0%, #1E3A6E 100%);
            color: rgba(255, 255, 255, 0.7);
            font-size: 4rem;
        }
        .wnm-close-btn {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(0, 0, 0, 0.1);
            color: #0B1D4A;
            font-size: 1.1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            transition: all 0.2s ease;
            z-index: 20;
        }
        .wnm-close-btn i {
            pointer-events: none;
            display: block;
            line-height: 1;
        }
        .wnm-close-btn:hover {
            background: #0B1D4A;
            color: #ffffff;
            transform: scale(1.08);
        }
        .wnm-content {
            padding: 2rem 2.25rem 2.25rem;
        }
        .wnm-badge-row {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        .wnm-category-pill {
            background: rgba(11, 29, 74, 0.08);
            color: #0B1D4A;
            font-weight: 700;
            font-size: 0.8rem;
            padding: 0.35rem 0.85rem;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .wnm-date-pill {
            color: #64748b;
            font-size: 0.82rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        .wnm-title {
            font-size: 1.65rem;
            font-weight: 800;
            color: #0B1D4A;
            margin: 0 0 1.25rem;
            line-height: 1.3;
        }
        .wnm-body-text {
            font-size: 1rem;
            color: #334155;
            line-height: 1.75;
            margin-bottom: 2rem;
            word-break: break-word;
        }
        .wnm-body-text h1, .wnm-body-text h2, .wnm-body-text h3, .wnm-body-text h4 {
            color: #0B1D4A;
            margin: 1.25rem 0 0.5rem;
            font-weight: 700;
            line-height: 1.3;
        }
        .wnm-body-text h2 { font-size: 1.25rem; }
        .wnm-body-text h3 { font-size: 1.1rem; }
        .wnm-body-text p { margin: 0 0 0.85rem; }
        .wnm-body-text p:last-child { margin-bottom: 0; }
        .wnm-body-text ul, .wnm-body-text ol {
            padding-left: 1.5rem;
            margin: 0 0 0.85rem;
        }
        .wnm-body-text strong {
            font-weight: 700;
            color: #0B1D4A;
        }
        .wnm-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>

<div id="fb-root"></div>
<script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v17.0"></script>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<!-- ═══════════════════════════════════════════════════════════ Hero -->
<section class="hero">
    <div class="hero-bg"></div>
    <div class="hero-overlay"></div>
    <div class="hero-pattern"></div>
    <div class="hero-content">
        <h2 class="hero-tagline">One LSC. One IECEP.</h2>
        <h1 class="hero-title" style="color: white; font-family: 'Times New Roman', Arial, serif;">Institute of Electronics Engineers of the Philippines<br>Laguna Student Chapter</h1>
        <div class="hero-buttons">
            <button type="button" id="affiliateNowBtn" class="btn btn-primary">
                <i></i> Affiliate Now
            </button>
            <a href="#how-to-affiliate" class="btn btn-outline">How to Get Affiliated</a>
        </div>
    </div>

    <div class="hero-schools">
        <div class="schools-grid">
            <img src="<?php echo ASSETS_URL; ?>/icons/LETRAN.png"      alt="Colegio de San Juan de Letrán" loading="lazy">
            <img src="<?php echo ASSETS_URL; ?>/icons/LSPU-SCC.png"    alt="Laguna State Polytechnic University - Santa Cruz Campus" loading="lazy">
            <img src="<?php echo ASSETS_URL; ?>/icons/LSPU-SPCC.png"   alt="Laguna State Polytechnic University - San Pablo City Campus" loading="lazy">
            <img src="<?php echo ASSETS_URL; ?>/icons/MMCL.webp"       alt="Malayan Colleges Laguna" loading="lazy">
            <img src="<?php echo ASSETS_URL; ?>/icons/PUP-STA ROSA.png" alt="Polytechnic University of the Philippines - Santa Rosa Campus" loading="lazy">
            <img src="<?php echo ASSETS_URL; ?>/icons/UC-PNC.png"      alt="Pamantasan ng Cabuyao" loading="lazy">
            <img src="<?php echo ASSETS_URL; ?>/icons/UPHSD.png"       alt="University of Perpetual Help System - DALTA" loading="lazy">
            <img src="<?php echo ASSETS_URL; ?>/icons/UPHSL-BINAN.png" alt="University of Perpetual Help System Laguna - Biñán Campus" loading="lazy">
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════ What's New -->
<section id="features" class="section whats-new">
    <div class="container">
        <div class="section-heading text-center">
            <h2 class="section-title">
                What's New in IECEP-LSC?
            </h2>
            <p class="section-subtitle">
                Stay updated on executive announcements, student conventions, technical research seminars, and institutional accreditation notices.
            </p>
        </div>

        <div id="featured-cards-container" class="featured-cards-grid">
            <?php
            // If Supabase has featured cards, use them; otherwise, provide default highlights
            $displayCards = !empty($featuredCards) ? $featuredCards : [
                [
                    'id' => 'card-conv-2026',
                    'title' => 'IECEP-LSC Regional Student Convention 2026',
                    'description' => "Join hundreds of aspiring Electronics Engineering students across Laguna for the premier annual convention. Featuring groundbreaking research symposiums, robotics innovation challenges, technical quiz bowls, and keynote speeches from PRC board topnotchers and industry experts.\n\nDate: October 2026\nVenue: Laguna Provincial Capitol Cultural Center\nRegistration: Open to all affiliated chapter student members.",
                    'image_url' => ASSETS_URL . '/icons/hero.png',
                    'badge_text' => 'Flagship Event',
                    'category' => 'Regional Convention',
                    'date' => 'Oct 2026',
                    'read_time' => '3 min read',
                    'button_text' => 'View Details',
                    'button_url' => '#how-to-affiliate',
                    'button_color' => '#0B1D4A'
                ],
                [
                    'id' => 'card-affil-2026',
                    'title' => 'Annual Institutional Affiliation Drive (AY 2026–2027)',
                    'description' => "Official accreditation is now open for all tertiary and vocational institutions in Laguna offering ECE, ECT, and allied engineering curricula.\n\nAffiliated chapters receive:\n• Automated Digital Member IDs with QR/Blockchain hash verification\n• School Officer Dashboard access with real-time roster sync\n• Official Chapter Certificate of Good Standing\n• Priority slots for student seminars and regional competitions.",
                    'image_url' => ASSETS_URL . '/icons/iecep-logo.png',
                    'badge_text' => 'Accreditation',
                    'category' => 'Affiliation Drive',
                    'date' => 'AY 2026–2027',
                    'read_time' => '2 min read',
                    'button_text' => 'Affiliate Now',
                    'button_url' => '#how-to-affiliate',
                    'button_color' => '#0B1D4A'
                ],
                [
                    'id' => 'card-techx-series',
                    'title' => 'TechX & IoT Embedded Systems Masterclass Series',
                    'description' => "Level up your technical competence with our hands-on engineering workshop series. Master microcontrollers, ESP32 RF protocols, firmware debugging, and smart sensing applications led by certified professional engineers.\n\nFree certificate of attendance and continuing development training for verified student members.",
                    'image_url' => '',
                    'badge_text' => 'Technical Workshop',
                    'category' => 'Skills & Training',
                    'date' => 'Sept 2026',
                    'read_time' => '4 min read',
                    'button_text' => 'Learn More',
                    'button_url' => '#how-to-affiliate',
                    'button_color' => '#0B1D4A'
                ]
            ];
            ?>

            <?php
            if (!function_exists('getCardBadgeIcon')) {
                function getCardBadgeIcon($badgeText, $category = '') {
                    $text = strtolower($badgeText . ' ' . $category);
                    if (strpos($text, 'flagship') !== false || strpos($text, 'convention') !== false || strpos($text, 'event') !== false) {
                        return 'fa-solid fa-trophy';
                    }
                    if (strpos($text, 'accredit') !== false || strpos($text, 'affil') !== false) {
                        return 'fa-solid fa-award';
                    }
                    if (strpos($text, 'tech') !== false || strpos($text, 'workshop') !== false || strpos($text, 'seminar') !== false) {
                        return 'fa-solid fa-laptop-code';
                    }
                    if (strpos($text, 'memorandum') !== false || strpos($text, 'memo') !== false || strpos($text, 'policy') !== false) {
                        return 'fa-solid fa-scroll';
                    }
                    if (strpos($text, 'important') !== false || strpos($text, 'urgent') !== false) {
                        return 'fa-solid fa-circle-exclamation';
                    }
                    return 'fa-solid fa-bolt';
                }
            }
            ?>

            <?php foreach ($displayCards as $idx => $card): ?>
                <?php
                    $imageUrl = trim((string)($card['image_url'] ?? ''));
                    if (strpos($imageUrl, 'http://localhost/IECEP-LSC-MEMSYS') === 0) {
                        $imageUrl = APP_URL . substr($imageUrl, strlen('http://localhost/IECEP-LSC-MEMSYS'));
                    } elseif (strpos($imageUrl, 'http://localhost') === 0) {
                        $imageUrl = APP_URL . substr($imageUrl, strlen('http://localhost'));
                    } elseif ($imageUrl !== '' && !str_starts_with($imageUrl, 'http://') && !str_starts_with($imageUrl, 'https://') && !str_starts_with($imageUrl, '//')) {
                        $imageUrl = PUBLIC_URL . '/' . ltrim($imageUrl, '/');
                    }
                    $buttonText = trim((string)($card['button_text'] ?? 'View Details'));
                    $buttonUrl = trim((string)($card['button_url'] ?? ''));
                    $buttonColor = trim((string)($card['button_color'] ?? '#0B1D4A'));
                    $cardTitle = trim((string)($card['title'] ?? ''));
                    $cardDescription = trim((string)($card['description'] ?? ''));
                    $badgeText = trim((string)($card['badge_text'] ?? ''));
                    $category = trim((string)($card['category'] ?? $badgeText));
                    $cardDate = trim((string)($card['date'] ?? (!empty($card['created_at']) ? date('M d, Y', strtotime($card['created_at'])) : date('M Y'))));
                    $readTime = trim((string)($card['read_time'] ?? '2 min read'));

                    // JSON payload for modal preview
                    $cardJson = htmlspecialchars(json_encode([
                        'title' => $cardTitle,
                        'description' => $cardDescription,
                        'image_url' => $imageUrl,
                        'badge_text' => $badgeText,
                        'category' => $category,
                        'date' => $cardDate,
                        'read_time' => $readTime,
                        'button_text' => $buttonText,
                        'button_url' => $buttonUrl
                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                ?>
                <article class="featured-card" onclick="openWhatsNewModal(<?= $cardJson ?>)" tabindex="0" role="button" aria-label="View details for <?= h($cardTitle) ?>">
                    <div class="featured-card-image">
                        <?php if ($imageUrl !== ''): ?>
                            <img src="<?= h($imageUrl) ?>" alt="<?= h($cardTitle) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="featured-card-image-placeholder">
                                <i class="fa-solid fa-newspaper"></i>
                            </div>
                        <?php endif; ?>
                        <div class="featured-card-quick-view">
                            <span class="btn-quick-preview">
                                <i class="fa-solid fa-magnifying-glass-plus"></i> Quick View
                            </span>
                        </div>
                    </div>
                    <div class="featured-card-body">
                        <div class="featured-card-meta">
                            <?php if ($category !== '' && strtolower($category) !== 'update'): ?>
                                <span class="text-primary fw-semibold"><i class="fa-solid fa-tag text-gold"></i> <?= h($category) ?></span>
                            <?php endif; ?>
                            <span><i class="fa-solid fa-calendar-days"></i> <?= h($cardDate) ?></span>
                        </div>
                        <h3 class="featured-card-title"><?= h($cardTitle) ?></h3>
                        <p class="featured-card-description"><?= html_entity_decode(strip_tags($cardDescription)) ?></p>
                        <div class="featured-card-footer" onclick="event.stopPropagation()">
                            <button type="button" class="btn-view-card" onclick="openWhatsNewModal(<?= $cardJson ?>)">
                                <i class="fa-solid fa-arrow-right-to-bracket"></i> View Details
                            </button>
                            <?php if ($buttonUrl !== '' && $buttonUrl !== '#'): ?>
                                <a href="<?= h($buttonUrl) ?>" class="btn-link-card" title="Open Link" target="_blank" rel="noopener">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════ How to Affiliate -->
<section id="how-to-affiliate" class="section how-to-affiliate">
    <div class="container">
        <div class="section-heading text-center">
            <h2 class="section-title">
                How to get Affiliated?
            </h2>
            <p class="section-subtitle">
                A transparent, 4-stage digital onboarding workflow engineered for Laguna school chapters and student engineering leadership.
            </p>
        </div>

        <!-- 4-Step Grid -->
        <div class="affiliate-steps-grid">
            <div class="affiliate-step-card">
                <div class="affiliate-step-header">
                    <span class="step-badge-number">Step 01</span>
                </div>
                <h3 class="affiliate-step-title">Email Verification</h3>
                <p class="affiliate-step-desc">Enter your institutional officer email address to receive an instantaneous, secure 6-digit cryptographic verification code.</p>
            </div>

            <div class="affiliate-step-card">
                <div class="affiliate-step-header">
                    <span class="step-badge-number">Step 02</span>
                </div>
                <h3 class="affiliate-step-title">Upload Affiliation Kit</h3>
                <p class="affiliate-step-desc">Upload the 6 mandatory accreditation documents including Letter of Intent, Dean's Endorsement, CBL, and Member Directory Excel roster.</p>
            </div>

            <div class="affiliate-step-card">
                <div class="affiliate-step-header">
                    <span class="step-badge-number">Step 03</span>
                </div>
                <h3 class="affiliate-step-title">Committee Evaluation</h3>
                <p class="affiliate-step-desc">The IECEP-LSC verifies student counts, validates constitution compliance, and approves accreditation standing within 2–3 working days.</p>
            </div>

            <div class="affiliate-step-card">
                <div class="affiliate-step-header">
                    <span class="step-badge-number">Step 04</span>
                </div>
                <h3 class="affiliate-step-title">Portal Access &amp; IDs</h3>
                <p class="affiliate-step-desc">Approved school officers gain portal credentials to manage student directories, download Certificates of Good Standing, and issue digital member IDs.</p>
            </div>
        </div>

    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════ Contact -->
<section class="contact">
    <div class="contact-container">
        <div class="contact-content">
            <h2 style="font-family: 'Times New Roman', Arial, serif;">Get In Touch</h2>
            <p>Have questions about chapter accreditation or need assistance with your affiliation kit? Reach out to the IECEP-LSC.</p>
            
            <div class="contact-info-pills">
                <a href="https://mail.google.com/mail/?view=cm&fs=1&to=ieceplsc24@gmail.com" target="_blank" rel="noopener noreferrer" class="contact-info-pill" title="Send Email via Gmail">
                    <i class="fa-solid fa-envelope"></i>
                    <span>ieceplsc24@gmail.com</span>
                </a>
                <a href="https://www.facebook.com/IECEPLSC" target="_blank" rel="noopener noreferrer" class="contact-info-pill" title="Visit Official Facebook Page">
                    <i class="fa-brands fa-facebook"></i>
                    <span>facebook.com/IECEPLSC (Facebook Page)</span>
                </a>
                <div class="contact-info-pill">
                    <i class="fa-solid fa-clock"></i>
                    <span>Mon – Fri | 8:00 AM – 5:00 PM</span>
                </div>
            </div>

            <button type="button" class="btn btn-primary" id="ctaAffiliateBtn">
                <i class="fa-solid fa-arrow-right me-2"></i> Start Affiliation Now
            </button>
        </div>
        <div class="contact-form">
            <h3>
                <i class="fa-solid fa-paper-plane text-gold"></i> Send us a Message
            </h3>
            <?php if ($contactSuccess): ?>
                <div class="alert alert-success" style="padding:0.5rem 0.75rem; font-size:0.85rem; margin-bottom:0.65rem;"><i class="fa-solid fa-circle-check me-1"></i> Message sent successfully!</div>
            <?php endif; ?>
            <?php if ($contactError): ?>
                <div class="alert alert-error" style="padding:0.5rem 0.75rem; font-size:0.85rem; margin-bottom:0.65rem;"><i class="fa-solid fa-circle-exclamation me-1"></i> Failed to send message. Please try again.</div>
            <?php endif; ?>
            <form method="POST" action="/contact-submit.php">
                <div class="form-group">
                    <label for="contact-name" class="form-label">Your Name</label>
                    <input type="text" id="contact-name" name="name" class="form-input" placeholder="Enter your full name" required>
                </div>
                <div class="form-group">
                    <label for="contact-email" class="form-label">Your Email</label>
                    <input type="email" id="contact-email" name="email" class="form-input" placeholder="Enter institutional or personal email" required>
                </div>
                <div class="form-group">
                    <label for="contact-message" class="form-label">Your Message</label>
                    <textarea id="contact-message" name="message" class="form-textarea" placeholder="How can the IECEP-LSC assist your chapter?" required></textarea>
                </div>
                <button type="submit" class="form-submit">
                    <i class="fa-solid fa-paper-plane me-1"></i> Send Message
                </button>
            </form>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer-new.php'; ?>

<!-- ═══════════════════════════════════════════════════════════ Modal Styles -->
<style>
#affiliateModal {
    position: fixed !important;
    inset: 0 !important;
    background: rgba(11, 29, 74, 0.45);
    backdrop-filter: blur(3px);
    -webkit-backdrop-filter: blur(3px);
    display: flex !important;
    align-items: flex-start;
    justify-content: center !important;
    padding: 1.5rem;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
    overflow-y: auto;
    box-sizing: border-box;
}
#affiliateModal.active { opacity: 1; visibility: visible; }

#affiliateModal .modal-content {
    background: white;
    border-radius: 16px;
    width: min(640px, 100%) !important;
    margin: auto !important;
    position: relative;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    transform: scale(0.9);
    transition: transform 0.3s ease;
    flex-shrink: 0;
    align-self: center;
}
#affiliateModal.active .modal-content { transform: scale(1); }

#affiliateModal #modal-email-verification-step,
#affiliateModal #modal-application-form-step { display: none !important; }
#affiliateModal #modal-email-verification-step { display: block !important; }
#affiliateModal #modal-application-form-step.active,
#affiliateModal #modal-application-form-step[style*="block"] { display: block !important; }
#affiliateModal:has(#modal-application-form-step.active) #modal-email-verification-step,
#affiliateModal:has(#modal-application-form-step[style*="block"]) #modal-email-verification-step { display: none !important; }

.modal-title {
    font-family: 'Inter', sans-serif;
    font-size: 1.5rem;
    font-weight: 700;
    color: #0B1D4A;
    text-align: center;
    margin: 2rem 2rem 1rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f1f5f9;
}
.modal-close {
    position: absolute;
    top: 1.5rem; right: 1.5rem;
    background: #f8fafc;
    border: none;
    width: 40px; height: 40px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s ease;
    z-index: 10;
}
.modal-close:hover { background: #e2e8f0; color: #0B1D4A; transform: scale(1.1); }

.step-indicator {
    display: flex; align-items: center; justify-content: center;
    padding: 0 2rem 2rem; gap: 2rem;
}
.step-indicator-item { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; }
.step-indicator-number {
    width: 48px; height: 48px;
    border-radius: 50%;
    background: #e2e8f0; color: #64748b;
    display: flex; align-items: center; justify-content: center;
    font-weight: 600; font-size: 1.1rem;
    transition: all 0.3s ease;
    border: 3px solid transparent;
}
.step-indicator-item.active .step-indicator-number   { background: #C49A00; color: #0B1D4A; border-color: #C49A00; box-shadow: 0 4px 12px rgba(196,154,0,0.3); }
.step-indicator-item.completed .step-indicator-number{ background: #0B1D4A; color: white; border-color: #0B1D4A; }
.step-indicator-item span { font-size: 0.875rem; font-weight: 500; color: #64748b; text-align: center; }
.step-indicator-item.active span, .step-indicator-item.completed span { color: #0B1D4A; font-weight: 600; }
.step-indicator-line { flex: 1; height: 2px; background: #e2e8f0; max-width: 100px; }

.modal-section { background: #f8fafc; padding: 2rem; border-radius: 12px; margin: 0 2rem 2rem; border: 1px solid #e2e8f0; }
.modal-section h4 { font-family: 'Inter', sans-serif; font-size: 1.25rem; font-weight: 600; color: #0B1D4A; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; }
.modal-section h5 { font-family: 'Inter', sans-serif; font-size: 1.1rem; font-weight: 600; color: #0B1D4A; margin-bottom: 1rem; }

.form-group { margin-bottom: 1.5rem; }
.form-group label { display: block; margin-bottom: 0.5rem; font-family: 'Inter', sans-serif; font-weight: 600; color: #374151; font-size: 0.95rem; }
.form-group input, .form-group select {
    width: 100%; padding: 0.875rem 1rem;
    border: 2px solid #e2e8f0; border-radius: 8px;
    font-family: 'Inter', sans-serif; font-size: 1rem;
    transition: all 0.2s ease; background: white;
}
.form-group input:focus, .form-group select:focus { outline: none; border-color: #C49A00; box-shadow: 0 0 0 3px rgba(196,154,0,0.1); }
.form-group input[readonly] { background: #f8fafc; color: #64748b; }
.form-group small { display: block; margin-top: 0.5rem; color: #64748b; font-size: 0.875rem; }

.verification-inputs { display: flex; gap: 0.75rem; justify-content: center; margin: 2rem 0; }
.code-input {
    width: 50px; height: 50px;
    border: 2px solid #e2e8f0; border-radius: 8px;
    text-align: center; font-size: 1.25rem; font-weight: 600;
    font-family: 'Inter', sans-serif; transition: all 0.2s ease;
}
.code-input:focus { outline: none; border-color: #C49A00; box-shadow: 0 0 0 3px rgba(196,154,0,0.1); transform: scale(1.05); }

.file-upload-wrapper { position: relative; overflow: hidden; min-height: 60px; }
.file-upload-wrapper input[type="file"] { position: absolute; top: 0; left: 0; opacity: 0; width: 100%; height: 100%; cursor: pointer; z-index: 10; }
.file-upload-label {
    display: flex; align-items: center; justify-content: center; padding: 1rem;
    border: 2px dashed #C49A00; border-radius: 8px; background: #fff8f0;
    color: #0B1D4A; font-weight: 500; cursor: pointer; transition: all 0.2s ease;
    min-height: 60px; position: relative; z-index: 1; pointer-events: none;
}
.file-upload-wrapper:hover .file-upload-label { background: #fef3e2; border-color: #0B1D4A; }

.btn {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 0.875rem 2rem; border-radius: 50px;
    font-family: 'Inter', sans-serif; font-weight: 600; font-size: 1rem;
    text-decoration: none; cursor: pointer; transition: all 0.2s ease;
    border: none; min-width: 200px; position: relative; overflow: hidden;
}
.btn-primary { background: linear-gradient(135deg, #C49A00 0%, #D4AF37 100%); color: #0B1D4A; box-shadow: 0 4px 12px rgba(196,154,0,0.3); }
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(196,154,0,0.4); }
.btn-primary:active { transform: translateY(0); }
.btn-outline { background: transparent; color: white; border: 2px solid white; }
.btn-outline:hover { background: white; color: #0B1D4A; transform: translateY(-2px); }

.alert { padding: 1rem 1.5rem; border-radius: 8px; margin: 1rem 0; font-family: 'Inter', sans-serif; display: flex; align-items: center; gap: 0.75rem; }
.alert-success { background: linear-gradient(135deg, #f0fdf4, #dcfce7); color: #166534; border: 1px solid #bbf7d0; }
.alert-error   { background: linear-gradient(135deg, #fef2f2, #fee2e2); color: #dc2626; border: 1px solid #fecaca; }
.alert-info    { background: linear-gradient(135deg, #f0f9ff, #e0f2fe); color: #0284c7; border: 1px solid #bae6fd; }

.spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid transparent; border-top: 2px solid currentColor; border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.document-requirements { background: linear-gradient(135deg, #f0f9ff, #e0f2fe); border: 1px solid #bae6fd; border-radius: 12px; padding: 1.5rem; margin: 1.5rem 0; }
.document-requirements h5 { color: #0284c7; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
.document-requirements ul { margin: 0; padding-left: 1.5rem; }
.document-requirements li { margin-bottom: 0.5rem; color: #0c4a6e; }

.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
.form-grid-full { grid-column: 1 / -1; }
.modal-actions { text-align: center; margin: 2rem 0; }

.success-message { text-align: center; padding: 1.5rem; background: linear-gradient(135deg, #f0fdf4, #dcfce7); border-radius: 12px; border: 1px solid #bbf7d0; margin: 1rem 0; }
.success-message h4 { color: #166534; margin-bottom: 0.5rem; }
.success-message p   { color: #15803d; margin: 0; }

.modal-notification {
    padding: 1rem 1.5rem; border-radius: 12px; margin-bottom: 1rem;
    font-family: 'Inter', sans-serif; display: flex; align-items: center; gap: 0.75rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1); animation: slideDown 0.3s ease-out;
    position: relative; overflow: hidden;
}
.modal-notification::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; }
.modal-notification.success  { background: linear-gradient(135deg, #f0fdf4, #dcfce7); color: #166534; border: 1px solid #bbf7d0; }
.modal-notification.success::before { background: #10b981; }
.modal-notification.error    { background: linear-gradient(135deg, #fef2f2, #fee2e2); color: #dc2626; border: 1px solid #fecaca; }
.modal-notification.error::before   { background: #ef4444; }
.modal-notification.info     { background: linear-gradient(135deg, #f0f9ff, #e0f2fe); color: #0284c7; border: 1px solid #bae6fd; }
.modal-notification.info::before    { background: #3b82f6; }
.modal-notification-icon  { flex-shrink: 0; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; }
.modal-notification-content { flex: 1; font-size: 0.9rem; font-weight: 500; line-height: 1.4; }
.modal-notification-close { flex-shrink: 0; background: none; border: none; color: inherit; cursor: pointer; padding: 0.25rem; opacity: 0.7; transition: opacity 0.2s; }
.modal-notification-close:hover { opacity: 1; }

@keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
@keyframes fadeOut  { from { opacity: 1; transform: translateY(0); } to { opacity: 0; transform: translateY(-10px); } }

@media (max-width: 768px) {
    #affiliateModal .modal-content { width: 95%; max-height: none; margin: auto; }
    .modal-title { font-size: 1.25rem; margin: 1.25rem 1.25rem 0.75rem; }
    .modal-close { top: 1rem; right: 1rem; width: 36px; height: 36px; }
    .step-indicator { display: flex; flex-direction: row; align-items: center; justify-content: center; padding: 0 0.5rem 1rem; gap: 0.5rem; }
    .step-indicator-number { width: 34px; height: 34px; font-size: 0.9rem; }
    .step-indicator-item span { font-size: 0.7rem; text-align: center; }
    .step-indicator-line { flex: 1; min-width: 15px; max-width: 50px; height: 2px; transform: none; background: #e2e8f0; }
    .modal-section { margin: 0 1rem 1.25rem; padding: 1.25rem; }
    .code-input { width: 40px; height: 40px; font-size: 1rem; }
    .btn { padding: 0.75rem 1.25rem; font-size: 0.9rem; min-width: 160px; }
}
@media (max-width: 480px) {
    #affiliateModal .modal-content { width: 98%; margin: auto; padding: 1rem 0.5rem; }
    .step-indicator { padding: 0 0.25rem 0.85rem; gap: 0.25rem; }
    .step-indicator-number { width: 28px; height: 28px; font-size: 0.78rem; }
    .step-indicator-item span { font-size: 0.62rem; max-width: 70px; line-height: 1.15; }
    .step-indicator-line { min-width: 8px; max-width: 25px; height: 2px; transform: none; }
    .modal-section { margin: 0 0.25rem 1rem; padding: 1rem 0.5rem; }
    .code-input { width: 30px; height: 30px; font-size: 0.8rem; }
}
</style>

<!-- ═══════════════════════════════════════════════════════════ Affiliate Modal -->
<div id="affiliateModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="modal-content">
        <form id="affiliationForm" action="<?php echo BASE_URL; ?>/public/api/submit-affiliation.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="submit_affiliation">
            <input type="hidden" id="form-contact-email" name="contact_email">

            <button type="button" class="modal-close" id="closeModalBtn" aria-label="Close modal">&times;</button>
            <h3 class="modal-title" id="modal-title">Affiliation Application</h3>

            <div id="modalNotificationContainer" style="display:none;position:absolute;top:80px;left:50%;transform:translateX(-50%);z-index:1000;width:90%;max-width:500px;"></div>

            <!-- Step Indicator -->
            <div class="step-indicator">
                <div class="step-indicator-item active" id="modal-step1">
                    <div class="step-indicator-number">1</div>
                    <span>Email Verification</span>
                </div>
                <div class="step-indicator-line"></div>
                <div class="step-indicator-item" id="modal-step2">
                    <div class="step-indicator-number">2</div>
                    <span>Application Form</span>
                </div>
                <div class="step-indicator-line"></div>
                <div class="step-indicator-item" id="modal-step3">
                    <div class="step-indicator-number">3</div>
                    <span>Payment Summary</span>
                </div>
            </div>

            <!-- Step 1: Email Verification -->
            <div id="modal-email-verification-step">
                <div class="modal-section">
                    <h4><i class="fas fa-envelope" style="color:#C49A00;margin-right:0.5rem;"></i>Verify Your Email</h4>
                    <p style="text-align:center;color:#64748b;margin-bottom:2rem;font-size:1.05rem;">Enter your institution email address to receive a verification code</p>

                    <div id="modal-email-form">
                        <div class="form-group" style="max-width:400px;margin:0 auto 2rem;">
                            <label for="modal-verification-email">Email Address <span style="color:#dc2626;">*</span></label>
                            <input type="email" id="modal-verification-email" placeholder="your.email@institution.edu" required>
                            <small>Please use your institutional email address</small>
                        </div>
                        <div class="modal-actions">
                            <button type="button" id="modal-send-code-btn" class="btn btn-primary">Send Verification Code</button>
                        </div>
                    </div>

                    <div id="modal-code-form" style="display:none;">
                        <div class="success-message">
                            <h4><i class="fas fa-check-circle" style="margin-right:0.5rem;"></i>Verification Code Sent</h4>
                            <p>Code sent to <strong id="modal-sent-email"></strong></p>
                            <small style="color:#64748b;">Please check your inbox and spam folder</small>
                        </div>
                        <div class="verification-inputs">
                            <input type="text" maxlength="1" class="code-input" data-index="0">
                            <input type="text" maxlength="1" class="code-input" data-index="1">
                            <input type="text" maxlength="1" class="code-input" data-index="2">
                            <input type="text" maxlength="1" class="code-input" data-index="3">
                            <input type="text" maxlength="1" class="code-input" data-index="4">
                            <input type="text" maxlength="1" class="code-input" data-index="5">
                        </div>
                        <div class="modal-actions">
                            <button type="button" id="modal-verify-code-btn" class="btn btn-primary">Verify Code</button>
                        </div>
                    </div>
                </div>
                <div id="modal-verification-error" class="alert alert-error" style="display:none;margin:0 2rem 2rem;"></div>
                <div id="modal-verification-success" class="alert alert-success" style="display:none;margin:0 2rem 2rem;"></div>
            </div>

            <!-- Step 2: Application Form -->
            <div id="modal-application-form-step" style="display:none;">
                <div class="modal-section">
                    <h4><i class="fas fa-university" style="color:#C49A00;margin-right:0.5rem;"></i>Institution Information</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="modal-inst-name">Institution Name <span style="color:#dc2626;">*</span></label>
                            <input type="text" id="modal-inst-name" name="institution_name" required>
                        </div>
                        <div class="form-group">
                            <label for="modal-inst-type">Institution Type <span style="color:#dc2626;">*</span></label>
                            <select id="modal-inst-type" required>
                                <option value="">-- Select --</option>
                                <option>Public University</option>
                                <option>Private University</option>
                                <option>College</option>
                                <option>Technical Institution</option>
                            </select>
                        </div>
                        <div class="form-group form-grid-full">
                            <label for="modal-inst-address">Institution Address <span style="color:#dc2626;">*</span></label>
                            <input type="text" id="modal-inst-address" name="institution_address" required>
                        </div>
                    </div>
                </div>

                <div class="modal-section">
                    <h4><i class="fas fa-user-tie" style="color:#C49A00;margin-right:0.5rem;"></i>Contact Information</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="modal-contact-name">Contact Person Name <span style="color:#dc2626;">*</span></label>
                            <input type="text" id="modal-contact-name" name="contact_person" required>
                        </div>
                        <div class="form-group">
                            <label for="modal-contact-position">Position <span style="color:#dc2626;">*</span></label>
                            <input type="text" id="modal-contact-position" name="contact_position" required>
                        </div>
                        <div class="form-group">
                            <label for="modal-contact-email">Email <span style="color:#dc2626;">*</span></label>
                            <input type="email" id="modal-contact-email" name="contact_email" readonly>
                        </div>
                        <div class="form-group">
                            <label for="modal-contact-phone">Phone Number <span style="color:#dc2626;">*</span></label>
                            <input type="tel" id="modal-contact-phone" name="contact_phone" placeholder="09XXXXXXXXX" pattern="09[0-9]{9}">
                        </div>
                    </div>
                </div>

                <!-- Toolkit Info Panel in Step 2 -->
                <div id="modal-toolkit-panel" class="affiliate-toolkit-card" style="margin: 0 0 1.5rem;">
                    <div class="affiliate-toolkit-grid">
                        <!-- Left: Checklist & HEI Brackets -->
                        <div>
                            <h3 class="toolkit-checklist-title">
                                <i class="fa-solid fa-list-check text-gold"></i> Required Affiliation Documents
                            </h3>
                            <p class="toolkit-checklist-subtitle">
                                Per Art. IV Sec. 3 of the IECEP National Constitution &amp; By-Laws, please prepare the following for upload:
                            </p>
                            <div class="checklist-items-grid">
                                <div class="checklist-item">
                                    <i class="fa-solid fa-file-signature text-gold"></i>
                                    <div>
                                        <strong>Letter of Intent (LOI)</strong>
                                        <div class="item-sub">Signed by Chapter President</div>
                                    </div>
                                </div>
                                <div class="checklist-item">
                                    <i class="fa-solid fa-building-columns text-gold"></i>
                                    <div>
                                        <strong>Endorsement Letter</strong>
                                        <div class="item-sub">Signed by Dean / Dept. Chair</div>
                                    </div>
                                </div>
                                <div class="checklist-item">
                                    <i class="fa-solid fa-scale-balanced text-gold"></i>
                                    <div>
                                        <strong>Constitution &amp; By-Laws</strong>
                                        <div class="item-sub">Ratified local chapter copy</div>
                                    </div>
                                </div>
                                <div class="checklist-item">
                                    <i class="fa-solid fa-users-gear text-gold"></i>
                                    <div>
                                        <strong>Officers List with CVs</strong>
                                        <div class="item-sub">Executive board roster</div>
                                    </div>
                                </div>
                                <div class="checklist-item">
                                    <i class="fa-solid fa-sitemap text-gold"></i>
                                    <div>
                                        <strong>Organizational Chart</strong>
                                        <div class="item-sub">Departmental structure</div>
                                    </div>
                                </div>
                                <div class="checklist-item">
                                    <i class="fa-solid fa-file-excel text-gold"></i>
                                    <div>
                                        <strong>Member Directory (Excel)</strong>
                                        <div class="item-sub">4 sheets (1st to 4th Year)</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Section 1. HEI Affiliation Fee Brackets (BR No. 021-2024) -->
                            <div class="hei-brackets-container">
                                <div style="font-size:0.75rem; font-weight:700; color:#D4AF37; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:0.25rem;">
                                    <i class="fa-solid fa-building-columns text-gold me-1"></i> Section 1. HEI Institutional Fee
                                </div>
                                <p style="font-size:0.74rem; color:rgba(255,255,255,0.72); margin-bottom:0.5rem; line-height:1.35;">
                                    In accordance with Board Resolution No. 021-2024, affiliation fees follow student member count:
                                </p>
                                <div class="hei-brackets-grid">
                                    <div class="hei-bracket-box">
                                        <div class="hei-bracket-range">1–50 Members</div>
                                        <div class="hei-bracket-fee">₱1,500</div>
                                    </div>
                                    <div class="hei-bracket-box">
                                        <div class="hei-bracket-range">51–100 Members</div>
                                        <div class="hei-bracket-fee">₱2,000</div>
                                    </div>
                                    <div class="hei-bracket-box">
                                        <div class="hei-bracket-range">101–150 Members</div>
                                        <div class="hei-bracket-fee">₱2,500</div>
                                    </div>
                                    <div class="hei-bracket-box">
                                        <div class="hei-bracket-range">151+ Members</div>
                                        <div class="hei-bracket-fee">₱3,000</div>
                                    </div>
                                </div>
                                <div class="operational-fee-note">
                                    <i class="fa-solid fa-circle-info text-gold" style="font-size:0.9rem; flex-shrink:0;"></i>
                                    <span>Plus <strong>₱800.00</strong> operational &amp; activity fee collected upon renewal for chapter initiatives.</span>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Section 2. Individual Membership Fees -->
                        <div class="toolkit-action-box">
                            <div>
                                <div style="font-size:0.75rem; font-weight:700; color:#D4AF37; text-transform:uppercase; letter-spacing:0.06em; margin-bottom:0.25rem;">
                                    <i class="fa-solid fa-users text-gold me-1"></i> Section 2. Member Dues
                                </div>
                                <h4 style="font-size:1.05rem; font-weight:700; color:#fff; margin-bottom:0.25rem;">
                                    Individual Student Dues
                                </h4>
                                <p style="font-size:0.74rem; color:rgba(255,255,255,0.72); margin-bottom:0.65rem; line-height:1.35;">
                                    Individual student renewal occurs simultaneously with organizational affiliation:
                                </p>
                                <div class="fee-rate-box">
                                    <span class="fee-rate-label">Returning (Old) Members</span>
                                    <span class="fee-rate-val">₱200.00</span>
                                </div>
                                <div class="fee-rate-box">
                                    <span class="fee-rate-label">New Student Members</span>
                                    <span class="fee-rate-val">₱250.00</span>
                                </div>
                                <div class="fee-rate-box">
                                    <span class="fee-rate-label">Honorary Members</span>
                                    <span class="fee-rate-val">₱300.00</span>
                                </div>
                                <div style="font-size:0.72rem; color:rgba(255,255,255,0.68); margin:0.4rem 0 0.85rem; line-height:1.35;">
                                    <i class="fa-solid fa-id-card text-gold me-1"></i> Includes official ID &amp; full access to IECEP-LSC activities.
                                </div>
                            </div>
                            <button type="button" onclick="document.getElementById('modal-document-upload-section').scrollIntoView({behavior:'smooth', block:'start'})" class="btn btn-primary" style="width:100%; padding:0.65rem 1rem; font-size:0.88rem; font-weight:700; border-radius:50px; display:flex; align-items:center; justify-content:center; gap:0.4rem;">
                                <i class="fa-solid fa-cloud-arrow-up"></i> Upload Documents Below
                            </button>
                        </div>
                    </div>
                </div>

                <div class="modal-section">
                    <h4><i class="fas fa-file-arrow-up" style="color:#C49A00;margin-right:0.5rem;"></i>Upload Documents</h4>
                    <div id="modal-document-upload-section" style="display:none;">
                        <div class="form-grid">
                            <?php foreach ([
                                ['letter_of_intent',     'Letter of Intent',          '.pdf', 'fa-file-pdf'],
                                ['endorsement_letter',   'Endorsement Letter',         '.pdf', 'fa-file-pdf'],
                                ['constitution_by_laws', 'Constitution and By-Laws',   '.pdf', 'fa-file-pdf'],
                                ['officers_cvs',         'List of Officers with CVs',  '.pdf', 'fa-file-pdf'],
                                ['organizational_chart', 'Organizational Chart',        '.pdf', 'fa-file-pdf'],
                            ] as [$name, $label, $accept, $icon]): ?>
                            <div class="form-group">
                                <div class="file-upload-wrapper">
                                    <input type="file" name="<?php echo $name; ?>" accept="<?php echo $accept; ?>" required id="<?php echo $name; ?>_file">
                                    <label for="<?php echo $name; ?>_file" class="file-upload-label">
                                        <i class="fas <?php echo $icon; ?>" style="margin-right:0.5rem;"></i>
                                        <span class="label-text"><?php echo $label; ?> <span style="color:#dc2626;">*</span></span>
                                        <span class="file-selected" style="display:none;">
                                            <i class="fas fa-check-circle" style="color:#10b981;margin-right:0.5rem;"></i>
                                            <span class="file-name"></span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <!-- Member Directory (full-width with live parse preview) -->
                            <div class="form-group form-grid-full">
                                <label style="font-weight:600;color:#374151;font-size:0.95rem;margin-bottom:0.5rem;display:block;">
                                    Member Directory <span style="color:#dc2626;">*</span>
                                    <span style="font-weight:400;color:#64748b;font-size:0.85rem;margin-left:0.5rem;">(CSV or Excel — must have a "Status" column)</span>
                                </label>
                                <div class="file-upload-wrapper" id="member_directory_wrapper">
                                    <input type="file" name="member_directory" accept=".xls,.xlsx,.csv" required id="member_directory_file">
                                    <label for="member_directory_file" class="file-upload-label">
                                        <i class="fas fa-file-excel" style="margin-right:0.5rem;"></i>
                                        <span class="label-text">Member Directory <span style="color:#dc2626;">*</span></span>
                                        <span class="file-selected" style="display:none;">
                                            <i class="fas fa-check-circle" style="color:#10b981;margin-right:0.5rem;"></i>
                                            <span class="file-name"></span>
                                        </span>
                                    </label>
                                </div>

                                <!-- Live parse result -->
                                <div id="member-parse-result" style="display:none;margin-top:1rem;padding:1.25rem;background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:10px;">
                                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem;">
                                        <i class="fas fa-users" style="color:#059669;"></i>
                                        <strong style="color:#065f46;font-size:0.95rem;">Member Directory Detected</strong>
                                    </div>
                                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:0.75rem;">
                                        <div style="background:white;border-radius:8px;padding:0.75rem;border:1px solid #d1fae5;text-align:center;">
                                            <div id="parse-total" style="font-size:1.75rem;font-weight:700;color:#0B1D4A;">0</div>
                                            <div style="font-size:0.8rem;color:#64748b;margin-top:0.25rem;">Total Members</div>
                                        </div>
                                        <div style="background:white;border-radius:8px;padding:0.75rem;border:1px solid #d1fae5;text-align:center;">
                                            <div id="parse-new" style="font-size:1.75rem;font-weight:700;color:#2563eb;">0</div>
                                            <div style="font-size:0.8rem;color:#64748b;margin-top:0.25rem;">New Members</div>
                                        </div>
                                        <div style="background:white;border-radius:8px;padding:0.75rem;border:1px solid #d1fae5;text-align:center;">
                                            <div id="parse-old" style="font-size:1.75rem;font-weight:700;color:#d97706;">0</div>
                                            <div style="font-size:0.8rem;color:#64748b;margin-top:0.25rem;">Old / Renewing</div>
                                        </div>
                                    </div>
                                    <div id="parse-warning" style="display:none;margin-top:0.75rem;padding:0.6rem 0.9rem;background:#fef9c3;border:1px solid #fde68a;border-radius:6px;color:#92400e;font-size:0.85rem;">
                                        <i class="fas fa-triangle-exclamation" style="margin-right:0.4rem;"></i>
                                        <span id="parse-warning-text"></span>
                                    </div>
                                </div>
                                <div id="member-parse-error" style="display:none;margin-top:0.75rem;padding:0.6rem 0.9rem;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;color:#991b1b;font-size:0.85rem;">
                                    <i class="fas fa-circle-exclamation" style="margin-right:0.4rem;"></i>
                                    <span id="parse-error-text"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-actions" style="margin-top:2rem;">
                        <button type="button" id="modal-proceed-payment-btn" class="btn btn-primary" disabled>
                            <i class="fas fa-arrow-right" style="margin-right:0.5rem;"></i>Proceed to Payment Summary
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 3: Payment Summary -->
            <div id="modal-payment-step" style="display:none;">
                <div class="modal-section">
                    <h4><i class="fas fa-file-invoice-dollar" style="color:#C49A00;margin-right:0.5rem;"></i>Payment Summary</h4>
                    <p style="color:#64748b;margin-bottom:1.5rem;font-size:0.95rem;">Based on your submitted Member Directory, here is the breakdown of your affiliation fee.</p>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:0.75rem;margin-bottom:1.5rem;">
                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:0.75rem;text-align:center;">
                            <div id="pay-total" style="font-size:1.75rem;font-weight:700;color:#0B1D4A;">0</div>
                            <div style="font-size:0.8rem;color:#64748b;margin-top:0.25rem;">Total Members</div>
                        </div>
                        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:0.75rem;text-align:center;">
                            <div id="pay-new" style="font-size:1.75rem;font-weight:700;color:#2563eb;">0</div>
                            <div style="font-size:0.8rem;color:#64748b;margin-top:0.25rem;">New Members</div>
                        </div>
                        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:0.75rem;text-align:center;">
                            <div id="pay-old" style="font-size:1.75rem;font-weight:700;color:#d97706;">0</div>
                            <div style="font-size:0.8rem;color:#64748b;margin-top:0.25rem;">Old / Renewing</div>
                        </div>
                    </div>

                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;margin-bottom:1.5rem;">
                        <div style="padding:0.75rem 1rem;background:#0B1D4A;color:white;font-weight:600;font-size:0.9rem;">Fee Breakdown</div>
                        <table style="width:100%;border-collapse:collapse;font-size:0.9rem;">
                            <tbody>
                                <tr style="border-bottom:1px solid #e2e8f0;">
                                    <td style="padding:0.75rem 1rem;color:#374151;">National Affiliation Fee</td>
                                    <td style="padding:0.75rem 1rem;color:#64748b;font-size:0.82rem;" id="pay-bracket-label">1–50 members</td>
                                    <td style="padding:0.75rem 1rem;text-align:right;font-weight:600;color:#0B1D4A;" id="pay-affiliation-fee">₱0</td>
                                </tr>
                                <tr style="border-bottom:1px solid #e2e8f0;">
                                    <td style="padding:0.75rem 1rem;color:#374151;">Operational &amp; Activity Fee</td>
                                    <td style="padding:0.75rem 1rem;color:#64748b;font-size:0.82rem;">Local chapter programs</td>
                                    <td style="padding:0.75rem 1rem;text-align:right;font-weight:600;color:#0B1D4A;">₱800</td>
                                </tr>
                                <tr style="border-bottom:1px solid #e2e8f0;background:#fef9c3;">
                                    <td style="padding:0.75rem 1rem;color:#374151;font-weight:600;">Per-Member Fees</td>
                                    <td style="padding:0.75rem 1rem;color:#64748b;font-size:0.82rem;">
                                        <div>New: <span id="pay-new-inline">0</span> × ₱250</div>
                                        <div>Returning: <span id="pay-old-inline">0</span> × ₱200</div>
                                    </td>
                                    <td style="padding:0.75rem 1rem;text-align:right;font-weight:600;color:#0B1D4A;" id="pay-membership-total">₱0</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr style="background:#f0f9ff;">
                                    <td style="padding:1rem;font-weight:700;color:#0B1D4A;font-size:1rem;" colspan="2">Total Amount Due</td>
                                    <td style="padding:1rem;text-align:right;font-weight:700;color:#C49A00;font-size:1.2rem;" id="pay-total-fee">₱0</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:1rem;margin-bottom:1.5rem;font-size:0.88rem;color:#1e40af;">
                        <i class="fas fa-info-circle" style="margin-right:0.5rem;"></i>
                        Payment instructions will be sent to your verified email after the Registration Committee approves your application.
                    </div>

                    <div style="background:#fef9c3;border:1px solid #fde68a;border-radius:8px;padding:1rem;margin-bottom:1.5rem;font-size:0.88rem;color:#92400e;">
                        <i class="fas fa-triangle-exclamation" style="margin-right:0.5rem;"></i>
                        <strong>GCash Simulator (Mock):</strong> This system uses a payment simulator for demonstration purposes only. No real money will be deducted. Actual financial transactions are handled outside this system.
                    </div>

                    <!-- Payment Simulation Button -->
                    <div id="payment-simulation-section" style="margin-bottom:1.5rem;">
                        <button type="button" id="simulate-payment-btn" class="btn btn-primary" style="width:100%;">
                            <i class="fas fa-mobile-alt" style="margin-right:0.5rem;"></i>Simulate GCash Payment
                        </button>
                        <div id="payment-simulation-success" style="display:none;margin-top:1rem;padding:1rem;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;">
                            <div style="display:flex;align-items:center;gap:0.5rem;color:#166534;">
                                <i class="fas fa-check-circle" style="font-size:1.25rem;"></i>
                                <div>
                                    <strong>Payment Simulated Successfully!</strong>
                                    <div style="font-size:0.85rem;margin-top:0.25rem;">Receipt: <strong id="payment-receipt-number"></strong></div>
                                </div>
                            </div>
                        </div>
                        <div id="payment-simulation-error" style="display:none;margin-top:1rem;padding:1rem;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;color:#dc2626;font-size:0.88rem;">
                            <i class="fas fa-exclamation-circle" style="margin-right:0.5rem;"></i>
                            <span id="payment-error-text"></span>
                        </div>
                    </div>

                    <input type="hidden" id="hidden-total-members"  name="total_members">
                    <input type="hidden" id="hidden-new-members"    name="new_members">
                    <input type="hidden" id="hidden-old-members"    name="old_members">
                    <input type="hidden" id="hidden-affiliation-fee" name="affiliation_fee">
                    <input type="hidden" id="hidden-membership-total" name="membership_total">
                    <input type="hidden" id="hidden-total-fee"      name="total_fee">
                </div>

                <div class="modal-actions" style="margin-top:2rem;">
                    <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;">
                        <button type="button" id="modal-back-to-form-btn" class="btn" style="background:#f1f5f9;color:#0B1D4A;border:1px solid #e2e8f0;">
                            <i class="fas fa-arrow-left" style="margin-right:0.5rem;"></i>Back to Form
                        </button>
                        <div>
                            <div class="form-group" style="text-align:center;margin-bottom:0.75rem;">
                                <label style="display:flex;align-items:center;justify-content:center;gap:0.75rem;cursor:pointer;">
                                    <input type="checkbox" id="modal-terms-checkbox" name="terms" value="accepted" required style="width:20px;height:20px;">
                                    <span style="font-size:0.92rem;color:#374151;">I agree to the terms and conditions and certify that all information provided is accurate</span>
                                </label>
                            </div>
                            <button type="submit" id="modal-submit-application-btn" class="btn btn-primary" disabled>
                                <i class="fas fa-paper-plane" style="margin-right:0.5rem;"></i>Submit Application
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ What's New Detail Modal -->
<div id="whatsNewViewModal" role="dialog" aria-modal="true" aria-labelledby="wnm-title-el" onclick="handleWhatsNewBackdropClick(event)">
    <div class="whats-new-modal-dialog" onclick="event.stopPropagation()">
        <button type="button" class="wnm-close-btn" onclick="closeWhatsNewModal()" aria-label="Close dialog">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <div class="wnm-banner" id="wnm-banner-container">
            <img id="wnm-image" src="" alt="Announcement Banner" style="display:none;">
            <div id="wnm-placeholder" class="wnm-banner-placeholder">
                <i class="fa-solid fa-newspaper"></i>
            </div>
        </div>

        <div class="wnm-content">
            <div class="wnm-badge-row">
                <span class="wnm-date-pill" id="wnm-date">
                    <i class="fa-solid fa-calendar-days"></i> <span id="wnm-date-text"></span>
                </span>
                <span class="wnm-date-pill" id="wnm-readtime">
                    <i class="fa-solid fa-clock"></i> <span id="wnm-readtime-text"></span>
                </span>
            </div>

            <h2 class="wnm-title" id="wnm-title-el">Title</h2>

            <div class="wnm-body-text" id="wnm-body-text">
                Full description goes here...
            </div>

            <div class="wnm-actions">
                <a href="#" id="wnm-action-btn" class="btn btn-primary" style="flex:1; min-width:180px; padding:0.8rem 1.5rem; text-decoration:none;">
                    <i class="fa-solid fa-arrow-up-right-from-square me-2"></i> <span id="wnm-btn-text">Learn More</span>
                </a>
                <button type="button" class="btn btn-outline" style="color:#0B1D4A; border-color:#cbd5e1; background:#f8fafc; min-width:130px;" onclick="copyWhatsNewLink()">
                    <i class="fa-solid fa-share-nodes me-2"></i> <span id="wnm-share-text">Share</span>
                </button>
                <button type="button" class="btn btn-outline" style="color:#64748b; border-color:#e2e8f0; min-width:100px;" onclick="closeWhatsNewModal()">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Success Notification Modal -->
<div id="successNotificationModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width:500px;padding:0;overflow:hidden;">
        <div style="background:linear-gradient(135deg,#10b981,#059669);padding:2rem;text-align:center;">
            <div style="width:80px;height:80px;margin:0 auto 1rem;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;animation:scaleIn 0.5s ease;">
                <i class="fas fa-check" style="color:white;font-size:2.5rem;"></i>
            </div>
            <h3 style="color:white;margin:0;font-size:1.75rem;font-weight:700;">Application Submitted!</h3>
        </div>
        <div style="padding:2rem;">
            <p style="color:#64748b;margin-bottom:1.5rem;line-height:1.7;font-size:1rem;text-align:center;">Your affiliation application has been successfully submitted and is now visible to the Registration Committee for review.</p>
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:1.25rem;margin-bottom:1.5rem;">
                <div style="display:flex;align-items:start;gap:0.75rem;">
                    <i class="fas fa-info-circle" style="color:#059669;font-size:1.25rem;margin-top:0.125rem;"></i>
                    <div>
                        <p style="color:#166534;font-size:0.95rem;margin:0;font-weight:600;margin-bottom:0.5rem;">What happens next?</p>
                        <p style="color:#15803d;font-size:0.875rem;margin:0;line-height:1.6;">The Registration Committee will review your application within <strong>5–7 business days</strong>. You will receive an email notification once a decision has been made.</p>
                    </div>
                </div>
            </div>
            <button type="button" onclick="closeSuccessNotification()" class="btn btn-primary" style="width:100%;padding:1rem;font-size:1rem;">
                <i class="fas fa-check" style="margin-right:0.5rem;"></i>Got it, Thanks!
            </button>
        </div>
    </div>
</div>

<style>
@keyframes scaleIn {
    from { transform: scale(0) rotate(-180deg); opacity: 0; }
    to { transform: scale(1) rotate(0deg); opacity: 1; }
}

/* New badge for announcements */
.new-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #D4AF37;
    color: #0B1D4A;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    z-index: 2;
}

/* Event card styling */
.event-card .card-text {
    font-size: 0.9rem;
    line-height: 1.5;
}

.event-card .card-text i {
    color: #D4AF37;
}

/* Responsive adjustments for announcements and events */
@media (min-width: 768px) {
    .cards-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1024px) {
    .cards-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

/* Loading state animation */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.card:has(.card-title:contains('Loading')) {
    animation: pulse 1.5s ease-in-out infinite;
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ── Animated counter for Member Stats ─────────────────────────────────────
    function animateCounter(el) {
        const target = parseInt(el.dataset.target, 10) || 0;
        if (target === 0) { el.textContent = '0'; return; }
        const duration = 1800;
        const start    = performance.now();
        function update(now) {
            const elapsed  = now - start;
            const progress = Math.min(elapsed / duration, 1);
            // ease-out cubic
            const eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.round(eased * target).toLocaleString();
            if (progress < 1) requestAnimationFrame(update);
        }
        requestAnimationFrame(update);
    }

    // Run animation when stats section enters viewport
    const statsSection = document.getElementById('member-stats');
    if (statsSection) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    statsSection.querySelectorAll('.stat-number[data-target]').forEach(animateCounter);
                    observer.unobserve(statsSection);
                }
            });
        }, { threshold: 0.2 });
        observer.observe(statsSection);
    }

    // ── Mobile menu ────────────────────────────────────────────────────────────
    const mobileMenuBtn     = document.querySelector('.mobile-menu-btn');
    const mobileNav         = document.querySelector('.mobile-nav');
    const mobileNavOverlay  = document.querySelector('.mobile-nav-overlay');
    const mobileNavClose    = document.querySelector('.mobile-nav-close');

    function closeMobileMenu() {
        mobileMenuBtn?.classList.remove('active');
        mobileNav?.classList.remove('active');
        mobileNavOverlay?.classList.remove('active');
        mobileMenuBtn?.setAttribute('aria-expanded', 'false');
    }

    mobileMenuBtn?.addEventListener('click', function () {
        this.classList.toggle('active');
        mobileNav?.classList.toggle('active');
        mobileNavOverlay?.classList.toggle('active');
        this.setAttribute('aria-expanded', this.classList.contains('active'));
    });
    mobileNavClose?.addEventListener('click', closeMobileMenu);
    mobileNavOverlay?.addEventListener('click', closeMobileMenu);

    // ── Login button ───────────────────────────────────────────────────────────
    document.querySelector('.btn-login')?.addEventListener('click', function (e) {
        e.preventDefault();
        window.location.href = '<?php echo BASE_URL; ?>/login.php';
    });

    // ── Modal setup ────────────────────────────────────────────────────────────
    const API_BASE_URL = '<?php echo BASE_URL; ?>';
    let verifiedEmail = '';

    const overlay = document.createElement('div');
    overlay.id = 'affiliateOverlay';
    overlay.style.cssText = 'display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(11,29,74,0.15);backdrop-filter:blur(1px);z-index:99999;overflow-y:auto;';

    const overlayInner = document.createElement('div');
    overlayInner.style.cssText = 'min-height:100%;display:flex;align-items:center;justify-content:center;padding:1.5rem;box-sizing:border-box;';
    overlay.appendChild(overlayInner);
    document.body.appendChild(overlay);

    const modal        = document.getElementById('affiliateModal');
    const modalContent = modal.querySelector('.modal-content');

    function openModal() {
        overlayInner.appendChild(modalContent);
        modalContent.style.cssText = 'width:100%;max-width:820px;background:white;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,0.3);position:relative;flex-shrink:0;';
        overlay.style.display = 'block';
        document.body.style.overflow = 'hidden';
        resetModal();
    }

    function closeModal() {
        overlay.style.display = 'none';
        document.body.style.overflow = '';
    }

    function resetModal() {
        document.getElementById('modal-step1')?.classList.add('active');
        document.getElementById('modal-step1')?.classList.remove('completed');
        document.getElementById('modal-step2')?.classList.remove('active', 'completed');
        document.getElementById('modal-step3')?.classList.remove('active', 'completed');

        const toolkitPanel = document.getElementById('modal-toolkit-panel');
        if (toolkitPanel) toolkitPanel.style.display = 'block';

        const emailStep = document.getElementById('modal-email-verification-step');
        const formStep  = document.getElementById('modal-application-form-step');
        const payStep   = document.getElementById('modal-payment-step');

        if (emailStep) { emailStep.style.display = 'block'; emailStep.classList.add('active'); }
        if (formStep)  { formStep.style.display  = 'none';  formStep.classList.remove('active'); }
        if (payStep)   { payStep.style.display   = 'none'; }

        document.getElementById('modal-email-form')?.setAttribute('style', 'display:block');
        document.getElementById('modal-code-form')?.setAttribute('style', 'display:none');
        document.getElementById('modal-verification-email') && (document.getElementById('modal-verification-email').value = '');
        document.querySelectorAll('.code-input').forEach(i => i.value = '');
        document.getElementById('modal-verification-error') && (document.getElementById('modal-verification-error').style.display = 'none');
        document.getElementById('modal-verification-success') && (document.getElementById('modal-verification-success').style.display = 'none');
        verifiedEmail = '';
    }

    document.querySelectorAll('#affiliateNowBtn, #ctaAffiliateBtn, #howToAffiliateCtaBtn, .btn-affiliate-trigger').forEach(btn => btn?.addEventListener('click', function(e) {
        if (overlay.style.display === 'block') {
            // Already inside modal, scroll to email input
            const emailInput = document.getElementById('modal-verification-email');
            if (emailInput) {
                emailInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                emailInput.focus();
            }
            return;
        }
        openModal();
    }));
    document.getElementById('closeModalBtn')?.addEventListener('click', closeModal);
    overlay.addEventListener('click', e => { if (e.target === overlay || e.target === overlayInner) closeModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && overlay.style.display !== 'none') closeModal(); });

    // ── Send Code ─────────────────────────────────────────────────────────────
    document.getElementById('modal-send-code-btn')?.addEventListener('click', async function () {
        const email = document.getElementById('modal-verification-email').value.trim();
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showNotification('error', 'Please enter a valid email address');
            return;
        }
        this.disabled = true;
        this.innerHTML = '<span class="spinner"></span> Sending...';
        try {
            const res    = await fetch(API_BASE_URL + '/index.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=send_code&email=' + encodeURIComponent(email),
            });
            if (!res.ok) throw new Error(`Server error: ${res.status}`);
            const result = await res.json();
            if (result.success) {
                result.code
                    ? showModalSuccess(`Verification code: ${result.code} (Email not configured — use this code)`)
                    : showModalSuccess('Verification code sent to your email!');
                document.getElementById('modal-sent-email').textContent = email;
                document.getElementById('modal-email-form').style.display = 'none';
                document.getElementById('modal-code-form').style.display  = 'block';
                setupCodeInputs();
            } else {
                showNotification('error', result.message || 'Failed to send verification code');
                this.disabled = false;
                this.innerHTML = 'Send Verification Code';
            }
        } catch (err) {
            showNotification('error', 'Network error: ' + err.message);
            this.disabled = false;
            this.innerHTML = 'Send Verification Code';
        }
    });

    // ── Verify Code ───────────────────────────────────────────────────────────
    document.getElementById('modal-verify-code-btn')?.addEventListener('click', async function () {
        const codeInputs = document.querySelectorAll('#modal-code-form .code-input, .code-input');
        const code  = Array.from(codeInputs).map(i => i.value.trim()).join('');
        const email = (document.getElementById('modal-verification-email')?.value || '').trim();
        if (code.length !== 6) { showModalError('Please enter the complete 6-digit code'); return; }
        this.disabled = true;
        this.innerHTML = '<span class="spinner"></span> Verifying...';
        try {
            const res    = await fetch(API_BASE_URL + '/index.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=verify_code&email=' + encodeURIComponent(email) + '&code=' + encodeURIComponent(code),
            });
            if (!res.ok) throw new Error(`Server error: ${res.status}`);
            const result = await res.json();
            if (result.success) {
                verifiedEmail = email;
                showModalSuccess('Email verified successfully! Proceeding to application form...');
                setTimeout(moveToStep2, 1200);
            } else {
                showModalError(result.message || 'Invalid or expired verification code');
                this.disabled = false;
                this.innerHTML = 'Verify Code';
            }
        } catch (err) {
            showModalError('Network error. Please try again.');
            this.disabled = false;
            this.innerHTML = 'Verify Code';
        }
    });

    function setupCodeInputs() {
        const inputs = document.querySelectorAll('#modal-code-form .code-input, .code-input');
        inputs.forEach((input, idx) => {
            input.addEventListener('input', (e) => {
                const val = e.target.value;
                if (val.length > 1) {
                    const cleanDigits = val.replace(/\D/g, '').split('');
                    cleanDigits.forEach((d, i) => {
                        if (inputs[i]) inputs[i].value = d;
                    });
                    const nextIdx = Math.min(cleanDigits.length, inputs.length - 1);
                    inputs[nextIdx].focus();
                    if (cleanDigits.length >= 6) {
                        document.getElementById('modal-verify-code-btn')?.click();
                    }
                    return;
                }
                if (val && idx < inputs.length - 1) {
                    inputs[idx + 1].focus();
                }
                const fullCode = Array.from(inputs).map(i => i.value.trim()).join('');
                if (fullCode.length === 6) {
                    document.getElementById('modal-verify-code-btn')?.click();
                }
            });
            input.addEventListener('keydown', e => {
                if (e.key === 'Backspace' && !input.value && idx > 0) {
                    inputs[idx - 1].focus();
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('modal-verify-code-btn')?.click();
                }
            });
            input.addEventListener('paste', e => {
                e.preventDefault();
                const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
                const digits = pasted.split('');
                digits.forEach((d, i) => {
                    if (i < inputs.length) inputs[i].value = d;
                });
                if (digits.length >= inputs.length) {
                    inputs[inputs.length - 1].focus();
                    document.getElementById('modal-verify-code-btn')?.click();
                } else if (digits.length > 0 && inputs[digits.length]) {
                    inputs[digits.length].focus();
                }
            });
        });
        if (inputs[0]) inputs[0].focus();
    }

    // ── Fee computation (Constitution Art. IV Sec. 2) ────────────────────────
    let memberData = { total: 0, newCount: 0, oldCount: 0 };

    function computeFees(total, newCount, oldCount) {
        // Bracketed affiliation fee
        let affiliation = 1500, bracket = '1–50 members';
        if      (total > 150) { affiliation = 3000; bracket = '151+ members'; }
        else if (total > 100) { affiliation = 2500; bracket = '101–150 members'; }
        else if (total > 50)  { affiliation = 2000; bracket = '51–100 members'; }
        
        // Per-member fees (Constitution Art. IV Sec. 2)
        const newMemberFee = 250;      // New members: ₱250 each
        const returningMemberFee = 200; // Returning members: ₱200 each
        const membershipTotal = (newCount * newMemberFee) + (oldCount * returningMemberFee);
        
        // Operational fee
        const operational = 800;
        
        // Total = affiliation + operational + membership fees
        const grandTotal = affiliation + operational + membershipTotal;
        
        return { 
            affiliation, 
            operational, 
            membershipTotal,
            total: grandTotal, 
            bracket,
            newMemberFee,
            returningMemberFee
        };
    }

    // ── Step navigation ───────────────────────────────────────────────────────
    function moveToStep2() {
        document.getElementById('modal-step1').classList.replace('active', 'completed') || document.getElementById('modal-step1').classList.add('completed');
        document.getElementById('modal-step2').classList.add('active');
        document.getElementById('modal-email-verification-step').style.display = 'none';
        const formStep = document.getElementById('modal-application-form-step');
        formStep.style.display = 'block';
        formStep.classList.add('active');
        document.getElementById('modal-contact-email').value = verifiedEmail;
        document.getElementById('modal-document-upload-section').style.display = 'block';
        overlay.scrollTop = 0;
    }

    function moveToStep3() {
        document.getElementById('modal-step2').classList.remove('active');
        document.getElementById('modal-step2').classList.add('completed');
        document.getElementById('modal-step3').classList.add('active');
        document.getElementById('modal-application-form-step').style.display = 'none';
        document.getElementById('modal-payment-step').style.display = 'block';

        const fees = computeFees(memberData.total, memberData.newCount, memberData.oldCount);
        document.getElementById('pay-total').textContent         = memberData.total;
        document.getElementById('pay-new').textContent           = memberData.newCount;
        document.getElementById('pay-old').textContent           = memberData.oldCount;
        document.getElementById('pay-new-inline').textContent    = memberData.newCount;
        document.getElementById('pay-old-inline').textContent    = memberData.oldCount;
        document.getElementById('pay-bracket-label').textContent = fees.bracket;
        document.getElementById('pay-affiliation-fee').textContent = '₱' + fees.affiliation.toLocaleString();
        document.getElementById('pay-membership-total').textContent = '₱' + fees.membershipTotal.toLocaleString();
        document.getElementById('pay-total-fee').textContent     = '₱' + fees.total.toLocaleString();

        document.getElementById('hidden-total-members').value    = memberData.total;
        document.getElementById('hidden-new-members').value      = memberData.newCount;
        document.getElementById('hidden-old-members').value      = memberData.oldCount;
        document.getElementById('hidden-affiliation-fee').value  = fees.affiliation;
        document.getElementById('hidden-membership-total').value = fees.membershipTotal;
        document.getElementById('hidden-total-fee').value        = fees.total;
        
        // Check if payment was already simulated
        checkPaymentSimulation();
        
        overlay.scrollTop = 0;
    }

    document.getElementById('modal-proceed-payment-btn')?.addEventListener('click', function() {
        // Validate phone number before proceeding
        const phoneInput = document.getElementById('modal-contact-phone');
        const phoneValue = phoneInput.value.trim();
        
        if (!phoneValue || !/^09\d{9}$/.test(phoneValue)) {
            showNotification('error', 'Please enter a valid 11-digit phone number starting with 09');
            phoneInput.focus();
            return;
        }
        
        moveToStep3();
    });

    document.getElementById('modal-back-to-form-btn')?.addEventListener('click', function () {
        document.getElementById('modal-step3').classList.remove('active');
        document.getElementById('modal-step2').classList.remove('completed');
        document.getElementById('modal-step2').classList.add('active');
        document.getElementById('modal-payment-step').style.display         = 'none';
        document.getElementById('modal-application-form-step').style.display = 'block';
        overlay.scrollTop = 0;
    });

    document.getElementById('modal-terms-checkbox')?.addEventListener('change', function () {
        // Only enable submit if payment is simulated AND terms are checked
        const paymentSimulated = sessionStorage.getItem('payment_simulated') === 'true';
        document.getElementById('modal-submit-application-btn').disabled = !(this.checked && paymentSimulated);
    });

    // ── Payment Simulation ────────────────────────────────────────────────────
    let paymentSimulated = false;

    // Check if payment was already simulated (on modal open)
    function checkPaymentSimulation() {
        const simulated = sessionStorage.getItem('payment_simulated');
        const receiptNumber = sessionStorage.getItem('payment_receipt_number');
        const simulatedEmail = sessionStorage.getItem('payment_simulated_email');
        
        // Only restore if it's for the same email
        if (simulated === 'true' && receiptNumber && simulatedEmail === verifiedEmail) {
            paymentSimulated = true;
            document.getElementById('simulate-payment-btn').disabled = true;
            document.getElementById('simulate-payment-btn').innerHTML = '<i class="fas fa-check-circle" style="margin-right:0.5rem;"></i>Payment Already Simulated';
            document.getElementById('payment-simulation-success').style.display = 'block';
            document.getElementById('payment-receipt-number').textContent = receiptNumber;
            
            // Enable submit button if terms are checked
            const termsChecked = document.getElementById('modal-terms-checkbox')?.checked;
            if (termsChecked) {
                document.getElementById('modal-submit-application-btn').disabled = false;
            }
        }
    }

    document.getElementById('simulate-payment-btn')?.addEventListener('click', async function() {
        if (paymentSimulated) {
            showNotification('info', 'Payment has already been simulated');
            return;
        }

        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span> Simulating Payment...';
        
        document.getElementById('payment-simulation-error').style.display = 'none';

        try {
            const totalFee = parseFloat(document.getElementById('hidden-total-fee').value);
            const affiliationFee = parseFloat(document.getElementById('hidden-affiliation-fee').value);
            const membershipTotal = parseFloat(document.getElementById('hidden-membership-total').value);
            const memberCount = parseInt(document.getElementById('hidden-total-members').value);
            const newMembers = parseInt(document.getElementById('hidden-new-members').value);
            const oldMembers = parseInt(document.getElementById('hidden-old-members').value);
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

            const response = await fetch(API_BASE_URL + '/public/api/simulate-payment.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    total_fee: totalFee,
                    affiliation_fee: affiliationFee,
                    operational_fee: 800,
                    membership_total: membershipTotal,
                    member_count: memberCount,
                    new_members: newMembers,
                    old_members: oldMembers
                })
            });

            if (!response.ok) {
                const contentType = response.headers.get('content-type');
                let errorMsg = 'HTTP ' + response.status + ': ' + response.statusText;
                if (contentType && contentType.includes('application/json')) {
                    try {
                        const errorData = await response.json();
                        errorMsg = errorData.error || errorData.message || errorMsg;
                    } catch (e) {}
                } else {
                    const text = await response.text();
                    console.error('Non-JSON payment error:', text.substring(0, 300));
                }
                throw new Error(errorMsg);
            }

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Non-JSON payment response:', {status: response.status, contentType: contentType, preview: text.substring(0, 300)});
                throw new Error('Server returned non-JSON response');
            }

            const result = await response.json();

            if (result.success) {
                paymentSimulated = true;
                
                // Store in sessionStorage
                sessionStorage.setItem('payment_simulated', 'true');
                sessionStorage.setItem('payment_receipt_number', result.receipt_number);
                sessionStorage.setItem('payment_simulated_email', verifiedEmail);
                
                // Update UI
                btn.innerHTML = '<i class="fas fa-check-circle" style="margin-right:0.5rem;"></i>Payment Simulated';
                document.getElementById('payment-simulation-success').style.display = 'block';
                document.getElementById('payment-receipt-number').textContent = result.receipt_number;
                
                // Enable submit button if terms are checked
                const termsChecked = document.getElementById('modal-terms-checkbox')?.checked;
                if (termsChecked) {
                    document.getElementById('modal-submit-application-btn').disabled = false;
                }
                
                showNotification('success', result.message || 'Payment simulation successful!');
            } else {
                throw new Error(result.error || 'Payment simulation failed');
            }
        } catch (error) {
            console.error('Payment simulation error:', error);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-mobile-alt" style="margin-right:0.5rem;"></i>Simulate GCash Payment';
            
            document.getElementById('payment-simulation-error').style.display = 'block';
            document.getElementById('payment-error-text').textContent = error.message || 'Failed to simulate payment. Please try again.';
            
            showNotification('error', 'Payment simulation failed: ' + error.message);
        }
    });

    document.getElementById('modal-submit-application-btn')?.addEventListener('click', function () {
        document.getElementById('form-contact-email').value = verifiedEmail;
    });

    // ── Member Directory: parse on file select ────────────────────────────────
    document.getElementById('member_directory_file')?.addEventListener('change', function () {
        const file       = this.files[0];
        const wrapper    = document.getElementById('member_directory_wrapper');
        const resultEl   = document.getElementById('member-parse-result');
        const errorEl    = document.getElementById('member-parse-error');
        const proceedBtn = document.getElementById('modal-proceed-payment-btn');
        const label      = wrapper.querySelector('.file-upload-label');
        const labelText  = label.querySelector('.label-text');
        const fileSel    = label.querySelector('.file-selected');
        const fileNameEl = label.querySelector('.file-name');

        resultEl.style.display = 'none';
        errorEl.style.display  = 'none';
        document.getElementById('parse-warning').style.display = 'none';
        proceedBtn.disabled = true;

        if (!file) { labelText.style.display = 'block'; fileSel.style.display = 'none'; return; }

        const ext = file.name.split('.').pop().toLowerCase();
        if (!['csv', 'xls', 'xlsx'].includes(ext)) {
            this.value = '';
            labelText.style.display = 'block'; fileSel.style.display = 'none';
            wrapper.style.borderColor = '#f87171'; wrapper.style.backgroundColor = '#fef2f2';
            document.getElementById('parse-error-text').textContent = `Invalid file type ".${ext}". Only CSV or Excel files are allowed.`;
            errorEl.style.display = 'block';
            return;
        }

        labelText.style.display = 'none'; fileSel.style.display = 'flex';
        fileNameEl.textContent  = file.name;
        wrapper.style.borderColor = '#10b981'; wrapper.style.backgroundColor = '#f0fdf4';

        const reader = new FileReader();
        reader.onload = function (e) {
            try {
                let rows = [];
                if (ext === 'csv') {
                    const lines = e.target.result.split(/\r?\n/).filter(l => l.trim());
                    rows = lines.map(line => {
                        const cells = []; let cur = '', inQ = false;
                        for (const ch of line) {
                            if (ch === '"') { inQ = !inQ; }
                            else if (ch === ',' && !inQ) { cells.push(cur.trim()); cur = ''; }
                            else { cur += ch; }
                        }
                        cells.push(cur.trim());
                        return cells;
                    });
                } else {
                    const wb  = XLSX.read(new Uint8Array(e.target.result), { type: 'array' });
                    const ws  = wb.Sheets[wb.SheetNames[0]];
                    rows = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '' });
                }

                if (rows.length < 2) throw new Error('File appears to be empty or has only a header row.');

                const header = rows[0].map(h => String(h).trim().toLowerCase());
                
                // Improved column detection: search for multiple possible column names
                const typeKeywords = ['status', 'member type', 'type', 'classification', 'member classification', 'new/old', 'member status'];
                let typeCol = -1;
                for (let i = 0; i < header.length; i++) {
                    const h = header[i];
                    if (typeKeywords.some(keyword => h.includes(keyword))) {
                        typeCol = i;
                        break;
                    }
                }

                let newCount = 0, oldCount = 0, unknownCount = 0, totalRows = 0;
                
                // Count total valid rows first
                for (let i = 1; i < rows.length; i++) {
                    if (!rows[i].every(c => String(c).trim() === '')) totalRows++;
                }

                if (typeCol === -1) {
                    // No type column found – assume all members are new
                    newCount = totalRows;
                    oldCount = 0;
                } else {
                    // Parse each row and map values
                    for (let i = 1; i < rows.length; i++) {
                        const row = rows[i];
                        if (row.every(c => String(c).trim() === '')) continue;
                        
                        const val = String(row[typeCol] || '').trim().toLowerCase();
                        
                        // New member variations
                        if (val === 'new' || val === 'new member' || val === 'new_member' || 
                            val === '1st time' || val === 'first time' || val === 'newcomer') {
                            newCount++;
                        }
                        // Old/returning member variations
                        else if (val === 'old' || val === 'old member' || val === 'returning' || 
                                 val === 'renewing' || val === 'renewal' || val === 'continuing' || val === 'existing') {
                            oldCount++;
                        }
                        // Unrecognized – default to new
                        else {
                            newCount++;
                            unknownCount++;
                        }
                    }
                }

                const total = newCount + oldCount;
                memberData = { total, newCount, oldCount };

                document.getElementById('parse-total').textContent = total;
                document.getElementById('parse-new').textContent   = newCount;
                document.getElementById('parse-old').textContent   = oldCount;
                resultEl.style.display = 'block';

                if (unknownCount > 0) {
                    document.getElementById('parse-warning-text').textContent =
                        `${unknownCount} row(s) had unrecognised member types and were counted as new members.`;
                    document.getElementById('parse-warning').style.display = 'block';
                }
                
                if (typeCol === -1) {
                    document.getElementById('parse-warning-text').textContent =
                        `No member type column found. All ${total} members were counted as new members.`;
                    document.getElementById('parse-warning').style.display = 'block';
                }

                if (total === 0) throw new Error('No valid member rows found. Please check the file content.');
                proceedBtn.disabled = false;

            } catch (err) {
                document.getElementById('parse-error-text').textContent = err.message;
                errorEl.style.display = 'block';
                wrapper.style.borderColor = '#f87171'; wrapper.style.backgroundColor = '#fef2f2';
            }
        };
        ext === 'csv' ? reader.readAsText(file) : reader.readAsArrayBuffer(file);
    });

    // ── PDF file upload feedback ───────────────────────────────────────────────
    function showFileError(wrapper, label, labelText, fileSel, msg) {
        labelText.style.display = 'block'; fileSel.style.display = 'none';
        wrapper.style.borderColor = '#f87171'; wrapper.style.backgroundColor = '#fef2f2';
        let errEl = wrapper.parentElement.querySelector('.file-type-error');
        if (!errEl) {
            errEl = document.createElement('div');
            errEl.className = 'file-type-error';
            errEl.style.cssText = 'margin-top:0.4rem;padding:0.4rem 0.75rem;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;color:#991b1b;font-size:0.82rem;';
            wrapper.parentElement.appendChild(errEl);
        }
        errEl.innerHTML = `<i class="fas fa-circle-exclamation" style="margin-right:0.4rem;"></i>${msg}`;
        errEl.style.display = 'block';
    }

    function clearFileError(wrapper) {
        wrapper.style.borderColor = ''; wrapper.style.backgroundColor = '';
        wrapper.parentElement.querySelector('.file-type-error')?.setAttribute('style', 'display:none');
    }

    document.querySelectorAll('#modal-document-upload-section input[type="file"]:not(#member_directory_file)').forEach(input => {
        input.addEventListener('change', function () {
            const wrapper   = this.closest('.file-upload-wrapper');
            const label     = wrapper.querySelector('.file-upload-label');
            const labelText = label.querySelector('.label-text');
            const fileSel   = label.querySelector('.file-selected');
            const fileNameEl= label.querySelector('.file-name');

            if (!this.files[0]) { labelText.style.display = 'block'; fileSel.style.display = 'none'; clearFileError(wrapper); return; }

            const ext = this.files[0].name.split('.').pop().toLowerCase();
            if (ext !== 'pdf') {
                this.value = '';
                showFileError(wrapper, label, labelText, fileSel, `Invalid file type ".${ext}". Only PDF files are allowed here.`);
                return;
            }
            clearFileError(wrapper);
            labelText.style.display = 'none'; fileSel.style.display = 'flex';
            fileNameEl.textContent  = this.files[0].name;
            wrapper.style.borderColor = '#10b981'; wrapper.style.backgroundColor = '#f0fdf4';
        });
    });

    // Form submission - AJAX fetch to submit-affiliation.php
    document.getElementById('affiliationForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitBtn = document.getElementById('modal-submit-application-btn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner"></span> Submitting...';
        
        const formData = new FormData(this);
        formData.set('contact_email', verifiedEmail);
        formData.delete('action');
        
        try {
            const response = await fetch(API_BASE_URL + '/public/api/submit-affiliation.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Close affiliate modal
                const affiliateModal = document.getElementById('affiliateModal');
                if (affiliateModal) {
                    affiliateModal.classList.remove('active');
                    affiliateModal.style.display = 'none';
                }
                document.body.style.overflow = '';
                const overlay = document.getElementById('affiliateOverlay');
                if (overlay) overlay.remove();
                
                // Show success notification modal
                const successModal = document.getElementById('successNotificationModal');
                if (successModal) {
                    successModal.style.display = 'flex';
                }
            } else {
                showNotification('error', result.message || 'Failed to submit application. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane" style="margin-right:0.5rem;"></i>Submit Application';
            }
        } catch (error) {
            console.error('Submit error:', error);
            showNotification('error', 'Network error. Please check your connection and try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane" style="margin-right:0.5rem;"></i>Submit Application';
        }
    });

    // ── Notification helpers ───────────────────────────────────────────────────
    function showModalError(msg) {
        const el = document.getElementById('modal-verification-error');
        el.textContent = msg; el.style.display = 'block';
        document.getElementById('modal-verification-success').style.display = 'none';
        setTimeout(() => { el.style.display = 'none'; }, 5000);
    }

    function showModalSuccess(msg) {
        const el = document.getElementById('modal-verification-success');
        el.textContent = msg; el.style.display = 'block';
        document.getElementById('modal-verification-error').style.display = 'none';
        setTimeout(() => { el.style.display = 'none'; }, 5000);
    }

    function showNotification(type, msg) {
        const container = document.getElementById('modalNotificationContainer');
        if (!container) return;
        const icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', info: 'fa-info-circle' };
        const n = document.createElement('div');
        n.className = `modal-notification ${type}`;
        n.innerHTML = `<div class="modal-notification-icon"><i class="fas ${icons[type] || icons.info}"></i></div>
                       <div class="modal-notification-content">${msg}</div>
                       <button class="modal-notification-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>`;
        container.innerHTML = '';
        container.appendChild(n);
        container.style.display = 'block';
        setTimeout(() => {
            n.style.animation = 'fadeOut 0.3s ease-out forwards';
            setTimeout(() => { n.remove(); if (!container.children.length) container.style.display = 'none'; }, 300);
        }, 5000);
    }

});

function closeSuccessNotification() {
    const modal = document.getElementById('successNotificationModal');
    if (modal) {
        modal.style.display = 'none';
    }
    // Reload page to reset everything
    window.location.reload();
}

// ── What's New Detail Modal Handlers ───────────────────────────────────────
window.openWhatsNewModal = function(cardData) {
    if (!cardData) return;
    const modal = document.getElementById('whatsNewViewModal');
    if (!modal) return;

    // Fill Title
    const titleEl = document.getElementById('wnm-title-el');
    if (titleEl) titleEl.textContent = cardData.title || 'Chapter Announcement';
    
    // Fill Dates
    const dateEl = document.getElementById('wnm-date-text');
    if (dateEl) dateEl.textContent = cardData.date || 'Recent';
    
    const readEl = document.getElementById('wnm-readtime-text');
    if (readEl) readEl.textContent = cardData.read_time || '2 min read';

    // Fill Body Text
    const bodyEl = document.getElementById('wnm-body-text');
    if (bodyEl) {
        let desc = cardData.description || 'No additional details provided.';
        // Clean up quill cursor artifacts
        desc = desc.replace(/<span class="ql-cursor"[^>]*>.*?<\/span>/gi, '');
        // If it contains HTML tags, render as HTML; otherwise render text with linebreaks
        if (/<[a-z][\s\S]*>/i.test(desc)) {
            bodyEl.innerHTML = desc;
        } else {
            bodyEl.innerHTML = desc.split('\n').filter(line => line.trim() !== '').map(line => `<p>${line}</p>`).join('');
        }
    }

    // Fill Image
    const imgEl = document.getElementById('wnm-image');
    const placeholderEl = document.getElementById('wnm-placeholder');
    if (imgEl && placeholderEl) {
        if (cardData.image_url && cardData.image_url.trim() !== '') {
            imgEl.src = cardData.image_url;
            imgEl.style.display = 'block';
            placeholderEl.style.display = 'none';
        } else {
            imgEl.style.display = 'none';
            placeholderEl.style.display = 'flex';
        }
    }

    // Fill Action Button
    const actionBtn = document.getElementById('wnm-action-btn');
    const btnText = document.getElementById('wnm-btn-text');
    if (actionBtn && btnText) {
        if (cardData.button_url && cardData.button_url !== '#' && cardData.button_url.trim() !== '') {
            actionBtn.href = cardData.button_url;
            actionBtn.style.display = 'inline-flex';
            btnText.textContent = cardData.button_text || 'Learn More';
            if (cardData.button_url.startsWith('http')) {
                actionBtn.target = '_blank';
                actionBtn.rel = 'noopener';
            } else {
                actionBtn.target = '_self';
                if (cardData.button_url === '#how-to-affiliate') {
                    actionBtn.onclick = function(e) {
                        closeWhatsNewModal();
                    };
                }
            }
        } else {
            actionBtn.style.display = 'none';
        }
    }

    modal.style.display = 'flex';
    setTimeout(() => { modal.classList.add('active'); }, 10);
    document.body.style.overflow = 'hidden';
};

window.closeWhatsNewModal = function() {
    const modal = document.getElementById('whatsNewViewModal');
    if (!modal) return;
    modal.classList.remove('active');
    setTimeout(() => {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }, 250);
};

window.handleWhatsNewBackdropClick = function(event) {
    if (event.target === document.getElementById('whatsNewViewModal')) {
        closeWhatsNewModal();
    }
};

window.copyWhatsNewLink = function() {
    const shareText = document.getElementById('wnm-share-text');
    const currentUrl = window.location.origin + window.location.pathname + '#features';
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(currentUrl).then(() => {
            if (shareText) {
                const orig = shareText.textContent;
                shareText.textContent = 'Copied!';
                setTimeout(() => { shareText.textContent = orig; }, 2000);
            }
        }).catch(() => {
            if (shareText) {
                shareText.textContent = 'Copied!';
                setTimeout(() => { shareText.textContent = 'Share'; }, 2000);
            }
        });
    } else {
        if (shareText) {
            shareText.textContent = 'Copied!';
            setTimeout(() => { shareText.textContent = 'Share'; }, 2000);
        }
    }
};

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const wnm = document.getElementById('whatsNewViewModal');
        if (wnm && wnm.style.display !== 'none' && wnm.classList.contains('active')) {
            closeWhatsNewModal();
        }
    }
});
</script>
</body>
</html>