<?php
// API Proxy - forwards requests to api
error_reporting(0); // Suppress all errors
ini_set('display_errors', 0);

// Get endpoint from URL
$endpoint = $_GET['endpoint'] ?? '';
$action = $_GET['action'] ?? '';

error_log("API Proxy: endpoint=$endpoint, action=$action");

// Forward to the actual API file
$apiFile = __DIR__ . '/api/' . $endpoint . '.php';

error_log("API Proxy: Looking for file=$apiFile, exists=" . (file_exists($apiFile) ? 'YES' : 'NO'));

if (file_exists($apiFile)) {
    // Capture output to prevent any HTML from being sent
    ob_start();
    try {
        require $apiFile;
        $output = ob_get_clean();

        error_log("API Proxy: Output length=" . strlen($output) . ", first 100 chars=" . substr($output, 0, 100));

        // If output is empty, it means the file already sent JSON
        if (empty($output)) {
            exit;
        }

        // If output contains JSON, send it
        if (json_decode($output) !== null) {
            echo $output;
        } else {
            // Output contains non-JSON (likely errors), return error
            error_log("API Proxy: Output is not JSON, returning error");
            echo json_encode(['success' => false, 'message' => 'Server error occurred']);
        }
    } catch (Exception $e) {
        ob_end_clean();
        error_log("API Proxy: Exception - " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(404);
    error_log("API Proxy: File not found");
    echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
}
