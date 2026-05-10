<?php
// Submit Affiliation Application API Endpoint
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("FATAL ERROR in submit-affiliation.php: " . json_encode($error));
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        echo json_encode([
            'success' => false,
            'error' => 'Server error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']
        ]);
    }
});

// Clean any existing output buffer
if (ob_get_length()) ob_clean();
if (ob_get_level()) ob_end_clean();

// Start fresh output buffer for JSON (will be overridden for HTML if needed)
ob_start();

// Set default JSON headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * Detect AJAX request
 */
function isAjax(): bool {
    return (
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
        (isset($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    );
}

/**
 * Send JSON response and exit (for AJAX calls)
 */
function sendJsonResponse($success, $message = '', $error = '', $data = []) {
    $response = ['success' => $success];
    if ($message) $response['message'] = $message;
    if ($error) $response['error'] = $error;
    if (!empty($data)) $response['data'] = $data;

    echo json_encode($response);
    ob_end_flush();
    exit;
}

/**
 * Output a beautiful HTML success page for browser visits
 */
function outputSuccessPage($message, $isResubmission = false) {
    // Use BASE_URL (project root) for the home link – avoids 404 on public/index.php
    $baseUrl = defined('BASE_URL') ? BASE_URL : '/IECEP-LSC-MEMSYS';
    $logoUrl = (defined('BASE_PUBLIC_URL') ? BASE_PUBLIC_URL : '/IECEP-LSC-MEMSYS/public') . '/assets/icons/iecep-logo.png';
    $homeUrl = $baseUrl . '/index.php';
    $title = $isResubmission ? 'Resubmission Successful' : 'Application Submitted';

    // Clear JSON headers and set HTML
    header_remove('Content-Type');
    header('Content-Type: text/html; charset=UTF-8');

    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $title . ' – IECEP-LSC</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Inter", sans-serif;
            background: linear-gradient(135deg, #0B1D4A 0%, #1E3A6E 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .success-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            padding: 3rem 2rem;
            text-align: center;
            animation: fadeUp 0.6s ease;
        }
        .logo {
            width: 80px;
            height: auto;
            margin-bottom: 1.5rem;
        }
        .check-icon {
            font-size: 4rem;
            color: #C49A00;
            margin-bottom: 1rem;
        }
        h1 {
            color: #0B1D4A;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        p.message {
            color: #333;
            font-size: 1.1rem;
            line-height: 1.6;
            margin: 1.5rem 0;
        }
        .btn {
            display: inline-block;
            background: #C49A00;
            color: #0B1D4A;
            font-weight: 700;
            padding: 12px 30px;
            border-radius: 50px;
            text-decoration: none;
            margin-top: 1rem;
            transition: background 0.3s;
        }
        .btn:hover { background: #b88b00; }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="success-card">
        <img src="' . $logoUrl . '" alt="IECEP-LSC Logo" class="logo">
        <div class="check-icon">✔</div>
        <h1>' . $title . '!</h1>
        <p class="message">' . htmlspecialchars($message) . '</p>
        <a href="' . $homeUrl . '" class="btn">Return to Home</a>
    </div>
</body>
</html>';
    ob_end_flush();
    exit;
}

// --------------------------------------------------
// Main Logic
// --------------------------------------------------

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    sendJsonResponse(false, '', 'Method not allowed. Use POST.');
}

// Function to convert PHP ini size values to bytes
function returnBytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

// Check content length against post_max_size
$postMaxBytes = returnBytes(ini_get('post_max_size'));
$contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;

if ($contentLength > $postMaxBytes) {
    http_response_code(413);
    sendJsonResponse(false, '', 'Request too large. Total size ' . round($contentLength / 1024 / 1024, 2) . 'MB exceeds maximum allowed ' . ini_get('post_max_size'));
}

// Comprehensive extension bypass for missing PHP extensions
$missingExtensions = [];
$requiredExtensions = ['curl', 'json', 'openssl', 'mbstring', 'mysqli', 'pdo_mysql'];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

// Log missing extensions but continue with fallback
if (!empty($missingExtensions)) {
    error_log("Missing PHP extensions: " . implode(', ', $missingExtensions));

    // Check if we can still proceed with Supabase API
    if (!extension_loaded('curl') || !extension_loaded('json')) {
        sendJsonResponse(false, '', 'Server configuration error: Critical extensions (curl, json) are missing. Please contact your server administrator.');
    }

    // Log warnings for other missing extensions
    $warningExtensions = array_diff($missingExtensions, ['curl', 'json']);
    if (!empty($warningExtensions)) {
        error_log("WARNING: Some extensions missing, using fallback methods: " . implode(', ', $warningExtensions));
    }
}

// Add browser extension bypass headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Handle preflight OPTIONS request for browser extensions
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Load configuration first
    $configFilePath = __DIR__ . '/../../includes/config.php';
    if (!file_exists($configFilePath)) {
        error_log("Config file not found at: $configFilePath");
        sendJsonResponse(false, '', 'Configuration file not found');
    }
    require_once $configFilePath;
    error_log("Config loaded, APP_ENV: " . (defined('APP_ENV') ? APP_ENV : 'NOT DEFINED'));

    // Load Supabase configuration
    $configPath = __DIR__ . '/../../includes/supabase.php';
    if (!file_exists($configPath)) {
        error_log("Config file not found at: $configPath");
        sendJsonResponse(false, '', 'Supabase configuration not found');
    }

    $config = require $configPath;
    error_log("Supabase config loaded: " . json_encode(array_keys($config)));

    // Check if critical extensions are available for SupabaseClient
    if (!extension_loaded('curl') || !extension_loaded('json')) {
        error_log("Critical extensions missing: curl=" . (extension_loaded('curl') ? 'yes' : 'no') . ", json=" . (extension_loaded('json') ? 'yes' : 'no'));
        sendJsonResponse(false, '', 'Server configuration error: Critical PHP extensions (curl, json) are missing. Please contact your server administrator.');
    }

    // Load Supabase client with comprehensive error handling
    $clientPath = __DIR__ . '/../../src/lib/SupabaseClient.php';
    if (!file_exists($clientPath)) {
        error_log("Supabase client file not found at: $clientPath");
        sendJsonResponse(false, '', 'Supabase client not found');
    }

    require_once $clientPath;
    error_log("SupabaseClient file loaded from: $clientPath");

    try {
        // Check if class exists
        if (!class_exists('SupabaseClient', false)) {
            error_log("SupabaseClient class not found after require. Declared classes: " . implode(', ', get_declared_classes()));
            sendJsonResponse(false, '', 'SupabaseClient class not found');
        }

        error_log("Creating SupabaseClient with URL: {$config['url']}");
        $supabase = new SupabaseClient($config['url'], $config['anon_key']);
        error_log("Supabase client created successfully");

    } catch (Exception $e) {
        error_log("Supabase connection error: " . $e->getMessage());
        error_log("Supabase error details: " . $e->getTraceAsString());
        sendJsonResponse(false, '', 'Database connection failed: ' . $e->getMessage());
    }

    // Get POST data
    $institutionName = $_POST['institution_name'] ?? '';
    $institutionAddress = $_POST['institution_address'] ?? '';
    $contactPerson = $_POST['contact_person'] ?? '';
    $contactPosition = $_POST['contact_position'] ?? '';
    $contactPhone = $_POST['contact_phone'] ?? '';
    $contactEmail = $_POST['contact_email'] ?? '';
    $resubmitId = $_POST['resubmit_id'] ?? '';

    error_log("Submit affiliation request - Institution: $institutionName, Email: $contactEmail, Resubmit: $resubmitId");

    // Validate required fields
    $requiredFields = [
        'institution_name' => $institutionName,
        'institution_address' => $institutionAddress,
        'contact_person' => $contactPerson,
        'contact_phone' => $contactPhone,
        'contact_email' => $contactEmail
    ];

    foreach ($requiredFields as $field => $value) {
        if (empty(trim($value))) {
            sendJsonResponse(false, '', ucfirst(str_replace('_', ' ', $field)) . ' is required');
        }
    }

    // Validate email
    if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(false, '', 'Please provide a valid email address');
    }

    // Validate phone (basic validation)
    if (!preg_match('/^[0-9+\-\s()]+$/', $contactPhone)) {
        sendJsonResponse(false, '', 'Please provide a valid phone number');
    }

    // Check for duplicate email - if exists, automatically resubmit instead of blocking
    if (empty($resubmitId)) {
        $existingEmail = $supabase->select('pending_affiliations', ['email' => 'eq.' . $contactEmail]);
        if (!empty($existingEmail) && is_array($existingEmail)) {
            $existingApp = $existingEmail[0];
            $status = $existingApp['status'] ?? '';

            if ($status === 'approved') {
                sendJsonResponse(false, '', 'This email is already registered with an approved affiliation. Please use a different email or contact support.');
            } else {
                // Email exists but not approved - automatically resubmit by updating the existing application
                $resubmitId = $existingApp['id'];
                error_log("Found existing pending application ($resubmitId), will update instead of creating new");

                // Load existing documents for this application
                try {
                    $existingDocs = json_decode($existingApp['documents'], true) ?: [];
                    $existingDocuments = $existingDocs;
                    error_log("Loaded existing documents for auto-resubmission: " . count($existingDocuments));
                } catch (Exception $e) {
                    error_log("Error loading existing documents: " . $e->getMessage());
                    $existingDocuments = [];
                }
            }
        }
    }

    // Handle file uploads
    $documents = [];
    $documentFields = [
        // Initial submission fields
        'letter_of_intent' => ['name' => 'Letter of Intent', 'required' => true, 'max_size' => 15728640], // 15MB
        'endorsement_letter' => ['name' => 'Endorsement Letter', 'required' => true, 'max_size' => 15728640],
        'constitution_by_laws' => ['name' => 'Constitution and By-Laws', 'required' => true, 'max_size' => 15728640],
        'officers_cvs' => ['name' => 'List of Officers with CVs', 'required' => true, 'max_size' => 15728640],
        'organizational_chart' => ['name' => 'Organizational Chart', 'required' => true, 'max_size' => 15728640],
        'member_directory' => ['name' => 'Member Directory', 'required' => true, 'max_size' => 15728640],
        // Edit-affiliation resubmission fields (not required for updates)
        'moa' => ['name' => 'Memorandum of Agreement', 'required' => false, 'max_size' => 10485760], // 10MB
        'accreditation' => ['name' => 'Accreditation Certificate', 'required' => false, 'max_size' => 10485760],
        'cor' => ['name' => 'Certificate of Registration', 'required' => false, 'max_size' => 10485760],
        'school_registration' => ['name' => 'School Registration Document', 'required' => false, 'max_size' => 10485760],
        'officers_list' => ['name' => 'List of Officers', 'required' => false, 'max_size' => 10485760],
        'faculty_adviser' => ['name' => 'Faculty Adviser Profile', 'required' => false, 'max_size' => 10485760],
        'id_picture' => ['name' => 'Contact Person ID Picture', 'required' => false, 'max_size' => 10485760]
    ];

    // Allowed file types
    $allowedTypes = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/csv' => 'csv',
        'image/jpeg' => 'jpg',
        'image/png' => 'png'
    ];

    // Load existing documents if resubmitting
    $existingDocuments = [];
    if (!empty($resubmitId)) {
        try {
            $existingApp = $supabase->select('pending_affiliations', ['id' => 'eq.' . $resubmitId]);
            if (!empty($existingApp) && is_array($existingApp)) {
                $existingDocs = json_decode($existingApp[0]['documents'], true) ?: [];
                $existingDocuments = $existingDocs;
                error_log("Loaded existing documents for resubmission: " . count($existingDocuments));
            }
        } catch (Exception $e) {
            error_log("Error loading existing documents: " . $e->getMessage());
        }
    }

    // Process each document
    foreach ($documentFields as $fieldName => $fieldInfo) {
        error_log("Processing document field: $fieldName");

        if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES[$fieldName];
            $fileType = $file['type'];
            $fileName = $file['name'];
            $fileSize = $file['size'];

            error_log("Processing file: $fieldName, Name: $fileName, Type: $fileType, Size: $fileSize");

            // Validate file type
            if (!isset($allowedTypes[$fileType])) {
                error_log("Invalid file type: $fileType for field: $fieldName");
                sendJsonResponse(false, '', $fieldInfo['name'] . ' must be a PDF, Word, Excel, CSV, or image file');
            }

            // Validate file size
            if ($fileSize > $fieldInfo['max_size']) {
                error_log("File too large: $fileSize bytes for field: $fieldName");
                sendJsonResponse(false, '', $fieldInfo['name'] . ' must be less than ' . ($fieldInfo['max_size'] / 1024 / 1024) . 'MB');
            }

            // Create upload directory if it doesn't exist
            $uploadDir = __DIR__ . '/../assets/uploads/affiliations/';
            if (!file_exists($uploadDir)) {
                error_log("Creating upload directory: $uploadDir");
                if (!mkdir($uploadDir, 0755, true)) {
                    error_log("Failed to create upload directory: $uploadDir");
                    sendJsonResponse(false, '', 'Failed to create upload directory');
                }
                error_log("Upload directory created successfully");
            }

            // Check if directory is writable
            if (!is_writable($uploadDir)) {
                error_log("Upload directory is not writable: $uploadDir");
                sendJsonResponse(false, '', 'Upload directory is not writable');
            }

            // Generate unique filename
            $fileExtension = $allowedTypes[$fileType];
            $uniqueFileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9.-]/', '_', $fileName);
            $uploadPath = $uploadDir . $uniqueFileName;

            error_log("Moving file from {$file['tmp_name']} to $uploadPath");

            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $fileHash = hash_file('sha256', $uploadPath);
                $documents[$fieldName] = [
                    'name' => $fileName,
                    'type' => $fileType,
                    'size' => $fileSize,
                    'path' => 'assets/uploads/affiliations/' . $uniqueFileName,
                    'uploaded_at' => date('Y-m-d H:i:s'),
                    'hash' => $fileHash
                ];
                error_log("File uploaded successfully: $fieldName -> $uniqueFileName, Hash: $fileHash");
            } else {
                error_log("Failed to move uploaded file: $fieldName");
                sendJsonResponse(false, '', 'Failed to upload ' . $fieldInfo['name']);
            }
        } else {
            error_log("No file uploaded for field: $fieldName, error: " . ($_FILES[$fieldName]['error'] ?? 'not set'));

            // If resubmitting and no new file uploaded, keep existing document
            if (!empty($resubmitId) && isset($existingDocuments[$fieldName])) {
                $documents[$fieldName] = $existingDocuments[$fieldName];
                error_log("Keeping existing document for $fieldName");
            } elseif ($fieldInfo['required'] && empty($resubmitId)) {
                error_log("Required file missing: $fieldName");
                sendJsonResponse(false, '', $fieldInfo['name'] . ' is required');
            }
        }
    }

    // Prepare application data
    $applicationData = [
        'institution_name' => trim($institutionName),
        'address' => trim($institutionAddress),
        'contact_person' => trim($contactPerson),
        'contact_position' => trim($contactPosition),
        'contact_phone' => trim($contactPhone),
        'email' => trim($contactEmail),
        'documents' => json_encode($documents),
        'submitted_at' => date('Y-m-d H:i:s'),
        'status' => 'pending'
    ];

    // Add resubmission specific fields
    if (!empty($resubmitId)) {
        $applicationData['resubmitted_at'] = date('Y-m-d H:i:s');
        $applicationData['status'] = 'resubmitted';
        $applicationData['edit_token'] = null; // Invalidate the edit token

        // Preserve existing documents that weren't replaced
        $applicationData['documents'] = json_encode(array_merge($existingDocuments, $documents));
    }

    error_log("Application data prepared: " . json_encode($applicationData));

    // Handle submission
    if (!empty($resubmitId)) {
        // Update existing record
        $result = $supabase->update('pending_affiliations', $applicationData, $resubmitId);

        if ($result) {
            error_log("Application resubmitted successfully: $resubmitId");

            if (isAjax()) {
                sendJsonResponse(true, 'Application resubmitted successfully. The Registration Committee will review your updated application within 3-5 business days.');
            } else {
                outputSuccessPage('Application resubmitted successfully. The Registration Committee will review your updated application within 3-5 business days.', true);
            }
        } else {
            sendJsonResponse(false, '', 'Failed to update your application. Please try again.');
        }
    } else {
        // Insert new record
        $result = $supabase->insert('pending_affiliations', $applicationData);

        if ($result && isset($result[0]['id'])) {
            $applicationId = $result[0]['id'];
            error_log("Application submitted successfully: $applicationId");

            // Record document hashes on blockchain
            $blockchain = $GLOBALS['blockchain'] ?? null;
            if (isset($blockchain) && $blockchain instanceof \App\Lib\BlockchainService) {
                foreach ($documents as $fieldName => $docInfo) {
                    if (isset($docInfo['hash'])) {
                        $blockchain->record('document_hash', $applicationId, [
                            'field_name' => $fieldName,
                            'file_name' => $docInfo['name'],
                            'hash' => $docInfo['hash'],
                            'mime_type' => $docInfo['type'],
                            'size' => $docInfo['size'],
                            'path' => $docInfo['path']
                        ]);
                    }
                }
                error_log("Document hashes recorded on blockchain for application: $applicationId");
            }

            // Send confirmation email to applicant (if possible)
            try {
                if (extension_loaded('openssl') && extension_loaded('mbstring')) {
                    require_once __DIR__ . '/../../src/lib/EmailService.php';
                    $emailService = new \App\Lib\EmailService();
                    $emailSent = $emailService->sendAffiliationConfirmation($contactEmail, $institutionName);
                    error_log("Confirmation email sent to $contactEmail: " . ($emailSent ? 'SUCCESS' : 'FAILED'));
                } else {
                    error_log("WARNING: Email extensions missing, skipping email notification");
                }
            } catch (Exception $e) {
                error_log("Failed to send confirmation email: " . $e->getMessage());
            }

            if (isAjax()) {
                sendJsonResponse(true, 'Application submitted successfully. The Registration Committee will review your application within 3-5 business days.', '', [
                    'application_id' => $applicationId
                ]);
            } else {
                outputSuccessPage('Application submitted successfully. The Registration Committee will review your application within 3-5 business days.');
            }
        } else {
            sendJsonResponse(false, '', 'Failed to save your application. Please try again.');
        }
    }

} catch (Exception $e) {
    $errorMsg = "Submit affiliation error: " . $e->getMessage();
    error_log($errorMsg);
    error_log("Exception trace: " . $e->getTraceAsString());
    error_log("Exception file: " . $e->getFile() . " line: " . $e->getLine());
    error_log("POST data: " . json_encode($_POST));
    error_log("FILES data: " . json_encode(array_map(function($file) {
        return [
            'name' => $file['name'] ?? 'N/A',
            'size' => $file['size'] ?? 0,
            'error' => $file['error'] ?? 0,
            'type' => $file['type'] ?? 'N/A'
        ];
    }, $_FILES)));

    if (defined('APP_ENV') && APP_ENV === 'development') {
        sendJsonResponse(false, '', 'Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    } else {
        sendJsonResponse(false, '', 'An error occurred while processing your application. Please try again.');
    }
}

// Clean buffer and send output (should not reach here)
ob_end_flush();
exit;
?>