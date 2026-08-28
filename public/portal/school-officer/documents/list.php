<?php
if (!isset($current_page)) { $current_page = 'documents'; }
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../auth_check.php';
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/role-config.php';
require_once __DIR__ . '/../../../../includes/csrf.php';

require_role(['school_officer', 'admin', 'super_admin']);

$user = $_SESSION['user'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affiliation Documents - IECEP-LSC MEMSYS</title>
    <?php require_once __DIR__ . '/../../../../includes/head-meta.php'; ?>
    <style>
        .form-container { max-width: 900px; margin: 0 auto; }
        .file-upload-area { border: 2px dashed #cbd5e1; border-radius: 12px; padding: 20px; text-align: center; transition: var(--transition); cursor: pointer; background: #fafcff; }
        .file-upload-area:hover { border-color: var(--memsys-navy); background: #f0f4ff; }
        .file-upload-area input[type="file"] { display: none; }
        .file-upload-label { cursor: pointer; color: var(--memsys-text-muted); }
        .file-upload-label i { font-size: 2rem; color: var(--memsys-navy); margin-bottom: 8px; display: block; }
        .file-name { margin-top: 8px; font-size: 0.875rem; color: var(--memsys-success); font-weight: 600; }
        .documents-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 32px; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once __DIR__ . '/../../../../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="container py-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 pb-2 border-bottom">
                    <div>
                        <div class="text-muted small mb-1">
                            <a href="<?= BASE_URL ?>/public/portal/school-officer/dashboard.php" class="text-muted text-decoration-none">School Portal</a>
                            <span class="mx-1">/</span>
                            <span class="text-dark fw-semibold">Documents</span>
                        </div>
                        <h2 class="fw-bold text-dark mb-0">
                            <i class="fas fa-folder-open text-primary me-2"></i>Affiliation Document Submission
                        </h2>
                    </div>
                    <div>
                        <a href="<?= BASE_URL ?>/public/portal/school-officer/dashboard.php" class="btn btn-sm btn-outline">
                            <i class="fas fa-arrow-left me-1"></i> Dashboard
                        </a>
                    </div>
                </div>

                <div class="form-container">
                    <div class="card card-navy-top">
                        <div class="mb-4 text-center">
                            <h4 class="fw-bold text-dark mb-1">Chapter Affiliation Document Kit</h4>
                            <p class="text-muted small">Please complete all fields and upload all required documents for academic year endorsement.</p>
                        </div>

                <div class="alert alert-info">
                    <strong><i class="fas fa-info-circle"></i> Required Documents (IECEP Constitution Article IV Section 3):</strong>
                    <ul style="margin: 8px 0 0 20px;">
                        <li>Letter of Intent</li>
                        <li>Endorsement Letter</li>
                        <li>Constitution and Bylaws</li>
                        <li>List of Officers with CV</li>
                        <li>Departmental/Organizational Chart</li>
                        <li>Updated Member Directory (Excel with 4 sheets: 1st Yr, 2nd Yr, 3rd Yr, 4th Yr)</li>
                    </ul>
                </div>

                <div id="alert-container"></div>

                <form id="affiliationForm" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                    <div class="section-title">School Information</div>

                    <div class="form-group">
                        <label>School Name <span class="required">*</span></label>
                        <input type="text" name="school_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Organization Name <span class="required">*</span></label>
                        <input type="text" name="org_name" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Representative Name <span class="required">*</span></label>
                        <input type="text" name="rep_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Representative Email <span class="required">*</span></label>
                        <input type="email" name="rep_email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required readonly>
                    </div>

                    <div class="section-title">Required Documents</div>

                    <div class="documents-grid">
                        <div class="form-group">
                            <label>1. Letter of Intent <span class="required">*</span></label>
                            <div class="file-upload-area" onclick="document.getElementById('letter_of_intent').click()">
                                <label class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <div>Click to upload</div>
                                    <small>PDF, DOCX (Max 10MB)</small>
                                </label>
                                <input type="file" id="letter_of_intent" name="letter_of_intent" accept=".pdf,.docx" required onchange="showFileName(this)">
                                <div class="file-name"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>2. Endorsement Letter <span class="required">*</span></label>
                            <div class="file-upload-area" onclick="document.getElementById('endorsement_letter').click()">
                                <label class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <div>Click to upload</div>
                                    <small>PDF, DOCX (Max 10MB)</small>
                                </label>
                                <input type="file" id="endorsement_letter" name="endorsement_letter" accept=".pdf,.docx" required onchange="showFileName(this)">
                                <div class="file-name"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>3. Constitution and Bylaws <span class="required">*</span></label>
                            <div class="file-upload-area" onclick="document.getElementById('constitution_bylaws').click()">
                                <label class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <div>Click to upload</div>
                                    <small>PDF, DOCX (Max 10MB)</small>
                                </label>
                                <input type="file" id="constitution_bylaws" name="constitution_bylaws" accept=".pdf,.docx" required onchange="showFileName(this)">
                                <div class="file-name"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>4. List of Officers with CV <span class="required">*</span></label>
                            <div class="file-upload-area" onclick="document.getElementById('officers_cv').click()">
                                <label class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <div>Click to upload</div>
                                    <small>PDF, DOCX (Max 10MB)</small>
                                </label>
                                <input type="file" id="officers_cv" name="officers_cv" accept=".pdf,.docx" required onchange="showFileName(this)">
                                <div class="file-name"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>5. Organizational Chart <span class="required">*</span></label>
                            <div class="file-upload-area" onclick="document.getElementById('org_chart').click()">
                                <label class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <div>Click to upload</div>
                                    <small>PDF, DOCX, PNG, JPG (Max 10MB)</small>
                                </label>
                                <input type="file" id="org_chart" name="org_chart" accept=".pdf,.docx,.png,.jpg,.jpeg" required onchange="showFileName(this)">
                                <div class="file-name"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>6. Member Directory <span class="required">*</span></label>
                            <div class="file-upload-area" onclick="document.getElementById('member_directory').click()">
                                <label class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <div>Click to upload</div>
                                    <small>XLSX, XLS (Max 10MB)</small>
                                    <small style="display:block;margin-top:4px;color:#ef4444;">Must have 4 sheets: 1st Yr, 2nd Yr, 3rd Yr, 4th Yr</small>
                                </label>
                                <input type="file" id="member_directory" name="member_directory" accept=".xlsx,.xls" required onchange="showFileName(this)">
                                <div class="file-name"></div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-paper-plane"></i> Submit Application
                    </button>
                </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    function showFileName(input) {
        const fileNameDiv = input.parentElement.querySelector('.file-name');
        if (input.files.length > 0) {
            const file = input.files[0];
            const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
            fileNameDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${file.name} (${sizeMB} MB)`;
        }
    }

    function showAlert(type, message) {
        const container = document.getElementById('alert-container');
        container.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
        container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    document.getElementById('affiliationForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

        const formData = new FormData(this);

        try {
            const response = await fetch('/IECEP-LSC-MEMSYS/public/api/affiliate/submit-affiliation.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                showAlert('success', result.message || 'Application submitted successfully! You will be notified once reviewed.');
                this.reset();
                document.querySelectorAll('.file-name').forEach(el => el.innerHTML = '');
            } else {
                showAlert('error', result.message || 'Failed to submit application. Please try again.');
            }
        } catch (error) {
            showAlert('error', 'Network error: ' + error.message);
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Application';
        }
    });
    </script>
</body>
</html>
