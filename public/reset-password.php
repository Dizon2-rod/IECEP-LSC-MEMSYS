<?php
require_once __DIR__ . '/bootstrap.php';
// Reset Password Page
// GET /public/reset-password.php?token=TOKEN

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/paths.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../src/lib/SupabaseClient.php';

use App\Lib\SupabaseClient;

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Get token from URL
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($token)) {
    $error = 'Invalid or missing token.';
} else {
    // Verify token validity
    try {
        $config = require __DIR__ . '/../includes/supabase.php';
        $supabase = new SupabaseClient($config['url'], $config['service_role_key']);

        // Get reset record
        $resets = $supabase->select('password_resets', ['token' => 'eq.' . $token]);

        if (empty($resets) || !is_array($resets) || count($resets) === 0) {
            $error = 'Invalid or expired token.';
        } else {
            $reset = $resets[0];

            // Check if token is expired
            if (strtotime($reset['expires_at']) < time()) {
                $error = 'Token has expired. Please request a new password reset.';
            } else if ($reset['used']) {
                $error = 'This token has already been used.';
            } else {
                $error = '';
            }
        }
    } catch (Exception $e) {
        error_log('Token verification error: ' . $e->getMessage());
        $error = 'An error occurred. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Times+New+Roman:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            display: flex;
            min-height: 100vh;
            background: url('<?php echo BASE_URL; ?>/public/assets/icons/hero.png') center/cover no-repeat;
            position: relative;
            overflow: hidden;
        }
        .container { display: flex; width: 100%; min-height: 100vh; }
        .left-section {
            flex: 1; display: flex; flex-direction: column; justify-content: center;
            align-items: center; padding: 60px 40px; color: white; text-align: center;
            position: relative; z-index: 1;
        }
        .logo-container { margin-bottom: 40px; }
        .logo-container img {
            width: 150px; height: 150px; object-fit: contain;
        }
        .organization-title {
            font-family: 'Times New Roman', Arial, serif;
            font-size: 2rem; font-weight: 800; margin-bottom: 15px;
            line-height: 1.3; letter-spacing: 1px;
        }
        .organization-subtitle {
            font-family: 'Times New Roman', Times, serif;
            font-size: 1.5rem; font-weight: 700; margin-bottom: 30px;
        }
        .right-section { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px; }
        .card {
            background: white; border-radius: 80px; padding: 50px 40px;
            width: 100%; max-width: 550px; box-shadow: 0 25px 50px rgba(0,0,0,0.2);
        }
        .card-title {
            font-size: 2.5rem; font-weight: 700; font-style: italic; color: #000;
            margin-bottom: 15px; text-align: center;
        }
        .form-group { margin-bottom: 25px; position: relative; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #333; font-size: 0.9rem; }
        .input-wrapper { position: relative; }
        .input-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #999; font-size: 1rem; }
        .form-group input {
            width: 100%; padding: 16px 50px 16px 45px;
            border: 2px solid #e2e8f0; border-radius: 12px;
            font-size: 1rem; transition: all 0.3s; background: #f8fafc;
        }
        .form-group input:focus { outline: none; border-color: #0A2F6C; background: white; box-shadow: 0 0 0 3px rgba(10,47,108,0.1); }
        #togglePassword { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #999; font-size: 1rem; user-select: none; transition: color 0.2s ease; left: auto; }
        #togglePassword:hover { color: #0A2F6C !important; }
        .password-requirements {
            background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.85rem;
        }
        .requirement {
            padding: 5px 0; color: #666; display: flex; align-items: center; gap: 8px;
        }
        .requirement.met { color: #22c55e; }
        .requirement i { width: 16px; text-align: center; }
        .btn-submit {
            width: 100%; background: #0A2F6C; color: white; border: none;
            padding: 16px; border-radius: 12px; font-size: 1rem; font-weight: 600;
            cursor: pointer; transition: all 0.3s; margin-top: 20px;
        }
        .btn-submit:hover { background: #333; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.2); }
        .btn-submit:disabled { background: #ccc; cursor: not-allowed; transform: none; }
        .message {
            padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem;
            text-align: center; display: none;
        }
        .message.error {
            background: #fee2e2; color: #dc2626; border-left: 4px solid #dc2626; display: block;
        }
        .message.success {
            background: #dcfce7; color: #166534; border-left: 4px solid #22c55e; display: block;
        }
        .links-section {
            display: flex; justify-content: center; gap: 20px; margin-top: 30px;
            font-size: 0.9rem;
        }
        .links-section a {
            color: #0A2F6C; text-decoration: none; font-weight: 500; display: inline-flex;
            align-items: center; gap: 8px;
        }
        .links-section a:hover { color: #F5A623; }
        .spinner {
            display: inline-block; width: 16px; height: 16px; border: 2px solid #fff;
            border-top-color: #0A2F6C; border-radius: 50%; animation: spin 0.8s linear infinite;
        }
        .tagline {
            font-size: 2rem; font-family: 'Times New Roman MT', Times, serif;
            margin-top: 30px; margin-bottom: 20px; line-height: 1.5;
        }
        .schools-row {
            display: flex; justify-content: center; align-items: center; gap: 15px;
            margin-top: 10px; padding: 30px 0 0 0; flex-wrap: wrap;
        }
        .schools-row img {
            max-width: 80px; height: auto; transition: transform 0.3s ease;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3)); flex-shrink: 0;
        }
        .schools-row img:hover { transform: scale(1.1); }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (max-width: 768px) {
            .container { flex-direction: column; }
            .left-section { padding: 30px 20px; min-height: 40vh; }
            .organization-title { font-size: 1.3rem; }
            .organization-subtitle { font-size: 1.1rem; }
            .right-section { padding: 20px; min-height: 60vh; }
            .card { padding: 30px 25px; }
            .card-title { font-size: 1.8rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="left-section">
            <div>
                <div class="logo-container">
                    <img src="<?php echo BASE_URL; ?>/public/assets/icons/iecep-logo.png" alt="IECEP-LSC Logo">
                </div>
                <h1 class="organization-title">INSTITUTE OF ELECTRONICS ENGINEERS OF THE PHILIPPINES</h1>
                <h2 class="organization-subtitle">LAGUNA STUDENT CHAPTER</h2>
            </div>
            <div style="width: 100%; text-align: center;">
                <p class="tagline">One LSC. One IECEP.</p>
                <div class="schools-row">
                    <img src="<?php echo BASE_URL; ?>/public/assets/icons/LETRAN.png" alt="Colegio de San Juan de Letrán">
                    <img src="<?php echo BASE_URL; ?>/public/assets/icons/LSPU-SCC.png" alt="LSPU - Santa Cruz Campus">
                    <img src="<?php echo BASE_URL; ?>/public/assets/icons/LSPU-SPCC.png" alt="LSPU - San Pablo City Campus">
                    <img src="<?php echo BASE_URL; ?>/public/assets/icons/MMCL.webp" alt="Malayan Colleges Laguna">
                    <img src="<?php echo BASE_URL; ?>/public/assets/icons/PUP-STA ROSA.png" alt="PUP - Santa Rosa Campus">
                    <img src="<?php echo BASE_URL; ?>/public/assets/icons/UC-PNC.png" alt="University of Calamba">
                    <img src="<?php echo BASE_URL; ?>/public/assets/icons/UPHSD.png" alt="UPHSD">
                    <img src="<?php echo BASE_URL; ?>/public/assets/icons/UPHSL-BINAN.png" alt="UPHSL - Biñán">
                </div>
            </div>
        </div>
        <div class="right-section">
            <div class="card">
                <h2 class="card-title">Create New Password</h2>
                
                <?php if (!empty($error)): ?>
                    <div class="message error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                    <div class="links-section">
                        <a href="<?php echo BASE_URL; ?>/public/forgot-password.php"><i class="fas fa-arrow-left"></i> Request New Link</a>
                    </div>
                <?php else: ?>
                    <div id="message" class="message"></div>
                    
                    <form id="resetPasswordForm">
                        <input type="hidden" id="token" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        
                        <div class="form-group">
                            <label for="password">New Password</label>
                            <div class="input-wrapper" style="position: relative;">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="password" name="password" required placeholder="Enter new password">
                                <i class="fas fa-eye-slash" id="togglePassword" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #999; font-size: 1rem; user-select: none; transition: color 0.2s ease; left: auto;"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirmPassword">Confirm Password</label>
                            <div class="input-wrapper" style="position: relative;">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="confirmPassword" name="confirmPassword" required placeholder="Confirm new password">
                                <i class="fas fa-eye-slash" id="toggleConfirmPassword" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #999; font-size: 1rem; user-select: none; transition: color 0.2s ease; left: auto;"></i>
                            </div>
                        </div>

                        <div class="password-requirements">
                            <div style="font-weight: 600; margin-bottom: 10px; color: #333;">Password Requirements:</div>
                            <div class="requirement" id="req-length">
                                <i class="fas fa-circle" style="font-size: 0.4rem;"></i>
                                <span>At least 8 characters</span>
                            </div>
                            <div class="requirement" id="req-upper">
                                <i class="fas fa-circle" style="font-size: 0.4rem;"></i>
                                <span>At least one uppercase letter</span>
                            </div>
                            <div class="requirement" id="req-lower">
                                <i class="fas fa-circle" style="font-size: 0.4rem;"></i>
                                <span>At least one lowercase letter</span>
                            </div>
                            <div class="requirement" id="req-digit">
                                <i class="fas fa-circle" style="font-size: 0.4rem;"></i>
                                <span>At least one digit</span>
                            </div>
                            <div class="requirement" id="req-match">
                                <i class="fas fa-circle" style="font-size: 0.4rem;"></i>
                                <span>Passwords match</span>
                            </div>
                        </div>

                        <button type="submit" class="btn-submit" id="submitBtn" disabled>
                            <span id="btnText">Reset Password</span>
                        </button>
                    </form>

                    <div class="links-section">
                        <a href="<?php echo BASE_URL; ?>/login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const togglePasswordBtn = document.getElementById('togglePassword');
        const toggleConfirmPasswordBtn = document.getElementById('toggleConfirmPassword');
        const submitBtn = document.getElementById('submitBtn');
        const messageDiv = document.getElementById('message');
        const form = document.getElementById('resetPasswordForm');

        // Password visibility toggle
        if (togglePasswordBtn) {
            togglePasswordBtn.addEventListener('click', function() {
                togglePasswordVisibility(passwordInput, togglePasswordBtn);
            });
        }

        if (toggleConfirmPasswordBtn) {
            toggleConfirmPasswordBtn.addEventListener('click', function() {
                togglePasswordVisibility(confirmPasswordInput, toggleConfirmPasswordBtn);
            });
        }

        function togglePasswordVisibility(input, btn) {
            if (input.type === 'password') {
                input.type = 'text';
                btn.classList.remove('fa-eye-slash');
                btn.classList.add('fa-eye');
            } else {
                input.type = 'password';
                btn.classList.remove('fa-eye');
                btn.classList.add('fa-eye-slash');
            }
        }

        // Real-time password validation
        passwordInput.addEventListener('input', validatePassword);
        confirmPasswordInput.addEventListener('input', validatePassword);

        function validatePassword() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            const requirements = {
                'length': password.length >= 8,
                'upper': /[A-Z]/.test(password),
                'lower': /[a-z]/.test(password),
                'digit': /\d/.test(password),
                'match': password === confirmPassword && password.length > 0
            };

            // Update UI
            updateRequirement('req-length', requirements.length);
            updateRequirement('req-upper', requirements.upper);
            updateRequirement('req-lower', requirements.lower);
            updateRequirement('req-digit', requirements.digit);
            updateRequirement('req-match', requirements.match);

            // Enable/disable submit button
            const allMet = Object.values(requirements).every(v => v === true);
            submitBtn.disabled = !allMet;
        }

        function updateRequirement(id, met) {
            const elem = document.getElementById(id);
            if (met) {
                elem.classList.add('met');
                elem.querySelector('i').className = 'fas fa-check-circle';
            } else {
                elem.classList.remove('met');
                elem.querySelector('i').className = 'fas fa-circle';
            }
        }

        // Form submission
        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                const token = document.getElementById('token').value;
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;

                if (password !== confirmPassword) {
                    showMessage('Passwords do not match.', 'error');
                    return;
                }

                // Disable button
                submitBtn.disabled = true;
                const btnText = document.getElementById('btnText');
                btnText.innerHTML = '<span class="spinner"></span> Resetting...';
                messageDiv.className = 'message loading';

                try {
                    const response = await fetch('<?php echo BASE_URL; ?>/public/api/reset-password.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ token, new_password: password })
                    });

                    const data = await response.json();

                    if (response.ok && data.success) {
                        showMessage('Password reset successfully! Redirecting to login...', 'success');
                        setTimeout(() => {
                            window.location.href = '<?php echo BASE_URL; ?>/login.php';
                        }, 2000);
                    } else {
                        showMessage(data.message || 'Failed to reset password. Please try again.', 'error');
                        submitBtn.disabled = false;
                        btnText.textContent = 'Reset Password';
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showMessage('A network error occurred. Please try again.', 'error');
                    submitBtn.disabled = false;
                    btnText.textContent = 'Reset Password';
                }
            });
        }

        function showMessage(message, type) {
            messageDiv.textContent = message;
            messageDiv.className = `message ${type}`;
        }
    </script>
</body>
</html>
