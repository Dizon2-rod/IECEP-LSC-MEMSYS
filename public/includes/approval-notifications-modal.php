<?php
require_once __DIR__ . '/bootstrap.php';
/**
 * Approval Notifications Modal
 * Displays all items that need approval in a unified modal
 */

session_start();
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/../includes/supabase.php';
require_once __DIR__ . '/../includes/paths.php';

// Check if user is logged in and has approval permissions
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    exit;
}

$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['registration', 'committee_registration', 'admin', 'super_admin'])) {
    exit;
}

// Fetch pending items that need approval
$pendingItems = [
    'affiliations' => [],
    'members' => [],
    'events' => [],
    'payments' => []
];

try {
    require_once __DIR__ . '/../src/lib/SupabaseClient.php';
    $config = require __DIR__ . '/../includes/supabase.php';
    $supabase = new SupabaseClient($config['url'], $config['anon_key']);
    
    // Get pending affiliations
    try {
        $affiliations = $supabase->select('pending_affiliations', [
            'status' => 'in.(pending,resubmitted)',
            'order' => 'submitted_at.desc',
            'limit' => 10
        ]);
        if (is_array($affiliations)) {
            $pendingItems['affiliations'] = array_slice($affiliations, 0, 5);
        }
    } catch (Exception $e) {
        error_log("Error fetching affiliations: " . $e->getMessage());
    }
    
    // Get pending members (if table exists)
    try {
        $members = $supabase->select('member_applications', [
            'status' => 'eq.pending',
            'order' => 'submitted_at.desc',
            'limit' => 5
        ]);
        if (is_array($members)) {
            $pendingItems['members'] = $members;
        }
    } catch (Exception $e) {
        // Table might not exist, ignore
    }
    
    // Get pending events (if table exists)
    try {
        $events = $supabase->select('events', [
            'status' => 'eq.pending_approval',
            'order' => 'created_at.desc',
            'limit' => 5
        ]);
        if (is_array($events)) {
            $pendingItems['events'] = $events;
        }
    } catch (Exception $e) {
        // Table might not exist, ignore
    }
    
    // Get pending payments (if table exists)
    try {
        $payments = $supabase->select('payment_verifications', [
            'status' => 'eq.pending',
            'order' => 'submitted_at.desc',
            'limit' => 5
        ]);
        if (is_array($payments)) {
            $pendingItems['payments'] = $payments;
        }
    } catch (Exception $e) {
        // Table might not exist, ignore
    }
    
} catch (Exception $e) {
    error_log("Error fetching pending items: " . $e->getMessage());
}

$totalPending = count($pendingItems['affiliations']) + count($pendingItems['members']) + 
                count($pendingItems['events']) + count($pendingItems['payments']);
?>

<style>
.approval-notifications-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: none;
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

.approval-notifications-content {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    overflow: hidden;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

.approval-notifications-header {
    background: linear-gradient(135deg, #0B1D4A, #1e3a8a);
    color: white;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.approval-notifications-title {
    font-size: 18px;
    font-weight: 600;
    margin: 0;
}

.approval-notifications-close {
    background: none;
    border: none;
    color: white;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: background 0.2s;
}

.approval-notifications-close:hover {
    background: rgba(255, 255, 255, 0.1);
}

.approval-notifications-body {
    padding: 20px;
    max-height: 60vh;
    overflow-y: auto;
}

.approval-section {
    margin-bottom: 24px;
}

.approval-section:last-child {
    margin-bottom: 0;
}

.approval-section-title {
    font-size: 16px;
    font-weight: 600;
    color: #0B1D4A;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.approval-section-icon {
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.approval-item {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 8px;
    transition: all 0.2s;
}

.approval-item:hover {
    border-color: #0B1D4A;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.approval-item:last-child {
    margin-bottom: 0;
}

.approval-item-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}

.approval-item-title {
    font-weight: 500;
    color: #1e293b;
    margin: 0;
}

.approval-item-meta {
    font-size: 12px;
    color: #64748b;
    white-space: nowrap;
}

.approval-item-description {
    font-size: 14px;
    color: #475569;
    margin: 0;
}

.approval-item-actions {
    margin-top: 12px;
    display: flex;
    gap: 8px;
}

.approval-item-actions .btn {
    padding: 6px 12px;
    font-size: 12px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.approval-item-actions .btn-primary {
    background: #0B1D4A;
    color: white;
}

.approval-item-actions .btn-primary:hover {
    background: #1e3a8a;
}

.approval-item-actions .btn-outline {
    background: white;
    color: #0B1D4A;
    border: 1px solid #0B1D4A;
}

.approval-item-actions .btn-outline:hover {
    background: #0B1D4A;
    color: white;
}

.no-pending-items {
    text-align: center;
    padding: 40px 20px;
    color: #64748b;
}

.no-pending-items-icon {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.no-pending-items-text {
    font-size: 16px;
    margin-bottom: 8px;
}

.no-pending-items-subtext {
    font-size: 14px;
    opacity: 0.7;
}

.approval-badge {
    background: #dc2626;
    color: white;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 6px;
    border-radius: 10px;
    margin-left: 8px;
}

@media (max-width: 640px) {
    .approval-notifications-content {
        width: 95%;
        max-height: 90vh;
    }
    
    .approval-notifications-header {
        padding: 16px;
    }
    
    .approval-notifications-body {
        padding: 16px;
    }
}
</style>

<!-- Approval Notifications Modal -->
<div id="approvalNotificationsModal" class="approval-notifications-modal">
    <div class="approval-notifications-content">
        <div class="approval-notifications-header">
            <h3 class="approval-notifications-title">
                Pending Approvals
                <?php if ($totalPending > 0): ?>
                    <span class="approval-badge"><?php echo $totalPending; ?></span>
                <?php endif; ?>
            </h3>
            <button class="approval-notifications-close" onclick="closeApprovalNotificationsModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="approval-notifications-body">
            <?php if ($totalPending === 0): ?>
                <div class="no-pending-items">
                    <div class="no-pending-items-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="no-pending-items-text">All caught up!</div>
                    <div class="no-pending-items-subtext">No items currently need your approval</div>
                </div>
            <?php else: ?>
                <!-- Pending Affiliations -->
                <?php if (!empty($pendingItems['affiliations'])): ?>
                    <div class="approval-section">
                        <div class="approval-section-title">
                            <div class="approval-section-icon">
                                <i class="fas fa-university"></i>
                            </div>
                            Affiliation Applications
                        </div>
                        <?php foreach ($pendingItems['affiliations'] as $affiliation): ?>
                            <div class="approval-item">
                                <div class="approval-item-header">
                                    <div class="approval-item-title">
                                        <?php echo htmlspecialchars($affiliation['institution_name'] ?? 'Unknown Institution'); ?>
                                    </div>
                                    <div class="approval-item-meta">
                                        <?php echo date('M j, Y', strtotime($affiliation['submitted_at'] ?? 'now')); ?>
                                    </div>
                                </div>
                                <div class="approval-item-description">
                                    Contact: <?php echo htmlspecialchars($affiliation['contact_person'] ?? 'N/A'); ?> • 
                                    Email: <?php echo htmlspecialchars($affiliation['email'] ?? 'N/A'); ?>
                                </div>
                                <div class="approval-item-actions">
                                    <a href="/IECEP-LSC-MEMSYS/public/portal/admin/affiliations.php" class="btn btn-primary">
                                        <i class="fas fa-eye"></i> Review
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Pending Members -->
                <?php if (!empty($pendingItems['members'])): ?>
                    <div class="approval-section">
                        <div class="approval-section-title">
                            <div class="approval-section-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            Member Applications
                        </div>
                        <?php foreach ($pendingItems['members'] as $member): ?>
                            <div class="approval-item">
                                <div class="approval-item-header">
                                    <div class="approval-item-title">
                                        <?php echo htmlspecialchars($member['full_name'] ?? 'Unknown Member'); ?>
                                    </div>
                                    <div class="approval-item-meta">
                                        <?php echo date('M j, Y', strtotime($member['submitted_at'] ?? 'now')); ?>
                                    </div>
                                </div>
                                <div class="approval-item-description">
                                    Email: <?php echo htmlspecialchars($member['email'] ?? 'N/A'); ?> • 
                                    Position: <?php echo htmlspecialchars($member['position'] ?? 'N/A'); ?>
                                </div>
                                <div class="approval-item-actions">
                                    <a href="/IECEP-LSC-MEMSYS/public/portal/registration/members.php" class="btn btn-primary">
                                        <i class="fas fa-eye"></i> Review
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Pending Events -->
                <?php if (!empty($pendingItems['events'])): ?>
                    <div class="approval-section">
                        <div class="approval-section-title">
                            <div class="approval-section-icon">
                                <i class="fas fa-calendar"></i>
                            </div>
                            Event Approvals
                        </div>
                        <?php foreach ($pendingItems['events'] as $event): ?>
                            <div class="approval-item">
                                <div class="approval-item-header">
                                    <div class="approval-item-title">
                                        <?php echo htmlspecialchars($event['title'] ?? 'Unknown Event'); ?>
                                    </div>
                                    <div class="approval-item-meta">
                                        <?php echo date('M j, Y', strtotime($event['created_at'] ?? 'now')); ?>
                                    </div>
                                </div>
                                <div class="approval-item-description">
                                    Date: <?php echo htmlspecialchars($event['event_date'] ?? 'N/A'); ?> • 
                                    Location: <?php echo htmlspecialchars($event['location'] ?? 'N/A'); ?>
                                </div>
                                <div class="approval-item-actions">
                                    <a href="/IECEP-LSC-MEMSYS/public/portal/admin/events.php" class="btn btn-primary">
                                        <i class="fas fa-eye"></i> Review
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Pending Payments -->
                <?php if (!empty($pendingItems['payments'])): ?>
                    <div class="approval-section">
                        <div class="approval-section-title">
                            <div class="approval-section-icon">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            Payment Verifications
                        </div>
                        <?php foreach ($pendingItems['payments'] as $payment): ?>
                            <div class="approval-item">
                                <div class="approval-item-header">
                                    <div class="approval-item-title">
                                        <?php echo htmlspecialchars($payment['member_name'] ?? 'Unknown Member'); ?>
                                    </div>
                                    <div class="approval-item-meta">
                                        ₱<?php echo number_format($payment['amount'] ?? 0, 2); ?>
                                    </div>
                                </div>
                                <div class="approval-item-description">
                                    Type: <?php echo htmlspecialchars($payment['payment_type'] ?? 'N/A'); ?> • 
                                    Date: <?php echo date('M j, Y', strtotime($payment['submitted_at'] ?? 'now')); ?>
                                </div>
                                <div class="approval-item-actions">
                                    <a href="/IECEP-LSC-MEMSYS/public/portal/treasurer/payments.php" class="btn btn-primary">
                                        <i class="fas fa-eye"></i> Review
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function openApprovalNotificationsModal() {
    document.getElementById('approvalNotificationsModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeApprovalNotificationsModal() {
    document.getElementById('approvalNotificationsModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Close modal when clicking outside
document.getElementById('approvalNotificationsModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeApprovalNotificationsModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeApprovalNotificationsModal();
    }
});
</script>
