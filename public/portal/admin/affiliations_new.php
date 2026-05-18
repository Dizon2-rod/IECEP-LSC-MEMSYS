<?php
/**
 * Admin Affiliations Page
 * View, verify documents, and approve affiliation applications
 */

require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../../../includes/config.php';
require_role(['admin', 'registration']);

$db = getDbConnection();

// Fetch all applications with document counts
$stmt = $db->query("
    SELECT 
        aa.*,
        COUNT(ad.id) as doc_count,
        SUM(CASE WHEN ad.verified THEN 1 ELSE 0 END) as verified_count,
        (SELECT COUNT(*) FROM member_directory_imports WHERE application_id = aa.id) as member_count
    FROM affiliation_applications aa
    LEFT JOIN affiliation_documents ad ON aa.id = ad.application_id
    GROUP BY aa.id
    ORDER BY aa.submitted_at DESC
");
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$user = get_user_info();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affiliation Applications – IECEP-LSC</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/dashboard.css">
    <style>
        :root {
            --navy: #0A2F6C;
            --gold: #F5A623;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
        }
        .dashboard-content { padding: 40px; max-width: 1600px; margin: 0 auto; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 32px; }
        .stat-card { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .stat-card .stat-value { font-size: 2rem; font-weight: 700; color: var(--navy); }
        .stat-card .stat-label { font-size: 0.875rem; color: #64748b; margin-top: 4px; }
        .applications-list { display: flex; flex-direction: column; gap: 20px; }
        .application-card { background: white; padding: 28px; border-radius: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-left: 4px solid var(--navy); }
        .application-card.approved { border-left-color: var(--success); }
        .application-card.rejected { border-left-color: var(--error); }
        .application-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px; }
        .application-info h3 { font-size: 1.25rem; font-weight: 700; color: var(--navy); margin-bottom: 4px; }
        .application-info p { color: #64748b; font-size: 0.875rem; }
        .status-badge { padding: 6px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
        .status-badge.pending { background: #dbeafe; color: #1e40af; }
        .status-badge.approved { background: #d1fae5; color: #065f46; }
        .status-badge.rejected { background: #fee2e2; color: #991b1b; }
        .application-details { background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
        .application-details p { margin: 8px 0; font-size: 0.875rem; color: #475569; }
        .application-details strong { color: #1e293b; font-weight: 600; }
        .verification-status { display: flex; align-items: center; gap: 8px; margin: 16px 0; padding: 12px; background: white; border-radius: 8px; }
        .verification-status i { font-size: 1.25rem; }
        .verification-status.complete { background: #d1fae5; color: #065f46; }
        .verification-status.incomplete { background: #fef3c7; color: #92400e; }
        .btn { padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; font-size: 0.875rem; }
        .btn-primary { background: var(--navy); color: white; }
        .btn-primary:hover { background: #1e4a8a; }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #059669; }
        .btn-success:disabled { background: #94a3b8; cursor: not-allowed; }
        .btn-danger { background: var(--error); color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-warning:hover { background: #d97706; }
        .btn-outline { background: white; border: 2px solid #e2e8f0; color: #475569; }
        .btn-outline:hover { border-color: var(--navy); color: var(--navy); }
        .actions-bar { display: flex; gap: 12px; flex-wrap: wrap; }
        
        /* Modal Styles */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 10000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 32px; border-radius: 20px; max-width: 900px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .modal-header h3 { font-size: 1.5rem; font-weight: 700; color: var(--navy); }
        .modal-close { background: #f1f5f9; border: none; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        .modal-close:hover { background: #e2e8f0; }
        .documents-list { display: flex; flex-direction: column; gap: 16px; }
        .document-item { display: flex; justify-content: space-between; align-items: center; padding: 16px; background: #f8fafc; border-radius: 12px; border: 2px solid #e2e8f0; }
        .document-item.verified { background: #d1fae5; border-color: #10b981; }
        .document-info { flex: 1; }
        .document-info h4 { font-size: 1rem; font-weight: 600; color: #1e293b; margin-bottom: 4px; }
        .document-info p { font-size: 0.875rem; color: #64748b; }
        .document-actions { display: flex; gap: 8px; align-items: center; }
        .verify-checkbox { width: 24px; height: 24px; cursor: pointer; }
        .btn-sm { padding: 8px 16px; font-size: 0.875rem; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <header class="dashboard-header">
            <div class="header-content">
                <div>
                    <h1>Affiliation Applications</h1>
                    <p class="welcome-message">Review and manage affiliation applications</p>
                </div>
            </div>
        </header>

        <div class="dashboard-content">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo count(array_filter($applications, fn($a) => $a['status'] === 'pending')); ?></div>
                    <div class="stat-label">Pending Review</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count(array_filter($applications, fn($a) => $a['status'] === 'approved')); ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count(array_filter($applications, fn($a) => $a['status'] === 'rejected')); ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($applications); ?></div>
                    <div class="stat-label">Total Applications</div>
                </div>
            </div>

            <div class="applications-list">
                <?php foreach ($applications as $app): ?>
                <div class="application-card <?php echo $app['status']; ?>">
                    <div class="application-header">
                        <div class="application-info">
                            <h3><?php echo htmlspecialchars($app['school_name']); ?></h3>
                            <p><?php echo htmlspecialchars($app['org_name']); ?> • <?php echo htmlspecialchars($app['rep_name']); ?></p>
                        </div>
                        <span class="status-badge <?php echo $app['status']; ?>">
                            <?php echo ucfirst($app['status']); ?>
                        </span>
                    </div>

                    <div class="application-details">
                        <p><strong>Representative:</strong> <?php echo htmlspecialchars($app['rep_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($app['rep_email']); ?></p>
                        <p><strong>Submitted:</strong> <?php echo date('M j, Y g:i A', strtotime($app['submitted_at'])); ?></p>
                        <p><strong>Members:</strong> <?php echo $app['member_count']; ?> students</p>
                    </div>

                    <div class="verification-status <?php echo ($app['verified_count'] == 6) ? 'complete' : 'incomplete'; ?>">
                        <i class="fas <?php echo ($app['verified_count'] == 6) ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                        <span><strong>Documents:</strong> <?php echo $app['verified_count']; ?>/6 verified</span>
                    </div>

                    <div class="actions-bar">
                        <button onclick="viewDocuments('<?php echo $app['id']; ?>')" class="btn btn-outline">
                            <i class="fas fa-folder-open"></i> View Documents
                        </button>
                        
                        <?php if ($app['status'] === 'approved'): ?>
                        <button onclick="window.location.href='validate-directory.php?application_id=<?php echo $app['id']; ?>'" class="btn btn-warning">
                            <i class="fas fa-users-cog"></i> Validate Member Directory
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($app['status'] === 'pending'): ?>
                        <button onclick="approveApplication('<?php echo $app['id']; ?>')" class="btn btn-success" id="approve-btn-<?php echo $app['id']; ?>" <?php echo ($app['verified_count'] < 6) ? 'disabled' : ''; ?>>
                            <i class="fas fa-check"></i> Approve
                        </button>
                        <button onclick="rejectApplication('<?php echo $app['id']; ?>')" class="btn btn-danger">
                            <i class="fas fa-times"></i> Reject
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <!-- View Documents Modal -->
    <div id="documentsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-folder-open"></i> Application Documents</h3>
                <button class="modal-close" onclick="closeDocumentsModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="documentsList" class="documents-list"></div>
        </div>
    </div>

    <script>
    const csrfToken = '<?php echo generate_csrf_token(); ?>';

    async function viewDocuments(applicationId) {
        try {
            const response = await fetch(`/IECEP-LSC-MEMSYS/public/api/admin/get-documents.php?application_id=${applicationId}`);
            const result = await response.json();

            if (result.success) {
                displayDocuments(result.documents, applicationId);
                document.getElementById('documentsModal').classList.add('active');
            } else {
                alert('Failed to load documents');
            }
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }

    function displayDocuments(documents, applicationId) {
        const container = document.getElementById('documentsList');
        const docLabels = {
            'letter_of_intent': 'Letter of Intent',
            'endorsement_letter': 'Endorsement Letter',
            'constitution_bylaws': 'Constitution and Bylaws',
            'officers_cv': 'List of Officers with CV',
            'org_chart': 'Organizational Chart',
            'member_directory': 'Member Directory (Excel)'
        };

        let html = '';
        documents.forEach(doc => {
            html += `
                <div class="document-item ${doc.verified ? 'verified' : ''}" id="doc-${doc.id}">
                    <div class="document-info">
                        <h4>${docLabels[doc.document_type] || doc.document_type}</h4>
                        <p>${doc.filename} (${(doc.file_size / 1024).toFixed(2)} KB)</p>
                    </div>
                    <div class="document-actions">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" class="verify-checkbox" 
                                   ${doc.verified ? 'checked' : ''}
                                   onchange="toggleVerification('${applicationId}', '${doc.document_type}', this.checked)">
                            <span style="font-size:0.875rem;font-weight:600;color:${doc.verified ? '#065f46' : '#64748b'};">
                                ${doc.verified ? 'Verified' : 'Verify'}
                            </span>
                        </label>
                        <a href="/IECEP-LSC-MEMSYS/public/api/admin/download-document.php?id=${doc.id}" 
                           class="btn btn-primary btn-sm" target="_blank">
                            <i class="fas fa-download"></i> Download
                        </a>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    async function toggleVerification(applicationId, documentType, isVerified) {
        try {
            const formData = new FormData();
            formData.append('application_id', applicationId);
            formData.append('document_type', documentType);
            formData.append('verified', isVerified ? '1' : '0');
            formData.append('csrf_token', csrfToken);

            const response = await fetch('/IECEP-LSC-MEMSYS/public/api/admin/verify-document.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // Update approve button state
                const approveBtn = document.getElementById(`approve-btn-${applicationId}`);
                if (approveBtn) {
                    approveBtn.disabled = !result.all_verified;
                }
                
                // Reload page to update verification count
                if (result.all_verified) {
                    alert('All documents verified! You can now approve this application.');
                    location.reload();
                }
            } else {
                alert('Failed to update verification status');
            }
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }

    async function approveApplication(applicationId) {
        if (!confirm('Are you sure you want to approve this application? This will create an institution account.')) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('application_id', applicationId);
            formData.append('csrf_token', csrfToken);

            const response = await fetch('/IECEP-LSC-MEMSYS/public/api/admin/approve-affiliation.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                alert(result.message || 'Application approved successfully!');
                location.reload();
            } else {
                alert(result.message || 'Failed to approve application');
            }
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }

    async function rejectApplication(applicationId) {
        const reason = prompt('Please provide a reason for rejection:');
        if (!reason) return;

        try {
            const formData = new FormData();
            formData.append('application_id', applicationId);
            formData.append('reason', reason);
            formData.append('csrf_token', csrfToken);

            const response = await fetch('/IECEP-LSC-MEMSYS/public/api/admin/reject-affiliation.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                alert('Application rejected');
                location.reload();
            } else {
                alert('Failed to reject application');
            }
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }

    function closeDocumentsModal() {
        document.getElementById('documentsModal').classList.remove('active');
    }

    // Close modal on outside click
    document.getElementById('documentsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDocumentsModal();
        }
    });
    </script>
</body>
</html>
