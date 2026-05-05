<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// Check if critical extensions are available
$criticalExtensions = ['curl', 'json'];
$missingCritical = [];
foreach ($criticalExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingCritical[] = $ext;
    }
}

if (!empty($missingCritical)) {
    error_log("Missing critical PHP extensions: " . implode(', ', $missingCritical));
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error: Missing critical PHP extensions. Please contact your server administrator.']);
    exit;
}

require_once __DIR__ . '/../../includes/supabase.php';
require_once __DIR__ . '/../../includes/lib/EmailService.php';
require_once __DIR__ . '/../../includes/lib/digital_id.php';
require_once __DIR__ . '/../../includes/lib/qrcode.php';
require_once __DIR__ . '/../../includes/middleware/auth.php';

use App\Lib\Supabase;
use App\Lib\EmailService;
use App\Lib\DigitalIdService;
use App\Lib\QrCodeService;
use App\Middleware\AuthMiddleware;

$sb = new Supabase();
$auth = new AuthMiddleware();
$emailSvc = new EmailService();
$digitalId = new DigitalIdService();
$qrSvc = new QrCodeService();

$config = include __DIR__ . '/../config/config.php';
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'pending-affiliations':
            if ($method !== 'GET') { http_response_code(405); exit; }
            $user = $auth->requireRole(['committee_registration']);

            $result = $sb->from('pending_affiliations')
                ->select('*')
                ->eq('status', 'pending_review')
                ->order('submitted_at', false)
                ->get(true);

            echo json_encode(['success' => true, 'data' => $result['data'] ?? []]);
            break;

        case 'approve-affiliation':
            if ($method !== 'POST') { http_response_code(405); exit; }
            $user = $auth->requireRole(['committee_registration']);
            $data = json_decode(file_get_contents('php://input'), true);
            $pendingId = $data['pending_id'] ?? '';

            if (empty($pendingId)) {
                http_response_code(400);
                echo json_encode(['error' => 'Pending ID required']);
                exit;
            }

            // Get pending affiliation
            $pendingResult = $sb->from('pending_affiliations')
                ->select('*')
                ->eq('id', $pendingId)
                ->get(true);

            if ($pendingResult['error'] || empty($pendingResult['data'])) {
                http_response_code(404);
                echo json_encode(['error' => 'Pending affiliation not found']);
                exit;
            }

            $pending = $pendingResult['data'][0];

            // Generate random password
            $password = 'IECEP@2025' . bin2hex(random_bytes(4));

            // Create Supabase Auth user
            $authResult = $sb->auth()->adminCreateUser($pending['email'], $password, [
                'user_metadata' => ['full_name' => $pending['contact_person']],
            ]);

            if ($authResult['error']) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create user account', 'details' => $authResult['message']]);
                exit;
            }

            $newUserId = $authResult['data']['id'] ?? '';

            // Create user profile (school_officer)
            $sb->from('user_profiles')->insert([
                'user_id' => $newUserId,
                'role' => 'school_officer',
                'full_name' => $pending['contact_person'],
                'force_password_change' => true,
            ], true);

            // Create institution
            $instResult = $sb->from('institutions')->insert([
                'email' => $pending['email'],
                'name' => $pending['institution_name'],
                'address' => $pending['address'],
                'contact_person' => $pending['contact_person'],
                'status' => 'active',
                'affiliation_fee_paid' => false,
            ], true);

            $institutionId = $instResult['data'][0]['id'] ?? '';

            // Create member record for school officer
            $memberId = $sb->from('members')->insert([
                'institution_id' => $institutionId,
                'user_id' => $newUserId,
                'full_name' => $pending['contact_person'],
                'email' => $pending['email'],
                'member_type' => 'returning',
                'payment_status' => false,
            ], true);

            $newMemberId = $memberId['data'][0]['id'] ?? '';

            // Generate digital ID
            $verifyUrl = $config['app_url'] . "/verify-member.php?id=" . $newMemberId;
            $qrPath = sys_get_temp_dir() . '/qr_' . $newMemberId . '.png';
            $qrSvc->generateAndSave($verifyUrl, $qrPath, 200);

            $idPath = $digitalId->generate(
                $pending['contact_person'],
                $pending['institution_name'],
                'returning',
                $newMemberId,
                $qrPath
            );

            if ($idPath) {
                $idUploadResult = $sb->storage()->uploadBinary(
                    'member_ids',
                    "{$newMemberId}.png",
                    file_get_contents($idPath),
                    'image/png'
                );

                $digitalIdUrl = $sb->storage()->getPublicUrl('member_ids', "{$newMemberId}.png");

                $sb->from('members')
                    ->eq('id', $newMemberId)
                    ->update([
                        'digital_id_url' => $digitalIdUrl,
                        'qr_code' => $verifyUrl,
                    ], true);

                @unlink($idPath);
            }
            @unlink($qrPath);

            // Update pending affiliation status
            $sb->from('pending_affiliations')
                ->eq('id', $pendingId)
                ->update([
                    'status' => 'approved',
                    'reviewed_at' => date('Y-m-d\TH:i:s\Z'),
                ], true);

            // Send emails
            $emailSvc->sendAffiliationApproved($pending['email'], $pending['institution_name']);
            $emailSvc->sendCredentials($pending['email'], $pending['email'], $password);

            echo json_encode(['success' => true, 'message' => 'Affiliation approved. School officer account created.']);
            break;

        case 'reject-affiliation':
            if ($method !== 'POST') { http_response_code(405); exit; }
            $user = $auth->requireRole(['committee_registration']);
            $data = json_decode(file_get_contents('php://input'), true);
            $pendingId = $data['pending_id'] ?? '';
            $reason = $data['reason'] ?? '';

            if (empty($pendingId)) {
                http_response_code(400);
                echo json_encode(['error' => 'Pending ID required']);
                exit;
            }

            $pendingResult = $sb->from('pending_affiliations')
                ->select('*')
                ->eq('id', $pendingId)
                ->get(true);

            if ($pendingResult['error'] || empty($pendingResult['data'])) {
                http_response_code(404);
                echo json_encode(['error' => 'Pending affiliation not found']);
                exit;
            }

            $pending = $pendingResult['data'][0];

            $sb->from('pending_affiliations')
                ->eq('id', $pendingId)
                ->update([
                    'status' => 'rejected',
                    'reviewed_at' => date('Y-m-d\TH:i:s\Z'),
                    'rejection_reason' => $reason,
                ], true);

            $emailSvc->sendAffiliationRejected($pending['email'], $pending['institution_name'], $reason);

            echo json_encode(['success' => true, 'message' => 'Affiliation rejected.']);
            break;

        case 'pending-members':
            if ($method !== 'GET') { http_response_code(405); exit; }
            $user = $auth->requireRole(['committee_registration']);

            $result = $sb->from('member_upload_batches')
                ->select('*, institutions(name), pending_members(*)')
                ->eq('status', 'pending_approval')
                ->order('uploaded_at', false)
                ->get(true);

            echo json_encode(['success' => true, 'data' => $result['data'] ?? []]);
            break;

        case 'approve-members':
            if ($method !== 'POST') { http_response_code(405); exit; }
            $user = $auth->requireRole(['committee_registration']);
            $data = json_decode(file_get_contents('php://input'), true);
            $batchId = $data['batch_id'] ?? '';
            $approvedIds = $data['approved_ids'] ?? []; // array of pending_member IDs to approve
            $rejectedIds = $data['rejected_ids'] ?? [];

            if (empty($batchId)) {
                http_response_code(400);
                echo json_encode(['error' => 'Batch ID required']);
                exit;
            }

            // Approve selected members
            if (!empty($approvedIds)) {
                foreach ($approvedIds as $pmId) {
                    $sb->from('pending_members')
                        ->eq('id', $pmId)
                        ->update(['status' => 'approved_payment_pending'], true);
                }
            }

            // Reject selected members
            if (!empty($rejectedIds)) {
                foreach ($rejectedIds as $pmId) {
                    $sb->from('pending_members')
                        ->eq('id', $pmId)
                        ->update(['status' => 'rejected'], true);
                }
            }

            // Update batch status
            $sb->from('member_upload_batches')
                ->eq('id', $batchId)
                ->update([
                    'status' => 'approved_payment_pending',
                    'approved_at' => date('Y-m-d\TH:i:s\Z'),
                ], true);

            // Notify treasurer
            $treasurerProfile = $sb->from('user_profiles')
                ->select('user_id')
                ->eq('role', 'eb_treasurer')
                ->get(true);

            if (!$treasurerProfile['error'] && !empty($treasurerProfile['data'])) {
                $treasurerMember = $sb->from('members')
                    ->select('email')
                    ->eq('user_id', $treasurerProfile['data'][0]['user_id'])
                    ->get(true);
                if (!$treasurerMember['error'] && !empty($treasurerMember['data'])) {
                    $emailSvc->sendNotification(
                        $treasurerMember['data'][0]['email'],
                        'Member Fees Ready for Collection',
                        'A member list has been approved and fees are ready for collection.'
                    );
                }
            }

            echo json_encode(['success' => true, 'message' => 'Members approved. Fees pending.']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
