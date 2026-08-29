<?php
if (!isset($current_page)) { $current_page = 'upload-members'; }
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../auth_check.php';
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/role-config.php';
require_once __DIR__ . '/../../../../includes/csrf.php';

require_role(['school_officer', 'admin', 'super_admin']);

$csrf_token = generate_csrf_token();

$user = $_SESSION['user'] ?? [];
$userId = $user['id'] ?? $_SESSION['user_id'] ?? null;
$institutionId = $_SESSION['institution_id'] ?? $user['institution_id'] ?? null;

$supabase = getSupabaseClient() ?? new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);
$recentBatches = [];
if ($institutionId && $supabase) {
    try {
        $recentBatches = $supabase->select('upload_batches', [
            'institution_id' => 'eq.' . $institutionId,
            'order' => 'uploaded_at.desc',
            'limit' => '5'
        ]);
        if (!is_array($recentBatches)) $recentBatches = [];
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
    <title>Upload Member Directory — IECEP-LSC MEMSYS</title>
    <?php require_once __DIR__ . '/../../../../includes/head-meta.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/admin-portal.css">
    <style>
        :root {
            --bg-page: #F8FAFC;
            --bg-surface: #FFFFFF;
            --border-light: #E2E8F0;
            --text-heading: #0B1D4A;
            --text-primary: #0F172A;
            --text-muted: #64748B;
        }

        body {
            background-color: var(--bg-page) !important;
            font-family: 'DM Sans', 'Inter', -apple-system, sans-serif;
            color: var(--text-primary);
        }

        .upload-white-card {
            background: #FFFFFF;
            border: 1px solid var(--border-light);
            border-radius: 16px;
            padding: 2rem 2.25rem;
            box-shadow: 0 4px 20px -2px rgba(11, 29, 74, 0.04);
            max-width: 900px;
            margin: 0 auto 2rem;
        }

        .upload-dropzone-clean {
            border: 2px dashed var(--border-light);
            border-radius: 14px;
            padding: 3rem 2rem;
            text-align: center;
            background: #FAFCFF;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .upload-dropzone-clean:hover,
        .upload-dropzone-clean.dragover {
            background: #F1F5F9;
            border-color: #0B1D4A;
        }

        .dropzone-icon-circle {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: rgba(11, 29, 74, 0.06);
            color: #0B1D4A;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.2s ease;
        }

        .upload-dropzone-clean:hover .dropzone-icon-circle {
            background: #0B1D4A;
            color: #FFFFFF;
            transform: scale(1.08);
        }

        .file-preview-card {
            background: #F8FAFC;
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .format-badge-pill {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.2rem 0.55rem;
            background: #E2E8F0;
            color: #334155;
            border-radius: 4px;
            margin: 0 0.15rem;
        }

        .sheet-guideline-box {
            background: #F8FAFC;
            border: 1px solid var(--border-light);
            border-left: 4px solid #0B1D4A;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once __DIR__ . '/../../../../includes/sidebar.php'; ?>

        <main class="main-content ap-scope">
            <div class="container py-4">
                <!-- Clean Page Header -->
                <div class="ap-page-header" style="max-width: 900px; margin: 0 auto 1.5rem;">
                    <div class="ap-title-block">
                        <div class="text-muted small mb-1">
                            <a href="<?= BASE_URL ?>/public/portal/school-officer/dashboard.php" class="text-muted text-decoration-none">School Portal</a>
                            <span class="mx-1">/</span>
                            <span class="text-muted">Members</span>
                            <span class="mx-1">/</span>
                            <span class="text-dark fw-bold">Upload Directory</span>
                        </div>
                        <h1 class="ap-page-title">
                            <i class="fas fa-file-excel text-success"></i> Batch Upload Member Directory
                        </h1>
                        <p class="ap-page-subtitle">
                            Import 1st to 4th year student roster workbook (.xlsx) into the regional registration system.
                        </p>
                    </div>
                    <div class="ap-header-actions">
                        <button type="button" class="ap-btn-secondary" onclick="downloadSampleTemplate()">
                            <i class="fas fa-download me-1"></i> Sample CSV Template
                        </button>
                        <a href="<?= BASE_URL ?>/public/portal/school-officer/members/list.php" class="ap-btn-secondary">
                            <i class="fas fa-users me-1"></i> Member List
                        </a>
                    </div>
                </div>

                <!-- Upload Card -->
                <div class="upload-white-card">
                    <!-- Guidelines Notice -->
                    <div class="sheet-guideline-box d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-info-circle text-primary"></i>
                            <span class="small fw-semibold text-dark">Workbook must contain 4 worksheets:</span>
                            <span class="format-badge-pill">1st Yr</span>
                            <span class="format-badge-pill">2nd Yr</span>
                            <span class="format-badge-pill">3rd Yr</span>
                            <span class="format-badge-pill">4th Yr</span>
                        </div>
                        <span class="text-muted small">Max file size: 10 MB</span>
                    </div>

                    <div id="responseMessage" class="mb-3" style="display: none;"></div>

                    <form id="uploadForm" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="file" id="fileInput" name="directory_file" accept=".xlsx,.xls,.csv" style="display: none;">

                        <!-- Drag and Drop Zone -->
                        <div class="upload-dropzone-clean mb-3" id="uploadArea">
                            <div class="dropzone-icon-circle">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <h5 class="fw-bold mb-1 text-dark">Click to browse or drag and drop workbook</h5>
                            <p class="text-muted small mb-0">Excel Workbook (.xlsx, .xls) or CSV format</p>
                        </div>

                        <!-- Selected File Preview -->
                        <div id="fileSelected" class="mb-3" style="display: none;">
                            <div class="file-preview-card">
                                <div class="d-flex align-items-center gap-3">
                                    <i class="fas fa-file-excel text-success fa-2x"></i>
                                    <div>
                                        <div class="fw-bold text-dark" id="fileName">directory.xlsx</div>
                                        <div class="text-muted small" id="fileSize">0 KB</div>
                                    </div>
                                </div>
                                <button type="button" class="ap-btn-danger" id="removeFileBtn" style="padding: 0.35rem 0.85rem; font-size: 0.78rem;">
                                    <i class="fas fa-times me-1"></i> Change File
                                </button>
                            </div>

                            <button type="submit" class="ap-btn-primary w-100 justify-content-center mt-3" style="padding: 0.75rem 1.5rem; font-size: 0.95rem;" id="submitBtn">
                                <i class="fas fa-upload me-1"></i> Upload and Process Directory
                            </button>
                        </div>

                        <!-- Progress Bar Container -->
                        <div class="mt-3" id="progressContainer" style="display: none;">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small fw-semibold text-muted" id="progressText">Uploading workbook...</span>
                                <span class="small fw-bold text-primary" id="progressPercent">0%</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" id="progressBar" style="width: 0%"></div>
                            </div>
                        </div>
                    </form>

                    <!-- Success Card -->
                    <div id="successCard" class="mt-4" style="display: none;">
                        <div class="alert alert-success d-flex align-items-center gap-3 mb-3">
                            <i class="fas fa-check-circle fa-2x text-success"></i>
                            <div>
                                <h6 class="fw-bold mb-1">Directory Uploaded & Validated Successfully!</h6>
                                <p class="small mb-0">Batch Reference: <code class="fw-bold" id="batchId">BATCH-2026</code> &bull; Processed <span id="totalRows" class="fw-bold">0 records</span>.</p>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="<?= BASE_URL ?>/public/portal/school-officer/members/list.php" class="ap-btn-primary">
                                <i class="fas fa-users me-1"></i> View Member Directory
                            </a>
                            <button type="button" class="ap-btn-secondary" onclick="location.reload()">
                                <i class="fas fa-plus me-1"></i> Upload Another Batch
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Recent Batches Card -->
                <?php if (!empty($recentBatches)): ?>
                <div class="upload-white-card">
                    <h5 class="fw-bold text-dark mb-3" style="font-size: 1.05rem;">
                        <i class="fas fa-history text-muted me-2"></i>Recent Directory Upload Batches
                    </h5>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr style="background: #F8FAFC; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em;">
                                    <th>Batch ID</th>
                                    <th>File Name</th>
                                    <th>Total Records</th>
                                    <th>Status</th>
                                    <th>Uploaded At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentBatches as $batch): ?>
                                    <tr>
                                        <td><code class="fw-bold text-dark"><?= htmlspecialchars(substr($batch['id'] ?? 'N/A', 0, 14)) ?>...</code></td>
                                        <td class="fw-semibold text-dark"><?= htmlspecialchars($batch['file_name'] ?? 'directory.xlsx') ?></td>
                                        <td class="fw-bold"><?= number_format($batch['total_rows'] ?? 0) ?></td>
                                        <td>
                                            <?php
                                            $st = strtolower($batch['status'] ?? 'pending');
                                            $badgeClass = match($st) {
                                                'completed', 'approved', 'success' => 'bg-success',
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

                const response = await fetch('<?= BASE_URL ?>/public/api/affiliate/upload-directory.php', {
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
                } else {
                    showError(data.message || 'Upload failed. Please check workbook sheets and column headers.');
                    resetSubmitButton();
                }
            } catch (error) {
                showError('Server communication error: ' + error.message);
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
