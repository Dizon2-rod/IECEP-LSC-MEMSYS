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

// Fetch graphics from Supabase
try {
    $graphics = $supabase->select('creatives_graphics');
} catch (Exception $e) {
    $graphics = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Graphics Library - Creatives Committee</title>
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
        
        .graphics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
        }
        
        .graphic-card {
            background: white;
            border-radius: 16px;
            border: 1px solid var(--gray-200);
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .graphic-card:hover {
            border-color: var(--navy);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            transform: translateY(-4px);
        }
        
        .graphic-preview {
            aspect-ratio: 16/9;
            background: var(--gray-50);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
        }
        
        .graphic-preview i {
            font-size: 2rem;
        }
        
        .graphic-info {
            padding: 16px;
        }
        
        .graphic-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 4px;
        }
        
        .graphic-date {
            font-size: 0.75rem;
            color: #94a3b8;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
            background: white;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        @media (max-width: 1200px) {
            .graphics-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .graphics-grid {
                grid-template-columns: repeat(2, 1fr);
            }
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
                        <h1>Graphics Library</h1>
                        <p class="welcome-message">Upload and manage event graphics and promotional materials</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary">
                            <i class="fas fa-upload"></i>
                            Upload Graphic
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
                    <i class="fas fa-check-circle"></i> Graphics updated successfully!
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div style="background: #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 0.875rem;">
                    <i class="fas fa-exclamation-circle"></i> Failed to update graphics. Please try again.
                </div>
                <?php endif; ?>
                
                <div class="section-header">
                    <h2>All Graphics</h2>
                    <p>View and manage uploaded graphics</p>
                </div>
                
                <form action="update-graphics.php" method="POST">
                <div class="graphics-grid">
                    <?php foreach ($graphics as $index => $graphic): ?>
                    <div class="graphic-card">
                        <div class="graphic-preview">
                            <i class="fas fa-image"></i>
                        </div>
                        <div class="graphic-info">
                            <input type="text" name="graphics[<?php echo $index; ?>][name]" value="<?php echo htmlspecialchars($graphic['name']); ?>" style="font-size: 0.95rem; font-weight: 600; color: var(--gray-900); border: 1px solid var(--gray-200); padding: 4px 8px; border-radius: 4px; width: 100%; margin-bottom: 4px;">
                            <input type="text" name="graphics[<?php echo $index; ?>][image]" value="<?php echo htmlspecialchars($graphic['image']); ?>" placeholder="Image URL" style="font-size: 0.75rem; color: #94a3b8; border: 1px solid var(--gray-200); padding: 4px 8px; border-radius: 4px; width: 100%;">
                            <input type="date" name="graphics[<?php echo $index; ?>][date]" value="<?php echo htmlspecialchars($graphic['date']); ?>" style="font-size: 0.75rem; color: #94a3b8; border: 1px solid var(--gray-200); padding: 4px 8px; border-radius: 4px; width: 100%; margin-top: 4px;">
                            <input type="hidden" name="graphics[<?php echo $index; ?>][id]" value="<?php echo htmlspecialchars($graphic['id']); ?>">
                        </div>
                        <div class="graphic-actions">
                            <button type="button" class="btn btn-sm btn-outline" onclick="this.closest('.graphic-card').remove()">Remove</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="margin-top: 20px; text-align: center;">
                    <button type="button" class="btn btn-outline" onclick="addGraphic()">
                        <i class="fas fa-plus"></i> Add Graphic
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
        let graphicCount = <?php echo count($graphics); ?>;
        
        function addGraphic() {
            const grid = document.querySelector('.graphics-grid');
            const newIndex = graphicCount++;
            const today = new Date().toISOString().split('T')[0];
            
            const newItem = document.createElement('div');
            newItem.className = 'graphic-card';
            newItem.innerHTML = `
                <div class="graphic-preview">
                    <i class="fas fa-image"></i>
                </div>
                <div class="graphic-info">
                    <input type="text" name="graphics[${newIndex}][name]" value="" placeholder="Graphic name" style="font-size: 0.95rem; font-weight: 600; color: var(--gray-900); border: 1px solid var(--gray-200); padding: 4px 8px; border-radius: 4px; width: 100%; margin-bottom: 4px;">
                    <input type="text" name="graphics[${newIndex}][image]" value="" placeholder="Image URL" style="font-size: 0.75rem; color: #94a3b8; border: 1px solid var(--gray-200); padding: 4px 8px; border-radius: 4px; width: 100%;">
                    <input type="date" name="graphics[${newIndex}][date]" value="${today}" style="font-size: 0.75rem; color: #94a3b8; border: 1px solid var(--gray-200); padding: 4px 8px; border-radius: 4px; width: 100%; margin-top: 4px;">
                    <input type="hidden" name="graphics[${newIndex}][id]" value="">
                </div>
                <div class="graphic-actions">
                    <button type="button" class="btn btn-sm btn-outline" onclick="this.closest('.graphic-card').remove()">Remove</button>
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
