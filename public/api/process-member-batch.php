<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../portal/auth_check.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/paths.php';
require_once __DIR__ . '/../../src/lib/supabase.php';
require_once __DIR__ . '/../../src/lib/BlockchainService.php';
require_once __DIR__ . '/../../includes/lib/EmailService.php';

use App\Lib\Supabase;
use App\Lib\EmailService;
use App\Lib\BlockchainService;

$allowedRoles = ['committee_registration', 'admin'];
if (!require_role($allowedRoles, false)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Insufficient permissions']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$batchId = trim($input['batch_id'] ?? '');

if (empty($batchId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Batch ID is required']);
    exit;
}

$emailSvc = new EmailService();
$sb = new Supabase();

function getMembershipPrefix(Supabase $sb): string
{
    $setting = $sb->from('system_settings')
        ->select('*')
        ->eq('key', 'member_id_prefix')
        ->limit(1)
        ->get(true);

    if (!empty($setting['data'][0]['value'])) {
        return strtoupper(trim($setting['data'][0]['value']));
    }

    return 'IECEP';
}

function getMembershipYear(): int
{
    return intval(date('Y')) ?: intval(date('Y'));
}

function getCounterRowForYear(Supabase $sb, int $year): array
{
    $counterResult = $sb->from('member_id_counter')
        ->select('*')
        ->eq('year', $year)
        ->limit(1)
        ->get(true);

    if ($counterResult['error']) {
        throw new Exception('Unable to read membership counter');
    }

    if (!empty($counterResult['data'])) {
        return $counterResult['data'][0];
    }

    $insertResult = $sb->from('member_id_counter')->insert([
        'year' => $year,
        'last_number' => 0,
    ], true);

    if ($insertResult['error'] || empty($insertResult['data'])) {
        throw new Exception('Unable to initialize membership counter for year ' . $year);
    }

    return $insertResult['data'][0];
}

function generateMembershipId(Supabase $sb): string
{
    $year = getMembershipYear();
    $counterRow = getCounterRowForYear($sb, $year);
    $newNumber = intval($counterRow['last_number'] ?? 0) + 1;

    $updated = $sb->from('member_id_counter')
        ->eq('year', $year)
        ->update(['last_number' => $newNumber], true);

    if ($updated['error'] || empty($updated['data'])) {
        throw new Exception('Unable to increment membership counter for year ' . $year);
    }

    $prefix = getMembershipPrefix($sb);
    return sprintf('%s-%d-%04d', $prefix, $year, intval($updated['data'][0]['last_number']));
}

function findAuthUserByEmail(Supabase $sb, string $email): ?array
{
    $userResult = $sb->from('users')
        ->select('*')
        ->eq('email', $email)
        ->limit(1)
        ->get(true);

    if ($userResult['error']) {
        return null;
    }

    return !empty($userResult['data']) ? $userResult['data'][0] : null;
}

function generateMemberDigitalId(Supabase $sb, string $memberId): void
{
    $memberResult = $sb->from('members')
        ->select('id, full_name, email, institutions(name)')
        ->eq('id', $memberId)
        ->get(true);

    if ($memberResult['error'] || empty($memberResult['data'])) {
        throw new Exception('Member not found for digital ID generation');
    }

    $member = $memberResult['data'][0];
    $payload = [
        'member_id' => $member['id'],
        'full_name' => $member['full_name'],
        'email' => $member['email'],
        'institution' => $member['institutions']['name'] ?? 'Unknown',
        'issued_at' => date('c')
    ];

    $hash = hash('sha256', json_encode($payload));

    // Record blockchain entry
    if (isset($GLOBALS['blockchain']) && $GLOBALS['blockchain'] instanceof BlockchainService) {
        $GLOBALS['blockchain']->record('digital_id', $memberId, $payload);
    }

    // Generate QR code
    require_once __DIR__ . '/../../vendor/autoload.php'; // Assuming Endroid QR Code is installed
    $qrCode = new \Endroid\QrCode\QrCode($hash);
    $qrCode->setSize(300);
    $qrCode->setMargin(10);
    $qrCode->setEncoding(new \Endroid\QrCode\Encoding\Encoding('UTF-8'));
    $qrCode->setErrorCorrectionLevel(new \Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow());
    $qrCode->setRoundBlockSizeMode(new \Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin());
    $qrCode->setForegroundColor(new \Endroid\QrCode\Color\Color(0, 0, 0));
    $qrCode->setBackgroundColor(new \Endroid\QrCode\Color\Color(255, 255, 255));

    $writer = new \Endroid\QrCode\Writer\PngWriter();
    $result = $writer->write($qrCode);

    $qrPath = __DIR__ . '/../../public/assets/qr/' . $memberId . '.png';
    if (!is_dir(dirname($qrPath))) {
        mkdir(dirname($qrPath), 0755, true);
    }
    file_put_contents($qrPath, $result->getString());

    // Update member record
    $sb->from('members')
        ->eq('id', $memberId)
        ->update([
            'digital_id_hash' => $hash,
            'qr_code' => '/assets/qr/' . $memberId . '.png',
            'digital_id_url' => '/assets/qr/' . $memberId . '.png'
        ], true);
}

try {
    $batchResponse = $sb->from('member_upload_batches')
        ->select('*,pending_members(*)')
        ->eq('id', $batchId)
        ->eq('status', 'pending_approval')
        ->limit(1)
        ->get(true);

    if ($batchResponse['error'] || empty($batchResponse['data'])) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Pending member batch not found or already processed']);
        exit;
    }

    $batch = $batchResponse['data'][0];
    $pendingMembers = $batch['pending_members'] ?? [];
    $summary = [
        'total_members' => count($pendingMembers),
        'new_accounts_created' => 0,
        'renewed' => 0,
        'skipped' => 0,
        'errors' => [],
    ];

    foreach ($pendingMembers as $pending) {
        $pendingId = $pending['id'] ?? null;
        $fullName = trim($pending['full_name'] ?? '');
        $email = trim($pending['email'] ?? '');
        $memberType = trim($pending['member_type'] ?? 'new') ?: 'new';
        $yearLevel = trim($pending['year_level'] ?? '');
        $currentStatus = trim($pending['status'] ?? 'pending');

        if (empty($pendingId) || empty($email) || empty($fullName)) {
            $summary['skipped'] += 1;
            $summary['errors'][] = "Pending member row missing required fields: {$pendingId}";
            continue;
        }

        if ($currentStatus !== 'pending') {
            $summary['skipped'] += 1;
            continue;
        }

        try {
            $memberResult = $sb->from('members')
                ->select('*')
                ->eq('email', $email)
                ->limit(1)
                ->get(true);

            $membershipId = null;
            $memberPayload = [
                'full_name' => $fullName,
                'email' => $email,
                'member_type' => $memberType,
                'year_level' => $yearLevel,
                'updated_at' => date('c'),
            ];

            if (!$memberResult['error'] && !empty($memberResult['data'])) {
                $existingMember = $memberResult['data'][0];
                $membershipId = $existingMember['membership_id'] ?? generateMembershipId($sb);
                $memberPayload['membership_id'] = $membershipId;
                $memberPayload['member_type'] = $memberType ?: ($existingMember['member_type'] ?? 'returning');

                $updateResult = $sb->from('members')
                    ->eq('id', $existingMember['id'])
                    ->update($memberPayload, true);

                if ($updateResult['error']) {
                    throw new Exception('Failed to update existing member record');
                }

                if ($existingMember['payment_status'] ?? false) {
                    generateMemberDigitalId($sb, $existingMember['id']);
                }

                $emailSvc->sendMemberRenewalConfirmation($email, $fullName, $membershipId, $yearLevel);
                $summary['renewed'] += 1;

                // Audit logging
                $sb->from('audit_logs')->insert([
                    'user_id' => $_SESSION['user']['id'] ?? null,
                    'category' => 'member_management',
                    'action' => 'member_renewed',
                    'details' => json_encode([
                        'member_id' => $existingMember['id'],
                        'email' => $email,
                        'full_name' => $fullName,
                        'batch_id' => $batchId
                    ]),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'created_at' => date('c')
                ], true);
            } else {
                $password = 'IECEP@' . bin2hex(random_bytes(4));
                $authResult = $sb->auth()->adminCreateUser($email, $password, [
                    'user_metadata' => ['full_name' => $fullName],
                ]);

                if ($authResult['error'] || empty($authResult['data']['id'])) {
                    throw new Exception('Failed to create authentication user: ' . ($authResult['message'] ?? json_encode($authResult)));
                }

                $userId = $authResult['data']['id'];
                $membershipId = generateMembershipId($sb);

                $sb->from('user_profiles')->insert([
                    'user_id' => $userId,
                    'role' => 'member',
                    'full_name' => $fullName,
                    'force_password_change' => true,
                ], true);

                $insertResult = $sb->from('members')->insert([
                    'institution_id' => $batch['institution_id'],
                    'user_id' => $userId,
                    'full_name' => $fullName,
                    'email' => $email,
                    'member_type' => $memberType,
                    'year_level' => $yearLevel,
                    'payment_status' => false,
                    'membership_id' => $membershipId,
                ], true);

                if ($insertResult['error']) {
                    throw new Exception('Failed to insert member record');
                }

                $memberId = $insertResult['data'][0]['id'];
                generateMemberDigitalId($sb, $memberId);

                $emailSvc->sendCredentials($email, $email, $password);
                $summary['new_accounts_created'] += 1;

                // Audit logging
                $sb->from('audit_logs')->insert([
                    'user_id' => $_SESSION['user']['id'] ?? null,
                    'category' => 'member_management',
                    'action' => 'member_created',
                    'details' => json_encode([
                        'member_id' => $memberId,
                        'email' => $email,
                        'full_name' => $fullName,
                        'batch_id' => $batchId
                    ]),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'created_at' => date('c')
                ], true);
            }

            $sb->from('pending_members')
                ->eq('id', $pendingId)
                ->update(['status' => 'approved_payment_pending'], true);

            if (isset($GLOBALS['blockchain']) && $GLOBALS['blockchain'] instanceof BlockchainService) {
                $GLOBALS['blockchain']->record('membership_processing', $pendingId, [
                    'batch_id' => $batchId,
                    'member_email' => $email,
                    'member_name' => $fullName,
                    'membership_id' => $membershipId,
                    'processed_at' => date('c'),
                    'action' => empty($existingMember) ? 'created' : 'renewed',
                ]);
            }
        } catch (Exception $innerException) {
            $summary['skipped'] += 1;
            $summary['errors'][] = "{$fullName} ({$email}): " . $innerException->getMessage();
            continue;
        }
    }

    $batchUpdate = $sb->from('member_upload_batches')
        ->eq('id', $batchId)
        ->update([
            'status' => 'approved_payment_pending',
            'approved_at' => date('c'),
        ], true);

    if ($batchUpdate['error']) {
        throw new Exception('Unable to update batch processing status');
    }

    echo json_encode(['success' => true, 'summary' => $summary]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error processing member batch', 'details' => $e->getMessage()]);
    exit;
}
