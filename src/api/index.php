<?php
// API Router - routes /api/{endpoint}?action={action} to the correct PHP file
header('Content-Type: application/json');

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
