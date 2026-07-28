<?php
require_once __DIR__ . '/../bootstrap.php';
/**
 * Role Configuration - Navigation items for each role
 * Simplified to two roles: admin and school_officer
 */

// Define navigation items for each role
$ROLE_NAVIGATION = [
    'admin' => [
        'title' => 'Admin Dashboard',
        'role_display' => 'Administrator',
        'nav_items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/portal/admin/dashboard.php', 'active' => true],
            ['icon' => 'fa-users', 'label' => 'Members', 'url' => '/portal/admin/members/list.php'],
            ['icon' => 'fa-user', 'label' => 'Member Profile', 'url' => '/portal/admin/members/profile.php'],
            ['icon' => 'fa-file-import', 'label' => 'Batch Process', 'url' => '/portal/admin/members/batch-process.php'],
            ['icon' => 'fa-building', 'label' => 'Institutions', 'url' => '/portal/admin/institutions/list.php'],
            ['icon' => 'fa-calendar', 'label' => 'Events', 'url' => '/portal/admin/events/list.php'],
            ['icon' => 'fa-shield-alt', 'label' => 'Compliance', 'url' => '/portal/admin/compliance/dashboard.php'],
            ['icon' => 'fa-chart-line', 'label' => 'Financial Dashboard', 'url' => '/portal/admin/financial/dashboard.php'],
            ['icon' => 'fa-file-invoice-dollar', 'label' => 'Transactions', 'url' => '/portal/admin/financial/transactions.php'],
            ['icon' => 'fa-chart-bar', 'label' => 'Financial Reports', 'url' => '/portal/admin/financial/reports.php'],
            ['icon' => 'fa-balance-scale', 'label' => 'Transparency', 'url' => '/portal/admin/financial/transparency.php'],
            ['icon' => 'fa-file-alt', 'label' => 'Document Repository', 'url' => '/portal/admin/documents/repository.php'],
            ['icon' => 'fa-file-contract', 'label' => 'Memoranda', 'url' => '/portal/admin/documents/memoranda.php'],
            ['icon' => 'fa-bullhorn', 'label' => 'Announcements', 'url' => '/portal/admin/communication/announcements.php'],
            ['icon' => 'fa-star', 'label' => 'Featured Cards', 'url' => '/portal/admin/featured-cards.php'],
            ['icon' => 'fa-envelope', 'label' => 'Newsletter', 'url' => '/portal/admin/communication/newsletter.php'],
            ['icon' => 'fa-chart-pie', 'label' => 'Analytics', 'url' => '/portal/admin/analytics/dashboard.php'],
            ['icon' => 'fa-cog', 'label' => 'System Settings', 'url' => '/portal/admin/system/settings.php'],
            ['icon' => 'fa-users-cog', 'label' => 'User Management', 'url' => '/portal/admin/system/users.php'],
            ['icon' => 'fa-shield-alt', 'label' => 'Blockchain Health', 'url' => '/portal/admin/blockchain/health.php'],
            ['icon' => 'fa-link', 'label' => 'Blockchain Explorer', 'url' => '/portal/admin/blockchain/explorer.php'],
        ],
        'alert_message' => 'Welcome to the Admin Dashboard. From here you can manage all institutions, members, finances, compliance, documents, and system settings.',
    ],
    
    'school_officer' => [
        'title' => 'School Officer Dashboard',
        'role_display' => 'School Officer',
        'nav_items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/portal/school-officer/dashboard.php', 'active' => true],
            ['icon' => 'fa-users', 'label' => 'My Members', 'url' => '/portal/school-officer/members/list.php'],
            ['icon' => 'fa-file-import', 'label' => 'Upload Directory', 'url' => '/portal/school-officer/members/upload.php'],
            ['icon' => 'fa-shield-alt', 'label' => 'Compliance Status', 'url' => '/portal/school-officer/compliance/status.php'],
            ['icon' => 'fa-chart-line', 'label' => 'Financial Reports', 'url' => '/portal/school-officer/financial/reports.php'],
            ['icon' => 'fa-receipt', 'label' => 'Receipts', 'url' => '/portal/school-officer/financial/receipts.php'],
            ['icon' => 'fa-file-alt', 'label' => 'Documents', 'url' => '/portal/school-officer/documents/list.php'],
            ['icon' => 'fa-file-contract', 'label' => 'Memoranda', 'url' => '/portal/school-officer/memoranda/list.php'],
            ['icon' => 'fa-bullhorn', 'label' => 'Announcements', 'url' => '/portal/school-officer/announcements/list.php'],
            ['icon' => 'fa-id-card', 'label' => 'Digital ID', 'url' => '/portal/school-officer/digital-id/send.php'],
        ],
        'alert_message' => 'Manage your school chapter members, view compliance status, access financial reports, and upload documents.',
    ],
    
    'member' => [
        'title' => 'Member Dashboard',
        'role_display' => 'Member',
        'nav_items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/portal/member/dashboard.php', 'active' => true],
            ['icon' => 'fa-id-card', 'label' => 'Digital ID', 'url' => '/portal/member/digital-id.php'],
            ['icon' => 'fa-user', 'label' => 'Profile', 'url' => '/portal/member/profile.php'],
            ['icon' => 'fa-calendar', 'label' => 'Events', 'url' => '/portal/member/events.php'],
            ['icon' => 'fa-file-invoice-dollar', 'label' => 'Payments', 'url' => '/portal/member/payments.php'],
            ['icon' => 'fa-sign-out-alt', 'label' => 'Logout', 'url' => '/logout.php'],
        ],
        'alert_message' => 'Welcome to your member portal. View your digital ID, profile, upcoming events, and payment history.',
    ],
];

/**
 * Get role configuration
 * 
 * @param string $role The user role
 * @return array|null Role configuration or null if not found
 */
function getRoleConfig(string $role): ?array {
    global $ROLE_NAVIGATION;
    return $ROLE_NAVIGATION[$role] ?? null;
}

/**
 * Get all available roles
 * 
 * @return array List of all role keys
 */
function getAllRoles(): array {
    global $ROLE_NAVIGATION;
    return array_keys($ROLE_NAVIGATION);
}

/**
 * Check if role exists
 * 
 * @param string $role The user role
 * @return bool True if role exists
 */
function roleExists(string $role): bool {
    global $ROLE_NAVIGATION;
    return isset($ROLE_NAVIGATION[$role]);
}
