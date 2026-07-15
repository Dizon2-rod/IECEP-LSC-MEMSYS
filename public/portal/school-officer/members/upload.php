<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../portal/auth_check.php';
require_once __DIR__ . '/../../includes/config.php';

require_role(['school_officer']);

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Member Directory - IECEP-LSC MEMSYS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@6.0.0/css/all.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --danger-color: #e74c3c;
            --light-bg: #ecf0f1;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-bg);
        }
        .container-main {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .card-header {
            background-color: var(--primary-color);
            color: white;
        }
        .upload-area {
            border: 3px dashed var(--secondary-color);
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
        }
        .upload-area:hover {
            background-color: #e3f2fd;
            border-color: var(--primary-color);
        }
        .upload-area.dragover {
            background-color: #fff3cd;
            border-color: var(--danger-color);
        }
        .upload-icon {
            font-size: 3rem;
            color: var(--secondary-color);
            margin-bottom: 1rem;
        }
        .file-input {
            display: none;
        }
        .alert-info {
            background-color: #d1ecf1;
            border-color: #bee5eb;
            color: #0c5460;
        }
        .progress-container {
            display: none;
        }
        .btn-primary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
    </style>
</head>
<body>
    <div class="container-main">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-upload"></i> Upload Member Directory</h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <strong>Instructions:</strong> Upload an Excel file (.xlsx or .xls) containing member data in 4 sheets:
                            <ul class="mb-0 mt-2">
                                <li>1st Yr</li>
                                <li>2nd Yr</li>
                                <li>3rd Yr</li>
                                <li>4th Yr</li>
                            </ul>
                        </div>

                        <form id="uploadForm" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                            <div class="upload-area" id="uploadArea">
                                <div class="upload-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <h5>Drag and drop your file here</h5>
                                <p class="text-muted">or click to select a file</p>
                                <small class="text-muted">Maximum file size: 10 MB</small>
                                <input type="file" id="fileInput" class="file-input" name="directory_file" accept=".xlsx,.xls,.csv">
                            </div>

                            <div class="mt-3" id="fileSelected" style="display: none;">
                                <p><strong>Selected file:</strong> <span id="fileName"></span></p>
                                <button type="submit" class="btn btn-primary btn-lg w-100" id="submitBtn">
                                    <i class="fas fa-check"></i> Upload Directory
                                </button>
                            </div>

                            <div class="progress-container mt-3" id="progressContainer">
                                <div class="progress">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                         id="progressBar" role="progressbar" style="width: 0%"></div>
                                </div>
                                <p class="text-muted mt-2" id="progressText">Processing file...</p>
                            </div>
                        </form>

                        <div id="responseMessage" class="mt-3" style="display: none;"></div>
                    </div>
                </div>

                <div id="successCard" class="card border-success mt-4" style="display: none;">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-check-circle"></i> Upload Successful</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Batch ID:</strong> <code id="batchId"></code></p>
                        <p><strong>Total Rows Uploaded:</strong> <span id="totalRows"></span></p>
                        <p class="mb-0">
                            <a href="../../admin/list-batches.php" class="btn btn-primary">
                                <i class="fas fa-list"></i> Go to Validation Dashboard
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const fileSelected = document.getElementById('fileSelected');
        const fileName = document.getElementById('fileName');
        const uploadForm = document.getElementById('uploadForm');
        const progressContainer = document.getElementById('progressContainer');
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');
        const responseMessage = document.getElementById('responseMessage');
        const successCard = document.getElementById('successCard');

        // Click to open file dialog
        uploadArea.addEventListener('click', () => fileInput.click());

        // Drag and drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            fileInput.files = e.dataTransfer.files;
            handleFileSelect();
        });

        // File input change
        fileInput.addEventListener('change', handleFileSelect);

        function handleFileSelect() {
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                fileName.textContent = file.name;
                fileSelected.style.display = 'block';
            }
        }

        // Form submission
        uploadForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (fileInput.files.length === 0) {
                showError('Please select a file');
                return;
            }

            const formData = new FormData(uploadForm);
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;

            try {
                progressContainer.style.display = 'block';
                progressBar.style.width = '30%';

                const response = await fetch('../../api/affiliate/upload-directory.php', {
                    method: 'POST',
                    body: formData
                });

                progressBar.style.width = '90%';

                const data = await response.json();

                if (data.success) {
                    progressBar.style.width = '100%';
                    document.getElementById('batchId').textContent = data.batch_id;
                    document.getElementById('totalRows').textContent = data.total_rows;
                    successCard.style.display = 'block';
                    uploadForm.style.display = 'none';
                    progressContainer.style.display = 'none';

                    if (data.import_errors && data.import_errors.length > 0) {
                        const warnings = document.createElement('div');
                        warnings.className = 'alert alert-warning mt-3';
                        warnings.innerHTML = '<strong>Import Warnings:</strong><ul>' +
                            data.import_errors.map(e => '<li>' + e + '</li>').join('') +
                            '</ul>';
                        successCard.insertAdjacentElement('afterend', warnings);
                    }
                } else {
                    showError(data.message || 'Upload failed');
                    submitBtn.disabled = false;
                }
            } catch (error) {
                showError('Network error: ' + error.message);
                submitBtn.disabled = false;
            }
        });

        function showError(message) {
            responseMessage.style.display = 'block';
            responseMessage.innerHTML = '<div class="alert alert-danger">' +
                '<i class="fas fa-exclamation-circle"></i> ' + message + '</div>';
            progressContainer.style.display = 'none';
        }
    </script>
</body>
</html>
