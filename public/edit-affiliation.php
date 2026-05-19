<?php
require_once __DIR__ . '/bootstrap.php';
/**
 * Edit Affiliation Application
 * Allows applicants to resubmit documents after committee requests changes
 * Accessed via secure token sent in email
 */

session_start();
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/../includes/supabase.php';
require_once __DIR__ . '/../includes/paths.php';
require_once __DIR__ . '/../src/lib/SupabaseClient.php';
require_once __DIR__ . '/../src/lib/EmailService.php';

// Get configuration
$config = require __DIR__ . '/../includes/supabase.php';
$supabase = new App\Lib\SupabaseClient($config['url'], $config['anon_key']);
$emailService = new App\Lib\EmailService();

$errorMessage = '';
$successMessage = '';
$application = null;
$isValidToken = false;

// Validate token
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $errorMessage = 'Invalid access link. Please use the link sent to your email.';
} else {
    try {
        // Fetch application by edit token
        $result = $supabase->select('pending_affiliations', ['edit_token' => 'eq.' . $token]);
        
        if (empty($result) || !is_array($result)) {
            $errorMessage = 'Invalid or expired access link. Please request a new link from the Registration Committee.';
        } else {
            $application = $result[0];
            
            // Check if status is changes_requested
            $status = $application['status'] ?? '';
            if ($status !== 'changes_requested') {
                if ($status === 'resubmitted') {
                    $successMessage = 'Your application has already been resubmitted and is pending review.';
                } elseif ($status === 'approved') {
                    $successMessage = 'Your application has been approved. Please check your email for login credentials.';
                } elseif ($status === 'rejected') {
                    $errorMessage = 'This application has been rejected. Please submit a new application if you wish to reapply.';
                } elseif ($status === 'pending') {
                    $errorMessage = 'This application is still pending initial review and cannot be edited at this time.';
                }
            } else {
                $isValidToken = true;
            }
        }
    } catch (Exception $e) {
        $errorMessage = 'Unable to verify access. Please try again later.';
        error_log('Error validating edit token: ' . $e->getMessage());
    }
}

// Parse existing documents
$existingDocuments = [];
if ($application && !empty($application['documents'])) {
    $existingDocuments = json_decode($application['documents'], true) ?: [];
}

// Document field definitions
$documentFields = [
    'moa' => [
        'label' => 'Memorandum of Agreement',
        'description' => 'Signed MOA between institution and IECEP-LSC',
        'required' => false,
        'accept' => '.pdf,.doc,.docx'
    ],
    'accreditation' => [
        'label' => 'Accreditation Certificate',
        'description' => 'Current accreditation certificate from CHED or DepEd',
        'required' => false,
        'accept' => '.pdf,.jpg,.jpeg,.png'
    ],
    'cor' => [
        'label' => 'Certificate of Registration',
        'description' => 'SEC or DTI registration certificate',
        'required' => false,
        'accept' => '.pdf,.jpg,.jpeg,.png'
    ],
    'school_registration' => [
        'label' => 'School Registration Document',
        'description' => 'Official school registration document',
        'required' => false,
        'accept' => '.pdf,.jpg,.jpeg,.png'
    ],
    'letter_of_intent' => [
        'label' => 'Letter of Intent',
        'description' => 'Official letter expressing intent to affiliate',
        'required' => false,
        'accept' => '.pdf,.doc,.docx'
    ],
    'officers_list' => [
        'label' => 'List of Officers',
        'description' => 'Complete list of chapter officers with signatures',
        'required' => false,
        'accept' => '.pdf,.doc,.docx,.xls,.xlsx'
    ],
    'faculty_adviser' => [
        'label' => 'Faculty Adviser Profile',
        'description' => 'CV and credentials of faculty adviser',
        'required' => false,
        'accept' => '.pdf,.doc,.docx'
    ],
    'id_picture' => [
        'label' => 'Contact Person ID Picture',
        'description' => '2x2 or passport size ID photo',
        'required' => false,
        'accept' => '.jpg,.jpeg,.png'
    ]
];

// Handle AJAX API requests for resubmission
// Note: The actual resubmission is now handled by submit-affiliation.php API endpoint
// This file only handles the edit form display and token validation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_check'])) {
    // Simple health check endpoint for AJAX validation
    header('Content-Type: application/json');
    echo json_encode(['valid' => $isValidToken, 'status' => $application['status'] ?? 'unknown']);
    exit;
}

// Helper function to check if document exists
function hasExistingDocument($documents, $field) {
    return isset($documents[$field]) && is_array($documents[$field]) && !empty($documents[$field]['content']);
}

function getDocumentInfo($documents, $field) {
    if (hasExistingDocument($documents, $field)) {
        return $documents[$field];
    }
    return null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Affiliation Application - IECEP-LSC</title>
    <?php include __DIR__ . '/../includes/head-meta.php'; ?>
    <style>
        :root {
            --primary: #0B1D4A;
            --primary-light: #1a3a7a;
            --accent: #D4AF37;
            --accent-hover: #B8962E;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --white: #ffffff;
            --neutral-100: #f8fafc;
            --neutral-200: #e2e8f0;
            --neutral-300: #cbd5e1;
            --neutral-500: #64748b;
            --neutral-700: #334155;
            --neutral-900: #0f172a;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            padding: 40px 20px;
            color: var(--white);
        }
        
        .header img {
            height: 80px;
            margin-bottom: 20px;
        }
        
        .header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1rem;
        }
        
        .card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .card-header {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            padding: 24px 32px;
            border-bottom: 1px solid #f59e0b;
        }
        
        .card-header.error {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            border-bottom-color: #ef4444;
        }
        
        .card-header.success {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            border-bottom-color: #22c55e;
        }
        
        .card-header h2 {
            font-size: 1.25rem;
            color: #92400e;
            margin: 0 0 8px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-header.error h2 {
            color: #991b1b;
        }
        
        .card-header.success h2 {
            color: #166534;
        }
        
        .card-header p {
            color: #78350f;
            font-size: 0.95rem;
            line-height: 1.5;
            margin: 0;
        }
        
        .card-header.error p {
            color: #7f1d1d;
        }
        
        .card-header.success p {
            color: #166534;
        }
        
        .card-body {
            padding: 32px;
        }
        
        .institution-info {
            background: var(--neutral-100);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 28px;
        }
        
        .institution-info h3 {
            font-size: 1rem;
            color: var(--primary);
            margin: 0 0 12px 0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 0.75rem;
            color: var(--neutral-500);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        
        .info-value {
            font-size: 0.9rem;
            color: var(--neutral-900);
            font-weight: 500;
        }
        
        .committee-notes {
            background: #fef3c7;
            border-left: 4px solid var(--warning);
            border-radius: 0 12px 12px 0;
            padding: 20px;
            margin-bottom: 28px;
        }
        
        .committee-notes h4 {
            font-size: 0.9rem;
            color: #92400e;
            margin: 0 0 12px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .committee-notes-content {
            color: #78350f;
            font-size: 0.95rem;
            line-height: 1.6;
            white-space: pre-wrap;
        }
        
        .form-section {
            margin-bottom: 28px;
        }
        
        .section-title {
            font-size: 1.1rem;
            color: var(--primary);
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .document-upload {
            border: 2px dashed var(--neutral-300);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 16px;
            transition: all 0.3s ease;
            background: var(--neutral-100);
        }
        
        .document-upload:hover {
            border-color: var(--accent);
            background: rgba(212, 175, 55, 0.05);
        }
        
        .document-upload.has-file {
            border-color: var(--success);
            background: rgba(34, 197, 94, 0.05);
        }
        
        .document-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        
        .document-label {
            font-weight: 600;
            color: var(--neutral-900);
            font-size: 0.95rem;
        }
        
        .existing-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            background: var(--success);
            color: white;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .document-description {
            color: var(--neutral-500);
            font-size: 0.85rem;
            margin-bottom: 12px;
        }
        
        .file-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .file-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--primary);
            color: white;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
        }
        
        .file-button:hover {
            background: var(--primary-light);
        }
        
        .file-name {
            color: var(--neutral-500);
            font-size: 0.85rem;
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .file-name.has-file {
            color: var(--success);
            font-weight: 500;
        }
        
        .file-help {
            margin-top: 8px;
            font-size: 0.75rem;
            color: var(--neutral-500);
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .alert-error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
        
        .alert-success {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #166534;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px 32px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--accent);
            color: var(--primary);
        }
        
        .btn-primary:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);
        }
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: var(--neutral-200);
            color: var(--neutral-700);
        }
        
        .btn-secondary:hover {
            background: var(--neutral-300);
        }
        
        .form-actions {
            display: flex;
            gap: 16px;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid var(--neutral-200);
        }
        
        .loading-spinner {
            display: none;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(11, 29, 74, 0.3);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        .btn.loading .loading-spinner {
            display: inline-block;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .instructions {
            background: #dbeafe;
            border-left: 4px solid var(--info);
            border-radius: 0 12px 12px 0;
            padding: 20px;
            margin-bottom: 28px;
        }
        
        .instructions h4 {
            font-size: 0.9rem;
            color: #1e40af;
            margin: 0 0 10px 0;
        }
        
        .instructions ul {
            margin: 0;
            padding-left: 20px;
            color: #1e40af;
            font-size: 0.9rem;
            line-height: 1.7;
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.85rem;
        }
        
        .footer a {
            color: var(--accent);
            text-decoration: none;
        }
        
        @media (max-width: 640px) {
            body {
                padding: 10px;
            }
            
            .card-body {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 1.25rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <img src="<?php echo BASE_PUBLIC_URL; ?>/assets/icons/iecep-logo.png" alt="IECEP-LSC Logo">
            <h1>Edit Affiliation Application</h1>
            <p>Institute of Electronics Engineers of the Philippines – Laguna Student Chapter</p>
        </header>
        
        <?php if (!empty($errorMessage)): ?>
            <div class="card">
                <div class="card-header error">
                    <h2><i class="fas fa-exclamation-circle"></i> Access Error</h2>
                    <p><?php echo htmlspecialchars($errorMessage); ?></p>
                </div>
                <div class="card-body" style="text-align: center; padding: 40px;">
                    <p style="color: var(--neutral-500); margin-bottom: 24px;">
                        If you believe this is an error, please contact the Registration Committee.
                    </p>
                    <a href="mailto:ieceplsc24@gmail.com" class="btn btn-primary">
                        <i class="fas fa-envelope"></i> Contact Support
                    </a>
                </div>
            </div>
        <?php elseif (!empty($successMessage)): ?>
            <div class="card">
                <div class="card-header success">
                    <h2><i class="fas fa-check-circle"></i> Status Update</h2>
                    <p><?php echo htmlspecialchars($successMessage); ?></p>
                </div>
                <div class="card-body" style="text-align: center; padding: 40px;">
                    <a href="<?php echo BASE_URL; ?>/" class="btn btn-primary">
                        <i class="fas fa-home"></i> Return to Homepage
                    </a>
                </div>
            </div>
        <?php elseif ($isValidToken && $application): ?>
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-edit"></i> Resubmit Application</h2>
                    <p>The Registration Committee has reviewed your application and requested the following changes. Please upload the corrected documents below.</p>
                </div>
                
                <div class="card-body">
                    <?php if (!empty($_POST) && !empty($errorMessage)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <span><?php echo htmlspecialchars($errorMessage); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="institution-info">
                        <h3>Application Details</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Institution</span>
                                <span class="info-value"><?php echo htmlspecialchars($application['institution_name'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Contact Person</span>
                                <span class="info-value"><?php echo htmlspecialchars($application['contact_person'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Email</span>
                                <span class="info-value"><?php echo htmlspecialchars($application['email'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Original Submission</span>
                                <span class="info-value"><?php echo date('F j, Y', strtotime($application['submitted_at'] ?? 'now')); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($application['committee_notes'])): ?>
                        <div class="committee-notes">
                            <h4><i class="fas fa-exclamation-triangle"></i> Changes Requested by Committee</h4>
                            <div class="committee-notes-content">
                                <?php echo nl2br(htmlspecialchars($application['committee_notes'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="instructions">
                        <h4><i class="fas fa-info-circle"></i> How to Resubmit</h4>
                        <ul>
                            <li>Review the requested changes above carefully</li>
                            <li>Upload only the documents that need correction</li>
                            <li>Maximum file size: 10MB per document</li>
                            <li>Supported formats: PDF, DOC, DOCX, JPG, PNG</li>
                            <li>Click "Resubmit Application" when ready</li>
                        </ul>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" id="resubmitForm" data-api-url="<?php echo BASE_PUBLIC_URL; ?>/api/submit-affiliation.php">
                        <!-- Hidden fields for API resubmission -->
                        <input type="hidden" name="resubmit_id" value="<?php echo htmlspecialchars($application['id'] ?? ''); ?>">
                        <input type="hidden" name="institution_name" value="<?php echo htmlspecialchars($application['institution_name'] ?? ''); ?>">
                        <input type="hidden" name="institution_address" value="<?php echo htmlspecialchars($application['address'] ?? ''); ?>">
                        <input type="hidden" name="contact_person" value="<?php echo htmlspecialchars($application['contact_person'] ?? ''); ?>">
                        <input type="hidden" name="contact_position" value="<?php echo htmlspecialchars($application['contact_position'] ?? ''); ?>">
                        <input type="hidden" name="contact_phone" value="<?php echo htmlspecialchars($application['contact_phone'] ?? ''); ?>">
                        <input type="hidden" name="contact_email" value="<?php echo htmlspecialchars($application['email'] ?? ''); ?>">
                        
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-folder-open"></i> Document Uploads</h3>
                            
                            <?php foreach ($documentFields as $fieldName => $fieldConfig): 
                                $hasExisting = hasExistingDocument($existingDocuments, $fieldName);
                                $docInfo = getDocumentInfo($existingDocuments, $fieldName);
                            ?>
                                <div class="document-upload <?php echo $hasExisting ? 'has-file' : ''; ?>" id="upload-<?php echo $fieldName; ?>">
                                    <div class="document-header">
                                        <label class="document-label"><?php echo htmlspecialchars($fieldConfig['label']); ?></label>
                                        <?php if ($hasExisting): ?>
                                            <span class="existing-badge">
                                                <i class="fas fa-check"></i> Current File
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="document-description"><?php echo htmlspecialchars($fieldConfig['description']); ?></p>
                                    
                                    <div class="file-input-wrapper">
                                        <input 
                                            type="file" 
                                            name="<?php echo $fieldName; ?>" 
                                            id="<?php echo $fieldName; ?>" 
                                            class="file-input"
                                            accept="<?php echo $fieldConfig['accept']; ?>"
                                            onchange="updateFileName(this, '<?php echo $fieldName; ?>')"
                                        >
                                        <label for="<?php echo $fieldName; ?>" class="file-button">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <?php echo $hasExisting ? 'Replace File' : 'Choose File'; ?>
                                        </label>
                                        <span class="file-name <?php echo $hasExisting ? 'has-file' : ''; ?>" id="filename-<?php echo $fieldName; ?>">
                                            <?php if ($hasExisting): ?>
                                                <i class="fas fa-file"></i> <?php echo htmlspecialchars($docInfo['name'] ?? 'Existing file'); ?> (Current)
                                            <?php else: ?>
                                                No file selected
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    
                                    <p class="file-help">
                                        Accepted: <?php echo $fieldConfig['accept']; ?> | Max: 10MB
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <span class="loading-spinner"></span>
                                <span class="btn-text"><i class="fas fa-paper-plane"></i> Resubmit Application</span>
                            </button>
                            <a href="<?php echo BASE_URL; ?>/" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header error">
                    <h2><i class="fas fa-lock"></i> Invalid Access</h2>
                    <p>This page requires a valid access token. Please check your email for the correct link.</p>
                </div>
                <div class="card-body" style="text-align: center; padding: 40px;">
                    <p style="color: var(--neutral-500); margin-bottom: 24px;">
                        If you need to edit your application, please contact the Registration Committee to request a new link.
                    </p>
                    <a href="mailto:ieceplsc24@gmail.com" class="btn btn-primary">
                        <i class="fas fa-envelope"></i> Contact Support
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
        <footer class="footer">
            <p>© <?php echo date('Y'); ?> IECEP-LSC MEMSYS | <a href="mailto:ieceplsc24@gmail.com">ieceplsc24@gmail.com</a></p>
        </footer>
    </div>
    
    <script>
        function updateFileName(input, fieldName) {
            const fileName = input.files[0]?.name || '';
            const filenameDisplay = document.getElementById('filename-' + fieldName);
            const uploadContainer = document.getElementById('upload-' + fieldName);
            
            if (fileName) {
                filenameDisplay.innerHTML = '<i class="fas fa-file"></i> ' + fileName + ' (New)';
                filenameDisplay.classList.add('has-file');
                uploadContainer.classList.add('has-file');
            }
        }
        
        document.getElementById('resubmitForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            const hasFiles = Array.from(this.querySelectorAll('input[type="file"]')).some(input => input.files.length > 0);
            const apiUrl = this.dataset.apiUrl;
            
            if (!hasFiles) {
                alert('Please upload at least one corrected document before resubmitting.');
                return false;
            }
            
            // Show loading state
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
            submitBtn.querySelector('.btn-text').textContent = 'Uploading...';
            
            try {
                // Create FormData from the form
                const formData = new FormData(this);
                
                // Submit to API endpoint
                const response = await fetch(apiUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                // Get the response text first to debug if needed
                const responseText = await response.text();
                
                // Try to parse as JSON
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    console.error('Response was:', responseText);
                    throw new Error('Invalid response from server. Please try again.');
                }
                
                if (result.success) {
                    // Show success message - replace the form with success display
                    document.querySelector('.card').innerHTML = `
                        <div class="card-header success">
                            <h2><i class="fas fa-check-circle"></i> Success</h2>
                            <p>${result.message}</p>
                        </div>
                        <div class="card-body" style="text-align: center; padding: 40px;">
                            <a href="<?php echo BASE_URL; ?>/" class="btn btn-primary">
                                <i class="fas fa-home"></i> Return to Homepage
                            </a>
                        </div>
                    `;
                } else {
                    // Show error
                    throw new Error(result.error || 'Failed to resubmit application. Please try again.');
                }
            } catch (error) {
                console.error('Resubmission error:', error);
                alert(error.message || 'An error occurred. Please try again.');
                
                // Reset button state
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
                submitBtn.querySelector('.btn-text').textContent = 'Resubmit Application';
            }
        });
    </script>
</body>
</html>
