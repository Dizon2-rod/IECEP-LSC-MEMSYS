<?php
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../includes/csrf.php';
require_once __DIR__ . '/../../../includes/audit.php';
require_once __DIR__ . '/../../../src/lib/Supabase.php';
require_once __DIR__ . '/../../../src/lib/BlockchainService.php';
require_once __DIR__ . '/../../../src/lib/EmailService.php';

require_role(['registration_committee', 'admin', 'super_admin']);

$affiliation_id = filter_var($_GET['id'] ?? '', FILTER_SANITIZE_STRING);
$supabase = new Supabase();
$affiliation = $supabase->select('pending_affiliations', '*', ['id' => $affiliation_id])[0] ?? null;

if (!$affiliation) {
    header('Location: ' . BASE_URL . '/public/portal/registration/pending-affiliations.php');
    exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $blockchain = new BlockchainService();
    $email = new EmailService();
    
    try {
        if ($action === 'approve') {
            $supabase->update('pending_affiliations', ['status' => 'approved', 'reviewed_by' => $_SESSION['user']['id'], 'reviewed_at' => date('Y-m-d H:i:s')], ['id' => $affiliation_id]);
            
            $email->send($affiliation['contact_email'], 'Affiliation Application Approved', 
                "Dear {$affiliation['contact_person']},\n\nYour affiliation application for {$affiliation['institution_name']} has been approved.\n\nBest regards,\nIECEP-LSC");
            
            $blockchain->recordEvent('affiliation_approve', $affiliation_id, hash('sha256', json_encode($affiliation)));
            log_audit('affiliation_approve', 'pending_affiliations', $affiliation_id, ['status' => $affiliation['status']], ['status' => 'approved']);
            
            $_SESSION['success'] = 'Affiliation approved successfully';
            
        } elseif ($action === 'reject') {
            $explanation = filter_var($_POST['explanation'] ?? '', FILTER_SANITIZE_STRING);
            
            $supabase->update('pending_affiliations', ['status' => 'rejected', 'reviewed_by' => $_SESSION['user']['id'], 'reviewed_at' => date('Y-m-d H:i:s')], ['id' => $affiliation_id]);
            
            $email_body = "Dear {$affiliation['contact_person']},\n\n";
            $email_body .= "We regret to inform you that your affiliation application for {$affiliation['institution_name']} has been rejected.\n\n";
            if ($explanation) {
                $email_body .= "Reason: {$explanation}\n\n";
            }
            $email_body .= "You may reapply in the future.\n\nBest regards,\nIECEP-LSC";
            
            $email->send($affiliation['contact_email'], 'Affiliation Application Rejected', $email_body);
            
            $blockchain->recordEvent('affiliation_reject', $affiliation_id, hash('sha256', json_encode(['explanation' => $explanation])));
            log_audit('affiliation_reject', 'pending_affiliations', $affiliation_id, ['status' => $affiliation['status']], ['status' => 'rejected', 'explanation' => $explanation]);
            
            $_SESSION['success'] = 'Affiliation rejected';
        }
        
        header('Location: ' . BASE_URL . '/public/portal/registration/pending-affiliations.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

include __DIR__ . '/../../../includes/head-meta.php';
include __DIR__ . '/../../../includes/navbar.php';
?>

<div class="container mt-4">
    <h2>Affiliation Application Details</h2>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <h5><?= htmlspecialchars($affiliation['institution_name']) ?></h5>
            <p><strong>Status:</strong> <?= htmlspecialchars($affiliation['status']) ?></p>
            <p><strong>Contact Person:</strong> <?= htmlspecialchars($affiliation['contact_person']) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($affiliation['contact_email']) ?></p>
            <p><strong>Phone:</strong> <?= htmlspecialchars($affiliation['contact_phone']) ?></p>
            <p><strong>Submitted:</strong> <?= date('F j, Y', strtotime($affiliation['created_at'])) ?></p>
            
            <?php if ($affiliation['status'] === 'pending' || $affiliation['status'] === 'revision_requested'): ?>
                <hr>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-success" onclick="approveAffiliation()">Approve</button>
                    <button type="button" class="btn btn-danger" onclick="showRejectModal()">Reject</button>
                    <button type="button" class="btn btn-warning" onclick="showRevisionModal()">Request Revision</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="reject">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Affiliation</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Reason for Rejection</label>
                        <textarea name="explanation" class="form-control" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Revision Modal -->
<div class="modal fade" id="revisionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="revisionForm">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="request_revision">
                <input type="hidden" name="affiliation_id" value="<?= htmlspecialchars($affiliation_id) ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Request Revision</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Explanation</label>
                        <textarea name="explanation" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Deadline (days)</label>
                        <input type="number" name="deadline_days" class="form-control" value="7" min="1" max="30">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Send Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function approveAffiliation() {
    if (confirm('Approve this affiliation?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>"><input type="hidden" name="action" value="approve">';
        document.body.appendChild(form);
        form.submit();
    }
}

function showRejectModal() {
    $('#rejectModal').modal('show');
}

function showRevisionModal() {
    $('#revisionModal').modal('show');
}

document.getElementById('revisionForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    try {
        const response = await fetch('<?= BASE_URL ?>/public/api/affiliation-revision.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            alert('Revision request sent successfully');
            location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('An error occurred');
    }
});
</script>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
