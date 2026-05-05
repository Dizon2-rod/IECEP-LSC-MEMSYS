<?php
/**
 * Unified Dashboard - Role-Based Dashboard
 * 
 * This file serves as a centralized dashboard that routes to the appropriate
 * role-based view or can be included by individual dashboard files.
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../includes/role-config.php';
require_once __DIR__ . '/../../includes/dashboard-layout.php';

// Get user info
$user = get_user_info();
$role = $user['role'] ?? 'member';

// Get role configuration
$role_config = getRoleConfig($role);

if (!$role_config) {
    // Default to member if role not found
    $role_config = getRoleConfig('member');
}

// Build dashboard configuration
$dashboard_config = [
    'title' => $role_config['title'],
    'role' => $role,
    'role_display' => $role_config['role_display'],
    'nav_items' => $role_config['nav_items'],
    'show_alert' => true,
    'alert_message' => $role_config['alert_message'],
    'stats' => [],
    'content' => '',
];

// Fetch role-specific stats based on user role
switch ($role) {
    case 'eb_president':
    case 'super_admin':
        $dashboard_config['stats'] = [
            ['value' => '9', 'label' => 'Total Schools', 'color' => ''],
            ['value' => '450+', 'label' => 'Total Users', 'color' => ''],
            ['value' => '12', 'label' => 'Active Events', 'color' => ''],
            ['value' => '₱250K', 'label' => 'Total Transactions', 'color' => ''],
        ];
        $dashboard_config['content'] = '
            <div class="content-card">
                <h2>System Overview</h2>
                <p>As Super Admin, you have complete control over the IECEP-LSC MEMSYS. Monitor all activities, manage user roles, oversee school affiliations, and ensure system integrity.</p>
                
                <h3>Quick Actions</h3>
                <ul style="margin-left: 20px; line-height: 1.8;">
                    <li>🏫 Manage school affiliations</li>
                    <li>👥 Approve/reject applications</li>
                    <li>📊 Generate system reports</li>
                    <li>⚙️ Configure system settings</li>
                </ul>
            </div>
            <div class="content-card">
                <h2>Recent Activity</h2>
                <p>Monitor system activity and user actions across all schools.</p>
            </div>
        ';
        break;
        
    case 'admin':
        // Fetch pending affiliations count
        $pendingAffiliationsCount = 0;
        try {
            require_once __DIR__ . '/../../src/lib/SupabaseClient.php';
            $supabaseConfig = require __DIR__ . '/../../includes/supabase.php';
            $supabase = new SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);
            $pendingAffiliations = $supabase->select('pending_affiliations', ['status' => 'eq.pending_review']);
            if (is_array($pendingAffiliations)) {
                $pendingAffiliationsCount = count($pendingAffiliations);
            }
        } catch (Exception $e) {
            $pendingAffiliationsCount = 0;
        }
        
        $dashboard_config['stats'] = [
            ['value' => '150', 'label' => 'Total Members', 'color' => ''],
            ['value' => '12', 'label' => 'Schools', 'color' => ''],
            ['value' => (string)$pendingAffiliationsCount, 'label' => 'Pending Affiliations', 'color' => $pendingAffiliationsCount > 0 ? 'warning' : ''],
            ['value' => '₱125,000', 'label' => 'Total Collections', 'color' => ''],
        ];
        $dashboard_config['content'] = '
            <div class="content-card">
                <h2>Recent Activities</h2>
                <p>Welcome to the Admin Dashboard. From here you can manage users, monitor system activities, and oversee all IECEP-LSC operations.</p>
                <p style="margin-top:18px; font-weight:600;">Pending affiliation requests: ' . $pendingAffiliationsCount . '</p>
                <p><a href="' . PORTAL_URL . '/admin/affiliations.php" class="btn btn-primary" style="display:inline-block; margin-top:12px;">View Affiliation Requests</a></p>
            </div>
        ';
        break;
        
    case 'school_officer':
        $dashboard_config['stats'] = [
            ['value' => '45', 'label' => 'Chapter Members', 'color' => ''],
            ['value' => '92%', 'label' => 'Compliance Rate', 'color' => 'success'],
            ['value' => '3', 'label' => 'Pending Actions', 'color' => 'warning'],
            ['value' => '₱5,400', 'label' => 'Dues Collected', 'color' => ''],
        ];
        $dashboard_config['content'] = '
            <div class="content-card">
                <h2>Chapter Overview</h2>
                <p>Manage your school chapter efficiently. Keep track of member compliance, dues collection, and upcoming events.</p>
                
                <h3>Quick Actions</h3>
                <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 16px;">
                    <a href="' . PORTAL_URL . '/school-officer/add-member.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Add Member
                    </a>
                    <a href="' . PORTAL_URL . '/school-officer/bulk-import.php" class="btn btn-gold">
                        <i class="fas fa-file-import"></i> Bulk Import
                    </a>
                </div>
            </div>
        ';
        break;
        
    case 'member':
        $dashboard_config['stats'] = [
            ['value' => 'Active', 'label' => 'Membership Status', 'color' => 'success'],
            ['value' => '2', 'label' => 'Upcoming Events', 'color' => 'info'],
            ['value' => '100%', 'label' => 'Compliance', 'color' => 'success'],
            ['value' => '₱500', 'label' => 'Dues Paid', 'color' => ''],
        ];
        $dashboard_config['content'] = '
            <div class="content-card">
                <h2>My Membership</h2>
                <p>Welcome to IECEP-LSC! Stay connected with your chapter and participate in upcoming events.</p>
                
                <h3>Quick Links</h3>
                <ul style="margin-left: 20px; line-height: 1.8;">
                    <li><a href="' . PORTAL_URL . '/member/profile.php">View Profile</a></li>
                    <li><a href="' . PORTAL_URL . '/member/events.php">Upcoming Events</a></li>
                    <li><a href="' . PORTAL_URL . '/member/documents.php">My Documents</a></li>
                </ul>
            </div>
        ';
        break;
        
    case 'eb_pro_1':
    case 'committee_creatives':
        $dashboard_config['stats'] = [
            ['value' => '24', 'label' => 'Graphics', 'color' => ''],
            ['value' => '8', 'label' => 'Publications', 'color' => ''],
            ['value' => '12', 'label' => 'Announcements', 'color' => ''],
            ['value' => '5', 'label' => 'Team Members', 'color' => ''],
        ];
        $dashboard_config['content'] = '
            <div class="content-card">
                <h2>Creatives Management</h2>
                <p>Manage all creative assets, publications, and announcements for IECEP-LSC.</p>
                
                <h3>Quick Actions</h3>
                <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 16px;">
                    <a href="' . PORTAL_URL . '/creatives/graphics.php" class="btn btn-primary">
                        <i class="fas fa-image"></i> Manage Graphics
                    </a>
                    <a href="' . PORTAL_URL . '/creatives/announcements.php" class="btn btn-gold">
                        <i class="fas fa-bullhorn"></i> New Announcement
                    </a>
                </div>
            </div>
        ';
        break;
        
    case 'eb_pro_2':
    case 'committee_logistics':
        $dashboard_config['stats'] = [
            ['value' => '156', 'label' => 'Inventory Items', 'color' => ''],
            ['value' => '8', 'label' => 'Active Events', 'color' => 'info'],
            ['value' => '12', 'label' => 'Equipment', 'color' => ''],
            ['value' => '3', 'label' => 'Pending Requests', 'color' => 'warning'],
        ];
        $dashboard_config['content'] = '
            <div class="content-card">
                <h2>Logistics Overview</h2>
                <p>Manage inventory, equipment, and event logistics for IECEP-LSC activities.</p>
                
                <h3>Quick Actions</h3>
                <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 16px;">
                    <a href="' . PORTAL_URL . '/logistics/inventory.php" class="btn btn-primary">
                        <i class="fas fa-boxes"></i> View Inventory
                    </a>
                    <a href="' . PORTAL_URL . '/logistics/events.php" class="btn btn-gold">
                        <i class="fas fa-calendar-check"></i> Event Planning
                    </a>
                </div>
            </div>
        ';
        break;
        
    case 'eb_treasurer':
        $dashboard_config['stats'] = [
            ['value' => '₱125K', 'label' => 'Total Collections', 'color' => 'success'],
            ['value' => '45', 'label' => 'Paid Members', 'color' => ''],
            ['value' => '5', 'label' => 'Pending Payments', 'color' => 'warning'],
            ['value' => '12', 'label' => 'Schools Paid', 'color' => ''],
        ];
        $dashboard_config['content'] = '
            <div class="content-card">
                <h2>Financial Overview</h2>
                <p>Manage member dues, track payments, and generate financial reports.</p>
                
                <h3>Quick Actions</h3>
                <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 16px;">
                    <a href="' . PORTAL_URL . '/treasurer/payments.php" class="btn btn-primary">
                        <i class="fas fa-money-bill-wave"></i> View Payments
                    </a>
                    <a href="' . PORTAL_URL . '/treasurer/reports.php" class="btn btn-gold">
                        <i class="fas fa-chart-line"></i> Generate Report
                    </a>
                </div>
            </div>
        ';
        break;
        
    case 'eb_auditor':
        $dashboard_config['stats'] = [
            ['value' => '100%', 'label' => 'Audit Complete', 'color' => 'success'],
            ['value' => '0', 'label' => 'Discrepancies', 'color' => 'success'],
            ['value' => '24', 'label' => 'Records Reviewed', 'color' => ''],
            ['value' => '3', 'label' => 'Schools Audited', 'color' => ''],
        ];
        $dashboard_config['content'] = '
            <div class="content-card">
                <h2>Audit Dashboard</h2>
                <p>Review financial records and ensure compliance across all chapters.</p>
                
                <h3>Quick Actions</h3>
                <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 16px;">
                    <a href="' . PORTAL_URL . '/auditor/logs.php" class="btn btn-primary">
                        <i class="fas fa-search-dollar"></i> Audit Logs
                    </a>
                    <a href="' . PORTAL_URL . '/auditor/reports.php" class="btn btn-gold">
                        <i class="fas fa-file-contract"></i> Generate Report
                    </a>
                </div>
            </div>
        ';
        break;
        
    case 'eb_secretary_general':
        $dashboard_config['stats'] = [
            ['value' => '156', 'label' => 'Documents', 'color' => ''],
            ['value' => '24', 'label' => 'Meeting Minutes', 'color' => ''],
            ['value' => '8', 'label' => 'Pending Review', 'color' => 'warning'],
            ['value' => '45', 'label' => 'Correspondence', 'color' => ''],
        ];
        $dashboard_config['content'] = '
            <div class="content-card">
                <h2>Secretary Dashboard</h2>
                <p>Manage official documents, meeting minutes, and correspondence.</p>
                
                <h3>Quick Actions</h3>
                <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 16px;">
                    <a href="' . PORTAL_URL . '/secretary/documents.php" class="btn btn-primary">
                        <i class="fas fa-file-alt"></i> Documents
                    </a>
                    <a href="' . PORTAL_URL . '/secretary/minutes.php" class="btn btn-gold">
                        <i class="fas fa-calendar"></i> Meeting Minutes
                    </a>
                </div>
            </div>
        ';
        break;
        
    default:
        $dashboard_config['stats'] = [
            ['value' => 'Active', 'label' => 'Account Status', 'color' => 'success'],
        ];
        $dashboard_config['content'] = '
            <div class="content-card">
                <h2>Welcome</h2>
                <p>Your dashboard is being set up. Please contact the administrator if you need assistance.</p>
            </div>
        ';
}

// Render the dashboard
renderDashboard($dashboard_config);
