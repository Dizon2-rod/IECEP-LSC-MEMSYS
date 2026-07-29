<?php
require_once __DIR__ . '/../auth_check.php';

require_once __DIR__ . '/../bootstrap.php';
$current_page = 'profile';

require_once __DIR__ . '/../../../includes/config.php';
require_role(['school_officer']);

$user = get_user_info();
$supabase = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);

$school = [];
try {
    $schoolData = $supabase->select('institutions', [
        'email' => 'eq.' . ($user['email'] ?? '')
    ]);
    $school = $schoolData[0] ?? [];
} catch (Exception $e) {
    $school = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Profile - IECEP-LSC MEMSYS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/portal.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-school"></i> School Profile</h1>
            <p class="text-muted">Manage your school information</p>
        </div>

        <div class="content-card">
            <h2><i class="fas fa-info-circle me-2"></i>School Information</h2>
            <?php if (!empty($school)): ?>
                <table class="table">
                    <tr><th>School Name</th><td><?php echo htmlspecialchars($school['name'] ?? 'N/A'); ?></td></tr>
                    <tr><th>Acronym</th><td><?php echo htmlspecialchars($school['acronym'] ?? 'N/A'); ?></td></tr>
                    <tr><th>Type</th><td><?php echo htmlspecialchars($school['type'] ?? 'N/A'); ?></td></tr>
                    <tr><th>Address</th><td><?php echo htmlspecialchars($school['address'] ?? 'N/A'); ?></td></tr>
                    <tr><th>City</th><td><?php echo htmlspecialchars($school['city'] ?? 'N/A'); ?></td></tr>
                    <tr><th>Province</th><td><?php echo htmlspecialchars($school['province'] ?? 'N/A'); ?></td></tr>
                    <tr><th>Contact Person</th><td><?php echo htmlspecialchars($school['contact_person'] ?? 'N/A'); ?></td></tr>
                    <tr><th>Contact Email</th><td><?php echo htmlspecialchars($school['contact_email'] ?? 'N/A'); ?></td></tr>
                    <tr><th>Contact Phone</th><td><?php echo htmlspecialchars($school['contact_phone'] ?? 'N/A'); ?></td></tr>
                    <tr><th>Website</th><td><?php echo htmlspecialchars($school['website'] ?? 'N/A'); ?></td></tr>
                    <tr><th>Status</th><td><?php echo htmlspecialchars($school['status'] ?? 'N/A'); ?></td></tr>
                </table>
            <?php else: ?>
                <div class="alert alert-info">
                    <p>No school profile found. Please contact the administrator to set up your school profile.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
