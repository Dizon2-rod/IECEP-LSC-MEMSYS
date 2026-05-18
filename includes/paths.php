<?php
/**
 * paths.php - Centralized Path Configuration for IECEP-LSC MEMSYS
 * This file defines all base paths to ensure consistency across the application
 */

// __DIR__ is the includes/ folder, so its parent is the project root
$rootPath = dirname(__DIR__);
$publicPath = $rootPath . '/public';

// Define base paths as constants
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $rootPath);
}
if (!defined('PUBLIC_PATH')) {
    define('PUBLIC_PATH', $publicPath);
}
if (!defined('SRC_PATH')) {
    define('SRC_PATH', BASE_PATH . '/src/');
}
if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', SRC_PATH . 'config/');
}
if (!defined('LIB_PATH')) {
    define('LIB_PATH', SRC_PATH . 'lib/');
}
if (!defined('API_PATH')) {
    define('API_PATH', SRC_PATH . 'api/');
}
if (!defined('INCLUDES_PATH')) {
    define('INCLUDES_PATH', BASE_PATH . '/includes/');
}
if (!defined('PORTAL_PATH')) {
    define('PORTAL_PATH', PUBLIC_PATH . '/portal/');
}
if (!defined('ASSETS_PATH')) {
    define('ASSETS_PATH', PUBLIC_PATH . '/assets/');
}
if (!defined('CSS_PATH')) {
    define('CSS_PATH', PUBLIC_PATH . '/css/');
}
if (!defined('JS_PATH')) {
    define('JS_PATH', PUBLIC_PATH . '/js/');
}

// Web-accessible base URL (adjust based on your server configuration)
if (!defined('BASE_URL')) {
    if (defined('APP_URL') && APP_URL !== '') {
        define('BASE_URL', APP_URL);
    } else {
        define('BASE_URL', '/IECEP-LSC-MEMSYS');
    }
}
if (!defined('PUBLIC_URL')) {
    define('PUBLIC_URL', BASE_URL . '/public');
}
if (!defined('BASE_PUBLIC_URL')) {
    define('BASE_PUBLIC_URL', PUBLIC_URL);
}
if (!defined('PORTAL_URL')) {
    define('PORTAL_URL', PUBLIC_URL . '/portal');
}
if (!defined('ASSETS_URL')) {
    define('ASSETS_URL', PUBLIC_URL . '/assets');
}
if (!defined('CSS_URL')) {
    define('CSS_URL', PUBLIC_URL . '/css');
}
if (!defined('JS_URL')) {
    define('JS_URL', PUBLIC_URL . '/js');
}
if (!defined('API_URL')) {
    define('API_URL', PUBLIC_URL . '/api');
}
if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', BASE_PATH . '/storage/');
}
if (!defined('STORAGE_URL')) {
    define('STORAGE_URL', PUBLIC_URL . '/storage');
}

/**
 * Get absolute file path from relative path
 * @param string $relativePath - Relative path from project root
 * @return string - Absolute file path
 */
function get_path($relativePath) {
    return BASE_PATH . '/' . ltrim($relativePath, '/');
}

/**
 * Get web URL for a file
 * @param string $relativePath - Relative path from public directory
 * @return string - Full web URL
 */
function get_url($relativePath) {
    return PUBLIC_URL . '/' . ltrim($relativePath, '/');
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
