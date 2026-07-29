<?php
if (!isset($current_page)) { $current_page = basename(__FILE__, '.php'); }
require_once __DIR__ . '/../auth_check.php';
require_role(['member']);

require_once __DIR__ . '/../../../includes/role-config.php';
require_once __DIR__ . '/../../../bootstrap.php';

require_once __DIR__ . '/../../includes/lib/BlockchainService.php';
require_once __DIR__ . '/../../includes/lib/PdfService.php';
require_once __DIR__ . '/../../includes/lib/QrCodeService.php';

use App\Lib\BlockchainService;
use App\Lib\PdfService;
use App\Lib\QrCodeService;

$user = get_user_info();
$member_id = $_SESSION['member_id'] ?? $user['member_id'] ?? null;
$event_id = $_GET['event_id'] ?? null;

if (!$member_id || !$event_id) {
    header('Location: /login.php');
    exit;
}

$supabase = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);

// Fetch member and event details
try {
    $memberData = $supabase->select('members', [
        'id' => 'eq.' . $member_id
    ]);
    $member = $memberData[0] ?? [];
    
    $eventData = $supabase->select('events', [
        'id' => 'eq.' . $event_id
    ]);
    $event = $eventData[0] ?? [];
    
    // Check if certificate exists
    $certData = $supabase->select('certificates', [
        'member_id' => 'eq.' . $member_id,
        'event_id' => 'eq.' . $event_id
    ]);
    $certificate = $certData[0] ?? null;
    
} catch (Exception $e) {
    die('Error fetching data: ' . $e->getMessage());
}

if (empty($member) || empty($event)) {
    die('Member or event not found');
}

// Check attendance
try {
    $regData = $supabase->select('event_registrations', [
        'member_id' => 'eq.' . $member_id,
        'event_id' => 'eq.' . $event_id
    ]);
    $registration = $regData[0] ?? [];
} catch (Exception $e) {
    $registration = [];
}

if (empty($registration) || ($registration['status'] ?? '') !== 'attended') {
    die('You must attend the event to download a certificate');
}

// Create certificate if it doesn't exist
if (!$certificate) {
    $certificateNumber = 'CERT-' . date('Y') . '-' . strtoupper(substr(md5($member_id . $event_id), 0, 8));
    $issueDate = date('Y-m-d');
    
    // Record on blockchain
    $blockchain = new BlockchainService($supabase);
    $blockchainResult = $blockchain->record('certificate', $certificateNumber, [
        'member_id' => $member_id,
        'member_name' => $member['full_name'],
        'event_id' => $event_id,
        'event_name' => $event['title'],
        'event_date' => $event['start_date'],
        'certificate_number' => $certificateNumber,
        'issued_at' => date('c')
    ]);
    
    $blockchainHash = $blockchainResult['hash'] ?? null;
    
    // Create certificate record
    $certResult = $supabase->insert('certificates', [
        'member_id' => $member_id,
        'event_id' => $event_id,
        'certificate_number' => $certificateNumber,
        'blockchain_hash' => $blockchainHash,
        'issue_date' => $issueDate
    ]);
    
    $certificateId = $certResult[0]['id'] ?? null;
    $certificate = [
        'id' => $certificateId,
        'certificate_number' => $certificateNumber,
        'blockchain_hash' => $blockchainHash,
        'issue_date' => $issueDate
    ];
}

// Generate QR code for verification
$qrService = new QrCodeService();
$verifyUrl = APP_URL . "/verify-certificate.php?cert=" . $certificate['certificate_number'];
$qrPath = sys_get_temp_dir() . '/cert_qr_' . $certificate['certificate_number'] . '.png';
$qrService->generateAndSave($verifyUrl, $qrPath, 150);

// Generate PDF certificate
$pdfService = new PdfService();
$html = "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Georgia', serif; margin: 0; padding: 0; }
        .certificate {
            width: 800px;
            height: 600px;
            padding: 40px;
            border: 10px solid #0B1D4A;
            position: relative;
            background: linear-gradient(135deg, #fff5e6 0%, #fff 100%);
        }
        .certificate::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 20px;
            right: 20px;
            bottom: 20px;
            border: 2px solid #D4AF37;
            pointer-events: none;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #0B1D4A;
            font-size: 28px;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .header h2 {
            color: #D4AF37;
            font-size: 16px;
            margin: 5px 0 0;
            text-transform: uppercase;
        }
        .title {
            text-align: center;
            margin: 40px 0;
        }
        .title h3 {
            color: #0B1D4A;
            font-size: 24px;
            margin: 0;
            font-style: italic;
        }
        .recipient {
            text-align: center;
            margin: 30px 0;
        }
        .recipient h4 {
            color: #0B1D4A;
            font-size: 32px;
            margin: 0;
            font-weight: bold;
        }
        .details {
            text-align: center;
            margin: 30px 0;
            font-size: 14px;
            color: #333;
        }
        .details p {
            margin: 10px 0;
        }
        .signature {
            display: flex;
            justify-content: space-between;
            margin-top: 60px;
            padding: 0 50px;
        }
        .signature-line {
            text-align: center;
            width: 200px;
        }
        .signature-line .line {
            border-bottom: 1px solid #333;
            margin-bottom: 10px;
        }
        .signature-line .label {
            font-size: 12px;
            color: #666;
        }
        .seal {
            position: absolute;
            bottom: 40px;
            right: 40px;
            width: 100px;
            height: 100px;
        }
        .blockchain-badge {
            position: absolute;
            bottom: 40px;
            left: 40px;
            font-size: 10px;
            color: #28a745;
        }
        .qr-code {
            position: absolute;
            bottom: 40px;
            left: 40px;
        }
    </style>
</head>
<body>
    <div class='certificate'>
        <div class='header'>
            <h1>Institute of Electronics Engineers of the Philippines</h1>
            <h2>Luzon Student Chapter</h2>
        </div>
        
        <div class='title'>
            <h3>Certificate of Participation</h3>
        </div>
        
        <div class='recipient'>
            <h4>This is to certify that</h4>
            <h4>" . htmlspecialchars($member['full_name']) . "</h4>
        </div>
        
        <div class='details'>
            <p>has successfully participated in</p>
            <p><strong>" . htmlspecialchars($event['title']) . "</strong></p>
            <p>held on " . date('F d, Y', strtotime($event['start_date'])) . "</p>
            <p>at " . htmlspecialchars($event['venue'] ?? 'TBD') . "</p>
        </div>
        
        <div class='signature'>
            <div class='signature-line'>
                <div class='line'></div>
                <div class='label'>IECEP-LSC President</div>
            </div>
            <div class='signature-line'>
                <div class='line'></div>
                <div class='label'>Event Chairperson</div>
            </div>
        </div>
        
        <div class='qr-code'>
            <img src='data:image/png;base64," . base64_encode(file_get_contents($qrPath)) . "' style='width:80px;height:80px;'>
        </div>
        
        <div class='blockchain-badge'>
            🔒 Blockchain Verified<br>
            Hash: " . substr($certificate['blockchain_hash'] ?? '', 0, 12) . "...
        </div>
    </div>
</body>
</html>";

$options = new \Dompdf\Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$dompdf = new \Dompdf\Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

// Clean up QR file
@unlink($qrPath);

// Output PDF
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="certificate_' . $certificate['certificate_number'] . '.pdf"');
echo $dompdf->output();
