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
$userRole = $_SESSION['role'] ?? null;

if (!$userId || $userRole !== 'eb_treasurer') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$supabaseConfig = require __DIR__ . '/../../../includes/supabase.php';
$supabase = new \App\Lib\SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);

switch ($action) {
    case 'get_pending_payments':
        $payments = $supabase->select('financial_records', [
            'payment_status' => 'eq.Pending',
            'order' => 'created_at.desc',
            'select' => '*,school_profiles(school_name)'
        ]);
        
        echo json_encode(['payments' => $payments ?? []]);
        break;
        
    case 'verify_payment':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }
        
        $recordId = $_POST['record_id'] ?? null;
        
        if (!$recordId) {
            http_response_code(400);
            echo json_encode(['error' => 'Record ID required']);
            exit;
        }
        
        $receiptUrl = null;
        if (isset($_FILES['official_receipt'])) {
            $mimeType = mime_content_type($_FILES['official_receipt']['tmp_name']) ?: $_FILES['official_receipt']['type'];
            $fileName = uniqid() . '_' . basename($_FILES['official_receipt']['name']);
            $receiptUrl = uploadToSupabaseStorage('receipts', 'official/' . $fileName, $_FILES['official_receipt']['tmp_name'], $mimeType);
        }
        
        $result = $supabase->update('financial_records', [
            'payment_status' => 'Verified',
            'official_receipt_url' => $receiptUrl
        ], ['id' => 'eq.' . $recordId]);
        
        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to verify payment']);
        }
        break;
        
    case 'get_financial_summary':
        $allRecords = $supabase->select('financial_records');
        
        $totalTransactions = count($allRecords ?? []);
        $totalCollected = 0;
        $pendingAmount = 0;
        
        foreach ($allRecords as $record) {
            if (($record['payment_status'] ?? '') === 'Verified') {
                $totalCollected += (float)($record['amount'] ?? 0);
            } elseif (($record['payment_status'] ?? '') === 'Pending') {
                $pendingAmount += (float)($record['amount'] ?? 0);
            }
        }
        
        echo json_encode([
            'total_transactions' => $totalTransactions,
            'total_collected' => $totalCollected,
            'pending_amount' => $pendingAmount
        ]);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
