<?php
require_once __DIR__ . '/../auth_check.php';

// Load path configuration
require_once __DIR__ . '/../../../includes/paths.php';

// Allow member role
require_role(['member']);

// Set current page for sidebar active state
$current_page = 'dashboard';

$user = get_user_info();
$role_display = get_role_display_name($user['role']);
$displayName = $_SESSION['full_name'] ?? $_SESSION['email'] ?? $user['user_metadata']['full_name'] ?? $user['email'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Dashboard - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/dashboard.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/professional.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Unified Sidebar -->
        <?php include_once __DIR__ . '/../../../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="dashboard-header">
                <div class="header-content">
                    <div>
                        <h1>Member Dashboard</h1>
                        <p class="welcome-message">Welcome back, <?php echo htmlspecialchars($displayName); ?> - <?php echo $role_display; ?></p>
                    </div>
                </div>
            </div>

            <div class="dashboard-content" style="padding: 2rem;">
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Active</h3>
                        <p>Membership Status</p>
                    </div>
                    <div class="stat-card">
                        <h3>2024</h3>
                        <p>Member Since</p>
                    </div>
                    <div class="stat-card">
                        <h3>12</h3>
                        <p>Events Attended</p>
                    </div>
                    <div class="stat-card">
                        <h3>8</h3>
                        <p>Certificates Earned</p>
                    </div>
                </div>

                <div class="content-card">
                    <h2>Welcome to Your Dashboard</h2>
                    <p>Manage your profile, view your affiliation status, and stay updated with IECEP-LSC activities.</p>
                    <div style="margin-top: 20px; display: flex; gap: 10px;">
                        <a href="<?php echo PORTAL_URL; ?>/member/profile.php" class="btn btn-primary">
                            <i class="fas fa-user"></i> Update Profile
                        </a>
                        <a href="<?php echo PORTAL_URL; ?>/member/affiliation.php" class="btn btn-secondary">
                            <i class="fas fa-building"></i> View Affiliation
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
