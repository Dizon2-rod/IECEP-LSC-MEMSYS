<?php
require_once __DIR__ . '/../../bootstrap.php';
/**
 * public/portal/admin/members/batch-process.php
 * 
 * Bulk user import interface for Admin role
 * Allows CSV upload of new users with role assignment
 */

require_once __DIR__ . '/../../auth_check.php';

// Enforce admin role
if (!require_role(['admin'], false)) {
    header('HTTP/1.0 403 Forbidden');
    echo "Access denied";
    exit;
}

// Get page title
$page_title = "Bulk User Import";
$current_page = 'batch-process';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - IECEP-LSC</title>
    <?php include __DIR__ . '/../../../../includes/head-meta.php'; ?>
    <style>
        .upload-zone {
            border: 2px dashed #0B1D4A;
            border-radius: 8px;
            padding: 3rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 1.5rem 0;
        }
        .upload-zone:hover {
            background: #f0f4f8;
            border-color: #D4AF37;
        }
        .upload-zone.dragover {
            background: #f0f4f8;
            border-color: #D4AF37;
        }
        .file-input {
            display: none;
        }
        .upload-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #0B1D4A;
        }
        .upload-text {
            margin: 0;
            font-weight: 500;
        }
        .error-row {
            background: #fee2e2;
        }
        .badge-ready {
            background: #d1fae5;
            color: #065f46;
        }
        .badge-error {
            background: #fecaca;
            color: #991b1b;
        }
        .chart-container {
            position: relative;
            height: 350px;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-file-import"></i> <?= htmlspecialchars($page_title) ?></h1>
            <p class="text-muted">Upload a CSV file to import multiple users at once</p>
        </div>

        <div class="content-card">
            <div class="alert alert-info" role="alert">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Info:</strong> Only CSV files are accepted. Maximum 5MB. Each row must have valid email, name, and role.
                </div>
            </div>

            <div class="mb-4">
                <a href="#" id="downloadTemplate" class="btn btn-primary">
                    <i class="fas fa-download"></i> Download CSV Template
                </a>
            </div>

            <div class="upload-zone" id="uploadZone">
                <div class="upload-icon">
                    <i class="fas fa-cloud-upload-alt"></i>
                </div>
                <p class="upload-text">Drag CSV file here or click to select</p>
                <input type="file" id="fileInput" class="file-input" accept=".csv" required>
            </div>

            <div id="fileInfo" class="alert alert-info" style="display: none; margin-top: 1rem;">
                <strong>Selected file:</strong> <span id="fileName"></span>
            </div>

            <div id="previewSection" style="display: none; margin-top: 2rem;">
                <h3>Preview</h3>
                <div id="previewStats" class="stats-grid" style="margin: 1rem 0;">
                    <div class="stat-card">
                        <div class="stat-label">Total Records</div>
                        <div class="stat-value" id="totalRecords">0</div>
                    </div>
                    <div class="stat-card success">
                        <div class="stat-label">Ready to Import</div>
                        <div class="stat-value" id="readyRecords">0</div>
                    </div>
                    <div class="stat-card danger">
                        <div class="stat-label">Errors</div>
                        <div class="stat-value" id="errorRecords">0</div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Email</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody id="previewBody">
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="progressSection" style="display: none; margin-top: 2rem;">
                <h3>Import Progress</h3>
                <div class="progress" style="height: 24px; margin: 1rem 0;">
                    <div class="progress-bar" id="progressFill" role="progressbar" style="width: 0%">0%</div>
                </div>
                <p id="progressText" class="text-muted">Waiting to start...</p>
            </div>

            <div id="resultsSection" style="display: none; margin-top: 2rem; padding: 1.5rem; background: #f0fdf4; border-radius: 8px; border-left: 4px solid #10b981;">
                <h3><i class="fas fa-check-circle"></i> Import Completed</h3>
                <div class="stats-grid" style="margin: 1rem 0;">
                    <div class="stat-card success">
                        <div class="stat-label">Successfully Imported</div>
                        <div class="stat-value" id="successCount">0</div>
                    </div>
                    <div class="stat-card danger">
                        <div class="stat-label">Failed</div>
                        <div class="stat-value" id="failureCount">0</div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2" style="margin-top: 2rem;">
                <button type="button" id="importBtn" class="btn btn-primary" disabled>
                    <i class="fas fa-upload"></i> Import Users
                </button>
                <button type="button" id="resetBtn" class="btn btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </button>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('downloadTemplate').addEventListener('click', function(e) {
            e.preventDefault();
            const csv = 'email,full_name,role\njohn@example.com,John Doe,member\njane@example.com,Jane Smith,school_officer';
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'users_template.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        });

        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('fileInput');

        uploadZone.addEventListener('click', () => fileInput.click());

        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });

        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });

        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect();
            }
        });

        fileInput.addEventListener('change', handleFileSelect);

        async function handleFileSelect() {
            const file = fileInput.files[0];
            if (!file) return;

            document.getElementById('fileName').textContent = file.name;
            document.getElementById('fileInfo').style.display = 'block';

            if (file.size > 5242880) {
                alert('File size exceeds 5MB limit');
                resetForm();
                return;
            }

            const reader = new FileReader();
            reader.onload = async (e) => {
                const content = e.target.result;
                await previewCSV(content);
            };
            reader.readAsText(file);
        }

        async function previewCSV(content) {
            try {
                const lines = content.trim().split('\n');
                const headers = lines[0].split(',').map(h => h.trim());

                const required = ['email', 'full_name', 'role'];
                for (let req of required) {
                    if (!headers.includes(req)) {
                        alert(`Missing required column: ${req}`);
                        resetForm();
                        return;
                    }
                }

                const data = [];
                let readyCount = 0;
                let errorCount = 0;

                for (let i = 1; i < lines.length; i++) {
                    const values = lines[i].split(',').map(v => v.trim());
                    const row = {};

                    headers.forEach((header, idx) => {
                        row[header] = values[idx] || '';
                    });

                    const validation = validateImportRow(row);
                    row.validation = validation;

                    if (validation.valid) {
                        readyCount++;
                    } else {
                        errorCount++;
                    }

                    data.push(row);
                }

                displayPreview(data, readyCount, errorCount);
                document.getElementById('importBtn').disabled = errorCount === data.length;

            } catch (error) {
                alert('Error parsing CSV: ' + error.message);
                resetForm();
            }
        }

        function validateImportRow(row) {
            const errors = [];

            if (!row.email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(row.email)) {
                errors.push('Invalid email');
            }

            if (!row.full_name || row.full_name.length < 2) {
                errors.push('Name too short');
            }

            if (!row.role) {
                errors.push('Role required');
            }

            return {
                valid: errors.length === 0,
                errors: errors.join(', ')
            };
        }

        function displayPreview(data, readyCount, errorCount) {
            document.getElementById('totalRecords').textContent = data.length;
            document.getElementById('readyRecords').textContent = readyCount;
            document.getElementById('errorRecords').textContent = errorCount;

            const tbody = document.getElementById('previewBody');
            tbody.innerHTML = data.map((row, idx) => {
                const valid = row.validation.valid;
                return `<tr class="${valid ? '' : 'error-row'}">
                    <td>${escapeHtml(row.email)}</td>
                    <td>${escapeHtml(row.full_name)}</td>
                    <td>${escapeHtml(row.role)}</td>
                    <td><span class="status-badge ${valid ? 'badge-ready' : 'badge-error'}">${valid ? 'Ready' : 'Error'}</span></td>
                    <td>${valid ? '' : escapeHtml(row.validation.errors)}</td>
                </tr>`;
            }).join('');

            document.getElementById('previewSection').style.display = 'block';
            document.getElementById('importBtn').disabled = errorCount === data.length;
        }

        document.getElementById('importBtn').addEventListener('click', importUsers);
        document.getElementById('resetBtn').addEventListener('click', resetForm);

        async function importUsers() {
            if (!fileInput.files[0]) return;

            document.getElementById('progressSection').style.display = 'block';
            document.getElementById('importBtn').disabled = true;

            const formData = new FormData();
            formData.append('file', fileInput.files[0]);

            try {
                const response = await fetch('/api/admin/bulk-import.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    }
                });

                const result = await response.json();

                if (result.success) {
                    document.getElementById('successCount').textContent = result.data?.imported || 0;
                    document.getElementById('failureCount').textContent = result.data?.failed || 0;
                    document.getElementById('resultsSection').style.display = 'block';
                    setTimeout(() => {
                        location.reload();
                    }, 3000);
                } else {
                    alert('Import failed: ' + (result.message || 'Unknown error'));
                }
            } catch (error) {
                alert('Import error: ' + error.message);
            } finally {
                document.getElementById('progressSection').style.display = 'none';
                document.getElementById('importBtn').disabled = false;
            }
        }

        function resetForm() {
            fileInput.value = '';
            document.getElementById('fileInfo').style.display = 'none';
            document.getElementById('previewSection').style.display = 'none';
            document.getElementById('progressSection').style.display = 'none';
            document.getElementById('resultsSection').style.display = 'none';
            document.getElementById('importBtn').disabled = true;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
