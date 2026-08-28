<?php
require_once __DIR__ . '/../bootstrap.php';
/**
 * Role Configuration - Navigation items for each role
 * Simplified to two roles: admin and school_officer
 */

$ROLE_NAVIGATION = [
    'super_admin' => [
        'title' => 'Admin Portal',
        'role_display' => 'Admin Portal',
        'nav_items' => [
            ['icon' => 'fa-gauge-high', 'label' => 'Dashboard', 'url' => 'portal/admin/dashboard.php', 'active' => true],
            
            ['icon' => 'fa-users', 'label' => 'Members', 'url' => 'portal/admin/members/list.php', 'children' => [
                ['icon' => 'fa-users', 'label' => 'Members Roster', 'url' => 'portal/admin/members/list.php'],
                ['icon' => 'fa-user', 'label' => 'Member Profile', 'url' => 'portal/admin/members/profile.php'],
                ['icon' => 'fa-file-import', 'label' => 'Batch Processing', 'url' => 'portal/admin/members/batch-process.php'],
                ['icon' => 'fa-cloud-upload-alt', 'label' => 'Import Members', 'url' => 'portal/admin/import-members.php'],
                ['icon' => 'fa-list-check', 'label' => 'Directory Batches', 'url' => 'portal/admin/list-batches.php'],
            ]],

            ['icon' => 'fa-building-columns', 'label' => 'Institutions', 'url' => 'portal/admin/institutions/list.php'],
            
            ['icon' => 'fa-calendar-days', 'label' => 'Events', 'url' => 'portal/admin/events/list.php'],
            
            ['icon' => 'fa-shield-halved', 'label' => 'Compliance', 'url' => 'portal/admin/compliance/dashboard.php', 'children' => [
                ['icon' => 'fa-chart-pie', 'label' => 'Compliance Dashboard', 'url' => 'portal/admin/compliance/dashboard.php'],
                ['icon' => 'fa-file-signature', 'label' => 'Compliance Reports', 'url' => 'portal/admin/compliance/reports.php'],
                ['icon' => 'fa-clipboard-check', 'label' => 'Policy Compliance', 'url' => 'portal/admin/policy-compliance.php'],
            ]],

            ['icon' => 'fa-chart-line', 'label' => 'Financials', 'url' => 'portal/admin/financial/dashboard.php', 'children' => [
                ['icon' => 'fa-chart-line', 'label' => 'Financial Dashboard', 'url' => 'portal/admin/financial/dashboard.php'],
                ['icon' => 'fa-file-invoice-dollar', 'label' => 'Transactions', 'url' => 'portal/admin/financial/transactions.php'],
                ['icon' => 'fa-chart-bar', 'label' => 'Financial Reports', 'url' => 'portal/admin/financial/reports.php'],
                ['icon' => 'fa-receipt', 'label' => 'Receipt Verification', 'url' => 'portal/admin/financial/receipt.php'],
                ['icon' => 'fa-scale-balanced', 'label' => 'Transparency Ledger', 'url' => 'portal/admin/financial/transparency.php'],
            ]],

            ['icon' => 'fa-folder-open', 'label' => 'Documents', 'url' => 'portal/admin/documents/repository.php', 'children' => [
                ['icon' => 'fa-box-archive', 'label' => 'Repository', 'url' => 'portal/admin/documents/repository.php'],
                ['icon' => 'fa-file-contract', 'label' => 'Memoranda', 'url' => 'portal/admin/documents/memoranda.php'],
            ]],

            ['icon' => 'fa-shirt', 'label' => 'Merchandise', 'url' => 'portal/admin/merch/items.php', 'children' => [
                ['icon' => 'fa-boxes-stacked', 'label' => 'Catalog Items', 'url' => 'portal/admin/merch/items.php'],
                ['icon' => 'fa-dolly', 'label' => 'Orders', 'url' => 'portal/admin/merch/orders.php'],
            ]],

            ['icon' => 'fa-bullhorn', 'label' => 'Communication', 'url' => 'portal/admin/communication/announcements.php', 'children' => [
                ['icon' => 'fa-bullhorn', 'label' => 'Announcements', 'url' => 'portal/admin/communication/announcements.php'],
                ['icon' => 'fa-newspaper', 'label' => 'Newsletter', 'url' => 'portal/admin/communication/newsletter.php'],
                ['icon' => 'fa-envelope', 'label' => 'Messages', 'url' => 'portal/admin/messages.php'],
                ['icon' => 'fa-inbox', 'label' => 'Contact Inquiries', 'url' => 'portal/admin/contact-messages.php'],
                ['icon' => 'fa-square-poll-vertical', 'label' => 'Surveys', 'url' => 'portal/admin/surveys.php'],
            ]],

            ['icon' => 'fa-chart-simple', 'label' => 'Analytics', 'url' => 'portal/admin/analytics/dashboard.php'],

            ['icon' => 'fa-link', 'label' => 'Blockchain', 'url' => 'portal/admin/blockchain/explorer.php', 'children' => [
                ['icon' => 'fa-cube', 'label' => 'Explorer Dashboard', 'url' => 'portal/admin/blockchain/explorer.php'],
                ['icon' => 'fa-heart-pulse', 'label' => 'Ledger Health', 'url' => 'portal/admin/blockchain/health.php'],
                ['icon' => 'fa-id-card', 'label' => 'Digital IDs', 'url' => 'portal/admin/digital-id.php'],
            ]],

            ['icon' => 'fa-sliders', 'label' => 'System Control', 'url' => 'portal/admin/system/settings.php', 'children' => [
                ['icon' => 'fa-gear', 'label' => 'System Settings', 'url' => 'portal/admin/system/settings.php'],
                ['icon' => 'fa-users-gear', 'label' => 'User Management', 'url' => 'portal/admin/system/users.php'],
                ['icon' => 'fa-brain', 'label' => 'Decision Support', 'url' => 'portal/admin/decision-support.php'],
                ['icon' => 'fa-star', 'label' => 'Featured Cards', 'url' => 'portal/admin/featured-cards.php'],
                ['icon' => 'fa-shield-halved', 'label' => 'Enable 2FA', 'url' => 'portal/admin/enable-2fa.php'],
            ]],
        ],
        'alert_message' => 'Super Admin Command Active. Root-level governance, cryptographic audit trails, and multi-school administrative controls are unlocked.',
    ],

    'admin' => [
        'title' => 'Admin Portal',
        'role_display' => 'Admin Portal',
        'nav_items' => [
            ['icon' => 'fa-gauge-high', 'label' => 'Dashboard', 'url' => 'portal/admin/dashboard.php', 'active' => true],
            
            ['icon' => 'fa-users', 'label' => 'Members', 'url' => 'portal/admin/members/list.php', 'children' => [
                ['icon' => 'fa-users', 'label' => 'Members Roster', 'url' => 'portal/admin/members/list.php'],
                ['icon' => 'fa-user', 'label' => 'Member Profile', 'url' => 'portal/admin/members/profile.php'],
                ['icon' => 'fa-file-import', 'label' => 'Batch Processing', 'url' => 'portal/admin/members/batch-process.php'],
                ['icon' => 'fa-cloud-upload-alt', 'label' => 'Import Members', 'url' => 'portal/admin/import-members.php'],
                ['icon' => 'fa-list-check', 'label' => 'Directory Batches', 'url' => 'portal/admin/list-batches.php'],
            ]],

            ['icon' => 'fa-building-columns', 'label' => 'Institutions', 'url' => 'portal/admin/institutions/list.php'],
            
            ['icon' => 'fa-calendar-days', 'label' => 'Events', 'url' => 'portal/admin/events/list.php'],
            
            ['icon' => 'fa-shield-halved', 'label' => 'Compliance', 'url' => 'portal/admin/compliance/dashboard.php', 'children' => [
                ['icon' => 'fa-chart-pie', 'label' => 'Compliance Dashboard', 'url' => 'portal/admin/compliance/dashboard.php'],
                ['icon' => 'fa-file-signature', 'label' => 'Compliance Reports', 'url' => 'portal/admin/compliance/reports.php'],
                ['icon' => 'fa-clipboard-check', 'label' => 'Policy Compliance', 'url' => 'portal/admin/policy-compliance.php'],
            ]],

            ['icon' => 'fa-chart-line', 'label' => 'Financials', 'url' => 'portal/admin/financial/dashboard.php', 'children' => [
                ['icon' => 'fa-chart-line', 'label' => 'Financial Dashboard', 'url' => 'portal/admin/financial/dashboard.php'],
                ['icon' => 'fa-file-invoice-dollar', 'label' => 'Transactions', 'url' => 'portal/admin/financial/transactions.php'],
                ['icon' => 'fa-chart-bar', 'label' => 'Financial Reports', 'url' => 'portal/admin/financial/reports.php'],
                ['icon' => 'fa-receipt', 'label' => 'Receipt Verification', 'url' => 'portal/admin/financial/receipt.php'],
                ['icon' => 'fa-scale-balanced', 'label' => 'Transparency Ledger', 'url' => 'portal/admin/financial/transparency.php'],
            ]],

            ['icon' => 'fa-folder-open', 'label' => 'Documents', 'url' => 'portal/admin/documents/repository.php', 'children' => [
                ['icon' => 'fa-box-archive', 'label' => 'Repository', 'url' => 'portal/admin/documents/repository.php'],
                ['icon' => 'fa-file-contract', 'label' => 'Memoranda', 'url' => 'portal/admin/documents/memoranda.php'],
            ]],

            ['icon' => 'fa-shirt', 'label' => 'Merchandise', 'url' => 'portal/admin/merch/items.php', 'children' => [
                ['icon' => 'fa-boxes-stacked', 'label' => 'Catalog Items', 'url' => 'portal/admin/merch/items.php'],
                ['icon' => 'fa-dolly', 'label' => 'Orders', 'url' => 'portal/admin/merch/orders.php'],
            ]],

            ['icon' => 'fa-bullhorn', 'label' => 'Communication', 'url' => 'portal/admin/communication/announcements.php', 'children' => [
                ['icon' => 'fa-bullhorn', 'label' => 'Announcements', 'url' => 'portal/admin/communication/announcements.php'],
                ['icon' => 'fa-newspaper', 'label' => 'Newsletter', 'url' => 'portal/admin/communication/newsletter.php'],
                ['icon' => 'fa-envelope', 'label' => 'Messages', 'url' => 'portal/admin/messages.php'],
                ['icon' => 'fa-inbox', 'label' => 'Contact Inquiries', 'url' => 'portal/admin/contact-messages.php'],
                ['icon' => 'fa-square-poll-vertical', 'label' => 'Surveys', 'url' => 'portal/admin/surveys.php'],
            ]],

            ['icon' => 'fa-chart-simple', 'label' => 'Analytics', 'url' => 'portal/admin/analytics/dashboard.php'],

            ['icon' => 'fa-link', 'label' => 'Blockchain', 'url' => 'portal/admin/blockchain/explorer.php', 'children' => [
                ['icon' => 'fa-cube', 'label' => 'Explorer Dashboard', 'url' => 'portal/admin/blockchain/explorer.php'],
                ['icon' => 'fa-heart-pulse', 'label' => 'Ledger Health', 'url' => 'portal/admin/blockchain/health.php'],
                ['icon' => 'fa-id-card', 'label' => 'Digital IDs', 'url' => 'portal/admin/digital-id.php'],
            ]],

            ['icon' => 'fa-sliders', 'label' => 'System Control', 'url' => 'portal/admin/system/settings.php', 'children' => [
                ['icon' => 'fa-gear', 'label' => 'System Settings', 'url' => 'portal/admin/system/settings.php'],
                ['icon' => 'fa-users-gear', 'label' => 'User Management', 'url' => 'portal/admin/system/users.php'],
                ['icon' => 'fa-brain', 'label' => 'Decision Support', 'url' => 'portal/admin/decision-support.php'],
                ['icon' => 'fa-star', 'label' => 'Featured Cards', 'url' => 'portal/admin/featured-cards.php'],
                ['icon' => 'fa-shield-halved', 'label' => 'Enable 2FA', 'url' => 'portal/admin/enable-2fa.php'],
            ]],
        ],
        'alert_message' => 'Welcome to the Admin Dashboard. From here you can manage all institutions, members, finances, compliance, documents, and system settings.',
    ],
    
    'school_officer' => [
        'title' => 'School Officer Portal',
        'role_display' => 'School Officer',
        'nav_items' => [
            ['icon' => 'fa-gauge-high', 'label' => 'Dashboard', 'url' => 'portal/school-officer/dashboard.php', 'active' => true],
            
            ['icon' => 'fa-users', 'label' => 'Members', 'url' => 'portal/school-officer/members/list.php', 'children' => [
                ['icon' => 'fa-users', 'label' => 'Chapter Roster', 'url' => 'portal/school-officer/members/list.php'],
                ['icon' => 'fa-cloud-arrow-up', 'label' => 'Upload Directory', 'url' => 'portal/school-officer/members/upload.php'],
                ['icon' => 'fa-clipboard-user', 'label' => 'Attendance', 'url' => 'portal/school-officer/attendance.php'],
            ]],
            
            ['icon' => 'fa-shield-halved', 'label' => 'Compliance', 'url' => 'portal/school-officer/compliance/status.php'],
            
            ['icon' => 'fa-chart-line', 'label' => 'Financials', 'url' => 'portal/school-officer/financial/reports.php', 'children' => [
                ['icon' => 'fa-chart-bar', 'label' => 'Financial Reports', 'url' => 'portal/school-officer/financial/reports.php'],
                ['icon' => 'fa-receipt', 'label' => 'Receipts', 'url' => 'portal/school-officer/financial/receipts.php'],
                ['icon' => 'fa-hand-holding-dollar', 'label' => 'Fee Waiver Requests', 'url' => 'portal/school-officer/financial/fee-waiver.php'],
            ]],
            
            ['icon' => 'fa-folder-open', 'label' => 'Documents', 'url' => 'portal/school-officer/documents/list.php', 'children' => [
                ['icon' => 'fa-box-archive', 'label' => 'Document Repository', 'url' => 'portal/school-officer/documents/list.php'],
                ['icon' => 'fa-file-contract', 'label' => 'Chapter Memoranda', 'url' => 'portal/school-officer/memoranda/list.php'],
            ]],
            
            ['icon' => 'fa-bullhorn', 'label' => 'Announcements', 'url' => 'portal/school-officer/announcements/list.php'],
            ['icon' => 'fa-id-card', 'label' => 'Digital ID Dispatch', 'url' => 'portal/school-officer/digital-id/send.php'],
            ['icon' => 'fa-user-gear', 'label' => 'Officer Profile', 'url' => 'portal/school-officer/profile.php'],
        ],
        'alert_message' => 'Manage your school chapter members, view compliance status, access financial reports, and upload documents.',
    ],
    
    'member' => [
        'title' => 'Member Portal',
        'role_display' => 'Member',
        'nav_items' => [
            ['icon' => 'fa-gauge-high', 'label' => 'Dashboard', 'url' => 'portal/member/dashboard.php', 'active' => true],
            ['icon' => 'fa-id-card', 'label' => 'Digital ID', 'url' => 'portal/member/digital-id.php'],
            ['icon' => 'fa-user', 'label' => 'Profile', 'url' => 'portal/member/profile.php'],
            ['icon' => 'fa-calendar-days', 'label' => 'Events', 'url' => 'portal/member/events.php'],
            ['icon' => 'fa-file-invoice-dollar', 'label' => 'Payments', 'url' => 'portal/member/payments.php'],
            ['icon' => 'fa-certificate', 'label' => 'Certificates', 'url' => 'portal/member/certificate.php'],
            ['icon' => 'fa-square-poll-vertical', 'label' => 'Surveys', 'url' => 'portal/member/surveys.php'],
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
