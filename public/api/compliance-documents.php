<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/database.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

session_start();
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

switch ($action) {
    case 'upload_document':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        $schoolId = $_POST['school_id'] ?? null;
        $docType = $_POST['doc_type'] ?? null;
        
        if (!$schoolId || !$docType || !isset($_FILES['document'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }
        
        $uploadDir = __DIR__ . '/../../../storage/compliance/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $fileName = uniqid() . '_' . basename($_FILES['document']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['document']['tmp_name'], $targetPath)) {
            $fileUrl = '/storage/compliance/' . $fileName;
            
            $query = "INSERT INTO compliance_docs (school_id, doc_type, file_url) 
                      VALUES ($1, $2, $3) RETURNING id";
            $result = pg_query_params($conn, $query, [$schoolId, $docType, $fileUrl]);
            
            if ($result) {
                $row = pg_fetch_assoc($result);
                echo json_encode(['success' => true, 'doc_id' => $row['id']]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save document']);
            }
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to upload file']);
        }
        break;
        
    case 'get_documents':
        $schoolId = $_GET['school_id'] ?? null;
        
        if (!$schoolId) {
            http_response_code(400);
            echo json_encode(['error' => 'School ID required']);
            exit;
        }
        
        $query = "SELECT * FROM compliance_docs WHERE school_id = $1 ORDER BY created_at DESC";
        $result = pg_query_params($conn, $query, [$schoolId]);
        
        $docs = [];
        while ($row = pg_fetch_assoc($result)) {
            $docs[] = $row;
        }
        
        echo json_encode(['documents' => $docs]);
        break;
        
    case 'verify_document':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        $docId = $_POST['doc_id'] ?? null;
        
        if (!$docId) {
            http_response_code(400);
            echo json_encode(['error' => 'Document ID required']);
            exit;
        }
        
        $query = "UPDATE compliance_docs 
                  SET is_verified = true, verified_by = $1, verified_at = NOW() 
                  WHERE id = $2";
        $result = pg_query_params($conn, $query, [$userId, $docId]);
        
        if ($result) {
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
