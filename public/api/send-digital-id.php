<?php
require_once __DIR__ . '/../portal/auth_check.php';
require_role(['school_officer']);

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../src/lib/EmailService.php';
require_once __DIR__ . '/../../src/lib/BlockchainService.php';
require_once __DIR__ . '/../../vendor/autoload.php';

if (!function_exists('uploadToSupabaseStorage')) {
    function uploadToSupabaseStorage(string $bucket, string $path, string $tmpFile, string $mimeType): ?string {
        $supabaseClient = getSupabaseClient();
        if ($supabaseClient && method_exists($supabaseClient, 'uploadFile')) {
            $url = $supabaseClient->uploadFile($bucket, $path, $tmpFile, $mimeType);
            if ($url) return $url;
        }
        return null;
    }
}

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['member_ids']) || !is_array($input['member_ids'])) {
        throw new Exception('Invalid request: member_ids array required');
    }
    
    $memberIds = $input['member_ids'];
    if (empty($memberIds)) {
        throw new Exception('No members selected');
    }
    
    $user = get_user_info();
    $institution_id = $_SESSION['institution_id'] ?? $user['institution_id'] ?? null;
    if (!$institution_id) {
        throw new Exception('Institution ID not found in session');
    }
    
    $supabase = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);
    $emailService = new \App\Lib\EmailService();
    
    $sent = 0;
    $failed = 0;
    $skipped = 0;
    
    foreach ($memberIds as $memberId) {
        try {
            // Fetch member details
            $memberData = $supabase->select('members', [
                'id' => 'eq.' . $memberId,
                'institution_id' => 'eq.' . $institution_id
            ]);
            
            if (empty($memberData)) {
                $skipped++;
                continue;
            }
            
            $member = $memberData[0];
            
            // Check payment status
            if (!($member['payment_status'] ?? false)) {
                $skipped++;
                continue;
            }
            
            // Check if digital ID exists
            $digitalIdUrl = $member['digital_id_url'] ?? null;
            
            if (!$digitalIdUrl) {
                // Generate digital ID if not exists
                $blockchain = new \App\Lib\BlockchainService($supabase);
                
                // Generate QR code using Endroid QR Code library
                $verifyUrl = APP_URL . '/verify-member.php?id=' . $member['membership_id'];
                $qrCode = \Endroid\QrCode\QrCode::create($verifyUrl);
                $writer = new \Endroid\QrCode\Writer\PngWriter();
                $result = $writer->write($qrCode);
                
                // Save QR code to temp file and upload to Supabase Storage
                $qrTmpFile = sys_get_temp_dir() . '/digital-id-' . $member['membership_id'] . '.png';
                $result->saveToFile($qrTmpFile);
                $qrSupabaseUrl = uploadToSupabaseStorage('public', 'digital-ids/digital-id-' . $member['membership_id'] . '.png', $qrTmpFile, 'image/png');
                @unlink($qrTmpFile);
                $digitalIdUrl = $qrSupabaseUrl ?? (PUBLIC_URL . '/uploads/digital-ids/digital-id-' . $member['membership_id'] . '.png');
                
                // Record blockchain entry
                $blockchain->record('digital_id', $member['id'], [
                    'membership_id' => $member['membership_id'],
                    'full_name' => $member['full_name'],
                    'qr_code_url' => $digitalIdUrl,
                    'verify_url' => $verifyUrl,
                    'generated_at' => date('c')
                ]);
                
                // Update member record
                $supabase->update('members', $member['id'], [
                    'digital_id_url' => $digitalIdUrl
                ]);
            }
            
            // Send email
            $emailSent = $emailService->sendDigitalId(
                $member['email'],
                $member['full_name'],
                $member['membership_id'],
                $digitalIdUrl
            );
            
            if ($emailSent) {
                $sent++;
            } else {
                $failed++;
            }
            
        } catch (Exception $e) {
            error_log("Failed to send digital ID to member {$memberId}: " . $e->getMessage());
            $failed++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'sent' => $sent,
        'failed' => $failed,
        'skipped' => $skipped,
        'message' => "Sent {$sent} digital IDs, {$failed} failed, {$skipped} skipped"
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
