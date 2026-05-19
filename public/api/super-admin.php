<?php
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

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
        case 'override-affiliation':
            if ($method !== 'POST') { http_response_code(405); exit; }
            $user = $auth->requireRole(['eb_president']);
            $data = json_decode(file_get_contents('php://input'), true);
            $pendingId = $data['pending_id'] ?? '';
            $decision = $data['decision'] ?? ''; // 'approve' or 'reject'
            $reason = $data['reason'] ?? '';

            if (empty($pendingId) || !in_array($decision, ['approve', 'reject'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Pending ID and valid decision required']);
                exit;
            }

            // Forward to registration committee logic (simplified)
            if ($decision === 'approve') {
                $_GET['action'] = 'approve-affiliation';
                // Reuse registration logic
                $pendingResult = $sb->from('pending_affiliations')
                    ->select('*')
                    ->eq('id', $pendingId)
                    ->get(true);

                if ($pendingResult['error'] || empty($pendingResult['data'])) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Not found']);
                    exit;
                }

                $pending = $pendingResult['data'][0];
                $password = 'IECEP@2025' . bin2hex(random_bytes(4));

                $authResult = $sb->auth()->adminCreateUser($pending['email'], $password, [
                    'user_metadata' => ['full_name' => $pending['contact_person']],
                ]);

                if ($authResult['error']) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to create user']);
                    exit;
                }

                $newUserId = $authResult['data']['id'] ?? '';

                $sb->from('user_profiles')->insert([
                    'user_id' => $newUserId,
                    'role' => 'school_officer',
                    'full_name' => $pending['contact_person'],
                    'force_password_change' => true,
                ], true);

                $instResult = $sb->from('institutions')->insert([
                    'email' => $pending['email'],
                    'name' => $pending['institution_name'],
                    'address' => $pending['address'],
                    'contact_person' => $pending['contact_person'],
                    'status' => 'active',
                ], true);

                $institutionId = $instResult['data'][0]['id'] ?? '';

                $memberResult = $sb->from('members')->insert([
                    'institution_id' => $institutionId,
                    'user_id' => $newUserId,
                    'full_name' => $pending['contact_person'],
                    'email' => $pending['email'],
                    'member_type' => 'returning',
                    'payment_status' => false,
                ], true);

                $newMemberId = $memberResult['data'][0]['id'] ?? '';

                // Generate digital ID
                $verifyUrl = $config['app_url'] . "/verify-member.html?id=" . $newMemberId;
                $qrPath = sys_get_temp_dir() . '/qr_' . $newMemberId . '.png';
                $qrSvc->generateAndSave($verifyUrl, $qrPath, 200);
                $idPath = $digitalId->generate($pending['contact_person'], $pending['institution_name'], 'returning', $newMemberId, $qrPath);

                if ($idPath) {
                    $sb->storage()->uploadBinary('member_ids', "{$newMemberId}.png", file_get_contents($idPath), 'image/png');
                    $digitalIdUrl = $sb->storage()->getPublicUrl('member_ids', "{$newMemberId}.png");
                    $sb->from('members')->eq('id', $newMemberId)->update(['digital_id_url' => $digitalIdUrl, 'qr_code' => $verifyUrl], true);

                    if (isset($GLOBALS['blockchain']) && $GLOBALS['blockchain'] instanceof \App\Lib\BlockchainService) {
                        $memberPayload = [
                            'member_id' => $newMemberId,
                            'full_name' => $pending['contact_person'],
                            'institution_name' => $pending['institution_name'],
                            'member_type' => 'returning',
                            'issued_at' => date('c'),
                        ];
                        $GLOBALS['blockchain']->record('digital_id', $newMemberId, $memberPayload);
                    }

                    @unlink($idPath);
                }
                @unlink($qrPath);

                $sb->from('pending_affiliations')->eq('id', $pendingId)->update(['status' => 'approved', 'reviewed_at' => date('Y-m-d\TH:i:s\Z')], true);

                $emailSvc->sendAffiliationApproved($pending['email'], $pending['institution_name']);
                $emailSvc->sendCredentials($pending['email'], $pending['email'], $password);

                echo json_encode(['success' => true, 'message' => 'Affiliation approved by President override.']);
            } else {
                $sb->from('pending_affiliations')->eq('id', $pendingId)->update([
                    'status' => 'rejected',
                    'reviewed_at' => date('Y-m-d\TH:i:s\Z'),
                    'rejection_reason' => $reason ?: 'Rejected by President override',
                ], true);

                $pendingResult = $sb->from('pending_affiliations')->select('*')->eq('id', $pendingId)->get(true);
                if (!$pendingResult['error'] && !empty($pendingResult['data'])) {
                    $emailSvc->sendAffiliationRejected($pendingResult['data'][0]['email'], $pendingResult['data'][0]['institution_name'], $reason);
                }

                echo json_encode(['success' => true, 'message' => 'Affiliation rejected by President override.']);
            }
            break;

        case 'settings':
            if ($method === 'GET') {
                $user = $auth->requireRole(['eb_president']);
                $result = $sb->from('system_settings')->select('*')->get(true);
                echo json_encode(['success' => true, 'data' => $result['data'] ?? []]);
            } elseif ($method === 'PUT' || $method === 'POST') {
                $user = $auth->requireRole(['eb_president']);
                $data = json_decode(file_get_contents('php://input'), true);
                $settings = $data['settings'] ?? [];

                foreach ($settings as $key => $value) {
                    $sb->from('system_settings')
                        ->eq('key', $key)
                        ->update(['value' => $value, 'updated_at' => date('Y-m-d\TH:i:s\Z')], true);
                }

                echo json_encode(['success' => true, 'message' => 'Settings updated']);
            }
            break;

        case 'create-officer':
            if ($method !== 'POST') { http_response_code(405); exit; }
            $user = $auth->requireRole(['eb_president']);
            $data = json_decode(file_get_contents('php://input'), true);
            $emailAddr = $data['email'] ?? '';
            $fullName = $data['full_name'] ?? '';
            $role = $data['role'] ?? '';
            $memberType = $data['member_type'] ?? 'honorary';

            $validOfficerRoles = [
                'eb_vp_internal', 'eb_vp_external', 'eb_vp_academic',
                'eb_secretary_general', 'eb_assistant_secretary', 'eb_treasurer', 'eb_auditor',
                'eb_pro_1', 'eb_pro_2',
                'committee_creatives', 'committee_documentation', 'committee_logistics',
                'committee_marketing', 'committee_registration', 'committee_technical',
            ];

            if (empty($emailAddr) || empty($fullName) || !in_array($role, $validOfficerRoles)) {
                http_response_code(400);
                echo json_encode(['error' => 'Email, full name, and valid officer role required']);
                exit;
            }

            // Create auth user
            $password = 'IECEP@2025' . bin2hex(random_bytes(4));
            $authResult = $sb->auth()->adminCreateUser($emailAddr, $password, [
                'user_metadata' => ['full_name' => $fullName],
            ]);

            if ($authResult['error']) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create user', 'details' => $authResult['message']]);
                exit;
            }

            $newUserId = $authResult['data']['id'] ?? '';

            // Create user profile
            $sb->from('user_profiles')->insert([
                'user_id' => $newUserId,
                'role' => $role,
                'full_name' => $fullName,
                'force_password_change' => true,
            ], true);

            // Create member record linked to Executive Council
            $memberResult = $sb->from('members')->insert([
                'institution_id' => $config['executive_council_id'],
                'user_id' => $newUserId,
                'full_name' => $fullName,
                'email' => $emailAddr,
                'member_type' => $memberType,
                'payment_status' => true, // Officers don't need to pay
            ], true);

            $newMemberId = $memberResult['data'][0]['id'] ?? '';

            // Generate digital ID
            $verifyUrl = $config['app_url'] . "/verify-member.html?id=" . $newMemberId;
            $qrPath = sys_get_temp_dir() . '/qr_' . $newMemberId . '.png';
            $qrSvc->generateAndSave($verifyUrl, $qrPath, 200);
            $idPath = $digitalId->generate($fullName, 'IECEP-LSC Executive Council', $memberType, $newMemberId, $qrPath);

            if ($idPath) {
                $sb->storage()->uploadBinary('member_ids', "{$newMemberId}.png", file_get_contents($idPath), 'image/png');
                $digitalIdUrl = $sb->storage()->getPublicUrl('member_ids', "{$newMemberId}.png");
                $sb->from('members')->eq('id', $newMemberId)->update(['digital_id_url' => $digitalIdUrl, 'qr_code' => $verifyUrl], true);
                @unlink($idPath);
            }
            @unlink($qrPath);

            // Send credentials
            $emailSvc->sendCredentials($emailAddr, $emailAddr, $password);

            echo json_encode(['success' => true, 'message' => 'Officer account created', 'user_id' => $newUserId, 'member_id' => $newMemberId]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}
