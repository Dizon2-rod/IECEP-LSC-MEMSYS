<?php
session_start();

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
            'eb_president' => '/portal/super-admin/dashboard.php',
            'admin' => '/portal/admin/dashboard.php',
            'school_officer' => '/portal/school-officer/dashboard.php',
            'member' => '/portal/member/dashboard.php',
            'eb_pro_1' => '/portal/creatives/dashboard.php',
            'committee_creatives' => '/portal/creatives/dashboard.php',
            'eb_pro_2' => '/portal/admin/dashboard.php',
            'eb_treasurer' => '/portal/admin/dashboard.php',
            'eb_auditor' => '/portal/admin/dashboard.php',
            'eb_secretary' => '/portal/asst-secretary/dashboard.php',
        ];

        $redirectUrl = $redirectMap[$role] ?? '/portal/member/dashboard.php';
        error_log("Redirecting to: $redirectUrl");
        header('Location: ' . $redirectUrl);
        exit;
    }
}

function generateHash($receiptId, $amount, $payer, $previousHash) {
    $data = $receiptId . $amount . $payer . $previousHash . time();
    return hash('sha256', $data);
}

function sendVerificationCode($email) {
    // Generate 6-digit code
    $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

    // Store in session
    $_SESSION['verification_code'] = $code;
    $_SESSION['verification_email'] = $email;
    $_SESSION['code_sent_time'] = time();

    try {
        // Use EmailService with Gmail SMTP
        require_once __DIR__ . '/src/lib/EmailService.php';
        $emailService = new \App\Lib\EmailService();

        $result = $emailService->sendVerificationCode($email, $code);

        // Log for debugging
        error_log("Email verification attempt: " . $email . " - Result: " . ($result ? 'SUCCESS' : 'FAILED'));

        return $result;
    } catch (Exception $e) {
        error_log("Email verification error: " . $e->getMessage());
        return false;
    }
}

function verifyCode($code) {
    if(isset($_SESSION['verification_code']) && 
       isset($_SESSION['verification_email']) &&
       time() - $_SESSION['code_sent_time'] < 600) { // 10 minutes expiry
        
        if($_SESSION['verification_code'] === $code) {
            unset($_SESSION['verification_code']);
            return true;
        }
    }
    return false;
}

// Handle AJAX requests
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if(isset($_POST['action']) && $_POST['action'] === 'send_code') {
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        if(filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if(sendVerificationCode($email)) {
                echo json_encode(['success' => true, 'message' => 'Verification code sent to your email.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to send verification code. Please check your email configuration.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
        }
        exit;
    }
    
    if(isset($_POST['action']) && $_POST['action'] === 'verify_code') {
        $code = $_POST['code'];
        if(verifyCode($code)) {
            echo json_encode(['success' => true, 'message' => 'Email verified successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code.']);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>IECEP-LSC MEMSYS | Membership & Affiliation Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/public/css/components.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #ffffff;
            color: #1e293b;
            line-height: 1.5;
            overflow-x: hidden;
        }

        /* Hero */
        .hero {
            background: linear-gradient(rgba(10, 47, 108, 0.7), rgba(10, 47, 108, 0.7)), url('/public/assets/icons/hero.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            padding: 100px 0;
            position: relative;
            color: white;
            min-height: 100px;
            display: flex;
            align-items: center;
        }

        .hero-content {
            text-align: center;
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 40px;
        }

        
        .hero-content h1 {
            font-size: 3rem;
            font-family: 'Times New Roman MT';
            font-weight: auto;
            word-spacing: 15px;
            color: white;
            line-height: 1.2;
            margin-bottom: 20px;
        }

        .hero-content p {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 30px;
            max-width: 90%;
        }

        .hero-buttons {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .hero-image {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding-left: 20px;
        }

        .hero-image img {
            max-width: 100%;
            border-radius: 32px;
            box-shadow: 0 25px 40px -12px rgba(0,0,0,0.2);
        }

        .schools-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            max-width: 450px;
            width: 100%;
        }

        .schools-grid img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            transition: transform 0.3s ease;
            object-fit: contain;
        }

        .schools-grid img:hover {
            transform: scale(1.05);
        }

        /* Schools Banner */
        .schools-banner {
            background: linear-gradient(rgba(10, 47, 108, 0.8), rgba(10, 47, 108, 0.8)), url('/public/assets/icons/hero.png');
            background-size: cover;
            background-position: center;
            padding: 60px 0;
            text-align: center;
        }

        .unity-title {
            font-family: 'Times New Roman MT', Times, serif;
            font-size: 2.5rem;
            font-weight: 600;
            color: white;
            margin-top: -10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            font-style: italic;
        }

        .schools-row {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin-top: 30px;
            padding: 30px 0 0 0;
        }

        .schools-row img {
            max-width: 80px;
            height: auto;
            transition: transform 0.3s ease;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
            flex-shrink: 0;
        }

        .schools-row img:hover {
            transform: scale(1.1);
        }

        /* Features */
        .features {
            padding: 80px 0;
            background: white;
        }

        .section-title {
            text-align: center;
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--navy);
            margin-bottom: 16px;
        }

        .section-sub {
            text-align: center;
            color: var(--gray-700);
            max-width: 700px;
            margin: 0 auto 50px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 32px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .feature-card {
            position: relative;
            height: 500px;
            border-radius: 24px;
            overflow: hidden;
            background-size: cover;
            background-position: center;
            transition: all 0.5s ease;
            cursor: pointer;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            transition: transform 0.5s ease;
        }

        .feature-card:hover::before {
            transform: scale(1.1);
        }

        .feature-card::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0.3) 50%, transparent 100%);
            transition: all 0.5s ease;
        }

        .feature-card:hover::after {
            background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.4) 50%, transparent 100%);
        }

        .feature-card .feature-content {
            position: relative;
            z-index: 1;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 32px;
        }

        .feature-card h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 12px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .feature-card p {
            font-size: 0.95rem;
            color: rgba(255,255,255,0.9);
            line-height: 1.6;
            margin: 0;
        }

        @media (max-width: 1024px) {
            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 640px) {
            .features-grid {
                grid-template-columns: 1fr;
            }
        }

        /* How it works */
        .how-it-works {
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 100px 0;
        }

        .how-it-works .section-title {
            font-size: 2.25rem;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 12px;
        }

        .how-it-works .section-sub {
            font-size: 1.1rem;
            color: #64748b;
            max-width: 500px;
            margin: 0 auto;
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-top: 56px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .step-card {
            background: white;
            padding: 40px 28px;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .step-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(30, 58, 138, 0.08);
            border-color: #cbd5e1;
        }

        .step-number {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--gold) 0%, #fbbf24 100%);
            color: var(--navy);
            font-weight: 700;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            margin-bottom: 24px;
        }

        .step-card h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 12px;
            line-height: 1.4;
        }

        .step-card p {
            font-size: 0.95rem;
            color: #64748b;
            line-height: 1.6;
            margin: 0;
        }

        @media (max-width: 1024px) {
            .steps {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 640px) {
            .steps {
                grid-template-columns: 1fr;
            }
        }
        /* CTA */
        .cta {
            background: linear-gradient(120deg, var(--navy), #1a4a8a);
            color: white;
            text-align: center;
            padding: 40px 20px;
        }

        .cta h2 {
            font-size: 2rem;
            margin-bottom: 20px;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal-content {
            background: white;
            max-width: 900px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 32px;
            padding: 32px;
            position: relative;
            animation: fadeInUp 0.3s ease;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            font-size: 1.3rem;
            color: var(--navy);
        }

        .logo-img {
            height: 50px;
            width: auto;
        }
        .close-modal {
            position: absolute;
            top: 18px;
            right: 22px;
            font-size: 1.8rem;
            cursor: pointer;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px);}
            to { opacity: 1; transform: translateY(0);}
        }
        .code-section { display: none; }

        /* Step Indicator Styles */
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 32px;
        }
        .step {
            display: flex;
            align-items: center;
            color: #6c757d;
        }
        .step.active {
            color: var(--navy);
            font-weight: 600;
        }
        .step.completed {
            color: #28a745;
        }
        .step-number {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 2px solid #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
            font-weight: 600;
        }
        .step.active .step-number {
            border-color: var(--navy);
            background: var(--navy);
            color: white;
        }
        .step.completed .step-number {
            border-color: #28a745;
            background: #28a745;
            color: white;
        }
        .step-line {
            width: 60px;
            height: 2px;
            background: #6c757d;
            margin: 0 16px;
        }
        .step.completed + .step-line {
            background: #28a745;
        }

        /* Verification inputs */
        .verification-inputs {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin: 24px 0;
        }
        .verification-inputs input {
            width: 50px;
            height: 50px;
            text-align: center;
            font-size: 24px;
            font-weight: 600;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
        }
        .verification-inputs input:focus {
            outline: none;
            border-color: var(--navy);
        }

        /* Alert styles */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
        }
        .alert-danger {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        @media (max-width: 768px) {
            .hero-content h1 { font-size: 2rem; }
            .nav-links { display: none; }
            .step-indicator { flex-direction: column; align-items: center; }
            .step-line { width: 2px; height: 40px; margin: 8px 0; }
            .verification-inputs { gap: 8px; }
            .verification-inputs input { width: 40px; height: 40px; font-size: 20px; }
        }
    </style>
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<section class="hero">
    <div class="container">
        <div class="hero-content">
            <h2 class="unity-title">One LSC. One IECEP.</h2>
            <h1>Institute of Electronics Engineers <br>of the Philippines<br>Laguna Student Chapter</h1>
            <div class="hero-buttons">
                <button class="btn btn-primary" id="affiliateNowBtn"><i class="fas fa-handshake"></i> Affiliate Now</button>
                <a href="#how-it-works" class="btn btn-outline">How to Get Affiliated</a>
            </div>
            <div class="schools-row">
                <img src="/public/assets/icons/LETRAN.png" alt="Colegio de San Juan de Letrán">
                <img src="/public/assets/icons/LSPU-SCC.png" alt="Laguna State Polytechnic University - Santa Cruz Campus">
                <img src="/public/assets/icons/LSPU-SPCC.png" alt="Laguna State Polytechnic University - San Pablo City Campus">
                <img src="/public/assets/icons/MMCL.webp" alt="Malayan Colleges Laguna">
                <img src="/public/assets/icons/PUP-STA ROSA.png" alt="Polytechnic University of the Philippines - Santa Rosa Campus">
                <img src="/public/assets/icons/UC-PNC.png" alt="University of Calamba - Polytechnic College">
                <img src="/public/assets/icons/UPHSD.png" alt="University of Perpetual Help System - DALTA">
                <img src="/public/assets/icons/UPHSL-BINAN.png" alt="University of Perpetual Help System Laguna - Biñán Campus">
            </div>
        </div>
    </div>
</section>

<section id="features" class="features">
    <div class="container">
        <h2 class="section-title">What's New?</h2>
        <p class="section-sub">Updates and announcements from the IECEP-LSC.</p>
        <div class="features-grid">
            <?php
            require_once __DIR__ . '/src/lib/SupabaseClient.php';
            $config = require __DIR__ . '/src/config/supabase.php';
            $supabase = new SupabaseClient($config['url'], $config['anon_key']);
            
            // Expose Supabase config to JavaScript for real-time subscriptions
            $supabaseUrl = $config['url'];
            $supabaseKey = $config['anon_key'];
            
            try {
                $features = $supabase->select('creatives_features');
                foreach ($features as $feature):
            ?>
            <div class="feature-card" style="background-image: url('<?php echo htmlspecialchars($feature['image']); ?>');">
                <div class="feature-content">
                    <h3><?php echo htmlspecialchars($feature['title']); ?></h3>
                    <p><?php echo htmlspecialchars($feature['description']); ?></p>
                </div>
            </div>
            <?php 
                endforeach;
            } catch (Exception $e) {
                // Fallback to static content if Supabase fails
            }
            ?>
        </div>
    </div>
</section>

<section id="how-it-works" class="how-it-works">
    <div class="container">
        <h2 class="section-title">How to get Affiliated?</h2>
        <p class="section-sub">Simple Step Process to Bring Your Institution into IECEP-LSC</p>
        <div class="steps">
            <div class="step-card">
                <div class="step-number">1</div>
                <h3>Email verification</h3>
                <p>Enter your institution email address and receive a secure 6-digit verification code.</p>
            </div>
            <div class="step-card">
                <div class="step-number">2</div>
                <h3>Submit requirements</h3>
                <p>Upload the 6 required documents including (Letter of Intent, Endorsement Letter, and Member Directory, ect...)</p>
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

<section class="cta">
    <div class="container" style="display: flex; justify-content: space-between; align-items: center; gap: 24px;">
        <div style="flex: 1;">
            <h2>Ready to Digitize Your Affiliation?</h2>
            <button class="btn btn-primary" id="ctaAffiliateBtn" style="background:white; color:var(--navy);"><i class="fas fa-arrow-right"></i> Start Affiliation Now</button>
        </div>
        <div style="flex: 1; background: white; padding: 16px; border-radius: 12px; max-width: 400px;">
            <h3 style="color: var(--navy); margin-bottom: 12px; font-size: 1.1rem;">Contact Us</h3>
            <?php if ($contactSuccess): ?>
            <div style="background: #dcfce7; color: #166534; padding: 12px; border-radius: 6px; margin-bottom: 12px; font-size: 0.9rem;">
                <i class="fas fa-check-circle"></i> Message sent successfully!
            </div>
            <?php endif; ?>
            <?php if ($contactError): ?>
            <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 12px; font-size: 0.9rem;">
                <i class="fas fa-exclamation-circle"></i> Failed to send message. Please try again.
            </div>
            <?php endif; ?>
            <form method="POST" action="/contact-submit.php" style="display: flex; flex-direction: column; gap: 8px;">
                <input type="text" name="name" placeholder="Name" required style="padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.85rem;">
                <input type="email" name="email" placeholder="Email" required style="padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.85rem;">
                <textarea name="message" placeholder="Message" required style="padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.85rem; min-height: 60px; resize: vertical;"></textarea>
                <button type="submit" class="btn btn-primary" style="background: var(--navy); color: white; padding: 8px 16px;">
                    <i class="fas fa-paper-plane"></i> Send
                </button>
            </form>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

<!-- Modal (Affiliation Application) -->
<div id="affiliateModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" id="closeModalBtn" style="position: absolute; top: 15px; right: 20px; font-size: 28px; cursor: pointer; color: #666;">&times;</span>

        <div style="padding: 20px;">
            <h3 style="text-align: center; margin-bottom: 32px; color: var(--navy);">Affiliation Application</h3>

            <!-- Step Indicator -->
            <div class="step-indicator" style="margin-bottom: 32px;">
                <div class="step active" id="modal-step1">
                    <div class="step-number">1</div>
                    <span>Gmail Verification</span>
                </div>
                <div class="step-line"></div>
                <div class="step" id="modal-step2">
                    <div class="step-number">2</div>
                    <span>Application Form</span>
                </div>
            </div>

            <!-- Step 1: Email Verification -->
            <div id="modal-email-verification-step">
                <div style="background: #f8f9fa; padding: 32px; border-radius: 8px; margin-bottom: 24px;">
                    <h4 style="text-align: center; margin-bottom: 24px; color: var(--navy);">Verify Your Gmail</h4>
                    <p style="text-align: center; color: #6c757d; margin-bottom: 32px;">
                        Enter your gmail address to receive a verification code
                    </p>

                    <div id="modal-email-form">
                        <div style="max-width: 400px; margin: 0 auto 24px;">
                            <label for="modal-verification-email" style="display: block; margin-bottom: 8px; font-weight: 500;">Email Address <span style="color: #DC3545">*</span></label>
                            <input type="email" class="form-control" id="modal-verification-email" placeholder="your.email@gmail.com or your.email@institution.edu" required style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px;">
                            <small style="color: #6c757d; font-size: 0.9rem;">Please use your email address (Gmail or institutional email)</small>
                        </div>

                        <div style="text-align: center;">
                            <button type="button" class="btn btn-primary" id="modal-send-code-btn" style="min-width: 200px; padding: 12px 24px;">
                                Send Verification Code
                            </button>
                        </div>
                    </div>

                    <div id="modal-code-form" style="display: none;">
                        <div style="text-align: center; margin-bottom: 24px;">
                            <p style="color: #28a745; font-weight: 600;">Verification code sent to <span id="modal-sent-email"></span></p>
                            <p style="color: #6c757d; font-size: 0.9rem;">Please check your inbox and spam folder</p>
                        </div>

                        <div class="verification-inputs" style="margin: 24px 0;">
                            <input type="text" maxlength="1" class="code-input" data-index="0" style="width: 50px; height: 50px; text-align: center; font-size: 24px; font-weight: 600; border: 2px solid #e2e8f0; border-radius: 8px; margin: 0 4px;">
                            <input type="text" maxlength="1" class="code-input" data-index="1" style="width: 50px; height: 50px; text-align: center; font-size: 24px; font-weight: 600; border: 2px solid #e2e8f0; border-radius: 8px; margin: 0 4px;">
                            <input type="text" maxlength="1" class="code-input" data-index="2" style="width: 50px; height: 50px; text-align: center; font-size: 24px; font-weight: 600; border: 2px solid #e2e8f0; border-radius: 8px; margin: 0 4px;">
                            <input type="text" maxlength="1" class="code-input" data-index="3" style="width: 50px; height: 50px; text-align: center; font-size: 24px; font-weight: 600; border: 2px solid #e2e8f0; border-radius: 8px; margin: 0 4px;">
                            <input type="text" maxlength="1" class="code-input" data-index="4" style="width: 50px; height: 50px; text-align: center; font-size: 24px; font-weight: 600; border: 2px solid #e2e8f0; border-radius: 8px; margin: 0 4px;">
                            <input type="text" maxlength="1" class="code-input" data-index="5" style="width: 50px; height: 50px; text-align: center; font-size: 24px; font-weight: 600; border: 2px solid #e2e8f0; border-radius: 8px; margin: 0 4px;">
                        </div>

                        <div style="text-align: center;">
                            <button type="button" class="btn btn-success" id="modal-verify-code-btn" style="min-width: 200px; padding: 12px 24px;">
                                Verify Code
                            </button>
                        </div>

                        <div style="text-align: center; margin-top: 24px;">
                            <div id="modal-countdown" style="color: #6c757d; font-size: 0.9rem; margin-bottom: 8px;">You can request a new code in <span id="modal-timer">60</span> seconds</div>
                            <button type="button" id="modal-resend-btn" style="background: none; border: none; color: var(--navy); text-decoration: underline; cursor: pointer; font-size: 0.9rem;" disabled>Resend Code</button>
                        </div>
                    </div>

                    <div id="modal-verification-error" class="alert alert-danger" style="display: none; margin-top: 24px;"></div>
                    <div id="modal-verification-success" class="alert alert-success" style="display: none; margin-top: 24px;"></div>
                </div>
            </div>

            <!-- Step 2: Application Form -->
            <div id="modal-application-form-step" style="display: none;">
                <div style="background: #f8f9fa; padding: 32px; border-radius: 8px; margin-bottom: 24px;">
                    <h4 style="margin-bottom: 24px; color: var(--navy);">Institution Information</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px;">
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 500;">Institution Name <span style="color: #DC3545">*</span></label>
                            <input type="text" class="form-control" id="modal-inst-name" required style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 500;">Institution Type <span style="color: #DC3545">*</span></label>
                            <select class="form-control" id="modal-inst-type" required style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px;">
                                <option>-- Select --</option>
                                <option>Public University</option>
                                <option>Private University</option>
                                <option>College</option>
                                <option>Technical Institution</option>
                            </select>
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 500;">Address <span style="color: #DC3545">*</span></label>
                            <input type="text" class="form-control" id="modal-inst-address" required style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px;">
                        </div>
                    </div>
                </div>

                <div style="background: #f8f9fa; padding: 32px; border-radius: 8px;">
                    <h4 style="margin-bottom: 24px; color: var(--navy);">Contact Information</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 24px;">
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 500;">Contact Person Name <span style="color: #DC3545">*</span></label>
                            <input type="text" class="form-control" id="modal-contact-name" required style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 500;">Position <span style="color: #DC3545">*</span></label>
                            <input type="text" class="form-control" id="modal-contact-position" required style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 500;">Email <span style="color: #DC3545">*</span></label>
                            <input type="email" class="form-control" id="modal-contact-email" readonly style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8f9fa;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 8px; font-weight: 500;">Phone <span style="color: #DC3545">*</span></label>
                            <input type="tel" class="form-control" id="modal-contact-phone" required
                                   pattern="09\d{9}"
                                   maxlength="11"
                                   placeholder="09XXXXXXXXX"
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                   style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px;">
                            <small style="color: #6c757d; font-size: 0.9rem;">Format: 09XXXXXXXXX (11 digits, numbers only)</small>
                        </div>
                    </div>

                    <!-- Required Documents Section -->
                    <div style="margin-top: 32px;">
                        <h5 style="margin-bottom: 16px; color: var(--navy);">Required Documents</h5>
                        <div class="alert alert-info" style="background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 12px; border-radius: 8px; margin-bottom: 24px;">
                            <strong>Note:</strong> Please prepare the following documents before proceeding:
                            <ul style="margin-top: 12px; margin-bottom: 0;">
                                <li>Letter of Intent (PDF only)</li>
                                <li>Endorsement Letter (PDF only)</li>
                                <li>Constitution and By-Laws (PDF only)</li>
                                <li>List of Officers with CVs (PDF only)</li>
                                <li>Organizational Chart (PDF only)</li>
                                <li>Member Directory (PDF, Excel, or CSV)</li>
                            </ul>
                        </div>

                        <div id="modal-document-upload-section" style="display: none;">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
                                <div>
                                    <label style="display: block; margin-bottom: 8px; font-weight: 500;">Letter of Intent <span style="color: #DC3545">*</span></label>
                                    <input type="file" class="form-control" name="letter_of_intent" accept=".pdf" required style="width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 8px;">
                                    <small style="color: #6c757d; font-size: 0.8rem;">Accepted format: .pdf only</small>
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 8px; font-weight: 500;">Endorsement Letter <span style="color: #DC3545">*</span></label>
                                    <input type="file" class="form-control" name="endorsement_letter" accept=".pdf" required style="width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 8px;">
                                    <small style="color: #6c757d; font-size: 0.8rem;">Accepted format: .pdf only</small>
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 8px; font-weight: 500;">Constitution and By-Laws <span style="color: #DC3545">*</span></label>
                                    <input type="file" class="form-control" name="constitution_by_laws" accept=".pdf" required style="width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 8px;">
                                    <small style="color: #6c757d; font-size: 0.8rem;">Accepted format: .pdf only</small>
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 8px; font-weight: 500;">List of Officers with CVs <span style="color: #DC3545">*</span></label>
                                    <input type="file" class="form-control" name="officers_cvs" accept=".pdf" required style="width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 8px;">
                                    <small style="color: #6c757d; font-size: 0.8rem;">Accepted format: .pdf only</small>
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 8px; font-weight: 500;">Organizational Chart <span style="color: #DC3545">*</span></label>
                                    <input type="file" class="form-control" name="organizational_chart" accept=".pdf" required style="width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 8px;">
                                    <small style="color: #6c757d; font-size: 0.8rem;">Accepted format: .pdf only</small>
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 8px; font-weight: 500;">Member Directory <span style="color: #DC3545">*</span></label>
                                    <input type="file" class="form-control" name="member_directory" accept=".pdf,.xls,.xlsx,.csv" required style="width: 100%; padding: 8px; border: 1px solid #e2e8f0; border-radius: 8px;">
                                    <small style="color: #6c757d; font-size: 0.8rem;">Accepted formats: .pdf,.xls,.xlsx,.csv</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 32px;">
                        <label style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 16px;">
                            <input type="checkbox" id="modal-terms-checkbox" required>
                            <span style="font-size: 0.9rem;">I agree to the terms and conditions and certify that all information provided is accurate</span>
                        </label>
                        <button type="button" class="btn btn-primary" id="modal-submit-application-btn" style="min-width: 200px; padding: 12px 24px;" disabled>
                            Submit Application
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let verifiedEmail = '';
    let verificationToken = '';
    let countdownInterval;

    // Modal functionality
    const modal = document.getElementById('affiliateModal');
    const btns = document.querySelectorAll('#affiliateNowBtn, #ctaAffiliateBtn');
    const closeBtn = document.getElementById('closeModalBtn');

    function openModal() {
        modal.style.display = 'flex';
        resetModal();
    }

    function resetModal() {
        // Reset to step 1
        document.getElementById('modal-step1').classList.add('active');
        document.getElementById('modal-step1').classList.remove('completed');
        document.getElementById('modal-step2').classList.remove('active', 'completed');

        document.getElementById('modal-email-verification-step').style.display = 'block';
        document.getElementById('modal-application-form-step').style.display = 'none';

        // Reset forms
        document.getElementById('modal-email-form').style.display = 'block';
        document.getElementById('modal-code-form').style.display = 'none';
        document.getElementById('modal-verification-email').value = '';
        document.querySelectorAll('.code-input').forEach(input => input.value = '');
        document.getElementById('modal-verification-error').style.display = 'none';
        document.getElementById('modal-verification-success').style.display = 'none';

        verifiedEmail = '';
        verificationToken = '';
        clearInterval(countdownInterval);
    }

    btns.forEach(btn => btn.addEventListener('click', openModal));
    closeBtn.addEventListener('click', () => modal.style.display = 'none');
    window.addEventListener('click', e => { if (e.target === modal) modal.style.display = 'none'; });

    // Email verification functionality
    document.getElementById('modal-send-code-btn').addEventListener('click', async function() {
        const email = document.getElementById('modal-verification-email').value.trim();

        if (!email) {
            showModalError('Please enter your email address');
            return;
        }

        if (!validateEmail(email)) {
            showModalError('Please enter a valid email address');
            return;
        }

        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm" style="margin-right: 8px;"></span>Sending...';

        try {
            const apiUrl = window.location.origin + '/public/api.php?endpoint=affiliate&action=send-code';
            console.log('Fetching from:', apiUrl);
            console.log('Request body:', JSON.stringify({ email: email }));

            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email: email })
            });

            console.log('Response status:', response.status);
            console.log('Response ok:', response.ok);

            const result = await response.json();
            console.log('Response data:', result);

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
                startCountdown();
            } else {
                showModalError(result.message || result.error || 'Failed to send verification code');
                this.disabled = false;
                this.innerHTML = 'Send Verification Code';
            }
        } catch (error) {
            console.error('Fetch error:', error);
            console.error('Error name:', error.name);
            console.error('Error message:', error.message);
            showModalError('Network error: ' + error.message + '. Please try again.');
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
                const digits = pastedData.split('');

                digits.forEach((digit, i) => {
                    if (i < inputs.length && /^\d$/.test(digit)) {
                        inputs[i].value = digit;
                    }
                });

                if (digits.length >= inputs.length) {
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
        this.innerHTML = '<span class="spinner-border spinner-border-sm" style="margin-right: 8px;"></span>Verifying...';

        try {
            const response = await fetch('/public/api.php?endpoint=affiliate&action=verify-code', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email: email, code: code })
            });

            const result = await response.json();

            if (result.success) {
                verifiedEmail = email;
                verificationToken = result.token;
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

    document.getElementById('modal-resend-btn').addEventListener('click', async function() {
        const email = document.getElementById('modal-verification-email').value.trim();

        this.disabled = true;
        this.textContent = 'Sending...';

        try {
            const response = await fetch('api.php?endpoint=affiliate&action=send-code', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email: email })
            });

            const result = await response.json();

            if (result.success) {
                showModalSuccess('New verification code sent!');
                startCountdown();
                // Clear code inputs
                document.querySelectorAll('.code-input').forEach(input => input.value = '');
                document.querySelector('.code-input').focus();
            } else {
                showModalError(result.error || 'Failed to resend verification code');
            }
        } catch (error) {
            showModalError('Network error. Please try again.');
        } finally {
            this.disabled = false;
            this.textContent = 'Resend Code';
        }
    });

    function startCountdown() {
        let seconds = 60;
        const countdownEl = document.getElementById('modal-countdown');
        const timerEl = document.getElementById('modal-timer');
        const resendBtn = document.getElementById('modal-resend-btn');

        countdownEl.style.display = 'block';
        resendBtn.disabled = true;

        clearInterval(countdownInterval);

        countdownInterval = setInterval(() => {
            seconds--;
            timerEl.textContent = seconds;

            if (seconds <= 0) {
                clearInterval(countdownInterval);
                countdownEl.style.display = 'none';
                resendBtn.disabled = false;
            }
        }, 1000);
    }

    function moveToModalStep2() {
        // Update step indicator
        document.getElementById('modal-step1').classList.remove('active');
        document.getElementById('modal-step1').classList.add('completed');
        document.getElementById('modal-step2').classList.add('active');

        // Hide verification step, show application form
        document.getElementById('modal-email-verification-step').style.display = 'none';
        document.getElementById('modal-application-form-step').style.display = 'block';

        // Set verified email in contact form
        document.getElementById('modal-contact-email').value = verifiedEmail;

        // Setup document upload section
        setupDocumentUpload();
    }

    function setupDocumentUpload() {
        const uploadSection = document.getElementById('modal-document-upload-section');
        uploadSection.style.display = 'block';
    }

    // Terms checkbox validation
    document.getElementById('modal-terms-checkbox').addEventListener('change', function() {
        document.getElementById('modal-submit-application-btn').disabled = !this.checked;
    });

    // Submit application
    document.getElementById('modal-submit-application-btn').addEventListener('click', async function() {
        console.log('Submit button clicked');
        
        if (!validateModalApplicationForm()) {
            console.log('Validation failed');
            return;
        }

        console.log('Validation passed, preparing to submit');
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm" style="margin-right: 8px;"></span>Submitting...';

        const formData = new FormData();

        // Add form fields
        formData.append('contact_email', verifiedEmail);
        formData.append('institution_name', document.getElementById('modal-inst-name').value);
        formData.append('institution_address', document.getElementById('modal-inst-address').value);
        formData.append('contact_name', document.getElementById('modal-contact-name').value);
        formData.append('contact_position', document.getElementById('modal-contact-position').value);
        formData.append('contact_phone', document.getElementById('modal-contact-phone').value);
        formData.append('terms', 'accepted');

        console.log('Form data prepared');

        // Add documents
        const documentInputs = document.querySelectorAll('#modal-document-upload-section input[type="file"]');
        console.log('Document inputs found:', documentInputs.length);
        documentInputs.forEach(input => {
            if (input.files[0]) {
                console.log('Adding file:', input.name, input.files[0].name);
                formData.append(input.name, input.files[0]);
            }
        });

        try {
            console.log('Sending fetch request...');
            const apiUrl = window.location.origin + '/public/api.php?endpoint=affiliate&action=submit';
            const response = await fetch(apiUrl, {
                method: 'POST',
                body: formData
            });

            console.log('Submitted to:', apiUrl);
            console.log('Response received, status:', response.status);
            console.log('Response ok:', response.ok);
            console.log('Response content-type:', response.headers.get('content-type'));

            // Get raw response text first to debug
            const responseText = await response.text();
            console.log('Raw response:', responseText.substring(0, 500));

            // Try to parse as JSON
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (e) {
                console.error('Failed to parse JSON:', e);
                console.error('Response was:', responseText);
                showModalError('Server returned invalid response. Please check server logs.');
                this.disabled = false;
                this.innerHTML = 'Submit Application';
                return;
            }

            console.log('Parsed result:', result);

            if (result.success) {
                showModalSuccess(result.message);
                setTimeout(() => {
                    modal.style.display = 'none';
                    // Optional: redirect or show success message
                }, 3000);
            } else {
                console.error('Submission failed:', result);
                showModalError(result.error || result.message || 'Failed to submit application');
                this.disabled = false;
                this.innerHTML = 'Submit Application';
            }
        } catch (error) {
            console.error('Network error:', error);
            showModalError('Network error. Please try again. Error: ' + error.message);
            this.disabled = false;
            this.innerHTML = 'Submit Application';
        }
    });

    function validateModalApplicationForm() {
        const fields = ['modal-inst-name', 'modal-inst-address', 'modal-contact-name', 'modal-contact-position', 'modal-contact-phone'];
        const fieldNames = {
            'modal-inst-name': 'Institution Name',
            'modal-inst-address': 'Address',
            'modal-contact-name': 'Contact Person Name',
            'modal-contact-position': 'Position',
            'modal-contact-phone': 'Phone'
        };

        for (const fieldId of fields) {
            const field = document.getElementById(fieldId);
            if (!field.value.trim()) {
                showModalError(`${fieldNames[fieldId]} is required`);
                field.focus();
                return false;
            }
        }

        // Validate phone number format
        const phoneField = document.getElementById('modal-contact-phone');
        const phoneValue = phoneField.value.trim();
        const phonePattern = /^09\d{9}$/;
        if (!phonePattern.test(phoneValue)) {
            showModalError('Phone number must be 11 digits starting with 09 (e.g., 09123456789)');
            phoneField.focus();
            return false;
        }

        // Check if all documents are uploaded
        const documentInputs = document.querySelectorAll('#modal-document-upload-section input[type="file"]');
        for (const input of documentInputs) {
            if (!input.files[0]) {
                showModalError(`Please upload ${input.previousElementSibling.textContent.replace(' *', '')}`);
                return false;
            }
        }

        return true;
    }

    function validateEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

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
});

</script>
<script>
    document.getElementById('loginBtn').addEventListener('click', (e) => { 
        e.preventDefault(); 
        window.location.href = '/login.php'; 
    });

    // Dropdown functionality
    const dropdowns = document.querySelectorAll('.dropdown');
    
    dropdowns.forEach(dropdown => {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        const links = dropdown.querySelectorAll('.dropdown-menu a');
        
        // Prevent navigation on toggle click
        toggle.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            
            // Close all other dropdowns
            dropdowns.forEach(otherDropdown => {
                if (otherDropdown !== dropdown) {
                    otherDropdown.classList.remove('active');
                }
            });
            
            // Toggle current dropdown
            dropdown.classList.toggle('active');
        });
        
        // Allow navigation only when option is clicked
        links.forEach(link => {
            link.addEventListener('click', (e) => {
                // Close dropdown after clicking an option
                dropdown.classList.remove('active');
            });
        });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.dropdown')) {
            dropdowns.forEach(dropdown => {
                dropdown.classList.remove('active');
            });
        }
    });

    // Close dropdowns when pressing Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            dropdowns.forEach(dropdown => {
                dropdown.classList.remove('active');
            });
        }
    });
</script>

<script>
    window.SUPABASE_URL = <?php echo json_encode($supabaseUrl); ?>;
    window.SUPABASE_ANON_KEY = <?php echo json_encode($supabaseKey); ?>;
</script>
</body>
</html>