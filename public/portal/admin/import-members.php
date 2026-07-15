<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../bootstrap.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Members - IECEP-LSC Admin</title>
    <?php include __DIR__ . '/../../includes/head-meta.php'; ?>
    <style>
        .import-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .import-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 32px;
            margin-bottom: 24px;
        }

        .import-header {
            margin-bottom: 32px;
        }

        .import-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #0B1D4A;
            margin: 0 0 8px 0;
        }

        .import-header p {
            color: #64748b;
            margin: 0;
            font-size: 16px;
        }

        .upload-zone {
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8fafc;
        }

        .upload-zone:hover {
            border-color: #0B1D4A;
            background: #f1f5f9;
        }

        .upload-zone.dragover {
            border-color: #0B1D4A;
            background: #e0e7ff;
        }

        .upload-icon {
            font-size: 48px;
            color: #0B1D4A;
            margin-bottom: 16px;
        }

        .upload-text h3 {
            color: #0B1D4A;
            margin: 0 0 8px 0;
            font-size: 18px;
            font-weight: 600;
        }

        .upload-text p {
            color: #64748b;
            margin: 0;
            font-size: 14px;
        }

        .file-input {
            display: none;
        }

        .file-info {
            margin-top: 16px;
            padding: 12px;
            background: #f1f5f9;
            border-radius: 6px;
            display: none;
        }

        .file-info.show {
            display: block;
        }

        .file-info-text {
            color: #0B1D4A;
            font-weight: 500;
            margin: 0;
        }

        .template-section {
            margin-top: 32px;
            padding-top: 32px;
            border-top: 1px solid #e2e8f0;
        }

        .template-section h3 {
            color: #0B1D4A;
            font-size: 18px;
            font-weight: 600;
            margin: 0 0 16px 0;
        }

        .template-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-template {
            padding: 10px 20px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: white;
            color: #0B1D4A;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
        }

        .btn-template:hover {
            background: #f1f5f9;
            border-color: #0B1D4A;
        }

        .btn-import {
            width: 100%;
            padding: 12px;
            background: #0B1D4A;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            margin-top: 24px;
            transition: all 0.2s ease;
            display: none;
        }

        .btn-import.show {
            display: block;
        }

        .btn-import:hover:not(:disabled) {
            background: #0f2d5c;
        }

        .btn-import:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
        }

        .progress-section {
            margin-top: 24px;
            display: none;
        }

        .progress-section.show {
            display: block;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 12px;
        }

        .progress-fill {
            height: 100%;
            background: #10b981;
            width: 0%;
            transition: width 0.3s ease;
        }

        .progress-text {
            font-size: 14px;
            color: #64748b;
            margin: 0;
        }

        .results-section {
            margin-top: 24px;
            display: none;
        }

        .results-section.show {
            display: block;
        }

        .result-card {
            padding: 16px;
            border-radius: 6px;
            margin-bottom: 12px;
        }

        .result-success {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            color: #065f46;
        }

        .result-warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            color: #92400e;
        }

        .result-error {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }

        .result-card p {
            margin: 0;
            font-size: 14px;
        }

        .result-card strong {
            display: block;
            margin-bottom: 4px;
        }

        .instructions {
            background: #f0f9ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .instructions h4 {
            color: #0c4a6e;
            margin: 0 0 8px 0;
            font-size: 14px;
            font-weight: 600;
        }

        .instructions ul {
            margin: 0;
            padding-left: 20px;
            color: #0c4a6e;
            font-size: 14px;
        }

        .instructions li {
            margin-bottom: 4px;
        }

        @media (max-width: 640px) {
            .import-container {
                padding: 20px 16px;
            }

            .import-card {
                padding: 20px;
            }

            .import-header h1 {
                font-size: 24px;
            }

            .template-buttons {
                flex-direction: column;
            }

            .btn-template {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="import-container">
        <div class="import-card">
            <div class="import-header">
                <h1>Import Members</h1>
                <p>Bulk import member accounts from CSV or Excel files</p>
            </div>

            <div class="instructions">
                <h4>ðŸ“‹ File Format Requirements:</h4>
                <ul>
                    <li>Column A: Full Name (required)</li>
                    <li>Column B: Email Address (required)</li>
                    <li>First row should be headers</li>
                    <li>Supported formats: CSV, XLSX, XLS</li>
                </ul>
            </div>

            <form id="importForm" enctype="multipart/form-data">
                <div class="upload-zone" id="uploadZone">
                    <div class="upload-icon">ðŸ“</div>
                    <div class="upload-text">
                        <h3>Click to upload or drag and drop</h3>
                        <p>CSV or Excel files (max 10MB)</p>
                    </div>
                    <input type="file" id="fileInput" class="file-input" accept=".csv,.xlsx,.xls" name="file">
                </div>

                <div class="file-info" id="fileInfo">
                    <p class="file-info-text" id="fileName"></p>
                </div>

                <button type="submit" class="btn-import" id="importBtn">Import Members</button>
            </form>

            <div class="progress-section" id="progressSection">
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
                <p class="progress-text" id="progressText">Processing...</p>
            </div>

            <div class="results-section" id="resultsSection">
                <div id="resultsContainer"></div>
            </div>

            <div class="template-section">
                <h3>ðŸ“¥ Download Template</h3>
                <div class="template-buttons">
                    <button type="button" class="btn-template" onclick="downloadTemplate('csv')">
                        ðŸ“„ CSV Template
                    </button>
                    <button type="button" class="btn-template" onclick="downloadTemplate('xlsx')">
                        ðŸ“Š Excel Template
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('fileInput');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const importForm = document.getElementById('importForm');
        const importBtn = document.getElementById('importBtn');
        const progressSection = document.getElementById('progressSection');
        const progressFill = document.getElementById('progressFill');
        const progressText = document.getElementById('progressText');
        const resultsSection = document.getElementById('resultsSection');
        const resultsContainer = document.getElementById('resultsContainer');

        // File upload handlers
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
            fileInput.files = e.dataTransfer.files;
            handleFileSelect();
        });

        fileInput.addEventListener('change', handleFileSelect);

        function handleFileSelect() {
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                fileName.textContent = `Selected: ${file.name} (${formatFileSize(file.size)})`;
                fileInfo.classList.add('show');
                importBtn.classList.add('show');
            }
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        // Form submission
        importForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (fileInput.files.length === 0) {
                alert('Please select a file');
                return;
            }

            const formData = new FormData();
            formData.append('file', fileInput.files[0]);

            importBtn.disabled = true;
            progressSection.classList.add('show');
            resultsSection.classList.remove('show');
            resultsContainer.innerHTML = '';

            try {
                const response = await fetch('<?php echo BASE_URL; ?>/public/api/import-members.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (response.ok) {
                    displayResults(data);
                } else {
                    showError(data.error || 'Import failed');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            } finally {
                importBtn.disabled = false;
                progressSection.classList.remove('show');
            }
        });

        function displayResults(data) {
            resultsContainer.innerHTML = '';

            // Success message
            const successCard = document.createElement('div');
            successCard.className = 'result-card result-success';
            successCard.innerHTML = `
                <strong>âœ“ Import Completed</strong>
                <p>${data.message}</p>
            `;
            resultsContainer.appendChild(successCard);

            // Stats
            if (data.stats) {
                const statsCard = document.createElement('div');
                statsCard.className = 'result-card result-success';
                statsCard.innerHTML = `
                    <strong>ðŸ“Š Statistics</strong>
                    <p>Total rows: ${data.stats.total_rows}</p>
                    <p>Successfully imported: ${data.stats.successful}</p>
                    <p>Warnings: ${data.stats.warnings}</p>
                `;
                resultsContainer.appendChild(statsCard);
            }

            // Warnings
            if (data.warnings && data.warnings.length > 0) {
                data.warnings.forEach(warning => {
                    const warningCard = document.createElement('div');
                    warningCard.className = 'result-card result-warning';
                    warningCard.innerHTML = `<p>${warning}</p>`;
                    resultsContainer.appendChild(warningCard);
                });
            }

            resultsSection.classList.add('show');
        }

        function showError(message) {
            const errorCard = document.createElement('div');
            errorCard.className = 'result-card result-error';
            errorCard.innerHTML = `<strong>âœ— Error</strong><p>${message}</p>`;
            resultsContainer.appendChild(errorCard);
            resultsSection.classList.add('show');
        }

        function downloadTemplate(format) {
            if (format === 'csv') {
                downloadCSVTemplate();
            } else {
                downloadExcelTemplate();
            }
        }

        function downloadCSVTemplate() {
            const csv = 'Full Name,Email Address\nJohn Doe,john@example.com\nJane Smith,jane@example.com';
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'member-template.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }

        function downloadExcelTemplate() {
            // For Excel, we'll create a simple HTML table and let the browser handle it
            const html = `
                <table>
                    <tr>
                        <th>Full Name</th>
                        <th>Email Address</th>
                    </tr>
                    <tr>
                        <td>John Doe</td>
                        <td>john@example.com</td>
                    </tr>
                    <tr>
                        <td>Jane Smith</td>
                        <td>jane@example.com</td>
                    </tr>
                </table>
            `;
            const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'member-template.xls';
            a.click();
            window.URL.revokeObjectURL(url);
        }
    </script>

    <?php include __DIR__ . '/../../includes/footer-new.php'; ?>
</body>
</html>

