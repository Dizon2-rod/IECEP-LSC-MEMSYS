<?php
require_once __DIR__ . '/../auth_check.php';

require_once __DIR__ . '/../bootstrap.php';
$current_page = 'attendance';

require_once __DIR__ . '/../../../includes/config.php';
require_role(['school_officer']);

$supabase = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);

$attendances = [];
try {
    $attendances = $supabase->select('attendance', null, 'created_at', 'DESC');
} catch (Exception $e) {
    $attendances = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - School Officer Portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/portal.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-calendar-alt"></i> Attendance Tracking</h1>
            <p class="text-muted">Record and view attendance</p>
        </div>

        <div class="content-card">
            <h2><i class="fas fa-list me-2"></i>Attendance Records</h2>
            <?php if (!empty($attendances)): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Event</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendances as $att): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($att['member_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($att['event_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($att['date'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($att['status'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <p>No attendance records found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
