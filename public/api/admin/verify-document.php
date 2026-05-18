<?php
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
    $db = getDbConnection();
    
    $application_id = $_POST['application_id'] ?? null;
    $document_type = $_POST['document_type'] ?? null;
    $verified = isset($_POST['verified']) ? (bool)$_POST['verified'] : false;
    $user_id = $_SESSION['user']['id'] ?? null;
    
    if (!$application_id || !$document_type) {
        throw new Exception('Missing required parameters');
    }
    
    // Update verification status
    $stmt = $db->prepare("
        UPDATE affiliation_documents
        SET verified = ?,
            verified_by = ?,
            verified_at = CASE WHEN ? THEN NOW() ELSE NULL END
        WHERE application_id = ? AND document_type = ?
    ");
    $stmt->execute([$verified, $user_id, $verified, $application_id, $document_type]);
    
    // Check if all 6 documents are verified
    $stmt = $db->prepare("
        SELECT COUNT(*) as total, SUM(CASE WHEN verified THEN 1 ELSE 0 END) as verified_count
        FROM affiliation_documents
        WHERE application_id = ?
    ");
    $stmt->execute([$application_id]);
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $all_verified = ($counts['total'] == 6 && $counts['verified_count'] == 6);
    
    // Log audit
    log_audit('document_verification', 'affiliation_documents', $application_id, null, [
        'document_type' => $document_type,
        'verified' => $verified,
        'all_verified' => $all_verified
    ]);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Verification status updated',
        'all_verified' => $all_verified,
        'verified_count' => (int)$counts['verified_count']
    ]);
    
} catch (Exception $e) {
    error_log("Verify document error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
