<?php
if (!isset($current_page)) { $current_page = 'documents'; }
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../auth_check.php';
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/role-config.php';
require_once __DIR__ . '/../../../../includes/csrf.php';

require_role(['school_officer', 'admin', 'super_admin']);

$user = $_SESSION['user'] ?? [];
$userName = $user['user_metadata']['full_name'] ?? $user['name'] ?? $user['email'] ?? 'School Officer';
$userEmail = $user['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affiliation Documents — IECEP-LSC MEMSYS</title>
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

        .form-white-card {
            background: #FFFFFF;
            border: 1px solid var(--border-light);
            border-radius: 16px;
            padding: 2rem 2.25rem;
            box-shadow: 0 4px 20px -2px rgba(11, 29, 74, 0.04);
            max-width: 960px;
            margin: 0 auto;
        }

        .info-callout-card {
            background: #F8FAFC;
            border: 1px solid var(--border-light);
            border-left: 4px solid #0B1D4A;
            border-radius: 10px;
            padding: 1.15rem 1.4rem;
            margin-bottom: 1.75rem;
        }

        .doc-upload-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.25rem;
            margin-bottom: 1.75rem;
        }

        @media (max-width: 768px) {
            .doc-upload-grid { grid-template-columns: 1fr; }
            .form-white-card { padding: 1.25rem; }
        }

        .doc-upload-box {
            border: 2px dashed var(--border-light);
            border-radius: 12px;
            padding: 1.25rem 1rem;
            text-align: center;
            background: #FAFCFF;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .doc-upload-box:hover {
            border-color: #0B1D4A;
            background: #F1F5F9;
        }

        .doc-upload-box input[type="file"] {
            display: none;
        }

        .doc-upload-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(11, 29, 74, 0.06);
            color: #0B1D4A;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s ease;
        }

        .doc-upload-box:hover .doc-upload-icon {
            background: #0B1D4A;
            color: #FFFFFF;
            transform: scale(1.05);
        }

        .file-selected-name {
            margin-top: 0.5rem;
            font-size: 0.8rem;
            font-weight: 700;
            color: #059669;
            display: none;
        }

        .form-field-group {
            margin-bottom: 1.25rem;
        }

        .form-field-label {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--text-heading);
            margin-bottom: 0.35rem;
            display: block;
        }

        .form-field-input {
            width: 100%;
            padding: 0.55rem 0.95rem;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            font-size: 0.88rem;
            color: var(--text-primary);
            background: #FFFFFF;
            transition: all 0.2s ease;
        }

        .form-field-input:focus {
            outline: none;
            border-color: #0B1D4A;
            box-shadow: 0 0 0 3px rgba(11, 29, 74, 0.08);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once __DIR__ . '/../../../../includes/sidebar.php'; ?>
        
        <main class="main-content ap-scope">
            <div class="container py-4">
                <!-- Clean Page Header -->
                <div class="ap-page-header">
                    <div class="ap-title-block">
                        <div class="text-muted small mb-1">
                            <a href="<?= BASE_URL ?>/public/portal/school-officer/dashboard.php" class="text-muted text-decoration-none">School Portal</a>
                            <span class="mx-1">/</span>
                            <span class="text-dark fw-bold">Documents</span>
                        </div>
                        <h1 class="ap-page-title">
                            <i class="fas fa-folder-open text-primary"></i> Chapter Affiliation Document Kit
                        </h1>
                        <p class="ap-page-subtitle">
                            Upload required accreditation documentation for institutional chartering and regional endorsement.
                        </p>
                    </div>
                    <div class="ap-header-actions">
                        <a href="<?= BASE_URL ?>/public/portal/school-officer/dashboard.php" class="ap-btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Dashboard
                        </a>
                    </div>
                </div>

                <!-- Form Card -->
                <div class="form-white-card">
                    <div class="info-callout-card">
                        <div class="d-flex align-items-center gap-2 fw-bold text-dark mb-1">
                            <i class="fas fa-info-circle text-primary"></i> Required Document Kit (IECEP National CBL Art. IV Sec. 3)
                        </div>
                        <div class="text-muted small">
                            Please upload all 6 required files in PDF or Word format (Excel for Directory). Submissions will be reviewed by the Regional Executive Committee.
                        </div>
                    </div>

                    <div id="alert-container"></div>

                    <form id="affiliationForm" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                        <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                            <i class="fas fa-university me-2 text-primary"></i>1. School & Representative Information
                        </h5>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6 form-field-group">
                                <label class="form-field-label">School / Institution Name <span class="text-danger">*</span></label>
                                <input type="text" name="school_name" class="form-field-input" placeholder="e.g. Laguna State Polytechnic University" required>
                            </div>

                            <div class="col-md-6 form-field-group">
                                <label class="form-field-label">Student Organization Name <span class="text-danger">*</span></label>
                                <input type="text" name="org_name" class="form-field-input" placeholder="e.g. IECEP LSPU-SCC Student Chapter" required>
                            </div>

                            <div class="col-md-6 form-field-group">
                                <label class="form-field-label">Representative Name <span class="text-danger">*</span></label>
                                <input type="text" name="rep_name" class="form-field-input" value="<?= htmlspecialchars($userName) ?>" required>
                            </div>

                            <div class="col-md-6 form-field-group">
                                <label class="form-field-label">Representative Email <span class="text-danger">*</span></label>
                                <input type="email" name="rep_email" class="form-field-input bg-light" value="<?= htmlspecialchars($userEmail) ?>" required readonly>
                            </div>
                        </div>

                        <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">
                            <i class="fas fa-file-upload me-2 text-primary"></i>2. Upload Required Accreditation Files
                        </h5>

                        <div class="doc-upload-grid">
                            <!-- 1. Letter of Intent -->
                            <div class="doc-upload-box" onclick="document.getElementById('letter_of_intent').click()">
                                <div class="doc-upload-icon"><i class="fas fa-file-signature"></i></div>
                                <div class="fw-bold text-dark small mb-1">1. Letter of Intent <span class="text-danger">*</span></div>
                                <div class="text-muted" style="font-size: 0.75rem;">PDF, DOCX (Max 10MB)</div>
                                <input type="file" id="letter_of_intent" name="letter_of_intent" accept=".pdf,.docx" required onchange="showFileFeedback(this)">
                                <div class="file-selected-name"></div>
                            </div>

                            <!-- 2. Endorsement Letter -->
                            <div class="doc-upload-box" onclick="document.getElementById('endorsement_letter').click()">
                                <div class="doc-upload-icon"><i class="fas fa-stamp"></i></div>
                                <div class="fw-bold text-dark small mb-1">2. Dean Endorsement Letter <span class="text-danger">*</span></div>
                                <div class="text-muted" style="font-size: 0.75rem;">PDF, DOCX (Max 10MB)</div>
                                <input type="file" id="endorsement_letter" name="endorsement_letter" accept=".pdf,.docx" required onchange="showFileFeedback(this)">
                                <div class="file-selected-name"></div>
                            </div>

                            <!-- 3. Constitution and Bylaws -->
                            <div class="doc-upload-box" onclick="document.getElementById('constitution_bylaws').click()">
                                <div class="doc-upload-icon"><i class="fas fa-book"></i></div>
                                <div class="fw-bold text-dark small mb-1">3. Constitution & Bylaws <span class="text-danger">*</span></div>
                                <div class="text-muted" style="font-size: 0.75rem;">PDF, DOCX (Max 10MB)</div>
                                <input type="file" id="constitution_bylaws" name="constitution_bylaws" accept=".pdf,.docx" required onchange="showFileFeedback(this)">
                                <div class="file-selected-name"></div>
                            </div>

                            <!-- 4. List of Officers with CV -->
                            <div class="doc-upload-box" onclick="document.getElementById('officers_cv').click()">
                                <div class="doc-upload-icon"><i class="fas fa-id-card-alt"></i></div>
                                <div class="fw-bold text-dark small mb-1">4. Officers Directory & CVs <span class="text-danger">*</span></div>
                                <div class="text-muted" style="font-size: 0.75rem;">PDF, DOCX (Max 10MB)</div>
                                <input type="file" id="officers_cv" name="officers_cv" accept=".pdf,.docx" required onchange="showFileFeedback(this)">
                                <div class="file-selected-name"></div>
                            </div>

                            <!-- 5. Organizational Chart -->
                            <div class="doc-upload-box" onclick="document.getElementById('org_chart').click()">
                                <div class="doc-upload-icon"><i class="fas fa-sitemap"></i></div>
                                <div class="fw-bold text-dark small mb-1">5. Organizational Chart <span class="text-danger">*</span></div>
                                <div class="text-muted" style="font-size: 0.75rem;">PDF, DOCX, PNG, JPG (Max 10MB)</div>
                                <input type="file" id="org_chart" name="org_chart" accept=".pdf,.docx,.png,.jpg,.jpeg" required onchange="showFileFeedback(this)">
                                <div class="file-selected-name"></div>
                            </div>

                            <!-- 6. Member Directory -->
                            <div class="doc-upload-box" onclick="document.getElementById('member_directory').click()">
                                <div class="doc-upload-icon"><i class="fas fa-file-excel text-success"></i></div>
                                <div class="fw-bold text-dark small mb-1">6. Member Directory Workbook <span class="text-danger">*</span></div>
                                <div class="text-muted" style="font-size: 0.75rem;">XLSX, XLS (4 Sheets: 1st, 2nd, 3rd, 4th Yr)</div>
                                <input type="file" id="member_directory" name="member_directory" accept=".xlsx,.xls" required onchange="showFileFeedback(this)">
                                <div class="file-selected-name"></div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end pt-3 border-top">
                            <button type="submit" class="ap-btn-primary" id="submitBtn">
                                <i class="fas fa-paper-plane me-1"></i> Submit Affiliation Kit
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
    function showFileFeedback(input) {
        const feedbackDiv = input.parentElement.querySelector('.file-selected-name');
        if (input.files.length > 0) {
            const file = input.files[0];
            const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
            feedbackDiv.style.display = 'block';
            feedbackDiv.innerHTML = `<i class="fas fa-check-circle text-success me-1"></i> ${file.name} (${sizeMB} MB)`;
        }
    }

    function showAlert(type, message) {
        const container = document.getElementById('alert-container');
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        container.innerHTML = `<div class="alert ${alertClass} mb-4"><i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>${message}</div>`;
        container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    document.getElementById('affiliationForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Submitting...';

        const formData = new FormData(this);

        try {
            const response = await fetch('<?= BASE_URL ?>/public/api/affiliate/submit-affiliation.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                showAlert('success', result.message || 'Application submitted successfully! The Regional Secretariat has been notified.');
                this.reset();
                document.querySelectorAll('.file-selected-name').forEach(el => el.style.display = 'none');
            } else {
                showAlert('error', result.message || 'Failed to submit application. Please verify all required files.');
            }
        } catch (error) {
            showAlert('error', 'Network communication error: ' + error.message);
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i> Submit Affiliation Kit';
        }
    });
    </script>
</body>
</html>
