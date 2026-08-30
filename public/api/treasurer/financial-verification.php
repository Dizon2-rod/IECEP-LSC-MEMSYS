<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../../includes/config.php';

if (!function_exists('uploadToSupabaseStorage')) {
    function uploadToSupabaseStorage(string $bucket, string $path, string $tmpFile, string $mimeType): ?string {
        $supabaseClient = getSupabaseClient();
        if ($supabaseClient && method_exists($supabaseClient, 'uploadFile')) {
            $url = $supabaseClient->uploadFile($bucket, $path, $tmpFile, $mimeType);
            if ($url) return $url;
        }
        return null;
    }
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
