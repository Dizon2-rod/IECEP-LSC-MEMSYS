<?php
require_once __DIR__ . '/bootstrap.php';
// Apply Page
session_start();

// Allow direct access to apply.php for resubmission even if logged in
// Bypass any authentication redirects
if (isset($_GET['resubmit'])) {
    // Don't redirect, allow access for resubmission
    error_log("Resubmit mode detected - bypassing auth checks");
} elseif (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // If not resubmitting and logged in, redirect to dashboard
    require_once dirname(__DIR__) . '/includes/paths.php';
    $role = $_SESSION['user']['role'] ?? ($_SESSION['role'] ?? 'member');
    $redirectUrl = function_exists('get_role_dashboard_url')
        ? get_role_dashboard_url($role)
        : PORTAL_URL . '/member/dashboard.php';
    header('Location: ' . $redirectUrl);
    exit;
}

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/../includes/supabase.php';
require_once __DIR__ . '/../includes/paths.php';

$existingApplication = null;
$changeInstructions = '';
$resubmitId = $_GET['resubmit'] ?? '';

if (!empty($resubmitId)) {
    try {
        require_once __DIR__ . '/../src/lib/SupabaseClient.php';
        $config = require __DIR__ . '/../includes/supabase.php';
        $supabase = new SupabaseClient($config['url'], $config['anon_key']);
        $application = $supabase->select('pending_affiliations', ['id' => 'eq.' . $resubmitId]);

        error_log("Resubmit ID: $resubmitId, Application found: " . (!empty($application) ? 'YES' : 'NO'));

        if (!empty($application) && is_array($application)) {
            $existingApplication = $application[0];
            error_log("Existing application data: " . json_encode($existingApplication));
            // Extract change instructions from documents
            if (!empty($existingApplication['documents'])) {
                $docs = json_decode($existingApplication['documents'], true) ?: [];
                $changeInstructions = $docs['changes_instructions'] ?? '';
                error_log("Change instructions: $changeInstructions");
            }
        }
    } catch (Exception $e) {
        error_log("Error loading application: " . $e->getMessage());
    }
}
$isResubmit = !empty($existingApplication);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Affiliation Application - IECEP-LSC MEMSYS</title>
    <?php include __DIR__ . '/../includes/head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/styles.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/professional.css">
    <style>
        .page-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 var(--space-4);
        }

        .page-title {
            text-align: center;
            font-size: clamp(2rem, 4vw, 2.5rem);
            font-weight: 800;
            color: var(--primary);
            margin-bottom: var(--space-8);
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: var(--space-8);
            gap: var(--space-4);
        }

        .step {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            color: var(--neutral-500);
            font-weight: 500;
            transition: all var(--transition-base);
        }

        .step.active {
            color: var(--primary);
            font-weight: 700;
        }

        .step.completed {
            color: var(--accent);
        }

        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid var(--neutral-300);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            transition: all var(--transition-base);
        }

        .step.active .step-number {
            border-color: var(--primary);
            background: var(--primary);
            color: var(--white);
        }

        .step.completed .step-number {
            border-color: var(--accent);
            background: var(--accent);
            color: var(--primary);
        }

        .step-line {
            width: 60px;
            height: 2px;
            background: var(--neutral-300);
        }

        .step.completed + .step-line {
            background: var(--accent);
        }

        .card {
            background: var(--white);
            border-radius: var(--radius-xl);
            padding: var(--space-8);
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid var(--neutral-200);
        }

        .verification-inputs {
            display: flex;
            gap: var(--space-3);
            justify-content: center;
            margin: var(--space-6) 0;
        }

        .verification-inputs input {
            width: 60px;
            height: 60px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            border: 2px solid var(--neutral-300);
            border-radius: var(--radius-lg);
            color: var(--primary);
            transition: all var(--transition-base);
        }

        .verification-inputs input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(11, 29, 74, 0.1);
        }

        .hidden {
            display: none !important;
        }

        .countdown {
            color: var(--neutral-500);
            font-size: 0.9rem;
            margin-top: var(--space-3);
        }

        .resend-btn {
            background: none;
            border: none;
            color: var(--primary);
            text-decoration: underline;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all var(--transition-base);
        }

        .resend-btn:hover {
            color: var(--accent);
        }

        .resend-btn:disabled {
            color: var(--neutral-500);
            text-decoration: none;
            cursor: not-allowed;
        }

        .form-group {
            margin-bottom: var(--space-4);
        }

        .form-group label {
            display: block;
            margin-bottom: var(--space-2);
            font-weight: 600;
            color: var(--neutral-900);
        }

        .form-control {
            width: 100%;
            padding: var(--space-3);
            border: 2px solid var(--neutral-300);
            border-radius: var(--radius-md);
            font-family: inherit;
            font-size: 1rem;
            transition: border-color var(--transition-base);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }

        .form-control:read-only {
            background: var(--neutral-100);
            cursor: not-allowed;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-3) var(--space-6);
            font-size: 1rem;
            font-weight: 600;
            border-radius: var(--radius-md);
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: all var(--transition-base);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: var(--white);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(11, 29, 74, 0.3);
        }

        .btn-lg {
            padding: var(--space-4) var(--space-8);
            font-size: 1.1rem;
        }

        .alert {
            padding: var(--space-4);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-6);
        }

        .alert-warning {
            background: #FFF3CD;
            border: 1px solid #FFC107;
            color: #856404;
        }

        .alert-info {
            background: #D1ECF1;
            border: 1px solid #B8Daff;
            color: #0C5460;
        }

        .alert-success {
            background: #D4EDDA;
            border: 1px solid #C3E6CB;
            color: #155724;
        }

        .alert-danger {
            background: #F8D7DA;
            border: 1px solid #F5C6CB;
            color: #721C24;
        }

        @media (min-width: 768px) {
            .page-container {
                margin: 60px auto;
            }
        }

        @media (max-width: 768px) {
            .step-indicator {
                display: flex;
                flex-direction: row;
                align-items: center;
                justify-content: center;
                gap: 0.4rem;
                padding: 0 0.5rem;
            }

            .step-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 0.25rem;
            }

            .step-number {
                width: 32px;
                height: 32px;
                font-size: 0.85rem;
            }

            .step-item span {
                font-size: 0.68rem;
                text-align: center;
            }

            .step-line {
                flex: 1;
                max-width: 40px;
                min-width: 12px;
                height: 2px;
                transform: none;
            }

            .verification-inputs input {
                width: 45px;
                height: 45px;
                font-size: 1.25rem;
            }

            #member-summary-card > div:first-of-type {
                grid-template-columns: 1fr !important;
            }

            table {
                font-size: 0.875rem;
            }

            table th, table td {
                padding: var(--space-2) !important;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="page-container">
        <h1 class="page-title">Affiliation Application</h1>

        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step active" id="step1">
                <div class="step-number">1</div>
                <span>Email Verification</span>
            </div>
            <div class="step-line"></div>
            <div class="step" id="step2">
                <div class="step-number">2</div>
                <span>Application Form</span>
            </div>
            <div class="step-line"></div>
            <div class="step" id="step3">
                <div class="step-number">3</div>
                <span>Payment Summary</span>
            </div>
        </div>

        <!-- Change Instructions Banner (for resubmission) -->
        <?php if (!empty($changeInstructions)): ?>
        <div class="alert alert-warning" style="margin-bottom: var(--space-6)">
            <h4 style="margin-top: 0; color: #856404;">Changes Requested by Registration Committee</h4>
            <p style="white-space: pre-wrap; margin-bottom: 0; color: #856404;"><?php echo htmlspecialchars($changeInstructions); ?></p>
        </div>
        <?php endif; ?>

        <!-- Step 1: Email Verification -->
        <div id="email-verification-step" style="<?php echo !empty($existingApplication) ? 'display:none' : ''; ?>">
            <div class="card">
                <h3 style="text-align: center; margin-bottom: var(--space-4); color: var(--primary);">Step 1: Verify Your Email</h3>
                <p style="text-align: center; color: var(--neutral-500); margin-bottom: var(--space-6);">
                    Enter your email address to receive a 6-digit verification code before proceeding to institution details.
                </p>

                <!-- Hidden field indicating email verification status -->
                <input type="hidden" id="email-verified-flag" name="email_verified" value="<?php echo !empty($existingApplication) ? 'true' : 'false'; ?>">

                <div id="email-form">
                    <div class="form-group" style="max-width: 420px; margin: 0 auto var(--space-6);">
                        <label for="verification-email">Gmail / Institutional Email Address <span style="color: #DC3545;">*</span></label>
                        <input type="email" class="form-control" id="verification-email" placeholder="your.email@gmail.com or your.email@institution.edu" required>
                        <small style="color: var(--neutral-500); display: block; margin-top: var(--space-2);">A 6-digit verification code will be sent to your Gmail inbox.</small>
                    </div>

                    <div style="text-align: center;">
                        <button type="button" class="btn btn-primary btn-lg" id="send-code-btn" style="min-width: 220px;" onclick="handleSendVerificationCode(this)">
                            <i class="fas fa-paper-plane me-2"></i> Send Verification Code
                        </button>
                    </div>
                </div>

                <div id="code-form" class="hidden">
                    <div style="text-align: center; margin-bottom: var(--space-6);">
                        <p style="color: var(--accent); font-weight: 700; font-size: 1.05rem;">
                            <i class="fas fa-envelope-circle-check me-2"></i> Verification code sent to <span id="sent-email"></span>
                        </p>
                        <p style="color: var(--neutral-500); font-size: 0.9rem;">Please check your Gmail inbox and spam folder.</p>
                    </div>

                    <div class="form-group" style="max-width: 300px; margin: 0 auto var(--space-4);">
                        <label for="verification-code-input" style="text-align: center; display: block; font-weight: 600; color: var(--primary); margin-bottom: 8px;">
                            6-Digit Verification Code <span style="color: #DC3545;">*</span>
                        </label>
                        <input type="text" class="form-control" id="verification-code-input" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" placeholder="------" style="font-size: 2rem; font-weight: 800; letter-spacing: 12px; text-align: center; border: 2px solid #CBD5E1; border-radius: 12px; font-family: monospace;" autocomplete="one-time-code">
                    </div>

                    <div class="verification-inputs" style="margin-top: 10px; margin-bottom: 10px;">
                        <input type="text" maxlength="1" class="code-input" data-index="0">
                        <input type="text" maxlength="1" class="code-input" data-index="1">
                        <input type="text" maxlength="1" class="code-input" data-index="2">
                        <input type="text" maxlength="1" class="code-input" data-index="3">
                        <input type="text" maxlength="1" class="code-input" data-index="4">
                        <input type="text" maxlength="1" class="code-input" data-index="5">
                    </div>

                    <div style="text-align: center; margin-top: var(--space-6);">
                        <button type="button" class="btn btn-primary btn-lg" id="verify-code-btn" style="min-width: 220px;" onclick="handleVerifyCode(this)">
                            <i class="fas fa-check-circle me-2"></i> Verify Code
                        </button>
                    </div>

                    <div style="text-align: center; margin-top: var(--space-6); display: flex; flex-direction: column; align-items: center; gap: 8px;">
                        <div class="countdown" id="countdown">Code expires in <span id="timer">10:00</span></div>
                        <button type="button" class="resend-btn" id="resend-btn" disabled onclick="handleResendCode(this)">
                            <i class="fas fa-redo me-1"></i> Resend Verification Code
                        </button>
                        <button type="button" class="resend-btn" id="change-email-btn" style="color: #64748B; font-size: 0.85rem; text-decoration: none;" onclick="handleChangeEmail(this)">
                            ← Change Email Address
                        </button>
                    </div>
                </div>

                <div id="verification-error" class="alert alert-danger hidden" style="margin-top: var(--space-6);"></div>
                <div id="verification-success" class="alert alert-success hidden" style="margin-top: var(--space-6);"></div>
            </div>
        </div>

        <!-- Step 2: Application Form -->
        <div id="application-form-step" class="hidden" style="<?php echo !empty($existingApplication) ? 'display:block' : ''; ?>">
            <div class="card" style="padding: var(--space-8); margin-bottom: var(--space-6);">
                <h3 style="color: var(--primary); margin-bottom: var(--space-6);">Institution Information</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--space-6);">
                    <div class="form-group">
                        <label>Institution Name <span style="color: #DC3545;">*</span></label>
                        <input type="text" class="form-control" id="inst-name" required value="<?php echo htmlspecialchars($existingApplication['institution_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Institution Type <span style="color: #DC3545;">*</span></label>
                        <select class="form-control" id="inst-type" required>
                            <option>-- Select --</option>
                            <option>Public University</option>
                            <option>Private University</option>
                            <option>College</option>
                            <option>Technical Institution</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Address <span style="color: #DC3545;">*</span></label>
                        <input type="text" class="form-control" id="inst-address" required value="<?php echo htmlspecialchars($existingApplication['address'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="card" style="padding: var(--space-8);">
                <h3 style="color: var(--primary); margin-bottom: var(--space-6);">Contact Information</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--space-6);">
                    <div class="form-group">
                        <label>Contact Person Name <span style="color: #DC3545;">*</span></label>
                        <input type="text" class="form-control" id="contact-name" required value="<?php echo htmlspecialchars($existingApplication['contact_person'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Position <span style="color: #DC3545;">*</span></label>
                        <input type="text" class="form-control" id="contact-position" required value="<?php echo htmlspecialchars($existingApplication['contact_position'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Email <span style="color: #DC3545;">*</span></label>
                        <input type="email" class="form-control" id="contact-email" readonly style="background: var(--neutral-100);" value="<?php echo htmlspecialchars($existingApplication['email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone <span style="color: #DC3545;">*</span></label>
                        <input type="tel" class="form-control" id="contact-phone" required value="<?php echo htmlspecialchars($existingApplication['contact_phone'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Required Documents Section -->
                <div style="margin-top: var(--space-8);">
                    <h4 style="margin-bottom: var(--space-4); color: var(--primary);">Required Documents</h4>
                    <?php if ($isResubmit): ?>
                    <div class="alert alert-info">
                        <strong>Note:</strong> Only upload the documents that need to be updated based on the Registration Committee's instructions. Documents you don't upload will keep their existing versions.
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <strong>Note:</strong> Please prepare the following documents before proceeding:
                        <ul style="margin-top: var(--space-3); margin-bottom: 0;">
                            <li>Letter of Intent (PDF, Word, or Image)</li>
                            <li>Endorsement Letter (PDF, Word, or Image)</li>
                            <li>Constitution and By-Laws (PDF or Word)</li>
                            <li>List of Officers with CVs (PDF or Word)</li>
                            <li>Organizational Chart (PDF, Word, or Image)</li>
                            <li>Member Directory (PDF, Word, Excel, or CSV)</li>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <div id="document-upload-section" style="border: 1px solid var(--neutral-200); border-radius: var(--radius-lg); padding: var(--space-4);">
                        <?php if (!empty($existingApplication)): ?>
                            <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: var(--radius-md); padding: var(--space-3); margin-bottom: var(--space-4);">
                                <strong>Resubmission Mode:</strong> You can upload new files to replace existing documents below.
                            </div>
                            <?php
                            $documents = [
                                'letter_of_intent'     => ['name' => 'Letter of Intent (Official PDF)', 'accept' => '.pdf,application/pdf'],
                                'endorsement_letter'   => ['name' => 'Endorsement Letter (Signed PDF)', 'accept' => '.pdf,application/pdf'],
                                'constitution_by_laws' => ['name' => 'Constitution and By-Laws (PDF)', 'accept' => '.pdf,application/pdf'],
                                'officers_cvs'         => ['name' => 'List of Officers with CVs (PDF)', 'accept' => '.pdf,application/pdf'],
                                'organizational_chart' => ['name' => 'Organizational Chart (PDF)', 'accept' => '.pdf,application/pdf'],
                                'member_directory'     => ['name' => 'Member Directory (Excel / CSV)', 'accept' => '.xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv']
                            ];
                            $existingDocs = !empty($existingApplication['documents']) ? json_decode($existingApplication['documents'], true) : [];
                            error_log("Resubmit mode - Existing documents loaded: " . json_encode($existingDocs));
                            ?>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--space-6);">
                                <?php foreach ($documents as $key => $doc): ?>
                                    <?php
                                    $hasExisting = isset($existingDocs[$key]) && !empty($existingDocs[$key]);
                                    $existingFileName = $hasExisting ? ($existingDocs[$key]['name'] ?? 'uploaded file') : '';
                                    ?>
                                    <div class="form-group">
                                        <label><?php echo htmlspecialchars($doc['name']); ?></label>
                                        <?php if ($hasExisting): ?>
                                            <div style="background: #e8f5e9; border: 1px solid #28a745; border-radius: var(--radius-md); padding: var(--space-2); margin-bottom: var(--space-2);">
                                                <div style="color: #28a745; font-size: 13px; font-weight: 600;">&#10003; Current: <?php echo htmlspecialchars($existingFileName); ?></div>
                                                <div style="color: #666; font-size: 12px; margin-top: 4px;">Select a new file below to replace</div>
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" name="<?php echo $key; ?>" accept="<?php echo $doc['accept']; ?>" id="file-<?php echo $key; ?>" onchange="console.log('File selected for <?php echo $key; ?>:', this.files[0]?.name)" style="background: var(--white); cursor: pointer;">
                                        <small style="color: var(--neutral-500); display: block; margin-top: var(--space-2);">Accepted formats: <?php echo $doc['accept']; ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <!-- Documents will be added dynamically via JavaScript -->
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Member Count Summary Card -->
                <div id="member-summary-card" class="hidden" style="margin-top: var(--space-6); padding: var(--space-4); background: #f0f9ff; border: 2px solid #0ea5e9; border-radius: var(--radius-lg);">
                    <h4 style="color: var(--primary); margin-bottom: var(--space-3);">📊 Member Directory Summary</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: var(--space-3);">
                        <div>
                            <div style="font-size: 0.875rem; color: var(--neutral-600);">Total Members</div>
                            <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary);" id="summary-total">0</div>
                        </div>
                        <div>
                            <div style="font-size: 0.875rem; color: var(--neutral-600);">New Members</div>
                            <div style="font-size: 1.5rem; font-weight: 700; color: #10b981;" id="summary-new">0</div>
                        </div>
                        <div>
                            <div style="font-size: 0.875rem; color: var(--neutral-600);">Old/Renewing</div>
                            <div style="font-size: 1.5rem; font-weight: 700; color: #f59e0b;" id="summary-old">0</div>
                        </div>
                    </div>
                    <div id="warning-section" class="hidden" style="margin-top: var(--space-3); padding: var(--space-3); background: #fef3c7; border-radius: var(--radius-md);">
                        <strong style="color: #92400e;">⚠️ Warnings:</strong>
                        <ul id="warning-list" style="margin: var(--space-2) 0 0 var(--space-4); color: #92400e;"></ul>
                    </div>
                </div>

                <div style="text-align: center; margin-top: var(--space-8);">
                    <label style="display: flex; align-items: center; justify-content: center; gap: var(--space-2); margin-bottom: var(--space-4);">
                        <input type="checkbox" id="terms-checkbox" required>
                        I agree to the terms and conditions and certify that all information provided is accurate
                    </label>
                    <button type="button" class="btn btn-primary btn-lg" id="proceed-to-payment-btn" style="min-width: 200px;" disabled>
                        Proceed to Payment Summary
                    </button>
                </div>
            </div>
        </div>

        <!-- Step 3: Payment Summary -->
        <div id="payment-summary-step" class="hidden">
            <div class="card">
                <h3 style="text-align: center; margin-bottom: var(--space-6); color: var(--primary);">💳 Payment Summary</h3>
                
                <div style="background: #f8fafc; border-radius: var(--radius-lg); padding: var(--space-6); margin-bottom: var(--space-6);">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--neutral-300);">
                                <th style="text-align: left; padding: var(--space-3); color: var(--neutral-700); font-weight: 600;">Description</th>
                                <th style="text-align: right; padding: var(--space-3); color: var(--neutral-700); font-weight: 600;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding: var(--space-3); color: var(--neutral-600);">Member Count</td>
                                <td style="text-align: right; padding: var(--space-3); font-weight: 600;" id="pay-member-count">-</td>
                            </tr>
                            <tr>
                                <td style="padding: var(--space-3); color: var(--neutral-600);">New Members</td>
                                <td style="text-align: right; padding: var(--space-3); color: #10b981; font-weight: 600;" id="pay-new">-</td>
                            </tr>
                            <tr>
                                <td style="padding: var(--space-3); color: var(--neutral-600);">Old/Renewing Members</td>
                                <td style="text-align: right; padding: var(--space-3); color: #f59e0b; font-weight: 600;" id="pay-old">-</td>
                            </tr>
                            <tr style="border-top: 2px solid var(--neutral-300);">
                                <td style="padding: var(--space-3); font-weight: 600; color: var(--neutral-700);">Variable Affiliation Fee</td>
                                <td style="text-align: right; padding: var(--space-3); font-weight: 700; color: var(--primary);" id="pay-affiliation-fee">₱0.00</td>
                            </tr>
                            <tr>
                                <td style="padding: var(--space-3); color: var(--neutral-600);">Operational & Activity Fee</td>
                                <td style="text-align: right; padding: var(--space-3); font-weight: 600;" id="pay-operational-fee">₱800.00</td>
                            </tr>
                            <tr style="border-top: 3px solid var(--primary); background: #f0f9ff;">
                                <td style="padding: var(--space-4); font-size: 1.25rem; font-weight: 700; color: var(--primary);">Total Amount Due</td>
                                <td style="text-align: right; padding: var(--space-4); font-size: 1.5rem; font-weight: 800; color: var(--primary);" id="pay-total-fee">₱0.00</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-info" style="margin-bottom: var(--space-6);">
                    <strong>📋 Fee Breakdown (2025 Constitution & By-Laws):</strong>
                    <ul style="margin: var(--space-2) 0 0 var(--space-4);">
                        <li><strong>Institutional Affiliation (BR No. 021-2024):</strong> 1–50: ₱1,500 | 51–100: ₱2,000 | 101–150: ₱2,500 | 151+: ₱3,000</li>
                        <li><strong>Operational & Activity Fee:</strong> ₱800.00 (Fixed per organization)</li>
                        <li><strong>Individual Student Dues (Art. IV Sec. 2):</strong> Returning: ₱200.00 | New: ₱250.00 | Honorary: ₱300.00</li>
                    </ul>
                </div>

                <div style="display: flex; gap: var(--space-3); justify-content: center;">
                    <button type="button" class="btn" id="back-to-form-btn" style="background: var(--neutral-200); color: var(--neutral-700);">
                        ← Back to Form
                    </button>
                    <button type="button" class="btn btn-primary btn-lg" id="submit-application-btn" style="min-width: 200px;">
                        Submit Application
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Load XLSX library for parsing -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="<?php echo PUBLIC_URL; ?>/js/member-directory-parser.js"></script>
    <script>
        const API_URL = '<?php echo API_URL; ?>';
        const BASE_URL = '<?php echo BASE_URL; ?>';
        let verifiedEmail = '';
        let verificationToken = '';
        let countdownInterval;
        let isResubmit = <?php echo !empty($existingApplication) ? 'true' : 'false'; ?>;
        let resubmitId = '<?php echo htmlspecialchars($resubmitId); ?>';
        let currentEmail = '';

        // Safe URL builders for verification APIs
        window.getSendVerificationCodeApiUrl = function() {
            if (typeof API_URL !== 'undefined' && API_URL) {
                return API_URL.replace(/\/+$/, '') + '/send-verification-code.php';
            }
            const origin = window.location.origin;
            if (window.location.pathname.includes('/public/')) {
                const prefix = window.location.pathname.substring(0, window.location.pathname.indexOf('/public/'));
                return origin + prefix + '/public/api/send-verification-code.php';
            }
            const prefix = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
            return origin + (prefix ? prefix : '') + '/public/api/send-verification-code.php';
        };

        window.getVerifyCodeApiUrl = function() {
            if (typeof API_URL !== 'undefined' && API_URL) {
                return API_URL.replace(/\/+$/, '') + '/verify-code.php';
            }
            const origin = window.location.origin;
            if (window.location.pathname.includes('/public/')) {
                const prefix = window.location.pathname.substring(0, window.location.pathname.indexOf('/public/'));
                return origin + prefix + '/public/api/verify-code.php';
            }
            const prefix = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
            return origin + (prefix ? prefix : '') + '/public/api/verify-code.php';
        };

        window.getSubmitApiUrl = function() {
            if (typeof API_URL !== 'undefined' && API_URL) {
                return API_URL.replace(/\/+$/, '') + '/submit-affiliation.php';
            }
            const origin = window.location.origin;
            if (window.location.pathname.includes('/public/')) {
                const prefix = window.location.pathname.substring(0, window.location.pathname.indexOf('/public/'));
                return origin + prefix + '/public/api/submit-affiliation.php';
            }
            const prefix = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
            return origin + (prefix ? prefix : '') + '/public/api/submit-affiliation.php';
        };

        const SUBMIT_API_URL = window.getSubmitApiUrl();

        // Email validation helper
        function validateEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        // Error and Success notification helpers
        function showError(message) {
            const errorEl = document.getElementById('verification-error');
            const successEl = document.getElementById('verification-success');
            if (errorEl) {
                errorEl.innerHTML = message;
                errorEl.classList.remove('hidden');
            }
            if (successEl) {
                successEl.classList.add('hidden');
            }
        }

        function showSuccess(message) {
            const errorEl = document.getElementById('verification-error');
            const successEl = document.getElementById('verification-success');
            if (successEl) {
                successEl.textContent = message;
                successEl.classList.remove('hidden');
            }
            if (errorEl) {
                errorEl.classList.add('hidden');
            }
        }

        // 60-Second Cooldown Timer for Resend Button
        let resendCooldownInterval = null;
        function startResendCooldown(seconds = 60) {
            const resendBtn = document.getElementById('resend-btn');
            if (!resendBtn) return;
            clearInterval(resendCooldownInterval);

            let remaining = seconds;
            resendBtn.disabled = true;
            resendBtn.innerHTML = `<i class="fas fa-hourglass-half me-1"></i> Resend Code (${remaining}s)`;

            resendCooldownInterval = setInterval(() => {
                remaining--;
                if (remaining <= 0) {
                    clearInterval(resendCooldownInterval);
                    resendBtn.disabled = false;
                    resendBtn.innerHTML = '<i class="fas fa-redo me-1"></i> Resend Verification Code';
                } else {
                    resendBtn.innerHTML = `<i class="fas fa-hourglass-half me-1"></i> Resend Code (${remaining}s)`;
                }
            }, 1000);
        }

        // Global handler for sending verification code (callable via onclick or addEventListener)
        window.handleSendVerificationCode = async function(btn) {
            console.log('[Verification] handleSendVerificationCode called');
            const sendBtn = btn || document.getElementById('send-code-btn');
            const emailInput = document.getElementById('verification-email');
            if (!emailInput) {
                console.error('[Verification] verification-email input not found');
                return;
            }

            const email = emailInput.value.trim();

            if (!email) {
                showError('Please enter your email address');
                emailInput.focus();
                return;
            }

            if (!validateEmail(email)) {
                showError('Please enter a valid email address');
                emailInput.focus();
                return;
            }

            if (sendBtn) {
                sendBtn.disabled = true;
                sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Sending Verification Code...';
            }

            // Hide old messages while requesting
            const errorEl = document.getElementById('verification-error');
            if (errorEl) errorEl.classList.add('hidden');

            try {
                const targetUrl = window.getSendVerificationCodeApiUrl();
                console.log('[Verification] Posting send-verification-code to:', targetUrl);
                const response = await fetch(targetUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        email: email
                    })
                });

                const rawText = await response.text();
                let result;
                try {
                    result = JSON.parse(rawText);
                } catch (parseErr) {
                    console.error('[Verification] Non-JSON server response:', rawText);
                    throw new Error('Server returned an unexpected response format.');
                }

                if (response.ok && result.success) {
                    currentEmail = email;
                    const sentEmailSpan = document.getElementById('sent-email');
                    if (sentEmailSpan) sentEmailSpan.textContent = email;

                    document.getElementById('email-form')?.classList.add('hidden');
                    document.getElementById('code-form')?.classList.remove('hidden');
                    
                    showSuccess(result.message || 'Verification code sent to your email! Please check your inbox and spam folder.');
                    startCountdown();
                    startResendCooldown(60);
                    setupCodeInputs();
                } else {
                    const errorMsg = result.message || result.error || 'Failed to send verification code. Please try again.';
                    showError(errorMsg);

                    if (response.status === 429 && result.retry_after) {
                        startResendCooldown(result.retry_after);
                    }

                    if (sendBtn) {
                        sendBtn.disabled = false;
                        sendBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Send Verification Code';
                    }
                }
            } catch (error) {
                console.error('[Verification] Send code error:', error);
                showError(error.message || 'Cannot connect to the server. Please check your internet connection.');
                if (sendBtn) {
                    sendBtn.disabled = false;
                    sendBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Send Verification Code';
                }
            }
        };

        // Attach event listener as backup to inline onclick
        document.getElementById('send-code-btn')?.addEventListener('click', function() {
            window.handleSendVerificationCode(this);
        });

        // Initialize Member Directory Parser defensively
        let parser = null;
        try {
            if (typeof MemberDirectoryParser !== 'undefined') {
                parser = new MemberDirectoryParser();
            }
        } catch (e) {
            console.warn('MemberDirectoryParser deferred:', e);
        }
        let memberDirectoryParsed = false;

        // If resubmitting, skip email verification and set verified email
        if (isResubmit) {
            verifiedEmail = '<?php echo htmlspecialchars($existingApplication['email'] ?? ''); ?>';
            document.getElementById('step1')?.classList.remove('active');
            document.getElementById('step1')?.classList.add('completed');
            document.getElementById('step2')?.classList.add('active');
            document.getElementById('email-verification-step')?.classList.add('hidden');
            document.getElementById('application-form-step')?.classList.remove('hidden');
            console.log('Resubmit mode - document upload section rendered server-side');
        }

        // Setup both single 6-digit input and 6-box inputs
        function setupCodeInputs() {
            const singleInput = document.getElementById('verification-code-input');
            const boxInputs = document.querySelectorAll('#code-form .code-input');

            // Clear previous inputs
            if (singleInput) singleInput.value = '';
            boxInputs.forEach(input => input.value = '');

            if (singleInput) {
                singleInput.focus();

                singleInput.addEventListener('input', function(e) {
                    const cleaned = this.value.replace(/\D/g, '').slice(0, 6);
                    this.value = cleaned;

                    // Sync with individual boxes
                    const digits = cleaned.split('');
                    boxInputs.forEach((box, i) => {
                        box.value = digits[i] || '';
                    });

                    if (cleaned.length === 6) {
                        document.getElementById('verify-code-btn')?.click();
                    }
                });

                singleInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        document.getElementById('verify-code-btn')?.click();
                    }
                });
            }

            boxInputs.forEach((input, index) => {
                input.addEventListener('input', function(e) {
                    const val = e.target.value;
                    if (val.length > 1) {
                        const cleanDigits = val.replace(/\D/g, '').split('');
                        cleanDigits.forEach((digit, i) => {
                            if (boxInputs[i]) boxInputs[i].value = digit;
                        });
                        const fullCode = Array.from(boxInputs).map(i => i.value.trim()).join('').slice(0, 6);
                        if (singleInput) singleInput.value = fullCode;
                        const nextIndex = Math.min(cleanDigits.length, boxInputs.length - 1);
                        boxInputs[nextIndex]?.focus();
                        if (fullCode.length >= 6) {
                            document.getElementById('verify-code-btn')?.click();
                        }
                        return;
                    }

                    const fullCode = Array.from(boxInputs).map(i => i.value.trim()).join('');
                    if (singleInput) singleInput.value = fullCode;

                    if (val && index < boxInputs.length - 1) {
                        boxInputs[index + 1].focus();
                    }

                    if (fullCode.length === 6) {
                        document.getElementById('verify-code-btn')?.click();
                    }
                });

                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !e.target.value && index > 0) {
                        boxInputs[index - 1].focus();
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        document.getElementById('verify-code-btn')?.click();
                    }
                });

                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pastedData = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
                    const digits = pastedData.split('');

                    digits.forEach((digit, i) => {
                        if (i < boxInputs.length) {
                            boxInputs[i].value = digit;
                        }
                    });

                    const fullCode = Array.from(boxInputs).map(i => i.value.trim()).join('').slice(0, 6);
                    if (singleInput) singleInput.value = fullCode;

                    if (digits.length >= boxInputs.length) {
                        boxInputs[boxInputs.length - 1].focus();
                        document.getElementById('verify-code-btn')?.click();
                    } else if (digits.length > 0 && boxInputs[digits.length]) {
                        boxInputs[digits.length].focus();
                    }
                });
            });
        }

        // Verify code functionality
        window.handleVerifyCode = async function(btn) {
            const verifyBtn = btn || document.getElementById('verify-code-btn');
            const singleInput = document.getElementById('verification-code-input');
            let code = singleInput ? singleInput.value.trim() : '';

            if (!code || code.length < 6) {
                const boxInputs = document.querySelectorAll('#code-form .code-input');
                code = Array.from(boxInputs).map(input => input.value.trim()).join('');
            }

            if (code.length !== 6) {
                showError('Please enter the complete 6-digit verification code');
                if (singleInput) singleInput.focus();
                return;
            }

            const email = (document.getElementById('verification-email')?.value || currentEmail || '').trim();
            if (!email) {
                showError('Email address is missing. Please restart email verification.');
                return;
            }

            if (verifyBtn) {
                verifyBtn.disabled = true;
                verifyBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Verifying Code...';
            }

            try {
                const targetUrl = window.getVerifyCodeApiUrl();
                console.log('[Verification] Posting verify-code to:', targetUrl);
                const response = await fetch(targetUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        email: email,
                        code: code
                    })
                });

                const rawText = await response.text();
                let result;
                try {
                    result = JSON.parse(rawText);
                } catch (parseErr) {
                    console.error('[Verification] Non-JSON server response:', rawText);
                    throw new Error('Server returned an unexpected response format.');
                }

                if (response.ok && result.success) {
                    verifiedEmail = email;
                    const verifiedFlag = document.getElementById('email-verified-flag');
                    if (verifiedFlag) verifiedFlag.value = 'true';

                    showSuccess(result.message || 'Email verified successfully! Proceeding to application form...');

                    setTimeout(() => {
                        moveToStep2();
                    }, 800);
                } else {
                    const errorMsg = result.message || result.error || 'Invalid or expired verification code';
                    showError(errorMsg);

                    if (result.locked) {
                        if (verifyBtn) {
                            verifyBtn.disabled = true;
                            verifyBtn.innerHTML = '<i class="fas fa-lock me-2"></i> Code Locked';
                        }
                    } else {
                        if (verifyBtn) {
                            verifyBtn.disabled = false;
                            verifyBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Verify Code';
                        }
                    }
                }
            } catch (error) {
                console.error('[Verification] Verify code error:', error);
                showError(error.message || 'Cannot connect to the server. Please check your internet connection.');
                if (verifyBtn) {
                    verifyBtn.disabled = false;
                    verifyBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Verify Code';
                }
            }
        };

        document.getElementById('verify-code-btn')?.addEventListener('click', function() {
            window.handleVerifyCode(this);
        });

        // Change email button handler
        window.handleChangeEmail = function(btn) {
            document.getElementById('code-form')?.classList.add('hidden');
            document.getElementById('email-form')?.classList.remove('hidden');
            const sendBtn = document.getElementById('send-code-btn');
            if (sendBtn) {
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Send Verification Code';
            }
            const emailInput = document.getElementById('verification-email');
            if (emailInput) {
                emailInput.focus();
                emailInput.select();
            }
            clearInterval(countdownInterval);
            clearInterval(resendCooldownInterval);
            const errorEl = document.getElementById('verification-error');
            if (errorEl) errorEl.classList.add('hidden');
            const successEl = document.getElementById('verification-success');
            if (successEl) successEl.classList.add('hidden');
        };

        document.getElementById('change-email-btn')?.addEventListener('click', function(e) {
            e.preventDefault();
            window.handleChangeEmail(this);
        });

        // Resend code functionality
        window.handleResendCode = async function(btn) {
            const resendButton = btn || document.getElementById('resend-btn');
            const email = currentEmail || document.getElementById('verification-email')?.value.trim();

            if (!email) {
                showError('Email not found. Please re-enter your email address.');
                return;
            }

            if (resendButton) {
                resendButton.disabled = true;
                resendButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending...';
            }

            try {
                const targetUrl = window.getSendVerificationCodeApiUrl();
                const response = await fetch(targetUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        email: email
                    })
                });

                const rawText = await response.text();
                let result;
                try {
                    result = JSON.parse(rawText);
                } catch (parseErr) {
                    throw new Error('Server returned an unexpected response format.');
                }

                if (response.ok && result.success) {
                    showSuccess(result.message || 'New verification code sent! Check your inbox.');
                    startCountdown();
                    startResendCooldown(60);
                    setupCodeInputs();

                    const verifyBtn = document.getElementById('verify-code-btn');
                    if (verifyBtn) {
                        verifyBtn.disabled = false;
                        verifyBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Verify Code';
                    }
                } else {
                    const errorMsg = result.message || result.error || 'Failed to resend verification code';
                    showError(errorMsg);

                    if (response.status === 429 && result.retry_after) {
                        startResendCooldown(result.retry_after);
                    } else if (resendButton) {
                        resendButton.disabled = false;
                        resendButton.innerHTML = '<i class="fas fa-redo me-1"></i> Resend Verification Code';
                    }
                }
            } catch (error) {
                console.error('[Verification] Resend error:', error);
                showError('Network error: ' + error.message);
                if (resendButton) {
                    resendButton.disabled = false;
                    resendButton.innerHTML = '<i class="fas fa-redo me-1"></i> Resend Verification Code';
                }
            }
        };

        document.getElementById('resend-btn')?.addEventListener('click', function(e) {
            e.preventDefault();
            window.handleResendCode(this);
        });

        function startCountdown() {
            let seconds = 600; // 10 minutes
            const countdownEl = document.getElementById('countdown');
            const timerEl = document.getElementById('timer');

            if (countdownEl) countdownEl.classList.remove('hidden');

            clearInterval(countdownInterval);

            countdownInterval = setInterval(() => {
                seconds--;
                const minutes = Math.floor(seconds / 60);
                const remainingSeconds = seconds % 60;
                if (timerEl) {
                    timerEl.textContent = `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
                }

                if (seconds <= 0) {
                    clearInterval(countdownInterval);
                    if (countdownEl) {
                        countdownEl.textContent = 'Verification code has expired. Please request a new code.';
                    }
                    const verifyBtn = document.getElementById('verify-code-btn');
                    if (verifyBtn) {
                        verifyBtn.disabled = true;
                        verifyBtn.innerHTML = '<i class="fas fa-clock me-2"></i> Code Expired';
                    }
                }
            }, 1000);
        }

        function moveToStep2() {
            // Update step indicator
            document.getElementById('step1').classList.remove('active');
            document.getElementById('step1').classList.add('completed');
            document.getElementById('step2').classList.add('active');
            
            // Hide verification step, show application form
            document.getElementById('email-verification-step').classList.add('hidden');
            document.getElementById('application-form-step').classList.remove('hidden');
            
            // Set verified email in contact form
            document.getElementById('contact-email').value = verifiedEmail;
            
            // Setup document upload section
            setupDocumentUpload();
        }

        function setupDocumentUpload() {
            const uploadSection = document.getElementById('document-upload-section');
            const documents = [
                { key: 'letter_of_intent', name: 'Letter of Intent (PDF)', accept: '.pdf,application/pdf' },
                { key: 'endorsement_letter', name: 'Endorsement Letter (PDF)', accept: '.pdf,application/pdf' },
                { key: 'constitution_by_laws', name: 'Constitution and By-Laws (PDF)', accept: '.pdf,application/pdf' },
                { key: 'officers_cvs', name: 'List of Officers with CVs (PDF)', accept: '.pdf,application/pdf' },
                { key: 'organizational_chart', name: 'Organizational Chart (PDF)', accept: '.pdf,application/pdf' },
                { key: 'member_directory', name: 'Member Directory (Excel / CSV)', accept: '.xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv' }
            ];

            const existingDocs = <?php echo !empty($existingApplication['documents']) ? json_encode(json_decode($existingApplication['documents'], true) ?: []) : '[]'; ?>;

            console.log('setupDocumentUpload called, isResubmit:', isResubmit, 'existingDocs:', existingDocs);

            let html = '<div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(300px, 1fr));gap:24px">';

            documents.forEach(doc => {
                const hasExisting = existingDocs[doc.key] && isResubmit;
                const isRequired = !isResubmit ? '*' : '';
                const existingFileName = hasExisting ? (existingDocs[doc.key].name || 'uploaded file') : '';

                console.log(`Document ${doc.key}: hasExisting=${hasExisting}, existingFileName=${existingFileName}`);

                html += `
                    <div class="form-group">
                        <label>${doc.name} ${isRequired ? '<span style="color:#DC3545">*</span>' : ''}</label>
                        ${hasExisting ? `
                            <div style="background:#e8f5e9;border:1px solid #28a745;border-radius:4px;padding:8px;margin-bottom:8px">
                                <div style="color:#28a745;font-size:13px;font-weight:600">&#10003; Current: ${existingFileName}</div>
                            </div>
                        ` : ''}
                        <div style="display:flex;gap:8px;align-items:center">
                            <input type="file" class="form-control" name="${doc.key}" accept="${doc.accept}" ${isRequired ? 'required' : ''} id="file-${doc.key}">
                            ${hasExisting ? `<button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('file-${doc.key}').click()" style="white-space:nowrap">Replace</button>` : ''}
                        </div>
                        <small class="form-text text-muted">Accepted formats: ${doc.accept}</small>
                    </div>
                `;
            });

            html += '</div>';
            uploadSection.innerHTML = html;
            uploadSection.style.display = 'block';

            console.log('Document upload section set up, display:', uploadSection.style.display);

            // Add live validation listeners
            ['letter_of_intent', 'endorsement_letter', 'constitution_by_laws', 'officers_cvs', 'organizational_chart'].forEach(k => {
                const el = document.getElementById(`file-${k}`);
                if (el) {
                    el.addEventListener('change', function() {
                        if (this.files && this.files[0]) {
                            const fn = this.files[0].name.toLowerCase();
                            if (!fn.endsWith('.pdf') && this.files[0].type !== 'application/pdf') {
                                alert('⚠️ Invalid File Type!\n\nOnly official PDF (.pdf) documents are accepted for this field.');
                                this.value = '';
                            }
                        }
                    });
                }
            });

            // Add Member Directory file listener
            const memberDirectoryInput = document.getElementById('file-member_directory');
            if (memberDirectoryInput) {
                memberDirectoryInput.addEventListener('change', handleMemberDirectoryUpload);
            }
        }

        // Handle Member Directory Upload and Parsing
        async function handleMemberDirectoryUpload(event) {
            const file = event.target.files[0];
            if (!file) return;

            const summaryCard = document.getElementById('member-summary-card');
            const proceedBtn = document.getElementById('proceed-to-payment-btn');

            // Show processing state
            summaryCard.innerHTML = '<div style="text-align:center;padding:var(--space-4);"><div class="spinner-border" style="color:var(--primary);"></div><p style="margin-top:var(--space-2);color:var(--neutral-600);">Processing file...</p></div>';
            summaryCard.classList.remove('hidden');
            proceedBtn.disabled = true;

            try {
                const result = await parser.parseFile(file);
                const fees = parser.calculateFees(result.total);

                // Update summary card
                document.getElementById('summary-total').textContent = result.total;
                document.getElementById('summary-new').textContent = result.new;
                document.getElementById('summary-old').textContent = result.old;

                // Show warnings if any
                if (result.warnings.length > 0) {
                    const warningSection = document.getElementById('warning-section');
                    const warningList = document.getElementById('warning-list');
                    warningList.innerHTML = result.warnings.map(w => `<li>${w}</li>`).join('');
                    warningSection.classList.remove('hidden');
                }

                // Restore summary card HTML
                summaryCard.innerHTML = `
                    <h4 style="color: var(--primary); margin-bottom: var(--space-3);">📊 Member Directory Summary</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: var(--space-3);">
                        <div>
                            <div style="font-size: 0.875rem; color: var(--neutral-600);">Total Members</div>
                            <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary);" id="summary-total">${result.total}</div>
                        </div>
                        <div>
                            <div style="font-size: 0.875rem; color: var(--neutral-600);">New Members</div>
                            <div style="font-size: 1.5rem; font-weight: 700; color: #10b981;" id="summary-new">${result.new}</div>
                        </div>
                        <div>
                            <div style="font-size: 0.875rem; color: var(--neutral-600);">Old/Renewing</div>
                            <div style="font-size: 1.5rem; font-weight: 700; color: #f59e0b;" id="summary-old">${result.old}</div>
                        </div>
                    </div>
                    ${result.warnings.length > 0 ? `
                        <div id="warning-section" style="margin-top: var(--space-3); padding: var(--space-3); background: #fef3c7; border-radius: var(--radius-md);">
                            <strong style="color: #92400e;">⚠️ Warnings:</strong>
                            <ul id="warning-list" style="margin: var(--space-2) 0 0 var(--space-4); color: #92400e;">
                                ${result.warnings.map(w => `<li>${w}</li>`).join('')}
                            </ul>
                        </div>
                    ` : ''}
                `;

                memberDirectoryParsed = true;
                updateProceedButton();

                showSuccess('Member directory parsed successfully!');
            } catch (error) {
                summaryCard.classList.add('hidden');
                showError(error.message);
                memberDirectoryParsed = false;
                proceedBtn.disabled = true;
            }
        }

        // Update Proceed Button State
        function updateProceedButton() {
            const termsChecked = document.getElementById('terms-checkbox').checked;
            const proceedBtn = document.getElementById('proceed-to-payment-btn');
            proceedBtn.disabled = !(memberDirectoryParsed && termsChecked);
        }

        // Terms checkbox validation
        document.getElementById('terms-checkbox').addEventListener('change', updateProceedButton);

        // Proceed to Payment Summary
        document.getElementById('proceed-to-payment-btn').addEventListener('click', function() {
            if (!memberDirectoryParsed) {
                showError('Please upload and parse the Member Directory first.');
                return;
            }

            const parsedData = parser.getParsedData();
            const feeData = parser.getFeeCalculation();

            // Update Payment Summary
            document.getElementById('pay-member-count').textContent = parsedData.total;
            document.getElementById('pay-new').textContent = parsedData.new;
            document.getElementById('pay-old').textContent = parsedData.old;
            document.getElementById('pay-affiliation-fee').textContent = `₱${feeData.affiliationFee.toLocaleString()}.00`;
            document.getElementById('pay-total-fee').textContent = `₱${feeData.totalFee.toLocaleString()}.00`;

            // Move to Step 3
            document.getElementById('step2').classList.remove('active');
            document.getElementById('step2').classList.add('completed');
            document.getElementById('step3').classList.add('active');
            document.getElementById('application-form-step').classList.add('hidden');
            document.getElementById('payment-summary-step').classList.remove('hidden');
        });

        // Back to Form Button
        document.getElementById('back-to-form-btn').addEventListener('click', function() {
            document.getElementById('step3').classList.remove('active');
            document.getElementById('step2').classList.remove('completed');
            document.getElementById('step2').classList.add('active');
            document.getElementById('payment-summary-step').classList.add('hidden');
            document.getElementById('application-form-step').classList.remove('hidden');
        });

        // Test if resend button exists on page load
        document.addEventListener('DOMContentLoaded', function() {
            const resendBtn = document.getElementById('resend-btn');
            console.log('Page loaded - Resend button found:', !!resendBtn);
            if (resendBtn) {
                console.log('Resend button disabled:', resendBtn.disabled);
                console.log('Resend button text:', resendBtn.textContent);
            }

            // Initialize document upload section if not resubmitting
            if (!isResubmit) {
                console.log('Initializing document upload section...');
                setupDocumentUpload();
            }
        });

        // Submit application
        document.getElementById('submit-application-btn').addEventListener('click', async function() {
            console.log('SUBMIT BUTTON CLICKED!');
            
            if (!validateApplicationForm()) {
                console.log('Form validation failed');
                return;
            }
            
            if (!memberDirectoryParsed) {
                showError('Member directory must be uploaded and parsed.');
                return;
            }
            
            console.log('Form validation passed, preparing submission...');
            
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Uploading requirements & submitting...';
            
            const formData = new FormData();
            console.log('Creating FormData...');

            // Add form fields
            formData.append('contact_email', verifiedEmail);
            formData.append('email_verified', 'true');
            formData.append('institution_name', document.getElementById('inst-name').value);
            formData.append('institution_address', document.getElementById('inst-address').value);
            formData.append('contact_person', document.getElementById('contact-name').value);
            formData.append('contact_position', document.getElementById('contact-position').value);
            formData.append('contact_phone', document.getElementById('contact-phone').value);
            formData.append('terms', 'accepted');

            // Add member directory data
            const parsedData = parser.getParsedData();
            const feeData = parser.getFeeCalculation();
            formData.append('member_count_total', parsedData.total);
            formData.append('member_count_new', parsedData.new);
            formData.append('member_count_old', parsedData.old);
            formData.append('affiliation_fee', feeData.affiliationFee);
            formData.append('operational_fee', feeData.operationalFee);
            formData.append('total_fee', feeData.totalFee);

            if (verifiedEmail) {
                formData.set('contact_email', verifiedEmail);
                formData.set('email_verified', 'true');
            } else {
                const emailInput = document.getElementById('contact-email') || document.getElementById('verification-email');
                if (emailInput && emailInput.value) {
                    formData.set('contact_email', emailInput.value.trim());
                    formData.set('email_verified', 'true');
                }
            }

            // Add resubmit ID if resubmitting
            if (isResubmit && resubmitId) {
                formData.append('resubmit_id', resubmitId);
                console.log('Adding resubmit ID:', resubmitId);
            }
            
            // Add documents
            const documentInputs = document.querySelectorAll('#document-upload-section input[type="file"]');
            console.log(`Found ${documentInputs.length} document inputs for submission`);
            
            documentInputs.forEach((input, index) => {
                if (input.files[0]) {
                    console.log(`Adding document ${index}: ${input.name} - ${input.files[0].name}`);
                    formData.append(input.name, input.files[0]);
                } else {
                    console.log(`No file for input ${index}: ${input.name}`);
                }
            });

            // Log FormData contents (for debugging)
            console.log('FormData contents:');
            for (let [key, value] of formData.entries()) {
                if (value instanceof File) {
                    console.log(`${key}: ${value.name} (${value.size} bytes)`);
                } else {
                    console.log(`${key}: ${value}`);
                }
            }
            
            try {
                const targetUrl = (typeof window.getSubmitApiUrl === 'function') ? window.getSubmitApiUrl() : (API_URL + '/submit-affiliation.php');
                console.log('Sending submission request to ' + targetUrl);
                const response = await fetch(targetUrl, {
                    method: 'POST',
                    body: formData
                });
                
                console.log('Response status:', response.status);
                console.log('Response ok:', response.ok);
                
                const rawText = await response.text();
                let result;
                try {
                    result = JSON.parse(rawText);
                } catch (jsonErr) {
                    console.error('Submission non-JSON response:', rawText);
                    throw new Error('Server returned an unexpected response format. Please try again.');
                }
                console.log('Response result:', result);
                
                if (result.success) {
                    showSuccess(result.message);
                    setTimeout(() => {
                        window.location.href = '/';
                    }, 3000);
                } else {
                    if (result.resubmit_available && result.application_id) {
                        showResubmitModal(result.error, result.application_id);
                    } else {
                        showError(result.error || result.message || 'Failed to submit application');
                    }
                    this.disabled = false;
                    this.innerHTML = 'Submit Application';
                }
            } catch (error) {
                console.error('Submission error:', error);
                showError('Network error: ' + error.message);
                this.disabled = false;
                this.innerHTML = 'Submit Application';
            }
        });

        function validateApplicationForm() {
            console.log('Validating application form...');
            
            const fields = ['inst-name', 'inst-address', 'contact-name', 'contact-position', 'contact-phone'];
            const fieldNames = {
                'inst-name': 'Institution Name',
                'inst-address': 'Address',
                'contact-name': 'Contact Person Name',
                'contact-position': 'Position',
                'contact-phone': 'Phone'
            };

            for (const fieldId of fields) {
                const field = document.getElementById(fieldId);
                if (!field || !field.value.trim()) {
                    console.log(`Field ${fieldId} is missing or empty`);
                    showError(`${fieldNames[fieldId]} is required`);
                    if (field) field.focus();
                    return false;
                }
            }

            // Check if verified email exists
            const emailVerifiedFlag = document.getElementById('email-verified-flag')?.value === 'true';
            if (!verifiedEmail || !emailVerifiedFlag) {
                console.log('No verified email found');
                showError('Email verification is required before you can proceed.');
                return false;
            }

            // Check if all documents are uploaded (only if not resubmitting)
            if (!isResubmit) {
                const documentInputs = document.querySelectorAll('#document-upload-section input[type="file"]');
                console.log(`Found ${documentInputs.length} document inputs`);
                
                if (documentInputs.length === 0) {
                    console.log('No document inputs found - setting up document upload section');
                    setupDocumentUpload();
                    // Try validation again after setup
                    setTimeout(() => validateApplicationForm(), 100);
                    return false;
                }
                
                for (const input of documentInputs) {
                    if (!input.files[0]) {
                        console.log(`Missing document for input: ${input.name}`);
                        const label = input.previousElementSibling?.textContent || input.name;
                        showError(`Please upload ${label.replace(' *', '')}`);
                        return false;
                    }
                }
            }

            console.log('Form validation passed');
            return true;
        }

        function validateEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        function showError(message) {
            const errorEl = document.getElementById('verification-error');
            const successEl = document.getElementById('verification-success');

            errorEl.innerHTML = message;
            errorEl.classList.remove('hidden');
            successEl.classList.add('hidden');

            setTimeout(() => {
                errorEl.classList.add('hidden');
            }, 8000);
        }

        function showSuccess(message) {
            const errorEl = document.getElementById('verification-error');
            const successEl = document.getElementById('verification-success');
            
            successEl.textContent = message;
            successEl.classList.remove('hidden');
            errorEl.classList.add('hidden');
            
            setTimeout(() => {
                successEl.classList.add('hidden');
            }, 5000);
        }

        function showResubmitModal(message, applicationId) {
            // Create modal if it doesn't exist
            let modal = document.getElementById('resubmitModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'resubmitModal';
                modal.style.cssText = `
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.6);
                    backdrop-filter: blur(4px);
                    z-index: 10000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                `;
                document.body.appendChild(modal);
            }

            modal.innerHTML = `
                <div style="background: white; padding: 36px; border-radius: 20px; max-width: 500px; width: 90%; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); animation: slideIn 0.3s ease-out;">
                    <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px;">
                        <div style="width: 56px; height: 56px; background: #fef3c7; border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        </div>
                        <div>
                            <h3 style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0; letter-spacing: -0.01em;">Email Already Registered</h3>
                        </div>
                    </div>
                    <p style="color: #64748b; font-size: 0.95rem; line-height: 1.6; margin-bottom: 28px;">${message}</p>
                    <div style="display: flex; gap: 12px; flex-direction: column;">
                        <button onclick="window.location.href='?resubmit=${applicationId}'" style="background: linear-gradient(135deg, #0A2F6C 0%, #1e4a8a 100%); color: white; padding: 14px 24px; border-radius: 12px; font-size: 0.95rem; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; text-align: center;">
                            Resubmit Application
                        </button>
                        <button onclick="closeResubmitModal()" style="background: white; color: #64748b; padding: 14px 24px; border-radius: 12px; font-size: 0.95rem; font-weight: 600; cursor: pointer; border: 2px solid #e2e8f0; transition: all 0.2s;">
                            Cancel
                        </button>
                    </div>
                </div>
            `;

            modal.style.display = 'flex';

            // Add close on outside click
            modal.onclick = function(e) {
                if (e.target === modal) {
                    closeResubmitModal();
                }
            };
        }

        function closeResubmitModal() {
            const modal = document.getElementById('resubmitModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        // Add animation keyframes
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateY(-20px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
