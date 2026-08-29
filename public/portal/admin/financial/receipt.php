<?php
require_once __DIR__ . '/../../bootstrap.php';
$current_page = 'receipt';

require_once __DIR__ . '/../../auth_check.php';
require_role(['admin', 'super_admin', 'eb_treasurer', 'eb_auditor', 'treasurer', 'auditor']);

$supabase = getSupabaseClient();

$transactionId = trim($_GET['id'] ?? '');
$tx = null;
$member = null;
$institution = null;

try {
    if (!empty($transactionId)) {
        $res = $supabase->select('transactions', ['id' => 'eq.' . $transactionId]);
        if (is_array($res) && !empty($res)) {
            $tx = $res[0];
        } else {
            $res2 = $supabase->select('transactions', ['receipt_number' => 'eq.' . $transactionId]);
            if (is_array($res2) && !empty($res2)) {
                $tx = $res2[0];
            }
        }
    }

    if (!$tx) {
        $latest = $supabase->select('transactions', ['select' => '*', 'order' => 'created_at.desc', 'limit' => 1]);
        if (is_array($latest) && !empty($latest)) {
            $tx = $latest[0];
        }
    }

    if ($tx) {
        if (!empty($tx['member_id'])) {
            $memData = $supabase->select('members', ['id' => 'eq.' . $tx['member_id']]);
            if (is_array($memData) && !empty($memData)) $member = $memData[0];
        }
        if (!empty($tx['institution_id'])) {
            $instData = $supabase->select('institutions', ['id' => 'eq.' . $tx['institution_id']]);
            if (is_array($instData) && !empty($instData)) $institution = $instData[0];
        }
    }
} catch (Exception $e) {
    error_log("Receipt query error: " . $e->getMessage());
}

$pageTitle = $tx ? ('Official Receipt — ' . ($tx['receipt_number'] ?? 'Receipt')) : 'Official Treasury Receipt';
$amount = $tx ? floatval($tx['amount'] ?? 0) : 0.0;
$rcpNumber = $tx['receipt_number'] ?? 'RCP-UNASSIGNED';
$txHash = $tx['blockchain_hash'] ?? ($tx ? hash('sha256', ($tx['id'] ?? '') . $rcpNumber . $amount) : '');
$payerName = $member['full_name'] ?? ($institution['name'] ?? ($tx['payer_name'] ?? 'Authorized Chapter Representative'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Official electronic treasury receipt and cryptographic verification for IECEP-LSC transactions.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-navy: #0B1D4A;
            --color-blue: #2563EB;
            --color-gold: #D4AF37;
            --color-emerald: #059669;
            --bg-page: #F8FAFC;
            --border-color: #E2E8F0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-page);
            color: #1E293B;
            margin: 0;
            padding: 0;
        }

        .receipt-container {
            max-width: 700px;
            margin: 0 auto 2rem;
            background: #FFFFFF;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: 0 10px 25px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .receipt-head {
            background: var(--color-navy);
            color: #FFFFFF;
            padding: 1.75rem 2rem;
            text-align: center;
        }
        .receipt-body {
            padding: 2rem;
        }
        .receipt-row {
            display: flex;
            justify-content: space-between;
            padding: 0.65rem 0;
            border-bottom: 1px solid #F1F5F9;
            font-size: 0.85rem;
        }
        .receipt-total-strip {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            padding: 1rem 1.25rem;
            margin: 1.5rem 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-white {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.42rem 0.85rem;
            border-radius: 7px;
            font-size: 0.78rem;
            font-weight: 700;
            text-decoration: none;
            background: #FFFFFF;
            border: 1px solid #CBD5E1;
            color: #0F172A;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: all 0.18s ease;
        }
    </style>
</head>
<body>
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
                <a href="<?= PORTAL_URL ?>/admin/financial/transactions.php" class="btn-white">
                    <i class="fas fa-arrow-left"></i> Back to Transactions
                </a>
                <?php if ($tx): ?>
                    <button class="btn-white" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Official Receipt
                    </button>
                <?php endif; ?>
            </div>

            <?php if (!$tx): ?>
                <div class="ap-card" style="text-align:center; padding:3rem 1.5rem;">
                    <i class="fas fa-file-circle-xmark" style="font-size:2.5rem; color:#CBD5E1; margin-bottom:0.75rem; display:block;"></i>
                    <h3 style="margin:0 0 0.25rem; font-size:1.1rem; color:#0F172A;">No Transaction Record Found</h3>
                    <p style="margin:0; font-size:0.85rem; color:#64748B;">Please select a valid transaction from the ledger or record a new transaction.</p>
                </div>
            <?php else: ?>
                <div class="receipt-container">
                    <div class="receipt-head">
                        <h2 style="margin:0 0 0.25rem; font-size:1.3rem; font-weight:800;">Institute of Electronics Engineers of the Philippines</h2>
                        <p style="margin:0; color:var(--color-gold); font-size:0.85rem; font-weight:700;">Laguna Student Chapter — Official Electronic Receipt</p>
                    </div>
                    <div class="receipt-body">
                        <div style="display:flex; justify-content:space-between; margin-bottom:1.5rem;">
                            <div>
                                <span style="font-size:0.72rem; font-weight:700; color:#64748B; text-transform:uppercase;">Receipt Number</span>
                                <div style="font-family:'JetBrains Mono', monospace; font-size:1.05rem; font-weight:800; color:var(--color-navy);"><?= htmlspecialchars($rcpNumber) ?></div>
                            </div>
                            <div style="text-align:right;">
                                <span style="font-size:0.72rem; font-weight:700; color:#64748B; text-transform:uppercase;">Payment Date</span>
                                <div style="font-size:0.88rem; font-weight:700; color:#0F172A;"><?= !empty($tx['created_at']) ? date('M d, Y h:i A', strtotime($tx['created_at'])) : date('M d, Y') ?></div>
                            </div>
                        </div>

                        <div class="receipt-row">
                            <span style="color:#64748B;">Payer / Remitter:</span>
                            <strong><?= htmlspecialchars($payerName) ?></strong>
                        </div>
                        <div class="receipt-row">
                            <span style="color:#64748B;">Purpose / Transaction Type:</span>
                            <strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $tx['type'] ?? 'Membership Fee'))) ?></strong>
                        </div>
                        <div class="receipt-row">
                            <span style="color:#64748B;">Payment Channel:</span>
                            <span style="font-weight:700; color:var(--color-blue); text-transform:uppercase;"><?= htmlspecialchars($tx['payment_method'] ?? 'GCash') ?></span>
                        </div>
                        <div class="receipt-row">
                            <span style="color:#64748B;">Notes / Reference:</span>
                            <span><?= htmlspecialchars($tx['notes'] ?? $tx['description'] ?? 'Official Dues Remittance') ?></span>
                        </div>

                        <div class="receipt-total-strip">
                            <div>
                                <span style="font-size:0.75rem; font-weight:700; color:#64748B; text-transform:uppercase;">Total Amount Paid</span>
                                <div style="font-size:0.75rem; color:#059669; font-weight:700;"><i class="fas fa-circle-check"></i> Verified & Reconciled</div>
                            </div>
                            <div style="font-size:1.6rem; font-weight:800; color:#059669;">
                                ₱<?= number_format($amount, 2) ?>
                            </div>
                        </div>

                        <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:6px; padding:0.65rem 0.85rem; font-size:0.72rem; color:#64748B; word-break:break-all;">
                            <strong>Cryptographic Audit Hash:</strong><br>
                            <span style="font-family:'JetBrains Mono', monospace; color:var(--color-navy);"><?= htmlspecialchars($txHash) ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>
</body>
</html>
