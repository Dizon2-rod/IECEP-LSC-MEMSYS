<?php
require_once __DIR__ . '/bootstrap.php';
/**
 * Get Documents API
 * Fetch all documents for an application
 */

require_once __DIR__ . '/../../../includes/paths.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../portal/auth_check.php';

require_role(['admin', 'registration']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $supabase = getSupabaseClient();

    $application_id = $_GET['application_id'] ?? null;

    if (!$application_id) {
        throw new Exception('Application ID is required');
    }

    // Fetch documents
    $response = $supabase->from('affiliation_documents')
        ->select('*')
        ->eq('application_id', $application_id)
        ->execute();

    $documents = $response->data ?? [];

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'documents' => $documents
    ]);

} catch (Exception $e) {
    error_log("Get documents error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
