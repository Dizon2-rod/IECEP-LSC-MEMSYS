<?php
require_once __DIR__ . '/bootstrap.php';
// API Router - routes /api/{endpoint}?action={action} to the correct PHP file
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Check if critical extensions are available
$criticalExtensions = ['curl', 'json'];
$missingCritical = [];
foreach ($criticalExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingCritical[] = $ext;
    }
}

if (!empty($missingCritical)) {
    error_log("Missing critical PHP extensions: " . implode(', ', $missingCritical));
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error: Missing critical PHP extensions. Please contact your server administrator.']);
    exit;
}

$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

error_log("API Router: Request URI = $requestUri, Path = $path");

// Extract endpoint from /api/{endpoint} or /IECEP-LSC-MEMSYS/api/{endpoint}
if (preg_match('#/api/([a-z0-9_-]+)$#', $path, $matches)) {
    $endpoint = $matches[1];
    error_log("API Router: Extracted endpoint = $endpoint");
} else {
    error_log("API Router: No endpoint match found in path");
    $endpoint = null;
}

if ($endpoint) {

    $allowedEndpoints = [
        'auth', 'affiliate', 'email', 'registration', 'officer', 'member',
        'treasurer', 'auditor', 'secretary', 'vp-academic', 'pro',
        'committee', 'super-admin', 'compliance', 'attendance',
        'verify-payment', 'verify-member', 'affiliation_status',
    ];

    if (in_array($endpoint, $allowedEndpoints)) {
        $file = __DIR__ . "/{$endpoint}.php";
        error_log("API Router: Looking for file = $file, exists = " . (file_exists($file) ? 'YES' : 'NO'));
        if (file_exists($file)) {
            error_log("API Router: Including file $file");
            require $file;
            exit;
        }
    }
}

http_response_code(404);
echo json_encode(['error' => 'Endpoint not found']);
