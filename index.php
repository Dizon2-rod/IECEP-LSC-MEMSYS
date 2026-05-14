<?php
// Add comprehensive browser extension bypass headers
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

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/includes/paths.php';

// Handle AJAX requests FIRST - before config.php enables display_errors
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/error.log');
    ob_start();
    
    // Register shutdown function to catch fatal errors
    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR])) {
            error_log("FATAL ERROR: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Server fatal error: ' . $error['message']]);
        }
    });
    
    // Add comprehensive browser extension bypass headers for AJAX requests
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    require_once __DIR__ . '/src/lib/SupabaseClient.php';

    $action = $_POST['action'];

    if ($action === 'send_code') {
        // Check if critical extensions are available
        $criticalExtensions = ['curl', 'json', 'openssl', 'mbstring'];
        $missingCritical = [];
        foreach ($criticalExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $missingCritical[] = $ext;
            }
        }
        
        if (!empty($missingCritical)) {
            error_log("Missing critical PHP extensions: " . implode(', ', $missingCritical));
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Server configuration error: Missing PHP extensions: ' . implode(', ', $missingCritical) . '. Please contact your server administrator.']);
            exit;
        }
        
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                // Check if email already exists in pending_affiliations
                $supabaseConfig = require __DIR__ . '/includes/supabase.php';
                $supabaseClient = new SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);
                
                $existingEmail = $supabaseClient->select('pending_affiliations', ['email' => 'eq.' . $email]);
                if (!empty($existingEmail) && is_array($existingEmail)) {
                    $existingApp = $existingEmail[0];
                    $status = $existingApp['status'] ?? '';
                    
                    if ($status === 'approved') {
                        ob_end_clean();
                        echo json_encode(['success' => false, 'message' => 'This email is already registered with an approved affiliation. Please use a different email or contact support.']);
                        exit;
                    } else {
                        // Email exists but not approved - provide the resubmit option
                        ob_end_clean();
                        echo json_encode(['success' => false, 'message' => 'You already have a pending affiliation application with this email. Please contact support for assistance.', 'resubmit_available' => true]);
                        exit;
                    }
                }
                
                // Generate 6-digit code
                $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                $_SESSION['verification_code'] = $code;
                $_SESSION['verification_email'] = $email;
                $_SESSION['code_sent_time'] = time();

                // Try Supabase storage
                $emailSent = false;
                try {
                    $supabaseConfig = require __DIR__ . '/includes/supabase.php';
                    $supabaseClient = new SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);
                    $supabaseClient->insert('verification_codes', [
                        'email' => $email,
                        'code' => $code,
                        'expires_at' => date('Y-m-d H:i:s', time() + 600),
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                    ]);
                } catch (Exception $e) {
                    error_log("Supabase store fallback: " . $e->getMessage());
                }

                // Try email send
                $emailError = null;
                try {
                    error_log("Step 1: Including config.php...");
                    require_once __DIR__ . '/includes/config.php';
                    error_log("Step 2: Config loaded. APP_ENV=" . (defined('APP_ENV') ? APP_ENV : 'NOT DEFINED'));
                    
                    error_log("Step 3: Including EmailService.php...");
                    require_once __DIR__ . '/src/lib/EmailService.php';
                    error_log("Step 4: EmailService.php loaded.");
                    
                    error_log("Step 5: Creating EmailService instance...");
                    $emailService = new \App\Lib\EmailService();
                    error_log("Step 6: EmailService created.");
                    
                    error_log("Step 7: Sending verification code...");
                    $emailSent = $emailService->sendVerificationCode($email, $code);
                    error_log("Step 8: Email sent result: " . ($emailSent ? 'SUCCESS' : 'FAILED'));
                } catch (Exception $e) {
                    $emailError = $e->getMessage();
                    error_log("Email send error at step: " . $emailError);
                    error_log("Stack trace: " . $e->getTraceAsString());
                }

                $response = ['success' => true, 'message' => 'Verification code sent to your email!'];
                if (!$emailSent) {
                    $response['code'] = $code;
                    if ($emailError) {
                        $response['message'] = 'Verification code generated. Email failed: ' . $emailError;
                    } else {
                        $response['message'] = 'Verification code generated (email delivery pending - code shown for testing)';
                    }
                }
                ob_end_clean();
                echo json_encode($response);
            } catch (Exception $e) {
                error_log("send_code error: " . $e->getMessage());
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
            }
        } else {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
        }
        exit;
    }

    if ($action === 'verify_code') {
        // Check if critical extensions are available
        $criticalExtensions = ['curl', 'json'];
        $missingCritical = [];
        foreach ($criticalExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $missingCritical[] = $ext;
            }
        }
        
        if (!empty($missingCritical)) {
            error_log("Missing critical PHP extensions: " . implode(', ', $missingCritical));
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Server configuration error: Missing critical PHP extensions. Please contact your server administrator.']);
            exit;
        }
        
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $code = $_POST['code'] ?? '';
        $verified = false;

        // Check session
        if (isset($_SESSION['verification_code']) &&
            isset($_SESSION['verification_email']) &&
            time() - $_SESSION['code_sent_time'] < 600) {
            if ($_SESSION['verification_code'] === $code && $_SESSION['verification_email'] === $email) {
                unset($_SESSION['verification_code']);
                $verified = true;
            }
        }

        // Fallback: check Supabase
        if (!$verified) {
            try {
                $supabaseConfig = require __DIR__ . '/includes/supabase.php';
                $supabaseClient = new SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);
                $result = $supabaseClient->select('verification_codes', [
                    'email' => 'eq.' . $email,
                    'code' => 'eq.' . $code,
                    'expires_at' => 'gte.' . date('Y-m-d H:i:s'),
                    'used_at' => 'is.null'
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

        if ($verified) {
            $_SESSION['verified_email'] = $email;
            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'Email verified successfully.']);
        } else {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code.']);
        }
        exit;
    }

    if ($action === 'submit_affiliation') {
        // Browser extension bypass headers
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Check if critical extensions are available
        $criticalExtensions = ['curl', 'json'];
        $missingCritical = [];
        foreach ($criticalExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $missingCritical[] = $ext;
            }
        }
        
        if (!empty($missingCritical)) {
            error_log("Missing critical PHP extensions: " . implode(', ', $missingCritical));
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Server configuration error: Missing critical PHP extensions. Please contact your server administrator.']);
            exit;
        }
        
        try {
            require_once __DIR__ . '/includes/config.php';
            $supabaseConfig = require __DIR__ . '/includes/supabase.php';
            $supabaseClient = new SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);

            $contactEmail = filter_var($_POST['contact_email'] ?? '', FILTER_SANITIZE_EMAIL);
            $institutionName = trim($_POST['institution_name'] ?? '');
            $institutionAddress = trim($_POST['institution_address'] ?? '');
            $contactName = trim($_POST['contact_name'] ?? '');
            $contactPosition = trim($_POST['contact_position'] ?? '');
            $contactPhone = trim($_POST['contact_phone'] ?? '');

            // Validate required fields
            if (empty($contactEmail) || empty($institutionName) || empty($institutionAddress) || empty($contactName) || empty($contactPosition) || empty($contactPhone)) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'All fields are required.']);
                exit;
            }

            if (!preg_match('/^09\d{9}$/', $contactPhone)) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Phone number must be 11 digits starting with 09.']);
                exit;
            }

            // Redirect to proper API endpoint for complete processing
            ob_end_clean();
            echo json_encode(['success' => true, 'redirect' => '/IECEP-LSC-MEMSYS/public/api/submit-affiliation.php', 'message' => 'Processing application...']);
            exit;

            // Check if email was verified
            $emailVerified = isset($_SESSION['verified_email']) && $_SESSION['verified_email'] === $contactEmail;
            if (!$emailVerified) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'Email not verified. Please verify your email first.']);
                exit;
            }

            // Handle file uploads - store as base64 in documents JSONB column
            $documents = [];
            $allowedTypes = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            foreach ($_FILES as $fieldName => $file) {
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedTypes)) {
                        ob_end_clean();
                        echo json_encode(['success' => false, 'message' => "Invalid file type for {$file['name']}. Allowed: " . implode(', ', $allowedTypes)]);
                        exit;
                    }
                    if ($file['size'] > $maxSize) {
                        ob_end_clean();
                        echo json_encode(['success' => false, 'message' => "File {$file['name']} exceeds 5MB limit."]);
                        exit;
                    }
                    $fileContent = base64_encode(file_get_contents($file['tmp_name']));
                    $documents[$fieldName] = [
                        'name' => $file['name'],
                        'type' => $file['type'],
                        'content' => $fileContent
                    ];
                }
            }

            // Insert into Supabase - column names match the table schema
            $affiliationData = [
                'email' => $contactEmail,
                'institution_name' => $institutionName,
                'address' => $institutionAddress,
                'contact_person' => $contactName,
                'contact_position' => $contactPosition,
                'contact_phone' => $contactPhone,
                'documents' => json_encode($documents),
                'status' => 'pending_review'
            ];

            $result = $supabaseClient->insert('pending_affiliations', $affiliationData);

            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'Affiliation application submitted successfully! Your application will be reviewed by the Registration Committee.']);
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

// Now load config for page rendering
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/supabase.php';

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Check for contact form submission messages
$contactSuccess = isset($_GET['contact']) && $_GET['contact'] === 'success';
$contactError = isset($_GET['contact']) && $_GET['contact'] === 'error';

// Redirect to dashboard if already logged in (except for apply.php for resubmission)
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Allow access to apply.php for resubmission
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    error_log("Redirect check - currentPath: $currentPath, looking for apply.php");
    if (strpos($currentPath, 'apply.php') !== false) {
        error_log("Allowing access to apply.php - skipping redirect");
        // Don't redirect, allow access to apply.php
    } else {
        $role = $_SESSION['user']['role'] ?? '';
        $redirectMap = [
            'eb_president' => PORTAL_URL . '/super-admin/dashboard.php',
            'admin' => PORTAL_URL . '/admin/dashboard.php',
            'school_officer' => PORTAL_URL . '/school-officer/dashboard.php',
            'member' => PORTAL_URL . '/member/dashboard.php',
            'eb_pro_1' => PORTAL_URL . '/creatives/dashboard.php',
            'committee_creatives' => PORTAL_URL . '/creatives/dashboard.php',
            'eb_pro_2' => PORTAL_URL . '/logistics/dashboard.php',
            'eb_treasurer' => PORTAL_URL . '/treasurer/dashboard.php',
            'eb_auditor' => PORTAL_URL . '/auditor/dashboard.php',
            'eb_secretary_general' => PORTAL_URL . '/secretary/dashboard.php',
        ];

        $redirectUrl = $redirectMap[$role] ?? PORTAL_URL . '/member/dashboard.php';
        error_log("Redirecting to: $redirectUrl");
        header('Location: ' . $redirectUrl);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>IECEP-LSC MEMSYS | Membership & Affiliation Management System</title>
    <?php include __DIR__ . '/includes/head-meta.php'; ?>
    <style>
        /* Page-specific responsive styles */
        @media (max-width: 575.98px) {
            /* Modal mobile optimizations */
            .modal-content {
                margin: 1rem;
                max-width: calc(100% - 2rem);
                max-height: 90vh;
                border-radius: var(--radius-lg);
            }
            
            /* Override for affiliate modal to maintain centering */
            #affiliateModal .modal-content {
                margin: 0 !important;
                max-width: 95% !important;
            }
            
            .modal-title {
                font-size: 1.5rem;
                margin-bottom: 1.5rem;
            }
            
            .step-indicator {
                flex-direction: column;
                gap: 0.5rem;
                margin-bottom: 1.5rem;
            }
            
            .step-indicator-item {
                width: 100%;
                justify-content: center;
            }
            
            .step-indicator-line {
                width: 2px;
                height: 20px;
                transform: rotate(90deg);
            }
            
            /* Form mobile optimizations */
            .form-input, .form-textarea {
                font-size: 16px; /* Prevent zoom on iOS */
            }
            
            /* Verification inputs mobile */
            .verification-inputs {
                gap: 0.5rem;
            }
            
            .verification-inputs input {
                width: 40px;
                height: 45px;
                font-size: 1.25rem;
            }
            
            /* Contact form mobile */
            .contact-container {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
        }
        
        @media (min-width: 576px) and (max-width: 767.98px) {
            .modal-content {
                margin: 2rem;
                max-width: calc(100% - 4rem);
            }
            
            /* Override for affiliate modal to maintain centering */
            #affiliateModal .modal-content {
                margin: 0 !important;
                max-width: 90% !important;
            }
            
            .verification-inputs input {
                width: 45px;
                height: 50px;
            }
        }
        
        /* Touch-friendly buttons */
        @media (hover: none) and (pointer: coarse) {
            .btn, button, input[type="submit"], input[type="button"] {
                min-height: 48px;
                min-width: 48px;
            }
        }
    </style>
</head>
<body>

<!-- Header -->
<?php include __DIR__ . '/includes/navbar.php'; ?>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-bg"></div>
    <div class="hero-overlay"></div>
    <div class="hero-pattern"></div>
    <div class="hero-content">
        <h2 class="hero-tagline">One LSC. One IECEP.</h2>
        <h1 class="hero-title" style="color: white;">Institute of Electronics Engineers <br>of the Philippines<br>Laguna Student Chapter</h1>
        <div class="hero-buttons">
            <button type="button" id="affiliateNowBtn" class="btn btn-primary">
                <i></i> Affiliate Now
            </button>
            <a href="#how-to-affiliate" class="btn btn-outline">
                How to Get Affiliated
            </a>
        </div>
    </div>
    
    <!-- Schools inside Hero -->
    <div class="hero-schools">
        <div class="schools-grid">
            <img src="<?php echo ASSETS_URL; ?>/icons/LETRAN.png" alt="Colegio de San Juan de Letrán" loading="lazy">
            <img src="<?php echo ASSETS_URL; ?>/icons/LSPU-SCC.png" alt="Laguna State Polytechnic University - Santa Cruz Campus" loading="lazy">
            <img src="<?php echo ASSETS_URL; ?>/icons/LSPU-SPCC.png" alt="Laguna State Polytechnic University - San Pablo City Campus" loading="lazy">
            <img src="<?php echo ASSETS_URL; ?>/icons/MMCL.webp" alt="Malayan Colleges Laguna" loading="lazy">
            <img src="<?php echo ASSETS_URL; ?>/icons/PUP-STA ROSA.png" alt="Polytechnic University of the Philippines - Santa Rosa Campus" loading="lazy">
            <img src="<?php echo ASSETS_URL; ?>/icons/UC-PNC.png" alt="Pamantasan ng Cabuyao" loading="lazy">
            <img src="<?php echo ASSETS_URL; ?>/icons/UPHSD.png" alt="University of Perpetual Help System - DALTA" loading="lazy">
            <img src="<?php echo ASSETS_URL; ?>/icons/UPHSL-BINAN.png" alt="University of Perpetual Help System Laguna - Biñán Campus" loading="lazy">
        </div>
    </div>
</section>

<!-- What's New Section -->
<section id="features" class="section whats-new">
    <div class="container">
        <h2 class="section-title">What's New?</h2>
        <div class="cards-grid">
            <?php
            require_once __DIR__ . '/src/lib/SupabaseClient.php';
            $config = require __DIR__ . '/includes/supabase.php';
            $supabaseClient = new SupabaseClient($config['url'], $config['anon_key']);
            
            try {
                $features = $supabaseClient->select('creatives_features');
                if (!empty($features)):
                    foreach ($features as $feature):
            ?>
            <div class="card">
                <div class="card-image">
                    <?php if (!empty($feature['image'])): 
                        $imagePath = strpos($feature['image'], 'http') === 0 ? $feature['image'] : BASE_URL . '/' . ltrim($feature['image'], '/');
                    ?>
                        <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="<?php echo htmlspecialchars($feature['title']); ?>" loading="lazy">
                    <?php else: ?>
                        <i class="fas fa-newspaper"></i>
                    <?php endif; ?>
                </div>
                <div class="card-content">
                    <div class="card-meta">
                        <span class="card-date"><?php echo !empty($feature['created_at']) ? date('d F Y', strtotime($feature['created_at'])) : date('d F Y'); ?></span>
                        <span>·</span>
                        <span class="card-category">News</span>
                    </div>
                    <h3 class="card-title"><?php echo htmlspecialchars($feature['title']); ?></h3>
                    <p class="card-text"><?php echo htmlspecialchars($feature['description']); ?></p>
                    <a href="#" class="card-link">Read More <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            <?php 
                    endforeach;
                else:
            ?>
            <div class="card">
                <div class="card-image">
                    <i class="fas fa-newspaper"></i>
                </div>
                <div class="card-content">
                    <div class="card-meta">
                        <span class="card-date">07 December 2023</span>
                        <span>·</span>
                        <span class="card-category">News</span>
                    </div>
                    <h3 class="card-title">IECEP News</h3>
                    <p class="card-text">Stay updated with the latest news and announcements from IECEP-LSC.</p>
                    <a href="#" class="card-link">Read More <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            <div class="card">
                <div class="card-image">
                        <i class="fas fa-star"></i>
                </div>
                <div class="card-content">
                    <div class="card-meta">
                        <span class="card-date">05 December 2023</span>
                        <span>·</span>
                        <span class="card-category">News</span>
                    </div>
                    <h3 class="card-title">Featured Content</h3>
                    <p class="card-text">Discover featured stories and achievements from our member institutions.</p>
                    <a href="#" class="card-link">Read More <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            <div class="card">
                <div class="card-image">
                        <i class="fas fa-calendar-days"></i>
                </div>
                <div class="card-content">
                    <div class="card-meta">
                        <span class="card-date">03 December 2023</span>
                        <span>·</span>
                        <span class="card-category">News</span>
                    </div>
                    <h3 class="card-title">Upcoming Events</h3>
                    <p class="card-text">Join our upcoming seminars, workshops, and networking events.</p>
                    <a href="#" class="card-link">Read More <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            <div class="card">
                <div class="card-image">
                        <i class="fas fa-star"></i>
                </div>
                <div class="card-content">
                    <div class="card-meta">
                        <span class="card-date">05 December 2023</span>
                        <span>·</span>
                        <span class="card-category">News</span>
                    </div>
                    <h3 class="card-title">Featured Content</h3>
                    <p class="card-text">Discover featured stories and achievements from our member institutions.</p>
                    <a href="#" class="card-link">Read More <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            <div class="card">
                <div class="card-image">
                        <i class="fas fa-calendar-days"></i>
                </div>
                <div class="card-content">
                    <div class="card-meta">
                        <span class="card-date">03 December 2023</span>
                        <span>·</span>
                        <span class="card-category">News</span>
                    </div>
                    <h3 class="card-title">Upcoming Events</h3>
                    <p class="card-text">Join our upcoming seminars, workshops, and networking events.</p>
                    <a href="#" class="card-link">Read More <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            <?php 
                endif;
            } catch (Exception $e) {
            ?>
            <div class="card">
                <div class="card-image">
                    <i class="fas fa-newspaper"></i>
                </div>
                <div class="card-content">
                    <div class="card-meta">
                        <span class="card-date">07 December 2023</span>
                        <span>·</span>
                        <span class="card-category">News</span>
                    </div>
                    <h3 class="card-title">IECEP News</h3>
                    <p class="card-text">Stay updated with the latest news and announcements from IECEP-LSC.</p>
                    <a href="#" class="card-link">Read More <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            <div class="card">
                <div class="card-image">
                    <i class="fas fa-star"></i>
                </div>
                <div class="card-content">
                    <div class="card-meta">
                        <span class="card-date">05 December 2023</span>
                        <span>·</span>
                        <span class="card-category">News</span>
                    </div>
                    <h3 class="card-title">Featured Content</h3>
                    <p class="card-text">Discover featured stories and achievements from our member institutions.</p>
                    <a href="#" class="card-link">Read More <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            <div class="card">
                <div class="card-image">
                    <i class="fas fa-calendar-days"></i>
                </div>
                <div class="card-content">
                    <div class="card-meta">
                        <span class="card-date">03 December 2023</span>
                        <span>·</span>
                        <span class="card-category">News</span>
                    </div>
                    <h3 class="card-title">Upcoming Events</h3>
                    <p class="card-text">Join our upcoming seminars, workshops, and networking events.</p>
                    <a href="#" class="card-link">Read More <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</section>

<!-- How to Affiliate Section -->
<section id="how-to-affiliate" class="section how-to-affiliate">
    <div class="container">
        <h2 class="section-title">How to get Affiliated?</h2>
        <p class="section-subtitle">Simple Step Process to Bring Your Institution into IECEP-LSC</p>
        <div class="steps-grid">
            <div class="step-card">
                <div class="step-number">1</div>
                <h3>Email verification</h3>
                <p>Enter your institution email address and receive a secure 6-digit verification code.</p>
            </div>
            <div class="step-card">
                <div class="step-number">2</div>
                <h3>Submit requirements</h3>
                <p>Upload the 6 required documents including Letter of Intent, Endorsement Letter, and Member Directory.</p>
            </div>
            <div class="step-card">
                <div class="step-number">3</div>
                <h3>Committee approval</h3>
                <p>The Registration Committee reviews your application and documents for approval.</p>
            </div>
            <div class="step-card">
                <div class="step-number">4</div>
                <h3>Access portal</h3>
                <p>Approved school officers receive login credentials to manage members and track compliance.</p>
            </div>
        </div>
    </div>
</section>

<!-- Contact Section -->
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
            <div class="alert alert-success">
                <i class="fas fa-circle-check"></i> Message sent successfully!
            </div>
            <?php endif; ?>
            <?php if ($contactError): ?>
            <div class="alert alert-error">
                <i class="fas fa-circle-exclamation"></i> Failed to send message. Please try again.
            </div>
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

<!-- Footer -->
<?php include __DIR__ . '/includes/footer-new.php'; ?>

<!-- Enhanced Modal Styles -->
<style>
/* Enhanced Modal Design */
#affiliateModal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(11, 29, 74, 0.8);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
    overflow: auto;
}

#affiliateModal.active {
    opacity: 1;
    visibility: visible;
}

#affiliateModal .modal-content {
    background: white;
    border-radius: 16px;
    width: min(640px, 100%);
    max-height: calc(100vh - 3rem);
    overflow-y: auto;
    position: relative;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    transform: scale(0.9);
    transition: transform 0.3s ease;
    /* Remove margin: auto to prevent conflict with flex centering */
    margin: 0;
}

#affiliateModal.active .modal-content {
    transform: scale(1);
}

/* Step Visibility Control - CRITICAL */
#affiliateModal #modal-email-verification-step,
#affiliateModal #modal-application-form-step {
    display: none !important;
}

#affiliateModal #modal-email-verification-step {
    display: block !important;
}

#affiliateModal #modal-application-form-step.active,
#affiliateModal #modal-application-form-step[style*="block"],
#affiliateModal #modal-application-form-step[style*="display: block"] {
    display: block !important;
}

/* Hide email step when application form is active */
#affiliateModal:has(#modal-application-form-step.active) #modal-email-verification-step,
#affiliateModal:has(#modal-application-form-step[style*="block"]) #modal-email-verification-step {
    display: none !important;
}

/* Modal Header */
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
    top: 1.5rem;
    right: 1.5rem;
    background: #f8fafc;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s ease;
    z-index: 10;
}

.modal-close:hover {
    background: #e2e8f0;
    color: #0B1D4A;
    transform: scale(1.1);
}

/* Enhanced Step Indicator */
.step-indicator {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 2rem 2rem;
    gap: 2rem;
}

.step-indicator-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    position: relative;
}

.step-indicator-number {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 1.1rem;
    transition: all 0.3s ease;
    border: 3px solid transparent;
}

.step-indicator-item.active .step-indicator-number {
    background: #C49A00;
    color: #0B1D4A;
    border-color: #C49A00;
    box-shadow: 0 4px 12px rgba(196, 154, 0, 0.3);
}

.step-indicator-item.completed .step-indicator-number {
    background: #0B1D4A;
    color: white;
    border-color: #0B1D4A;
}

.step-indicator-item span {
    font-size: 0.875rem;
    font-weight: 500;
    color: #64748b;
    text-align: center;
}

.step-indicator-item.active span {
    color: #0B1D4A;
    font-weight: 600;
}

.step-indicator-item.completed span {
    color: #0B1D4A;
}

.step-indicator-line {
    flex: 1;
    height: 2px;
    background: #e2e8f0;
    max-width: 100px;
}

/* Enhanced Form Sections */
.modal-section {
    background: #f8fafc;
    padding: 2rem;
    border-radius: 12px;
    margin: 0 2rem 2rem;
    border: 1px solid #e2e8f0;
}

.modal-section h4 {
    font-family: 'Inter', sans-serif;
    font-size: 1.25rem;
    font-weight: 600;
    color: #0B1D4A;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-section h5 {
    font-family: 'Inter', sans-serif;
    font-size: 1.1rem;
    font-weight: 600;
    color: #0B1D4A;
    margin-bottom: 1rem;
}

/* Enhanced Form Fields */
.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-family: 'Inter', sans-serif;
    font-weight: 600;
    color: #374151;
    font-size: 0.95rem;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 0.875rem 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-family: 'Inter', sans-serif;
    font-size: 1rem;
    transition: all 0.2s ease;
    background: white;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #C49A00;
    box-shadow: 0 0 0 3px rgba(196, 154, 0, 0.1);
}

.form-group input[readonly] {
    background: #f8fafc;
    color: #64748b;
}

.form-group small {
    display: block;
    margin-top: 0.5rem;
    color: #64748b;
    font-size: 0.875rem;
}

/* Enhanced Verification Inputs */
.verification-inputs {
    display: flex;
    gap: 0.75rem;
    justify-content: center;
    margin: 2rem 0;
}

.code-input {
    width: 50px;
    height: 50px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    text-align: center;
    font-size: 1.25rem;
    font-weight: 600;
    font-family: 'Inter', sans-serif;
    transition: all 0.2s ease;
}

.code-input:focus {
    outline: none;
    border-color: #C49A00;
    box-shadow: 0 0 0 3px rgba(196, 154, 0, 0.1);
    transform: scale(1.05);
}

/* Enhanced File Upload */
.file-upload-wrapper {
    position: relative;
    overflow: hidden;
    min-height: 60px;
}

.file-upload-wrapper input[type="file"] {
    position: absolute;
    top: 0;
    left: 0;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
    z-index: 10;
}

.file-upload-label {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    border: 2px dashed #C49A00;
    border-radius: 8px;
    background: #fff8f0;
    color: #0B1D4A;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    min-height: 60px;
    position: relative;
    z-index: 1;
    pointer-events: none;
}

.file-upload-wrapper:hover .file-upload-label {
    background: #fef3e2;
    border-color: #0B1D4A;
}

/* Enhanced Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.875rem 2rem;
    border-radius: 50px;
    font-family: 'Inter', sans-serif;
    font-weight: 600;
    font-size: 1rem;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    min-width: 200px;
    position: relative;
    overflow: hidden;
}

.btn-primary {
    background: linear-gradient(135deg, #C49A00 0%, #D4AF37 100%);
    color: #0B1D4A;
    box-shadow: 0 4px 12px rgba(196, 154, 0, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(196, 154, 0, 0.4);
}

.btn-primary:active {
    transform: translateY(0);
}

.btn-outline {
    background: transparent;
    color: #0B1D4A;
    border: 2px solid #0B1D4A;
}

.btn-outline:hover {
    background: #0B1D4A;
    color: white;
    transform: translateY(-2px);
}

/* Enhanced Alerts */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin: 1rem 0;
    font-family: 'Inter', sans-serif;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-success {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    color: #166534;
    border: 1px solid #bbf7d0;
}

.alert-error {
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    color: #dc2626;
    border: 1px solid #fecaca;
}

.alert-info {
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    color: #0284c7;
    border: 1px solid #bae6fd;
}

/* Loading States */
.spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid transparent;
    border-top: 2px solid currentColor;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Success Notification Modal */
#successNotificationModal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease-out;
}

#successNotificationModal.active {
    display: flex;
}

#successNotificationModal .modal-content {
    animation: slideUp 0.4s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from { 
        opacity: 0;
        transform: translateY(20px);
    }
    to { 
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    #affiliateModal .modal-content {
        width: 95%;
        max-height: 95vh;
        margin: 0; /* Override general modal-content margin */
    }
    
    .modal-title {
        font-size: 1.25rem;
        margin: 1.5rem 1.5rem 1rem;
    }
    
    .modal-close {
        top: 1rem;
        right: 1rem;
        width: 36px;
        height: 36px;
    }
    
    .step-indicator {
        padding: 0 1.5rem 1.5rem;
        gap: 1rem;
    }
    
    .step-indicator-number {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
    
    .step-indicator-item span {
        font-size: 0.75rem;
    }
    
    .modal-section {
        margin: 0 1.5rem 1.5rem;
        padding: 1.5rem;
    }
    
    .verification-inputs {
        gap: 0.5rem;
    }
    
    .code-input {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
    
    .btn {
        padding: 0.75rem 1.5rem;
        font-size: 0.95rem;
        min-width: 180px;
    }
}

@media (max-width: 480px) {
    #affiliateModal .modal-content {
        width: 98%;
        margin: 0; /* Override general modal-content margin */
    }
    
    .modal-section {
        margin: 0 1rem 1rem;
        padding: 1rem;
    }
    
    .verification-inputs {
        gap: 0.25rem;
    }
    
    .code-input {
        width: 32px;
        height: 32px;
        font-size: 0.875rem;
    }
}

/* Focus Management */
#affiliateModal.active {
    pointer-events: auto;
}

#affiliateModal:not(.active) {
    pointer-events: none;
}

/* Enhanced Document Requirements */
.document-requirements {
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border: 1px solid #bae6fd;
    border-radius: 12px;
    padding: 1.5rem;
    margin: 1.5rem 0;
}

.document-requirements h5 {
    color: #0284c7;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.document-requirements ul {
    margin: 0;
    padding-left: 1.5rem;
}

.document-requirements li {
    margin-bottom: 0.5rem;
    color: #0c4a6e;
}

/* Form Grid Layout */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.form-grid-full {
    grid-column: 1 / -1;
}

/* Centered Actions */
.modal-actions {
    text-align: center;
    margin: 2rem 0;
}

/* Success Message Styling */
.success-message {
    text-align: center;
    padding: 1.5rem;
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border-radius: 12px;
    border: 1px solid #bbf7d0;
    margin: 1rem 0;
}

.success-message h4 {
    color: #166534;
    margin-bottom: 0.5rem;
}

.success-message p {
    color: #15803d;
    margin: 0;
}

/* In-Modal Notification System */
.modal-notification {
    padding: 1rem 1.5rem;
    border-radius: 12px;
    margin-bottom: 1rem;
    font-family: 'Inter', sans-serif;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    animation: slideDown 0.3s ease-out;
    position: relative;
    overflow: hidden;
}

.modal-notification::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
}

.modal-notification.success {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    color: #166534;
    border: 1px solid #bbf7d0;
}

.modal-notification.success::before {
    background: #10b981;
}

.modal-notification.error {
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    color: #dc2626;
    border: 1px solid #fecaca;
}

.modal-notification.error::before {
    background: #ef4444;
}

.modal-notification.info {
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    color: #0284c7;
    border: 1px solid #bae6fd;
}

.modal-notification.info::before {
    background: #3b82f6;
}

.modal-notification-icon {
    flex-shrink: 0;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-notification-content {
    flex: 1;
    font-size: 0.9rem;
    font-weight: 500;
    line-height: 1.4;
}

.modal-notification-close {
    flex-shrink: 0;
    background: none;
    border: none;
    color: inherit;
    cursor: pointer;
    padding: 0.25rem;
    opacity: 0.7;
    transition: opacity 0.2s;
}

.modal-notification-close:hover {
    opacity: 1;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeOut {
    from {
        opacity: 1;
        transform: translateY(0);
    }
    to {
        opacity: 0;
        transform: translateY(-10px);
    }
}
</style>

<!-- Modal (Affiliation Application) -->
<div id="affiliateModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="modal-content">
        <form id="affiliationForm" action="/IECEP-LSC-MEMSYS/public/api/submit-affiliation.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="submit_affiliation">
            <input type="hidden" id="form-contact-email" name="contact_email">
            
            <button type="button" class="modal-close" id="closeModalBtn" aria-label="Close modal">&times;</button>
            
            <h3 class="modal-title" id="modal-title">Affiliation Application</h3>
            
            <!-- Notification Container -->
            <div id="modalNotificationContainer" style="display: none; position: absolute; top: 80px; left: 50%; transform: translateX(-50%); z-index: 1000; width: 90%; max-width: 500px;"></div>
            
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
            </div>
            
            <!-- Step 1: Email Verification -->
            <div id="modal-email-verification-step">
            <div class="modal-section">
                <h4><i class="fas fa-envelope" style="color: #C49A00; margin-right: 0.5rem;"></i>Verify Your Email</h4>
                <p style="text-align: center; color: #64748b; margin-bottom: 2rem; font-size: 1.05rem;">
                    Enter your institution email address to receive a verification code
                </p>
                
                <div id="modal-email-form">
                    <div class="form-group" style="max-width: 400px; margin: 0 auto 2rem;">
                        <label for="modal-verification-email">Email Address <span style="color: #dc2626;">*</span></label>
                        <input type="email" id="modal-verification-email" placeholder="your.email@institution.edu" required>
                        <small>Please use your institutional email address</small>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" id="modal-send-code-btn" class="btn btn-primary">
                            Send Verification Code
                        </button>
                    </div>
                </div>
                
                <div id="modal-code-form" style="display: none;">
                    <div class="success-message">
                        <h4><i class="fas fa-check-circle" style="margin-right: 0.5rem;"></i>Verification Code Sent</h4>
                        <p>Code sent to <strong id="modal-sent-email"></strong></p>
                        <small style="color: #64748b;">Please check your inbox and spam folder</small>
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
                        <button type="button" id="modal-verify-code-btn" class="btn btn-primary">
                            Verify Code
                        </button>
                    </div>
                </div>
            </div>
            
            <div id="modal-verification-error" class="alert alert-error" style="display: none; margin: 0 2rem 2rem;"></div>
            <div id="modal-verification-success" class="alert alert-success" style="display: none; margin: 0 2rem 2rem;"></div>
        </div>
        
        <!-- Step 2: Application Form -->
        <div id="modal-application-form-step" style="display: none;">
            <div class="modal-section">
                <h4><i class="fas fa-university" style="color: #C49A00; margin-right: 0.5rem;"></i>Institution Information</h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="modal-inst-name">Institution Name <span style="color: #dc2626;">*</span></label>
                        <input type="text" id="modal-inst-name" name="institution_name" required>
                    </div>
                    <div class="form-group">
                        <label for="modal-inst-type">Institution Type <span style="color: #dc2626;">*</span></label>
                        <select id="modal-inst-type" required>
                            <option value="">-- Select --</option>
                            <option>Public University</option>
                            <option>Private University</option>
                            <option>College</option>
                            <option>Technical Institution</option>
                        </select>
                    </div>
                    <div class="form-group form-grid-full">
                        <label for="modal-inst-address">Institution Address <span style="color: #dc2626;">*</span></label>
                        <input type="text" id="modal-inst-address" name="institution_address" required>
                    </div>
                </div>
            </div>
            
            <div class="modal-section">
                <h4><i class="fas fa-user-tie" style="color: #C49A00; margin-right: 0.5rem;"></i>Contact Information</h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="modal-contact-name">Contact Person Name <span style="color: #dc2626;">*</span></label>
                        <input type="text" id="modal-contact-name" name="contact_person" required>
                    </div>
                    <div class="form-group">
                        <label for="modal-contact-position">Position <span style="color: #dc2626;">*</span></label>
                        <input type="text" id="modal-contact-position" name="contact_position" required>
                    </div>
                    <div class="form-group">
                        <label for="modal-contact-email">Email <span style="color: #dc2626;">*</span></label>
                        <input type="email" id="modal-contact-email" name="contact_email" readonly>
                    </div>
                    <div class="form-group">
                        <label for="modal-contact-phone">Phone Number <span style="color: #dc2626;">*</span></label>
                        <input type="tel" id="modal-contact-phone" name="contact_phone" placeholder="09XXXXXXXXX" pattern="09[0-9]{9}" required>
                    </div>
                </div>
                
                <div class="document-requirements">
                    <h5><i class="fas fa-file-alt" style="margin-right: 0.5rem;"></i>Required Documents</h5>
                    <p><strong>Please prepare the following documents before proceeding:</strong></p>
                    <ul>
                        <li>Letter of Intent (PDF only)</li>
                        <li>Endorsement Letter (PDF only)</li>
                        <li>Constitution and By-Laws (PDF only)</li>
                        <li>List of Officers with CVs (PDF only)</li>
                        <li>Organizational Chart (PDF only)</li>
                        <li>Member Directory (PDF, Excel, or CSV)</li>
                    </ul>
                </div>
                    
                    <div id="modal-document-upload-section" style="display: none;">
                        <div class="form-grid">
                            <div class="form-group">
                                <div class="file-upload-wrapper">
                                    <input type="file" name="letter_of_intent" accept=".pdf" required id="letter_of_intent_file">
                                    <label for="letter_of_intent_file" class="file-upload-label">
                                        <i class="fas fa-file-pdf" style="margin-right: 0.5rem;"></i>
                                        <span class="label-text">Letter of Intent <span style="color: #dc2626;">*</span></span>
                                        <span class="file-selected" style="display: none;">
                                            <i class="fas fa-check-circle" style="color: #10b981; margin-right: 0.5rem;"></i>
                                            <span class="file-name"></span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="file-upload-wrapper">
                                    <input type="file" name="endorsement_letter" accept=".pdf" required id="endorsement_letter_file">
                                    <label for="endorsement_letter_file" class="file-upload-label">
                                        <i class="fas fa-file-pdf" style="margin-right: 0.5rem;"></i>
                                        <span class="label-text">Endorsement Letter <span style="color: #dc2626;">*</span></span>
                                        <span class="file-selected" style="display: none;">
                                            <i class="fas fa-check-circle" style="color: #10b981; margin-right: 0.5rem;"></i>
                                            <span class="file-name"></span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="file-upload-wrapper">
                                    <input type="file" name="constitution_by_laws" accept=".pdf" required id="constitution_by_laws_file">
                                    <label for="constitution_by_laws_file" class="file-upload-label">
                                        <i class="fas fa-file-pdf" style="margin-right: 0.5rem;"></i>
                                        <span class="label-text">Constitution and By-Laws <span style="color: #dc2626;">*</span></span>
                                        <span class="file-selected" style="display: none;">
                                            <i class="fas fa-check-circle" style="color: #10b981; margin-right: 0.5rem;"></i>
                                            <span class="file-name"></span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="file-upload-wrapper">
                                    <input type="file" name="officers_cvs" accept=".pdf" required id="officers_cvs_file">
                                    <label for="officers_cvs_file" class="file-upload-label">
                                        <i class="fas fa-file-pdf" style="margin-right: 0.5rem;"></i>
                                        <span class="label-text">List of Officers with CVs <span style="color: #dc2626;">*</span></span>
                                        <span class="file-selected" style="display: none;">
                                            <i class="fas fa-check-circle" style="color: #10b981; margin-right: 0.5rem;"></i>
                                            <span class="file-name"></span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="file-upload-wrapper">
                                    <input type="file" name="organizational_chart" accept=".pdf" required id="organizational_chart_file">
                                    <label for="organizational_chart_file" class="file-upload-label">
                                        <i class="fas fa-file-pdf" style="margin-right: 0.5rem;"></i>
                                        <span class="label-text">Organizational Chart <span style="color: #dc2626;">*</span></span>
                                        <span class="file-selected" style="display: none;">
                                            <i class="fas fa-check-circle" style="color: #10b981; margin-right: 0.5rem;"></i>
                                            <span class="file-name"></span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="file-upload-wrapper">
                                    <input type="file" name="member_directory" accept=".pdf,.xls,.xlsx,.csv" required id="member_directory_file">
                                    <label for="member_directory_file" class="file-upload-label">
                                        <i class="fas fa-file-excel" style="margin-right: 0.5rem;"></i>
                                        <span class="label-text">Member Directory <span style="color: #dc2626;">*</span></span>
                                        <span class="file-selected" style="display: none;">
                                            <i class="fas fa-check-circle" style="color: #10b981; margin-right: 0.5rem;"></i>
                                            <span class="file-name"></span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-actions" style="margin-top: 2rem;">
                    <div class="form-group" style="text-align: center;">
                        <label style="display: flex; align-items: center; justify-content: center; gap: 0.75rem; margin-bottom: 1.5rem; cursor: pointer;">
                            <input type="checkbox" id="modal-terms-checkbox" name="terms" value="accepted" required style="width: 20px; height: 20px;">
                            <span style="font-size: 0.95rem; color: #374151;">I agree to the terms and conditions and certify that all information provided is accurate</span>
                        </label>
                    </div>
                    <button type="submit" id="modal-submit-application-btn" class="btn btn-primary">
                        <i class="fas fa-paper-plane" style="margin-right: 0.5rem;"></i>
                        Submit Application
                    </button>
                </div>
            </div>
        </div>
        </form>
    </div>
</div>

<!-- Success Notification Modal -->
<div id="successNotificationModal" class="modal" style="display: none;">
    <div class="modal-overlay" onclick="closeSuccessNotification()"></div>
    <div class="modal-content" style="max-width: 450px; padding: 2rem;">
        <div style="text-align: center;">
            <div style="width: 60px; height: 60px; margin: 0 auto 1.5rem; background: linear-gradient(135deg, #10b981, #059669); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-check" style="color: white; font-size: 1.5rem;"></i>
            </div>
            <h3 style="color: #0f172a; margin-bottom: 1rem; font-size: 1.25rem;">Application Submitted Successfully!</h3>
            <p style="color: #64748b; margin-bottom: 1.5rem; line-height: 1.6;">
                Your affiliation application has been submitted and is now visible to the Registration Committee for review.
            </p>
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem;">
                <p style="color: #166534; font-size: 0.9rem; margin: 0;">
                    <i class="fas fa-info-circle" style="margin-right: 0.5rem;"></i>
                    <strong>Next Steps:</strong> The Registration Committee will review your application within 3-5 business days. You will receive an email notification once a decision has been made.
                </p>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: center;">
                <button type="button" onclick="closeSuccessNotification()" class="btn btn-primary" style="padding: 0.75rem 1.5rem;">
                    <i class="fas fa-check" style="margin-right: 0.5rem;"></i>
                    Got it
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile Menu Toggle
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const mobileNav = document.querySelector('.mobile-nav');
    const mobileNavOverlay = document.querySelector('.mobile-nav-overlay');
    const mobileNavClose = document.querySelector('.mobile-nav-close');

    if (mobileMenuBtn && mobileNav && mobileNavOverlay) {
        mobileMenuBtn.addEventListener('click', function() {
            this.classList.toggle('active');
            mobileNav.classList.toggle('active');
            mobileNavOverlay.classList.toggle('active');
            this.setAttribute('aria-expanded', this.classList.contains('active'));
        });
    }

    if (mobileNavClose && mobileMenuBtn && mobileNav && mobileNavOverlay) {
        mobileNavClose.addEventListener('click', function() {
            mobileMenuBtn.classList.remove('active');
            mobileNav.classList.remove('active');
            mobileNavOverlay.classList.remove('active');
            mobileMenuBtn.setAttribute('aria-expanded', 'false');
        });
    }

    if (mobileNavOverlay && mobileMenuBtn && mobileNav) {
        mobileNavOverlay.addEventListener('click', function() {
            mobileMenuBtn.classList.remove('active');
            mobileNav.classList.remove('active');
            mobileNavOverlay.classList.remove('active');
            mobileMenuBtn.setAttribute('aria-expanded', 'false');
        });
    }
    
    // Login Button
    const loginBtn = document.querySelector('.btn-login');
    if (loginBtn) {
        loginBtn.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = '<?php echo BASE_URL; ?>/login.php';
        });
    }
    
    // Modal Functionality
    let verifiedEmail = '';

    const modal = document.getElementById('affiliateModal');
    const btns = document.querySelectorAll('#affiliateNowBtn, #ctaAffiliateBtn');
    const closeBtn = document.getElementById('closeModalBtn');

    function openModal() {
        if (modal) {
            modal.classList.add('active');
            resetModal();
        }
    }

    function closeModal() {
        if (modal) {
            modal.classList.remove('active');
        }
    }

    function resetModal() {
        const step1 = document.getElementById('modal-step1');
        const step2 = document.getElementById('modal-step2');
        const emailVerificationStep = document.getElementById('modal-email-verification-step');
        const applicationFormStep = document.getElementById('modal-application-form-step');
        const emailForm = document.getElementById('modal-email-form');
        const codeForm = document.getElementById('modal-code-form');
        const emailInput = document.getElementById('modal-verification-email');
        const errorDiv = document.getElementById('modal-verification-error');
        const successDiv = document.getElementById('modal-verification-success');

        if (step1) {
            step1.classList.add('active');
            step1.classList.remove('completed');
        }
        if (step2) step2.classList.remove('active', 'completed');
        
        // Reset step visibility
        if (emailVerificationStep) {
            emailVerificationStep.style.display = 'block';
            emailVerificationStep.classList.add('active');
        }
        if (applicationFormStep) {
            applicationFormStep.style.display = 'none';
            applicationFormStep.classList.remove('active');
        }
        
        if (emailForm) emailForm.style.display = 'block';
        if (codeForm) codeForm.style.display = 'none';
        if (emailInput) emailInput.value = '';
        document.querySelectorAll('.code-input').forEach(input => input.value = '');
        if (errorDiv) errorDiv.style.display = 'none';
        if (successDiv) successDiv.style.display = 'none';

        verifiedEmail = '';
    }

    btns.forEach(btn => {
        if (btn) btn.addEventListener('click', openModal);
    });

    if (closeBtn && modal) {
        closeBtn.addEventListener('click', () => modal.classList.remove('active'));
    }

    if (modal) {
        modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('active'); });
    }

    // Escape key to close modal
    if (modal) {
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                modal.classList.remove('active');
            }
        });
    }
    
    // Email Verification
    document.getElementById('modal-send-code-btn').addEventListener('click', async function() {
        const email = document.getElementById('modal-verification-email').value.trim();
        
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showNotification('error', 'Please enter a valid email address');
            return;
        }
        
        this.disabled = true;
        this.innerHTML = '<span class="spinner"></span> Sending...';
        
        try {
            const response = await fetch('/IECEP-LSC-MEMSYS/index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=send_code&email=' + encodeURIComponent(email)
            });

            // Check if response is ok
            if (!response.ok) {
                const errorText = await response.text();
                console.error('Server returned non-OK status:', response.status, errorText);
                throw new Error(`Server error: ${response.status}`);
            }

            const result = await response.json();
            
            if (result.success) {
                if (result.code) {
                    showModalSuccess(`Verification code: ${result.code} (Email not configured - use this code)`);
                } else {
                    showModalSuccess('Verification code sent to your email!');
                }
                document.getElementById('modal-sent-email').textContent = email;
                document.getElementById('modal-email-form').style.display = 'none';
                document.getElementById('modal-code-form').style.display = 'block';
                setupCodeInputs();
            } else {
                showNotification('error', result.message || 'Failed to send verification code');
                this.disabled = false;
                this.innerHTML = 'Send Verification Code';
            }
        } catch (error) {
            console.error('Fetch error:', error);
            showNotification('error', 'Network error: ' + error.message + '. Please check console for details.');
            this.disabled = false;
            this.innerHTML = 'Send Verification Code';
        }
    });

    function setupCodeInputs() {
        const inputs = document.querySelectorAll('.code-input');
        inputs.forEach((input, index) => {
            input.addEventListener('input', function(e) {
                if (e.target.value && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            });
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    inputs[index - 1].focus();
                }
            });
            input.addEventListener('paste', function(e) {
                e.preventDefault();
                const pastedData = e.clipboardData.getData('text').slice(0, 6);
                pastedData.split('').forEach((digit, i) => {
                    if (i < inputs.length && /^\d$/.test(digit)) {
                        inputs[i].value = digit;
                    }
                });
                if (pastedData.length >= inputs.length) {
                    inputs[inputs.length - 1].focus();
                }
            });
        });
        inputs[0].focus();
    }
    
    document.getElementById('modal-verify-code-btn').addEventListener('click', async function() {
        const code = Array.from(document.querySelectorAll('.code-input')).map(input => input.value).join('');
        
        if (code.length !== 6) {
            showModalError('Please enter the complete 6-digit code');
            return;
        }
        
        const email = document.getElementById('modal-verification-email').value.trim();
        
        this.disabled = true;
        this.innerHTML = '<span class="spinner"></span> Verifying...';
        
        try {
            const response = await fetch('/IECEP-LSC-MEMSYS/index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=verify_code&email=' + encodeURIComponent(email) + '&code=' + encodeURIComponent(code)
            });

            // Check if response is ok
            if (!response.ok) {
                const errorText = await response.text();
                console.error('Server returned non-OK status:', response.status, errorText);
                throw new Error(`Server error: ${response.status}`);
            }

            const result = await response.json();
            
            if (result.success) {
                verifiedEmail = email;
                showModalSuccess('Email verified successfully! Proceeding to application form...');
                
                setTimeout(() => {
                    moveToModalStep2();
                }, 1500);
            } else {
                showModalError(result.error || 'Invalid verification code');
                this.disabled = false;
                this.innerHTML = 'Verify Code';
            }
        } catch (error) {
            showModalError('Network error. Please try again.');
            this.disabled = false;
            this.innerHTML = 'Verify Code';
        }
    });
    
        
    function moveToModalStep2() {
        // Update step indicators
        document.getElementById('modal-step1').classList.remove('active');
        document.getElementById('modal-step1').classList.add('completed');
        document.getElementById('modal-step2').classList.add('active');
        
        // Hide email verification step and show application form step
        const emailStep = document.getElementById('modal-email-verification-step');
        const formStep = document.getElementById('modal-application-form-step');
        
        emailStep.style.display = 'none';
        emailStep.classList.remove('active');
        
        formStep.style.display = 'block';
        formStep.classList.add('active');
        
        // Set verified email and show document upload
        document.getElementById('modal-contact-email').value = verifiedEmail;
        document.getElementById('modal-document-upload-section').style.display = 'block';
        
        // Scroll to top of modal
        document.querySelector('#affiliateModal .modal-content').scrollTop = 0;
    }
    
    document.getElementById('modal-terms-checkbox').addEventListener('change', function() {
        document.getElementById('modal-submit-application-btn').disabled = !this.checked;
    });
    
    // File upload feedback
    const fileInputs = document.querySelectorAll('#modal-document-upload-section input[type="file"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const wrapper = this.closest('.file-upload-wrapper');
            const label = wrapper.querySelector('.file-upload-label');
            const labelText = label.querySelector('.label-text');
            const fileSelected = label.querySelector('.file-selected');
            const fileName = label.querySelector('.file-name');
            
            if (this.files[0]) {
                // Show selected file
                labelText.style.display = 'none';
                fileSelected.style.display = 'flex';
                fileName.textContent = this.files[0].name;
                wrapper.style.borderColor = '#10b981';
                wrapper.style.backgroundColor = '#f0fdf4';
            } else {
                // Reset to original state
                labelText.style.display = 'block';
                fileSelected.style.display = 'none';
                fileName.textContent = '';
                wrapper.style.borderColor = '';
                wrapper.style.backgroundColor = '';
            }
        });
    });
    
    // Handle form submission - force correct action to bypass browser extension
    const affiliationForm = document.getElementById('affiliationForm');
    if (affiliationForm) {
        affiliationForm.addEventListener('submit', function(e) {
            // Force the correct action URL (browser extension may have changed it)
            affiliationForm.action = '/IECEP-LSC-MEMSYS/public/api/submit-affiliation.php';
            affiliationForm.method = 'POST';
            
            // Set the verified email before submission
            document.getElementById('form-contact-email').value = verifiedEmail;
            
            // Log for debugging
            console.log('Form submitting to:', affiliationForm.action);
            
            // Allow natural form submission
        });
    }
    
    // Legacy click handler - just sets the email
    document.getElementById('modal-submit-application-btn').addEventListener('click', function() {
        // Set the verified email in the hidden field before form submission
        document.getElementById('form-contact-email').value = verifiedEmail;
    });
    
    function showModalError(message) {
        const errorEl = document.getElementById('modal-verification-error');
        const successEl = document.getElementById('modal-verification-success');
        
        errorEl.textContent = message;
        errorEl.style.display = 'block';
        successEl.style.display = 'none';
        
        setTimeout(() => {
            errorEl.style.display = 'none';
        }, 5000);
    }
    
    function showModalSuccess(message) {
        const errorEl = document.getElementById('modal-verification-error');
        const successEl = document.getElementById('modal-verification-success');
        
        successEl.textContent = message;
        successEl.style.display = 'block';
        errorEl.style.display = 'none';
        
        setTimeout(() => {
            successEl.style.display = 'none';
        }, 5000);
    }
    
    function showNotification(type, message) {
        const container = document.getElementById('modalNotificationContainer');
        if (!container) return;
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `modal-notification ${type}`;
        
        // Choose icon based on type
        let icon = '';
        switch(type) {
            case 'success':
                icon = '<i class="fas fa-check-circle"></i>';
                break;
            case 'error':
                icon = '<i class="fas fa-exclamation-circle"></i>';
                break;
            case 'info':
                icon = '<i class="fas fa-info-circle"></i>';
                break;
            default:
                icon = '<i class="fas fa-info-circle"></i>';
        }
        
        notification.innerHTML = `
            <div class="modal-notification-icon">${icon}</div>
            <div class="modal-notification-content">${message}</div>
            <button class="modal-notification-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        // Clear existing notifications
        container.innerHTML = '';
        
        // Add new notification
        container.appendChild(notification);
        container.style.display = 'block';
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.style.animation = 'fadeOut 0.3s ease-out forwards';
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.remove();
                    }
                    if (container.children.length === 0) {
                        container.style.display = 'none';
                    }
                }, 300);
            }
        }, 5000);
    }
    
    function showSuccessScreen(message) {
        const modalContent = document.querySelector('#affiliateModal .modal-content');
        if (!modalContent) return;
        
        // Replace modal content with success screen
        modalContent.innerHTML = `
            <button class="modal-close" onclick="closeModal()" aria-label="Close modal">&times;</button>
            
            <div style="text-align: center; padding: 2rem;">
                <div style="margin-bottom: 2rem;">
                    <i class="fas fa-check-circle" style="font-size: 4rem; color: #10b981;"></i>
                </div>
                
                <h2 style="color: #0B1D4A; margin-bottom: 1rem; font-size: 1.75rem;">Application Submitted!</h2>
                
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 1.5rem; margin: 1.5rem 0; text-align: left;">
                    <p style="color: #166534; margin: 0; line-height: 1.6;">${message}</p>
                    <p style="color: #166534; margin: 1rem 0 0 0; font-weight: 600;">What happens next?</p>
                    <ul style="color: #166534; margin: 0.5rem 0 0 0; padding-left: 1.5rem;">
                        <li>Registration Committee will review your application</li>
                        <li>You'll receive a decision within 3-5 business days</li>
                        <li>A confirmation email has been sent to your email address</li>
                    </ul>
                </div>
                
                <button onclick="closeModal()" class="btn btn-primary" style="margin-top: 1.5rem;">
                    <i class="fas fa-check" style="margin-right: 0.5rem;"></i>
                    Close
                </button>
            </div>
        `;
        
        // Clear any existing notifications
        const notificationContainer = document.getElementById('modalNotificationContainer');
        if (notificationContainer) {
            notificationContainer.innerHTML = '';
            notificationContainer.style.display = 'none';
        }
    }
    
    function showSuccessNotification() {
        const modal = document.getElementById('successNotificationModal');
        modal.classList.add('active');
    }
    
    function closeSuccessNotification() {
        const modal = document.getElementById('successNotificationModal');
        modal.classList.remove('active');
    }
});
</script>
</body>
</html>