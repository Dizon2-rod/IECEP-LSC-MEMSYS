<?php
require_once __DIR__ . '/../bootstrap.php';

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
require_once __DIR__ . '/../../src/lib/BlockchainService.php';
require_once __DIR__ . '/../../src/lib/EmailService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = $_POST ?: json_decode(file_get_contents('php://input'), true);
if ($input === null || !is_array($input)) {
    $input = $_POST;
}

if (!isset($input['csrf_token'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request: CSRF token required']);
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

$required = ['merch_item_id', 'quantity', 'buyer_name', 'buyer_email'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
        exit;
    }
}

$merchItemId = $input['merch_item_id'];
$quantity = (int)$input['quantity'];
$buyerName = trim((string)$input['buyer_name']);
$buyerEmail = trim((string)$input['buyer_email']);
$notes = trim((string)($input['notes'] ?? ''));

if ($quantity < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Quantity must be at least 1']);
    exit;
}

if (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

if (empty($buyerName)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Buyer name is required']);
    exit;
}

try {
    $supabaseConfig = require INCLUDES_PATH . 'supabase.php';
    $supabase = new SupabaseClient($supabaseConfig['url'], $supabaseConfig['service_role_key'] ?? $supabaseConfig['anon_key']);

    if (!empty($supabaseConfig['service_role_key'])) {
        $supabase->setServiceRoleKey($supabaseConfig['service_role_key']);
    }

    // Step 1: Fetch the merch item
    $items = $supabase->select('merch_items', ["id" => "eq.$merchItemId"]);
    if (!is_array($items) || empty($items[0])) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Merch item not found']);
        exit;
    }

    $item = $items[0];
    $price = (float)($item['price'] ?? 0);
    $stock = (int)($item['stock'] ?? 0);
    $itemName = $item['name'] ?? 'Unknown Item';

    // Step 2: Check stock
    if ($stock < $quantity) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Not enough stock. Available: $stock, requested: $quantity"]);
        exit;
    }

    $totalAmount = $price * $quantity;

    // Step 3: Generate receipt number
    $receiptNumber = 'RCP-' . date('Y') . '-' . str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);

    // Step 4: Create the merch order
    $orderItems = json_encode([
        [
            'merch_item_id' => $merchItemId,
            'name' => $itemName,
            'quantity' => $quantity,
            'price_each' => $price,
        ]
    ], JSON_UNESCAPED_SLASHES);

    // Get member_id if logged in
    $memberId = $_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? null;
    $institutionId = $_SESSION['user']['institution_id'] ?? $_SESSION['institution_id'] ?? null;

    // Create merch_orders row
    $orderResult = $supabase->insert('merch_orders', [
        'member_id' => $memberId,
        'buyer_name' => $buyerName,
        'buyer_email' => $buyerEmail,
        'items' => $orderItems,
        'total_amount' => round($totalAmount, 2),
        'status' => 'pending',
        'notes' => $notes,
        'created_at' => date('Y-m-d\TH:i:s\Z'),
        'updated_at' => date('Y-m-d\TH:i:s\Z'),
    ]);

    $orderId = $orderResult[0]['id'] ?? null;

    if (!$orderId) {
        throw new Exception('Failed to create merch order');
    }

    // Step 5: Create a transactions row
    $transactionResult = $supabase->insert('transactions', [
        'member_id' => $memberId,
        'institution_id' => $institutionId,
        'amount' => round($totalAmount, 2),
        'currency' => 'PHP',
        'type' => 'merchandise',
        'transaction_type' => 'payment',
        'receipt_number' => $receiptNumber,
        'payment_method' => 'online_payment',
        'status' => 'pending',
        'notes' => 'Merch order: ' . $itemName,
        'metadata' => json_encode([
            'merch_order_id' => $orderId,
            'item_name' => $itemName,
            'quantity' => $quantity,
            'price_each' => $price,
        ], JSON_UNESCAPED_SLASHES),
        'created_at' => date('Y-m-d\TH:i:s\Z'),
        'updated_at' => date('Y-m-d\TH:i:s\Z'),
    ]);

    $transactionId = $transactionResult[0]['id'] ?? null;

    // Step 6: Update merch_orders with transaction_id
    if ($transactionId) {
        $supabase->update('merch_orders', [
            'transaction_id' => $transactionId,
            'updated_at' => date('Y-m-d\TH:i:s\Z'),
        ], $orderId);
    }

    // Step 7: Record on blockchain
    try {
        $blockchain = new \App\Lib\BlockchainService($supabase);
        $blockchain->record(
            'payment',
            $orderId,
            [
                'record_type' => 'merch',
                'order_id' => $orderId,
                'item_name' => $itemName,
                'quantity' => $quantity,
                'amount' => round($totalAmount, 2),
                'buyer_email' => $buyerEmail,
                'receipt_number' => $receiptNumber,
                'status' => 'paid',
            ],
            $institutionId
        );
    } catch (\Throwable $e) {
        error_log("Blockchain recording failed (non-fatal): " . $e->getMessage());
    }

    // Step 8: Decrement stock
    $newStock = $stock - $quantity;
    $supabase->update('merch_items', [
        'stock' => $newStock,
        'updated_at' => date('Y-m-d\TH:i:s\Z'),
    ], $merchItemId);

    // Step 9: Send confirmation email to buyer
    try {
        $email = new \App\Lib\EmailService();
        $email->sendNotification(
            $buyerEmail,
            'IECEP-LSC Merchandise Order Confirmation',
            "
                <h2 style='color:#0B1D4A'>Order Confirmation</h2>
                <p>Thank you for your order, <strong>$buyerName</strong>!</p>
                <div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin:16px 0'>
                    <h3 style='color:#0B1D4A'>Order Details</h3>
                    <p><strong>Receipt Number:</strong> $receiptNumber</p>
                    <p><strong>Item:</strong> $itemName</p>
                    <p><strong>Quantity:</strong> $quantity</p>
                    <p><strong>Unit Price:</strong> ₱" . number_format($price, 2) . "</p>
                    <p><strong>Total Amount:</strong> <span style='color:#D4AF37;font-weight:bold'>₱" . number_format($totalAmount, 2) . "</span></p>
                </div>
                <p>Your order has been received and is being processed. You will be notified when your order ships.</p>
                <p style='color:#6b7280;font-size:0.9rem'>This is a pending order. Payment will be processed upon delivery confirmation.</p>
            "
        );
    } catch (\Throwable $e) {
        error_log("Buyer confirmation email failed (non-fatal): " . $e->getMessage());
    }

    // Step 10: Store order details in session for success page
    $_SESSION['merch_order_success'] = [
        'order_id' => $orderId,
        'receipt_number' => $receiptNumber,
        'item_name' => $itemName,
        'quantity' => $quantity,
        'unit_price' => $price,
        'total_amount' => round($totalAmount, 2),
    ];

    // Clear the order item from session
    unset($_SESSION['merch_item_cache']);

    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully!',
        'order_id' => $orderId,
        'transaction_id' => $transactionId,
        'receipt_number' => $receiptNumber,
        'total_amount' => round($totalAmount, 2),
    ]);

} catch (\Exception $e) {
    error_log("Order merch error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your order. Please try again.',
    ]);
}
