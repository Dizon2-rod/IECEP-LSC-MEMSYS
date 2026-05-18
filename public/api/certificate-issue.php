<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../autoload.php';

header('Content-Type: application/json');

require_role(['admin', 'super_admin', 'committee_registration']);
require_csrf();

$action = $_POST['action'] ?? 'issue';

$supabaseConfig = require __DIR__ . '/../../includes/supabase.php';
$supabase = new \App\Lib\SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);
$blockchain = new \App\Lib\BlockchainService($supabase);

switch ($action) {
    case 'issue':
        $eventId = $_POST['event_id'] ?? '';
        $memberIds = isset($_POST['member_ids']) ? explode(',', $_POST['member_ids']) : [];

        if (empty($eventId) || empty($memberIds)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'event_id and member_ids required']);
            exit;
        }

        try {
            // Get event details
            $events = $supabase->select('events', ['id' => 'eq.' . $eventId]);
            if (empty($events)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Event not found']);
                exit;
            }
            $event = $events[0];

            $certificates = [];
            $year = date('Y');

            foreach ($memberIds as $memberId) {
                $memberId = trim($memberId);
                
                // Get member details
                $members = $supabase->select('members', ['id' => 'eq.' . $memberId]);
                if (empty($members)) continue;
                $member = $members[0];

                // Generate certificate number
                $lastCert = $supabase->select('certificates', [
                    'certificate_number' => 'like.CERT-' . $year . '%',
                    'order' => 'issue_date.desc',
                    'limit' => 1
                ]);

                $nextNumber = 1;
                if (!empty($lastCert)) {
                    $lastNumber = (int)substr($lastCert[0]['certificate_number'], -5);
                    $nextNumber = $lastNumber + 1;
                }

                $certificateNumber = 'CERT-' . $year . '-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

                // Record in blockchain first
                $blockchainRecord = $blockchain->record('certificate', $certificateNumber, [
                    'member_id' => $memberId,
                    'event_id' => $eventId,
                    'certificate_number' => $certificateNumber,
                    'issue_date' => date('Y-m-d'),
                    'event_title' => $event['title']
                ]);

                $blockchainHash = $blockchainRecord['hash'];

                // Generate PDF certificate
                $pdfContent = generateCertificatePDF([
                    'certificate_number' => $certificateNumber,
                    'member_name' => ($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''),
                    'event_title' => $event['title'],
                    'event_date' => date('F d, Y', strtotime($event['start_datetime'])),
                    'blockchain_hash' => $blockchainHash
                ]);

                // Save PDF
                $certDir = __DIR__ . '/../../storage/certificates/';
                if (!is_dir($certDir)) {
                    mkdir($certDir, 0755, true);
                }
                $certPath = 'storage/certificates/' . $certificateNumber . '.pdf';
                file_put_contents(__DIR__ . '/../../' . $certPath, $pdfContent);

                // Insert certificate record
                $certificate = $supabase->insert('certificates', [
                    'member_id' => $memberId,
                    'event_id' => $eventId,
                    'issue_date' => date('Y-m-d'),
                    'certificate_number' => $certificateNumber,
                    'blockchain_hash' => $blockchainHash,
                    'file_path' => $certPath,
                    'template_type' => 'participation'
                ]);

                // Send notification
                $supabase->insert('notifications', [
                    'user_id' => $member['user_id'] ?? null,
                    'title' => 'Certificate Issued',
                    'message' => "Your certificate for {$event['title']} has been issued.",
                    'type' => 'info',
                    'action_url' => '/member/certificates.php?id=' . $certificateNumber,
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                $certificates[] = [
                    'certificate_number' => $certificateNumber,
                    'member_id' => $memberId,
                    'blockchain_hash' => $blockchainHash
                ];
            }

            echo json_encode(['success' => true, 'certificates' => $certificates]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Generate certificate PDF
 */
function generateCertificatePDF($data) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    
    $dompdf = new \Dompdf\Dompdf();
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: "Times New Roman", serif; text-align: center; padding: 50px; }
            .certificate { border: 10px solid #0A2F6C; padding: 40px; }
            .title { font-size: 48px; color: #0A2F6C; margin-bottom: 20px; }
            .subtitle { font-size: 24px; margin-bottom: 40px; }
            .recipient { font-size: 36px; font-weight: bold; margin: 30px 0; }
            .details { font-size: 18px; margin: 20px 0; }
            .cert-number { font-size: 12px; color: #666; margin-top: 40px; }
        </style>
    </head>
    <body>
        <div class="certificate">
            <div class="title">CERTIFICATE OF PARTICIPATION</div>
            <div class="subtitle">IECEP - Luzon South Central</div>
            <p class="details">This is to certify that</p>
            <div class="recipient">' . htmlspecialchars($data['member_name']) . '</div>
            <p class="details">has successfully participated in</p>
            <div class="recipient">' . htmlspecialchars($data['event_title']) . '</div>
            <p class="details">held on ' . htmlspecialchars($data['event_date']) . '</p>
            <div class="cert-number">
                Certificate No: ' . htmlspecialchars($data['certificate_number']) . '<br>
                Blockchain Hash: ' . substr($data['blockchain_hash'], 0, 16) . '...
            </div>
        </div>
    </body>
    </html>';
    
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    
    return $dompdf->output();
}
