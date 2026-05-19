<?php
require_once __DIR__ . '/../bootstrap.php';
/**
 * School Officer Affiliation Form
 * Upload all 6 required documents including member directory
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/csrf.php';

// Check if user is logged in as school officer
if (!isset($_SESSION['logged_in']) || $_SESSION['user']['role'] !== 'school_officer') {
    header('Location: /IECEP-LSC-MEMSYS/public/login.php');
    exit;
}

$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Affiliation Application - IECEP-LSC</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/dashboard.css">
    <style>
        :root {
            --navy: #0A2F6C;
            --gold: #F5A623;
            --success: #10b981;
            --error: #ef4444;
        }
        .form-container { max-width: 900px; margin: 40px auto; padding: 0 24px; }
        .form-card { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .form-header { text-align: center; margin-bottom: 40px; }
        .form-header h2 { font-size: 2rem; font-weight: 700; color: var(--navy); margin-bottom: 8px; }
        .form-header p { color: #64748b; }
        .form-group { margin-bottom: 24px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #1e293b; }
        .form-group label .required { color: var(--error); }
        .form-control { width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem; transition: all 0.2s; }
        .form-control:focus { outline: none; border-color: var(--navy); box-shadow: 0 0 0 3px rgba(10, 47, 108, 0.1); }
        .file-upload-area { border: 2px dashed #cbd5e1; border-radius: 8px; padding: 20px; text-align: center; transition: all 0.2s; cursor: pointer; }
        .file-upload-area:hover { border-color: var(--navy); background: #f8fafc; }
        .file-upload-area input[type="file"] { display: none; }
        .file-upload-label { cursor: pointer; color: #64748b; }
        .file-upload-label i { font-size: 2rem; color: var(--navy); margin-bottom: 8px; display: block; }
        .file-name { margin-top: 8px; font-size: 0.875rem; color: var(--success); font-weight: 600; }
        .documents-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 32px; }
        .btn { padding: 14px 32px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; font-size: 1rem; }
        .btn-primary { background: var(--navy); color: white; width: 100%; }
        .btn-primary:hover { background: #1e4a8a; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(10, 47, 108, 0.3); }
        .btn-primary:disabled { background: #94a3b8; cursor: not-allowed; transform: none; }
        .alert { padding: 16px; border-radius: 8px; margin-bottom: 24px; }
        .alert-info { background: #dbeafe; border: 1px solid #3b82f6; color: #1e40af; }
        .alert-success { background: #d1fae5; border: 1px solid #10b981; color: #065f46; }
        .alert-error { background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; }
        .section-title { font-size: 1.25rem; font-weight: 700; color: var(--navy); margin: 32px 0 16px; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <header class="dashboard-header">
            <div class="header-content">
                <div>
                    <h1>Affiliation Application</h1>
                    <p class="welcome-message">Submit your school's affiliation application</p>
                </div>
            </div>
        </header>

        <div class="form-container">
            <div class="form-card">
                <div class="form-header">
                    <h2>IECEP-LSC Affiliation Application</h2>
                    <p>Please complete all fields and upload all required documents</p>
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
    </main>

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
