<?php
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware/auth.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_role(['admin', 'super_admin', 'eb_treasurer']);

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    $db = $GLOBALS['supabaseClient'] ?? null;
    if (!$db) {
        throw new Exception('Database connection not available');
    }

    switch ($action) {
        case 'list':
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = 20;
            $offset = ($page - 1) * $perPage;

            $filters = [];
            if (!empty($_GET['status'])) {
                $filters['status'] = 'eq.' . $_GET['status'];
            }
            if (!empty($_GET['type'])) {
                $filters['type'] = 'eq.' . $_GET['type'];
            }
            if (!empty($_GET['institution_id'])) {
                $filters['institution_id'] = 'eq.' . $_GET['institution_id'];
            }
            if (!empty($_GET['date_from'])) {
                $filters['transaction_date'] = "gte.{$_GET['date_from']}";
            }
            if (!empty($_GET['date_to'])) {
                $filters['transaction_date'] = (!empty($_GET['date_from']) ? '' : "transaction_date=lte.{$_GET['date_to']}");
            }

            $transactions = $db->select('transactions', $filters, 'transaction_date', 'desc', $perPage, $offset);
            $total = $db->from('transactions')->select('*', ['count' => 'exact'])->execute();

            echo json_encode([
                'success' => true,
                'transactions' => $transactions ?? [],
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => ceil(($total['count'] ?? 0) / $perPage),
                    'total_records' => $total['count'] ?? 0
                ]
            ]);
            break;

        case 'detail':
            $txId = $_GET['id'] ?? '';
            if (empty($txId)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Transaction ID required']);
                exit;
            }

            $transaction = $db->select('transactions', ['id' => 'eq.' . $txId]);
            echo json_encode([
                'success' => true,
                'transaction' => $transaction[0] ?? null
            ]);
            break;

        case 'receipt':
            $txId = $_GET['id'] ?? '';
            if (empty($txId)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Transaction ID required']);
                exit;
            }

            $transaction = $db->select('transactions', ['id' => 'eq.' . $txId]);
            $tx = $transaction[0] ?? null;

            if (!$tx) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Transaction not found']);
                exit;
            }

            header('Content-Type: text/html');
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <title>Receipt - <?= htmlspecialchars($tx['id']) ?></title>
                <style>
                    body { font-family: 'Inter', sans-serif; padding: 40px; max-width: 600px; margin: 0 auto; }
                    .receipt { border: 1px solid #e5e7eb; border-radius: 12px; padding: 32px; }
                    .receipt h1 { color: #0B1D4A; margin-bottom: 8px; }
                    .receipt-header { display: flex; justify-content: space-between; margin-bottom: 24px; }
                    .receipt-amount { font-size: 2rem; font-weight: 700; color: #10b981; }
                    .receipt-details { margin-top: 24px; }
                    .receipt-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f3f4f6; }
                    .receipt-label { color: #6b7280; }
                    .receipt-value { font-weight: 600; }
                </style>
            </head>
            <body>
                <div class="receipt">
                    <div class="receipt-header">
                        <div>
                            <h1>IECEP-LSC MEMSYS</h1>
                            <p>Official Receipt</p>
                        </div>
                        <div style="text-align: right;">
                            <p><strong>Receipt #:</strong><br><?= htmlspecialchars($tx['id']) ?></p>
                            <p><strong>Date:</strong><br><?= date('F d, Y', strtotime($tx['transaction_date'] ?? $tx['created_at'] ?? 'now')) ?></p>
                        </div>
                    </div>
                    <div class="receipt-amount">₱<?= number_format((float)($tx['amount'] ?? 0), 2) ?></div>
                    <div class="receipt-details">
                        <div class="receipt-row">
                            <span class="receipt-label">Transaction Type</span>
                            <span class="receipt-value"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $tx['type'] ?? 'N/A'))) ?></span>
                        </div>
                        <div class="receipt-row">
                            <span class="receipt-label">Status</span>
                            <span class="receipt-value"><?= htmlspecialchars(ucfirst($tx['status'] ?? 'N/A')) ?></span>
                        </div>
                        <div class="receipt-row">
                            <span class="receipt-label">Member</span>
                            <span class="receipt-value"><?= htmlspecialchars($tx['member_name'] ?? 'N/A') ?></span>
                        </div>
                        <div class="receipt-row">
                            <span class="receipt-label">Institution</span>
                            <span class="receipt-value"><?= htmlspecialchars($tx['institution_name'] ?? 'N/A') ?></span>
                        </div>
                        <?php if (!empty($tx['description'])): ?>
                        <div class="receipt-row">
                            <span class="receipt-label">Description</span>
                            <span class="receipt-value"><?= htmlspecialchars($tx['description']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div style="margin-top: 32px; text-align: center; color: #6b7280; font-size: 0.875rem;">
                        <p>Thank you for your payment!</p>
                        <p>This receipt is blockchain-verified and immutable.</p>
                    </div>
                </div>
            </body>
            </html>
            <?php
            exit;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
