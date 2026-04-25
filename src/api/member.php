<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
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
        case 'profile':
            if ($method === 'GET') {
                $user = $auth->requireAuth();

                $memberResult = $sb->from('members')
                    ->select('*, institutions(id, name, compliance_status)')
                    ->eq('user_id', $user['user_id'])
                    ->get(true, $user['jwt']);

                if ($memberResult['error'] || empty($memberResult['data'])) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Member record not found']);
                    exit;
                }

                echo json_encode(['success' => true, 'data' => $memberResult['data'][0]]);
            } elseif ($method === 'PUT') {
                $user = $auth->requireAuth();
                $data = json_decode(file_get_contents('php://input'), true);

                $allowedFields = ['full_name', 'year_level'];
                $updateData = array_intersect_key($data, array_flip($allowedFields));

                if (!empty($updateData)) {
                    $sb->from('members')
                        ->eq('user_id', $user['user_id'])
                        ->update($updateData, true);

                    if (isset($updateData['full_name'])) {
                        $sb->from('user_profiles')
                            ->eq('user_id', $user['user_id'])
                            ->update(['full_name' => $updateData['full_name']], true);
                    }
                }

                echo json_encode(['success' => true, 'message' => 'Profile updated']);
            }
            break;

        case 'digital-id':
            if ($method !== 'GET') { http_response_code(405); exit; }
            $user = $auth->requireAuth();

            $memberResult = $sb->from('members')
                ->select('id, digital_id_url, qr_code')
                ->eq('user_id', $user['user_id'])
                ->get(true, $user['jwt']);

            if ($memberResult['error'] || empty($memberResult['data'])) {
                http_response_code(404);
                echo json_encode(['error' => 'Member not found']);
                exit;
            }

            echo json_encode(['success' => true, 'data' => $memberResult['data'][0]]);
            break;

        case 'fee-status':
            if ($method !== 'GET') { http_response_code(405); exit; }
            $user = $auth->requireAuth();

            $memberResult = $sb->from('members')
                ->select('id, payment_status, email, member_type')
                ->eq('user_id', $user['user_id'])
                ->get(true, $user['jwt']);

            $memberId = $memberResult['data'][0]['id'] ?? '';

            $transaction = $sb->from('transactions')
                ->select('receipt_id, amount, status, blockchain_tx_hash, receipt_url, paid_at')
                ->eq('member_id', $memberId)
                ->eq('status', 'paid')
                ->get(true);

            echo json_encode([
                'success' => true,
                'payment_status' => $memberResult['data'][0]['payment_status'] ?? false,
                'transaction' => $transaction['data'][0] ?? null,
            ]);
            break;

        case 'announcements':
            if ($method !== 'GET') { http_response_code(405); exit; }
            $user = $auth->requireAuth();

            $announcements = $sb->from('announcements')
                ->select('*, read_receipts(user_id, read_at)')
                ->order('sent_at', false)
                ->limit(20)
                ->get(true, $user['jwt']);

            echo json_encode(['success' => true, 'data' => $announcements['data'] ?? []]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
