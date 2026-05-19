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
 * Get portal URL for a role-specific page
 * @param string $role - User role
 * @param string $page - Page name (e.g., 'dashboard.php')
 * @return string - Full portal URL
 */
function get_portal_url($role, $page = 'dashboard.php') {
    $rolePath = get_role_path($role);
    return PORTAL_URL . '/' . $rolePath . '/' . $page;
}

/**
 * Get the portal directory path for a role
 * @param string $role - User role
 * @return string - Directory path
 */
function get_role_path($role) {
    $rolePaths = [
        'eb_president' => 'super-admin',
        'eb_vp_internal' => 'registration',
        'eb_treasurer' => 'treasurer',
        'eb_auditor' => 'auditor',
        'eb_pro_1' => 'creatives',
        'eb_pro_2' => 'logistics',
        'eb_secretary_general' => 'secretary',
        'committee_registration' => 'registration',
        'committee_creatives' => 'creatives',
        'committee_marketing' => 'marketing',
        'committee_logistics' => 'logistics',
        'committee_documentation' => 'committee/documentation',
        'committee_technical' => 'committee/technical',
        'school_officer' => 'school-officer',
        'member' => 'member',
        'admin' => 'admin',
    ];
    
    return $rolePaths[$role] ?? 'member';
}
?>
