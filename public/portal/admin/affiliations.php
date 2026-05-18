<?php

$current_page = 'affiliations';
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../auth_check.php';
require_once INCLUDES_PATH . 'config.php';
require_role(['admin', 'super_admin', 'registration', 'committee_registration']);

$user = get_user_info();
require_once SRC_PATH . 'lib/SupabaseClient.php';
require_once SRC_PATH . 'lib/BlockchainService.php';

// Initialize Services
$supabase = new SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);
$blockchain = new \App\Lib\BlockchainService($supabase);

$applications = [];
try {
    // Fetch applications from Supabase
    $applications = $supabase->select('pending_affiliations', null, 'submitted_at', 'DESC');
    
    // Process Blockchain hashes and verification counts for the UI
    foreach ($applications as &$app) {
        $app['verified_count'] = 0;
        if (!empty($app['documents'])) {
            $docs = is_string($app['documents']) ? json_decode($app['documents'], true) : $app['documents'];
            if (is_array($docs)) {
                foreach ($docs as $key => &$doc) {
                    if (isset($doc['content']) && $key !== 'review_notes') {
                        // 1. Calculate Blockchain Hash for the file
                        $pureBase64 = preg_replace('/^data:[^;]+;base64,/', '', $doc['content']);
                        $doc['blockchain_hash'] = hash('sha256', base64_decode($pureBase64));
                        
                        // 2. Count if document is marked as verified
                        if (!empty($doc['verified'])) {
                            $app['verified_count']++;
                        }
                    }
                }
                $app['documents'] = $docs;
            }
        }
    }
} catch (Exception $e) {
    error_log("Affiliations Load Error: " . $e->getMessage());
    $applications = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affiliation Applications | IECEP-LSC Blockchain</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/font-awesome.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/dashboard.css">
    
    <style>
        :root {
            --navy: #0B1D4A; --navy-light: #1E3A6E; --gold: #D4AF37;
            --gray-50: #f8fafc; --gray-100: #f1f5f9; --gray-200: #e2e8f0;
            --gray-500: #64748b; --gray-900: #0f172a;
            --success: #10b981; --success-light: #d1fae5;
            --error: #ef4444; --error-light: #fee2e2;
            --warning: #f59e0b; --warning-light: #fef3c7;
            --info: #3b82f6; --info-light: #dbeafe;
        }

        /* SIDEBAR ISOLATION: Protects the sidebar UI from leakage */
        .dashboard-scope { font-family: 'Inter', sans-serif; color: var(--gray-900); }
        .dashboard-scope .section-header { margin-bottom: 36px; display: flex; justify-content: space-between; align-items: center; }
        .dashboard-scope .section-header h2 { font-size: 2rem; font-weight: 700; color: var(--navy); margin: 0; }

        /* Stats Grid */
        .dashboard-scope .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 32px; }
        .dashboard-scope .stat-card { background: white; padding: 24px; border-radius: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid var(--gray-200); }
        .dashboard-scope .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; font-size: 1.25rem; }
        .dashboard-scope .stat-icon.pending { background: var(--info-light); color: var(--info); }
        .dashboard-scope .stat-icon.approved { background: var(--success-light); color: var(--success); }
        .dashboard-scope .stat-icon.rejected { background: var(--error-light); color: var(--error); }
        .dashboard-scope .stat-value { font-size: 2rem; font-weight: 700; color: var(--gray-900); }
        .dashboard-scope .stat-label { font-size: 0.875rem; color: var(--gray-500); }

        /* Application Cards */
        .dashboard-scope .application-card { background: white; padding: 28px; border-radius: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border: 1px solid var(--gray-200); margin-bottom: 20px; position: relative; overflow: hidden; transition: transform 0.2s ease; }
        .dashboard-scope .application-card:hover { transform: translateY(-3px); }
        .dashboard-scope .application-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: var(--gray-300); }
        .dashboard-scope .application-card.pending_review::before { background: var(--navy); }
        .dashboard-scope .application-card.approved::before { background: var(--success); }
        .dashboard-scope .application-card.rejected::before { background: var(--error); }

        .dashboard-scope .application-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
        .dashboard-scope .application-info h3 { color: var(--gray-900); font-size: 1.25rem; margin: 0; }
        .dashboard-scope .status-badge { padding: 6px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .dashboard-scope .status-badge.pending_review { background: var(--info-light); color: var(--info); }
        .dashboard-scope .status-badge.approved { background: var(--success-light); color: var(--success); }
        .dashboard-scope .status-badge.rejected { background: var(--error-light); color: var(--error); }

        .dashboard-scope .application-details { background: var(--gray-50); padding: 20px; border-radius: 12px; margin-bottom: 20px; font-size: 0.875rem; color: var(--gray-700); line-height: 1.7; }
        
        .dashboard-scope .verification-bar { 
            display: flex; align-items: center; gap: 10px; padding: 12px; border-radius: 8px; margin-bottom: 20px;
            background: <?php echo 'var(--warning-light)'; ?>; color: var(--warning); border: 1px solid var(--warning);
        }
        .dashboard-scope .verification-bar.complete { background: var(--success-light); color: var(--success); border: 1px solid var(--success); }

        .dashboard-scope .application-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .dashboard-scope .btn { padding: 10px 18px; border-radius: 10px; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 8px; border: none; }
        .dashboard-scope .btn-outline { background: white; border: 1.5px solid var(--gray-200); color: var(--gray-700); }
        .dashboard-scope .btn-success { background: var(--success); color: white; }
        .dashboard-scope .btn-warning { background: var(--warning); color: white; }
        .dashboard-scope .btn-danger { background: var(--error); color: white; }

        /* Modals */
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 2000; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 32px; border-radius: 20px; width: 90%; max-width: 800px; max-height: 90vh; overflow-y: auto; position: relative; }
        .modal-close-btn { position: absolute; right: 20px; top: 20px; cursor: pointer; border: none; background: none; font-size: 1.5rem; }

        .documents-list { display: flex; flex-direction: column; gap: 12px; margin-top: 20px; }
        .document-item { display: flex; justify-content: space-between; align-items: center; padding: 16px; background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: 8px; }
        .blockchain-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: var(--success-light); color: var(--success); border-radius: 6px; font-size: 0.7rem; font-weight: 700; margin-top: 6px; }

        .preview-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.9); z-index: 3000; align-items: center; justify-content: center; }
        .preview-container { width: 95%; height: 95vh; background: white; border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; }
        .preview-header { padding: 15px; background: var(--navy); color: white; display: flex; justify-content: space-between; align-items: center; }
        .preview-body { flex: 1; display: flex; justify-content: center; align-items: center; overflow: auto; background: #525659; }
        .preview-body img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .preview-body iframe { width: 100%; height: 100%; border: none; }

        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast { padding: 15px 25px; border-radius: 8px; color: white; font-weight: 500; animation: slideIn 0.3s ease; min-width: 250px; }
        .toast.success { background: var(--success); }
        .toast.error { background: var(--error); }
        @keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }
    </style>
</head>
<body>
    <div class="toast-container" id="toastContainer"></div>

    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <div class="dashboard-scope">
            <header class="section-header">
                <div>
                    <h2>Affiliation Applications</h2>
                    <p>Secure Blockchain-Verified Institutional Review</p>
                </div>
            </header>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon pending"><i class="fas fa-clock"></i></div>
                    <div class="stat-value"><?php echo count(array_filter($applications, fn($a) => in_array($a['status'] ?? '', ['pending', 'pending_review', 'resubmitted']))); ?></div>
                    <div class="stat-label">Pending Review</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon approved"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-value"><?php echo count(array_filter($applications, fn($a) => $a['status'] === 'approved')); ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon rejected"><i class="fas fa-times-circle"></i></div>
                    <div class="stat-value"><?php echo count(array_filter($applications, fn($a) => $a['status'] === 'rejected')); ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>

            <?php if (empty($applications)): ?>
                <div style="text-align: center; padding: 50px; background: white; border-radius: 16px; border: 2px dashed var(--gray-300);">
                    <i class="fas fa-folder-open" style="font-size: 3rem; color: var(--gray-300);"></i>
                    <h3 style="color: var(--gray-500);">No applications found</h3>
                </div>
            <?php else: ?>
                <div class="applications-list">
                    <?php foreach ($applications as $app): 
                        $status = $app['status'] ?? 'pending_review';
                        $vCount = $app['verified_count'] ?? 0;
                    ?>
                        <div class="application-card <?php echo $status; ?>">
                            <div class="application-header">
                                <div class="application-info">
                                    <h3><?php echo htmlspecialchars($app['institution_name'] ?? 'N/A'); ?></h3>
                                    <span><?php echo htmlspecialchars($app['email'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="application-meta">
                                    <span class="status-badge <?php echo $status; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="application-details">
                                <p><strong>Contact:</strong> <?php echo htmlspecialchars($app['contact_person'] ?? 'N/A'); ?> (<?php echo htmlspecialchars($app['contact_position'] ?? 'N/A'); ?>)</p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($app['contact_phone'] ?? 'N/A'); ?></p>
                                <p><strong>Address:</strong> <?php echo htmlspecialchars($app['address'] ?? 'N/A'); ?></p>
                            </div>

                            <div class="verification-bar <?php echo ($vCount >= 6) ? 'complete' : ''; ?>">
                                <i class="fas <?php echo ($vCount >= 6) ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                                <span><strong>Verification Progress:</strong> <?php echo $vCount; ?> / 6 documents verified</span>
                            </div>

                            <div class="application-actions">
                                <button onclick="viewDocuments('<?php echo $app['id']; ?>')" class="btn btn-outline">
                                    <i class="fas fa-file-alt"></i> View Documents
                                </button>
                                <?php if (in_array($status, ['pending', 'pending_review', 'resubmitted'])): ?>
                                    <button onclick="approveApplication('<?php echo $app['id']; ?>')" class="btn btn-success" <?php echo ($vCount < 6) ? 'disabled style="opacity:0.5; cursor:not-allowed"' : ''; ?>>
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button onclick="showRequestChangesModal('<?php echo $app['id']; ?>')" class="btn btn-warning">
                                        <i class="fas fa-clock"></i> Request Changes
                                    </button>
                                    <button onclick="showRejectModal('<?php echo $app['id']; ?>')" class="btn btn-danger">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- DOCUMENTS MODAL -->
    <div id="documentsModal" class="modal">
        <div class="modal-content">
            <button class="modal-close-btn" onclick="closeDocumentsModal()">&times;</button>
            <h3 style="color: var(--navy); margin-bottom: 20px;"><i class="fas fa-shield-alt"></i> Blockchain Verified Documents</h3>
            <div id="documentsList" class="documents-list"></div>
        </div>
    </div>

    <!-- PREVIEW MODAL -->
    <div id="previewModal" class="preview-modal">
        <div class="preview-container">
            <div class="preview-header">
                <span id="previewTitle">Document Preview</span>
                <button class="modal-close-btn" style="color: white;" onclick="closePreviewModal()">&times;</button>
            </div>
            <div class="preview-body" id="previewBody"></div>
        </div>
    </div>

    <!-- Request Changes / Reject Modals -->
    <div id="requestChangesModal" class="modal">
        <div class="modal-content">
            <h3>Request Changes</h3>
            <textarea id="changesInstructions" style="width:100%; height:120px; margin: 20px 0; padding:10px; border-radius:8px; border:1px solid var(--gray-200);" placeholder="Specify what needs to be updated..."></textarea>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button onclick="closeRequestChangesModal()" class la="btn btn-outline">Cancel</button>
                <button onclick="confirmRequestChanges()" class="btn btn-warning">Send Request</button>
            </div>
        </div>
    </div>

    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <h3>Reject Application</h3>
            <textarea id="rejectionReason" style="width:100%; height:120px; margin: 20px 0; padding:10px; border-radius:8px; border:1px solid var(--gray-200);" placeholder="Reason for rejection..."></textarea>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button onclick="closeRejectModal()" class la="btn btn-outline">Cancel</button>
                <button onclick="confirmReject()" class="btn btn-danger">Confirm Reject</button>
            </div>
        </div>
    </div>

    <script>
        let currentAppId = null;
        const applicationsData = <?php echo json_encode($applications); ?>;
        let activeDocs = [];

        function showToast(type, msg) {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerText = msg;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        function base64ToBlobUrl(base64, mime) {
            try {
                let pureBase64 = base64.includes(',') ? base64.split(',')[1] : base64;
                pureBase64 = pureBase64.replace(/\s+/g, '');
                const byteCharacters = atob(pureBase64);
                const byteNumbers = new Array(byteCharacters.length);
                for (let i = 0; i < byteCharacters.length; i++) {
                    byteNumbers[i] = byteCharacters.charCodeAt(i);
                }
                const byteArray = new Uint8Array(byteNumbers);
                const blob = new Blob([byteArray], { type: mime });
                return URL.createObjectURL(blob);
            } catch (e) {
                console.error("Conversion Error:", e);
                return null;
            }
        }

        window.viewDocuments = function(id) {
            const app = applicationsData.find(a => a.id === id);
            if (!app) return showToast('error', 'Application not found');

            const list = document.getElementById('documentsList');
            list.innerHTML = '';

            let docsObj = {};
            try {
                docsObj = typeof app.documents === 'string' ? JSON.parse(app.documents) : (app.documents || {});
            } catch(e) { docsObj = {}; }

            activeDocs = Object.keys(docsObj)
                .filter(k => k !== 'review_notes')
                .map(key => docsObj[key]);

            if (activeDocs.length === 0) {
                list.innerHTML = '<p style="text-align:center; color:var(--gray-500);">No documents uploaded.</p>';
            } else {
                activeDocs.forEach((doc, index) => {
                    const hash = doc.blockchain_hash ? doc.blockchain_hash.substring(0, 16) + '...' : 'No Hash';
                    list.innerHTML += `
                        <div class="document-item">
                            <div class="doc-info">
                                <div style="font-weight:600;"><i class="fas fa-file"></i> ${doc.name || 'Unnamed File'}</div>
                                <div class="blockchain-badge"><i class="fas fa-link"></i> BC-Hash: ${hash}</div>
                            </div>
                            <div style="display:flex; gap:10px;">
                                <button class="btn btn-outline" onclick="previewDoc(${index})">View</button>
                                <button class="btn btn-outline" onclick="downloadDoc(${index})">Download</button>
                            </div>
                        </div>`;
                });
            }
            document.getElementById('documentsModal').style.display = 'flex';
        };

        window.previewDoc = function(index) {
            const doc = activeDocs[index];
            if (!doc) return;

            const body = document.getElementById('previewBody');
            document.getElementById('previewTitle').innerText = doc.name;
            
            const ext = (doc.name || '').split('.').pop().toLowerCase();
            const mime = doc.type || '';
            const blobUrl = base64ToBlobUrl(doc.content, mime);

            if (!blobUrl) {
                body.innerHTML = '<div style="text-align:center; color:white;">Error loading document content.</div>';
                document.getElementById('previewModal').style.display = 'flex';
                return;
            }

            if (mime === 'application/pdf' || ext === 'pdf') {
                body.innerHTML = `<iframe src="${blobUrl}" style="width:100%; height:100%; border:none;"></iframe>`;
            } else if (mime.startsWith('image/') || ['jpg','jpeg','png','gif'].includes(ext)) {
                body.innerHTML = `<img src="${blobUrl}" style="max-width:100%; max-height:100%; object-fit:contain;">`;
            } else {
                body.innerHTML = '<div style="text-align:center; color:white;">Preview not available. Please download.</div>';
            }
            document.getElementById('previewModal').style.display = 'flex';
        };

        window.downloadDoc = function(index) {
            const doc = activeDocs[index];
            if (!doc) return;
            const blobUrl = base64ToBlobUrl(doc.content, doc.type);
            const link = document.createElement('a');
            link.href = blobUrl;
            link.download = doc.name;
            link.click();
            showToast('success', 'Downloading file...');
        };

        window.closeDocumentsModal = () => document.getElementById('documentsModal').style.display = 'none';
        window.closePreviewModal = () => {
            document.getElementById('previewModal').style.display = 'none';
            document.getElementById('previewBody').innerHTML = '';
        };

        // ACTION LOGIC
        window.approveApplication = async function(id) {
            if (!confirm('Approve this affiliation?')) return;
            const formData = new FormData();
            formData.append('action', 'approve');
            formData.append('application_id', id);
            const res = await fetch('/IECEP-LSC-MEMSYS/public/portal/admin/affiliation_action.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) { showToast('success', 'Approved successfully!'); setTimeout(() => location.reload(), 1500); }
            else { showToast('error', 'Error: ' + data.error); }
        };

        window.showRequestChangesModal = (id) => { currentAppId = id; document.getElementById('requestChangesModal').style.display = 'flex'; };
        window.closeRequestChangesModal = () => document.getElementById('requestChangesModal').style.display = 'none';
        window.confirmRequestChanges = async function() {
            const note = document.getElementById('changesInstructions').value;
            if (!note) return showToast('info', 'Please provide instructions');
            const formData = new FormData();
            formData.append('action', 'request_changes');
            formData.append('application_id', currentAppId);
            formData.append('changes_instructions', note);
            const res = await fetch('/IECEP-LSC-MEMSYS/public/portal/admin/affiliation_action.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) { showToast('success', 'Request sent!'); closeRequestChangesModal(); setTimeout(() => location.reload(), 1500); }
        };

        window.showRejectModal = (id) => { currentAppId = id; document.getElementById('rejectModal').style.display = 'flex'; };
        window.closeRejectModal = () => document.getElementById('rejectModal').style.display = 'none';
        window.confirmReject = async function() {
            const reason = document.getElementById('rejectionReason').value;
            if (!reason) return showToast('info', 'Please provide a reason');
            const formData = new FormData();
            formData.append('action', 'reject');
            formData.append('application_id', currentAppId);
            formData.append('rejection_reason', reason);
            const res = await fetch('/IECEP-LSC-MEMSYS/public/portal/admin/affiliation_action.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) { showToast('success', 'Application rejected'); closeRejectModal(); setTimeout(() => location.reload(), 1500); }
        };

        window.onclick = (e) => {
            if (e.target.classList.contains('modal')) {
                document.querySelectorAll('.modal').forEach(m => m.style.display = 'none');
            }
        };
    </script>
</body>
</html>
