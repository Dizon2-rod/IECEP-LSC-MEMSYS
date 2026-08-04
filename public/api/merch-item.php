<?php
require_once dirname(__DIR__) . '/../bootstrap.php';

error_reporting(0);
ini_set('display_errors', 0);

while (ob_get_level()) ob_end_clean();
ob_start();

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../src/lib/SupabaseClient.php';

$input = $_POST ?: json_decode(file_get_contents('php://input'), true);
if ($input === null || !is_array($input)) {
    $input = $_POST;
}

if (!isset($input['csrf_token'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'CSRF token required']);
    exit;
}

$csrfValid = isset($_SESSION['csrf_token']) && $input['csrf_token'] === $_SESSION['csrf_token'];

if (!$csrfValid) {
    if (!defined('APP_ENV') || APP_ENV !== 'production') {
        error_log("CSRF bypassed in development mode");
    } else {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}

$action = $input['action'] ?? '';

try {
    $supabaseConfig = require INCLUDES_PATH . 'supabase.php';
    $supabase = new SupabaseClient($supabaseConfig['url'], $supabaseConfig['service_role_key'] ?? $supabaseConfig['anon_key']);
    if (!empty($supabaseConfig['service_role_key'])) {
        $supabase->setServiceRoleKey($supabaseConfig['service_role_key']);
    }

    if ($action === 'toggle_status') {
        $itemId = $input['id'] ?? '';
        $isActive = !empty($input['is_active']);

        if (empty($itemId)) {
            echo json_encode(['success' => false, 'message' => 'Item ID is required']);
            exit;
        }

        $supabase->update('merch_items', [
            'is_active' => $isActive,
            'updated_at' => date('Y-m-d\TH:i:s\Z'),
        ], $itemId);

        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
        exit;
    }

    if ($action === 'add' || $action === 'edit') {
        $name = trim((string)($input['name'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $price = (float)($input['price'] ?? 0);
        $stock = (int)($input['stock'] ?? 0);
        $imageUrl = trim((string)($input['image_url'] ?? ''));
        $itemId = $input['id'] ?? '';

        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Item name is required']);
            exit;
        }

        $payload = [
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'stock' => $stock,
        ];

        if ($imageUrl !== '') {
            $payload['image_url'] = $imageUrl;
        }

        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadedFile = $_FILES['image'];
            if ($uploadedFile['error'] === UPLOAD_ERR_OK && $uploadedFile['size'] > 0) {
                $bucket = 'public';
                $pathDir = 'merchandise';
                $extension = strtolower(pathinfo($uploadedFile['name'] ?? 'image.jpg', PATHINFO_EXTENSION));
                $filename = uniqid('merch-', true) . '.' . $extension;
                $objectPath = $pathDir . '/' . $filename;

                $uploadUrl = rtrim($supabaseConfig['url'], '/') . '/storage/v1/object/' . $bucket . '/' . $objectPath;

                $fileContents = file_get_contents($uploadedFile['tmp_name']);
                $headers = [
                    'Authorization: Bearer ' . $supabaseConfig['service_role_key'],
                    'apikey: ' . $supabaseConfig['service_role_key'],
                    'Content-Type: application/octet-stream',
                    'x-upsert: true',
                ];

                $ch = curl_init($uploadUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContents);
                $uploadResp = curl_exec($ch);
                $uploadCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($uploadCode >= 200 && $uploadCode < 400) {
                    $payload['image_url'] = rtrim($supabaseConfig['url'], '/') . '/storage/v1/object/public/' . $bucket . '/' . $objectPath;
                }
            }
        }

        if ($action === 'add') {
            $payload['created_at'] = date('Y-m-d\TH:i:s\Z');
            $payload['updated_at'] = date('Y-m-d\TH:i:s\Z');
            $payload['is_active'] = true;

            $result = $supabase->insert('merch_items', $payload);
            $newId = $result[0]['id'] ?? null;

            $_SESSION['merch_flash'] = ['success' => 'Item "' . $name . '" added successfully.'];
            echo json_encode(['success' => true, 'message' => 'Item added successfully', 'id' => $newId]);
        } else {
            $payload['updated_at'] = date('Y-m-d\TH:i:s\Z');
            $supabase->update('merch_items', $payload, $itemId);

            $_SESSION['merch_flash'] = ['success' => 'Item "' . $name . '" updated successfully.'];
            echo json_encode(['success' => true, 'message' => 'Item updated successfully', 'id' => $itemId]);
        }
        exit;
    }

    // Unknown action
    echo json_encode(['success' => false, 'message' => 'Unknown action: ' . $action]);
    exit;

} catch (\Exception $e) {
    error_log("Merch item API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
