<?php
if (!isset($current_page)) { $current_page = 'upload-members'; }
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../auth_check.php';
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/csrf.php';

require_role(['school_officer', 'admin', 'super_admin']);

$csrf_token = generate_csrf_token();

$user = $_SESSION['user'] ?? [];
$userId = $user['id'] ?? $_SESSION['user_id'] ?? null;
$institutionId = $_SESSION['institution_id'] ?? $user['institution_id'] ?? null;

$supabase = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);
$recentBatches = [];
if ($institutionId) {
    try {
        $recentBatches = $supabase->select('upload_batches', [
            'institution_id' => 'eq.' . $institutionId,
            'order' => 'uploaded_at.desc',
            'limit' => '5'
        ]);
    } catch (Exception $e) {
        $recentBatches = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Member Directory - IECEP-LSC MEMSYS</title>
    <?php require_once __DIR__ . '/../../../../includes/head-meta.php'; ?>
    <style>
        .upload-container {
            max-width: 880px;
            margin: 0 auto;
        }

        .upload-zone {
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 3rem 2rem;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .upload-zone:hover,
        .upload-zone.dragover {
            background: #f1f5f9;
            border-color: var(--memsys-navy);
        }

        .upload-zone-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #e2e8f0;
            color: var(--memsys-navy);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.2s ease;
        }

        .upload-zone:hover .upload-zone-icon,
        .upload-zone.dragover .upload-zone-icon {
            background: var(--memsys-navy);
            color: #ffffff;
            transform: scale(1.05);
        }

        .file-selected-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .format-badge {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.2rem 0.5rem;
            background: #e2e8f0;
            color: #334155;
            border-radius: 4px;
            margin: 0 0.15rem;
        }

        .helper-callout {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.85rem 1.25rem;
            font-size: 0.88rem;
            color: #475569;
        }

        details.format-details {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.85rem 1.25rem;
            background: #ffffff;
            font-size: 0.9rem;
        }

        details.format-details summary {
            font-weight: 600;
            color: var(--memsys-navy);
            cursor: pointer;
            user-select: none;
        }

        details.format-details[open] summary {
            margin-bottom: 0.75rem;
        }

        body.dark-mode .upload-zone {
            background: #0f172a;
            border-color: #334155;
        }

        body.dark-mode .upload-zone:hover,
        body.dark-mode .upload-zone.dragover {
            background: #1e293b;
            border-color: var(--memsys-gold);
        }

        body.dark-mode .upload-zone-icon {
            background: #1e293b;
            color: #e2e8f0;
        }

        body.dark-mode .file-selected-card,
        body.dark-mode .helper-callout,
        body.dark-mode details.format-details {
            background: #1e293b;
            border-color: #334155;
            color: #e2e8f0;
        }

        body.dark-mode .format-badge {
            background: #334155;
            color: #f1f5f9;
        }

        body.dark-mode details.format-details summary {
            color: var(--memsys-gold);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once __DIR__ . '/../../../../includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="container py-4 upload-container">
                <!-- Clean Breadcrumbs & Header -->
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 pb-2 border-bottom">
                    <div>
                        <div class="text-muted small mb-1">
                            <a href="<?= BASE_URL ?>/public/portal/school-officer/dashboard.php" class="text-muted text-decoration-none">School Portal</a>
                            <span class="mx-1">/</span>
                            <span>Members</span>
                            <span class="mx-1">/</span>
                            <span class="text-dark fw-semibold">Upload Directory</span>
                        </div>
                        <h2 class="fw-bold text-dark mb-0">Upload Member Directory</h2>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="downloadSampleTemplate()">
                            <i class="fas fa-download me-1"></i> Sample Template
                        </button>
                        <a href="<?= BASE_URL ?>/public/portal/school-officer/dashboard.php" class="btn btn-sm btn-outline">
                            <i class="fas fa-arrow-left me-1"></i> Dashboard
                        </a>
                    </div>
                </div>

                <!-- Main Card -->
                <div class="card mb-4">
                    <p class="text-muted mb-3" style="font-size: 0.95rem;">
                        Upload your official member directory workbook. The system will parse student records, validate data formats, and queue members for official registration.
                    </p>

                    <!-- Sheet Structure Notice -->
                    <div class="helper-callout d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-info-circle text-primary"></i>
                            <span>Workbook must contain 4 sheets:</span>
                            <span class="format-badge">1st Yr</span>
                            <span class="format-badge">2nd Yr</span>
                            <span class="format-badge">3rd Yr</span>
                            <span class="format-badge">4th Yr</span>
                        </div>
                        <span class="text-muted small">Max 10 MB (.xlsx, .xls, .csv)</span>
                    </div>

                    <form id="uploadForm" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="file" id="fileInput" name="directory_file" accept=".xlsx,.xls,.csv" style="display: none;">

                        <!-- Clean Dropzone -->
                        <div class="upload-zone mb-3" id="uploadArea">
                            <div class="upload-zone-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <h5 class="fw-bold mb-1 text-dark">Click to browse or drag and drop</h5>
                            <p class="text-muted small mb-0">Excel Workbook (.xlsx, .xls) or CSV format</p>
                        </div>

                        <!-- Selected File Preview -->
                        <div id="fileSelected" class="mb-3" style="display: none;">
                            <div class="file-selected-card">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="fas fa-file-excel text-success fs-3"></i>
                                    <div>
                                        <div class="fw-semibold text-dark" id="fileName">directory.xlsx</div>
                                        <div class="text-muted small" id="fileSize">0 KB</div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline text-danger" id="removeFileBtn" title="Remove file">
                                    <i class="fas fa-times me-1"></i> Change File
                                </button>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 mt-3" id="submitBtn">
                                <i class="fas fa-upload me-1"></i> Upload and Process Directory
                            </button>
                        </div>

                        <!-- Progress Bar Container -->
                        <div class="progress-container mt-3" id="progressContainer" style="display: none;">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small fw-semibold text-muted" id="progressText">Uploading workbook...</span>
                                <span class="small fw-bold text-primary" id="progressPercent">0%</span>
                            </div>
                            <div class="progress" style="height: 8px; border-radius: 4px; background: #e2e8f0;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                     id="progressBar" 
                                     role="progressbar" 
                                     style="width: 0%; background: var(--memsys-navy);"></div>
                            </div>
                        </div>
                    </form>

                    <!-- Response Message -->
                    <div id="responseMessage" class="mt-3" style="display: none;"></div>

                    <!-- Success Card -->
                    <div id="successCard" class="p-4 rounded-3 border border-success bg-light mt-4" style="display: none;">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <i class="fas fa-check-circle text-success fs-2"></i>
                            <div>
                                <h5 class="mb-0 text-success fw-bold">Directory Upload Complete</h5>
                                <p class="text-muted small mb-0">Your member batch has been successfully received and validated.</p>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <div class="p-3 bg-white rounded border">
                                    <div class="text-muted small mb-1">Batch Reference ID</div>
                                    <div class="d-flex align-items-center justify-content-between">
                                        <code class="fw-bold fs-6" id="batchId">-</code>
                                        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" id="copyBatchBtn" onclick="copyBatchId()" title="Copy ID">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="p-3 bg-white rounded border">
                                    <div class="text-muted small mb-1">Total Rows Processed</div>
                                    <div class="h5 fw-bold mb-0 text-dark" id="totalRows">0</div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <a href="<?= BASE_URL ?>/public/portal/school-officer/dashboard.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-arrow-left me-1"></i> Return to Dashboard
                            </a>
                            <button type="button" class="btn btn-sm btn-outline" onclick="window.location.reload();">
                                <i class="fas fa-redo me-1"></i> Upload Another File
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Collapsible Expected Format Drawer -->
                <details class="format-details mb-4">
                    <summary><i class="fas fa-table me-1"></i> View Expected Column Headers & Requirements</summary>
                    <p class="text-muted small mb-2 mt-2">Each sheet (<code>1st Yr</code>, <code>2nd Yr</code>, <code>3rd Yr</code>, <code>4th Yr</code>) should have the following headers on row 1 or 2:</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Header</th>
                                    <th>Field Description</th>
                                    <th>Format / Example</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>#</code></td>
                                    <td>Row Number</td>
                                    <td>1, 2, 3...</td>
                                    <td><span class="badge bg-success">Required</span></td>
                                </tr>
                                <tr>
                                    <td><code>Name</code></td>
                                    <td>Full Name (Last Name, First Name)</td>
                                    <td>Dela Cruz, Juan</td>
                                    <td><span class="badge bg-success">Required</span></td>
                                </tr>
                                <tr>
                                    <td><code>Birthday</code></td>
                                    <td>Date of Birth</td>
                                    <td>YYYY-MM-DD</td>
                                    <td><span class="badge bg-secondary">Optional</span></td>
                                </tr>
                                <tr>
                                    <td><code>Address</code></td>
                                    <td>Home / Campus Address</td>
                                    <td>Santa Cruz, Laguna</td>
                                    <td><span class="badge bg-secondary">Optional</span></td>
                                </tr>
                                <tr>
                                    <td><code>Cellphone</code></td>
                                    <td>Mobile Contact Number</td>
                                    <td>09123456789</td>
                                    <td><span class="badge bg-success">Required</span></td>
                                </tr>
                                <tr>
                                    <td><code>Email</code></td>
                                    <td>Student / Institutional Email</td>
                                    <td>student@school.edu.ph</td>
                                    <td><span class="badge bg-success">Required</span></td>
                                </tr>
                                <tr>
                                    <td><code>IECEP ID Number</code></td>
                                    <td>Existing Chapter ID</td>
                                    <td>IECEP-2026-XXXX</td>
                                    <td><span class="badge bg-secondary">Optional</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </details>

                <!-- Recent Upload Batches Section -->
                <?php if (!empty($recentBatches)): ?>
                <div class="card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0 text-dark"><i class="fas fa-history me-1 text-muted"></i> Recent Upload Batches</h6>
                        <span class="text-muted small"><?= count($recentBatches) ?> recorded</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Batch Reference</th>
                                    <th>File Name</th>
                                    <th>Rows</th>
                                    <th>Status</th>
                                    <th>Uploaded At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentBatches as $batch): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($batch['id'] ?? 'N/A') ?></code></td>
                                        <td><?= htmlspecialchars($batch['file_name'] ?? 'directory.xlsx') ?></td>
                                        <td><?= number_format($batch['total_rows'] ?? 0) ?></td>
                                        <td>
                                            <?php
                                            $st = $batch['status'] ?? 'pending';
                                            $badgeClass = match($st) {
                                                'completed', 'approved' => 'bg-success',
                                                'in_progress', 'validated' => 'bg-primary',
                                                'failed', 'rejected' => 'bg-danger',
                                                default => 'bg-secondary'
                                            };
                                            ?>
                                            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($st)) ?></span>
                                        </td>
                                        <td class="text-muted small"><?= date('M d, Y - h:i A', strtotime($batch['uploaded_at'] ?? 'now')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const fileSelected = document.getElementById('fileSelected');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const removeFileBtn = document.getElementById('removeFileBtn');
        const uploadForm = document.getElementById('uploadForm');
        const progressContainer = document.getElementById('progressContainer');
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');
        const progressPercent = document.getElementById('progressPercent');
        const responseMessage = document.getElementById('responseMessage');
        const successCard = document.getElementById('successCard');

        uploadArea.addEventListener('click', () => fileInput.click());

        ['dragenter', 'dragover'].forEach(name => {
            uploadArea.addEventListener(name, (e) => {
                e.preventDefault();
                e.stopPropagation();
                uploadArea.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(name => {
            uploadArea.addEventListener(name, (e) => {
                e.preventDefault();
                e.stopPropagation();
                uploadArea.classList.remove('dragover');
            });
        });

        uploadArea.addEventListener('drop', (e) => {
            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                handleFileSelect();
            }
        });

        fileInput.addEventListener('change', handleFileSelect);

        function handleFileSelect() {
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                fileName.textContent = file.name;
                fileSize.textContent = formatBytes(file.size);
                fileSelected.style.display = 'block';
                uploadArea.style.display = 'none';
                responseMessage.style.display = 'none';
            }
        }

        if (removeFileBtn) {
            removeFileBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                fileInput.value = '';
                fileSelected.style.display = 'none';
                uploadArea.style.display = 'block';
            });
        }

        function formatBytes(bytes, decimals = 1) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }

        uploadForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (fileInput.files.length === 0) {
                showError('Please select an Excel directory file first.');
                return;
            }

            const formData = new FormData(uploadForm);
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing Directory...';

            try {
                progressContainer.style.display = 'block';
                updateProgress(35, 'Uploading workbook to server...');

                const response = await fetch('../../api/affiliate/upload-directory.php', {
                    method: 'POST',
                    body: formData
                });

                updateProgress(75, 'Parsing worksheets and validating rows...');

                const data = await response.json();

                if (data.success) {
                    updateProgress(100, 'Import completed successfully.');
                    const finalBatchId = data.batch_id || 'BATCH-' + Math.random().toString(36).substring(2, 9).toUpperCase();
                    document.getElementById('batchId').textContent = finalBatchId;
                    document.getElementById('totalRows').textContent = (data.total_rows || '0') + ' student records';
                    
                    setTimeout(() => {
                        successCard.style.display = 'block';
                        uploadForm.style.display = 'none';
                        progressContainer.style.display = 'none';
                    }, 350);

                    if (data.import_errors && data.import_errors.length > 0) {
                        const warnings = document.createElement('div');
                        warnings.className = 'alert alert-warning mt-3';
                        warnings.innerHTML = '<strong><i class="fas fa-exclamation-triangle me-1"></i>Import Warnings:</strong><ul class="mb-0 mt-2">' +
                            data.import_errors.map(err => '<li>' + err + '</li>').join('') +
                            '</ul>';
                        successCard.insertAdjacentElement('afterend', warnings);
                    }
                } else {
                    showError(data.message || 'Upload failed. Please check workbook sheets and column headers.');
                    resetSubmitButton();
                }
            } catch (error) {
                showError('Server error: ' + error.message);
                resetSubmitButton();
            }
        });

        function updateProgress(percent, text) {
            progressBar.style.width = percent + '%';
            progressPercent.textContent = percent + '%';
            if (text) progressText.textContent = text;
        }

        function resetSubmitButton() {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-upload me-1"></i> Upload and Process Directory';
        }

        function showError(message) {
            responseMessage.style.display = 'block';
            responseMessage.innerHTML = '<div class="alert alert-danger d-flex align-items-center gap-2 mb-0">' +
                '<i class="fas fa-exclamation-circle flex-shrink-0"></i> <div>' + message + '</div></div>';
            progressContainer.style.display = 'none';
        }

        function copyBatchId() {
            const batchText = document.getElementById('batchId').textContent;
            navigator.clipboard.writeText(batchText).then(() => {
                const btn = document.getElementById('copyBatchBtn');
                btn.innerHTML = '<i class="fas fa-check text-success"></i>';
                setTimeout(() => { btn.innerHTML = '<i class="fas fa-copy"></i>'; }, 2000);
            });
        }

        function downloadSampleTemplate() {
            const csvContent = "data:text/csv;charset=utf-8," + 
                "# (Number),Name,Birthday,Address,Cellphone,Email,IECEP ID Number\n" +
                "1,Dela Cruz Juan,2003-05-15,Santa Cruz Laguna,09123456789,juan.delacruz@school.edu,IECEP-2026-001\n" +
                "2,Santos Maria,2004-08-20,San Pablo Laguna,09987654321,maria.santos@school.edu,IECEP-2026-002\n";
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "IECEP_Member_Directory_Template.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>
