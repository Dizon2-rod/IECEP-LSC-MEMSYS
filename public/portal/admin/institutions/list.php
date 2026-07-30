<?php

require_once __DIR__ . '/../../bootstrap.php';
$current_page = 'list';

require_once __DIR__ . '/../../auth_check.php';
require_role(['admin', 'super_admin', 'registration', 'committee_registration']);

$user = get_user_info();

$applications = [];
$pendingApps = [];
$approvedApps = [];
$rejectedApps = [];
$dataSource = 'supabase';
try {
    require_once SRC_PATH . 'lib/SupabaseClient.php';
    $supabase = new SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);
    
    $pendingApps = $supabase->select('pending_affiliations', ['status' => 'eq.pending_review']);
    $approvedApps = $supabase->select('pending_affiliations', ['status' => 'eq.approved']);
    $rejectedApps = $supabase->select('pending_affiliations', ['status' => 'eq.rejected']);
    
    $applications = array_merge(
        is_array($pendingApps) ? $pendingApps : [],
        is_array($approvedApps) ? $approvedApps : [],
        is_array($rejectedApps) ? $rejectedApps : []
    );
    
    if (!is_array($pendingApps)) $pendingApps = [];
    if (!is_array($approvedApps)) $approvedApps = [];
    if (!is_array($rejectedApps)) $rejectedApps = [];
    if (!is_array($applications)) $applications = [];
} catch (Exception $e) {
    error_log("Supabase affiliations load failed: " . $e->getMessage());
    $dataSource = 'none';
    $applications = $pendingApps = $approvedApps = $rejectedApps = [];
}

foreach ($applications as &$app) {
    if (!is_array($app)) {
        continue;
    }
    $app['verified_count'] = 0;
    $docs = [];
        
        if (!empty($app['documents'])) {
            $docsData = is_string($app['documents']) ? json_decode($app['documents'], true) : $app['documents'];
            if (is_array($docsData)) {
                $docs = $docsData;
            }
        }
        
        if (!empty($docs)) {
            foreach ($docs as $key => &$doc) {
                if (isset($doc['content']) && $key !== 'review_notes') {
                    $pureBase64 = preg_replace('/^data:[^;]+;base64,/', '', $doc['content']);
                    $doc['blockchain_hash'] = hash('sha256', base64_decode($pureBase64));
                    if (!empty($doc['verified']) && $doc['verified'] === true) {
                        $app['verified_count']++;
                    }
                }
            }
        }
        $app['documents'] = $docs;
    }
    unset($app);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affiliation Applications | IECEP-LSC Blockchain</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/font-awesome.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/dashboard.css">
    
    <style>
        :root {
            --navy: #0B1D4A; --navy-dark: #061131; --navy-light: #1E3A6E;
            --gold: #C5A059; --gold-hover: #B38F4D;
            --slate-50: #F8FAFC; --slate-100: #F1F5F9; --slate-200: #E2E8F0;
            --slate-400: #94A3B8; --slate-600: #475569; --slate-900: #0F172A;
            --success: #10B981; --success-bg: #DCFCE7;
            --error: #EF4444; --error-bg: #FEE2E2;
            --warning: #F59E0B; --warning-bg: #FEF3C7;
            --info: #3B82F6; --info-bg: #DBEAFE;
            --radius: 12px;
            --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .dashboard-scope { font-family: 'Inter', sans-serif; color: var(--slate-900); padding-bottom: 40px; }
        .section-header { margin-bottom: 32px; display: flex; justify-content: space-between; align-items: flex-end; }
        .section-header h2 { font-size: 1.875rem; font-weight: 800; color: var(--navy); margin: 0; letter-spacing: -0.025em; }
        .section-header p { color: var(--slate-600); margin: 4px 0 0 0; font-size: 0.95rem; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 24px; border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--slate-200); display: flex; align-items: center; gap: 20px; transition: transform 0.2s ease; }
        .stat-card:hover { transform: translateY(-4px); }
        .stat-icon { width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0; }
        .stat-icon.pending { background: var(--info-bg); color: var(--info); }
        .stat-icon.approved { background: var(--success-bg); color: var(--success); }
        .stat-icon.rejected { background: var(--error-bg); color: var(--error); }
        .stat-details { display: flex; flex-direction: column; }
        .stat-value { font-size: 1.75rem; font-weight: 800; color: var(--slate-900); line-height: 1; }
        .stat-label { font-size: 0.875rem; color: var(--slate-600); font-weight: 500; margin-top: 4px; }

        .application-card { background: white; border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--slate-200); margin-bottom: 24px; overflow: hidden; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .application-card:hover { box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); border-color: var(--gold); }
        .card-accent { height: 4px; width: 100%; }
.pending_review .card-accent { background: var(--info); }
.approved .card-accent { background: var(--success); }
.rejected .card-accent { background: var(--error); }
.under_review .card-accent { background: var(--info); }
.requires_revision .card-accent { background: var(--warning); }

        .application-main { padding: 24px; }
        .application-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .application-info h3 { color: var(--navy); font-size: 1.25rem; font-weight: 700; margin: 0 0 4px 0; }
        .application-info .email { color: var(--slate-400); font-size: 0.875rem; }

        .status-badge { padding: 6px 12px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
.status-badge.pending_review { background: var(--info-bg); color: var(--info); }
.status-badge.approved { background: var(--success-bg); color: var(--success); }
.status-badge.rejected { background: var(--error-bg); color: var(--error); }
.status-badge.under_review { background: var(--info-bg); color: var(--info); }
.status-badge.requires_revision { background: var(--warning-bg); color: var(--warning); }

        .application-details-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; background: var(--slate-50); padding: 20px; border-radius: var(--radius); margin-bottom: 24px; border: 1px solid var(--slate-100); }
        .detail-item { display: flex; flex-direction: column; gap: 4px; }
        .detail-label { font-size: 0.75rem; text-transform: uppercase; color: var(--slate-400); font-weight: 600; }
        .detail-value { font-size: 0.9rem; color: var(--slate-900); font-weight: 500; }

        .payment-summary-box { 
            background: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%); 
            border: 1px solid var(--slate-200); 
            border-radius: var(--radius); 
            padding: 16px; 
            margin-bottom: 24px; 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); 
            gap: 12px; 
        }
        .payment-item { display: flex; flex-direction: column; }
        .payment-label { font-size: 0.7rem; color: var(--slate-400); text-transform: uppercase; font-weight: 600; }
        .payment-value { font-size: 1rem; font-weight: 700; color: var(--navy); }
        .payment-value.highlight { color: var(--gold); }

        .verification-container { margin-bottom: 24px; }
        .verification-text { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-size: 0.875rem; font-weight: 600; }
        .progress-bg { height: 10px; background: var(--slate-200); border-radius: 5px; overflow: hidden; position: relative; }
        .progress-fill { height: 100%; background: var(--gold); transition: width 0.5s ease; box-shadow: 0 0 10px rgba(197, 160, 89, 0.5); }
        .progress-fill.complete { background: var(--success); }

        .application-actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn { padding: 10px 20px; border-radius: 8px; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 8px; border: none; }
        .btn-outline { background: white; border: 1px solid var(--slate-200); color: var(--slate-600); }
        .btn-outline:hover { background: var(--slate-100); border-color: var(--slate-400); }
        .btn-success { background: var(--success); color: white; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-danger { background: var(--error); color: white; }

        .modal { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); z-index: 2000; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 0; border-radius: 20px; width: 90%; max-width: 800px; max-height: 90vh; overflow: hidden; position: relative; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        .modal-header { padding: 24px; background: var(--navy); color: white; display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 24px; overflow-y: auto; max-height: calc(90vh - 80px); }
        .modal-close-btn { background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; opacity: 0.7; }

        .documents-list { display: flex; flex-direction: column; gap: 12px; }
        .document-item { display: flex; justify-content: space-between; align-items: center; padding: 16px; background: var(--slate-50); border: 1px solid var(--slate-200); border-radius: var(--radius); transition: all 0.2s ease; }
        .blockchain-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: var(--success-bg); color: var(--success); border-radius: 4px; font-size: 0.65rem; font-weight: 800; margin-top: 6px; }

        .preview-modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.9); z-index: 3000; align-items: center; justify-content: center; }
        .preview-container { width: 95%; height: 95vh; background: white; border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; }
        .preview-header { padding: 15px; background: var(--navy); color: white; display: flex; justify-content: space-between; align-items: center; }
        .preview-body { flex: 1; display: flex; justify-content: center; align-items: center; overflow: auto; background: #334155; }
        .preview-body img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .preview-body iframe { width: 100%; height: 100%; border: none; }

        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast { padding: 16px 24px; border-radius: 12px; color: white; font-weight: 600; animation: slideIn 0.3s ease; min-width: 300px; box-shadow: 0 10px 15px rgba(0,0,0,0.2); }
        .toast.success { background: var(--success); }
        .toast.error { background: var(--error); }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        .tabs { display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 2px solid var(--slate-200); }
        .tab-btn { padding: 12px 24px; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-weight: 600; color: var(--slate-600); transition: all 0.2s ease; font-family: 'Inter', sans-serif; }
        .tab-btn:hover { color: var(--navy); }
        .tab-btn.active { color: var(--navy); border-bottom-color: var(--gold); }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }
    </style>
</head>
<body class="dashboard-scope">
    <div class="toast-container" id="toastContainer"></div>

    <?php include __DIR__ . '/../../../../includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="section-header">
            <div>
                <h2>Affiliation Applications</h2>
                <p>Review and verify institutional documents with Blockchain integrity.</p>
            </div>
            <div style="display: flex; gap: 12px; align-items: center;">
                <span id="selectedCount" style="display: none; color: var(--slate-600); font-weight: 600;"></span>
                <button id="bulkApproveBtn" onclick="bulkApprove()" class="btn btn-success" style="display: none;" disabled>
                    <i class="fas fa-check-double"></i> Bulk Approve Selected
                </button>
            </div>
        </header>

        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('pending')">Pending Review (<?php echo count($pendingApps); ?>)</button>
            <button class="tab-btn" onclick="switchTab('approved')">Approved (<?php echo count($approvedApps); ?>)</button>
            <button class="tab-btn" onclick="switchTab('rejected')">Rejected (<?php echo count($rejectedApps); ?>)</button>
        </div>

        <!-- Bulk Actions Bar -->
        <div id="bulkActionsBar" style="display: none; background: white; padding: 16px 24px; border-radius: var(--radius); box-shadow: var(--shadow); margin-bottom: 24px; border: 1px solid var(--slate-200);">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()" style="width: 18px; height: 18px; cursor: pointer;">
                    <label for="selectAll" style="font-weight: 600; cursor: pointer; margin: 0;">Select All</label>
                    <span id="selectedCountText" style="color: var(--slate-600);"></span>
                </div>
                <div style="display: flex; gap: 12px;">
                    <button onclick="bulkApprove()" class="btn btn-success" id="bulkApproveBtn2">
                        <i class="fas fa-check-double"></i> Approve Selected
                    </button>
                    <button onclick="addNotesToSelected()" class="btn btn-outline">
                        <i class="fas fa-sticky-note"></i> Add Notes
                    </button>
                </div>
            </div>
        </div>

        <div class="tab-panel active" id="tab-pending">
            <?php if (empty($pendingApps)): ?>
                <div style="text-align: center; padding: 80px; background: white; border-radius: var(--radius); border: 2px dashed var(--slate-200); box-shadow: var(--shadow);">
                    <i class="fas fa-folder-open" style="font-size: 4rem; color: var(--slate-200); margin-bottom: 20px;"></i>
                    <h3 style="color: var(--slate-400); font-weight: 500;">No applications awaiting review.</h3>
                </div>
            <?php else: ?>
                <div style="background: white; border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--slate-200); overflow: hidden;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: var(--slate-50); border-bottom: 2px solid var(--slate-200);">
                                <th style="padding: 16px; text-align: left; font-weight: 700; color: var(--navy);">Institution Name</th>
                                <th style="padding: 16px; text-align: left; font-weight: 700; color: var(--navy);">Date Submitted</th>
                                <th style="padding: 16px; text-align: left; font-weight: 700; color: var(--navy);">Status</th>
                                <th style="padding: 16px; text-align: right; font-weight: 700; color: var(--navy);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingApps as $app): 
                                if (!is_array($app)) continue;
                                $status = $app['status'] ?? 'pending';
                            ?>
                                <tr style="border-bottom: 1px solid var(--slate-100); transition: background 0.2s;" onmouseover="this.style.background='var(--slate-50)'" onmouseout="this.style.background='white'">
                                    <td style="padding: 16px;">
                                        <div style="font-weight: 600; color: var(--navy);"><?php echo htmlspecialchars($app['institution_name'] ?? 'N/A'); ?></div>
                                        <div style="font-size: 0.85rem; color: var(--slate-400);"><?php echo htmlspecialchars($app['email'] ?? 'N/A'); ?></div>
                                    </td>
                                    <td style="padding: 16px; color: var(--slate-600);"><?php echo htmlspecialchars($app['submitted_at'] ?? 'N/A'); ?></td>
                                    <td style="padding: 16px;"><span class="status-badge <?php echo $status; ?>"><?php echo ucfirst(str_replace('_', ' ', $status)); ?></span></td>
                                    <td style="padding: 16px; text-align: right;">
                                        <button onclick="viewDocuments('<?php echo $app['id']; ?>')" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.8rem;">
                                            <i class="fas fa-file-alt"></i> Review
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="tab-panel" id="tab-approved">
            <?php if (empty($approvedApps)): ?>
                <div style="text-align: center; padding: 80px; background: white; border-radius: var(--radius); border: 2px dashed var(--slate-200); box-shadow: var(--shadow);">
                    <i class="fas fa-folder-open" style="font-size: 4rem; color: var(--slate-200); margin-bottom: 20px;"></i>
                    <h3 style="color: var(--slate-400); font-weight: 500;">No approved applications.</h3>
                </div>
            <?php else: ?>
                <div class="applications-list">
                    <?php foreach ($approvedApps as $app): 
                        if (!is_array($app)) continue;
                        $status = $app['status'] ?? 'approved';
                        $vCount = $app['verified_count'] ?? 0;
                        $progress = ($vCount / 6) * 100;
                    ?>
                        <div class="application-card <?php echo $status; ?>">
                            <div class="card-accent"></div>
                            <div class="application-main">
                                <div class="application-header">
                                    <div class="application-info" style="display: flex; align-items: center; gap: 12px;">
                                        <div>
                                            <h3><?php echo htmlspecialchars($app['institution_name'] ?? 'N/A'); ?></h3>
                                            <span class="email"><?php echo htmlspecialchars($app['email'] ?? 'N/A'); ?></span>
                                        </div>
                                    </div>
                                    <span class="status-badge <?php echo $status; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                    </span>
                                </div>
                                
                                <div class="application-details-grid">
                                    <div class="detail-item">
                                        <span class="detail-label">Contact Person</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($app['contact_person'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Position</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($app['contact_position'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Phone Number</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($app['contact_phone'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Institution Address</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($app['institution_address'] ?? 'N/A'); ?></span>
                                    </div>
                                </div>

                                <div class="application-actions">
                                    <button onclick="viewDocuments('<?php echo $app['id']; ?>')" class="btn btn-outline">
                                        <i class="fas fa-file-alt"></i> Review Documents
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="tab-panel" id="tab-rejected">
            <?php if (empty($rejectedApps)): ?>
                <div style="text-align: center; padding: 80px; background: white; border-radius: var(--radius); border: 2px dashed var(--slate-200); box-shadow: var(--shadow);">
                    <i class="fas fa-folder-open" style="font-size: 4rem; color: var(--slate-200); margin-bottom: 20px;"></i>
                    <h3 style="color: var(--slate-400); font-weight: 500;">No rejected applications.</h3>
                </div>
            <?php else: ?>
                <div class="applications-list">
                    <?php foreach ($rejectedApps as $app): 
                        if (!is_array($app)) continue;
                        $status = $app['status'] ?? 'rejected';
                        $vCount = $app['verified_count'] ?? 0;
                        $progress = ($vCount / 6) * 100;
                    ?>
                        <div class="application-card <?php echo $status; ?>">
                            <div class="card-accent"></div>
                            <div class="application-main">
                                <div class="application-header">
                                    <div class="application-info" style="display: flex; align-items: center; gap: 12px;">
                                        <div>
                                            <h3><?php echo htmlspecialchars($app['institution_name'] ?? 'N/A'); ?></h3>
                                            <span class="email"><?php echo htmlspecialchars($app['email'] ?? 'N/A'); ?></span>
                                        </div>
                                    </div>
                                    <span class="status-badge <?php echo $status; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                    </span>
                                </div>
                                
                                <div class="application-details-grid">
                                    <div class="detail-item">
                                        <span class="detail-label">Contact Person</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($app['contact_person'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Position</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($app['contact_position'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Phone Number</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($app['contact_phone'] ?? 'N/A'); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Institution Address</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($app['institution_address'] ?? 'N/A'); ?></span>
                                    </div>
                                </div>

                                <div class="application-actions">
                                    <button onclick="viewDocuments('<?php echo $app['id']; ?>')" class="btn btn-outline">
                                        <i class="fas fa-file-alt"></i> Review Documents
                                    </button>
                                </div>
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
            <div class="modal-header">
                <h3 style="margin:0;"><i class="fas fa-shield-alt"></i> Blockchain Verified Documents</h3>
                <button class="modal-close-btn" onclick="closeDocumentsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="documentsList" class="documents-list"></div>
            </div>
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

    <!-- Request Changes Modal -->
    <div id="requestChangesModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Request Changes</h3>
                <button class="modal-close-btn" style="color:white;" onclick="closeRequestChangesModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p style="color:var(--slate-600); margin-bottom:15px;">Select the documents that need correction and add notes for each.</p>
                
                <div id="requestChangesDocList" style="margin-bottom:20px;">
                    <p style="color:var(--slate-400); text-align:center; padding:20px;">Loading documents...</p>
                </div>
                
                <div style="background:var(--slate-50); border-radius:var(--radius); padding:15px; margin-bottom:20px; border:1px solid var(--slate-200);">
                    <label style="font-weight:600; color:var(--slate-700); display:block; margin-bottom:8px;">
                        <i class="fas fa-comment-dots"></i> General Instructions
                    </label>
                    <textarea id="changesInstructions" style="width:100%; height:100px; padding:12px; border-radius:8px; border:1px solid var(--slate-200); font-family:inherit;" placeholder="Example: The Letter of Intent is missing the official seal..."></textarea>
                </div>
                
                <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:20px;">
                    <button onclick="closeRequestChangesModal()" class="btn btn-outline">Cancel</button>
                    <button onclick="confirmRequestChanges()" class="btn btn-warning">Send Request</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header" style="background:var(--error);">
                <h3>Reject Application</h3>
                <button class="modal-close-btn" style="color:white;" onclick="closeRejectModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p style="color:var(--slate-600); margin-bottom:15px;">Provide a detailed reason why this application cannot be approved.</p>
                <textarea id="rejectionReason" style="width:100%; height:150px; padding:15px; border-radius:var(--radius); border:1px solid var(--slate-200); font-family:inherit;" placeholder="Example: Institution does not meet the minimum member requirement..."></textarea>
                <div style="display:flex; justify-content:flex-end; gap:12px; margin-top:20px;">
                    <button onclick="closeRejectModal()" class="btn btn-outline">Cancel</button>
                    <button onclick="confirmReject()" class="btn btn-danger">Confirm Reject</button>
                </div>
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
            } catch (e) { return null; }
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

            const docKeys = Object.keys(docsObj).filter(k => k !== 'review_notes');
            activeDocs = docKeys.map(key => docsObj[key]);

            if (docKeys.length === 0) {
                list.innerHTML = '<p style="text-align:center; color:var(--slate-400);">No documents uploaded.</p>';
            } else {
                docKeys.forEach((key, index) => {
                    const doc = docsObj[key];
                    const hash = doc.blockchain_hash ? doc.blockchain_hash.substring(0, 16) + '...' : 'No Hash';
                    const isVerified = doc.verified === true;
                    
                    list.innerHTML += `
                        <div id="doc-row-${id}-${index}" class="document-item" style="${isVerified ? 'border-left: 5px solid var(--success); background: #f0fdf4;' : ''}">
                            <div class="doc-info">
                                <div id="doc-name-${id}-${index}" style="font-weight:600; color:var(--slate-900);">
                                    <i class="fas fa-file-pdf" style="color:var(--error); margin-right:8px;"></i> 
                                    ${doc.name || 'Unnamed File'}
                                    ${isVerified ? '<span style="color:var(--success); margin-left:10px;"><i class="fas fa-check-circle"></i> Verified</span>' : ''}
                                </div>
                                <div class="blockchain-badge"><i class="fas fa-link"></i> BC-Hash: ${hash}</div>
                            </div>
                            <div id="doc-btns-${id}-${index}" style="display:flex; gap:10px;">
                                ${!isVerified ? `<button class="btn btn-success" onclick="verifyDocument('${id}', '${key}', ${index})">Verify</button>` : ''}
                                <button class="btn btn-outline" onclick="previewDoc(${index})">View</button>
                                <button class="btn btn-outline" onclick="downloadDoc(${index})">Download</button>
                            </div>
                        </div>`;
                });
            }
            document.getElementById('documentsModal').style.display = 'flex';
        };

        window.verifyDocument = async function(appId, docKey, index) {
            const formData = new FormData();
            formData.append('action', 'verify_document');
            formData.append('application_id', appId);
            formData.append('doc_key', docKey);

            try {
                const res = await fetch('/IECEP-LSC-MEMSYS/public/portal/admin/affiliation_action.php', { 
                    method: 'POST', 
                    body: formData 
                });
                const data = await res.json();
                
                if (data.success) {
                    showToast('success', 'Document verified!');
                    
                    // 1. Update the Document Row in Modal
                    const row = document.getElementById(`doc-row-${appId}-${index}`);
                    const nameDiv = document.getElementById(`doc-name-${appId}-${index}`);
                    const btnDiv = document.getElementById(`doc-btns-${appId}-${index}`);
                    
                    row.style.borderLeft = '5px solid var(--success)';
                    row.style.background = '#f0fdf4';
                    nameDiv.innerHTML += `<span style="color:var(--success); margin-left:10px;"><i class="fas fa-check-circle"></i> Verified</span>`;
                    btnDiv.innerHTML = `
                        <button class="btn btn-outline" onclick="previewDoc(${index})">View</button>
                        <button class="btn btn-outline" onclick="downloadDoc(${index})">Download</button>
                    `;

                    // 2. Update Progress Bar and Count on Main Page
                    const app = applicationsData.find(a => a.id === appId);
                    app.verified_count++;
                    
                    const vCountText = document.getElementById(`vcount-${appId}`);
                    const progressFill = document.getElementById(`progress-${appId}`);
                    
                    if(vCountText) vCountText.innerText = `${app.verified_count} / 6 Docs Verified`;
                    if(progressFill) {
                        const newWidth = (app.verified_count / 6) * 100;
                        progressFill.style.width = newWidth + '%';
                        if(app.verified_count >= 6) progressFill.classList.add('complete');
                    }

                    // 3. Enable Approve Button if count reached 6
                    if(app.verified_count >= 6) {
                        const approveBtn = document.getElementById(`approve-${appId}`);
                        if(approveBtn) {
                            approveBtn.disabled = false;
                            approveBtn.style.opacity = '1';
                            approveBtn.style.cursor = 'pointer';
                        }
                    }
                } else {
                    showToast('error', 'Error: ' + data.error);
                }
            } catch (e) {
                showToast('error', 'Server connection error');
            }
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
                body.innerHTML = '<div style="text-align:center; color:white;">Error loading content.</div>';
                document.getElementById('previewModal').style.display = 'flex';
                return;
            }

            if (['xls', 'xlsx', 'csv'].includes(ext)) {
                body.innerHTML = `
                    <div style="text-align:center; color:white; padding:2rem;">
                        <i class="fas fa-file-excel" style="font-size:4rem; color:var(--success); margin-bottom:1rem;"></i>
                        <h3>Excel Document</h3>
                        <p>This type of file cannot be previewed in the browser.</p>
                        <a href="${blobUrl}" download="${doc.name}" class="btn btn-success" style="text-decoration:none; margin-top:1rem;">
                            <i class="fas fa-download"></i> Download to View
                        </a>
                    </div>`;
            } else if (mime === 'application/pdf' || ext === 'pdf') {
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

        window.approveApplication = async function(id) {
            if (!confirm('Are you sure you want to approve this affiliation?')) return;
            const formData = new FormData();
            formData.append('action', 'approve');
            formData.append('application_id', id);
            const res = await fetch('/IECEP-LSC-MEMSYS/public/portal/admin/affiliation_action.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) { showToast('success', 'Approved successfully!'); setTimeout(() => location.reload(), 1500); }
            else { showToast('error', 'Error: ' + data.error); }
        };

        window.showRequestChangesModal = (id) => { 
            currentAppId = id; 
            const app = applicationsData.find(a => a.id === id);
            if (!app) return showToast('error', 'Application not found');
            
            const docList = document.getElementById('requestChangesDocList');
            docList.innerHTML = '';
            
            let docsObj = {};
            try { 
                docsObj = typeof app.documents === 'string' ? JSON.parse(app.documents) : (app.documents || {}); 
            } catch(e) { docsObj = {}; }
            
            const docKeys = Object.keys(docsObj).filter(k => k !== 'review_notes');
            
            if (docKeys.length === 0) {
                docList.innerHTML = '<p style="color:var(--slate-400); text-align:center; padding:20px;">No documents uploaded.</p>';
            } else {
                docList.innerHTML = '<div style="display:flex; flex-direction:column; gap:12px;">';
                docKeys.forEach((key, index) => {
                    const doc = docsObj[key];
                    const docName = doc.name || key;
                    docList.innerHTML += `
                        <div style="display:flex; align-items:flex-start; gap:12px; padding:12px; background:white; border:1px solid var(--slate-200); border-radius:var(--radius);">
                            <input type="checkbox" id="req-doc-${index}" data-doc-key="${key}" data-doc-name="${docName}" 
                                   style="width:18px; height:18px; margin-top:4px; cursor:pointer;">
                            <div style="flex:1;">
                                <label for="req-doc-${index}" style="font-weight:600; color:var(--slate-900); cursor:pointer; display:block; margin-bottom:6px;">
                                    <i class="fas fa-file-pdf" style="color:var(--error); margin-right:6px;"></i> ${docName}
                                </label>
                                <textarea id="req-doc-note-${index}" placeholder="Note for this document..." 
                                          style="width:100%; height:60px; padding:8px; border-radius:6px; border:1px solid var(--slate-200); font-size:0.85rem; font-family:inherit; resize:vertical;"></textarea>
                            </div>
                        </div>`;
                });
                docList.innerHTML += '</div>';
            }
            
            document.getElementById('requestChangesModal').style.display = 'flex';
        };
        window.closeRequestChangesModal = () => document.getElementById('requestChangesModal').style.display = 'none';
        window.confirmRequestChanges = async function() {
            const generalNote = document.getElementById('changesInstructions').value;
            if (!generalNote) return showToast('info', 'Please provide general instructions');
            
            const checkboxes = document.querySelectorAll('#requestChangesDocList input[type="checkbox"]:checked');
            if (checkboxes.length === 0) return showToast('info', 'Please select at least one document');
            
            const selectedDocs = [];
            checkboxes.forEach(cb => {
                const note = document.getElementById('req-doc-note-' + cb.id.split('-')[2]).value;
                selectedDocs.push({
                    key: cb.dataset.docKey,
                    name: cb.dataset.docName,
                    note: note
                });
            });
            
            const formData = new FormData();
            formData.append('action', 'request_changes');
            formData.append('application_id', currentAppId);
            formData.append('changes_instructions', generalNote);
            formData.append('selected_documents', JSON.stringify(selectedDocs));
            
            const res = await fetch('/IECEP-LSC-MEMSYS/public/portal/admin/affiliation_action.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) { showToast('success', 'Request sent!'); closeRequestChangesModal(); setTimeout(() => location.reload(), 1500); }
            else showToast('error', 'Error: ' + data.error);
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

        window.saveNote = async function(id) {
            const note = document.getElementById('notes-' + id).value;
            const formData = new FormData();
            formData.append('action', 'add_note');
            formData.append('application_id', id);
            formData.append('note', note);
            const res = await fetch('/IECEP-LSC-MEMSYS/public/portal/admin/affiliation_action.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) showToast('success', 'Note saved');
            else showToast('error', 'Error: ' + data.error);
        };

        window.resubmitApplication = async function(id) {
            if (!confirm('Resubmit this application for review?')) return;
            const formData = new FormData();
            formData.append('action', 'resubmit');
            formData.append('application_id', id);
            const res = await fetch('/IECEP-LSC-MEMSYS/public/portal/admin/affiliation_action.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) { showToast('success', 'Resubmitted for review'); setTimeout(() => location.reload(), 1500); }
            else showToast('error', 'Error: ' + data.error);
        };

        window.updateBulkActions = function() {
            const checkboxes = document.querySelectorAll('.app-checkbox:checked');
            const count = checkboxes.length;
            const bulkBtn = document.getElementById('bulkApproveBtn');
            const bulkBtn2 = document.getElementById('bulkApproveBtn2');
            const bulkBar = document.getElementById('bulkActionsBar');
            const selectedCountText = document.getElementById('selectedCountText');
            const selectAll = document.getElementById('selectAll');

            if (count > 0) {
                bulkBtn.style.display = 'inline-flex';
                bulkBtn.disabled = false;
                bulkBtn2.style.display = 'inline-flex';
                bulkBtn2.disabled = false;
                bulkBar.style.display = 'block';
                selectedCountText.textContent = count + ' selected';
            } else {
                bulkBtn.style.display = 'none';
                bulkBtn2.style.display = 'none';
                bulkBar.style.display = 'none';
                selectAll.checked = false;
            }
        };

        window.toggleSelectAll = function() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.app-checkbox:not([disabled])');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateBulkActions();
        };

        window.bulkApprove = async function() {
            const checkboxes = document.querySelectorAll('.app-checkbox:checked');
            if (checkboxes.length === 0) return showToast('info', 'No applications selected');

            if (!confirm('Approve ' + checkboxes.length + ' selected applications?')) return;

            const ids = Array.from(checkboxes).map(cb => cb.dataset.id);
            const formData = new FormData();
            formData.append('action', 'bulk_approve');
            formData.append('application_ids', JSON.stringify(ids));

            const res = await fetch('/IECEP-LSC-MEMSYS/public/portal/admin/affiliation_action.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                showToast('success', data.message);
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('error', 'Error: ' + data.error);
            }
        };

        window.addNotesToSelected = async function() {
            const checkboxes = document.querySelectorAll('.app-checkbox:checked');
            if (checkboxes.length === 0) return showToast('info', 'No applications selected');

            const note = prompt('Enter note for selected applications:');
            if (!note) return;

            const ids = Array.from(checkboxes).map(cb => cb.dataset.id);
            for (const id of ids) {
                const formData = new FormData();
                formData.append('action', 'add_note');
                formData.append('application_id', id);
                formData.append('note', note);
                await fetch('/IECEP-LSC-MEMSYS/public/portal/admin/affiliation_action.php', { method: 'POST', body: formData });
            }
            showToast('success', 'Notes added to selected applications');
            setTimeout(() => location.reload(), 1000);
        };

        window.onclick = (e) => {
            if (e.target.classList.contains('modal')) {
                document.querySelectorAll('.modal').forEach(m => m.style.display = 'none');
            }
        };

        window.switchTab = function(tabName) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById('tab-' + tabName).classList.add('active');
        };
    </script>
</body>
</html>
