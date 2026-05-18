<?php
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
    $db = getDbConnection();
    
    $application_id = $_GET['application_id'] ?? null;
    
    if (!$application_id) {
        throw new Exception('Application ID is required');
    }
    
    // Fetch documents
    $stmt = $db->prepare("
        SELECT * FROM affiliation_documents
        WHERE application_id = ?
        ORDER BY 
            CASE document_type
                WHEN 'letter_of_intent' THEN 1
                WHEN 'endorsement_letter' THEN 2
                WHEN 'constitution_bylaws' THEN 3
                WHEN 'officers_cv' THEN 4
                WHEN 'org_chart' THEN 5
                WHEN 'member_directory' THEN 6
            END
    ");
    $stmt->execute([$application_id]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
