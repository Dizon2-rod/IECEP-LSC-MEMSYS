<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../lib/supabase.php';
require_once __DIR__ . '/../lib/EmailService.php';
require_once __DIR__ . '/../lib/pdf.php';
require_once __DIR__ . '/../lib/qrcode.php';
require_once __DIR__ . '/../lib/digital_id.php';
require_once __DIR__ . '/../middleware/auth.php';

use App\Lib\Supabase;
use App\Lib\EmailService;
use App\Lib\PdfService;
use App\Lib\QrCodeService;
use App\Lib\DigitalIdService;
use App\Middleware\AuthMiddleware;

$sb = new Supabase();
$auth = new AuthMiddleware();
$emailSvc = new EmailService();
$pdfSvc = new PdfService();
$qrSvc = new QrCodeService();
$digitalId = new DigitalIdService();

$config = include __DIR__ . '/../config/config.php';
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'pending-member-payments':
            if ($method !== 'GET') { http_response_code(405); exit; }
            $user = $auth->requireRole(['eb_treasurer']);

            $result = $sb->from('pending_members')
                ->select('*, member_upload_batches!inner(institution_id, institutions(name)), members!inner(institution_id)')
                ->eq('status', 'approved_payment_pending')
                ->get(true);

            echo json_encode(['success' => true, 'data' => $result['data'] ?? []]);
            break;

        case 'mark-members-paid':
            if ($method !== 'POST') { http_response_code(405); exit; }
            $user = $auth->requireRole(['eb_treasurer']);
            $data = json_decode(file_get_contents('php://input'), true);
            $pendingMemberIds = $data['pending_member_ids'] ?? [];
            $markAffiliationFee = $data['affiliation_fee_institution_id'] ?? null;

            if (empty($pendingMemberIds) && empty($markAffiliationFee)) {
                http_response_code(400);
                echo json_encode(['error' => 'No members selected']);
                exit;
            }

            $results = [];
            $errors = [];

            // Process affiliation fee if provided
            if ($markAffiliationFee) {
                $receiptId = 'RCP-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
                $feeAmount = $config['fee_affiliation'] / 100; // Convert cents to PHP

                // Insert transaction
                $sb->from('transactions')->insert([
                    'receipt_id' => $receiptId,
                    'institution_id' => $markAffiliationFee,
                    'amount' => $feeAmount,
                    'description' => 'Institutional Affiliation Fee',
                    'status' => 'paid',
                    'paid_at' => date('Y-m-d\TH:i:s\Z'),
                ], true);

                // Update institution
                $sb->from('institutions')
                    ->eq('id', $markAffiliationFee)
                    ->update(['affiliation_fee_paid' => true], true);

                // Generate receipt PDF
                $instResult = $sb->from('institutions')
                    ->select('name')
                    ->eq('id', $markAffiliationFee)
                    ->get(true);
                $instName = $instResult['data'][0]['name'] ?? 'Institution';

                $qrPath = sys_get_temp_dir() . '/receipt_qr_' . $receiptId . '.png';
                $qrSvc->generateAndSave($config['app_url'] . "/verify-payment.php?receipt_id=$receiptId", $qrPath, 150);

                $pdfContent = $pdfSvc->generateReceipt(
                    $receiptId,
                    $instName,
                    $feeAmount,
                    date('F j, Y'),
                    null,
                    $qrPath
                );

                $receiptUpload = $sb->storage()->uploadBinary(
                    'receipts',
                    "{$receiptId}.pdf",
                    $pdfContent,
                    'application/pdf'
                );

                $receiptUrl = $sb->storage()->getPublicUrl('receipts', "{$receiptId}.pdf");

                $sb->from('transactions')
                    ->eq('receipt_id', $receiptId)
                    ->update(['receipt_url' => $receiptUrl], true);

                @unlink($qrPath);

                $results[] = ['type' => 'affiliation_fee', 'receipt_id' => $receiptId];
            }

            // Process each member payment
            foreach ($pendingMemberIds as $pmId) {
                // Get pending member details
                $pmResult = $sb->from('pending_members')
                    ->select('*, member_upload_batches!inner(institution_id)')
                    ->eq('id', $pmId)
                    ->get(true);

                if ($pmResult['error'] || empty($pmResult['data'])) {
                    $errors[] = "Pending member $pmId not found";
                    continue;
                }

                $pm = $pmResult['data'][0];
                $institutionId = $pm['member_upload_batches']['institution_id'];

                // Determine fee amount
                $feeCents = match($pm['member_type']) {
                    'new' => $config['fee_new_member'],
                    'returning' => $config['fee_returning_member'],
                    'honorary' => $config['fee_honorary_member'],
                    default => $config['fee_new_member'],
                };
                $feeAmount = $feeCents / 100;

                // Generate receipt ID
                $receiptId = 'RCP-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));

                // Create Supabase Auth user
                $password = 'IECEP@2025' . bin2hex(random_bytes(4));
                $authResult = $sb->auth()->adminCreateUser($pm['email'], $password, [
                    'user_metadata' => ['full_name' => $pm['full_name']],
                ]);

                if ($authResult['error']) {
                    $errors[] = "Failed to create auth user for {$pm['email']}: " . $authResult['message'];
                    continue;
                }

                $newUserId = $authResult['data']['id'] ?? '';

                // Create user profile
                $sb->from('user_profiles')->insert([
                    'user_id' => $newUserId,
                    'role' => 'member',
                    'full_name' => $pm['full_name'],
                    'force_password_change' => true,
                ], true);

                // Create member record
                $memberResult = $sb->from('members')->insert([
                    'institution_id' => $institutionId,
                    'user_id' => $newUserId,
                    'full_name' => $pm['full_name'],
                    'email' => $pm['email'],
                    'member_type' => $pm['member_type'],
                    'payment_status' => true,
                    'year_level' => $pm['year_level'],
                ], true);

                $newMemberId = $memberResult['data'][0]['id'] ?? '';

                // Generate digital ID
                $instResult = $sb->from('institutions')
                    ->select('name')
                    ->eq('id', $institutionId)
                    ->get(true);
                $instName = $instResult['data'][0]['name'] ?? 'Institution';

                $verifyUrl = $config['app_url'] . "/verify-member.php?id=" . $newMemberId;
                $qrPath = sys_get_temp_dir() . '/qr_' . $newMemberId . '.png';
                $qrSvc->generateAndSave($verifyUrl, $qrPath, 200);

                $idPath = $digitalId->generate($pm['full_name'], $instName, $pm['member_type'], $newMemberId, $qrPath);

                if ($idPath) {
                    $sb->storage()->uploadBinary('member_ids', "{$newMemberId}.png", file_get_contents($idPath), 'image/png');
                    $digitalIdUrl = $sb->storage()->getPublicUrl('member_ids', "{$newMemberId}.png");

                    $sb->from('members')
                        ->eq('id', $newMemberId)
                        ->update(['digital_id_url' => $digitalIdUrl, 'qr_code' => $verifyUrl], true);
                    @unlink($idPath);
                }
                @unlink($qrPath);

                // Generate receipt PDF
                $receiptQrPath = sys_get_temp_dir() . '/receipt_qr_' . $receiptId . '.png';
                $qrSvc->generateAndSave($config['app_url'] . "/verify-payment.php?receipt_id=$receiptId", $receiptQrPath, 150);

                $pdfContent = $pdfSvc->generateReceipt(
                    $receiptId,
                    $pm['full_name'],
                    $feeAmount,
                    date('F j, Y'),
                    null,
                    $receiptQrPath
                );

                $sb->storage()->uploadBinary('receipts', "{$receiptId}.pdf", $pdfContent, 'application/pdf');
                $receiptUrl = $sb->storage()->getPublicUrl('receipts', "{$receiptId}.pdf");

                @unlink($receiptQrPath);

                // Insert transaction
                $sb->from('transactions')->insert([
                    'receipt_id' => $receiptId,
                    'institution_id' => $institutionId,
                    'member_id' => $newMemberId,
                    'amount' => $feeAmount,
                    'description' => "Membership Fee - {$pm['member_type']}",
                    'status' => 'paid',
                    'receipt_url' => $receiptUrl,
                    'paid_at' => date('Y-m-d\TH:i:s\Z'),
                ], true);

                // Send credentials email
                $emailSvc->sendCredentials($pm['email'], $pm['email'], $password);

                // Update pending member status
                $sb->from('pending_members')
                    ->eq('id', $pmId)
                    ->update(['status' => 'paid_account_created'], true);

                $results[] = [
                    'pending_member_id' => $pmId,
                    'member_id' => $newMemberId,
                    'receipt_id' => $receiptId,
                    'email' => $pm['email'],
                ];
            }

            // Check if all members in the batch are done
            if (!empty($pendingMemberIds)) {
                $firstPm = $sb->from('pending_members')
                    ->select('batch_id')
                    ->eq('id', $pendingMemberIds[0])
                    ->get(true);

                if (!$firstPm['error'] && !empty($firstPm['data'])) {
                    $batchId = $firstPm['data'][0]['batch_id'];

                    $remaining = $sb->from('pending_members')
                        ->select('id')
                        ->eq('batch_id', $batchId)
                        ->eq('status', 'approved_payment_pending')
                        ->get(true);

                    if (empty($remaining['data'])) {
                        $sb->from('member_upload_batches')
                            ->eq('id', $batchId)
                            ->update(['status' => 'fully_paid'], true);
                    }
                }
            }

            echo json_encode(['success' => true, 'results' => $results, 'errors' => $errors]);
            break;

        case 'transactions':
            if ($method !== 'GET') { http_response_code(405); exit; }
            $user = $auth->requireRole(['eb_treasurer', 'eb_auditor']);

            $result = $sb->from('transactions')
                ->select('*, institutions(name), members(full_name, email)')
                ->order('paid_at', false)
                ->get(true);

            echo json_encode(['success' => true, 'data' => $result['data'] ?? []]);
            break;

        case 'report':
            if ($method !== 'POST') { http_response_code(405); exit; }
            $user = $auth->requireRole(['eb_treasurer']);

            $result = $sb->from('transactions')
                ->select('*, institutions(name), members(full_name, email)')
                ->eq('status', 'paid')
                ->order('paid_at', false)
                ->get(true);

            $transactions = $result['data'] ?? [];
            $totalAmount = array_sum(array_column($transactions, 'amount'));

            $html = "<table style='width:100%;border-collapse:collapse'>
                <tr style='background:#0A2F6C;color:#fff'>
                    <th style='padding:8px;text-align:left'>Receipt ID</th>
                    <th style='padding:8px;text-align:left'>Payer</th>
                    <th style='padding:8px;text-align:left'>Institution</th>
                    <th style='padding:8px;text-align:left'>Amount</th>
                    <th style='padding:8px;text-align:left'>Date</th>
                    <th style='padding:8px;text-align:left'>Status</th>
                </tr>";

            foreach ($transactions as $tx) {
                $payer = $tx['members']['full_name'] ?? ($tx['institutions']['name'] ?? 'N/A');
                $inst = $tx['institutions']['name'] ?? 'N/A';
                $html .= "<tr style='border-bottom:1px solid #eee'>
                    <td style='padding:8px'>{$tx['receipt_id']}</td>
                    <td style='padding:8px'>{$payer}</td>
                    <td style='padding:8px'>{$inst}</td>
                    <td style='padding:8px'>₱" . number_format($tx['amount'], 2) . "</td>
                    <td style='padding:8px'>{$tx['paid_at']}</td>
                    <td style='padding:8px'><span style='color:#28a745'>Paid</span></td>
                </tr>";
            }

            $html .= "</table><br><strong>Total: ₱" . number_format($totalAmount, 2) . "</strong>";

            $pdfSvc = new PdfService();
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml("<h1 style='color:#0A2F6C'>IECEP-LSC Financial Report</h1><p>Generated: " . date('F j, Y H:i') . "</p>" . $html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="financial_report_' . date('YmdHis') . '.pdf"');
            echo $dompdf->output();
            exit;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
