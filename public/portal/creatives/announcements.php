<?php
require_once __DIR__ . '/../auth_check.php';

// Allow eb_pro_1 (head) and committee_creatives
require_role(['eb_pro_1', 'committee_creatives']);

$user = get_user_info();
$role_display = get_role_display_name($user['role']);
$is_head = $user['role'] === 'eb_pro_1';

$success = isset($_GET['success']);
$error = isset($_GET['error']);

// Load Supabase configuration
require_once __DIR__ . '/../../../src/lib/SupabaseClient.php';
$config = require __DIR__ . '/../../../src/config/supabase.php';
$supabase = new SupabaseClient($config['url'], $config['anon_key']);

// Expose Supabase config to JavaScript for real-time subscriptions
$supabaseUrl = $config['url'];
$supabaseKey = $config['anon_key'];

// Fetch announcements from Supabase
try {
    $announcements = $supabase->select('creatives_announcements');
} catch (Exception $e) {
    $announcements = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Creatives Committee</title>
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
        
        .announcements-list {
            background: white;
            border-radius: 16px;
            border: 1px solid var(--gray-200);
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        
        .announcement-item {
            padding: 24px;
            border-bottom: 1px solid var(--gray-100);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .announcement-item:last-child {
            border-bottom: none;
        }
        
        .announcement-item:hover {
            background: var(--gray-50);
        }
        
        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        
        .announcement-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .announcement-date {
            font-size: 0.75rem;
            color: #94a3b8;
        }
        
        .announcement-content {
            color: #64748b;
            font-size: 0.875rem;
            line-height: 1.5;
        }
        
        .announcement-actions {
            margin-top: 16px;
            display: flex;
            gap: 8px;
        }
        
        .btn-sm {
            padding: 8px 14px;
            font-size: 0.8rem;
            border-radius: 8px;
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
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../sidebar_creatives.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="dashboard-header">
                <div class="header-content">
                    <div>
                        <h1>Announcements</h1>
                        <p class="welcome-message">Manage IECEP-LSC announcements and news</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            New Announcement
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
                    <i class="fas fa-check-circle"></i> Announcements updated successfully!
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div style="background: #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 0.875rem;">
                    <i class="fas fa-exclamation-circle"></i> Failed to update announcements. Please try again.
                </div>
                <?php endif; ?>
                
                <div class="section-header">
                    <h2>All Announcements</h2>
                    <p>View and manage published announcements</p>
                </div>
                
                <form action="update-announcements.php" method="POST">
                <div class="announcements-list">
                    <?php foreach ($announcements as $index => $announcement): ?>
                    <div class="announcement-item">
                        <div class="announcement-header">
                            <div class="announcement-title">
                                <input type="text" name="announcements[<?php echo $index; ?>][title]" value="<?php echo htmlspecialchars($announcement['title']); ?>" style="font-size: 0.95rem; font-weight: 600; color: var(--gray-900); border: 1px solid var(--gray-200); padding: 4px 8px; border-radius: 4px; width: 100%;">
                            </div>
                            <div class="announcement-date">
                                <input type="date" name="announcements[<?php echo $index; ?>][date]" value="<?php echo htmlspecialchars($announcement['date']); ?>" style="font-size: 0.75rem; color: #94a3b8; border: 1px solid var(--gray-200); padding: 4px 8px; border-radius: 4px;">
                            </div>
                        </div>
                        <div class="announcement-content">
                            <textarea name="announcements[<?php echo $index; ?>][content]" rows="2" style="color: #64748b; font-size: 0.875rem; line-height: 1.5; border: 1px solid var(--gray-200); padding: 8px; border-radius: 4px; width: 100%; resize: vertical;"><?php echo htmlspecialchars($announcement['content']); ?></textarea>
                        </div>
                        <div class="announcement-actions">
                            <input type="hidden" name="announcements[<?php echo $index; ?>][id]" value="<?php echo htmlspecialchars($announcement['id']); ?>">
                            <input type="hidden" name="announcements[<?php echo $index; ?>][author]" value="<?php echo htmlspecialchars($announcement['author']); ?>">
                            <input type="hidden" name="announcements[<?php echo $index; ?>][status]" value="<?php echo htmlspecialchars($announcement['status']); ?>">
                            <button type="button" class="btn btn-sm btn-outline" onclick="removeAnnouncement('<?php echo $announcement['id']; ?>')">Remove</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="margin-top: 20px; text-align: center;">
                    <button type="button" class="btn btn-outline" onclick="addAnnouncement()">
                        <i class="fas fa-plus"></i> Add Announcement
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
        let announcementCount = <?php echo count($announcements); ?>;
        
        function addAnnouncement() {
            const list = document.querySelector('.announcements-list');
            const newIndex = announcementCount++;
            const today = new Date().toISOString().split('T')[0];
            const newId = crypto.randomUUID();
            
            const newItem = document.createElement('div');
            newItem.className = 'announcement-item';
            newItem.innerHTML = `
                <div class="announcement-header">
                    <div class="announcement-title">
                        <input type="text" name="announcements[${newIndex}][title]" value="" placeholder="Announcement title" style="font-size: 0.95rem; font-weight: 600; color: var(--gray-900); border: 1px solid var(--gray-200); padding: 4px 8px; border-radius: 4px; width: 100%;">
                    </div>
                    <div class="announcement-date">
                        <input type="date" name="announcements[${newIndex}][date]" value="${today}" style="font-size: 0.75rem; color: #94a3b8; border: 1px solid var(--gray-200); padding: 4px 8px; border-radius: 4px;">
                    </div>
                </div>
                <div class="announcement-content">
                    <textarea name="announcements[${newIndex}][content]" rows="2" placeholder="Announcement content" style="color: #64748b; font-size: 0.875rem; line-height: 1.5; border: 1px solid var(--gray-200); padding: 8px; border-radius: 4px; width: 100%; resize: vertical;"></textarea>
                </div>
                <div class="announcement-actions">
                    <input type="hidden" name="announcements[${newIndex}][id]" value="">
                    <input type="hidden" name="announcements[${newIndex}][author]" value="Creatives Committee">
                    <input type="hidden" name="announcements[${newIndex}][status]" value="published">
                    <button type="button" class="btn btn-sm btn-outline" onclick="this.closest('.announcement-item').remove()">Remove</button>
                </div>
            `;
            list.appendChild(newItem);
        }
        
        function removeAnnouncement(id) {
            const items = document.querySelectorAll('.announcement-item');
            items.forEach(item => {
                const idField = item.querySelector('input[name*="[id]"]');
                if (idField && idField.value === id) {
                    item.remove();
                }
            });
        }
    </script>
    
    <script>
        window.SUPABASE_URL = <?php echo json_encode($supabaseUrl); ?>;
        window.SUPABASE_ANON_KEY = <?php echo json_encode($supabaseKey); ?>;
    </script>
    <script src="/IECEP-LSC-MEMSYS/public/assets/js/supabase-realtime.js"></script>
</body>
</html>
