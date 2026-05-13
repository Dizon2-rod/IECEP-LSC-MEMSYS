<?php
/**
 * Change Password Page - Forced password change on first login
 * IECEP-LSC MEMSYS
 */

session_start();
require_once __DIR__ . '/../includes/paths.php';
require_once __DIR__ . '/../includes/config.php';

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Check if password change is required
$isFirstLogin = isset($_GET['first']) && $_GET['first'] === '1';
$requirePasswordChange = isset($_SESSION['require_password_change']) && $_SESSION['require_password_change'] === true;
$mustChangePassword = $_SESSION['user']['must_change_password'] ?? false;

// If not required, redirect to dashboard
if (!$isFirstLogin && !$requirePasswordChange && !$mustChangePassword) {
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
    header('Location: ' . $redirectUrl);
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate password requirements
    if (empty($newPassword)) {
        $error = 'Please enter a new password.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $newPassword)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $newPassword)) {
        $error = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $newPassword)) {
        $error = 'Password must contain at least one number.';
    } elseif (!preg_match('/[^A-Za-z0-9]/', $newPassword)) {
        $error = 'Password must contain at least one special character.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        // Hash the new password
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
        
        try {
            require_once __DIR__ . '/../includes/supabase.php';
            require_once __DIR__ . '/../src/lib/SupabaseClient.php';
            
            $config = require __DIR__ . '/../includes/supabase.php';
            $supabase = new SupabaseClient($config['url'], $config['service_role_key']);
            
            $userId = $_SESSION['user']['id'] ?? '';
            $userEmail = $_SESSION['user']['email'] ?? '';
            
            if (empty($userId)) {
                throw new Exception('User session invalid');
            }
            
            // Try to update in custom users table first
            $users = $supabase->select('users', ['id' => 'eq.' . $userId]);
            if (!empty($users) && is_array($users)) {
                // Update password in custom users table
                $updateResult = $supabase->update('users', ['password' => $passwordHash], $userId);
                if (!$updateResult) {
                    throw new Exception('Failed to update password in users table');
                }
                
                // Also update user_profiles to clear force_password_change
                $profileUpdate = $supabase->update('user_profiles', ['force_password_change' => false], null, ['user_id' => 'eq.' . $userId]);
                if (!$profileUpdate) {
                    error_log('Warning: Failed to clear force_password_change flag in user_profiles');
                }
            } else {
                // Fallback: try Supabase Auth password update
                $authUpdate = $supabase->authUpdatePassword($userId, $newPassword);
                if (!$authUpdate) {
                    throw new Exception('Failed to update password via Supabase Auth');
                }
                
                // Update user_profiles to clear force_password_change
                $profileUpdate = $supabase->update('user_profiles', ['force_password_change' => false], null, ['user_id' => 'eq.' . $userId]);
                if (!$profileUpdate) {
                    error_log('Warning: Failed to clear force_password_change flag in user_profiles');
                }
            }
            
            // Clear password change flags from session
            $_SESSION['user']['must_change_password'] = false;
            unset($_SESSION['require_password_change']);
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            $success = 'Password changed successfully! Redirecting to your dashboard...';
            
        } catch (Exception $e) {
            error_log('Password change error: ' . $e->getMessage());
            $error = 'Failed to update password. Please try again.';
        }
        
        // Redirect after success
        $role = $_SESSION['user']['role'] ?? '';
        $redirectMap = [
            'school_officer' => PORTAL_URL . '/school-officer/dashboard.php',
            'admin' => PORTAL_URL . '/admin/dashboard.php',
            'eb_president' => PORTAL_URL . '/super-admin/dashboard.php',
            'member' => PORTAL_URL . '/member/dashboard.php',
        ];
        
        $redirectUrl = $redirectMap[$role] ?? PORTAL_URL . '/member/dashboard.php';
        
        header('Refresh: 2; URL=' . $redirectUrl);
    }
}

$userName = $_SESSION['user']['name'] ?? 'User';
$userEmail = $_SESSION['user']['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - IECEP-LSC MEMSYS</title>
    <?php include __DIR__ . '/../includes/head-meta.php'; ?>
    <style>
        :root {
            --navy: #0B1D4A;
            --navy-light: #1a3a7a;
            --gold: #D4AF37;
            --gold-hover: #B8962E;
            --success: #22c55e;
            --danger: #ef4444;
            --white: #ffffff;
            --gray-100: #f8fafc;
            --gray-200: #e2e8f0;
            --gray-500: #64748b;
            --gray-700: #334155;
            --gray-900: #0f172a;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            padding: 20px;
        }
        
        .container {
            width: 100%;
            max-width: 480px;
        }
        
        .card {
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            padding: 40px 30px;
            text-align: center;
            border-bottom: 1px solid #f59e0b;
        }
        
        .icon-circle {
            width: 80px;
            height: 80px;
            background: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .icon-circle i {
            font-size: 36px;
            color: var(--navy);
        }
        
        .card-header h1 {
            font-size: 1.5rem;
            color: var(--navy);
            margin-bottom: 10px;
        }
        
        .card-header p {
            color: #78350f;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        .card-body {
            padding: 40px 30px;
        }
        
        .user-info {
            background: var(--gray-100);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .user-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--navy);
            margin-bottom: 5px;
        }
        
        .user-email {
            font-size: 0.9rem;
            color: var(--gray-500);
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.9rem;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-500);
            font-size: 1rem;
        }
        
        .input-wrapper .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-500);
            cursor: pointer;
            font-size: 1rem;
            transition: color 0.2s;
        }
        
        .input-wrapper .toggle-password:hover {
            color: var(--navy);
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 45px;
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--gray-100);
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--navy);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(11, 29, 74, 0.1);
        }
        
        .password-requirements {
            background: #f0f9ff;
            border-left: 4px solid #0ea5e9;
            border-radius: 0 8px 8px 0;
            padding: 15px 20px;
            margin-bottom: 25px;
        }
        
        .password-requirements h4 {
            font-size: 0.85rem;
            color: #0369a1;
            margin: 0 0 10px 0;
        }
        
        .password-requirements ul {
            margin: 0;
            padding-left: 20px;
            font-size: 0.85rem;
            color: #0369a1;
            line-height: 1.8;
        }
        
        .password-requirements li.valid {
            color: var(--success);
        }
        
        .password-requirements li.valid::marker {
            content: "✓ ";
            color: var(--success);
        }
        
        .error-message {
            background: #fee2e2;
            color: var(--danger);
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 4px solid var(--danger);
        }
        
        .success-message {
            background: #dcfce7;
            color: var(--success);
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 4px solid var(--success);
        }
        
        .btn {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: var(--navy);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background: var(--navy-light);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(11, 29, 74, 0.3);
        }
        
        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .loading-spinner {
            display: none;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: var(--white);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        .btn.loading .loading-spinner {
            display: inline-block;
        }
        
        .btn.loading .btn-text {
            display: none;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .logout-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--gray-500);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.2s;
        }
        
        .logout-link:hover {
            color: var(--navy);
        }
        
        @media (max-width: 480px) {
            .card-body, .card-header {
                padding: 30px 20px;
            }
            
            .icon-circle {
                width: 60px;
                height: 60px;
            }
            
            .icon-circle i {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <?php if (!empty($success)): ?>
                <div class="card-header" style="background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); border-color: #22c55e;">
                    <div class="icon-circle">
                        <i class="fas fa-check" style="color: #16a34a;"></i>
                    </div>
                    <h1 style="color: #166534;">Success!</h1>
                    <p style="color: #166534;"><?php echo htmlspecialchars($success); ?></p>
                </div>
                <div class="card-body" style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--navy);"></i>
                    <p style="margin-top: 20px; color: var(--gray-500);">Redirecting to dashboard...</p>
                </div>
            <?php else: ?>
                <div class="card-header">
                    <div class="icon-circle">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h1>Security Setup Required</h1>
                    <p>For your security, you must create a new password before accessing your account.</p>
                </div>
                
                <div class="card-body">
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
                        <div class="user-email"><?php echo htmlspecialchars($userEmail); ?></div>
                    </div>
                    
                    <?php if (!empty($error)): ?>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <span><?php echo htmlspecialchars($error); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="password-requirements">
                        <h4><i class="fas fa-info-circle"></i> Password Requirements</h4>
                        <ul id="requirements-list">
                            <li id="req-length">At least 8 characters</li>
                            <li id="req-upper">One uppercase letter (A-Z)</li>
                            <li id="req-lower">One lowercase letter (a-z)</li>
                            <li id="req-number">One number (0-9)</li>
                            <li id="req-special">One special character (!@#$%^&*)</li>
                        </ul>
                    </div>
                    
                    <form method="POST" id="passwordForm">
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <div class="input-wrapper">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="new_password" name="new_password" required placeholder="Enter new password">
                                <i class="fas fa-eye toggle-password" onclick="togglePassword('new_password', this)"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <div class="input-wrapper">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm new password">
                                <i class="fas fa-eye toggle-password" onclick="togglePassword('confirm_password', this)"></i>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <span class="loading-spinner"></span>
                            <span class="btn-text"><i class="fas fa-key"></i> Change Password</span>
                        </button>
                    </form>
                    
                    <a href="<?php echo BASE_URL; ?>/login.php?logout=true" class="logout-link">
                        <i class="fas fa-sign-out-alt"></i> Log out and return to login
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Password strength checker
        const newPasswordInput = document.getElementById('new_password');
        const requirements = {
            length: document.getElementById('req-length'),
            upper: document.getElementById('req-upper'),
            lower: document.getElementById('req-lower'),
            number: document.getElementById('req-number'),
            special: document.getElementById('req-special')
        };
        
        newPasswordInput.addEventListener('input', function() {
            const password = this.value;
            
            if (password.length >= 8) {
                requirements.length.classList.add('valid');
            } else {
                requirements.length.classList.remove('valid');
            }
            
            if (/[A-Z]/.test(password)) {
                requirements.upper.classList.add('valid');
            } else {
                requirements.upper.classList.remove('valid');
            }
            
            if (/[a-z]/.test(password)) {
                requirements.lower.classList.add('valid');
            } else {
                requirements.lower.classList.remove('valid');
            }
            
            if (/[0-9]/.test(password)) {
                requirements.number.classList.add('valid');
            } else {
                requirements.number.classList.remove('valid');
            }
            
            if (/[^A-Za-z0-9]/.test(password)) {
                requirements.special.classList.add('valid');
            } else {
                requirements.special.classList.remove('valid');
            }
        });
        
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            const hasLength = password.length >= 8;
            const hasUpper = /[A-Z]/.test(password);
            const hasLower = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[^A-Za-z0-9]/.test(password);
            
            if (!hasLength || !hasUpper || !hasLower || !hasNumber || !hasSpecial) {
                e.preventDefault();
                alert('Please meet all password requirements before submitting.');
                return false;
            }
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match.');
                document.getElementById('confirm_password').focus();
                return false;
            }
            
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>
