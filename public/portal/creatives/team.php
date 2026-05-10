<?php
$current_page = basename(__FILE__, '.php');
require_once __DIR__ . '/../auth_check.php';

// Only allow eb_pro_1 (head)
require_role(['eb_pro_1']);

$user = get_user_info();
$role_display = get_role_display_name($user['role']);
$is_head = $user['role'] === 'eb_pro_1';

$success = isset($_GET['success']);
$error = isset($_GET['error']);

// Load Supabase configuration
require_once __DIR__ . '/../../../src/lib/SupabaseClient.php';
$config = require __DIR__ . '/../../../includes/supabase.php';
$supabase = new SupabaseClient($config['url'], $config['anon_key']);

// Expose Supabase config to JavaScript for real-time subscriptions
$supabaseUrl = $config['url'];
$supabaseKey = $config['anon_key'];

// Fetch team members from Supabase
try {
    $team = $supabase->select('creatives_team');
} catch (Exception $e) {
    $team = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Members - Creatives Committee</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/dashboard.css">
    <style>
        :root {
            --navy: #0A2F6C;
            --navy-light: #1e4a8a;
            --gold: #F5A623;
            --gold-dark: #d48f1f;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-700: #334155;
            --gray-900: #0f172a;
        }
        
        .dashboard-content {
            padding: 48px;
            max-width: 1400px;
            margin: 0 auto;
            background: var(--gray-50);
            min-height: calc(100vh - 72px);
        }
        
        .main-content {
            margin-left: 260px;
            background: var(--gray-50);
        }
        
        .section-header {
            margin-bottom: 32px;
        }
        
        .section-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .section-header p {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 400;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.875rem;
            letter-spacing: 0.25px;
        }
        
        .btn-primary {
            background: var(--navy);
            color: white;
            box-shadow: 0 2px 8px rgba(10, 47, 108, 0.2);
        }
        
        .btn-primary:hover {
            background: var(--navy-light);
            box-shadow: 0 4px 12px rgba(10, 47, 108, 0.3);
            transform: translateY(-1px);
        }
        
        .team-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }
        
        .team-card {
            background: white;
            border-radius: 16px;
            border: 1px solid var(--gray-200);
            padding: 32px 24px;
            text-align: center;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .team-card:hover {
            border-color: var(--navy);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            transform: translateY(-4px);
        }
        
        .team-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--gray-50);
            margin: 0 auto 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-size: 2rem;
        }
        
        .team-name {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 4px;
        }
        
        .team-role {
            font-size: 0.8rem;
            color: var(--navy);
            margin-bottom: 12px;
        }
        
        .team-email {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-bottom: 16px;
        }
        
        .team-actions {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.75rem;
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--gray-200);
            color: var(--gray-700);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .btn-outline:hover {
            background: var(--gray-100);
            border-color: var(--gray-300);
        }
        
        @media (max-width: 1024px) {
            .team-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 640px) {
            .team-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
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
                        <h1>Team Members</h1>
                        <p class="welcome-message">Manage Creatives & Publication Committee members</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary">
                            <i class="fas fa-user-plus"></i>
                            Add Member
                        </button>
                        <div class="user-menu">
                            <img src="<?php echo $user['user_metadata']['avatar_url'] ?? '/IECEP-LSC-MEMSYS/public/assets/images/default-avatar.png'; ?>" alt="User Avatar" class="user-avatar">
                            <span><?php echo htmlspecialchars($user['user_metadata']['full_name'] ?? 'User'); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="dashboard-content">
                <?php if ($success): ?>
                <div style="background: #dcfce7; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 0.875rem;">
                    <i class="fas fa-circle-check"></i> Team members updated successfully!
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div style="background: #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 0.875rem;">
                    <i class="fas fa-exclamation-circle"></i> Failed to update team members. Please try again.
                </div>
                <?php endif; ?>
                
                <div class="section-header">
                    <h2>Committee Members</h2>
                    <p>View and manage team members</p>
                </div>
                
                <form action="update-team.php" method="POST">
                <div class="team-grid">
                    <?php foreach ($team as $index => $member): ?>
                    <div class="team-card">
                        <div class="team-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="team-info">
                            <input type="text" name="team[<?php echo $index; ?>][name]" value="<?php echo htmlspecialchars($member['name']); ?>" style="font-size: 1rem; font-weight: 600; color: var(--gray-900); border: 1px solid var(--gray-200); padding: 4px 8px; border-radius: 4px; width: 100%; margin-bottom: 4px;">
                            <input type="text" name="team[<?php echo $index; ?>][role]" value="<?php echo htmlspecialchars($member['role']); ?>" style="font-size: 0.8rem; color: var(--navy); border: 1px solid var(--gray-200); padding: 4px 8px; border-radius: 4px; width: 100%; margin-bottom: 4px;">
                            <input type="text" name="team[<?php echo $index; ?>][email]" value="<?php echo htmlspecialchars($member['email']); ?>" style="font-size: 0.75rem; color: #94a3b8; border: 1px solid var(--gray-200); padding: 4px 8px; border-radius: 4px; width: 100%; margin-bottom: 12px;">
                            <input type="hidden" name="team[<?php echo $index; ?>][id]" value="<?php echo htmlspecialchars($member['id']); ?>">
                        </div>
                        <div class="team-actions">
                            <button type="button" class="btn btn-sm btn-outline" onclick="this.closest('.team-card').remove()">Remove</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="margin-top: 20px; text-align: center;">
                    <button type="button" class="btn btn-outline" onclick="addMember()">
                        <i class="fas fa-user-plus"></i> Add Member
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        let memberCount = <?php echo count($team); ?>;
        
        function addMember() {
            const grid = document.querySelector('.team-grid');
            const newIndex = memberCount++;
            
            const newItem = document.createElement('div');
            newItem.className = 'team-card';
            newItem.innerHTML = `
                <div class="team-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <input type="text" name="team[${newIndex}][name]" value="" placeholder="Member name" style="font-size: 1rem; font-weight: 600; color: var(--gray-900); border: 1px solid var(--gray-200); padding: 4px 8px; border-radius: 4px; width: 100%; margin-bottom: 4px;">
                <input type="text" name="team[${newIndex}][role]" value="Committee Member" placeholder="Role" style="font-size: 0.8rem; color: var(--navy); border: 1px solid var(--gray-200); padding: 4px 8px; border-radius: 4px; width: 100%; margin-bottom: 4px;">
                <input type="text" name="team[${newIndex}][email]" value="" placeholder="Email" style="font-size: 0.75rem; color: #94a3b8; border: 1px solid var(--gray-200); padding: 4px 8px; border-radius: 4px; width: 100%; margin-bottom: 12px;">
                <input type="hidden" name="team[${newIndex}][id]" value="">
                <div class="team-actions">
                    <button type="button" class="btn btn-sm btn-outline" onclick="this.closest('.team-card').remove()">Remove</button>
                </div>
            `;
            grid.appendChild(newItem);
        }
    </script>
    
    <script>
        window.SUPABASE_URL = <?php echo json_encode($supabaseUrl); ?>;
        window.SUPABASE_ANON_KEY = <?php echo json_encode($supabaseKey); ?>;
    </script>
    <script src="/IECEP-LSC-MEMSYS/public/assets/js/supabase-realtime.js"></script>
</body>
</html>

