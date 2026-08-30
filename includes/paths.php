<?php
require_once __DIR__ . '/../bootstrap.php';
/**
 * paths.php - Additional Path Configuration for IECEP-LSC MEMSYS
 * This file defines role-specific paths and functions
 */

// Define base paths if not already defined by bootstrap.php
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(dirname(__FILE__)));
}
if (!defined('SRC_PATH')) {
    define('SRC_PATH', BASE_PATH . '/src/');
}
if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', BASE_PATH . '/public');
}

// Define additional constants if not already defined
if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', SRC_PATH . 'config/');
}
if (!defined('LIB_PATH')) {
    define('LIB_PATH', SRC_PATH . 'lib/');
}
if (!defined('API_PATH')) {
    define('API_PATH', SRC_PATH . 'api/');
}
if (!defined('PORTAL_PATH')) {
    define('PORTAL_PATH', PUBLIC_PATH . '/portal/');
}
if (!defined('CSS_PATH')) {
    define('CSS_PATH', PUBLIC_PATH . '/css/');
}
if (!defined('JS_PATH')) {
    define('JS_PATH', PUBLIC_PATH . '/js/');
}

/**
 * Normalize any role alias or casing to standard role key: 'super_admin' | 'admin' | 'school_officer' | 'member'
 * @param string|null $role
 * @return string
 */
function normalize_user_role($role) {
    $r = strtolower(trim((string)$role));
    
    // Super Admin
    if (in_array($r, ['super_admin', 'superadmin', 'super_administrator'])) {
        return 'super_admin';
    }
    
    // Admin / Executive Board
    if (in_array($r, [
        'admin', 'administrator', 'regional_admin',
        'eb_president', 'eb_vp_internal', 'eb_treasurer', 'eb_auditor',
        'eb_pro_1', 'eb_pro_2', 'eb_secretary_general',
        'committee_registration', 'committee_creatives', 'committee_marketing', 'committee_logistics'
    ])) {
        return 'admin';
    }
    
    // School Officer
    if (in_array($r, ['school_officer', 'school_admin', 'officer', 'school-officer'])) {
        return 'school_officer';
    }
    
    // Member / Student
    return 'member';
}

/**
 * Get portal URL for a role-specific page
 * @param string $role - User role
 * @param string $page - Page name (e.g., 'dashboard.php')
 * @return string - Full portal URL
 */
function get_portal_url($role, $page = 'dashboard.php') {
    $rolePath = get_role_path($role);
    return PORTAL_URL . '/' . $rolePath . '/' . ltrim($page, '/');
}

/**
 * Get the portal directory path for a role
 * @param string $role - User role
 * @return string - Directory path
 */
function get_role_path($role) {
    $normalized = normalize_user_role($role);
    $rolePaths = [
        'super_admin'    => 'admin',
        'admin'          => 'admin',
        'school_officer' => 'school-officer',
        'member'         => 'member',
    ];
    
    return $rolePaths[$normalized] ?? 'member';
}

/**
 * Get the appropriate dashboard URL for any user role
 * @param string|null $role
 * @return string
 */
function get_role_dashboard_url($role) {
    $normalized = normalize_user_role($role);
    if ($normalized === 'super_admin' || $normalized === 'admin') {
        return PORTAL_URL . '/admin/dashboard.php';
    } elseif ($normalized === 'school_officer') {
        return PORTAL_URL . '/school-officer/dashboard.php';
    } else {
        return PORTAL_URL . '/member/dashboard.php';
    }
}
?>
