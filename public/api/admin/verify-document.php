<?php
require_once __DIR__ . '/bootstrap.php';
/**
 * Verify Document API
 * Toggle document verification status
 */

require_once __DIR__ . '/../../../includes/paths.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../portal/auth_check.php';

require_role(['admin', 'registration']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
    exit;
}

try {
    $supabase = getSupabaseClient();

    $application_id = $_POST['application_id'] ?? null;
    $document_type = $_POST['document_type'] ?? null;
    $verified = isset($_POST['verified']) ? (bool)$_POST['verified'] : false;
    $user_id = $_SESSION['user']['id'] ?? null;

    if (!$application_id || !$document_type) {
        throw new Exception('Missing required parameters');
    }

    // Update verification status
    $updateData = [
        'verified' => $verified,
        'verified_by' => $user_id,
        'verified_at' => $verified ? date('Y-m-d H:i:s') : null,
    ];
    $supabase->from('affiliation_documents')
        ->update($updateData)
        ->eq('application_id', $application_id)
        ->eq('document_type', $document_type)
        ->execute();

    // Check if all 6 documents are verified
    $countResponse = $supabase->from('affiliation_documents')
        ->select('id', ['count' => 'exact'])
        ->eq('application_id', $application_id)
        ->execute();

    $totalCount = $countResponse->count ?? 0;

    // Fetch verified count
    $verifiedResponse = $supabase->from('affiliation_documents')
        ->select('id')
        ->eq('application_id', $application_id)
        ->eq('verified', true)
        ->execute();

    $verifiedCount = count($verifiedResponse->data ?? []);

    $allVerified = ($totalCount == 6 && $verifiedCount == 6);

    // Log audit
    log_audit('document_verification', 'affiliation_documents', $application_id, null, [
        'document_type' => $document_type,
        'verified' => $verified,
        'all_verified' => $allVerified
    ]);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Verification status updated',
        'all_verified' => $allVerified,
        'verified_count' => (int)$verifiedCount
    ]);

} catch (Exception $e) {
    error_log("Verify document error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
