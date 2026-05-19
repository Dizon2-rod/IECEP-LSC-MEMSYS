<?php
require_once __DIR__ . '/bootstrap.php';
// Prevent session blocking issues
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure paths.php exists before requiring to prevent timeout
$pathsFile = __DIR__ . '/../includes/paths.php';
if (!file_exists($pathsFile)) {
    die('Configuration error: paths.php not found');
}
require_once $pathsFile;
require_once __DIR__ . '/../includes/config.php'; // defines BASE_URL, PORTAL_URL, etc.

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// If already logged in, redirect to dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $role = $_SESSION['role'] ?? 'member';
    $redirectMap = [
        'eb_president'           => PORTAL_URL . '/super-admin/dashboard.php',
        'super_admin'            => PORTAL_URL . '/super-admin/dashboard.php',
        'admin'                  => PORTAL_URL . '/admin/dashboard.php',
        'school_officer'         => PORTAL_URL . '/school-officer/dashboard.php',
        'member'                 => PORTAL_URL . '/member/dashboard.php',
        'committee_creatives'    => PORTAL_URL . '/creatives/dashboard.php',
        'committee_registration' => PORTAL_URL . '/registration/dashboard.php',
    ];
    $redirectUrl = $redirectMap[$role] ?? PORTAL_URL . '/member/dashboard.php';
    header('Location: ' . $redirectUrl);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - IECEP-LSC MEMSYS</title>
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
        .card-subtitle {
            font-size: 0.95rem; color: #666; text-align: center;
            margin-bottom: 30px; line-height: 1.5;
        }
        .form-group { margin-bottom: 25px; position: relative; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #333; font-size: 0.9rem; }
        .input-wrapper { position: relative; }
        .input-wrapper i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #999; font-size: 1rem; }
        .form-group input {
            width: 100%; padding: 16px 18px 16px 45px;
            border: 2px solid #e2e8f0; border-radius: 12px;
            font-size: 1rem; transition: all 0.3s; background: #f8fafc;
        }
        .form-group input:focus { outline: none; border-color: #0A2F6C; background: white; box-shadow: 0 0 0 3px rgba(10,47,108,0.1); }
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
        .message.success {
            background: #dcfce7; color: #166534; border-left: 4px solid #22c55e; display: block;
        }
        .message.error {
            background: #fee2e2; color: #dc2626; border-left: 4px solid #dc2626; display: block;
        }
        .message.loading {
            background: #dbeafe; color: #1e40af; border-left: 4px solid #3b82f6; display: block;
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
                <h2 class="card-title">Reset Password</h2>
                <p class="card-subtitle">Enter your email address and we'll send you a link to reset your password.</p>
                
                <div id="message" class="message"></div>
                
                <form id="forgotPasswordForm">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" required placeholder="Enter your email" autocomplete="email">
                        </div>
                    </div>
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <span id="btnText">Send Reset Link</span>
                    </button>
                </form>

                <div class="links-section">
                    <a href="<?php echo BASE_URL; ?>/login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        const form = document.getElementById('forgotPasswordForm');
        const messageDiv = document.getElementById('message');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const email = document.getElementById('email').value.trim();
            
            // Validate email
            if (!email || !isValidEmail(email)) {
                showMessage('Please enter a valid email address.', 'error');
                return;
            }

            // Disable button and show loading state
            submitBtn.disabled = true;
            btnText.innerHTML = '<span class="spinner"></span> Sending...';
            messageDiv.className = 'message loading';

            try {
                const response = await fetch('<?php echo BASE_URL; ?>/public/api/request-password-reset.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ email })
                });

                const data = await response.json();

                if (response.ok) {
                    showMessage('If an account exists with this email, you will receive a password reset link shortly.', 'success');
                    form.reset();
                } else {
                    showMessage(data.message || 'An error occurred. Please try again.', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showMessage('A network error occurred. Please try again.', 'error');
            } finally {
                submitBtn.disabled = false;
                btnText.textContent = 'Send Reset Link';
            }
        });

        function isValidEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        function showMessage(message, type) {
            messageDiv.textContent = message;
            messageDiv.className = `message ${type}`;
        }
    </script>
</body>
</html>
