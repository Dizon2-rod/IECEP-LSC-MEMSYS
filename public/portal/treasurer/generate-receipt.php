<?php
require_once __DIR__ . '/../bootstrap.php';
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/role-config.php';

// Check if user is logged in and has treasurer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'eb_treasurer') {
    header('Location: ' . PORTAL_URL . '/login.php');
    exit;
}

// Check if transaction ID is provided
if (!isset($_GET['transaction_id'])) {
    die('Transaction ID is required');
}

$transactionId = (int) $_GET['transaction_id'];

try {
    // Fetch transaction details with member and institution info
    $transaction = $supabaseClient->from('transactions')
        ->select('*, members(name, email, institution_id, institutions(name, address))')
        ->eq('id', $transactionId)
        ->single();

    if (!$transaction) {
        die('Transaction not found');
    }

    // Generate PDF receipt using DOMPDF
    require_once '../../vendor/dompdf/dompdf/src/Dompdf.php';

    $dompdf = new Dompdf();
    $dompdf->setPaper('A4', 'portrait');

    // HTML content for receipt
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>IECEP-LSC Payment Receipt</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
            .header { text-align: center; border-bottom: 2px solid #0B1D4A; padding-bottom: 20px; margin-bottom: 30px; }
            .logo { font-size: 24px; font-weight: bold; color: #0B1D4A; margin-bottom: 10px; }
            .subtitle { color: #666; font-size: 14px; }
            .receipt-info { margin-bottom: 30px; }
            .info-row { display: flex; margin-bottom: 10px; }
            .info-label { width: 150px; font-weight: bold; color: #333; }
            .info-value { flex: 1; }
            .amount-section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .amount { font-size: 24px; font-weight: bold; color: #0B1D4A; text-align: center; }
            .footer { margin-top: 50px; text-align: center; color: #666; font-size: 12px; border-top: 1px solid #ddd; padding-top: 20px; }
            .qr-code { text-align: center; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="logo">IECEP-LSC MEMSYS</div>
            <div class="subtitle">Institute of Electronics Engineers of the Philippines<br>Laguna Section Chapter</div>
            <h2>PAYMENT RECEIPT</h2>
        </div>

        <div class="receipt-info">
            <div class="info-row">
                <div class="info-label">Receipt No:</div>
                <div class="info-value">TXN-' . str_pad($transaction['id'], 6, '0', STR_PAD_LEFT) . '</div>
            </div>
            <div class="info-row">
                <div class="info-label">Date:</div>
                <div class="info-value">' . date('F j, Y', strtotime($transaction['created_at'])) . '</div>
            </div>
            <div class="info-row">
                <div class="info-label">Member Name:</div>
                <div class="info-value">' . htmlspecialchars($transaction['members']['name']) . '</div>
            </div>
            <div class="info-row">
                <div class="info-label">Institution:</div>
                <div class="info-value">' . htmlspecialchars($transaction['members']['institutions']['name']) . '</div>
            </div>
            <div class="info-row">
                <div class="info-label">Payment Type:</div>
                <div class="info-value">' . ucfirst($transaction['payment_type']) . '</div>
            </div>
            <div class="info-row">
                <div class="info-label">Status:</div>
                <div class="info-value">' . ucfirst($transaction['status']) . '</div>
            </div>
        </div>

        <div class="amount-section">
            <div class="amount">₱' . number_format($transaction['amount'], 2) . '</div>
            <div style="text-align: center; margin-top: 10px; color: #666;">Amount Paid</div>
        </div>

        <div class="qr-code">
            <!-- QR Code would be generated here using Endroid QR Code -->
            <div style="border: 1px solid #ddd; display: inline-block; padding: 10px;">
                <div style="width: 100px; height: 100px; background: #f8f9fa; display: flex; align-items: center; justify-content: center; color: #666; font-size: 12px;">
                    QR Code<br>Placeholder
                </div>
            </div>
        </div>

        <div class="footer">
            <p>This is an official receipt from IECEP-LSC MEMSYS</p>
            <p>Generated on ' . date('F j, Y \a\t g:i A') . '</p>
            <p>For any inquiries, please contact the IECEP-LSC Treasurer</p>
        </div>
    </body>
    </html>';

    $dompdf->loadHtml($html);
    $dompdf->render();

    // Output PDF
    $dompdf->stream('IECEP-LSC-Receipt-TXN-' . str_pad($transaction['id'], 6, '0', STR_PAD_LEFT) . '.pdf', [
        'Attachment' => true
    ]);

} catch (Exception $e) {
    error_log('Receipt generation error: ' . $e->getMessage());
    die('Error generating receipt. Please try again later.');
}
?>