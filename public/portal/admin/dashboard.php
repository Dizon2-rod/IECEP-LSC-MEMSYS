<?php
/**
 * Admin Dashboard - Uses Dynamic Sidebar
 */

require_once __DIR__ . '/../auth_check.php';
require_role(['eb_president', 'admin']);

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
    <title>Admin Dashboard - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/font-awesome.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/dashboard.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/professional.css">
    <style>
        /* Admin Dashboard Specific Styles */
        .dashboard-header {
            background: white;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .dashboard-header h1 {
            color: #0B1D4A;
            font-size: 1.875rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .welcome-message {
            color: #64748b;
            font-size: 0.95rem;
            margin: 0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #0B1D4A;
            transition: transform 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }
        
        .stat-card.warning {
            border-left-color: #f59e0b;
        }
        
        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            color: #0B1D4A;
            margin-bottom: 0.5rem;
        }
        
        .stat-card p {
            color: #64748b;
            font-weight: 500;
        }
        
        .content-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        
        .content-card h2 {
            color: #0B1D4A;
            margin-bottom: 1rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: #0B1D4A;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .btn:hover {
            background: #1E3A6E;
            transform: translateY(-1px);
        }
        
        .alert {
            background: #dbeafe;
            border: 1px solid #3b82f6;
            color: #1e40af;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert i {
            font-size: 1.25rem;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-header {
                padding: 1rem;
            }
            
            .dashboard-header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Dynamic Sidebar -->
    <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <header class="dashboard-header">
            <div class="header-content">
                <div>
                    <h1>Admin Dashboard</h1>
                    <p class="welcome-message">Welcome back, <?php echo htmlspecialchars($displayName); ?> - <?php echo $role_display; ?></p>
                </div>
            </div>
        </header>

            <!-- Alert -->
            <div class="alert">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Welcome to the Admin Dashboard!</strong>
                    From here you can manage users, monitor system activities, and oversee IECEP-LSC operations.
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>150</h3>
                    <p>Total Members</p>
                </div>
                <div class="stat-card">
                    <h3>12</h3>
                    <p>Schools</p>
                </div>
                <div class="stat-card <?php echo $pendingAffiliationsCount > 0 ? 'warning' : ''; ?>">
                    <h3 id="pending-affiliations-count"><?php echo $pendingAffiliationsCount; ?></h3>
                    <p>Pending Affiliations</p>
                </div>
                <div class="stat-card">
                    <h3>₱125,000</h3>
                    <p>Total Collections</p>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="content-card">
                <h2>Recent Activities</h2>
                <p>Welcome to the Admin Dashboard. From here you can manage users, monitor system activities, and oversee all IECEP-LSC operations.</p>
                <p style="margin-top:18px; font-weight:600;">Pending affiliation requests: <?php echo $pendingAffiliationsCount; ?></p>
                <p><a href="<?php echo PORTAL_URL; ?>/admin/affiliations.php" class="btn" style="display:inline-block; margin-top:12px;">View Affiliation Requests</a></p>
            </div>
        </main>

        <!-- Real-Time Integration Script -->
        <script>
        // Admin Dashboard Real-Time Updates
        document.addEventListener('DOMContentLoaded', function() {
            // Listen for new pending affiliations
            window.addEventListener('realtime:pending_affiliations', function(event) {
                const { action, new: newRecord, old: oldRecord } = event.detail;

                if (action === 'INSERT') {
                    // New affiliation submitted - update counter
                    updatePendingAffiliationsCount();
                } else if (action === 'UPDATE') {
                    // Affiliation status changed - refresh if needed
                    if (oldRecord.status !== newRecord.status && newRecord.status !== 'pending_review') {
                        // Affiliation was approved/rejected, decrement counter
                        updatePendingAffiliationsCount();
                    }
                }
            });
        });

        function updatePendingAffiliationsCount() {
            const countElement = document.getElementById('pending-affiliations-count');
            if (countElement) {
                const currentCount = parseInt(countElement.textContent) || 0;
                // For INSERT events, increment; for status changes to non-pending, decrement
                // The real-time handler above determines the action
                const newCount = currentCount + 1; // This will be overridden by specific logic
                countElement.textContent = newCount;

                // Highlight the element to show update
                countElement.classList.add('highlight');
                setTimeout(() => countElement.classList.remove('highlight'), 1000);

                // Update warning class if needed
                const statCard = countElement.closest('.stat-card');
                if (newCount > 0) {
                    statCard.classList.add('warning');
                } else {
                    statCard.classList.remove('warning');
                }
            }
        }

        // Override default real-time handlers for admin-specific behavior
        window.onNewPendingAffiliation = function(newAffiliation) {
            updatePendingAffiliationsCount();
            console.log('New pending affiliation:', newAffiliation);
        };

        window.onAffiliationStatusChanged = function(updatedAffiliation) {
            // If status changed from pending_review to something else, decrement counter
            if (updatedAffiliation.status !== 'pending_review') {
                const countElement = document.getElementById('pending-affiliations-count');
                if (countElement) {
                    const currentCount = parseInt(countElement.textContent) || 0;
                    if (currentCount > 0) {
                        countElement.textContent = currentCount - 1;

                        // Update warning class
                        const statCard = countElement.closest('.stat-card');
                        if (currentCount - 1 === 0) {
                            statCard.classList.remove('warning');
                        }
                    }
                }
            }
            console.log('Affiliation status changed:', updatedAffiliation);
        };
    </script>
    <script>
        window.IECEP_CONFIG = {
            SUPABASE_URL: <?php echo json_encode(SUPABASE_URL); ?>,
            SUPABASE_ANON_KEY: <?php echo json_encode(SUPABASE_ANON_KEY); ?>
        };
    </script>
    <script src="/IECEP-LSC-MEMSYS/public/js/realtime.js" defer></script>
    </body>
</html>
