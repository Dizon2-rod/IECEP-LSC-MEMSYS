<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/autoload.php';

$certificateNumber = $_GET['cert'] ?? '';
$hash = $_GET['hash'] ?? '';

$supabaseConfig = require __DIR__ . '/includes/supabase.php';
$supabase = new \App\Lib\SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);
$blockchain = new \App\Lib\BlockchainService($supabase);

$certificate = null;
$verified = false;
$error = null;

if (!empty($certificateNumber) || !empty($hash)) {
    try {
        $filters = [];
        if (!empty($certificateNumber)) {
            $filters['certificate_number'] = 'eq.' . $certificateNumber;
        }
        if (!empty($hash)) {
            $filters['blockchain_hash'] = 'eq.' . $hash;
        }

        $certificates = $supabase->select('certificates', $filters);
        
        if (!empty($certificates)) {
            $certificate = $certificates[0];
            
            // Verify blockchain integrity
            $verified = $blockchain->verify('certificate', $certificate['certificate_number']);
            
            // Get member and event details
            $member = $supabase->select('members', ['id' => 'eq.' . $certificate['member_id']])[0] ?? null;
            $event = $supabase->select('events', ['id' => 'eq.' . $certificate['event_id']])[0] ?? null;
            
            $certificate['member_name'] = $member ? ($member['first_name'] . ' ' . $member['last_name']) : 'Unknown';
            $certificate['event_title'] = $event['title'] ?? 'Unknown Event';
        } else {
            $error = 'Certificate not found';
        }
    } catch (Exception $e) {
        $error = 'Verification error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Verification - IECEP-LSC</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        :root {
            --navy: #0A2F6C;
            --gold: #F5A623;
            --green: #10b981;
            --red: #ef4444;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            padding: 48px;
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        .logo {
            width: 80px;
            height: 80px;
            background: var(--navy);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 2rem;
        }
        h1 { color: var(--navy); margin-bottom: 8px; }
        .subtitle { color: #64748b; }
        .search-form {
            margin: 30px 0;
            padding: 20px;
            background: #f8fafc;
            border-radius: 12px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--navy);
        }
        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }
        button {
            width: 100%;
            padding: 12px;
            background: var(--navy);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        button:hover { background: var(--gold); color: var(--navy); }
        .result {
            margin-top: 30px;
            padding: 30px;
            border-radius: 12px;
        }
        .verified {
            background: #f0fdf4;
            border: 2px solid var(--green);
        }
        .not-verified {
            background: #fef2f2;
            border: 2px solid var(--red);
        }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .verified .status-badge {
            background: var(--green);
            color: white;
        }
        .not-verified .status-badge {
            background: var(--red);
            color: white;
        }
        .cert-details {
            margin-top: 20px;
        }
        .detail-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .detail-label {
            font-weight: 600;
            color: var(--navy);
            width: 200px;
        }
        .detail-value {
            color: #475569;
            flex: 1;
        }
        .hash {
            font-family: monospace;
            font-size: 12px;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">🎓</div>
            <h1>Certificate Verification</h1>
            <p class="subtitle">IECEP - Luzon South Central</p>
        </div>

        <div class="search-form">
            <form method="GET">
                <div class="form-group">
                    <label>Certificate Number</label>
                    <input type="text" name="cert" placeholder="CERT-2025-00001" value="<?= htmlspecialchars($certificateNumber) ?>">
                </div>
                <div class="form-group">
                    <label>Or Blockchain Hash</label>
                    <input type="text" name="hash" placeholder="Enter blockchain hash" value="<?= htmlspecialchars($hash) ?>">
                </div>
                <button type="submit">Verify Certificate</button>
            </form>
        </div>

        <?php if ($certificate): ?>
            <div class="result <?= $verified ? 'verified' : 'not-verified' ?>">
                <div class="status-badge">
                    <?= $verified ? '✓ Verified' : '✗ Not Verified' ?>
                </div>
                
                <?php if ($verified): ?>
                    <p><strong>This certificate is authentic and verified on the blockchain.</strong></p>
                    
                    <div class="cert-details">
                        <div class="detail-row">
                            <div class="detail-label">Certificate Number:</div>
                            <div class="detail-value"><?= htmlspecialchars($certificate['certificate_number']) ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Recipient:</div>
                            <div class="detail-value"><?= htmlspecialchars($certificate['member_name']) ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Event:</div>
                            <div class="detail-value"><?= htmlspecialchars($certificate['event_title']) ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Issue Date:</div>
                            <div class="detail-value"><?= date('F d, Y', strtotime($certificate['issue_date'])) ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Blockchain Hash:</div>
                            <div class="detail-value hash"><?= htmlspecialchars($certificate['blockchain_hash']) ?></div>
                        </div>
                    </div>
                <?php else: ?>
                    <p><strong>Warning:</strong> This certificate could not be verified. It may have been tampered with or is not authentic.</p>
                <?php endif; ?>
            </div>
        <?php elseif ($error): ?>
            <div class="result not-verified">
                <div class="status-badge">✗ Error</div>
                <p><?= htmlspecialchars($error) ?></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
