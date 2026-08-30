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

/**
 * Normalize and unpack a pending_affiliations record from Supabase
 * Ensures institution_name, documents URLs, and roster counts are directly accessible
 * @param array|null $app
 * @return array
 */
function normalize_pending_affiliation_app($app) {
    if (!is_array($app)) return [];
    
    $docs = [];
    if (!empty($app['documents'])) {
        if (is_string($app['documents'])) {
            $decoded = json_decode($app['documents'], true);
            if (is_array($decoded)) {
                $docs = $decoded;
            }
        } elseif (is_array($app['documents'])) {
            $docs = $app['documents'];
        }
    }

    $instName = !empty($app['school_name']) ? $app['school_name'] : ($docs['institution_name'] ?? ($app['institution_name'] ?? 'School Application'));
    $app['institution_name'] = $instName;
    $app['school_name'] = $instName;
    $app['institution_address'] = $docs['institution_address'] ?? ($app['institution_address'] ?? 'Laguna, Philippines');
    $app['contact_position'] = $docs['contact_position'] ?? ($app['contact_position'] ?? 'School Officer');
    $app['contact_email'] = $app['email'] ?? ($docs['contact_email'] ?? ($app['contact_email'] ?? ''));
    $app['email'] = $app['contact_email'];
    $app['contact_phone'] = $app['contact_number'] ?? ($docs['contact_phone'] ?? ($app['contact_phone'] ?? ''));
    $app['contact_number'] = $app['contact_phone'];

    $app['letter_of_intent'] = $docs['letter_of_intent'] ?? ($app['letter_of_intent'] ?? null);
    $app['endorsement_letter'] = $docs['endorsement_letter'] ?? ($app['endorsement_letter'] ?? null);
    $app['constitution_by_laws'] = $docs['constitution_by_laws'] ?? ($app['constitution_by_laws'] ?? null);
    $app['officers_cvs'] = $docs['officers_cvs'] ?? ($app['officers_cvs'] ?? null);
    $app['organizational_chart'] = $docs['organizational_chart'] ?? ($app['organizational_chart'] ?? null);
    $app['member_directory'] = $docs['member_directory'] ?? ($app['member_directory'] ?? null);

    $app['total_members'] = intval($docs['total_members'] ?? ($app['total_members'] ?? 0));
    $app['new_members'] = intval($docs['new_members'] ?? ($app['new_members'] ?? 0));
    $app['old_members'] = intval($docs['old_members'] ?? ($app['old_members'] ?? 0));
    $app['affiliation_fee'] = floatval($docs['affiliation_fee'] ?? ($app['affiliation_fee'] ?? 0));
    $app['membership_total'] = floatval($docs['membership_total'] ?? ($app['membership_total'] ?? 0));
    $app['total_fee'] = floatval($docs['total_fee'] ?? ($app['total_fee'] ?? 0));
    $app['receipt_number'] = $docs['receipt_number'] ?? ($app['receipt_number'] ?? '');
    
    // Also keep parsed array in documents field
    $app['documents_parsed'] = $docs;

    return $app;
}
?>
