<?php
require_once __DIR__ . '/../bootstrap.php';
$current_page = basename(__FILE__, '.php');
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
$config = require __DIR__ . '/../../../includes/supabase.php';
$supabase = new SupabaseClient($config['url'], $config['anon_key']);

// Expose Supabase config to JavaScript for real-time subscriptions
$supabaseUrl = $config['url'];
$supabaseKey = $config['anon_key'];

// Fetch publications from Supabase
try {
    $publications = $supabase->select('creatives_publications');
} catch (Exception $e) {
    $publications = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publications - Creatives Committee</title>
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
        
        .publications-list {
            background: white;
            border-radius: 16px;
            border: 1px solid var(--gray-200);
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        
        .publication-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 24px;
            border-bottom: 1px solid var(--gray-100);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .publication-item:last-child {
            border-bottom: none;
        }
        
        .publication-item:hover {
            background: var(--gray-50);
        }
        
        .publication-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            background: var(--gray-50);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--navy);
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        
        .publication-info {
            flex: 1;
        }
        
        .publication-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 4px;
        }
        
        .publication-meta {
            font-size: 0.75rem;
            color: #94a3b8;
        }
        
        .publication-actions {
            display: flex;
            gap: 8px;
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
                        <h1>Publications</h1>
                        <p class="welcome-message">Manage newsletters and official publications</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            New Publication
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
                    <i class="fas fa-circle-check"></i> Publications updated successfully!
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div style="background: #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 0.875rem;">
                    <i class="fas fa-exclamation-circle"></i> Failed to update publications. Please try again.
                </div>
                <?php endif; ?>
                
                <div class="section-header">
                    <h2>All Publications</h2>
                    <p>View and manage published documents</p>
                </div>
                
                <form action="update-publications.php" method="POST">
                <div class="publications-list">
                    <?php foreach ($publications as $index => $publication): ?>
                    <div class="publication-item">
                        <div class="publication-icon">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div class="publication-info">
                            <input type="text" name="publications[<?php echo $index; ?>][title]" value="<?php echo htmlspecialchars($publication['title']); ?>" style="font-size: 0.95rem; font-weight: 600; color: var(--gray-900); border: 1px solid var(--gray-200); padding: 4px 8px; border-radius: 4px; width: 100%; margin-bottom: 4px;">
                            <input type="text" name="publications[<?php echo $index; ?>][file]" value="<?php echo htmlspecialchars($publication['file']); ?>" placeholder="File path" style="font-size: 0.75rem; color: #94a3b8; border: 1px solid var(--gray-200); padding: 4px 8px; border-radius: 4px; width: 100%;">
                            <input type="text" name="publications[<?php echo $index; ?>][size]" value="<?php echo htmlspecialchars($publication['size']); ?>" placeholder="File size" style="font-size: 0.75rem; color: #94a3b8; border: 1px solid var(--gray-200); padding: 4px 8px; border-radius: 4px; width: 100%; margin-top: 4px;">
                            <input type="date" name="publications[<?php echo $index; ?>][date]" value="<?php echo htmlspecialchars($publication['date']); ?>" style="font-size: 0.75rem; color: #94a3b8; border: 1px solid var(--gray-200); padding: 4px 8px; border-radius: 4px; width: 100%; margin-top: 4px;">
                            <input type="hidden" name="publications[<?php echo $index; ?>][id]" value="<?php echo htmlspecialchars($publication['id']); ?>">
                        </div>
                        <div class="publication-actions">
                            <button type="button" class="btn btn-sm btn-outline" onclick="this.closest('.publication-item').remove()">Remove</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="margin-top: 20px; text-align: center;">
                    <button type="button" class="btn btn-outline" onclick="addPublication()">
                        <i class="fas fa-plus"></i> Add Publication
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
        let publicationCount = <?php echo count($publications); ?>;
        
        function addPublication() {
            const list = document.querySelector('.publications-list');
            const newIndex = publicationCount++;
            const today = new Date().toISOString().split('T')[0];
            
            const newItem = document.createElement('div');
            newItem.className = 'publication-item';
            newItem.innerHTML = `
                <div class="publication-icon">
                    <i class="fas fa-file-pdf"></i>
                </div>
                <div class="publication-info">
                    <input type="text" name="publications[${newIndex}][title]" value="" placeholder="Publication title" style="font-size: 0.95rem; font-weight: 600; color: var(--gray-900); border: 1px solid var(--gray-200); padding: 4px 8px; border-radius: 4px; width: 100%; margin-bottom: 4px;">
                    <input type="text" name="publications[${newIndex}][file]" value="" placeholder="File path" style="font-size: 0.75rem; color: #94a3b8; border: 1px solid var(--gray-200); padding: 4px 8px; border-radius: 4px; width: 100%;">
                    <input type="text" name="publications[${newIndex}][size]" value="" placeholder="File size" style="font-size: 0.75rem; color: #94a3b8; border: 1px solid var(--gray-200); padding: 4px 8px; border-radius: 4px; width: 100%; margin-top: 4px;">
                    <input type="date" name="publications[${newIndex}][date]" value="${today}" style="font-size: 0.75rem; color: #94a3b8; border: 1px solid var(--gray-200); padding: 4px 8px; border-radius: 4px; width: 100%; margin-top: 4px;">
                    <input type="hidden" name="publications[${newIndex}][id]" value="">
                </div>
                <div class="publication-actions">
                    <button type="button" class="btn btn-sm btn-outline" onclick="this.closest('.publication-item').remove()">Remove</button>
                </div>
            `;
            list.appendChild(newItem);
        }
    </script>
    
    <script>
        window.SUPABASE_URL = <?php echo json_encode($supabaseUrl); ?>;
        window.SUPABASE_ANON_KEY = <?php echo json_encode($supabaseKey); ?>;
    </script>
    <script src="/IECEP-LSC-MEMSYS/public/assets/js/supabase-realtime.js"></script>
</body>
</html>

