<?php
// API Proxy - forwards requests to api
require_once __DIR__ . '/../includes/config.php';

if (defined('APP_ENV') && APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

header('Content-Type: application/json; charset=utf-8');

// Get endpoint from URL and sanitize it
$endpoint = strtolower($_GET['endpoint'] ?? '');
$endpoint = preg_replace('/[^a-z0-9_-]/', '', $endpoint);
$action = $_GET['action'] ?? '';

error_log("API Proxy: endpoint={$endpoint}, action={$action}");

if (empty($endpoint)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Endpoint parameter is required']);
    exit;
}

$apiFile = __DIR__ . '/api/' . $endpoint . '.php';
error_log("API Proxy: Looking for file={$apiFile}, exists=" . (file_exists($apiFile) ? 'YES' : 'NO'));

if (file_exists($apiFile)) {
    ob_start();
    try {
        require $apiFile;
        $output = ob_get_clean();

        if (empty($output)) {
            exit;
        }

        $decoded = json_decode($output, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            echo $output;
            exit;
        }

        error_log("API Proxy: Output is not JSON: " . substr($output, 0, 500));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error occurred']);
    } catch (Throwable $e) {
        ob_end_clean();
        error_log("API Proxy: Exception - " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server error occurred']);
    }
} else {
    http_response_code(404);
    error_log('API Proxy: File not found');
    echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
}
