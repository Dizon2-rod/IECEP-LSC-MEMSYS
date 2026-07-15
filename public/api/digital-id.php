<?php
require_once __DIR__ . '/bootstrap.php';
require_once '../../includes/config.php';
require_once '../../includes/database.php';

// Set headers for API responses
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$action = $_GET['action'] ?? '';
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? '';

try {
    switch ($action) {
        case 'get_digital_id':
            // Get user's digital ID information
            $userIdParam = $_GET['user_id'] ?? $userId;

            // Check permissions - users can only view their own ID unless admin
            if ($userIdParam !== $userId && !in_array($userRole, ['eb_admin', 'eb_president', 'eb_vp_internal'])) {
                throw new Exception('Permission denied');
            }

            $userProfile = $supabaseClient->from('user_profiles')
                ->select('*')
                ->eq('id', $userIdParam)
                ->single()
                ->execute();

            if (!$userProfile) {
                throw new Exception('User profile not found');
            }

            // Get blockchain records for this user
            $blockchainRecords = $supabaseClient->from('blockchain_records')
                ->select('*')
                ->eq('user_id', $userIdParam)
                ->order('created_at', ['ascending' => false])
                ->execute();

            // Calculate digital identity score
            $identityScore = calculateIdentityScore($userProfile, $blockchainRecords);

            echo json_encode([
                'success' => true,
                'digital_id' => [
                    'user_id' => $userProfile['id'],
                    'name' => $userProfile['name'],
                    'email' => $userProfile['email'],
                    'role' => $userProfile['role'],
                    'institution' => $userProfile['institution'],
                    'member_since' => $userProfile['created_at'],
                    'identity_score' => $identityScore,
                    'blockchain_records' => $blockchainRecords,
                    'verification_status' => $userProfile['verification_status'] ?? 'pending'
                ]
            ]);
            break;

        case 'generate_digital_id':
            // Generate or update digital ID
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed');
            }

            $userIdParam = $_GET['user_id'] ?? $userId;

            // Check permissions
            if ($userIdParam !== $userId && !in_array($userRole, ['eb_admin', 'eb_president'])) {
                throw new Exception('Permission denied');
            }

            $userProfile = $supabaseClient->from('user_profiles')
                ->select('*')
                ->eq('id', $userIdParam)
                ->single()
                ->execute();

            if (!$userProfile) {
                throw new Exception('User profile not found');
            }

            // Generate blockchain record
            $blockchainData = generateBlockchainRecord($userProfile);

            // Store in blockchain_records table
            $recordData = [
                'user_id' => $userIdParam,
                'record_type' => 'digital_identity',
                'data_hash' => $blockchainData['hash'],
                'previous_hash' => getLastBlockHash(),
                'block_data' => json_encode([
                    'user_id' => $userProfile['id'],
                    'name' => $userProfile['name'],
                    'email' => $userProfile['email'],
                    'role' => $userProfile['role'],
                    'institution' => $userProfile['institution'],
                    'verification_data' => $blockchainData['verification_data']
                ]),
                'timestamp' => date('c'),
                'created_at' => date('c')
            ];

            $result = $supabaseClient->from('blockchain_records')->insert($recordData)->execute();

            // Update user profile with verification status
            $supabaseClient->from('user_profiles')
                ->update([
                    'verification_status' => 'verified',
                    'digital_id_hash' => $blockchainData['hash'],
                    'updated_at' => date('c')
                ])
                ->eq('id', $userIdParam)
                ->execute();

            echo json_encode([
                'success' => true,
                'message' => 'Digital ID generated successfully',
                'digital_id_hash' => $blockchainData['hash'],
                'blockchain_record' => $result[0] ?? null
            ]);
            break;

        case 'verify_digital_id':
            // Verify digital ID integrity
            $userIdParam = $_GET['user_id'] ?? '';
            $hashToVerify = $_GET['hash'] ?? '';

            if (empty($userIdParam) || empty($hashToVerify)) {
                throw new Exception('User ID and hash are required');
            }

            // Get blockchain record
            $record = $supabaseClient->from('blockchain_records')
                ->select('*')
                ->eq('user_id', $userIdParam)
                ->eq('data_hash', $hashToVerify)
                ->single()
                ->execute();

            if (!$record) {
                echo json_encode([
                    'success' => false,
                    'verified' => false,
                    'message' => 'Digital ID record not found'
                ]);
                break;
            }

            // Verify blockchain integrity
            $isValid = verifyBlockchainIntegrity($record);

            echo json_encode([
                'success' => true,
                'verified' => $isValid,
                'message' => $isValid ? 'Digital ID is valid and verified' : 'Digital ID verification failed',
                'record' => $record
            ]);
            break;

        case 'download_certificate':
            // Generate and download digital certificate
            $userIdParam = $_GET['user_id'] ?? $userId;

            // Check permissions
            if ($userIdParam !== $userId && !in_array($userRole, ['eb_admin', 'eb_president'])) {
                throw new Exception('Permission denied');
            }

            $userProfile = $supabaseClient->from('user_profiles')
                ->select('*')
                ->eq('id', $userIdParam)
                ->single()
                ->execute();

            if (!$userProfile || $userProfile['verification_status'] !== 'verified') {
                throw new Exception('User not verified or profile not found');
            }

            // Generate PDF certificate
            $certificateData = generateDigitalCertificate($userProfile);

            // Set headers for file download
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="digital_certificate_' . $userProfile['id'] . '.pdf"');
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');

            echo $certificateData;
            exit;
            break;

        case 'get_blockchain_history':
            // Get blockchain history for a user
            $userIdParam = $_GET['user_id'] ?? $userId;

            // Check permissions
            if ($userIdParam !== $userId && !in_array($userRole, ['eb_admin', 'eb_president', 'eb_vp_internal'])) {
                throw new Exception('Permission denied');
            }

            $records = $supabaseClient->from('blockchain_records')
                ->select('*')
                ->eq('user_id', $userIdParam)
                ->order('created_at', ['ascending' => false])
                ->execute();

            echo json_encode([
                'success' => true,
                'blockchain_history' => $records
            ]);
            break;

        case 'revoke_digital_id':
            // Revoke digital ID (admin only)
            if (!in_array($userRole, ['eb_admin', 'eb_president'])) {
                throw new Exception('Admin permission required');
            }

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed');
            }

            $userIdParam = $_GET['user_id'] ?? '';
            if (empty($userIdParam)) {
                throw new Exception('User ID required');
            }

            $reason = $_POST['reason'] ?? 'Administrative revocation';

            // Create revocation record
            $revocationData = [
                'user_id' => $userIdParam,
                'record_type' => 'revocation',
                'data_hash' => hash('sha256', $userIdParam . 'revoked' . time()),
                'previous_hash' => getLastBlockHash(),
                'block_data' => json_encode([
                    'revoked_user_id' => $userIdParam,
                    'reason' => $reason,
                    'revoked_by' => $userId,
                    'revoked_at' => date('c')
                ]),
                'timestamp' => date('c'),
                'created_at' => date('c')
            ];

            $supabaseClient->from('blockchain_records')->insert($revocationData)->execute();

            // Update user profile
            $supabaseClient->from('user_profiles')
                ->update([
                    'verification_status' => 'revoked',
                    'updated_at' => date('c')
                ])
                ->eq('id', $userIdParam)
                ->execute();

            echo json_encode([
                'success' => true,
                'message' => 'Digital ID revoked successfully'
            ]);
            break;

        case 'get_identity_stats':
            // Get digital identity statistics (admin only)
            if (!in_array($userRole, ['eb_admin', 'eb_president', 'eb_vp_internal'])) {
                throw new Exception('Permission denied');
            }

            // Get verification status counts
            $verifiedCount = $supabaseClient->from('user_profiles')
                ->select('count', ['count' => 'exact'])
                ->eq('verification_status', 'verified')
                ->execute();

            $pendingCount = $supabaseClient->from('user_profiles')
                ->select('count', ['count' => 'exact'])
                ->eq('verification_status', 'pending')
                ->execute();

            $revokedCount = $supabaseClient->from('user_profiles')
                ->select('count', ['count' => 'exact'])
                ->eq('verification_status', 'revoked')
                ->execute();

            // Get blockchain records count
            $blockchainCount = $supabaseClient->from('blockchain_records')
                ->select('count', ['count' => 'exact'])
                ->execute();

            echo json_encode([
                'success' => true,
                'stats' => [
                    'verified_users' => $verifiedCount['count'] ?? 0,
                    'pending_users' => $pendingCount['count'] ?? 0,
                    'revoked_users' => $revokedCount['count'] ?? 0,
                    'total_blockchain_records' => $blockchainCount['count'] ?? 0
                ]
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action specified'
            ]);
            break;
    }

} catch (Exception $e) {
    error_log('Digital ID API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Helper functions

function calculateIdentityScore($userProfile, $blockchainRecords) {
    $score = 0;

    // Base score for having a profile
    $score += 20;

    // Verification status
    if (($userProfile['verification_status'] ?? '') === 'verified') {
        $score += 30;
    }

    // Blockchain records
    $recordCount = count($blockchainRecords);
    $score += min($recordCount * 5, 25); // Max 25 points for records

    // Account age (older accounts get more points)
    if (isset($userProfile['created_at'])) {
        $accountAge = time() - strtotime($userProfile['created_at']);
        $accountAgeYears = $accountAge / (365 * 24 * 60 * 60);
        $score += min($accountAgeYears * 2, 15); // Max 15 points for account age
    }

    // Role-based bonus
    $roleBonuses = [
        'eb_president' => 10,
        'eb_vp_internal' => 8,
        'eb_treasurer' => 6,
        'eb_secretary' => 6,
        'member' => 5
    ];
    $score += $roleBonuses[$userProfile['role']] ?? 0;

    return min($score, 100); // Max score of 100
}

function generateBlockchainRecord($userProfile) {
    // Create verification data
    $verificationData = [
        'user_id' => $userProfile['id'],
        'name' => $userProfile['name'],
        'email' => $userProfile['email'],
        'role' => $userProfile['role'],
        'institution' => $userProfile['institution'],
        'timestamp' => time(),
        'nonce' => bin2hex(random_bytes(16))
    ];

    // Generate SHA-256 hash
    $dataString = json_encode($verificationData, JSON_UNESCAPED_UNICODE);
    $hash = hash('sha256', $dataString);

    return [
        'hash' => $hash,
        'verification_data' => $verificationData
    ];
}

function getLastBlockHash() {
    global $supabaseClient;

    try {
        $lastRecord = $supabaseClient->from('blockchain_records')
            ->select('data_hash')
            ->order('created_at', ['ascending' => false])
            ->limit(1)
            ->single()
            ->execute();

        return $lastRecord['data_hash'] ?? 'genesis';
    } catch (Exception $e) {
        return 'genesis';
    }
}

function verifyBlockchainIntegrity($record) {
    // Verify the hash matches the block data
    $blockData = json_decode($record['block_data'], true);
    $expectedHash = hash('sha256', json_encode($blockData, JSON_UNESCAPED_UNICODE));

    return hash_equals($record['data_hash'], $expectedHash);
}

function generateDigitalCertificate($userProfile) {
    require_once '../../../vendor/autoload.php';

    // Create PDF certificate
    $pdf = new \Dompdf\Dompdf();
    $pdf->setPaper('A4', 'landscape');

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Digital Identity Certificate</title>
        <style>
            body { font-family: "Inter", Arial, sans-serif; margin: 0; padding: 40px; background: #f8f9fa; }
            .certificate { max-width: 800px; margin: 0 auto; background: white; padding: 60px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
            .header { text-align: center; margin-bottom: 50px; }
            .logo { font-size: 48px; color: #0B1D4A; margin-bottom: 20px; }
            .title { font-size: 36px; color: #0B1D4A; font-weight: bold; margin-bottom: 10px; }
            .subtitle { font-size: 18px; color: #D4AF37; margin-bottom: 40px; }
            .content { margin: 40px 0; }
            .field { margin-bottom: 20px; }
            .label { font-weight: bold; color: #0B1D4A; display: inline-block; width: 150px; }
            .value { color: #333; }
            .signature { margin-top: 60px; text-align: center; }
            .signature-line { border-top: 2px solid #0B1D4A; width: 200px; margin: 0 auto 10px; }
            .verification-code { margin-top: 40px; font-family: monospace; background: #f8f9fa; padding: 10px; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class="certificate">
            <div class="header">
                <div class="logo">IECEP-LSC</div>
                <div class="title">Digital Identity Certificate</div>
                <div class="subtitle">Institute of Electronics Engineers of the Philippines - Laguna Section Chapter</div>
            </div>

            <div class="content">
                <div class="field">
                    <span class="label">Certificate ID:</span>
                    <span class="value">' . strtoupper(substr($userProfile['digital_id_hash'] ?? 'pending', 0, 16)) . '</span>
                </div>
                <div class="field">
                    <span class="label">Member Name:</span>
                    <span class="value">' . htmlspecialchars($userProfile['name']) . '</span>
                </div>
                <div class="field">
                    <span class="label">Email Address:</span>
                    <span class="value">' . htmlspecialchars($userProfile['email']) . '</span>
                </div>
                <div class="field">
                    <span class="label">Role/Position:</span>
                    <span class="value">' . ucfirst(str_replace('eb_', '', $userProfile['role'])) . '</span>
                </div>
                <div class="field">
                    <span class="label">Institution:</span>
                    <span class="value">' . htmlspecialchars($userProfile['institution'] ?? 'N/A') . '</span>
                </div>
                <div class="field">
                    <span class="label">Member Since:</span>
                    <span class="value">' . date('F d, Y', strtotime($userProfile['created_at'])) . '</span>
                </div>
                <div class="field">
                    <span class="label">Verification Date:</span>
                    <span class="value">' . date('F d, Y') . '</span>
                </div>
            </div>

            <div class="signature">
                <div class="signature-line"></div>
                <div>Digital Signature Verified</div>
                <small>Blockchain Hash: ' . ($userProfile['digital_id_hash'] ?? 'N/A') . '</small>
            </div>

            <div class="verification-code">
                <strong>Verification Code:</strong><br>
                ' . chunk_split($userProfile['digital_id_hash'] ?? 'pending', 4, ' ') . '
            </div>
        </div>
    </body>
    </html>';

    $pdf->loadHtml($html);
    $pdf->render();

    return $pdf->output();
}
?>