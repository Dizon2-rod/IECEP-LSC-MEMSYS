<?php
// auth_check.php - Central Authorization Checker for IECEP-LSC MEMSYS
session_start();

/**
 * require_role - Check if current user has required role
 * @param array $allowed_roles - Array of allowed role strings
 * @param bool $redirect - Whether to redirect unauthorized users (default: true)
 * @return bool - True if authorized, false otherwise
 */
function require_role($allowed_roles, $redirect = true) {
    // Check if user is logged in
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['role'])) {
        if ($redirect) {
            header('Location: /IECEP-LSC-MEMSYS/login.php');
            exit;
        }
        return false;
    }

    $user_role = $_SESSION['user']['role'];


    // Check if user role is in allowed roles
    if (!in_array($user_role, $allowed_roles)) {
        if ($redirect) {
            // Redirect to user's appropriate dashboard or show access denied
            $user_dashboard = get_role_dashboard($user_role);
            if ($user_dashboard) {
                header('Location: ' . $user_dashboard);
            } else {
                // If no dashboard available, show access denied
                http_response_code(403);
                echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Inter", sans-serif;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        :root {
            --navy: #0A2F6C;
            --gold: #F5A623;
            --red: #dc2626;
        }
        .access-denied {
            max-width: 500px;
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            padding: 48px;
            text-align: center;
        }
        .icon {
            width: 80px;
            height: 80px;
            background: var(--red);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            color: white;
            font-size: 2rem;
        }
        h1 { color: var(--navy); margin-bottom: 16px; font-size: 1.8rem; }
        p { color: #64748b; margin-bottom: 32px; line-height: 1.6; }
        .btn {
            display: inline-block;
            background: var(--navy);
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn:hover { background: var(--gold); color: var(--navy); }
    </style>
</head>
<body>
    <div class="access-denied">
        <div class="icon">🔒</div>
        <h1>Access Denied</h1>
        <p>You don\'t have permission to access this page. Please contact your administrator if you believe this is an error.</p>
        <a href="/IECEP-LSC-MEMSYS/login.php" class="btn">Return to Login</a>
    </div>
</body>
</html>';
            }
            exit;
        }
        return false;
    }

    return true;
}

/**
 * get_role_dashboard - Get the appropriate dashboard URL for a role
 * @param string $role - User role
 * @return string|null - Dashboard URL or null if not found
 */
function get_role_dashboard($role) {
    $role_dashboards = [
        'eb_president' => '/IECEP-LSC-MEMSYS/public/portal/super-admin/dashboard.php',
        'eb_vp_internal' => '/IECEP-LSC-MEMSYS/public/portal/registration/dashboard.php',
        'eb_treasurer' => '/IECEP-LSC-MEMSYS/public/portal/treasurer/dashboard.php',
        'eb_auditor' => '/IECEP-LSC-MEMSYS/public/portal/auditor/dashboard.php',
        'eb_pro_1' => '/IECEP-LSC-MEMSYS/public/portal/creatives/dashboard.php',
        'eb_pro_2' => '/IECEP-LSC-MEMSYS/public/portal/logistics/dashboard.php',
        'eb_secretary_general' => '/IECEP-LSC-MEMSYS/public/portal/secretary/dashboard.php',
        'committee_registration' => '/IECEP-LSC-MEMSYS/public/portal/registration/dashboard.php',
        'committee_creatives' => '/IECEP-LSC-MEMSYS/public/portal/creatives/dashboard.php',
        'committee_marketing' => '/IECEP-LSC-MEMSYS/public/portal/marketing/dashboard.php',
        'committee_logistics' => '/IECEP-LSC-MEMSYS/public/portal/logistics/dashboard.php',
        'school_officer' => '/IECEP-LSC-MEMSYS/public/portal/officer/dashboard.php',
        'member' => '/IECEP-LSC-MEMSYS/public/portal/member/dashboard.php'
    ];
    
    return $role_dashboards[$role] ?? null;
}

/**
 * get_user_info - Get current user information
 * @return array|null - User info or null if not logged in
 */
function get_user_info() {
    return $_SESSION['user'] ?? null;
}

/**
 * is_logged_in - Check if user is logged in
 * @return bool - True if logged in
 */
function is_logged_in() {
    return isset($_SESSION['user']) && isset($_SESSION['user']['id']);
}

/**
 * get_role_display_name - Get display name for a role
 * @param string $role - Role string
 * @return string - Display name
 */
function get_role_display_name($role) {
    $role_names = [
        'eb_president' => 'EB President',
        'eb_vp_internal' => 'EB VP Internal',
        'eb_treasurer' => 'EB Treasurer',
        'eb_auditor' => 'EB Auditor',
        'eb_pro_1' => 'EB PRO 1',
        'eb_pro_2' => 'EB PRO 2',
        'eb_secretary_general' => 'EB Secretary General',
        'committee_registration' => 'Registration Committee',
        'committee_creatives' => 'Creatives Committee',
        'committee_marketing' => 'Marketing Committee',
        'committee_logistics' => 'Logistics Committee',
        'school_officer' => 'School Officer',
        'member' => 'Member'
    ];
    
    return $role_names[$role] ?? $role;
}
?>
