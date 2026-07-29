<?php
require_once __DIR__ . '/../../auth_check.php';
require_role(['school_officer']);

require_once __DIR__ . '/../../includes/role-config.php';
require_once __DIR__ . '/../../bootstrap.php';

$current_page = 'send-digital-id';

$user = get_user_info();
$institution_id = $_SESSION['institution_id'] ?? $user['institution_id'] ?? null;

if (!$institution_id) {
    header('Location: /portal/school-officer/dashboard.php');
    exit;
}

$supabase = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);

// Fetch members for this institution
try {
    $members = $supabase->select('members', [
        'institution_id' => 'eq.' . $institution_id,
        'order' => 'full_name.asc'
    ]);
} catch (Exception $e) {
    $members = [];
    $error = 'Failed to load members: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/../../../includes/head-meta.php'; ?>
    <title>Send Digital IDs - School Officer Dashboard</title>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once __DIR__ . '/../../../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="container py-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h2 mb-2">Send Digital IDs</h1>
                        <p class="text-muted">Send digital ID cards to your school members via email</p>
                    </div>
                    <button class="btn btn-primary" onclick="showSendModal()">
                        <i class="fas fa-paper-plane me-2"></i>Send Selected
                    </button>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="50">
                                            <input type="checkbox" id="selectAll" onchange="toggleAll(this)">
                                        </th>
                                        <th>Member Name</th>
                                        <th>Membership ID</th>
                                        <th>Email</th>
                                        <th>Payment Status</th>
                                        <th>Digital ID Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($members)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4">
                                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No members found for your institution.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($members as $member): ?>
                                            <?php
                                                $payment_status = $member['payment_status'] ?? false;
                                                $digital_id_url = $member['digital_id_url'] ?? null;
                                                $can_send = $payment_status && $digital_id_url;
                                            ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" 
                                                           class="member-checkbox" 
                                                           value="<?= htmlspecialchars($member['id'] ?? '') ?>"
                                                           data-email="<?= htmlspecialchars($member['email'] ?? '') ?>"
                                                           data-name="<?= htmlspecialchars($member['full_name'] ?? '') ?>"
                                                           data-membership-id="<?= htmlspecialchars($member['membership_id'] ?? '') ?>"
                                                           data-digital-id="<?= htmlspecialchars($digital_id_url ?? '') ?>"
                                                           data-can-send="<?= $can_send ? '1' : '0' ?>"
                                                           <?= $can_send ? '' : 'disabled' ?>>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($member['full_name'] ?? 'N/A') ?></strong>
                                                </td>
                                                <td>
                                                    <code><?= htmlspecialchars($member['membership_id'] ?? 'N/A') ?></code>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($member['email'] ?? 'N/A') ?>
                                                </td>
                                                <td>
                                                    <?php if ($payment_status): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check-circle me-1"></i>Paid
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">
                                                            <i class="fas fa-clock me-1"></i>Pending
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($digital_id_url): ?>
                                                        <span class="badge bg-info">
                                                            <i class="fas fa-id-card me-1"></i>Generated
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="fas fa-hourglass me-1"></i>Pending
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Send Confirmation Modal -->
    <div class="modal" id="sendModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeSendModal()">&times;</button>
            <h2 class="modal-title">Send Digital IDs</h2>
            <div id="selectedCount" class="mb-3"></div>
            <div id="sendProgress" class="d-none">
                <div class="progress mb-3">
                    <div class="progress-bar" id="progressBar" style="width: 0%"></div>
                </div>
                <p id="progressText">Sending...</p>
            </div>
            <div id="sendResults" class="d-none">
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <span id="successCount">0</span> digital IDs sent successfully
                </div>
                <?php if (!empty($failed)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <span id="failedCount">0</span> failed to send
                    </div>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2 justify-content-end mt-4">
                <button class="btn btn-outline" onclick="closeSendModal()">Cancel</button>
                <button class="btn btn-primary" id="confirmSendBtn" onclick="confirmSend()">
                    <i class="fas fa-paper-plane me-2"></i>Send
                </button>
            </div>
        </div>
    </div>

    <script>
        function toggleAll(checkbox) {
            document.querySelectorAll('.member-checkbox:not(:disabled)').forEach(cb => {
                cb.checked = checkbox.checked;
            });
        }

        function showSendModal() {
            const selected = document.querySelectorAll('.member-checkbox:checked');
            if (selected.length === 0) {
                alert('Please select at least one member to send digital IDs.');
                return;
            }
            document.getElementById('selectedCount').textContent = 
                `You have selected ${selected.length} member(s) to send digital IDs.`;
            document.getElementById('sendModal').classList.add('active');
            document.getElementById('sendProgress').classList.add('d-none');
            document.getElementById('sendResults').classList.add('d-none');
            document.getElementById('confirmSendBtn').classList.remove('d-none');
        }

        function closeSendModal() {
            document.getElementById('sendModal').classList.remove('active');
        }

        async function confirmSend() {
            const selected = document.querySelectorAll('.member-checkbox:checked');
            const memberIds = Array.from(selected).map(cb => cb.value);
            
            document.getElementById('sendProgress').classList.remove('d-none');
            document.getElementById('confirmSendBtn').classList.add('d-none');
            
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            
            try {
                const response = await fetch('/api/send-digital-id.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        member_ids: memberIds
                    })
                });
                
                const result = await response.json();
                
                progressBar.style.width = '100%';
                progressText.textContent = 'Complete!';
                
                document.getElementById('sendProgress').classList.add('d-none');
                document.getElementById('sendResults').classList.remove('d-none');
                document.getElementById('successCount').textContent = result.sent || 0;
                
                if (result.failed > 0) {
                    document.getElementById('failedCount').textContent = result.failed;
                }
                
                // Uncheck all checkboxes
                document.querySelectorAll('.member-checkbox').forEach(cb => cb.checked = false);
                
            } catch (error) {
                progressText.textContent = 'Error: ' + error.message;
                progressBar.classList.add('bg-danger');
            }
        }
    </script>
</body>
</html>
