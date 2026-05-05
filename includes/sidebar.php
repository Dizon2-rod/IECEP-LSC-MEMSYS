<?php
// Dynamic Sidebar - Adapts to user role
// This file should be included after auth_check.php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load path configuration
require_once __DIR__ . '/../includes/paths.php';

// Base URL variables for maintainability
$base_public_url = '/IECEP-LSC-MEMSYS/public';
$base_root_url = '/IECEP-LSC-MEMSYS';

// Get user info with fallbacks
$user = isset($_SESSION['user']) ? $_SESSION['user'] : [];

// Enhanced role detection with multiple fallbacks
$role = $_SESSION['role'] ?? 
         $user['role'] ?? 
         $user['user_metadata']['role'] ?? 
         'member';

// Additional fallback: check if user has admin privileges in other session data
if ($role === 'member' && isset($_SESSION['user']) && is_array($_SESSION['user'])) {
    $user_data = $_SESSION['user'];
    if (isset($user_data['app_metadata']) && isset($user_data['app_metadata']['role'])) {
        $role = $user_data['app_metadata']['role'];
    }
    if (isset($user_data['user_metadata']) && isset($user_data['user_metadata']['role'])) {
        $role = $user_data['user_metadata']['role'];
    }
}

$user_name = $user['user_metadata']['full_name'] ?? $_SESSION['user_name'] ?? $user['email'] ?? 'User';
$user_email = $user['email'] ?? $_SESSION['user_email'] ?? '';
$avatar_url = $user['user_metadata']['avatar_url'] ?? '';

// Debug: Log the detected role
error_log("Sidebar detected role: " . $role . " (Session role: " . ($_SESSION['role'] ?? 'not set') . ")");
error_log("User data: " . json_encode($user));

// Get current page for active state - use REQUEST_URI for better detection
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$request_path = parse_url($request_uri, PHP_URL_PATH) ?? '';

// Role-based menu mappings
$role_menus = [
    'admin' => [
        'title' => 'Admin Panel',
        'icon' => 'fa-user-shield',
        'badge' => 'Administrator',
        'items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/portal/admin/dashboard.php'],
            ['icon' => 'fa-building', 'label' => 'Affiliations', 'url' => '/portal/admin/affiliations.php'],
            ['icon' => 'fa-envelope', 'label' => 'Contact Messages', 'url' => '/portal/admin/contact-messages.php'],
            ['icon' => 'fa-users', 'label' => 'Members', 'url' => '/portal/admin/members.php'],
            ['icon' => 'fa-calendar', 'label' => 'Events', 'url' => '/portal/admin/events.php'],
            ['icon' => 'fa-credit-card', 'label' => 'Payments', 'url' => '/portal/admin/payments.php']
        ]
    ],
    'super_admin' => [
        'title' => 'Super Admin Panel',
        'icon' => 'fa-crown',
        'badge' => 'Super Administrator',
        'items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/portal/admin/dashboard.php'],
            ['icon' => 'fa-building', 'label' => 'Affiliations', 'url' => '/portal/admin/affiliations.php'],
            ['icon' => 'fa-envelope', 'label' => 'Contact Messages', 'url' => '/portal/admin/contact-messages.php'],
            ['icon' => 'fa-users', 'label' => 'Members', 'url' => '/portal/admin/members.php'],
            ['icon' => 'fa-calendar', 'label' => 'Events', 'url' => '/portal/admin/events.php'],
            ['icon' => 'fa-credit-card', 'label' => 'Payments', 'url' => '/portal/admin/payments.php'],
            ['icon' => 'fa-user-cog', 'label' => 'User Management', 'url' => '/portal/admin/user-management.php'],
            ['icon' => 'fa-user-tag', 'label' => 'Role Management', 'url' => '/portal/admin/role-management.php']
        ]
    ],
    'registration' => [
        'title' => 'Registration Committee',
        'icon' => 'fa-user-plus',
        'badge' => 'Registration',
        'items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/portal/registration/dashboard.php'],
            ['icon' => 'fa-hourglass-half', 'label' => 'Pending Affiliations', 'url' => '/portal/registration/pending-affiliations.php'],
            ['icon' => 'fa-users', 'label' => 'Members', 'url' => '/portal/registration/members.php']
        ]
    ],
    'treasurer' => [
        'title' => 'Treasurer Office',
        'icon' => 'fa-coins',
        'badge' => 'Treasurer',
        'items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/portal/treasurer/dashboard.php'],
            ['icon' => 'fa-credit-card', 'label' => 'Payments', 'url' => '/portal/treasurer/payments.php'],
            ['icon' => 'fa-coins', 'label' => 'Budget', 'url' => '/portal/treasurer/budget.php'],
            ['icon' => 'fa-file-invoice-dollar', 'label' => 'Financial Reports', 'url' => '/portal/treasurer/reports.php']
        ]
    ],
    'school_officer' => [
        'title' => 'School Officer Portal',
        'icon' => 'fa-graduation-cap',
        'badge' => 'School Officer',
        'items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/portal/school-officer/dashboard.php'],
            ['icon' => 'fa-users', 'label' => 'Manage Members', 'url' => '/portal/school-officer/members.php'],
            ['icon' => 'fa-check-double', 'label' => 'Compliance', 'url' => '/portal/school-officer/compliance.php'],
            ['icon' => 'fa-file-alt', 'label' => 'Reports', 'url' => '/portal/school-officer/reports.php']
        ]
    ],
    'member' => [
        'title' => 'Member Portal',
        'icon' => 'fa-user',
        'badge' => 'Member',
        'items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/portal/member/dashboard.php'],
            ['icon' => 'fa-id-card', 'label' => 'Profile', 'url' => '/portal/member/profile.php'],
            ['icon' => 'fa-building', 'label' => 'My Affiliation', 'url' => '/portal/member/affiliation.php']
        ]
    ],
    'auditor' => [
        'title' => 'Auditor Office',
        'icon' => 'fa-search',
        'badge' => 'Auditor',
        'items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/portal/auditor/dashboard.php'],
            ['icon' => 'fa-circle-check', 'label' => 'Compliance', 'url' => '/portal/auditor/compliance.php'],
            ['icon' => 'fa-history', 'label' => 'Audit Logs', 'url' => '/portal/auditor/logs.php'],
            ['icon' => 'fa-file-alt', 'label' => 'Reports', 'url' => '/portal/auditor/reports.php']
        ]
    ],
    'secretary' => [
        'title' => 'Secretary Office',
        'icon' => 'fa-file-alt',
        'badge' => 'Secretary',
        'items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/portal/secretary/dashboard.php'],
            ['icon' => 'fa-users', 'label' => 'Members', 'url' => '/portal/secretary/members.php'],
            ['icon' => 'fa-calendar', 'label' => 'Events', 'url' => '/portal/secretary/events.php'],
            ['icon' => 'fa-book', 'label' => 'Minutes', 'url' => '/portal/secretary/minutes.php'],
            ['icon' => 'fa-folder-open', 'label' => 'Documents', 'url' => '/portal/secretary/documents.php']
        ]
    ],
    'creatives' => [
        'title' => 'Creatives Committee',
        'icon' => 'fa-palette',
        'badge' => 'Creatives',
        'items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/portal/creatives/dashboard.php'],
            ['icon' => 'fa-school', 'label' => 'Affiliated Schools Management', 'url' => '/portal/creatives/affiliated-schools-management.php'],
            ['icon' => 'fa-bullhorn', 'label' => 'Announcements', 'url' => '/portal/creatives/announcements.php'],
            ['icon' => 'fa-star', 'label' => 'Features Manager', 'url' => '/portal/creatives/features-manager.php'],
            ['icon' => 'fa-image', 'label' => 'Graphics', 'url' => '/portal/creatives/graphics.php'],
            ['icon' => 'fa-award', 'label' => 'Manage Awards', 'url' => '/portal/creatives/manage-awards.php'],
            ['icon' => 'fa-calendar-alt', 'label' => 'Manage Calendar', 'url' => '/portal/creatives/manage-calendar.php'],
            ['icon' => 'fa-newspaper', 'label' => 'Publications', 'url' => '/portal/creatives/publications.php'],
            ['icon' => 'fa-file-alt', 'label' => 'Reports', 'url' => '/portal/creatives/reports.php'],
            ['icon' => 'fa-users-cog', 'label' => 'Team', 'url' => '/portal/creatives/team.php']
        ]
    ],
    'vp_internal' => [
        'title' => 'VP Internal Office',
        'icon' => 'fa-users',
        'badge' => 'VP Internal',
        'items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/portal/vp-internal/dashboard.php'],
            ['icon' => 'fa-seedling', 'label' => 'Chapter Development', 'url' => '/portal/vp-internal/chapter-development.php']
        ]
    ],
    'vp_external' => [
        'title' => 'VP External Office',
        'icon' => 'fa-handshake',
        'badge' => 'VP External',
        'items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/portal/vp-external/dashboard.php'],
            ['icon' => 'fa-handshake', 'label' => 'External Relations', 'url' => '/portal/vp-external/relations.php']
        ]
    ],
    'vp_academic' => [
        'title' => 'VP Academic Office',
        'icon' => 'fa-graduation-cap',
        'badge' => 'VP Academic',
        'items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/portal/vp-academic/dashboard.php'],
            ['icon' => 'fa-book-open', 'label' => 'Academic Affairs', 'url' => '/portal/vp-academic/affairs.php']
        ]
    ],
    'pro' => [
        'title' => 'PRO Office',
        'icon' => 'fa-bullhorn',
        'badge' => 'PRO',
        'items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/portal/pro/dashboard.php'],
            ['icon' => 'fa-photo-video', 'label' => 'Media', 'url' => '/portal/pro/media.php'],
            ['icon' => 'fa-bullhorn', 'label' => 'Announcements', 'url' => '/portal/pro/announcements.php']
        ]
    ],
    'president' => [
        'title' => 'President Office',
        'icon' => 'fa-crown',
        'badge' => 'President',
        'items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/portal/president/dashboard.php'],
            ['icon' => 'fa-users', 'label' => 'All Members', 'url' => '/portal/president/members.php'],
            ['icon' => 'fa-file-alt', 'label' => 'Reports', 'url' => '/portal/president/reports.php']
        ]
    ],
    'officer' => [
        'title' => 'Officer Portal',
        'icon' => 'fa-user-tie',
        'badge' => 'Officer',
        'items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/portal/officer/dashboard.php'],
            ['icon' => 'fa-users', 'label' => 'Members', 'url' => '/portal/officer/members.php'],
            ['icon' => 'fa-calendar', 'label' => 'Events', 'url' => '/portal/officer/events.php']
        ]
    ],
    'committee' => [
        'title' => 'Committee Portal',
        'icon' => 'fa-users',
        'badge' => 'Committee',
        'items' => [
            ['icon' => 'fa-tachometer-alt', 'label' => 'Dashboard', 'url' => '/portal/committee/dashboard.php'],
            ['icon' => 'fa-folder', 'label' => 'Documentation', 'url' => '/portal/committee/documentation.php'],
            ['icon' => 'fa-truck', 'label' => 'Logistics', 'url' => '/portal/committee/logistics.php'],
            ['icon' => 'fa-chart-line', 'label' => 'Marketing', 'url' => '/portal/committee/marketing.php'],
            ['icon' => 'fa-cogs', 'label' => 'Technical', 'url' => '/portal/committee/technical.php']
        ]
    ]
];

// Handle legacy committee roles by mapping to unified committee
$legacy_committee_map = [
    'committee_creatives' => 'creatives',
    'committee_logistics' => 'committee',
    'committee_marketing' => 'committee',
    'committee_technical' => 'committee',
    'committee_documentation' => 'committee',
    'logistics' => 'committee',
    'marketing' => 'committee',
    'technical' => 'committee',
    'documentation' => 'committee'
];

// Map legacy committee roles
if (isset($legacy_committee_map[$role])) {
    $role = $legacy_committee_map[$role];
}

// Dynamic Portal Title Mapping
$portal_names = [
    'admin' => 'Admin Portal',
    'super_admin' => 'Super Admin Portal',
    'school_officer' => 'Affiliated School Portal',
    'member' => 'Member Portal',
    'registration' => 'Registration Portal',
    'committee_registration' => 'Registration Portal',
    'treasurer' => 'Treasurer Portal',
    'auditor' => 'Auditor Portal',
    'secretary' => 'Secretary Portal',
    'creatives' => 'Creatives Portal',
    'vp_internal' => 'VP Internal Portal',
    'vp_external' => 'VP External Portal',
    'vp_academic' => 'VP Academic Portal',
    'pro' => 'PRO Portal',
    'president' => 'President Portal',
    'officer' => 'Officer Portal'
];

// Use the same enhanced role detection for portal title
$current_role = $role;
$portal_title = $portal_names[$current_role] ?? 'Dashboard';

// Debug: Log portal title mapping
error_log("Portal title mapping - Current role: " . $current_role . " -> Portal title: " . $portal_title);

// Get menu configuration for current role
$menu_config = $role_menus[$role] ?? $role_menus['member'];

// Function to check if menu item is active
function isMenuItemActive($item_url, $request_path) {
    // Remove query string and compare full paths
    $item_path = parse_url($item_url, PHP_URL_PATH);
    return $request_path === $item_path || strpos($request_path, $item_path) !== false;
}
?>

<style>
/* Dynamic Sidebar Styles */
:root {
    --sidebar-primary: #0B1D4A;
    --sidebar-primary-light: #1E3A6E;
    --sidebar-accent: #D4AF37;
    --sidebar-accent-hover: #B8960C;
    --sidebar-white: #FFFFFF;
    --sidebar-gray-100: #F8FAFC;
    --sidebar-gray-200: #E2E8F0;
    --sidebar-gray-600: #475569;
    --sidebar-gray-700: #334155;
    --sidebar-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    --sidebar-shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
    --sidebar-transition: all 0.3s ease;
}

.sidebar {
    width: 260px;
    background: var(--sidebar-primary);
    color: var(--sidebar-white);
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    overflow-y: auto;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    transform: translateX(0);
    transition: var(--sidebar-transition);
    box-shadow: var(--sidebar-shadow-lg);
}

.sidebar.mobile-hidden {
    transform: translateX(-100%);
}

.sidebar-header {
    padding: 28px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.15);
    text-align: center;
    background: linear-gradient(135deg, rgba(11, 29, 74, 0.95) 0%, rgba(30, 58, 110, 0.95) 100%);
}

.sidebar-brand {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin-bottom: 12px;
}

.sidebar-brand img {
    width: 36px;
    height: auto;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
}

.sidebar-brand h3 {
    font-size: 1.4rem;
    font-weight: 800;
    margin: 0;
    color: var(--sidebar-white);
    font-family: 'Inter', sans-serif;
    letter-spacing: -0.02em;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
}

.sidebar-header p {
    font-size: 1.1rem;
    font-weight: 600;
    opacity: 0.95;
    margin: 6px 0 16px;
    font-family: 'Inter', sans-serif;
    color: var(--sidebar-white);
    letter-spacing: 0.01em;
}

.user-role-badge {
    background: rgba(196, 154, 0, 0.25);
    color: #C49A00;
    padding: 8px 16px;
    border-radius: 25px;
    font-size: 0.85rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-family: 'Inter', sans-serif;
    border: 1px solid rgba(196, 154, 0, 0.3);
    box-shadow: 0 2px 4px rgba(196, 154, 0, 0.1);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.sidebar-nav {
    flex: 1;
    padding: 20px 0;
}

.nav-menu {
    list-style: none;
    margin: 0;
    padding: 0;
}

.nav-menu li {
    margin-bottom: 2px;
}

.nav-menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    transition: var(--sidebar-transition);
    font-size: 0.95rem;
    font-weight: 500;
    font-family: 'Inter', sans-serif;
    position: relative;
}

.nav-menu a:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--sidebar-white);
}

.nav-menu a.active {
    background: rgba(196, 154, 0, 0.15);
    color: var(--sidebar-accent);
    border-left: 4px solid #C49A00;
    font-weight: 600;
    position: relative;
}

.nav-menu a.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: linear-gradient(180deg, #C49A00 0%, #D4AF37 100%);
    box-shadow: 0 0 8px rgba(196, 154, 0, 0.4);
}

.nav-menu i {
    width: 20px;
    text-align: center;
    font-size: 0.9rem;
}

.sidebar-footer {
    padding: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #C49A00 0%, #D4AF37 100%);
    color: var(--sidebar-primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
    font-family: 'Inter', sans-serif;
    border: 2px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    position: relative;
    overflow: hidden;
}

.user-avatar::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0) 100%);
    border-radius: 50%;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(255, 255, 255, 0.3);
}

.user-details {
    flex: 1;
}

.user-name {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--sidebar-white);
    font-family: 'Inter', sans-serif;
}

.user-email {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
    margin-top: 2px;
    font-family: 'Inter', sans-serif;
}

.logout-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
    text-decoration: none;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 500;
    transition: var(--sidebar-transition);
    font-family: 'Inter', sans-serif;
}

.logout-btn:hover {
    background: rgba(239, 68, 68, 0.3);
    color: #f87171;
}

/* Mobile Toggle Button */
.sidebar-toggle {
    display: none;
    position: fixed;
    top: 1rem;
    left: 1rem;
    z-index: 1001;
    background: var(--sidebar-primary);
    color: var(--sidebar-white);
    border: none;
    border-radius: 8px;
    padding: 0.75rem;
    cursor: pointer;
    box-shadow: var(--sidebar-shadow);
    transition: var(--sidebar-transition);
}

.sidebar-toggle:hover {
    background: var(--sidebar-primary-light);
}

/* Mobile Overlay */
.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
    opacity: 0;
    visibility: hidden;
    transition: var(--sidebar-transition);
}

.sidebar-overlay.active {
    display: block;
    opacity: 1;
    visibility: visible;
}

:root {
    --sidebar-width: 260px;
    --sidebar-transition: 0.3s ease;
}

/* Sidebar itself */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: var(--sidebar-width);
    height: 100vh;
    background: #0B1D4A;
    z-index: 1000;
    transform: translateX(0);
    transition: transform var(--sidebar-transition);
    overflow-y: auto;
}

/* Main content wrapper – desktop */
.main-content {
    margin-left: var(--sidebar-width);
    transition: margin-left var(--sidebar-transition);
    padding: 2rem;
    min-height: 100vh;
}

/* Mobile: hide sidebar off-canvas */
@media (max-width: 767.98px) {
    .sidebar {
        transform: translateX(-100%);
    }
    .sidebar.open {
        transform: translateX(0);
    }
    .main-content {
        margin-left: 0;
    }
}

@media (min-width: 768px) {
    .sidebar-toggle {
        display: none;
    }
    
    .sidebar-overlay {
        display: none !important;
    }
}
</style>

<!-- Mobile Sidebar Toggle Button -->
<button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
    <i class="fas fa-bars"></i>
</button>

<!-- Mobile Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
// Mobile Sidebar Toggle
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    const toggle = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (toggle && sidebar && overlay) {
        toggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
        });
        
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        });
        
        // Close sidebar on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    }
});
</script>

<!-- Mobile Toggle Button -->
<button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
    <i class="fas fa-bars"></i>
</button>

<!-- Mobile Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <img src="<?php echo $base_public_url; ?>/assets/icons/iecep-logo.png" alt="IECEP-LSC Logo">
            <h3>IECEP-LSC</h3>
        </div>
        <p><?php echo htmlspecialchars($portal_title); ?></p>
        <div class="user-role-badge">
            <?php echo htmlspecialchars($menu_config['badge']); ?>
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav-menu">
            <?php foreach ($menu_config['items'] as $item): ?>
                <li>
                    <a href="<?php echo $base_public_url . htmlspecialchars($item['url']); ?>" 
                       class="<?php echo isMenuItemActive($item['url'], $request_path) ? 'active' : ''; ?>">
                        <i class="fas <?php echo htmlspecialchars($item['icon']); ?>"></i>
                        <span><?php echo htmlspecialchars($item['label']); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?php if ($avatar_url): ?>
                    <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="User Avatar">
                <?php else: ?>
                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                <div class="user-email"><?php echo htmlspecialchars($user_email); ?></div>
            </div>
        </div>
        <a href="<?php echo $base_root_url; ?>/login.php?logout=true" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<script>
// Prevent script duplication
if (typeof window.sidebarInitialized === 'undefined') {
    window.sidebarInitialized = true;
    
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        function toggleSidebar() {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
            
            // Prevent body scroll when sidebar is open on mobile
            if (window.innerWidth <= 767) {
                if (sidebar.classList.contains('active')) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            }
        }
        
        function closeSidebar() {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        // Toggle sidebar
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleSidebar);
        }
        
        // Close sidebar when clicking overlay
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', closeSidebar);
        }
        
        // Close sidebar on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                closeSidebar();
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 767) {
                closeSidebar();
            }
        });
    });
}
</script>
