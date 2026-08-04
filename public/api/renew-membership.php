<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../autoload.php';

/**
 * Upload a file to Supabase Storage
 */
function uploadToSupabaseStorage(string $bucket, string $path, string $tmpFile, string $mimeType): ?string {
    $config = require __DIR__ . '/../../includes/supabase.php';
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

require_role(['member', 'admin', 'super_admin', 'school_officer']);
require_csrf();

$memberId = $_POST['member_id'] ?? $_SESSION['user']['member_id'] ?? '';
$paymentProof = $_FILES['payment_proof'] ?? null;

if (empty($memberId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'member_id required']);
    exit;
}

$supabaseConfig = require __DIR__ . '/../../includes/supabase.php';
$supabase = new \App\Lib\SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);
$blockchain = new \App\Lib\BlockchainService($supabase);

try {
    // Get member details
    $members = $supabase->select('members', ['id' => 'eq.' . $memberId]);
    
    if (empty($members)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Member not found']);
        exit;
    }

    $member = $members[0];

    // Calculate renewal fee (could be from fee_brackets table)
    $renewalFee = 500.00; // Default fee

    // Handle payment proof upload if provided
    $paymentProofPath = null;
    if ($paymentProof && $paymentProof['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $paymentProof['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid file type']);
            exit;
        }

        if ($paymentProof['size'] > 5 * 1024 * 1024) { // 5MB max
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'File too large']);
            exit;
        }

        $extension = pathinfo($paymentProof['name'], PATHINFO_EXTENSION);
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $paymentProofPath = uploadToSupabaseStorage('payments', 'renewals/' . $filename, $paymentProof['tmp_name'], $mimeType);
    }

    // Create transaction record
    $transaction = $supabase->insert('transactions', [
        'user_id' => $member['user_id'] ?? null,
        'member_id' => $memberId,
        'amount' => $renewalFee,
        'transaction_type' => 'membership_renewal',
        'status' => $paymentProofPath ? 'pending' : 'unpaid',
        'payment_proof' => $paymentProofPath,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    $transactionId = is_array($transaction) && isset($transaction[0]['id']) ? $transaction[0]['id'] : ($transaction['id'] ?? null);

    // If payment proof provided, extend membership (pending verification)
    if ($paymentProofPath) {
        $currentExpiry = $member['membership_expiry'] ?? date('Y-m-d');
        $newExpiry = date('Y-m-d', strtotime($currentExpiry . ' +1 year'));

        $supabase->update('members', [
            'membership_expiry' => $newExpiry,
            'status' => 'active',
            'updated_at' => date('Y-m-d H:i:s')
        ], ['id' => $memberId]);

        // Record in blockchain
        $blockchain->record('membership_change', $memberId, [
            'action' => 'renewal',
            'previous_expiry' => $currentExpiry,
            'new_expiry' => $newExpiry,
            'transaction_id' => $transactionId
        ]);

        // Send notification
        $supabase->insert('notifications', [
            'user_id' => $member['user_id'],
            'title' => 'Membership Renewal Submitted',
            'message' => 'Your membership renewal is pending verification. New expiry: ' . $newExpiry,
            'type' => 'info',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    echo json_encode([
        'success' => true,
        'transaction_id' => $transactionId,
        'renewal_fee' => $renewalFee,
        'status' => $paymentProofPath ? 'pending' : 'unpaid',
        'new_expiry' => $newExpiry ?? null
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
