<?php
/**
 * Role Configuration - Navigation items for each role
 */

// Define navigation items for each role
$ROLE_NAVIGATION = [
    'eb_president' => [
        'title' => 'Super Admin Dashboard',
        'role_display' => 'Super Admin',
        'nav_items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/super-admin/dashboard.php', 'active' => true],
            ['icon' => 'fa-building', 'label' => 'Affiliations', 'url' => '/super-admin/affiliations.php'],
            ['icon' => 'fa-users', 'label' => 'Members', 'url' => '/super-admin/members.php'],
            ['icon' => 'fa-shield-alt', 'label' => 'Compliance', 'url' => '/super-admin/compliance.php'],
            ['icon' => 'fa-chart-line', 'label' => 'Reports', 'url' => '/super-admin/reports.php'],
            ['icon' => 'fa-cog', 'label' => 'Settings', 'url' => '/super-admin/settings.php'],
            ['icon' => 'fa-user-shield', 'label' => 'User Management', 'url' => '/super-admin/users.php'],
            ['icon' => 'fa-handshake', 'label' => 'Collaboration', 'url' => '/collaboration/index.php'],
            ['icon' => 'fa-history', 'label' => 'Audit Logs', 'url' => '/super-admin/audit.php'],
        ],
        'alert_message' => 'You have full system control including user management, school administration, and system operations.',
    ],
    
    'super_admin' => [
        'title' => 'Super Admin Dashboard',
        'role_display' => 'Super Admin',
        'nav_items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/super-admin/dashboard.php', 'active' => true],
            ['icon' => 'fa-building', 'label' => 'Affiliations', 'url' => '/super-admin/affiliations.php'],
            ['icon' => 'fa-users', 'label' => 'Members', 'url' => '/super-admin/members.php'],
            ['icon' => 'fa-shield-alt', 'label' => 'Compliance', 'url' => '/super-admin/compliance.php'],
            ['icon' => 'fa-chart-line', 'label' => 'Reports', 'url' => '/super-admin/reports.php'],
            ['icon' => 'fa-cog', 'label' => 'Settings', 'url' => '/super-admin/settings.php'],
            ['icon' => 'fa-user-shield', 'label' => 'User Management', 'url' => '/super-admin/users.php'],
            ['icon' => 'fa-history', 'label' => 'Audit Logs', 'url' => '/super-admin/audit.php'],
        ],
        'alert_message' => 'You have full system control including user management, school administration, and system operations.',
    ],
    
    'admin' => [
        'title' => 'Admin Dashboard',
        'role_display' => 'Administrator',
        'nav_items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/admin/dashboard.php', 'active' => true],
            ['icon' => 'fa-users', 'label' => 'Members', 'url' => '/admin/members.php'],
            ['icon' => 'fa-building', 'label' => 'Schools', 'url' => '/admin/schools.php'],
            ['icon' => 'fa-calendar', 'label' => 'Events', 'url' => '/admin/events.php'],
            ['icon' => 'fa-file-invoice', 'label' => 'Payments', 'url' => '/admin/payments.php'],
            ['icon' => 'fa-shield-alt', 'label' => 'Compliance', 'url' => '/admin/compliance.php'],
            ['icon' => 'fa-chart-bar', 'label' => 'Reports', 'url' => '/admin/reports.php'],
            ['icon' => 'fa-bullhorn', 'label' => 'Announcements', 'url' => '/admin/announcements.php'],
            ['icon' => 'fa-bell', 'label' => 'Push Notifications', 'url' => '/admin/push.php'],
            ['icon' => 'fa-list-check', 'label' => 'Validate Directories', 'url' => '/portal/admin/list-batches.php'],
        ],
        'alert_message' => 'Welcome to the Admin Dashboard. From here you can manage users, monitor system activities, and oversee IECEP-LSC operations.',
    ],
    
    'school_officer' => [
        'title' => 'School Officer Dashboard',
        'role_display' => 'School Officer',
        'nav_items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/school-officer/dashboard.php', 'active' => true],
            ['icon' => 'fa-users', 'label' => 'My Members', 'url' => '/school-officer/members.php'],
            ['icon' => 'fa-user-plus', 'label' => 'Add Member', 'url' => '/school-officer/add-member.php'],
            ['icon' => 'fa-file-import', 'label' => 'Bulk Import', 'url' => '/school-officer/bulk-import.php'],
            ['icon' => 'fa-upload', 'label' => 'Upload Directory', 'url' => '/portal/school-officer/upload-directory.php'],
            ['icon' => 'fa-calendar', 'label' => 'Events', 'url' => '/school-officer/events.php'],
            ['icon' => 'fa-qrcode', 'label' => 'QR Check-in', 'url' => '/school-officer/qr-checkin.php'],
            ['icon' => 'fa-chart-pie', 'label' => 'Compliance', 'url' => '/school-officer/compliance.php'],
        ],
        'alert_message' => 'Manage your school chapter members, track compliance, and stay updated with IECEP-LSC activities.',
    ],
    
    'member' => [
        'title' => 'Member Dashboard',
        'role_display' => 'Member',
        'nav_items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/member/dashboard.php', 'active' => true],
            ['icon' => 'fa-id-card', 'label' => 'My Profile', 'url' => '/member/profile.php'],
            ['icon' => 'fa-id-badge', 'label' => 'Digital ID', 'url' => '/member/digital-id.php'],
            ['icon' => 'fa-calendar', 'label' => 'Events', 'url' => '/member/events.php'],
            ['icon' => 'fa-ticket-alt', 'label' => 'My Registrations', 'url' => '/member/my-events.php'],
            ['icon' => 'fa-certificate', 'label' => 'Certificates', 'url' => '/member/certificates.php'],
            ['icon' => 'fa-file-alt', 'label' => 'Documents', 'url' => '/member/documents.php'],
            ['icon' => 'fa-sync', 'label' => 'Renew Membership', 'url' => '/member/renew.php'],
            ['icon' => 'fa-cog', 'label' => 'Settings', 'url' => '/member/settings.php'],
        ],
        'alert_message' => 'Welcome to IECEP-LSC MEMSYS. Access your profile, view events, and manage your membership.',
    ],
    
    'eb_pro_1' => [
        'title' => 'Creatives',
        'dashboard_link' => 'dashboard.php',
        'nav_items' => [
            ['title' => 'Dashboard', 'link' => 'dashboard.php', 'icon' => 'fas fa-tachometer-alt'],
            ['title' => 'Manage Calendar', 'link' => 'manage-calendar.php', 'icon' => 'fas fa-calendar-alt'],
            ['title' => 'Manage Schools', 'link' => 'affiliated-schools-management.php', 'icon' => 'fas fa-school'],
            ['title' => 'Profile', 'link' => 'profile.php', 'icon' => 'fas fa-user'],
            ['title' => 'Settings', 'link' => 'settings.php', 'icon' => 'fas fa-cog'],
        ]
    ],
    
    'committee_creatives' => [
        'title' => 'Creatives Dashboard',
        'role_display' => 'Creatives Committee',
        'nav_items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/committee/creatives/dashboard.php', 'active' => true],
            ['icon' => 'fa-image', 'label' => 'Graphics', 'url' => '/committee/creatives/graphics.php'],
            ['icon' => 'fa-newspaper', 'label' => 'Publications', 'url' => '/committee/creatives/publications.php'],
            ['icon' => 'fa-bullhorn', 'label' => 'Announcements', 'url' => '/committee/creatives/announcements.php'],
        ],
        'alert_message' => 'Manage creative assets, publications, and announcements for IECEP-LSC.',
    ],
    
    'eb_pro_2' => [
        'title' => 'Logistics Dashboard',
        'role_display' => 'EB PRO II',
        'nav_items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/logistics/dashboard.php', 'active' => true],
            ['icon' => 'fa-boxes', 'label' => 'Inventory', 'url' => '/logistics/inventory.php'],
            ['icon' => 'fa-calendar-check', 'label' => 'Event Planning', 'url' => '/logistics/events.php'],
            ['icon' => 'fa-truck', 'label' => 'Equipment', 'url' => '/logistics/equipment.php'],
        ],
        'alert_message' => 'Manage logistics, inventory, and event planning for IECEP-LSC activities.',
    ],
    
    'committee_logistics' => [
        'title' => 'Logistics Dashboard',
        'role_display' => 'Logistics Committee',
        'nav_items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/committee/logistics/dashboard.php', 'active' => true],
            ['icon' => 'fa-boxes', 'label' => 'Inventory', 'url' => '/committee/logistics/inventory.php'],
            ['icon' => 'fa-calendar-check', 'label' => 'Event Planning', 'url' => '/committee/logistics/events.php'],
        ],
        'alert_message' => 'Manage logistics and inventory for IECEP-LSC activities.',
    ],
    
    'eb_treasurer' => [
        'title' => 'Treasurer Dashboard',
        'role_display' => 'Treasurer',
        'nav_items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/treasurer/dashboard.php', 'active' => true],
            ['icon' => 'fa-money-bill-wave', 'label' => 'Payments', 'url' => '/treasurer/payments.php'],
            ['icon' => 'fa-cogs', 'label' => 'Fee Settings', 'url' => '/treasurer/fee-settings.php'],
            ['icon' => 'fa-chart-line', 'label' => 'Financial Reports', 'url' => '/treasurer/reports.php'],
            ['icon' => 'fa-file-invoice-dollar', 'label' => 'Invoices', 'url' => '/treasurer/invoices.php'],
            ['icon' => 'fa-university', 'label' => 'School Dues', 'url' => '/treasurer/school-dues.php'],
        ],
        'alert_message' => 'Manage financial transactions, track payments, and generate financial reports.',
    ],
    
    'eb_auditor' => [
        'title' => 'Auditor Dashboard',
        'role_display' => 'Auditor',
        'nav_items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/auditor/dashboard.php', 'active' => true],
            ['icon' => 'fa-search-dollar', 'label' => 'Audit Logs', 'url' => '/auditor/logs.php'],
            ['icon' => 'fa-balance-scale', 'label' => 'Compliance', 'url' => '/auditor/compliance.php'],
            ['icon' => 'fa-file-contract', 'label' => 'Reports', 'url' => '/auditor/reports.php'],
        ],
        'alert_message' => 'Audit financial records, review compliance, and ensure system integrity.',
    ],
    
    'eb_secretary_general' => [
        'title' => 'Secretary Dashboard',
        'role_display' => 'Secretary General',
        'nav_items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/secretary/dashboard.php', 'active' => true],
            ['icon' => 'fa-file-alt', 'label' => 'Documents', 'url' => '/secretary/documents.php'],
            ['icon' => 'fa-calendar', 'label' => 'Minutes', 'url' => '/secretary/minutes.php'],
            ['icon' => 'fa-envelope', 'label' => 'Correspondence', 'url' => '/secretary/correspondence.php'],
        ],
        'alert_message' => 'Manage official documents, meeting minutes, and organizational correspondence.',
    ],
    
    'registration' => [
        'title' => 'Registration Committee Dashboard',
        'role_display' => 'Registration Committee',
        'nav_items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/admin/dashboard.php', 'active' => true],
            ['icon' => 'fa-users', 'label' => 'Members', 'url' => '/admin/members.php'],
            ['icon' => 'fa-list-check', 'label' => 'Validate Directories', 'url' => '/portal/admin/list-batches.php'],
            ['icon' => 'fa-chart-bar', 'label' => 'Reports', 'url' => '/admin/reports.php'],
        ],
        'alert_message' => 'Manage member registrations, validate member directories, and track membership data.',
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
