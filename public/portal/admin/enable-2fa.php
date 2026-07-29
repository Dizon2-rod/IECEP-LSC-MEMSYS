<?php
require_once __DIR__ . '/../auth_check.php';
require_role(['admin']);

require_once __DIR__ . '/../../../includes/role-config.php';
require_once __DIR__ . '/../bootstrap.php';

require_once __DIR__ . '/../../../src/lib/BlockchainService.php';

$current_page = 'security';

$user = get_user_info();
$supabase = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);

// Check if MFA is already enabled
try {
    $profileData = $supabase->select('user_profiles', [
        'user_id' => 'eq.' . ($user['id'] ?? '')
    ]);
    $profile = $profileData[0] ?? [];
    $mfaEnabled = $profile['mfa_enabled'] ?? false;
} catch (Exception $e) {
    $mfaEnabled = false;
}

// Generate TOTP secret if not exists
if (!$mfaEnabled && empty($profile['mfa_secret'] ?? '')) {
    $secret = strtoupper(substr(md5($user['id'] . time()), 0, 16));
    // Update user profile with secret
    try {
        $supabase->update('user_profiles', ['mfa_secret' => $secret], $profile['id'] ?? '');
        $mfaSecret = $secret;
    } catch (Exception $e) {
        $mfaSecret = '';
    }
  
} else {
    $mfaSecret = $profile['mfa_secret'] ?? '';
}

// Generate QR code URL for authenticator apps
$issuer = 'IECEP-LSC';
$account = $user['email'] ?? 'admin@iecep-lsc.org';
$qrUrl = "otpauth://totp/{$issuer}:{$account}?secret={$mfaSecret}&issuer={$issuer}";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/../../../includes/head-meta.php'; ?>
    <title>Enable Two-Factor Authentication - Admin Portal</title>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once __DIR__ . '/../../../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="container py-5">
                <div class="mb-4">
                    <h1 class="h2 mb-2">Two-Factor Authentication</h1>
                    <p class="text-muted">Add an extra layer of security to your admin account</p>
                </div>

                <?php if ($mfaEnabled): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-shield-alt me-2"></i>
                        Two-Factor Authentication is already enabled for your account.
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Disable 2FA</h5>
                            <p class="text-muted">You can disable two-factor authentication if needed.</p>
                            <button class="btn btn-danger" onclick="disable2FA()">
                                <i class="fas fa-times me-1"></i>Disable 2FA
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-4">Setup Two-Factor Authentication</h5>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="mb-3">Step 1: Install an Authenticator App</h6>
                                    <p class="text-muted small mb-3">
                                        Download and install a TOTP authenticator app on your mobile device:
                                    </p>
                                    <ul class="list-unstyled">
                                        <li><i class="fab fa-google me-2"></i>Google Authenticator</li>
                                        <li><i class="fas fa-shield-alt me-2"></i>Authy</li>
                                        <li><i class="fas fa-mobile-alt me-2"></i>Microsoft Authenticator</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="mb-3">Step 2: Scan QR Code</h6>
                                    <div class="text-center mb-3">
                                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($qrUrl) ?>" alt="QR Code" style="border: 1px solid #ddd; padding: 10px; border-radius: 8px;">
                                    </div>
                                    <p class="text-muted small text-center">
                                        Or enter this code manually: <code><?= $mfaSecret ?></code>
                                    </p>
                                </div>
                            </div>

                            <hr class="my-4">

                            <h6 class="mb-3">Step 3: Verify Setup</h6>
                            <p class="text-muted small mb-3">
                                Enter the 6-digit code from your authenticator app to verify the setup:
                            </p>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Authentication Code</label>
                                        <input type="text" class="form-control" id="totpCode" placeholder="123456" maxlength="6" pattern="[0-9]{6}">
                                    </div>
                                    <button class="btn btn-primary" onclick="verifyAndEnable()">
                                        <i class="fas fa-check me-1"></i>Verify and Enable 2FA
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        async function verifyAndEnable() {
            const code = document.getElementById('totpCode').value;
            
            if (!code || code.length !== 6 || !/^\d{6}$/.test(code)) {
                alert('Please enter a valid 6-digit code');
                return;
            }

            try {
                const response = await fetch('/api/enable-2fa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ code })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Two-Factor Authentication enabled successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (result.error || 'Invalid code. Please try again.'));
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        async function disable2FA() {
            if (!confirm('Are you sure you want to disable two-factor authentication? This will reduce the security of your account.')) {
                return;
            }

            try {
                const response = await fetch('/api/disable-2fa.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                });

                const result = await response.json();

                if (result.success) {
                    alert('Two-Factor Authentication disabled successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (result.error || 'Failed to disable 2FA'));
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
    </script>
</body>
</html>
