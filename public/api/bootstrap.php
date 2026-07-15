<?php
/**
 * API Bootstrap File
 * 
 * This file provides a consistent bootstrap for all API endpoints.
 * It includes the main project bootstrap and sets up API-specific headers.
 */

// Include the main project bootstrap
require_once __DIR__ . '/../../bootstrap.php';

// Only set headers if running in web context (not CLI)
if (php_sapi_name() !== 'cli') {
    // Set API-specific headers
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Handle preflight OPTIONS requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// Check for critical PHP extensions (warn but don't exit in CLI)
$criticalExtensions = ['curl', 'json'];
$missingCritical = [];
foreach ($criticalExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingCritical[] = $ext;
    }
}

if (!empty($missingCritical)) {
    $errorMsg = "Missing critical PHP extensions: " . implode(', ', $missingCritical);
    error_log($errorMsg);
    
    // Only exit if running in web context
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        echo json_encode(['error' => 'Server configuration error: Missing critical PHP extensions. Please contact your server administrator.']);
        exit;
    }
}

// Load API-specific configurations
require_once __DIR__ . '/../../includes/supabase.php';
require_once __DIR__ . '/../../includes/config.php';

// Initialize common services
try {
    // Supabase client is initialized in the main bootstrap via getSupabaseClient()
    // Additional API-specific initializations can go here
} catch (Exception $e) {
    error_log("API bootstrap error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server initialization error', 'message' => $e->getMessage()]);
    exit;
}

// API bootstrap complete
error_log('[API Bootstrap] API endpoint initialized successfully');