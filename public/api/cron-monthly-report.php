<?php
require_once __DIR__ . '/bootstrap.php';

// Security: Check for cron secret
$cronSecret = $_GET['secret'] ?? $_SERVER['HTTP_X_CRON_SECRET'] ?? '';
$expectedSecret = defined('CRON_SECRET') ? CRON_SECRET : '';

if ($cronSecret !== $expectedSecret) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/supabase.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/lib/EmailService.php';
require_once __DIR__ . '/../../includes/lib/PdfService.php';

use App\Lib\EmailService;
use App\Lib\PdfService;

try {
    $sb = new \App\Lib\Supabase(SUPABASE_URL, SUPABASE_ANON_KEY);
    $emailService = new EmailService();
    $pdfService = new PdfService();
    
    // Get previous month
    $previousMonth = date('F Y', strtotime('first day of previous month'));
    $previousMonthStart = date('Y-m-01', strtotime('first day of previous month'));
    $previousMonthEnd = date('Y-m-t', strtotime('last day of previous month'));
    
    // Fetch financial data for previous month
    $transactions = $sb->from('transactions')
        ->select('*')
        ->gte('transaction_date', $previousMonthStart)
        ->lte('transaction_date', $previousMonthEnd)
        ->eq('status', 'paid')
        ->get(true);
    
    if ($transactions['error']) {
        throw new Exception('Failed to fetch transactions: ' . $transactions['message']);
    }
    
    $transactionData = $transactions['data'] ?? [];
    
    // Calculate totals
    $totalIncome = 0;
    $totalTransactions = count($transactionData);
    $paidTransactions = 0;
    $pendingTransactions = 0;
    
    foreach ($transactionData as $tx) {
        $totalIncome += floatval($tx['amount'] ?? 0);
        if ($tx['status'] === 'paid') {
            $paidTransactions++;
        }
    }
    
    // Get pending transactions count
    $pendingResult = $sb->from('transactions')
        ->select('id')
        ->eq('status', 'pending')
        ->get(true);
    
    if (!$pendingResult['error']) {
        $pendingTransactions = count($pendingResult['data'] ?? []);
    }
    
    // Get institution data
    $institutions = $sb->from('institutions')
        ->select('name, id')
        ->eq('status', 'active')
        ->get(true);
    
    if (!$institutions['error']) {
        $institutionData = $institutions['data'] ?? [];
    } else {
        $institutionData = [];
    }
    
    // Generate PDF report
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: 'Inter', sans-serif; padding: 40px; }
            .header { text-align: center; margin-bottom: 40px; border-bottom: 2px solid #0B1D4A; padding-bottom: 20px; }
            .header h1 { color: #0B1D4A; margin: 0; }
            .header p { color: #64748b; margin: 5px 0 0; }
            .summary { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 40px; }
            .summary-card { background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #D4AF37; }
            .summary-card h3 { margin: 0 0 10px; color: #0B1D4A; font-size: 14px; }
            .summary-card .value { font-size: 28px; font-weight: 700; color: #0B1D4A; }
            .table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
            .table th { background: #0B1D4A; color: white; padding: 12px; text-align: left; }
            .table td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
            .table tr:nth-child(even) { background: #f8f9fa; }
            .footer { text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>IECEP-LSC MEMSYS</h1>
            <p>Monthly Financial Report</p>
            <p>{$previousMonth}</p>
        </div>
        
        <div class='summary'>
            <div class='summary-card'>
                <h3>Total Income</h3>
                <div class='value'>₱" . number_format($totalIncome, 2) . "</div>
            </div>
            <div class='summary-card'>
                <h3>Total Transactions</h3>
                <div class='value'>{$totalTransactions}</div>
            </div>
            <div class='summary-card'>
                <h3>Paid Transactions</h3>
                <div class='value'>{$paidTransactions}</div>
            </div>
            <div class='summary-card'>
                <h3>Pending Transactions</h3>
                <div class='value'>{$pendingTransactions}</div>
            </div>
        </div>
        
        <h2 style='color: #0B1D4A; margin-bottom: 20px;'>Transaction Summary</h2>
        <table class='table'>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>";
    
    foreach (array_slice($transactionData, 0, 50) as $tx) {
        $html .= "
                <tr>
                    <td>" . date('M d, Y', strtotime($tx['transaction_date'] ?? '')) . "</td>
                    <td>" . htmlspecialchars($tx['type'] ?? 'N/A') . "</td>
                    <td>₱" . number_format($tx['amount'] ?? 0, 2) . "</td>
                    <td>" . htmlspecialchars($tx['status'] ?? 'N/A') . "</td>
                </tr>";
    }
    
    $html .= "
            </tbody>
        </table>
        
        <div class='footer'>
            <p>Generated on " . date('F d, Y g:i A') . "</p>
            <p>IECEP-LSC Member Management System</p>
        </div>
    </body>
    </html>";
    
    // Generate PDF
    $options = new \Dompdf\Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    $pdfContent = $dompdf->output();
    $pdfFilename = "financial_report_{$previousMonthStart}.pdf";
    
    // Get recipient emails from system settings
    $treasurerEmail = $sb->from('system_settings')
        ->select('value')
        ->eq('key', 'treasurer_email')
        ->get(true);
    
    $presidentEmail = $sb->from('system_settings')
        ->select('value')
        ->eq('key', 'president_email')
        ->get(true);
    
    $recipients = [];
    if (!$treasurerEmail['error'] && !empty($treasurerEmail['data'])) {
        $recipients[] = $treasurerEmail['data'][0]['value'] ?? '';
    }
    if (!$presidentEmail['error'] && !empty($presidentEmail['data'])) {
        $recipients[] = $presidentEmail['data'][0]['value'] ?? '';
    }
    
    // Default fallback emails
    if (empty($recipients)) {
        $recipients = ['treasurer@iecep-lsc.org', 'president@iecep-lsc.org'];
    }
    
    // Send emails
    $sentCount = 0;
    foreach ($recipients as $recipient) {
        $subject = "IECEP-LSC Monthly Financial Report - {$previousMonth}";
        $message = "
        <html>
        <body>
            <h2>Monthly Financial Report</h2>
            <p>Dear IECEP-LSC Officer,</p>
            <p>Please find attached the monthly financial report for {$previousMonth}.</p>
            <p><strong>Summary:</strong></p>
            <ul>
                <li>Total Income: ₱" . number_format($totalIncome, 2) . "</li>
                <li>Total Transactions: {$totalTransactions}</li>
                <li>Paid Transactions: {$paidTransactions}</li>
                <li>Pending Transactions: {$pendingTransactions}</li>
            </ul>
            <p>This report was automatically generated by the IECEP-LSC Member Management System.</p>
            <p>Best regards,<br>IECEP-LSC MEMSYS</p>
        </body>
        </html>";
        
        $emailSent = $emailService->sendEmail($recipient, $subject, $message, $pdfContent, $pdfFilename);
        
        if ($emailSent) {
            $sentCount++;
        }
    }
    
    // Log action in audit_logs
    $sb->from('audit_logs')->insert([
        'action' => 'monthly_financial_report_sent',
        'details' => json_encode([
            'month' => $previousMonth,
            'total_income' => $totalIncome,
            'total_transactions' => $totalTransactions,
            'recipients' => $recipients,
            'sent_count' => $sentCount
        ]),
        'performed_by' => 'system_cron'
    ], true);
    
    echo json_encode([
        'success' => true,
        'message' => 'Monthly financial report generated and sent',
        'data' => [
            'month' => $previousMonth,
            'total_income' => $totalIncome,
            'total_transactions' => $totalTransactions,
            'recipients' => count($recipients),
            'sent' => $sentCount
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
