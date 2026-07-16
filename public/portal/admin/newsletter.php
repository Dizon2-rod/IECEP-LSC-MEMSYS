<?php
require_once __DIR__ . '/../auth_check.php';
require_role(['admin']);

require_once __DIR__ . '/../../includes/role-config.php';
require_once __DIR__ . '/../../bootstrap.php';

$current_page = 'newsletter';

$supabase = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);

// Fetch email blasts
try {
    $blasts = $supabase->select('email_blasts', [
        'order' => 'created_at.desc'
    ]);
} catch (Exception $e) {
    $blasts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/../../includes/head-meta.php'; ?>
    <title>Newsletter - Admin Portal</title>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="container py-5">
                <div class="mb-4">
                    <h1 class="h2 mb-2">Newsletter Management</h1>
                    <p class="text-muted">Create and send bulk email newsletters to members</p>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Create New Newsletter</h5>
                    </div>
                    <div class="card-body">
                        <form id="newsletterForm">
                            <div class="mb-3">
                                <label class="form-label">Subject</label>
                                <input type="text" class="form-control" id="newsletterSubject" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Recipient Filter</label>
                                <select class="form-select" id="recipientFilter">
                                    <option value="all">All Members</option>
                                    <option value="members">Members Only</option>
                                    <option value="officers">School Officers Only</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">HTML Content</label>
                                <textarea class="form-control" id="newsletterContent" rows="10" required placeholder="Enter HTML content for the newsletter"></textarea>
                                <div class="form-text">Use HTML tags for formatting. Images should use absolute URLs.</div>
                            </div>
                            <button type="button" class="btn btn-primary" onclick="createNewsletter()">
                                <i class="fas fa-save me-1"></i>Create Newsletter
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Newsletter History</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($blasts)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                No newsletters sent yet.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Subject</th>
                                            <th>Recipients</th>
                                            <th>Status</th>
                                            <th>Sent Date</th>
                                            <th>Open Rate</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($blasts as $blast): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($blast['subject'] ?? 'N/A') ?></td>
                                                <td><?= $blast['recipient_count'] ?? 0 ?></td>
                                                <td>
                                                    <?php
                                                    $status = $blast['status'] ?? 'draft';
                                                    $statusLabels = [
                                                        'draft' => 'Draft',
                                                        'sent' => 'Sent',
                                                        'failed' => 'Failed'
                                                    ];
                                                    $statusColors = [
                                                        'draft' => 'secondary',
                                                        'sent' => 'success',
                                                        'failed' => 'danger'
                                                    ];
                                                    ?>
                                                    <span class="badge bg-<?= $statusColors[$status] ?? 'secondary' ?>">
                                                        <?= htmlspecialchars($statusLabels[$status] ?? $status) ?>
                                                    </span>
                                                </td>
                                                <td><?= $blast['sent_at'] ? date('M d, Y g:i A', strtotime($blast['sent_at'])) : 'Not sent' ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-info" onclick="viewStats('<?= htmlspecialchars($blast['id'] ?? '') ?>')">
                                                        View Stats
                                                    </button>
                                                </td>
                                                <td>
                                                    <?php if ($status === 'draft'): ?>
                                                        <button class="btn btn-sm btn-primary" onclick="sendNewsletter('<?= htmlspecialchars($blast['id'] ?? '') ?>')">
                                                            <i class="fas fa-paper-plane me-1"></i>Send
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-secondary" disabled>
                                                            Sent
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Stats Modal -->
    <div class="modal fade" id="statsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Newsletter Statistics</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="statsContent">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        async function createNewsletter() {
            const subject = document.getElementById('newsletterSubject').value;
            const content = document.getElementById('newsletterContent').value;
            const recipientFilter = document.getElementById('recipientFilter').value;
            
            if (!subject || !content) {
                alert('Please provide subject and content');
                return;
            }
            
            try {
                const response = await fetch('/api/newsletter.php?action=create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        subject,
                        html_content: content,
                        recipient_filter: recipientFilter
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Newsletter created successfully!');
                    document.getElementById('newsletterForm').reset();
                    location.reload();
                } else {
                    alert('Error: ' + (result.error || 'Failed to create newsletter'));
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
        
        async function sendNewsletter(blastId) {
            if (!confirm('Are you sure you want to send this newsletter?')) {
                return;
            }
            
            try {
                const response = await fetch('/api/newsletter.php?action=send', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        blast_id: blastId,
                        recipient_filter: 'all'
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(`Newsletter sent to ${result.data.total_recipients} recipients!`);
                    location.reload();
                } else {
                    alert('Error: ' + (result.error || 'Failed to send newsletter'));
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
        
        async function viewStats(blastId) {
            const modal = new bootstrap.Modal(document.getElementById('statsModal'));
            modal.show();
            
            const statsContent = document.getElementById('statsContent');
            statsContent.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            
            try {
                const response = await fetch(`/api/newsletter.php?action=stats&blast_id=${blastId}`);
                const result = await response.json();
                
                if (result.success) {
                    const stats = result.data;
                    statsContent.innerHTML = `
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="h3">${stats.total}</div>
                                <div class="text-muted">Total Sent</div>
                            </div>
                            <div class="col-4">
                                <div class="h3">${stats.opened}</div>
                                <div class="text-muted">Opened</div>
                            </div>
                            <div class="col-4">
                                <div class="h3">${stats.clicked}</div>
                                <div class="text-muted">Clicked</div>
                            </div>
                        </div>
                        <hr>
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="h4">${stats.open_rate}%</div>
                                <div class="text-muted">Open Rate</div>
                            </div>
                            <div class="col-6">
                                <div class="h4">${stats.click_rate}%</div>
                                <div class="text-muted">Click Rate</div>
                            </div>
                        </div>
                    `;
                } else {
                    statsContent.innerHTML = '<div class="alert alert-danger">Failed to load statistics</div>';
                }
            } catch (error) {
                statsContent.innerHTML = '<div class="alert alert-danger">Error: ' + error.message + '</div>';
            }
        }
    </script>
</body>
</html>
