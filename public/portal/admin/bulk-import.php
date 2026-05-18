<?php
/**
 * public/portal/admin/bulk-import.php
 * 
 * Bulk user import interface for Admin role
 * Allows CSV upload of new users with role assignment
 */

require_once __DIR__ . '/../auth_check.php';

// Enforce admin role
if (!require_role(['admin'], false)) {
    header('HTTP/1.0 403 Forbidden');
    echo "Access denied";
    exit;
}

// Get page title
$page_title = "Bulk User Import";
$current_page = 'bulk-import';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/../../includes/head-meta.php'; ?>
    <title><?= htmlspecialchars($page_title) ?> - IECEP-LSC</title>
    <style>
        .import-container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
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
        .template-download {
            margin: 1rem 0;
        }
        .template-download a {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: #0B1D4A;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
        }
        .template-download a:hover {
            background: #1E3A6E;
        }
        .preview-section {
            margin: 2rem 0;
        }
        .preview-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }
        .preview-table th, .preview-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        .preview-table th {
            background: #f3f4f6;
            font-weight: 600;
            color: #0B1D4A;
        }
        .error-row {
            background: #fee2e2;
        }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .badge-error {
            background: #fecaca;
            color: #991b1b;
        }
        .badge-ready {
            background: #d1fae5;
            color: #065f46;
        }
        .progress-bar {
            width: 100%;
            height: 24px;
            background: #e5e7eb;
            border-radius: 6px;
            overflow: hidden;
            margin: 1rem 0;
        }
        .progress-fill {
            height: 100%;
            background: #10b981;
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>
    
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="dashboard-content">
            <div class="import-container">
                <h1>
                    <i class="fas fa-file-import"></i>
                    <?= htmlspecialchars($page_title) ?>
                </h1>
                <p class="text-muted">Upload a CSV file to import multiple users at once</p>
                
                <!-- Security Alert -->
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle"></i>
                    <strong>Info:</strong> Only CSV files are accepted. Maximum 5MB. Each row must have valid email, name, and role.
                </div>
                
                <!-- Template Download -->
                <div class="template-download">
                    <a href="#" id="downloadTemplate">
                        <i class="fas fa-download"></i> Download CSV Template
                    </a>
                </div>
                
                <!-- Upload Zone -->
                <div class="upload-zone" id="uploadZone">
                    <div style="font-size: 2rem; margin-bottom: 1rem;">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <p style="margin: 0; font-weight: 500;">Drag CSV file here or click to select</p>
                    <input type="file" id="fileInput" class="file-input" accept=".csv" required>
                </div>
                
                <!-- File Info -->
                <div id="fileInfo" style="display: none; margin: 1rem 0; padding: 1rem; background: #f0f9ff; border-radius: 6px; border-left: 4px solid #0B1D4A;">
                    <strong>Selected file:</strong> <span id="fileName"></span>
                </div>
                
                <!-- Preview Section -->
                <div id="previewSection" class="preview-section" style="display: none;">
                    <h3>Preview</h3>
                    <div id="previewStats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin: 1rem 0;">
                        <div style="padding: 1rem; background: #f3f4f6; border-radius: 6px;">
                            <div style="font-size: 0.875rem; color: #6b7280;">Total Records</div>
                            <div style="font-size: 1.5rem; font-weight: bold;" id="totalRecords">0</div>
                        </div>
                        <div style="padding: 1rem; background: #f3f4f6; border-radius: 6px;">
                            <div style="font-size: 0.875rem; color: #6b7280;">Ready to Import</div>
                            <div style="font-size: 1.5rem; font-weight: bold; color: #10b981;" id="readyRecords">0</div>
                        </div>
                        <div style="padding: 1rem; background: #f3f4f6; border-radius: 6px;">
                            <div style="font-size: 0.875rem; color: #6b7280;">Errors</div>
                            <div style="font-size: 1.5rem; font-weight: bold; color: #ef4444;" id="errorRecords">0</div>
                        </div>
                    </div>
                    
                    <table class="preview-table">
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
                
                <!-- Progress Section -->
                <div id="progressSection" style="display: none;">
                    <h3>Import Progress</h3>
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill" style="width: 0%">0%</div>
                    </div>
                    <p id="progressText" style="font-size: 0.875rem; color: #6b7280;">Waiting to start...</p>
                </div>
                
                <!-- Results Section -->
                <div id="resultsSection" style="display: none; margin: 2rem 0; padding: 1.5rem; background: #f0fdf4; border-radius: 8px; border-left: 4px solid #10b981;">
                    <h3><i class="fas fa-check-circle"></i> Import Completed</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin: 1rem 0;">
                        <div>
                            <div style="font-size: 0.875rem; color: #6b7280;">Successfully Imported</div>
                            <div style="font-size: 1.5rem; font-weight: bold; color: #10b981;" id="successCount">0</div>
                        </div>
                        <div>
                            <div style="font-size: 0.875rem; color: #6b7280;">Failed</div>
                            <div style="font-size: 1.5rem; font-weight: bold; color: #ef4444;" id="failureCount">0</div>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="button" id="importBtn" class="btn btn-primary" disabled>
                        <i class="fas fa-upload"></i> Import Users
                    </button>
                    <button type="button" id="resetBtn" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Template download
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
        
        // File upload handling
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
            
            // Validate file size (5MB)
            if (file.size > 5242880) {
                alert('File size exceeds 5MB limit');
                resetForm();
                return;
            }
            
            // Read and parse CSV
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
                
                // Validate headers
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
                    
                    // Validate row
                    const validation = validateImportRow(row);
                    row.validation = validation;
                    
                    if (validation.valid) {
                        readyCount++;
                    } else {
                        errorCount++;
                    }
                    
                    data.push(row);
                }
                
                // Show preview
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
        
        // Import button
        document.getElementById('importBtn').addEventListener('click', importUsers);
        document.getElementById('resetBtn').addEventListener('click', resetForm);
        
        async function importUsers() {
            if (!fileInput.files[0]) return;
            
            document.getElementById('progressSection').style.display = 'block';
            document.getElementById('importBtn').disabled = true;
            
            const formData = new FormData();
            formData.append('file', fileInput.files[0]);
            
            try {
                const response = await fetch('<?= htmlspecialchars(API_URL) ?>/admin/bulk-import.php', {
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
</body>
</html>
