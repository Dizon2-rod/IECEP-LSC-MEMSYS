<?php
/**
 * Approval Notification Bell
 * Displays a bell icon with pending approval count
 */

session_start();
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/../includes/supabase.php';
require_once __DIR__ . '/../includes/paths.php';

// Only show for users with approval permissions
$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['registration', 'committee_registration', 'admin', 'super_admin'])) {
    exit;
}

// Count pending items
$pendingCount = 0;

try {
    require_once __DIR__ . '/../src/lib/SupabaseClient.php';
    $config = require __DIR__ . '/../includes/supabase.php';
    $supabase = new SupabaseClient($config['url'], $config['anon_key']);
    
    // Count pending affiliations
    try {
        $affiliations = $supabase->select('pending_affiliations', [
            'status' => 'in.(pending,resubmitted)'
        ]);
        if (is_array($affiliations)) {
            $pendingCount += count($affiliations);
        }
    } catch (Exception $e) {
        // Ignore errors
    }
    
    // Count other pending items (optional, can be extended)
    // Add more counts here as needed
    
} catch (Exception $e) {
    // Ignore errors
}
?>

<style>
.approval-notification-bell {
    position: relative;
    cursor: pointer;
    padding: 8px;
    border-radius: 8px;
    transition: all 0.2s;
    margin-left: 12px;
}

.approval-notification-bell:hover {
    background: rgba(255, 255, 255, 0.1);
}

.approval-notification-bell i {
    font-size: 18px;
    color: white;
}

.approval-notification-badge {
    position: absolute;
    top: 4px;
    right: 4px;
    background: #dc2626;
    color: white;
    font-size: 10px;
    font-weight: 600;
    padding: 2px 5px;
    border-radius: 10px;
    min-width: 16px;
    text-align: center;
    line-height: 1;
}

.approval-notification-bell.pulse {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
    100% {
        transform: scale(1);
    }
}

/* Mobile responsive */
@media (max-width: 768px) {
    .approval-notification-bell {
        margin-left: 8px;
        padding: 6px;
    }
    
    .approval-notification-bell i {
        font-size: 16px;
    }
}
</style>

<div class="approval-notification-bell <?php echo $pendingCount > 0 ? 'pulse' : ''; ?>" 
     onclick="openApprovalNotificationsModal()" 
     title="Pending Approvals">
    <i class="fas fa-bell"></i>
    <?php if ($pendingCount > 0): ?>
        <span class="approval-notification-badge"><?php echo $pendingCount > 99 ? '99+' : $pendingCount; ?></span>
    <?php endif; ?>
</div>
