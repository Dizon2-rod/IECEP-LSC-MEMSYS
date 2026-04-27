<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../lib/supabase.php';
require_once __DIR__ . '/../middleware/auth.php';

use App\Lib\Supabase;
use App\Middleware\AuthMiddleware;

$sb = new Supabase();
$auth = new AuthMiddleware();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'verify-transaction':
            if ($method !== 'GET') { http_response_code(405); exit; }
            $user = $auth->requireRole(['eb_auditor']);
            $receiptId = $_GET['receipt_id'] ?? '';

            if (empty($receiptId)) {
                http_response_code(400);
                echo json_encode(['error' => 'Receipt ID required']);
                exit;
            }

            // Transaction verification now database-only
            $result = $sb->from('transactions')
                ->select('*')
                ->eq('receipt_id', $receiptId)
                ->get(true);

            if ($result['error'] || empty($result['data'])) {
                echo json_encode(['success' => false, 'error' => 'Transaction not found']);
            } else {
                echo json_encode(['success' => true, 'transaction' => $result['data'][0]]);
            }
            break;

        case 'flag-discrepancy':
            if ($method !== 'POST') { http_response_code(405); exit; }
            $user = $auth->requireRole(['eb_auditor']);
            $data = json_decode(file_get_contents('php://input'), true);
            $transactionId = $data['transaction_id'] ?? '';
            $reason = $data['reason'] ?? '';

            if (empty($transactionId) || empty($reason)) {
                http_response_code(400);
                echo json_encode(['error' => 'Transaction ID and reason required']);
                exit;
            }

            $sb->from('audit_logs')->insert([
                'user_id' => $user['user_id'],
                'action' => 'flag_discrepancy',
                'details' => [
                    'transaction_id' => $transactionId,
                    'reason' => $reason,
                    'flagged_by' => $user['profile']['full_name'] ?? 'Auditor',
                ],
            ], true);

            echo json_encode(['success' => true, 'message' => 'Discrepancy flagged.']);
            break;

        case 'audit-logs':
            if ($method !== 'GET') { http_response_code(405); exit; }
            $user = $auth->requireRole(['eb_auditor']);

            $result = $sb->from('audit_logs')
                ->select('*')
                ->order('created_at', false)
                ->limit(100)
                ->get(true);

            echo json_encode(['success' => true, 'data' => $result['data'] ?? []]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
