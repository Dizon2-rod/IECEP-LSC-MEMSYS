<?php
require_once __DIR__ . '/../bootstrap.php';
/**
 * Super Admin Dashboard - Now uses Admin Panel Layout
 */

require_once __DIR__ . '/../auth_check.php';
require_role(['eb_president', 'super_admin']);

require_once __DIR__ . '/../../../includes/paths.php';
require_once __DIR__ . '/../../../src/lib/SupabaseClient.php';
require_once __DIR__ . '/../../../includes/config.php';
$current_page = 'dashboard';

// Load Supabase credentials
$supabaseConfig = require __DIR__ . '/../../../includes/supabase.php';

$user = get_user_info();
$displayName = $_SESSION['full_name'] ?? $_SESSION['email'] ?? $user['user_metadata']['full_name'] ?? $user['email'] ?? 'User';
$role_display = get_role_display_name($user['role']);

// Fetch pending affiliations
$pendingAffiliationsCount = 0;
try {
    $supabase = new SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);
    $pendingAffiliations = $supabase->select('pending_affiliations', ['status' => 'eq.pending_review']);
    if (is_array($pendingAffiliations)) {
        $pendingAffiliationsCount = count($pendingAffiliations);
    }
} catch (Exception $e) {
    $pendingAffiliationsCount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/dashboard.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/professional.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="dashboard-header">
                <div class="header-content">
                    <div>
                        <h1>Super Admin Dashboard</h1>
                        <p class="welcome-message">Welcome back, <?php echo htmlspecialchars($displayName); ?> - <?php echo $role_display; ?></p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-outline">
                            <i class="fas fa-bell"></i>
                            <span class="badge"><?php echo $pendingAffiliationsCount; ?></span>
                        </button>
                        <div class="user-menu">
                            <img src="<?php echo $user['user_metadata']['avatar_url'] ?? '/IECEP-LSC-MEMSYS/public/assets/images/default-avatar.png'; ?>" alt="User Avatar" class="user-avatar">
                            <span><?php echo htmlspecialchars($displayName); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(245, 166, 35, 0.1); color: var(--gold);">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $pendingAffiliationsCount; ?></h3>
                            <p>Pending Affiliations</p>
                            <span class="stat-change neutral">Awaiting review</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(10, 47, 108, 0.1); color: var(--navy);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3>450+</h3>
                            <p>Total Users</p>
                            <span class="stat-change positive">+15 this week</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;">
                            <i class="fas fa-school"></i>
                        </div>
                        <div class="stat-content">
                            <h3>9</h3>
                            <p>Affiliated Schools</p>
                            <span class="stat-change positive">+1 this month</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <div class="stat-content">
                            <h3>12</h3>
                            <p>Active Events</p>
                            <span class="stat-change neutral">Ongoing</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h2>Quick Actions</h2>
                    <div class="actions-grid">
                        <a href="<?php echo PORTAL_URL; ?>/admin/affiliations.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-building"></i>
                            </div>
                            <h3>Review Affiliations</h3>
                            <p>Process pending school affiliation applications</p>
                        </a>

                        <a href="<?php echo PORTAL_URL; ?>/admin/members.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3>Manage Members</h3>
                            <p>View and manage member accounts</p>
                        </a>

                        <a href="<?php echo PORTAL_URL; ?>/super-admin/officers.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <h3>Manage Officers</h3>
                            <p>Update officer information</p>
                        </a>

                        <a href="<?php echo PORTAL_URL; ?>/super-admin/board-of-directors.php" class="action-card">
                            <div class="action-icon">
                                <i class="fas fa-users-cog"></i>
                            </div>
                            <h3>Board of Directors</h3>
                            <p>Manage board members</p>
                        </a>
                    </div>
                </div>

                <!-- System Overview -->
                <div class="recent-activity">
                    <h2>System Overview</h2>
                    <div class="activity-list">
                        <div class="activity-item">
                            <div class="activity-icon" style="background: rgba(245, 166, 35, 0.1); color: var(--gold);">
                                <i class="fas fa-crown"></i>
                            </div>
                            <div class="activity-content">
                                <p><strong>Super Admin Access</strong> - You have full system control including user management, school administration, and system operations.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        window.IECEP_CONFIG = {
            SUPABASE_URL: <?php echo json_encode(SUPABASE_URL); ?>,
            SUPABASE_ANON_KEY: <?php echo json_encode(SUPABASE_ANON_KEY); ?>
        };
    </script>
    <script src="/IECEP-LSC-MEMSYS/public/js/realtime.js" defer></script>
    <script>
        window.onNewPendingAffiliation = function(newRecord) {
            const el = document.getElementById('pending-affiliations-count');
            if (!el) return;
            const current = parseInt(el.textContent) || 0;
            el.textContent = current + 1;
            el.classList.add('live-update');
            setTimeout(() => el.classList.remove('live-update'), 900);
        };
    </script>
    <script src="/IECEP-LSC-MEMSYS/public/js/dashboard.js"></script>
</body>
</html>
