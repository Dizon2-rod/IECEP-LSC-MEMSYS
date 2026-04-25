<?php
// Apply Page
session_start();

// Allow direct access to apply.php for resubmission even if logged in
// Bypass any authentication redirects
if (isset($_GET['resubmit'])) {
    // Don't redirect, allow access for resubmission
    error_log("Resubmit mode detected - bypassing auth checks");
} elseif (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // If not resubmitting and logged in, redirect to dashboard
    $role = $_SESSION['user']['role'] ?? '';
    $redirectMap = [
        'eb_president' => '/IECEP-LSC-MEMSYS/public/portal/super-admin/dashboard.php',
        'admin' => '/IECEP-LSC-MEMSYS/public/portal/admin/dashboard.php',
        'school_officer' => '/IECEP-LSC-MEMSYS/public/portal/school-officer/dashboard.php',
        'member' => '/IECEP-LSC-MEMSYS/public/portal/member/dashboard.php',
    ];
    $redirectUrl = $redirectMap[$role] ?? '/IECEP-LSC-MEMSYS/public/portal/member/dashboard.php';
    header('Location: ' . $redirectUrl);
    exit;
}

require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/lib/SupabaseClient.php';

$existingApplication = null;
$changeInstructions = '';
$resubmitId = $_GET['resubmit'] ?? '';

if (!empty($resubmitId)) {
    try {
        $config = require __DIR__ . '/../src/config/supabase.php';
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affiliation Application - IECEP-LSC MEMSYS</title>
    <link rel="stylesheet" href="/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
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
            color: #0A2F6C;
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
            border-color: #0A2F6C;
            background: #0A2F6C;
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
            border-color: #0A2F6C;
        }
        .hidden {
            display: none !important;
        }
        .countdown {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 8px;
        }
        .resend-btn {
            background: none;
            border: none;
            color: #0A2F6C;
            text-decoration: underline;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .resend-btn:disabled {
            color: #6c757d;
            text-decoration: none;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="/" class="navbar-brand"><span>IECEP</span>-LSC</a>
        <ul class="navbar-nav">
            <li><a href="/">Home</a></li>
        </ul>
    </nav>

    <div style="max-width:900px;margin:40px auto;padding:0 24px">
        <h2 style="text-align:center;margin-bottom:32px">Affiliation Application</h2>
        
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
        </div>

        <!-- Change Instructions Banner (for resubmission) -->
        <?php if (!empty($changeInstructions)): ?>
        <div class="alert alert-warning" style="margin-bottom:32px">
            <h4 style="margin-top:0">Changes Requested by Registration Committee</h4>
            <p style="white-space:pre-wrap;margin-bottom:0"><?php echo htmlspecialchars($changeInstructions); ?></p>
        </div>
        <?php endif; ?>

        <!-- Step 1: Email Verification -->
        <div id="email-verification-step" style="<?php echo !empty($existingApplication) ? 'display:none' : ''; ?>">
            <div class="card" style="padding:32px">
                <h3 style="text-align:center;margin-bottom:24px">Verify Your Email</h3>
                <p style="text-align:center;color:#6c757d;margin-bottom:32px">
                    Enter your email address to receive a verification code
                </p>
                
                <div id="email-form">
                    <div class="form-group" style="max-width:400px;margin:0 auto 24px">
                        <label for="verification-email">Gmail Address <span style="color:#DC3545">*</span></label>
                        <input type="email" class="form-control" id="verification-email" placeholder="your.email@gmail.com or your.email@institution.edu" required>
                        <small class="form-text text-muted">Please Use Your Gmail Address </small>
                    </div>
                    
                    <div style="text-align:center">
                        <button type="button" class="btn btn-primary btn-lg" id="send-code-btn" style="min-width:200px">
                            Send Verification Code
                        </button>
                    </div>
                </div>

                <div id="code-form" class="hidden">
                    <div style="text-align:center;margin-bottom:24px">
                        <p style="color:#28a745;font-weight:600">Verification code sent to <span id="sent-email"></span></p>
                        <p style="color:#6c757d;font-size:0.9rem">Please check your inbox and spam folder</p>
                    </div>
                    
                    <div class="verification-inputs">
                        <input type="text" maxlength="1" class="code-input" data-index="0">
                        <input type="text" maxlength="1" class="code-input" data-index="1">
                        <input type="text" maxlength="1" class="code-input" data-index="2">
                        <input type="text" maxlength="1" class="code-input" data-index="3">
                        <input type="text" maxlength="1" class="code-input" data-index="4">
                        <input type="text" maxlength="1" class="code-input" data-index="5">
                    </div>
                    
                    <div style="text-align:center">
                        <button type="button" class="btn btn-success btn-lg" id="verify-code-btn" style="min-width:200px">
                            Verify Code
                        </button>
                    </div>
                    
                    <div style="text-align:center;margin-top:24px">
                        <div class="countdown" id="countdown">Code expires in <span id="timer">10:00</span></div>
                        <button type="button" class="resend-btn" id="resend-btn" disabled>Resend Code</button>
                    </div>
                </div>

                <div id="verification-error" class="alert alert-danger hidden" style="margin-top:24px"></div>
                <div id="verification-success" class="alert alert-success hidden" style="margin-top:24px"></div>
            </div>
        </div>

        <!-- Step 2: Application Form -->
        <div id="application-form-step" class="hidden" style="<?php echo !empty($existingApplication) ? 'display:block' : ''; ?>">
            <div class="card" style="padding:32px;margin-bottom:24px">
                <h3>Institution Information</h3>
                <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(250px, 1fr));gap:24px">
                    <div class="form-group">
                        <label>Institution Name <span style="color:#DC3545">*</span></label>
                        <input type="text" class="form-control" id="inst-name" required value="<?php echo htmlspecialchars($existingApplication['institution_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Institution Type <span style="color:#DC3545">*</span></label>
                        <select class="form-control" id="inst-type" required>
                            <option>-- Select --</option>
                            <option>Public University</option>
                            <option>Private University</option>
                            <option>College</option>
                            <option>Technical Institution</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Address <span style="color:#DC3545">*</span></label>
                        <input type="text" class="form-control" id="inst-address" required value="<?php echo htmlspecialchars($existingApplication['address'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="card" style="padding:32px">
                <h3>Contact Information</h3>
                <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(250px, 1fr));gap:24px">
                    <div class="form-group">
                        <label>Contact Person Name <span style="color:#DC3545">*</span></label>
                        <input type="text" class="form-control" id="contact-name" required value="<?php echo htmlspecialchars($existingApplication['contact_person'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Position <span style="color:#DC3545">*</span></label>
                        <input type="text" class="form-control" id="contact-position" required value="<?php echo htmlspecialchars($existingApplication['contact_position'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Email <span style="color:#DC3545">*</span></label>
                        <input type="email" class="form-control" id="contact-email" readonly style="background:#f8f9fa" value="<?php echo htmlspecialchars($existingApplication['email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone <span style="color:#DC3545">*</span></label>
                        <input type="tel" class="form-control" id="contact-phone" required value="<?php echo htmlspecialchars($existingApplication['contact_phone'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Required Documents Section -->
                <div style="margin-top:32px">
                    <h4 style="margin-bottom:24px">Required Documents</h4>
                    <?php if (isResubmit): ?>
                    <div class="alert alert-info">
                        <strong>Note:</strong> Only upload the documents that need to be updated based on the Registration Committee's instructions. Documents you don't upload will keep their existing versions.
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <strong>Note:</strong> Please prepare the following documents before proceeding:
                        <ul style="margin-top:12px;margin-bottom:0">
                            <li>Letter of Intent (PDF, Word, or Image)</li>
                            <li>Endorsement Letter (PDF, Word, or Image)</li>
                            <li>Constitution and By-Laws (PDF or Word)</li>
                            <li>List of Officers with CVs (PDF or Word)</li>
                            <li>Organizational Chart (PDF, Word, or Image)</li>
                            <li>Member Directory (PDF, Word, Excel, or CSV)</li>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <div id="document-upload-section" style="border:1px solid #e9ecef;border-radius:8px;padding:16px">
                        <?php if (!empty($existingApplication)): ?>
                            <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:4px;padding:12px;margin-bottom:16px">
                                <strong>Resubmission Mode:</strong> You can upload new files to replace existing documents below.
                            </div>
                            <?php
                            $documents = [
                                'letter_of_intent' => ['name' => 'Letter of Intent', 'accept' => '.pdf,.doc,.docx,.jpg,.jpeg,.png'],
                                'endorsement_letter' => ['name' => 'Endorsement Letter', 'accept' => '.pdf,.doc,.docx,.jpg,.jpeg,.png'],
                                'constitution_by_laws' => ['name' => 'Constitution and By-Laws', 'accept' => '.pdf,.doc,.docx'],
                                'officers_cvs' => ['name' => 'List of Officers with CVs', 'accept' => '.pdf,.doc,.docx'],
                                'organizational_chart' => ['name' => 'Organizational Chart', 'accept' => '.pdf,.doc,.docx,.jpg,.jpeg,.png'],
                                'member_directory' => ['name' => 'Member Directory', 'accept' => '.pdf,.doc,.docx,.xls,.xlsx,.csv']
                            ];
                            $existingDocs = !empty($existingApplication['documents']) ? json_decode($existingApplication['documents'], true) : [];
                            error_log("Resubmit mode - Existing documents loaded: " . json_encode($existingDocs));
                            ?>
                            <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(300px, 1fr));gap:24px">
                                <?php foreach ($documents as $key => $doc): ?>
                                    <?php
                                    $hasExisting = isset($existingDocs[$key]) && !empty($existingDocs[$key]);
                                    $existingFileName = $hasExisting ? ($existingDocs[$key]['name'] ?? 'uploaded file') : '';
                                    ?>
                                    <div class="form-group">
                                        <label><?php echo htmlspecialchars($doc['name']); ?></label>
                                        <?php if ($hasExisting): ?>
                                            <div style="background:#e8f5e9;border:1px solid #28a745;border-radius:4px;padding:8px;margin-bottom:8px">
                                                <div style="color:#28a745;font-size:13px;font-weight:600">&#10003; Current: <?php echo htmlspecialchars($existingFileName); ?></div>
                                                <div style="color:#666;font-size:12px;margin-top:4px">Select a new file below to replace</div>
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" name="<?php echo $key; ?>" accept="<?php echo $doc['accept']; ?>" id="file-<?php echo $key; ?>" onchange="console.log('File selected for <?php echo $key; ?>:', this.files[0]?.name)" style="background:#fff;cursor:pointer">
                                        <small class="form-text text-muted">Accepted formats: <?php echo $doc['accept']; ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <!-- Documents will be added dynamically via JavaScript -->
                        <?php endif; ?>
                    </div>
                </div>

                <div style="text-align:center;margin-top:32px">
                    <label style="display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:16px">
                        <input type="checkbox" id="terms-checkbox" required>
                        I agree to the terms and conditions and certify that all information provided is accurate
                    </label>
                    <button type="button" class="btn btn-primary btn-lg" id="submit-application-btn" style="min-width:200px" disabled>
                        Submit Application
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="/js/app.js"></script>
    <script>
        let verifiedEmail = '';
        let verificationToken = '';
        let countdownInterval;
        let isResubmit = <?php echo !empty($existingApplication) ? 'true' : 'false'; ?>;
        let resubmitId = '<?php echo htmlspecialchars($resubmitId); ?>';
        let currentEmail = ''; // Store current email for resend

        // If resubmitting, skip email verification and set verified email
        if (isResubmit) {
            verifiedEmail = '<?php echo htmlspecialchars($existingApplication['email'] ?? ''); ?>';
            document.getElementById('step1').classList.remove('active');
            document.getElementById('step1').classList.add('completed');
            document.getElementById('step2').classList.add('active');
            document.getElementById('email-verification-step').classList.add('hidden');
            document.getElementById('application-form-step').classList.remove('hidden');
            // Document upload section is now rendered server-side for resubmission
            console.log('Resubmit mode - document upload section rendered server-side');
        }

        // Email verification functionality
        document.getElementById('send-code-btn').addEventListener('click', async function() {
            const email = document.getElementById('verification-email').value.trim();
            
            if (!email) {
                showError('Please enter your email address');
                return;
            }
            
            if (!validateEmail(email)) {
                showError('Please enter a valid email address');
                return;
            }
            
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
            
            try {
                const response = await fetch('../api/email?action=send', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ email: email })
                });

                const result = await response.json();

                if (result.success) {
                    currentEmail = email; // Store email for resend
                    document.getElementById('sent-email').textContent = email;
                    document.getElementById('email-form').classList.add('hidden');
                    document.getElementById('code-form').classList.remove('hidden');
                    startCountdown();
                    setupCodeInputs();
                } else {
                    // Check if email already exists
                    if (result.email_exists) {
                        if (result.resubmit_available && result.application_id) {
                            showResubmitModal(result.message, result.application_id);
                        } else {
                            // Email is approved - show error without resubmit option
                            showError(result.message);
                        }
                    } else {
                        showError(result.error || 'Failed to send verification code');
                    }
                    this.disabled = false;
                    this.innerHTML = 'Send Verification Code';
                }
            } catch (error) {
                showError('Network error. Please try again.');
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

        document.getElementById('verify-code-btn').addEventListener('click', async function() {
            const code = Array.from(document.querySelectorAll('.code-input')).map(input => input.value).join('');
            
            if (code.length !== 6) {
                showError('Please enter the complete 6-digit code');
                return;
            }
            
            const email = document.getElementById('verification-email').value.trim();
            
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verifying...';
            
            try {
                const response = await fetch('../api/email?action=verify', {
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
                    showSuccess('Email verified successfully! Proceeding to application form...');
                    
                    setTimeout(() => {
                        moveToStep2();
                    }, 1500);
                } else {
                    showError(result.error || 'Invalid verification code');
                    this.disabled = false;
                    this.innerHTML = 'Verify Code';
                }
            } catch (error) {
                showError('Network error. Please try again.');
                this.disabled = false;
                this.innerHTML = 'Verify Code';
            }
        });

        document.getElementById('resend-btn').addEventListener('click', async function() {
            const email = currentEmail; // Use stored email instead of hidden input

            console.log('Resend button clicked. Email:', email);

            if (!email) {
                showError('Email not found. Please go back and enter your email.');
                return;
            }

            this.disabled = true;
            this.textContent = 'Sending...';

            try {
                console.log('Sending resend request to: ./api.php?endpoint=affiliate&action=send-code with email:', email);

                const response = await fetch('./api.php?endpoint=affiliate&action=send-code', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ email: email })
                });

                console.log('Response status:', response.status);
                console.log('Response ok:', response.ok);

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();

                console.log('Response result:', result);

                if (result.success) {
                    showSuccess('New verification code sent!');
                    startCountdown();
                    // Clear code inputs
                    document.querySelectorAll('.code-input').forEach(input => input.value = '');
                    document.querySelector('.code-input').focus();
                } else {
                    showError(result.error || 'Failed to resend verification code');
                }
            } catch (error) {
                console.error('Resend error:', error);
                showError('Network error: ' + error.message);
            } finally {
                this.disabled = false;
                this.textContent = 'Resend Code';
            }
        });

        function startCountdown() {
            let seconds = 600; // 10 minutes
            const countdownEl = document.getElementById('countdown');
            const timerEl = document.getElementById('timer');
            const resendBtn = document.getElementById('resend-btn');

            countdownEl.classList.remove('hidden');
            resendBtn.disabled = true;

            clearInterval(countdownInterval);

            countdownInterval = setInterval(() => {
                seconds--;
                const minutes = Math.floor(seconds / 60);
                const remainingSeconds = seconds % 60;
                timerEl.textContent = `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;

                if (seconds <= 0) {
                    clearInterval(countdownInterval);
                    countdownEl.textContent = 'Verification code has expired. Please request a new code.';
                    resendBtn.disabled = false;
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
                { key: 'letter_of_intent', name: 'Letter of Intent', accept: '.pdf,.doc,.docx,.jpg,.jpeg,.png' },
                { key: 'endorsement_letter', name: 'Endorsement Letter', accept: '.pdf,.doc,.docx,.jpg,.jpeg,.png' },
                { key: 'constitution_by_laws', name: 'Constitution and By-Laws', accept: '.pdf,.doc,.docx' },
                { key: 'officers_cvs', name: 'List of Officers with CVs', accept: '.pdf,.doc,.docx' },
                { key: 'organizational_chart', name: 'Organizational Chart', accept: '.pdf,.doc,.docx,.jpg,.jpeg,.png' },
                { key: 'member_directory', name: 'Member Directory', accept: '.pdf,.doc,.docx,.xls,.xlsx,.csv' }
            ];

            // Existing documents from PHP (if resubmitting)
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

            // Add change listeners to file inputs
            document.querySelectorAll('#document-upload-section input[type="file"]').forEach(input => {
                input.addEventListener('change', function(e) {
                    console.log('File selected:', e.target.files[0]?.name);
                });
            });
        }

        // Terms checkbox validation
        document.getElementById('terms-checkbox').addEventListener('change', function() {
            document.getElementById('submit-application-btn').disabled = !this.checked;
        });

        // Submit application
        document.getElementById('submit-application-btn').addEventListener('click', async function() {
            if (!validateApplicationForm()) {
                return;
            }
            
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
            
            const formData = new FormData();

            // Add form fields
            formData.append('contact_email', verifiedEmail);
            formData.append('institution_name', document.getElementById('inst-name').value);
            formData.append('institution_address', document.getElementById('inst-address').value);
            formData.append('contact_name', document.getElementById('contact-name').value);
            formData.append('contact_position', document.getElementById('contact-position').value);
            formData.append('contact_phone', document.getElementById('contact-phone').value);
            formData.append('terms', 'accepted');

            // Add resubmit ID if resubmitting
            if (isResubmit && resubmitId) {
                formData.append('resubmit_id', resubmitId);
            }
            
            // Add documents
            const documentInputs = document.querySelectorAll('#document-upload-section input[type="file"]');
            documentInputs.forEach(input => {
                if (input.files[0]) {
                    formData.append(input.name, input.files[0]);
                }
            });
            
            try {
                const response = await fetch('../src/api/affiliate.php?action=submit', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showSuccess(result.message);
                    setTimeout(() => {
                        window.location.href = '/';
                    }, 3000);
                } else {
                    // Check if this is a duplicate email error with resubmit option
                    if (result.resubmit_available && result.application_id) {
                        showResubmitModal(result.error, result.application_id);
                    } else {
                        showError(result.error || 'Failed to submit application');
                    }
                    this.disabled = false;
                    this.innerHTML = 'Submit Application';
                }
            } catch (error) {
                showError('Network error. Please try again.');
                this.disabled = false;
                this.innerHTML = 'Submit Application';
            }
        });

        function validateApplicationForm() {
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
                if (!field.value.trim()) {
                    showError(`${fieldNames[fieldId]} is required`);
                    field.focus();
                    return false;
                }
            }

            // Check if all documents are uploaded (only if not resubmitting)
            if (!isResubmit) {
                const documentInputs = document.querySelectorAll('#document-upload-section input[type="file"]');
                for (const input of documentInputs) {
                    if (!input.files[0]) {
                        showError(`Please upload ${input.previousElementSibling.textContent.replace(' *', '')}`);
                        return false;
                    }
                }
            }

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
