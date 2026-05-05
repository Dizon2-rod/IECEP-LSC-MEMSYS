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
$config = require __DIR__ . '/../../../includes/supabase.php';
$supabase = new SupabaseClient($config['url'], $config['anon_key']);

// Expose Supabase config to JavaScript for real-time subscriptions
$supabaseUrl = $config['url'];
$supabaseKey = $config['anon_key'];

// Fetch features from Supabase
try {
    $features = $supabase->select('creatives_features');
} catch (Exception $e) {
    $features = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Homepage Features - Creatives Committee</title>
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
        
        .features-list {
            display: grid;
            gap: 24px;
        }
        .feature-item {
            background: white;
            padding: 28px;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--gray-200);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .feature-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            border-color: var(--navy);
        }
        .feature-item h3 {
            color: var(--navy);
            margin-bottom: 16px;
            font-size: 1.1rem;
        }
        .feature-item input,
        .feature-item textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 16px;
            font-family: inherit;
            font-size: 0.95rem;
        }
        .feature-item input:focus,
        .feature-item textarea:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(245, 166, 35, 0.1);
        }
        .feature-item textarea {
            min-height: 80px;
            resize: vertical;
        }
        .feature-item label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #334155;
            font-size: 0.9rem;
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
                        <h1>Homepage Features</h1>
                        <p class="welcome-message">Manage the featured content on the IECEP-LSC homepage</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-outline">
                            <i class="fas fa-bell"></i>
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
                    <i class="fas fa-circle-check"></i> Features updated successfully!
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div style="background: #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 0.875rem;">
                    <i class="fas fa-exclamation-circle"></i> Failed to update features. Please try again.
                </div>
                <?php endif; ?>
                
                <div class="section-header">
                    <h2>Homepage Features</h2>
                    <p>Manage the featured content on the IECEP-LSC homepage</p>
                </div>

                <form action="update-features.php" method="POST" enctype="multipart/form-data">
                    <div class="features-list">
                        <?php foreach ($features as $index => $feature): ?>
                        <div class="feature-item">
                            <h3>Feature <?php echo $index + 1; ?></h3>
                            <input type="hidden" name="features[<?php echo $index; ?>][id]" value="<?php echo htmlspecialchars($feature['id']); ?>">
                            <label>Title</label>
                            <input type="text" name="features[<?php echo $index; ?>][title]" value="<?php echo htmlspecialchars($feature['title']); ?>" required>
                            
                            <label>Description</label>
                            <textarea name="features[<?php echo $index; ?>][description]" required><?php echo htmlspecialchars($feature['description']); ?></textarea>
                            
                            <label>Image</label>
                            <?php if (!empty($feature['image'])): ?>
                            <div style="margin-bottom: 12px;">
                                <img src="<?php echo htmlspecialchars($feature['image']); ?>" alt="Current image" style="max-width: 200px; max-height: 150px; border-radius: 8px; border: 1px solid #e2e8f0;">
                            </div>
                            <?php endif; ?>
                            <input type="file" name="features[<?php echo $index; ?>][image]" accept="image/*">
                            <input type="hidden" name="features[<?php echo $index; ?>][existing_image]" value="<?php echo htmlspecialchars($feature['image'] ?? ''); ?>">
                            
                            <label>Link (optional)</label>
                            <input type="text" name="features[<?php echo $index; ?>][link]" value="<?php echo htmlspecialchars($feature['link']); ?>">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div style="margin-top: 20px; text-align: center;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script>
        window.SUPABASE_URL = <?php echo json_encode($supabaseUrl); ?>;
        window.SUPABASE_ANON_KEY = <?php echo json_encode($supabaseKey); ?>;
    </script>
    <script src="/IECEP-LSC-MEMSYS/public/assets/js/supabase-realtime.js"></script>
</body>
</html>
