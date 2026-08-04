<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../../includes/config.php';

/**
 * Upload a file to Supabase Storage
 */
function uploadToSupabaseStorage(string $bucket, string $path, string $tmpFile, string $mimeType): ?string {
    $config = require __DIR__ . '/../../../includes/supabase.php';
    $url = rtrim($config['url'], '/') . "/storage/v1/object/$bucket/$path";
    $fileContent = file_get_contents($tmpFile);
    if ($fileContent === false) return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $fileContent,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $config['service_role_key'],
            'Authorization: Bearer ' . $config['service_role_key'],
            'Content-Type: ' . $mimeType,
            'x-upsert: true',
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode >= 200 && $httpCode < 300) {
        return $config['url'] . "/storage/v1/object/public/$bucket/$path";
    }
    error_log("Supabase Storage upload failed ($httpCode): $response");
    return null;
}

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

$supabaseConfig = require __DIR__ . '/../../../includes/supabase.php';
$supabase = new \App\Lib\SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);

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
        
        $mimeType = mime_content_type($_FILES['document']['tmp_name']) ?: $_FILES['document']['type'];
        $fileName = uniqid() . '_' . basename($_FILES['document']['name']);
        $supabaseUrl = uploadToSupabaseStorage('compliance', 'documents/' . $fileName, $_FILES['document']['tmp_name'], $mimeType);
        
        if ($supabaseUrl) {
            $fileUrl = $supabaseUrl;
            
            $document = $supabase->insert('compliance_docs', [
                'school_id' => $schoolId,
                'doc_type' => $docType,
                'file_url' => $fileUrl,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            if ($document) {
                echo json_encode(['success' => true, 'doc_id' => $document[0]['id'] ?? null]);
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
        
        $docs = $supabase->select('compliance_docs', ['school_id' => 'eq.' . $schoolId, 'order' => 'created_at.desc']);
        
        echo json_encode(['documents' => $docs ?? []]);
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
        
        $result = $supabase->update('compliance_docs', [
            'is_verified' => true,
            'verified_by' => $userId,
            'verified_at' => date('Y-m-d H:i:s')
        ], ['id' => 'eq.' . $docId]);
        
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
