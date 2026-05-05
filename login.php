<?php
session_start();
require_once __DIR__ . '/includes/paths.php';
require_once __DIR__ . '/includes/config.php'; // defines BASE_URL, PORTAL_URL, etc.

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Handle logout
if ((isset($_GET['logout']) && $_GET['logout'] === 'true') || (isset($_POST['logout']))) {
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
    $role = $_SESSION['role'] ?? '';
    $redirectMap = [
        'eb_president'          => PORTAL_URL . '/super-admin/dashboard.php',
        'super_admin'           => PORTAL_URL . '/super-admin/dashboard.php',
        'admin'                 => PORTAL_URL . '/admin/dashboard.php',
        'school_officer'        => PORTAL_URL . '/school-officer/dashboard.php',
        'member'                => PORTAL_URL . '/member/dashboard.php',
        'eb_pro_1'              => PORTAL_URL . '/creatives/dashboard.php',
        'committee_creatives'   => PORTAL_URL . '/creatives/dashboard.php',
        'eb_pro_2'              => PORTAL_URL . '/logistics/dashboard.php',
        'eb_treasurer'          => PORTAL_URL . '/treasurer/dashboard.php',
        'eb_auditor'            => PORTAL_URL . '/auditor/dashboard.php',
        'eb_secretary_general'  => PORTAL_URL . '/secretary/dashboard.php',
    ];
    $redirectUrl = $redirectMap[$role] ?? PORTAL_URL . '/member/dashboard.php';
    header('Location: ' . $redirectUrl);
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // --- Test accounts for development (unchanged) ---
    $testAccounts = [
        'super.admin@iecep-lsc.test' => [
            'password' => 'SuperAdmin123!',
            'role' => 'eb_president',
            'name' => 'IECEP Super Admin',
            'redirect' => PORTAL_URL . '/super-admin/dashboard.php'
        ],
        'test.president@iecep-lsc.test' => [
            'password' => 'TestPresident123!',
            'role' => 'admin',
            'name' => 'School President',
            'redirect' => PORTAL_URL . '/admin/dashboard.php'
        ],
        'test.vp@iecep-lsc.test' => [
            'password' => 'TestVp123!',
            'role' => 'admin',
            'name' => 'School VP',
            'redirect' => PORTAL_URL . '/admin/dashboard.php'
        ],
        'test.adviser@iecep-lsc.test' => [
            'password' => 'TestAdviser123!',
            'role' => 'admin',
            'name' => 'School Adviser',
            'redirect' => PORTAL_URL . '/admin/dashboard.php'
        ],
        'test.committee@iecep-lsc.test' => [
            'password' => 'TestCommittee123!',
            'role' => 'admin',
            'name' => 'Registration Committee',
            'redirect' => PORTAL_URL . '/admin/dashboard.php'
        ],
        'test.treasurer@iecep-lsc.test' => [
            'password' => 'TestTreasurer123!',
            'role' => 'school_officer',
            'name' => 'School Treasurer',
            'redirect' => PORTAL_URL . '/school-officer/dashboard.php'
        ],
        'test.auditor@iecep-lsc.test' => [
            'password' => 'TestAuditor123!',
            'role' => 'school_officer',
            'name' => 'School Auditor',
            'redirect' => PORTAL_URL . '/school-officer/dashboard.php'
        ],
        'test.member1@iecep-lsc.test' => [
            'password' => 'TestMember123!',
            'role' => 'member',
            'name' => 'Student Member 1',
            'redirect' => PORTAL_URL . '/member/dashboard.php'
        ],
        'test.member2@iecep-lsc.test' => [
            'password' => 'TestMember123!',
            'role' => 'member',
            'name' => 'Student Member 2',
            'redirect' => PORTAL_URL . '/member/dashboard.php'
        ],
        'creatives.head@iecep-lsc.test' => [
            'password' => 'CreativesHead123!',
            'role' => 'eb_pro_1',
            'name' => 'PRO 1 - Creatives Head',
            'redirect' => PORTAL_URL . '/creatives/dashboard.php'
        ],
        'creatives.member@iecep-lsc.test' => [
            'password' => 'CreativesMember123!',
            'role' => 'committee_creatives',
            'name' => 'Creatives Committee Member',
            'redirect' => PORTAL_URL . '/creatives/dashboard.php'
        ],
        'new.member@iecep-lsc.test' => [
            'password' => 'NewMember123!',
            'role' => 'member',
            'name' => 'New Member',
            'redirect' => PORTAL_URL . '/member/dashboard.php',
            'must_change_password' => true
        ]
    ];

    if (isset($testAccounts[$email]) && $testAccounts[$email]['password'] === $password) {
        $mustChangePassword = $testAccounts[$email]['must_change_password'] ?? false;

        $_SESSION['user'] = [
            'email' => $email,
            'name' => $testAccounts[$email]['name'],
            'role' => $testAccounts[$email]['role'],
            'must_change_password' => $mustChangePassword
        ];
        $_SESSION['logged_in'] = true;
        $_SESSION['role'] = $testAccounts[$email]['role'];

        if ($mustChangePassword) {
            $_SESSION['require_password_change'] = true;
            header('Location: ' . BASE_URL . '/change-password.php?first=1');
            exit;
        }

        header('Location: ' . $testAccounts[$email]['redirect']);
        exit;
    }

    // --- Custom users table authentication (with service role to bypass RLS) ---
    try {
        require_once __DIR__ . '/includes/supabase.php';
        require_once __DIR__ . '/src/lib/SupabaseClient.php';

        $config = require __DIR__ . '/includes/supabase.php';

        // Use the service role key for the login query to bypass Row‑Level Security
        $supabaseService = new SupabaseClient($config['url'], $config['service_role_key']);
        $users = $supabaseService->select('users', ['email' => 'eq.' . $email]);

        if (!empty($users) && is_array($users)) {
            $user = $users[0];

            // Verify password (bcrypt)
            if (password_verify($password, $user['password'] ?? '')) {
                // Check if account is active
                if (empty($user['is_active'])) {
                    $error = 'Your account has been deactivated. Please contact the administrator.';
                } else {
                    // Successful login
                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['email']     = $user['email'];
                    $_SESSION['full_name'] = $user['full_name'] ?? '';
                    $_SESSION['role']      = $user['role'] ?? 'member';
                    $_SESSION['logged_in'] = true;

                    // Store user info in 'user' array as well (for compatibility)
                    $_SESSION['user'] = [
                        'id'    => $user['id'],
                        'email' => $user['email'],
                        'name'  => $user['full_name'] ?? '',
                        'role'  => $user['role'] ?? 'member',
                        'must_change_password' => !empty($user['must_change_password'])
                    ];

                    // Check if forced password change is required
                    if (!empty($user['must_change_password'])) {
                        $_SESSION['require_password_change'] = true;
                        header('Location: ' . BASE_URL . '/change-password.php?first=1');
                        exit;
                    }

                    // Redirect to role-based dashboard
                    $redirectMap = [
                        'school_officer' => PORTAL_URL . '/school-officer/dashboard.php',
                        'admin'          => PORTAL_URL . '/admin/dashboard.php',
                        'super_admin'    => PORTAL_URL . '/super-admin/dashboard.php',
                        'eb_president'   => PORTAL_URL . '/super-admin/dashboard.php',
                        'member'         => PORTAL_URL . '/member/dashboard.php',
                    ];
                    $role = $user['role'] ?? 'member';
                    $redirectUrl = $redirectMap[$role] ?? PORTAL_URL . '/member/dashboard.php';
                    header('Location: ' . $redirectUrl);
                    exit;
                }
            } else {
                $error = 'Invalid email or password. Please try again.';
            }
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        $error = 'A system error occurred. Please try again later.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Times+New+Roman:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            display: flex;
            min-height: 100vh;
            background: url('public/assets/icons/hero.png') center/cover no-repeat;
            position: relative;
            overflow: hidden;
        }
        .login-container { display: flex; width: 100%; min-height: 100vh; }
        .login-left {
            flex: 1; display: flex; flex-direction: column; justify-content: space-between;
            align-items: center; padding: 60px 40px; color: white; text-align: center;
            position: relative; z-index: 1;
        }
        .logo-container { margin-bottom: 40px; }
        .logo-container img {
            width: 200px; height: 200px; object-fit: contain;
            margin-left:20px; margin-right: -100px; margin-top: 50px; margin-bottom: 30px;
        }
        .organization-title {
            font-family: 'Times New Roman', Arial, serif;
            font-size: 2.2rem; font-weight: 800; margin-bottom: 20px;
            margin-left:20px; margin-right: -100px; line-height: 1.3; letter-spacing: 1px; word-spacing: 2px;
        }
        .organization-subtitle {
            font-family: 'Times New Roman', Times, serif;
            font-size: 1.7rem; font-weight: 700; margin-left:20px; margin-right: -100px; margin-bottom: 100px;
        }
        .tagline {
            font-size: 2rem; font-family: 'Times New Roman MT', Times, serif;
            margin-top: -250px; margin-bottom: 20px; margin-left: 20px; margin-right: -100px; line-height: 1.5;
        }
        .schools-row {
            display: flex; justify-content: center; align-items: center; gap: 15px;
            margin-top: 10px; margin-left:20px; margin-right: -100px; padding: 30px 0 0 0; flex-wrap: wrap;
        }
        .schools-row img {
            max-width: 80px; height: auto; transition: transform 0.3s ease;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3)); flex-shrink: 0;
        }
        .schools-row img:hover { transform: scale(1.1); }
        .login-right { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px; }
        .login-card {
            background: white; border-radius: 80px; padding: 50px 40px;
            width: 100%; max-width: 550px; box-shadow: 0 25px 50px rgba(0,0,0,0.2);
        }
        .login-title {
            font-size: 2.5rem; font-weight: 700; font-style: italic; color: #000;
            margin-bottom: 40px; text-align: center;
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
        .form-options { display: flex; justify-content: space-between; align-items: center; margin: 25px 0; font-size: 0.9rem; }
        .form-options label { display: flex; align-items: center; gap: 8px; color: #666; cursor: pointer; }
        .form-options input[type="checkbox"] { width: auto; margin: 0; }
        .form-options a { color: #0A2F6C; text-decoration: none; font-weight: 500; }
        .form-options a:hover { color: #F5A623; }
        .btn-login {
            width: 100%; background: #0A2F6C; color: white; border: none;
            padding: 16px; border-radius: 12px; font-size: 1rem; font-weight: 600;
            cursor: pointer; transition: all 0.3s; margin-bottom: 20px;
        }
        .btn-login:hover { background: #333; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.2); }
        .divider { display: flex; align-items: center; margin: 25px 0; color: #999; font-size: 0.9rem; }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #e2e8f0; }
        .divider span { padding: 0 15px; }
        .btn-google {
            width: 100%; background: white; color: #333; border: 2px solid #e2e8f0;
            padding: 14px; border-radius: 12px; font-size: 1rem; font-weight: 500;
            cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 12px; transition: 0.3s;
        }
        .btn-google:hover { border-color: #0A2F6C; background: #f8fafc; }
        .btn-google img { width: 20px; height: 20px; }
        .error-message {
            background: #fee2e2; color: #dc2626; padding: 12px 16px; border-radius: 8px;
            margin-bottom: 20px; font-size: 0.9rem; text-align: center; border-left: 4px solid #dc2626;
        }
        .test-accounts { background: #f8fafc; padding: 15px; border-radius: 8px; margin-top: 20px; font-size: 0.75rem; }
        .test-accounts h4 { color: #0A2F6C; margin-bottom: 10px; font-size: 0.85rem; }
        .test-accounts div { margin-bottom: 3px; color: #666; }
        .btn-back-home {
            display: inline-flex; align-items: center; gap: 8px; background: #f8fafc; color: #0A2F6C;
            text-decoration: none; padding: 12px 24px; border-radius: 8px; font-size: 0.9rem; font-weight: 500;
            border: 2px solid #e2e8f0; transition: all 0.3s ease;
        }
        .btn-back-home:hover { background: #0A2F6C; color: white; border-color: #0A2F6C; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(10,47,108,0.2); }
        @media (max-width: 768px) {
            .login-container { flex-direction: column; }
            .login-left { padding: 30px 20px; min-height: 40vh; }
            .organization-title { font-size: 1.5rem; }
            .organization-subtitle { font-size: 1.3rem; }
            .schools-row { gap: 8px; }
            .schools-row img { max-width: 80px; }
            .login-right { padding: 20px; min-height: 60vh; }
            .login-card { padding: 30px 25px; }
            .login-title { font-size: 2rem; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <div>
                <div class="logo-container">
                    <img src="<?php echo BASE_URL; ?>/public/assets/icons/iecep-logo.png" alt="IECEP-LSC Logo">
                </div>
                <h1 class="organization-title">INSTITUTE OF ELECTRONICS ENGINEERS OF THE PHILIPPINES</h1>
                <h2 class="organization-subtitle">LAGUNA STUDENT CHAPTER</h2>
            </div>
            <div style="width: 100%;">
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
        <div class="login-right">
            <div class="login-card">
                <h2 class="login-title">Log In</h2>
                <?php if ($error): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required placeholder="Enter your email">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="password" name="password" required placeholder="Enter your password">
                        </div>
                    </div>
                    <div class="form-options">
                        <label><input type="checkbox" name="remember"> Remember Me</label>
                        <a href="#">Forgot Password?</a>
                    </div>
                    <button type="submit" class="btn-login">Log in</button>
                    <div style="text-align: center; margin-bottom: 30px;">
                        <a href="index.php" class="btn-back-home"><i class="fas fa-arrow-left"></i> Back to Homepage</a>
                    </div>
                </form>
                <div class="test-accounts">
                    <h4><i class="fas fa-info-circle"></i> Test Accounts</h4>
                    <div><strong>Super Admin:</strong> super.admin@iecep-lsc.test / SuperAdmin123!</div>
                    <div><strong>President:</strong> test.president@iecep-lsc.test / TestPresident123!</div>
                    <div><strong>VP:</strong> test.vp@iecep-lsc.test / TestVp123!</div>
                    <div><strong>Adviser:</strong> test.adviser@iecep-lsc.test / TestAdviser123!</div>
                    <div><strong>Registration Committee:</strong> test.committee@iecep-lsc.test / TestCommittee123!</div>
                    <div><strong>Treasurer:</strong> test.treasurer@iecep-lsc.test / TestTreasurer123!</div>
                    <div><strong>Auditor:</strong> test.auditor@iecep-lsc.test / TestAuditor123!</div>
                    <div><strong>Member 1:</strong> test.member1@iecep-lsc.test / TestMember123!</div>
                    <div><strong>Member 2:</strong> test.member2@iecep-lsc.test / TestMember123!</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>