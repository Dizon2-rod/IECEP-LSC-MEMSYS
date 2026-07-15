<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../autoload.php';

header('Content-Type: application/json');

require_role(['admin', 'super_admin', 'eb_treasurer']);
require_csrf();

/**
 * Generate Affiliation Receipt HTML
 * 
 * Creates a formatted HTML receipt for affiliation fee payments
 * with fee breakdown and simulation disclaimer.
 * 
 * @param array $data Receipt data
 * @return string HTML content
 */
function generateAffiliationReceiptHTML(array $data): string {
    $receiptNum = htmlspecialchars($data['receipt_number']);
    $institution = htmlspecialchars($data['institution_name']);
    $email = htmlspecialchars($data['institution_email']);
    $contact = htmlspecialchars($data['contact_person']);
    $memberCount = (int) $data['member_count'];
    $date = htmlspecialchars($data['date']);
    $reference = htmlspecialchars($data['payment_reference']);
    $affiliationFee = number_format($data['affiliation_fee'], 2);
    $operationalFee = number_format($data['operational_fee'], 2);
    $membershipFees = number_format($data['membership_fees_new'], 2);
    $totalAmount = number_format($data['total_amount'], 2);
    $disclaimer = htmlspecialchars($data['disclaimer']);
    $hash = htmlspecialchars($data['blockchain_hash']);
    
    // Generate QR code (simple text placeholder - can be enhanced with QR lib)
    $qrText = base64_encode($data['qr_data']);
    
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affiliation Fee Receipt - {$receiptNum}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; padding: 2rem; }
        .receipt-container { max-width: 900px; margin: 0 auto; background: white; padding: 3rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .header { text-align: center; border-bottom: 3px solid #0A2F6C; padding-bottom: 2rem; margin-bottom: 2rem; }
        .header h1 { color: #0A2F6C; font-size: 2rem; margin-bottom: 0.5rem; }
        .header .receipt-type { color: #666; font-size: 1.2rem; margin-bottom: 1rem; }
        .logo { height: 60px; margin-bottom: 1rem; }
        
        .receipt-details { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem; }
        .detail-section { padding: 1rem; background: #f9f9f9; border-left: 4px solid #F5A623; }
        .detail-section h4 { color: #0A2F6C; margin-bottom: 0.5rem; font-size: 0.9rem; text-transform: uppercase; }
        .detail-section p { color: #333; font-size: 0.95rem; margin-bottom: 0.5rem; }
        
        .fee-breakdown { margin: 2rem 0; }
        .fee-breakdown h3 { color: #0A2F6C; margin-bottom: 1rem; font-size: 1.1rem; }
        .fee-table { width: 100%; border-collapse: collapse; }
        .fee-table th { background: #0A2F6C; color: white; padding: 0.75rem; text-align: left; font-weight: 600; }
        .fee-table td { padding: 0.75rem; border-bottom: 1px solid #ddd; }
        .fee-table tr:hover { background: #f9f9f9; }
        .fee-table .amount { text-align: right; font-weight: 600; }
        .fee-total { background: #f0f0f0; font-weight: bold; font-size: 1.1rem; }
        
        .total-amount { text-align: center; margin: 2rem 0; padding: 2rem; background: linear-gradient(135deg, #0A2F6C 0%, #1E3A6E 100%); color: white; border-radius: 8px; }
        .total-amount .label { font-size: 0.95rem; margin-bottom: 0.5rem; }
        .total-amount .amount { font-size: 2.5rem; font-weight: bold; }
        
        .disclaimer { background: #FEF3C7; border: 1px solid #FCD34D; padding: 1rem; border-radius: 6px; margin: 2rem 0; color: #92400E; font-size: 0.9rem; }
        .disclaimer strong { display: block; margin-bottom: 0.5rem; }
        
        .footer { text-align: center; color: #999; font-size: 0.85rem; margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #ddd; }
        .hash-section { margin-top: 1rem; padding: 1rem; background: #f5f5f5; border-radius: 4px; font-size: 0.85rem; word-break: break-all; }
        
        @media print {
            body { background: white; padding: 0; }
            .receipt-container { box-shadow: none; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="header">
            <div class="receipt-type">AFFILIATION FEE RECEIPT</div>
            <h1>Receipt #{$receiptNum}</h1>
            <p style="color: #999; margin-top: 0.5rem;">{$date}</p>
        </div>

        <div class="receipt-details">
            <div class="detail-section">
                <h4>Institution Information</h4>
                <p><strong>{$institution}</strong></p>
                <p>Contact: {$contact}</p>
                <p>Email: {$email}</p>
            </div>
            <div class="detail-section">
                <h4>Payment Details</h4>
                <p><strong>Reference:</strong> {$reference}</p>
                <p><strong>Member Count:</strong> {$memberCount} members</p>
                <p><strong>Payment Status:</strong> Verified</p>
            </div>
        </div>

        <div class="fee-breakdown">
            <h3>Fee Breakdown</h3>
            <table class="fee-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="amount">Amount (PHP)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Affiliation Fee</td>
                        <td class="amount">₱{$affiliationFee}</td>
                    </tr>
                    <tr>
                        <td>Operational Fee</td>
                        <td class="amount">₱{$operationalFee}</td>
                    </tr>
                    <tr>
                        <td>Membership Fees (All Members)</td>
                        <td class="amount">₱{$membershipFees}</td>
                    </tr>
                    <tr class="fee-total">
                        <td><strong>TOTAL AMOUNT DUE</strong></td>
                        <td class="amount"><strong>₱{$totalAmount}</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="total-amount">
            <div class="label">TOTAL AMOUNT PAID</div>
            <div class="amount">₱{$totalAmount}</div>
        </div>

        <div class="disclaimer">
            <strong>⚠️ SIMULATION NOTICE</strong>
            {$disclaimer}
        </div>

        <div class="hash-section">
            <strong>Blockchain Hash:</strong> {$hash}
        </div>

        <div class="footer">
            <p>This is an official receipt for IECEP-LSC affiliation payment.</p>
            <p>Please retain for your records. For verification, visit: https://iecep-lsc.org/verify-receipt</p>
        </div>
    </div>
</body>
</html>
HTML;
}

$transactionId = $_POST['transaction_id'] ?? '';

if (empty($transactionId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'transaction_id required']);
    exit;
}

$supabaseConfig = require __DIR__ . '/../../includes/supabase.php';
$supabase = new \App\Lib\SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);
$blockchain = new \App\Lib\BlockchainService($supabase);

try {
    // Get transaction details
    $transactions = $supabase->select('transactions', ['id' => 'eq.' . $transactionId]);
    
    if (empty($transactions)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Transaction not found']);
        exit;
    }

    $transaction = $transactions[0];

    // AFFILIATION RECEIPT TEMPLATE DETECTION
    // If this is an affiliation fee, use affiliation-specific template
    if ($transaction['transaction_type'] === 'affiliation_fee' && !empty($transaction['pending_affiliation_id'])) {
        // Fetch affiliated affiliation details
        $affiliations = $supabase->select('pending_affiliations', ['id' => 'eq.' . $transaction['pending_affiliation_id']]);
        if (!empty($affiliations)) {
            $affiliation = $affiliations[0];
            
            // Generate receipt number if not exists
            if (empty($transaction['receipt_number'])) {
                $receiptNumber = $transaction['receipt_number'] ?? ('RCP-' . date('Y') . '-' . str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT));
            } else {
                $receiptNumber = $transaction['receipt_number'];
            }

            // Record in blockchain
            $blockchainRecord = $blockchain->record('transaction', $transactionId, [
                'receipt_number' => $receiptNumber,
                'amount' => $transaction['amount'],
                'transaction_type' => 'affiliation_fee',
                'institution_id' => $affiliation['institution_id'] ?? null,
                'affiliation_id' => $affiliation['id']
            ]);

            $blockchainHash = $blockchainRecord['hash'] ?? null;

            // Update transaction with receipt and hash
            $supabase->update('transactions', [
                'receipt_number' => $receiptNumber,
                'blockchain_hash' => $blockchainHash ?? null
            ], ['id' => $transactionId]);

            // Generate Affiliation Receipt PDF
            $affiliationReceiptData = [
                'receipt_number' => $receiptNumber,
                'receipt_type' => 'Affiliation Fee Receipt',
                'institution_name' => $affiliation['institution_name'] ?? 'N/A',
                'institution_email' => $affiliation['rep_email'] ?? 'N/A',
                'contact_person' => $affiliation['rep_name'] ?? 'N/A',
                'member_count' => $affiliation['estimated_member_count'] ?? 0,
                'date' => date('F d, Y', strtotime($transaction['created_at'])),
                'payment_reference' => $transaction['payment_reference'] ?? 'N/A',
                'affiliation_fee' => $affiliation['affiliation_fee'] ?? 0,
                'operational_fee' => $affiliation['operational_fee'] ?? 0,
                'membership_fees_new' => $affiliation['membership_fees_total'] ?? 0,
                'total_amount' => $affiliation['total_fee'] ?? $transaction['amount'],
                'blockchain_hash' => $blockchainHash ?? '',
                'is_simulation' => true,
                'disclaimer' => 'This receipt is for a simulated GCash payment. No real funds were transferred.',
                'qr_data' => json_encode([
                    'receipt' => $receiptNumber,
                    'hash' => $blockchainHash,
                    'type' => 'affiliation_fee',
                    'institution' => $affiliation['institution_name'] ?? 'N/A'
                ])
            ];

            // Generate HTML receipt (can be converted to PDF)
            $htmlReceipt = generateAffiliationReceiptHTML($affiliationReceiptData);

            // Save receipt
            $receiptPath = __DIR__ . '/../../storage/receipts/AFFILIATION-' . $receiptNumber . '.html';
            if (!is_dir(dirname($receiptPath))) {
                mkdir(dirname($receiptPath), 0755, true);
            }
            file_put_contents($receiptPath, $htmlReceipt);

            // Update transaction with receipt path
            $supabase->update('transactions', [
                'receipt_url' => 'storage/receipts/AFFILIATION-' . $receiptNumber . '.html'
            ], ['id' => $transactionId]);

            echo json_encode([
                'success' => true,
                'message' => 'Affiliation receipt generated',
                'receipt_number' => $receiptNumber,
                'receipt_url' => 'storage/receipts/AFFILIATION-' . $receiptNumber . '.html',
                'receipt_type' => 'affiliation_fee'
            ]);
            exit;
        }
    }

    // STANDARD RECEIPT TEMPLATE (for other transaction types)
    // Generate receipt number if not exists
    if (empty($transaction['receipt_number'])) {
        $year = date('Y');
        $lastReceipt = $supabase->select('transactions', [
            'receipt_number' => 'like.RCP-' . $year . '%',
            'order' => 'created_at.desc',
            'limit' => 1
        ]);

        $nextNumber = 1;
        if (!empty($lastReceipt)) {
            $lastNumber = (int)substr($lastReceipt[0]['receipt_number'], -5);
            $nextNumber = $lastNumber + 1;
        }

        $receiptNumber = 'RCP-' . $year . '-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    } else {
        $receiptNumber = $transaction['receipt_number'];
    }

    // Record in blockchain
    $blockchainRecord = $blockchain->record('transaction', $transactionId, [
        'receipt_number' => $receiptNumber,
        'amount' => $transaction['amount'],
        'transaction_type' => $transaction['transaction_type'] ?? 'payment',
        'user_id' => $transaction['user_id'] ?? null,
        'institution_id' => $transaction['institution_id'] ?? null
    ]);

    $blockchainHash = $blockchainRecord['hash'];

    // Update transaction with receipt number and blockchain hash
    $supabase->update('transactions', [
        'receipt_number' => $receiptNumber,
        'blockchain_hash' => $blockchainHash
    ], ['id' => $transactionId]);

    // Generate PDF receipt (using existing PDF library)
    require_once __DIR__ . '/../../src/lib/pdf.php';
    
    $pdfContent = generateReceiptPDF([
        'receipt_number' => $receiptNumber,
        'transaction' => $transaction,
        'blockchain_hash' => $blockchainHash,
        'qr_data' => json_encode([
            'receipt' => $receiptNumber,
            'hash' => $blockchainHash,
            'verify_url' => 'https://yourdomain.com/verify-receipt?hash=' . $blockchainHash
        ])
    ]);

    // Save PDF
    $receiptPath = __DIR__ . '/../../storage/receipts/' . $receiptNumber . '.pdf';
    if (!is_dir(dirname($receiptPath))) {
        mkdir(dirname($receiptPath), 0755, true);
    }
    file_put_contents($receiptPath, $pdfContent);

    // Update transaction with receipt path
    $supabase->update('transactions', [
        'receipt_path' => 'storage/receipts/' . $receiptNumber . '.pdf'
    ], ['id' => $transactionId]);

    // Send email notification (if EmailService available)
    if (class_exists('\App\Lib\EmailService')) {
        $emailService = new \App\Lib\EmailService();
        $user = $supabase->select('user_profiles', ['id' => 'eq.' . $transaction['user_id']])[0] ?? null;
        
        if ($user && !empty($user['email'])) {
            $emailService->sendReceiptEmail($user['email'], $receiptNumber, $receiptPath);
        }
    }

    echo json_encode([
        'success' => true,
        'receipt_number' => $receiptNumber,
        'blockchain_hash' => $blockchainHash,
        'receipt_path' => 'storage/receipts/' . $receiptNumber . '.pdf'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Generate receipt PDF
 */
function generateReceiptPDF($data) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    
    $dompdf = new \Dompdf\Dompdf();
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .header { text-align: center; margin-bottom: 30px; }
            .receipt-number { font-size: 24px; font-weight: bold; }
            .details { margin: 20px 0; }
            .qr-code { text-align: center; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>IECEP-LSC</h1>
            <p>Official Receipt</p>
            <div class="receipt-number">' . htmlspecialchars($data['receipt_number']) . '</div>
        </div>
        <div class="details">
            <p><strong>Amount:</strong> ₱' . number_format($data['transaction']['amount'], 2) . '</p>
            <p><strong>Date:</strong> ' . date('F d, Y', strtotime($data['transaction']['created_at'])) . '</p>
            <p><strong>Blockchain Hash:</strong> ' . substr($data['blockchain_hash'], 0, 16) . '...</p>
        </div>
        <div class="qr-code">
            <p>Scan to verify:</p>
            <p>[QR Code would be here]</p>
        </div>
    </body>
    </html>';
    
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    return $dompdf->output();
}
