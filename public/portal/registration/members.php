<?php
require_once __DIR__ . '/../auth_check.php';

require_once __DIR__ . '/../bootstrap.php';
$current_page = 'registration';

require_once __DIR__ . '/../../../includes/config.php';
require_role(['registration', 'admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Registration - IECEP-LSC MEMSYS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/portal.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-user-plus"></i> Member Registration</h1>
            <p class="text-muted">Register new members to the system</p>
        </div>

        <div class="content-card">
            <h2><i class="fas fa-clipboard-list me-2"></i>Registration Form</h2>
            <p>Use the member import feature to bulk register members, or manage individual registrations through the admin panel.</p>
            <a href="<?php echo PORTAL_URL; ?>/admin/import-members.php" class="btn btn-primary">
                <i class="fas fa-file-import"></i> Import Members
            </a>
            <a href="<?php echo PORTAL_URL; ?>/admin/members/list.php" class="btn btn-secondary">
                <i class="fas fa-users"></i> View All Members
            </a>
        </div>
    </div>
</body>
</html>
