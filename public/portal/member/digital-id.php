<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/middleware/auth.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'member') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$current_page = 'digital-id';

$user = get_user_info();
$member_id = $_SESSION['member_id'] ?? $user['member_id'] ?? null;

if (!$member_id) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$supabase = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);

// Fetch member details
try {
    $memberData = $supabase->select('members', [
        'id' => 'eq.' . $member_id
    ]);
    $member = $memberData[0] ?? [];
} catch (Exception $e) {
    $member = [];
}

// Check if digital ID exists
$digitalIdUrl = $member['digital_id_url'] ?? null;
$hasDigitalId = !empty($digitalIdUrl);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/../../../includes/head-meta.php'; ?>
    <title>My Digital ID - Member Portal</title>
    <style>
        .digital-id-card {
            background: linear-gradient(135deg, #0B1D4A 0%, #1E3A6E 100%);
            border-radius: 16px;
            padding: 32px;
            color: white;
            max-width: 400px;
            margin: 0 auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
        }
        .digital-id-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(212,175,55,0.1) 0%, transparent 70%);
        }
        .digital-id-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            position: relative;
            z-index: 1;
        }
        .digital-id-logo {
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .digital-id-logo img {
            width: 40px;
            height: 40px;
        }
        .digital-id-title {
            flex: 1;
        }
        .digital-id-title h3 {
            margin: 0;
            font-size: 14px;
            opacity: 0.8;
        }
        .digital-id-title h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
        }
        .digital-id-body {
            position: relative;
            z-index: 1;
        }
        .member-photo {
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid #D4AF37;
        }
        .member-photo i {
            font-size: 40px;
            color: rgba(255,255,255,0.5);
        }
        .member-name {
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .member-details {
            text-align: center;
            opacity: 0.9;
            font-size: 14px;
            margin-bottom: 24px;
        }
        .member-details p {
            margin: 4px 0;
        }
        .qr-code {
            text-align: center;
            margin-top: 20px;
        }
        .qr-code img {
            width: 120px;
            height: 120px;
            border: 4px solid white;
            border-radius: 8px;
        }
        .verified-badge {
            position: absolute;
            top: 16px;
            right: 16px;
            background: #22C55E;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            z-index: 2;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once __DIR__ . '/../../../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="container py-5">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h2 mb-2">My Digital ID</h1>
                        <p class="text-muted">Your official IECEP-LSC membership identification</p>
                    </div>
                    <button class="btn btn-primary" onclick="downloadPDF()">
                        <i class="fas fa-download me-2"></i>Download as PDF
                    </button>
                </div>

                <?php if (!$hasDigitalId): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Your digital ID has not been generated yet. Please contact your school officer or wait for your digital ID to be sent via email.
                    </div>
                <?php else: ?>
                    <div class="digital-id-card" id="digitalIdCard">
                        <div class="verified-badge">
                            <i class="fas fa-check-circle me-1"></i>Verified
                        </div>
                        <div class="digital-id-header">
                            <div class="digital-id-logo">
                                <img src="<?= PUBLIC_URL ?>/assets/icons/iecep-logo.png" alt="IECEP-LSC">
                            </div>
                            <div class="digital-id-title">
                                <h3>IECEP-LSC</h3>
                                <h2>Member ID Card</h2>
                            </div>
                        </div>
                        <div class="digital-id-body">
                            <div class="member-photo">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="member-name">
                                <?= htmlspecialchars($member['full_name'] ?? 'Member Name') ?>
                            </div>
                            <div class="member-details">
                                <p><strong><?= htmlspecialchars($member['school_affiliate'] ?? 'Institution') ?></strong></p>
                                <p><code><?= htmlspecialchars($member['membership_id'] ?? 'IECEP-XXXX-XXXX') ?></code></p>
                                <p><?= date('Y') ?> IECEP-LSC Member</p>
                            </div>
                            <div class="qr-code">
                                <img src="<?= htmlspecialchars($digitalIdUrl) ?>" alt="QR Code">
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function downloadPDF() {
            // Use DOMPDF to generate PDF
            window.print();
        }
    </script>
</body>
</html>

