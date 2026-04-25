<?php
namespace App\Lib;

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfService
{
    private array $config;

    public function __construct()
    {
        $this->config = include __DIR__ . '/../config/config.php';
    }

    public function generateReceipt(string $receiptId, string $payerName, float $amount, string $date, ?string $txHash = null, ?string $qrCodePath = null): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);

        $amountFormatted = '₱' . number_format($amount, 2);
        $etherscanLink = $txHash ? "https://sepolia.etherscan.io/tx/$txHash" : '';
        $qrImg = '';
        if ($qrCodePath && file_exists($qrCodePath)) {
            $qrData = base64_encode(file_get_contents($qrCodePath));
            $qrImg = "<img src='data:image/png;base64,{$qrData}' style='width:120px;height:120px;margin-top:12px' />";
        }

        $blockchainSection = '';
        if ($txHash) {
            $blockchainSection = "
                <div style='margin-top:20px;padding:16px;background:#f0f4f8;border-radius:8px;border-left:4px solid #0A2F6C'>
                    <h4 style='color:#0A2F6C;margin:0 0 8px 0'>Blockchain Verification</h4>
                    <p style='margin:4px 0;font-size:12px'><strong>Transaction Hash:</strong> <code style='font-size:11px'>{$txHash}</code></p>
                    <p style='margin:4px 0;font-size:12px'><a href='{$etherscanLink}'>View on Etherscan</a></p>
                    {$qrImg}
                </div>";
        }

        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: 'Helvetica', 'Arial', sans-serif; margin: 0; padding: 20px; color: #343a40; }
                .receipt { max-width: 600px; margin: 0 auto; border: 2px solid #0A2F6C; border-radius: 12px; padding: 32px; }
                .header { text-align: center; border-bottom: 2px solid #F5A623; padding-bottom: 16px; margin-bottom: 20px; }
                .header h1 { color: #0A2F6C; margin: 0; font-size: 20px; }
                .header h2 { color: #F5A623; margin: 4px 0 0; font-size: 14px; }
                .row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
                .row .label { font-weight: bold; color: #0A2F6C; }
                .row .value { text-align: right; }
                .amount { font-size: 28px; color: #0A2F6C; font-weight: bold; text-align: center; margin: 20px 0; }
                .footer { text-align: center; margin-top: 24px; padding-top: 16px; border-top: 2px solid #0A2F6C; font-size: 11px; color: #6c757d; }
                .badge { display: inline-block; background: #28a745; color: white; padding: 4px 12px; border-radius: 12px; font-size: 11px; }
            </style>
        </head>
        <body>
            <div class='receipt'>
                <div class='header'>
                    <h1>IECEP-LSC</h1>
                    <h2>Official Payment Receipt</h2>
                </div>
                <div class='row'>
                    <span class='label'>Receipt No.</span>
                    <span class='value'>{$receiptId}</span>
                </div>
                <div class='row'>
                    <span class='label'>Payer</span>
                    <span class='value'>{$payerName}</span>
                </div>
                <div class='row'>
                    <span class='label'>Date</span>
                    <span class='value'>{$date}</span>
                </div>
                <div class='amount'>{$amountFormatted}</div>
                {$blockchainSection}
                <div class='footer'>
                    <span class='badge'>On-Chain Verified</span><br><br>
                    This payment is immutably recorded on the Ethereum Sepolia blockchain.<br>
                    IECEP-LSC MEMSYS &copy; " . date('Y') . "
                </div>
            </div>
        </body>
        </html>";

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf->output();
    }
}
