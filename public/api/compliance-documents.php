<?php
/**
 * compliance-documents.php - Centralized API for institution compliance documents
 */

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$supabase = getSupabaseClient();
if (!$supabase) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection unavailable']);
    exit;
}

$docRepo = \App\Lib\DocumentRepository::getInstance($supabase);

switch ($action) {
    case 'upload_document':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        $schoolId = $_POST['school_id'] ?? ($_POST['institution_id'] ?? null);
        $docType = $_POST['doc_type'] ?? ($_POST['document_type'] ?? null);
        
        if (!$schoolId || !$docType || !isset($_FILES['document'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields (school_id, doc_type, document file)']);
            exit;
        }
        
        $mimeType = mime_content_type($_FILES['document']['tmp_name']) ?: $_FILES['document']['type'];
        $fileName = uniqid() . '_' . basename($_FILES['document']['name']);
        
        $supabaseUrl = uploadToSupabaseStorage('compliance', 'documents/' . $fileName, $_FILES['document']['tmp_name'], $mimeType);
        
        if ($supabaseUrl) {
            $created = $docRepo->uploadDocument([
                'institution_id' => $schoolId,
                'document_type'  => $docType,
                'file_url'       => $supabaseUrl,
                'file_name'      => $_FILES['document']['name'],
                'file_size'      => $_FILES['document']['size'] ?? 0,
                'uploaded_by'    => $userId,
                'status'         => 'submitted'
            ]);
            
            if ($created) {
                echo json_encode(['success' => true, 'doc_id' => $created['id'] ?? null, 'url' => $supabaseUrl]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save document record']);
            }
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to upload document file to storage']);
        }
        break;
        
    case 'get_documents':
        $schoolId = $_GET['school_id'] ?? ($_GET['institution_id'] ?? null);
        
        if (!$schoolId) {
            http_response_code(400);
            echo json_encode(['error' => 'School ID required']);
            exit;
        }
        
        $docs = $docRepo->getDocumentsForInstitution($schoolId);
        $missing = $docRepo->getMissingDocuments($schoolId);
        $required = $docRepo->getRequiredTypes();
        
        echo json_encode([
            'success' => true,
            'documents' => $docs,
            'missing' => $missing,
            'required_types' => $required
        ]);
        break;
        
    case 'get_required_types':
        echo json_encode([
            'success' => true,
            'required_types' => $docRepo->getRequiredTypes()
        ]);
        break;

    case 'verify_document':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        $docId = $_POST['doc_id'] ?? ($_POST['id'] ?? null);
        $status = $_POST['status'] ?? 'approved';
        $remarks = $_POST['remarks'] ?? null;
        
        if (!$docId) {
            http_response_code(400);
            echo json_encode(['error' => 'Document ID required']);
            exit;
        }
        
        $success = $docRepo->updateStatus($docId, $status, $userId, $remarks);
        
        if ($success) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to verify document']);
        }
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
