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
header("Content-Security-Policy: script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdn.skypack.dev; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com https://fonts.gstatic.com; img-src 'self' data: https:; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; connect-src 'self' https://cdnjs.cloudflare.com https://fonts.googleapis.com https://fcm.googleapis.com https://updates.push.services.mozilla.com https://cdn.jsdelivr.net https://*.googleapis.com https://*.supabase.co wss://*.supabase.co;");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/bootstrap.php';

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

            // Block already-registered emails
            $existing = $supabaseClient->select('pending_affiliations', ['email' => 'eq.' . $email]);
            if (!empty($existing) && is_array($existing)) {
                $status = $existing[0]['status'] ?? '';
                $message = $status === 'approved'
                    ? 'This email is already registered with an approved affiliation. Please use a different email or contact support.'
                    : 'You already have a pending affiliation application with this email. Please contact support for assistance.';
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => $message, 'resubmit_available' => $status !== 'approved']);
                exit;
            }

            // Generate & store 6-digit code
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $_SESSION['verification_code']  = $code;
            $_SESSION['verification_email'] = $email;
            $_SESSION['code_sent_time']     = time();

            try {
                $supabaseClient->insert('verification_codes', [
                    'email'      => $email,
                    'code'       => $code,
                    'expires_at' => date('Y-m-d H:i:s', time() + 600),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
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
                $emailSent    = $emailService->sendVerificationCode($email, $code);
            } catch (Exception $e) {
                $emailError = $e->getMessage();
                error_log("Email send error: $emailError\n" . $e->getTraceAsString());
            }

            $response = ['success' => true, 'message' => 'Verification code sent to your email!'];
            if (!$emailSent) {
                $response['code']    = $code; // dev fallback
                $response['message'] = $emailError
                    ? 'Verification code generated. Email failed: ' . $emailError
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

        $email    = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $code     = $_POST['code'] ?? '';
        $verified = false;

        // 1. Session check
        if (
            isset($_SESSION['verification_code'], $_SESSION['verification_email']) &&
            time() - ($_SESSION['code_sent_time'] ?? 0) < 600 &&
            $_SESSION['verification_code'] === $code &&
            $_SESSION['verification_email'] === $email
        ) {
            unset($_SESSION['verification_code']);
            $verified = true;
        }

        // 2. Supabase fallback
        if (!$verified) {
            try {
                $supabaseConfig = require __DIR__ . '/includes/supabase.php';
                $supabaseClient = new SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);
                $result = $supabaseClient->select('verification_codes', [
                    'email'      => 'eq.' . $email,
                    'code'       => 'eq.' . $code,
                    'expires_at' => 'gte.' . date('Y-m-d H:i:s'),
                    'used_at'    => 'is.null',
                ]);
                if (!empty($result) && is_array($result)) {
                    $supabaseClient->update('verification_codes', ['used_at' => date('Y-m-d H:i:s')], $result[0]['id']);
                    unset($_SESSION['verification_code']);
                    $verified = true;
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
                'redirect' => '/IECEP-LSC-MEMSYS/public/api/submit-affiliation.php',
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
        $role = $_SESSION['user']['role'] ?? '';
        $redirectMap = [
            'eb_president'         => PORTAL_URL . '/super-admin/dashboard.php',
            'admin'                => PORTAL_URL . '/admin/dashboard.php',
            'school_officer'       => PORTAL_URL . '/school-officer/dashboard.php',
            'member'               => PORTAL_URL . '/member/dashboard.php',
            'eb_pro_1'             => PORTAL_URL . '/creatives/dashboard.php',
            'committee_creatives'  => PORTAL_URL . '/creatives/dashboard.php',
            'eb_pro_2'             => PORTAL_URL . '/logistics/dashboard.php',
            'eb_treasurer'         => PORTAL_URL . '/treasurer/dashboard.php',
            'eb_auditor'           => PORTAL_URL . '/auditor/dashboard.php',
            'eb_secretary_general' => PORTAL_URL . '/secretary/dashboard.php',
        ];
        header('Location: ' . ($redirectMap[$role] ?? PORTAL_URL . '/member/dashboard.php'));
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
    <style>
        /* ── Responsive overrides ─────────────────────────────────────────── */
        @media (max-width: 575.98px) {
            .modal-content { margin: 1rem; max-width: calc(100% - 2rem); max-height: 90vh; border-radius: var(--radius-lg); }
            #affiliateModal .modal-content { margin: auto !important; max-width: 95% !important; }
            .modal-title { font-size: 1.5rem; margin-bottom: 1.5rem; }
            .step-indicator { flex-direction: column; gap: 0.5rem; margin-bottom: 1.5rem; }
            .step-indicator-item { width: 100%; justify-content: center; }
            .step-indicator-line { width: 2px; height: 20px; transform: rotate(90deg); }
            .form-input, .form-textarea { font-size: 16px; }
            .verification-inputs { gap: 0.5rem; }
            .verification-inputs input { width: 40px; height: 45px; font-size: 1.25rem; }
            .contact-container { grid-template-columns: 1fr; gap: 2rem; }
        }

        @media (min-width: 576px) and (max-width: 767.98px) {
            .modal-content { margin: 2rem; max-width: calc(100% - 4rem); }
            #affiliateModal .modal-content { margin: auto !important; max-width: 90% !important; }
            .verification-inputs input { width: 45px; height: 50px; }
        }

        @media (hover: none) and (pointer: coarse) {
            .btn, button, input[type="submit"], input[type="button"] { min-height: 48px; min-width: 48px; }
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
    </style>
</head>
<body>

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
        <h2 class="section-title">What's New?</h2>
        <div class="cards-grid">
            <?php
            try {
                $config = require __DIR__ . '/includes/supabase.php';
                $sb     = new SupabaseClient($config['url'], $config['anon_key']);
                $features = $sb->select('creatives_features');

                if (!empty($features)):
                    foreach ($features as $feature):
                        $imagePath = '';
                        if (!empty($feature['image'])) {
                            $imagePath = strpos($feature['image'], 'http') === 0
                                ? $feature['image']
                                : BASE_URL . '/' . ltrim($feature['image'], '/');
                        }
                        $dateLabel = !empty($feature['created_at'])
                            ? date('d F Y', strtotime($feature['created_at']))
                            : date('d F Y');
            ?>
            <div class="card">
                <div class="card-image">
                    <?php if ($imagePath): ?>
                        <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars($feature['title']); ?>" loading="lazy">
                    <?php else: ?>
                        <i class="fas fa-newspaper"></i>
                    <?php endif; ?>
                </div>
                <div class="card-content">
                    <div class="card-meta">
                        <span class="card-date"><?php echo $dateLabel; ?></span>
                        <span>·</span>
                        <span class="card-category">News</span>
                    </div>
                    <h3 class="card-title"><?php echo htmlspecialchars($feature['title']); ?></h3>
                    <p class="card-text"><?php echo htmlspecialchars($feature['description']); ?></p>
                    <a href="#" class="card-link">Read More <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            <?php endforeach; else: ?>
            <!-- Default cards when no data -->
            <?php foreach ([
                ['fas fa-newspaper', '07 December 2023', 'IECEP News', 'Stay updated with the latest news and announcements from IECEP-LSC.'],
                ['fas fa-star',      '05 December 2023', 'Featured Content', 'Discover featured stories and achievements from our member institutions.'],
                ['fas fa-calendar-days', '03 December 2023', 'Upcoming Events', 'Join our upcoming seminars, workshops, and networking events.'],
            ] as [$icon, $date, $title, $desc]): ?>
            <div class="card">
                <div class="card-image"><i class="<?php echo $icon; ?>"></i></div>
                <div class="card-content">
                    <div class="card-meta">
                        <span class="card-date"><?php echo $date; ?></span>
                        <span>·</span>
                        <span class="card-category">News</span>
                    </div>
                    <h3 class="card-title"><?php echo $title; ?></h3>
                    <p class="card-text"><?php echo $desc; ?></p>
                    <a href="#" class="card-link">Read More <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            <?php endforeach; endif;
            } catch (Exception $e) { ?>
            <div class="card">
                <div class="card-image"><i class="fas fa-newspaper"></i></div>
                <div class="card-content">
                    <div class="card-meta"><span class="card-date"><?php echo date('d F Y'); ?></span><span>·</span><span class="card-category">News</span></div>
                    <h3 class="card-title">IECEP News</h3>
                    <p class="card-text">Stay updated with the latest news and announcements from IECEP-LSC.</p>
                    <a href="#" class="card-link">Read More <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════ How to Affiliate -->
<section id="how-to-affiliate" class="section how-to-affiliate">
    <div class="container">
        <h2 class="section-title">How to get Affiliated?</h2>
        <p class="section-subtitle">Simple Step Process to Bring Your Institution into IECEP-LSC</p>
        <div class="steps-grid">
            <?php foreach ([
                ['1', 'Email Verification',  'Enter your institution email address and receive a secure 6-digit verification code.'],
                ['2', 'Submit Requirements', 'Upload the 6 required documents including Letter of Intent, Endorsement Letter, and Member Directory.'],
                ['3', 'Committee Approval',  'The Registration Committee reviews your application and documents for approval.'],
                ['4', 'Access Portal',       'Approved school officers receive login credentials to manage members and track compliance.'],
            ] as [$num, $heading, $desc]): ?>
            <div class="step-card">
                <div class="step-number"><?php echo $num; ?></div>
                <h3><?php echo $heading; ?></h3>
                <p><?php echo $desc; ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════ Contact -->
<section class="contact">
    <div class="contact-container">
        <div class="contact-content">
            <h2>Get In Touch</h2>
            <p>Have questions about affiliation or need assistance? Reach out to our team and we'll get back to you as soon as possible.</p>
            <button type="button" class="btn btn-primary" id="ctaAffiliateBtn">
                <i class="fas fa-arrow-right"></i> Start Affiliation Now
            </button>
        </div>
        <div class="contact-form">
            <h3 style="margin-bottom: var(--space-4); color: var(--primary);">
                <i class="fas fa-envelope"></i> Contact Us
            </h3>
            <?php if ($contactSuccess): ?>
                <div class="alert alert-success"><i class="fas fa-circle-check"></i> Message sent successfully!</div>
            <?php endif; ?>
            <?php if ($contactError): ?>
                <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> Failed to send message. Please try again.</div>
            <?php endif; ?>
            <form method="POST" action="/contact-submit.php">
                <div class="form-group">
                    <label for="contact-name" class="form-label">Your Name</label>
                    <input type="text" id="contact-name" name="name" class="form-input" placeholder="Enter your name" required>
                </div>
                <div class="form-group">
                    <label for="contact-email" class="form-label">Your Email</label>
                    <input type="email" id="contact-email" name="email" class="form-input" placeholder="Enter your email" required>
                </div>
                <div class="form-group">
                    <label for="contact-message" class="form-label">Your Message</label>
                    <textarea id="contact-message" name="message" class="form-textarea" placeholder="Enter your message" required></textarea>
                </div>
                <button type="submit" class="form-submit">
                    <i class="fas fa-paper-plane"></i> Send Message
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
    background: rgba(11, 29, 74, 0.8);
    backdrop-filter: blur(8px);
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
    .modal-title { font-size: 1.25rem; margin: 1.5rem 1.5rem 1rem; }
    .modal-close { top: 1rem; right: 1rem; width: 36px; height: 36px; }
    .step-indicator { padding: 0 1.5rem 1.5rem; gap: 1rem; }
    .step-indicator-number { width: 40px; height: 40px; font-size: 1rem; }
    .step-indicator-item span { font-size: 0.75rem; }
    .modal-section { margin: 0 1.5rem 1.5rem; padding: 1.5rem; }
    .code-input { width: 40px; height: 40px; font-size: 1rem; }
    .btn { padding: 0.75rem 1.5rem; font-size: 0.95rem; min-width: 180px; }
}
@media (max-width: 480px) {
    #affiliateModal .modal-content { width: 98%; margin: auto; }
    .modal-section { margin: 0 1rem 1rem; padding: 1rem; }
    .code-input { width: 32px; height: 32px; font-size: 0.875rem; }
}
</style>

<!-- ═══════════════════════════════════════════════════════════ Affiliate Modal -->
<div id="affiliateModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="modal-content">
        <form id="affiliationForm" action="/IECEP-LSC-MEMSYS/public/api/submit-affiliation.php" method="POST" enctype="multipart/form-data">
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

                    <div class="document-requirements">
                        <h5><i class="fas fa-file-alt" style="margin-right:0.5rem;"></i>Required Documents</h5>
                        <p><strong>Please prepare the following before proceeding:</strong></p>
                        <ul>
                            <li>Letter of Intent (PDF only)</li>
                            <li>Endorsement Letter (PDF only)</li>
                            <li>Constitution and By-Laws (PDF only)</li>
                            <li>List of Officers with CVs (PDF only)</li>
                            <li>Organizational Chart (PDF only)</li>
                            <li>Member Directory (CSV or Excel — must have a "Status" column)</li>
                        </ul>
                    </div>

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
        modalContent.style.cssText = 'width:100%;max-width:640px;background:white;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,0.3);position:relative;flex-shrink:0;';
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

    document.querySelectorAll('#affiliateNowBtn, #ctaAffiliateBtn').forEach(btn => btn?.addEventListener('click', openModal));
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
            const res    = await fetch('/IECEP-LSC-MEMSYS/index.php', {
                method: 'POST',
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
        const code  = Array.from(document.querySelectorAll('.code-input')).map(i => i.value).join('');
        const email = document.getElementById('modal-verification-email').value.trim();
        if (code.length !== 6) { showModalError('Please enter the complete 6-digit code'); return; }
        this.disabled = true;
        this.innerHTML = '<span class="spinner"></span> Verifying...';
        try {
            const res    = await fetch('/IECEP-LSC-MEMSYS/index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=verify_code&email=' + encodeURIComponent(email) + '&code=' + encodeURIComponent(code),
            });
            if (!res.ok) throw new Error(`Server error: ${res.status}`);
            const result = await res.json();
            if (result.success) {
                verifiedEmail = email;
                showModalSuccess('Email verified successfully! Proceeding to application form...');
                setTimeout(moveToStep2, 1500);
            } else {
                showModalError(result.message || 'Invalid verification code');
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
        const inputs = document.querySelectorAll('.code-input');
        inputs.forEach((input, idx) => {
            input.addEventListener('input', () => { if (input.value && idx < inputs.length - 1) inputs[idx + 1].focus(); });
            input.addEventListener('keydown', e => { if (e.key === 'Backspace' && !input.value && idx > 0) inputs[idx - 1].focus(); });
            input.addEventListener('paste', e => {
                e.preventDefault();
                e.clipboardData.getData('text').slice(0, 6).split('').forEach((d, i) => {
                    if (i < inputs.length && /^\d$/.test(d)) inputs[i].value = d;
                });
                if (e.clipboardData.getData('text').length >= inputs.length) inputs[inputs.length - 1].focus();
            });
        });
        inputs[0].focus();
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

            const response = await fetch('/IECEP-LSC-MEMSYS/public/api/simulate-payment.php', {
                method: 'POST',
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

    // Form submission - direkta mag-submit sa submit-affiliation.php
    document.getElementById('affiliationForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = document.getElementById('modal-submit-application-btn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner"></span> Submitting...';
        
        const formData = new FormData(this);
        formData.set('contact_email', verifiedEmail);
        formData.delete('action');
        
        // Direktang mag-submit sa submit-affiliation.php
        this.action = '/IECEP-LSC-MEMSYS/public/api/submit-affiliation.php';
        this.method = 'POST';
        this.enctype = 'multipart/form-data';
        this.submit();
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
</script>
</body>
</html>