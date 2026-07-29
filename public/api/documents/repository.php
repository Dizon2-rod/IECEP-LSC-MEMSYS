<?php
/**
 * Document Repository API
 * Centralized document storage with categorization
 */

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/middleware/auth.php';

header('Content-Type: application/json');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    $db = $GLOBALS['supabaseClient'] ?? null;
    if (!$db) {
        throw new Exception('Database connection not available');
    }
    
    switch ($method) {
        case 'POST':
            if ($action === 'upload') {
                // Upload document to repository
                $title = $_POST['title'] ?? '';
                $category = $_POST['category'] ?? '';
                $description = $_POST['description'] ?? '';
                $userId = $_SESSION['user']['id'] ?? '';
                
                if (empty($title) || empty($category)) {
                    throw new Exception('title and category are required');
                }
                
                // Handle file upload
                if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('File upload is required');
                }
                
                $file = $_FILES['file'];
                $fileName = $file['name'];
                $fileSize = $file['size'];
                $fileTmp = $file['tmp_name'];
                $fileType = mime_content_type($fileTmp);
                
                // Validate file type
                $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png'];
                if (!in_array($fileType, $allowedTypes)) {
                    throw new Exception('Invalid file type. Allowed types: PDF, DOC, DOCX, JPG, PNG');
                }
                
                // Generate unique filename
                $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
                $newFileName = uniqid() . '.' . $fileExt;
                $uploadPath = __DIR__ . '/../../uploads/documents/' . $newFileName;
                
                // Create directory if it doesn't exist
                $uploadDir = dirname($uploadPath);
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Move uploaded file
                if (!move_uploaded_file($fileTmp, $uploadPath)) {
                    throw new Exception('Failed to upload file');
                }
                
                // Hash document for blockchain verification
                $fileContent = file_get_contents($uploadPath);
                $fileHash = hash('sha256', $fileContent);
                
                // Insert document record
                $documentId = generateUUID();
                
                $result = $db->insert('documents', [
                    'id' => $documentId,
                    'title' => $title,
                    'category' => $category,
                    'description' => $description,
                    'file_path' => '/uploads/documents/' . $newFileName,
                    'file_name' => $fileName,
                    'file_size' => $fileSize,
                    'mime_type' => $fileType,
                    'file_hash' => $fileHash,
                    'uploaded_by' => $userId,
                    'version' => 1,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                // Record on blockchain
                if (isset($GLOBALS['blockchain'])) {
                    $GLOBALS['blockchain']->hashDocument($uploadPath, $fileName, $documentId);
                }
                
                echo json_encode([
                    'success' => true,
                    'document_id' => $documentId,
                    'message' => 'Document uploaded successfully'
                ]);
                
            } elseif ($action === 'update') {
                // Update document metadata or upload new version
                $input = json_decode(file_get_contents('php://input'), true);
                
                $documentId = $input['document_id'] ?? '';
                $title = $input['title'] ?? '';
                $category = $input['category'] ?? '';
                $description = $input['description'] ?? '';
                
                if (empty($documentId)) {
                    throw new Exception('document_id is required');
                }
                
                // Get current document
                $documents = $db->select('documents', [
                    'id' => 'eq.' . $documentId
                ]);
                
                if (empty($documents)) {
                    throw new Exception('Document not found');
                }
                
                $currentDoc = $documents[0];
                
                // Update metadata
                $updateData = [];
                if (!empty($title)) $updateData['title'] = $title;
                if (!empty($category)) $updateData['category'] = $category;
                if (!empty($description)) $updateData['description'] = $description;
                
                if (!empty($updateData)) {
                    $db->update('documents', $updateData, $documentId);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Document updated successfully'
                ]);
                
            } else {
                throw new Exception('Invalid action');
            }
            break;
            
        case 'GET':
            if ($action === 'list') {
                // Get documents with filtering
                $category = $_GET['category'] ?? '';
                $search = $_GET['search'] ?? '';
                
                $filters = [];
                if (!empty($category)) {
                    $filters['category'] = 'eq.' . $category;
                }
                $filters['order'] = 'created_at.desc';
                
                $documents = $db->select('documents', $filters);
                
                // Filter by search term if provided
                if (!empty($search)) {
                    $documents = array_filter($documents, function($doc) use ($search) {
                        return stripos($doc['title'], $search) !== false || 
                               stripos($doc['description'] ?? '', $search) !== false;
                    });
                }
                
                // Get uploader names
                foreach ($documents as &$doc) {
                    $userData = $db->select('user_profiles', [
                        'id' => 'eq.' . $doc['uploaded_by'],
                        'select' => 'full_name'
                    ]);
                    $doc['uploader_name'] = $userData[0]['full_name'] ?? 'Unknown';
                }
                
                echo json_encode([
                    'success' => true,
                    'documents' => array_values($documents)
                ]);
                
            } elseif ($action === 'categories') {
                // Get document categories
                $categories = [
                    'affiliation' => 'Affiliation Documents',
                    'constitution' => 'Constitution & Bylaws',
                    'policies' => 'Policies & Guidelines',
                    'memoranda' => 'Internal Memoranda',
                    'financial' => 'Financial Reports',
                    'compliance' => 'Compliance Reports',
                    'certificates' => 'Certificates',
                    'templates' => 'Templates',
                    'other' => 'Other'
                ];
                
                echo json_encode([
                    'success' => true,
                    'categories' => $categories
                ]);
                
            } elseif ($action === 'detail') {
                // Get document details
                $documentId = $_GET['document_id'] ?? '';
                
                if (empty($documentId)) {
                    throw new Exception('document_id parameter is required');
                }
                
                $documents = $db->select('documents', [
                    'id' => 'eq.' . $documentId
                ]);
                
                if (empty($documents)) {
                    throw new Exception('Document not found');
                }
                
                $document = $documents[0];
                
                // Get uploader details
                $userData = $db->select('user_profiles', [
                    'id' => 'eq.' . $document['uploaded_by']
                ]);
                $document['uploader'] = $userData[0] ?? null;
                
                echo json_encode([
                    'success' => true,
                    'document' => $document
                ]);
                
            } else {
                throw new Exception('Invalid action');
            }
            break;
            
        case 'DELETE':
            if ($action === 'delete') {
                // Delete document
                $documentId = $_GET['document_id'] ?? '';
                
                if (empty($documentId)) {
                    throw new Exception('document_id parameter is required');
                }
                
                // Get document
                $documents = $db->select('documents', [
                    'id' => 'eq.' . $documentId
                ]);
                
                if (empty($documents)) {
                    throw new Exception('Document not found');
                }
                
                $document = $documents[0];
                
                // Delete file
                $filePath = __DIR__ . '/../../' . ltrim($document['file_path'], '/');
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                
                // Delete database record
                $db->delete('documents', $documentId);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Document deleted successfully'
                ]);
                
            } else {
                throw new Exception('Invalid action');
            }
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function generateUUID() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
