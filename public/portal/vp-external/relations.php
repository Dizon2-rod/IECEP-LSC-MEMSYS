<?php
/**
 * VP External Relations - Uses Dynamic Sidebar
 */

require_once __DIR__ . '/../auth_check.php';
require_role(['vp_external', 'admin', 'super_admin']);

require_once __DIR__ . '/../../../includes/paths.php';

$user = get_user_info();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>External Relations - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/font-awesome.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>
        
        <main class="main-content main-content-with-sidebar">
            <header class="dashboard-header">
                <h1>External Relations</h1>
                <p>Manage partnerships and external communications</p>
            </header>
            
            <div class="dashboard-content">
                <div class="card">
                    <div class="card-body" style="padding: 40px; text-align: center;">
                        <i class="fas fa-handshake" style="font-size: 3rem; color: var(--accent); margin-bottom: 20px;"></i>
                        <h3>External Relations</h3>
                        <p style="color: var(--neutral-600);">This feature is under development. You will be able to manage external partnerships here.</p>
                        <a href="<?php echo BASE_PUBLIC_URL; ?>/portal/vp-external/dashboard.php" class="btn btn-primary" style="margin-top: 20px;">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
