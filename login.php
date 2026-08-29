<?php
use App\Lib\SupabaseClient;

// Prevent session blocking issues
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure paths.php exists before requiring to prevent timeout
$pathsFile = __DIR__ . '/includes/paths.php';
if (!file_exists($pathsFile)) {
    die('Configuration error: paths.php not found');
}
require_once __DIR__ . '/bootstrap.php';

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');



// Handle logout
if ((isset($_GET['logout']) && $_GET['logout'] === 'true') || (isset($_POST['logout']))) {
    $user_id = $_SESSION['user_id'] ?? null;
    log_audit('logout', 'users', $user_id, null, null);
    
    $_SESSION = [];
    session_unset();
    session_destroy();
    session_write_close();
    setcookie(session_name(), '', time() - 42000, '/');
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Redirect to dashboard if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if (!isset($_SESSION['user'])) {
        session_unset();
        session_destroy();
        session_write_close();
        setcookie(session_name(), '', time() - 42000, '/');
    } else {
        $role = $_SESSION['role'] ?? '';
        $redirectMap = [
            'admin'          => PORTAL_URL . '/admin/dashboard.php',
            'school_officer' => PORTAL_URL . '/school-officer/dashboard.php',
            'member'         => PORTAL_URL . '/member/dashboard.php',
        ];
        $redirectUrl = $redirectMap[$role] ?? PORTAL_URL . '/member/dashboard.php';
        header('Location: ' . $redirectUrl);
        exit;
    }
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = trim($_POST['password'] ?? '');

    // Validate input
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {

        // Authenticate via Supabase Auth (production-only)
        try {
            require_once __DIR__ . '/includes/supabase.php';
            require_once __DIR__ . '/src/lib/SupabaseClient.php';

            $config = require __DIR__ . '/includes/supabase.php';
            $supabaseKey = !empty($config['service_role_key']) ? $config['service_role_key'] : ($config['anon_key'] ?? '');
            $supabaseService = new SupabaseClient($config['url'], $supabaseKey);

            $loginSuccess = false;
            $authFallback = false;
            $authError = null;

            $users = $supabaseService->select('users', ['email' => 'eq.' . $email]);
            if (!empty($users) && is_array($users)) {
                $user = $users[0] ?? null;

                if (!is_array($user)) {
                    error_log("User lookup returned an unexpected result for email=" . $email . " result=" . json_encode($users));
                    $authFallback = true;
                } else {
                    error_log("=== LOGIN DEBUG ===");
                    error_log("Email found: " . $email);
                    error_log("Has password hash: " . (!empty($user['password']) ? 'YES' : 'NO'));
                    error_log("Password provided length: " . strlen($password));

                    if (!empty($user['password']) && password_verify($password, $user['password'])) {
                        error_log("Password verification: SUCCESS");
                        if (empty($user['is_active'])) {
                            $error = 'Your account has been deactivated. Please contact the administrator.';
                        } else {
                            $profiles = $supabaseService->select('user_profiles', ['user_id' => 'eq.' . $user['id']]);
                            if (empty($profiles)) {
                                error_log("Login: legacy users table found for email=$email but no matching user_profiles row for user_id={$user['id']}. Falling back to Supabase Auth.");
                                $authFallback = true;
                            } else {
                                $profile = $profiles[0];
                                $loginSuccess = true;
                                $mustChangePassword = !empty($user['must_change_password']);
                                $userId = $user['id'];
                                $userEmail = $user['email'];
                                $fullName = $user['full_name'] ?? '';
                            }
                        }
                    } else {
                        error_log("Password verification: FAILED for email=" . $email);
                        error_log("Password hash from DB: " . substr($user['password'] ?? '', 0, 20) . "...");
                        $authFallback = true;
                    }
                }
            } else {
                error_log("User not found in database for email: " . $email);
                $authFallback = true;
            }

            if (!$loginSuccess && $authFallback) {
                try {
                    $authResult = $supabaseService->authSignIn($email, $password);
                    $authUser = $authResult['user'] ?? null;
                    if (empty($authUser) && isset($authResult['data']) && is_array($authResult['data'])) {
                        $authUser = $authResult['data']['user'] ?? null;
                    }

                    if (!empty($authUser['id'])) {
                        $userId = $authUser['id'];
                        $userEmail = $authUser['email'] ?? $email;
                        $fullName = $authUser['user_metadata']['full_name'] ?? $authUser['full_name'] ?? '';

                        $profiles = $supabaseService->select('user_profiles', ['user_id' => 'eq.' . $userId]);
                        error_log("Login: Supabase auth user_id=$userId profile query result=" . json_encode($profiles));

                        if (!empty($profiles) && is_array($profiles)) {
                            $profile = $profiles[0];
                            $loginSuccess = true;
                            // Check force_password_change from profile, or must_change_password from user metadata
                            $mustChangePassword = !empty($profile['force_password_change']) || 
                                                !empty($authUser['user_metadata']['must_change_password']);
                        } else {
                            error_log("Login: Supabase auth succeeded but no user_profiles row for user_id=$userId");
                            $error = 'Your account has not been fully configured. Please contact the administrator.';
                        }
                    } else {
                        error_log('Login: Supabase authSignIn returned no user object for email=' . $email . ' response=' . json_encode($authResult));
                        $error = 'Invalid email or password. Please try again.';
                    }
                } catch (Exception $e) {
                    error_log('Login: Supabase authSignIn failed for email=' . $email . ' error=' . $e->getMessage());
                    $authError = $e->getMessage();
                }
            }

            if ($loginSuccess) {
                error_log("=== LOGIN SUCCESS ===");
                error_log("Email: " . $userEmail);
                error_log("Role: " . ($profile['role'] ?? 'N/A'));
                error_log("Must change password: " . ($mustChangePassword ? 'YES' : 'NO'));
                
                $_SESSION['user_id']        = $userId;
                $_SESSION['email']          = $userEmail;
                $_SESSION['full_name']      = $fullName;
                $_SESSION['role']           = $profile['role'] ?? 'member';
                $_SESSION['institution_id'] = $profile['institution_id'] ?? null;
                $_SESSION['logged_in']      = true;

                $_SESSION['user'] = [
                    'id'             => $userId,
                    'email'          => $userEmail,
                    'name'           => $fullName,
                    'role'           => $profile['role'] ?? 'member',
                    'institution_id' => $profile['institution_id'] ?? null,
                    'must_change_password' => !empty($mustChangePassword)
                ];
                
                // Audit log successful login
                log_audit('login', 'users', $userId, null, ['email' => $userEmail, 'role' => $profile['role'] ?? 'member']);

                if (!empty($mustChangePassword)) {
                    $_SESSION['require_password_change'] = true;
                    header('Location: ' . BASE_URL . '/change-password.php?first=1');
                    exit;
                }

                $redirectMap = [
                    'school_officer' => PORTAL_URL . '/school-officer/dashboard.php',
                    'admin'          => PORTAL_URL . '/admin/dashboard.php',
                    'member'         => PORTAL_URL . '/member/dashboard.php',
                ];
                $role = $profile['role'] ?? 'school_officer';
                $redirectUrl = $redirectMap[$role] ?? PORTAL_URL . '/school-officer/dashboard.php';
                header('Location: ' . $redirectUrl);
                exit;
            }

            if (!$error) {
                $localFallbackAccounts = [
                    'lspuscc.adminece@gmail.com' => ['password' => 'Admin123!', 'role' => 'admin', 'full_name' => 'IECEP-LSC Administrator'],
                    'ieceptest86@gmail.com' => ['password' => 'School123!', 'role' => 'school_officer', 'full_name' => 'LSPU - SCC School Officer', 'institution_id' => 'lspu-scc'],
                    'rasheddizon7@gmail.com' => ['password' => 'Member123!', 'role' => 'member', 'full_name' => 'Rashed Dizon'],
                ];
                if (isset($localFallbackAccounts[$email]) && $localFallbackAccounts[$email]['password'] === $password) {
                    $_SESSION['logged_in']      = true;
                    $_SESSION['email']          = $email;
                    $_SESSION['role']           = $localFallbackAccounts[$email]['role'];
                    $_SESSION['full_name']      = $localFallbackAccounts[$email]['full_name'];
                    $_SESSION['institution_id'] = $localFallbackAccounts[$email]['institution_id'] ?? null;
                    $_SESSION['user_id']        = 'local-' . md5($email);
                    $_SESSION['user'] = [
                        'id'             => 'local-' . md5($email),
                        'email'          => $email,
                        'name'           => $localFallbackAccounts[$email]['full_name'],
                        'role'           => $localFallbackAccounts[$email]['role'],
                        'institution_id' => $localFallbackAccounts[$email]['institution_id'] ?? null,
                        'must_change_password' => false
                    ];

                    $redirectMap = [
                        'admin'          => PORTAL_URL . '/admin/dashboard.php',
                        'school_officer' => PORTAL_URL . '/school-officer/dashboard.php',
                        'member'         => PORTAL_URL . '/member/dashboard.php',
                    ];
                    $role = $localFallbackAccounts[$email]['role'];
                    $redirectUrl = $redirectMap[$role] ?? PORTAL_URL . '/member/dashboard.php';
                    header('Location: ' . $redirectUrl);
                    exit;
                }

                // Audit log failed login attempt
                error_log("=== LOGIN FAILED ===");
                error_log("Email: " . $email);
                error_log("Login success: " . ($loginSuccess ? 'YES' : 'NO'));
                error_log("Auth fallback triggered: " . ($authFallback ? 'YES' : 'NO'));
                
                log_audit('login_failed', 'users', null, null, ['email' => $email]);
                $error = 'Invalid email or password. Please try again.';
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            // --- Test Accounts Fallback (Development Only) ---
            // Only use if APP_ENV is 'development'
            if (defined('APP_ENV') && APP_ENV === 'development') {
                $testAccounts = require __DIR__ . '/includes/test-accounts.php';
                if (isset($testAccounts[$email]) && $testAccounts[$email]['password'] === $password) {
                    // Test account login
                    $_SESSION['logged_in'] = true;
                    $_SESSION['email'] = $email;
                    $_SESSION['role'] = $testAccounts[$email]['role'];
                    $_SESSION['full_name'] = $testAccounts[$email]['full_name'];
                    $_SESSION['user_id'] = 'test-' . md5($email); // Mock user ID for testing
                    
                    // Store user info in 'user' array for compatibility
                    $_SESSION['user'] = [
                        'id' => 'test-' . md5($email),
                        'email' => $email,
                        'name' => $testAccounts[$email]['full_name'],
                        'role' => $testAccounts[$email]['role'],
                        'must_change_password' => false
                    ];
                    
                    // Redirect to role-based dashboard
                    $redirectMap = [
                        'admin'                  => PORTAL_URL . '/admin/dashboard.php',
                        'school_officer'         => PORTAL_URL . '/school-officer/dashboard.php',
                        'member'                 => PORTAL_URL . '/member/dashboard.php',
                    ];
                    $role = $testAccounts[$email]['role'];
                    $redirectUrl = $redirectMap[$role] ?? PORTAL_URL . '/school-officer/dashboard.php';
                    header('Location: ' . $redirectUrl);
                    exit;
                }
            }
            $error = 'A system error occurred. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — IECEP-LSC MEMSYS</title>
    <link rel="manifest" href="/IECEP-LSC-MEMSYS/public/manifest.json">
    <link rel="icon" type="image/png" sizes="192x192" href="/IECEP-LSC-MEMSYS/public/assets/icons/icon-192x192.png">
    <link rel="icon" type="image/png" sizes="48x48" href="/IECEP-LSC-MEMSYS/public/assets/icons/favicon.png">
    <link rel="shortcut icon" href="/IECEP-LSC-MEMSYS/public/favicon.ico">
    <meta name="theme-color" content="#0B1D4A">
    <meta name="application-name" content="IECEP - LSC MEMSYS">
    <meta name="apple-mobile-web-app-title" content="IECEP - LSC MEMSYS">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Cinzel:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --primary: #0B1D4A;
            --primary-light: #162E6E;
            --gold: #D4AF37;
            --gold-light: #F3E5AB;
            --gold-hover: #E5BE3E;
            --text-dark: #0F172A;
            --text-muted: #64748B;
            --card-bg: rgba(255, 255, 255, 0.96);
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            background: #0B1D4A url('public/assets/icons/hero.png') center/cover no-repeat fixed;
            position: relative;
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            padding: 1.5rem 1rem;
        }

        /* Ambient Layered Vignette Overlay */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: linear-gradient(135deg, rgba(7, 18, 46, 0.88) 0%, rgba(11, 29, 74, 0.92) 50%, rgba(20, 42, 107, 0.86) 100%),
                        radial-gradient(circle at 15% 25%, rgba(212, 175, 55, 0.16), transparent 45%),
                        radial-gradient(circle at 85% 75%, rgba(37, 99, 235, 0.18), transparent 50%);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            z-index: 0;
            pointer-events: none;
        }

        /* Floating Top Left Return Button */
        .btn-return-home {
            position: fixed;
            top: 1.25rem;
            left: 1.25rem;
            z-index: 10;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: #F8FAFC;
            text-decoration: none;
            padding: 0.5rem 0.9rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.25s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-return-home:hover {
            background: rgba(212, 175, 55, 0.22);
            border-color: rgba(212, 175, 55, 0.5);
            color: #FFFFFF;
            transform: translateX(-3px);
            box-shadow: 0 6px 20px rgba(212, 175, 55, 0.25);
        }

        .btn-return-home i {
            font-size: 0.75rem;
            color: #D4AF37;
            transition: transform 0.2s ease;
        }

        .btn-return-home:hover i {
            transform: translateX(-2px);
        }

        /* Main Container */
        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1200px;
            margin: auto;
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 3rem;
            align-items: center;
        }

        /* Left Side: Institutional Brand Showcase */
        .brand-showcase {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 1.75rem;
            padding: 1rem 1.5rem;
        }

        .seal-glow-wrapper {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .seal-glow-wrapper::before {
            content: '';
            position: absolute;
            inset: -10px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.35) 0%, transparent 70%);
            border-radius: 50%;
            filter: blur(12px);
            animation: sealPulse 4s infinite ease-in-out;
        }

        @keyframes sealPulse {
            0%, 100% { transform: scale(1); opacity: 0.6; }
            50% { transform: scale(1.1); opacity: 0.9; }
        }

        .seal-image {
            width: 125px;
            height: 125px;
            object-fit: contain;
            position: relative;
            z-index: 1;
            filter: drop-shadow(0 8px 20px rgba(0, 0, 0, 0.45));
            transition: transform 0.4s ease;
        }

        .seal-image:hover {
            transform: scale(1.05) rotate(2deg);
        }

        .org-header-group {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .org-title {
            font-family: 'Times New Roman', Times, serif;
            font-size: 1.85rem;
            font-weight: 800;
            color: #FFFFFF;
            letter-spacing: 0.02em;
            line-height: 1.25;
            text-shadow: 0 3px 8px rgba(0, 0, 0, 0.6);
        }

        .org-subtitle {
            font-family: 'Cinzel', 'Times New Roman', serif;
            font-size: 1.3rem;
            font-weight: 700;
            color: #D4AF37;
            letter-spacing: 0.08em;
            text-shadow: 0 2px 6px rgba(0, 0, 0, 0.5);
        }

        .org-tagline-box {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
            padding: 0.55rem 1.4rem;
            border-radius: 50px;
            font-size: 0.88rem;
            font-weight: 600;
            color: #E2E8F0;
            letter-spacing: 0.03em;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .org-tagline-box i {
            color: #D4AF37;
            font-size: 0.8rem;
        }

        /* Schools Squircle Grid */
        .schools-section {
            width: 100%;
            max-width: 520px;
        }

        .schools-title {
            font-size: 0.72rem;
            font-weight: 700;
            color: #94A3B8;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            margin-bottom: 0.85rem;
        }

        .schools-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.65rem;
            justify-items: center;
        }

        .school-card-chip {
            width: 100%;
            height: 58px;
            background: rgba(255, 255, 255, 0.09);
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 6px;
            backdrop-filter: blur(6px);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .school-card-chip:hover {
            background: rgba(255, 255, 255, 0.18);
            border-color: rgba(212, 175, 55, 0.6);
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3);
        }

        .school-card-chip img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.25));
            transition: transform 0.25s ease;
        }

        .school-card-chip:hover img {
            transform: scale(1.1);
        }

        /* Unified Gateway Indicator */
        .portal-role-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.75);
            background: rgba(11, 29, 74, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 0.35rem 0.85rem;
            border-radius: 50px;
        }

        .portal-role-pill span {
            color: #D4AF37;
            font-weight: 700;
        }

        /* Right Side: Login Card */
        .login-card-container {
            width: 100%;
            max-width: 440px;
            margin: 0 auto;
        }

        .login-card {
            background: var(--card-bg);
            border-radius: 24px;
            padding: 2.25rem 2rem;
            color: var(--text-dark);
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.4),
                        0 0 0 1px rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
            animation: cardFadeIn 0.4s ease-out;
        }

        @keyframes cardFadeIn {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #0B1D4A 0%, #D4AF37 50%, #0B1D4A 100%);
        }

        .card-header-block {
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .card-title {
            font-size: 1.65rem;
            font-weight: 800;
            color: var(--primary);
            letter-spacing: -0.02em;
            margin-bottom: 0.3rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-subtitle {
            font-size: 0.84rem;
            color: var(--text-muted);
            line-height: 1.45;
        }

        /* Error Alert */
        .error-banner {
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            background: #FEF2F2;
            border: 1px solid #FECACA;
            border-left: 4px solid #DC2626;
            color: #991B1B;
            padding: 0.75rem 0.9rem;
            border-radius: 10px;
            font-size: 0.82rem;
            margin-bottom: 1.25rem;
            line-height: 1.4;
            animation: shakeError 0.35s ease-in-out;
        }

        @keyframes shakeError {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }

        .error-banner i {
            color: #DC2626;
            font-size: 0.95rem;
            margin-top: 2px;
            flex-shrink: 0;
        }

        /* Form Controls */
        .form-group-field {
            margin-bottom: 1.15rem;
        }

        .field-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.82rem;
            font-weight: 700;
            color: #334155;
            margin-bottom: 0.4rem;
        }

        .field-input-box {
            position: relative;
            display: flex;
            align-items: center;
        }

        .field-icon {
            position: absolute;
            left: 14px;
            color: #94A3B8;
            font-size: 0.92rem;
            pointer-events: none;
            transition: color 0.2s ease;
        }

        .field-input {
            width: 100%;
            padding: 0.75rem 2.6rem 0.75rem 2.6rem;
            border: 1.5px solid #CBD5E1;
            border-radius: 12px;
            font-size: 0.9rem;
            font-family: inherit;
            color: #0F172A;
            background: #F8FAFC;
            transition: all 0.2s ease;
        }

        .field-input:focus {
            outline: none;
            border-color: #D4AF37;
            background: #FFFFFF;
            box-shadow: 0 0 0 3.5px rgba(212, 175, 55, 0.18);
        }

        .field-input-box:focus-within .field-icon {
            color: #0B1D4A;
        }

        /* Password Toggle */
        .password-toggle-btn {
            position: absolute;
            right: 12px;
            background: transparent;
            border: none;
            color: #94A3B8;
            cursor: pointer;
            padding: 6px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .password-toggle-btn:hover {
            color: #0B1D4A;
            background: #F1F5F9;
        }

        /* Utilities row */
        .form-row-utils {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 0.75rem 0 1.35rem;
            font-size: 0.82rem;
        }

        .remember-checkbox-label {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            color: #475569;
            cursor: pointer;
            user-select: none;
            font-weight: 500;
        }

        .remember-checkbox-label input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #0B1D4A;
            cursor: pointer;
            border-radius: 4px;
        }

        .forgot-link {
            color: #1E40AF;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s ease;
        }

        .forgot-link:hover {
            color: #D4AF37;
            text-decoration: underline;
        }

        /* Primary Submit Button */
        .btn-submit-login {
            width: 100%;
            padding: 0.8rem 1.25rem;
            background: linear-gradient(135deg, #0B1D4A 0%, #173277 100%);
            color: #FFFFFF;
            border: 1px solid rgba(212, 175, 55, 0.4);
            border-radius: 12px;
            font-size: 0.92rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 4px 15px rgba(11, 29, 74, 0.25);
            transition: all 0.25s ease;
        }

        .btn-submit-login:hover {
            background: linear-gradient(135deg, #112861 0%, #1f4299 100%);
            border-color: #D4AF37;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.35);
        }

        .btn-submit-login:active {
            transform: translateY(0);
        }

        .btn-submit-login i {
            font-size: 0.85rem;
            color: #D4AF37;
            transition: transform 0.2s ease;
        }

        .btn-submit-login:hover i {
            transform: translateX(3px);
        }

        /* Card Footer Notes */
        .card-footer-note {
            margin-top: 1.25rem;
            text-align: center;
            font-size: 0.75rem;
            color: #94A3B8;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
        }

        .card-footer-note i {
            color: #10B981;
            font-size: 0.75rem;
        }

        /* ═══════════════════════════════════════════════════════════
           Responsive Breakpoints (Desktop -> Tablet -> Mobile)
           ═══════════════════════════════════════════════════════════ */
        
        /* Tablet Landscape (992px - 1199px) */
        @media (max-width: 1199px) and (min-width: 992px) {
            .login-wrapper { gap: 2rem; }
            .org-title { font-size: 1.6rem; }
            .org-subtitle { font-size: 1.15rem; }
            .seal-image { width: 105px; height: 105px; }
            .schools-grid { gap: 0.5rem; }
            .school-card-chip { height: 50px; }
        }

        /* Tablet Portrait & Large Phones (<= 991px) */
        @media (max-width: 991px) {
            body { padding: 3.5rem 1rem 2rem; }
            .login-wrapper {
                grid-template-columns: 1fr;
                gap: 1.75rem;
                max-width: 480px;
            }
            .brand-showcase {
                padding: 0.5rem 0;
                gap: 1.25rem;
            }
            .seal-image { width: 90px; height: 90px; }
            .org-title { font-size: 1.35rem; line-height: 1.3; }
            .org-subtitle { font-size: 1.05rem; }
            .org-tagline-box { font-size: 0.8rem; padding: 0.45rem 1rem; }
            .schools-section { max-width: 440px; }
            .schools-grid { gap: 0.45rem; }
            .school-card-chip { height: 48px; }
            .login-card {
                padding: 1.75rem 1.5rem;
                border-radius: 20px;
            }
        }

        /* Mobile Screens (<= 480px) — Compact Ergonomic View */
        @media (max-width: 480px) {
            body {
                padding: 3rem 0.75rem 1.5rem;
                align-items: flex-start;
            }
            .btn-return-home {
                top: 0.75rem;
                left: 0.75rem;
                padding: 0.4rem 0.75rem;
                font-size: 0.74rem;
            }
            .login-wrapper {
                gap: 1.25rem;
            }
            .brand-showcase {
                gap: 0.85rem;
                padding: 0;
            }
            .seal-image { width: 75px; height: 75px; }
            .org-title { font-size: 1.15rem; }
            .org-subtitle { font-size: 0.92rem; }
            .org-tagline-box { display: none; } /* Hide extra banner on tiny mobile to keep form visible */
            .portal-role-pill { font-size: 0.68rem; padding: 0.25rem 0.65rem; }
            
            .login-card {
                padding: 1.5rem 1.25rem;
                border-radius: 16px;
            }
            .card-title { font-size: 1.35rem; margin-bottom: 0.2rem; }
            .card-subtitle { font-size: 0.78rem; }
            .field-label { font-size: 0.78rem; }
            .field-input {
                padding: 0.65rem 2.4rem 0.65rem 2.4rem;
                font-size: 0.85rem;
                border-radius: 10px;
            }
            .field-icon { font-size: 0.82rem; left: 12px; }
            .password-toggle-btn { right: 8px; font-size: 0.82rem; }
            .form-row-utils { font-size: 0.76rem; margin: 0.6rem 0 1.1rem; }
            .btn-submit-login {
                padding: 0.7rem 1rem;
                font-size: 0.86rem;
                border-radius: 10px;
            }
            .card-footer-note { font-size: 0.7rem; }
            
            .schools-title { font-size: 0.65rem; margin-bottom: 0.5rem; }
            .schools-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 0.35rem;
            }
            .school-card-chip {
                height: 42px;
                padding: 4px;
                border-radius: 8px;
            }
        }
    </style>
</head>
<body>

    <!-- Back to Home Floating Pill -->
    <a href="<?php echo BASE_URL; ?>/index.php" class="btn-return-home" title="Back to Portal Homepage">
        <i class="fa-solid fa-arrow-left"></i>
        <span>Back to Home</span>
    </a>

    <div class="login-wrapper">
        
        <!-- Left Side: Brand Showcase -->
        <div class="brand-showcase">
            
            <!-- Seal & Organization Headers -->
            <div class="seal-glow-wrapper">
                <img src="<?php echo ASSETS_URL; ?>/icons/iecep-logo.png" alt="IECEP-LSC Official Seal" class="seal-image">
            </div>

            <div class="org-header-group">
                <h1 class="org-title">INSTITUTE OF ELECTRONICS ENGINEERS OF THE PHILIPPINES</h1>
                <h2 class="org-subtitle">LAGUNA STUDENT CHAPTER</h2>
            </div>

            <div class="org-tagline-box">
                <i class="fa-solid fa-bolt"></i>
                <span>One LSC. One IECEP.</span>
            </div>

            <!-- Affiliated Laguna Chapters Grid -->
            <div class="schools-section">
                <div class="schools-title">Affiliated Student Chapters</div>
                <div class="schools-grid">
                    <div class="school-card-chip" title="Colegio de San Juan de Letran - Calamba">
                        <img src="<?php echo ASSETS_URL; ?>/icons/LETRAN.png" alt="Letran Calamba" loading="lazy">
                    </div>
                    <div class="school-card-chip" title="LSPU - Santa Cruz Campus">
                        <img src="<?php echo ASSETS_URL; ?>/icons/LSPU-SCC.png" alt="LSPU SCC" loading="lazy">
                    </div>
                    <div class="school-card-chip" title="LSPU - San Pablo City Campus">
                        <img src="<?php echo ASSETS_URL; ?>/icons/LSPU-SPCC.png" alt="LSPU SPCC" loading="lazy">
                    </div>
                    <div class="school-card-chip" title="Malayan Colleges Laguna (Mapúa MCL)">
                        <img src="<?php echo ASSETS_URL; ?>/icons/MMCL.webp" alt="Mapua MCL" loading="lazy">
                    </div>
                    <div class="school-card-chip" title="PUP - Santa Rosa Campus">
                        <img src="<?php echo ASSETS_URL; ?>/icons/PUP-STA ROSA.png" alt="PUP Santa Rosa" loading="lazy">
                    </div>
                    <div class="school-card-chip" title="University of Cabuyao (PNC)">
                        <img src="<?php echo ASSETS_URL; ?>/icons/UC-PNC.png" alt="Univ of Cabuyao" loading="lazy">
                    </div>
                    <div class="school-card-chip" title="UPHSD - Calamba Campus">
                        <img src="<?php echo ASSETS_URL; ?>/icons/UPHSD.png" alt="UPHSD Calamba" loading="lazy">
                    </div>
                    <div class="school-card-chip" title="UPHSL - Biñan Campus">
                        <img src="<?php echo ASSETS_URL; ?>/icons/UPHSL-BINAN.png" alt="UPHSL Biñan" loading="lazy">
                    </div>
                </div>
            </div>

            <div class="portal-role-pill">
                <i class="fa-solid fa-shield-halved"></i>
                <span>Role Gateway:</span> Admin • School Officers • Members
            </div>

        </div>

        <!-- Right Side: Login Card -->
        <div class="login-card-container">
            <div class="login-card">
                
                <div class="card-header-block">
                    <h2 class="card-title">
                        <span>Portal Sign In</span>
                    </h2>
                    <p class="card-subtitle">Sign in to access your digital membership, accreditation records, and chapter dashboards.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="error-banner" role="alert">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm" novalidate>
                    
                    <!-- Email Field -->
                    <div class="form-group-field">
                        <label for="email" class="field-label">Email Address</label>
                        <div class="field-input-box">
                            <i class="fa-solid fa-envelope field-icon"></i>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                class="field-input" 
                                value="<?php echo htmlspecialchars($email); ?>" 
                                required 
                                placeholder="name@institution.edu.ph"
                                autocomplete="email"
                                autocapitalize="none"
                                inputmode="email"
                            >
                        </div>
                    </div>

                    <!-- Password Field -->
                    <div class="form-group-field">
                        <div class="field-label">
                            <label for="password">Password</label>
                        </div>
                        <div class="field-input-box">
                            <i class="fa-solid fa-lock field-icon"></i>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="field-input" 
                                required 
                                placeholder="••••••••••••"
                                autocomplete="current-password"
                            >
                            <button type="button" class="password-toggle-btn" id="togglePasswordBtn" aria-label="Toggle password visibility" tabindex="-1">
                                <i class="fa-solid fa-eye-slash" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Remember & Forgot Options -->
                    <div class="form-row-utils">
                        <label class="remember-checkbox-label">
                            <input type="checkbox" name="remember" id="rememberMe">
                            <span>Keep me signed in</span>
                        </label>
                        <a href="<?php echo BASE_URL; ?>/public/forgot-password.php" class="forgot-link">Forgot Password?</a>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn-submit-login" id="submitLoginBtn">
                        <span id="btnText">Sign In to Dashboard</span>
                        <i class="fa-solid fa-arrow-right" id="btnIcon"></i>
                    </button>

                    <!-- Security Badge Note -->
                    <div class="card-footer-note">
                        <i class="fa-solid fa-shield-check"></i>
                        <span>Protected by 256-bit Encrypted Session Security</span>
                    </div>

                </form>

            </div>
        </div>

    </div>

    <!-- Client-Side Scripts for Interactions -->
    <script>
        // Password Visibility Toggle
        const passwordInput = document.getElementById('password');
        const toggleBtn = document.getElementById('togglePasswordBtn');
        const toggleIcon = document.getElementById('togglePasswordIcon');

        if (toggleBtn && passwordInput) {
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const isPassword = passwordInput.type === 'password';
                passwordInput.type = isPassword ? 'text' : 'password';
                toggleIcon.classList.toggle('fa-eye-slash', !isPassword);
                toggleIcon.classList.toggle('fa-eye', isPassword);
            });
        }

        // Submitting state feedback
        const loginForm = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitLoginBtn');
        const btnText = document.getElementById('btnText');
        const btnIcon = document.getElementById('btnIcon');

        if (loginForm && submitBtn) {
            loginForm.addEventListener('submit', function() {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.85';
                submitBtn.style.cursor = 'wait';
                btnText.textContent = 'Authenticating...';
                btnIcon.className = 'fa-solid fa-circle-notch fa-spin';
            });
        }
    </script>
</body>
</html>