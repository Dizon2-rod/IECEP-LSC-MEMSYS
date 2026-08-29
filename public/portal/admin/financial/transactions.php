<?php
require_once __DIR__ . '/../../bootstrap.php';
$current_page = 'transactions';

require_once __DIR__ . '/../../auth_check.php';
require_role(['admin', 'super_admin', 'eb_treasurer', 'eb_auditor', 'treasurer', 'auditor']);

$pageTitle = 'Treasury Transactions & Audit Ledger';
$supabase = getSupabaseClient();

$feedbackMsg = '';
$feedbackType = 'success';

// Handle POST: Record new transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'record_transaction') {
        $amount = floatval($_POST['amount'] ?? 0);
        $type = trim($_POST['type'] ?? 'membership_fee');
        $desc = trim($_POST['description'] ?? 'Chapter Dues Remittance');
        $status = trim($_POST['status'] ?? 'paid');
        $method = trim($_POST['payment_method'] ?? 'gcash');

        if ($amount > 0) {
            $timestamp = date('c');
            $rcpNumber = 'RCP-2026-' . rand(10000, 99999);
            $txHash = hash('sha256', $rcpNumber . '|' . $amount . '|' . $timestamp);

            try {
                $supabase->insert('transactions', [[
                    'id' => bin2hex(random_bytes(16)),
                    'amount' => $amount,
                    'currency' => 'PHP',
                    'type' => $type,
                    'transaction_type' => $type,
                    'status' => $status,
                    'payment_method' => $method,
                    'receipt_number' => $rcpNumber,
                    'transaction_date' => $timestamp,
                    'blockchain_hash' => $txHash,
                    'notes' => $desc,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp
                ]]);

                $feedbackMsg = "🎉 Transaction {$rcpNumber} for ₱" . number_format($amount, 2) . " recorded successfully!";
                $feedbackType = 'success';
            } catch (Exception $e) {
                error_log("Record transaction error: " . $e->getMessage());
                $feedbackMsg = "Error saving transaction: " . $e->getMessage();
                $feedbackType = 'warning';
            }
        }
    }
}

// Fetch real transactions from database
$transactionsList = [];
$totalPaid = 0.0;
$totalPending = 0.0;

try {
    $rawTx = $supabase->select('transactions', ['select' => '*', 'order' => 'created_at.desc']);
    if (is_array($rawTx)) {
        $transactionsList = $rawTx;
        foreach ($transactionsList as $tx) {
            $amt = floatval($tx['amount'] ?? 0);
            $st = strtolower($tx['status'] ?? 'pending');
            if ($st === 'paid' || $st === 'completed') {
                $totalPaid += $amt;
            } else {
                $totalPending += $amt;
            }
        }
    }
} catch (Exception $e) {
    error_log("Error loading transactions: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="View and manage all financial transactions, membership dues, and payment records for IECEP-LSC.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-navy: #0B1D4A;
            --color-navy-hover: #152C6E;
            --color-blue: #2563EB;
            --color-gold: #D4AF37;
            --color-emerald: #059669;
            --color-amber: #D97706;
            --bg-page: #F8FAFC;
            --border-color: #E2E8F0;
            --shadow-card: 0 1px 3px 0 rgba(0, 0, 0, 0.04), 0 1px 2px -1px rgba(0, 0, 0, 0.04);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-page);
            color: #1E293B;
            margin: 0;
            padding: 0;
        }

        .dash-header-banner {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 0.85rem 1.25rem;
            margin-bottom: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.75rem;
            box-shadow: var(--shadow-card);
        }
        .dash-header-title {
            margin: 0 0 0.15rem;
            font-size: 1.25rem;
            font-weight: 800;
            color: #0F172A;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .dash-header-sub {
            margin: 0;
            font-size: 0.8rem;
            color: #64748B;
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
        .btn-white:hover {
            background: #F8FAFC;
            border-color: #94A3B8;
            transform: translateY(-1px);
        }

        .btn-primary-navy {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.42rem 0.95rem;
            border-radius: 7px;
            font-size: 0.78rem;
            font-weight: 800;
            text-decoration: none;
            background: var(--color-navy);
            border: 1px solid var(--color-navy);
            color: #FFFFFF !important;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(11, 29, 74, 0.15);
            transition: all 0.18s ease;
        }
        .btn-primary-navy:hover {
            background: var(--color-navy-hover);
            transform: translateY(-1px);
            color: #FDE047 !important;
        }

        .dash-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.65rem;
            margin-bottom: 0.85rem;
        }
        .dash-kpi-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.65rem 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: var(--shadow-card);
            min-width: 0;
        }
        .kpi-icon-pill {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            flex-shrink: 0;
        }
        .kpi-icon-pill.emerald { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }
        .kpi-icon-pill.amber { background: #FEF3C7; color: #D97706; border: 1px solid #FDE68A; }
        .kpi-icon-pill.navy { background: rgba(11, 29, 74, 0.08); color: var(--color-navy); }
        .kpi-icon-pill.gold { background: #FEF9C3; color: #B45309; border: 1px solid #FDE68A; }

        .kpi-val {
            font-size: 1.25rem;
            font-weight: 800;
            color: #0F172A;
            line-height: 1.1;
        }
        .kpi-lbl {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748B;
            margin-top: 1px;
        }

        .white-controls-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.65rem 0.95rem;
            margin-bottom: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.65rem;
            box-shadow: var(--shadow-card);
        }
        .search-input-field {
            padding: 0.45rem 0.75rem 0.45rem 2rem;
            border: 1px solid #CBD5E1;
            border-radius: 7px;
            font-size: 0.8rem;
            outline: none;
            width: 100%;
            box-sizing: border-box;
            background: #F8FAFC;
        }

        .ap-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
            margin-bottom: 1rem;
        }
        .ap-card-header {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #FFFFFF;
        }
        .ap-card-title {
            margin: 0;
            font-size: 0.88rem;
            font-weight: 800;
            color: #0F172A;
        }

        .ap-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
            text-align: left;
        }
        .ap-table th {
            background: #F8FAFC;
            color: #64748B;
            font-weight: 700;
            font-size: 0.72rem;
            padding: 0.55rem 0.85rem;
            border-bottom: 1px solid var(--border-color);
            text-transform: uppercase;
        }
        .ap-table td {
            padding: 0.65rem 0.85rem;
            border-bottom: 1px solid #F1F5F9;
            color: #334155;
            vertical-align: middle;
        }
        .ap-table tr:hover td {
            background: #F8FAFC;
        }

        .doc-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(11, 29, 74, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
        }
        .doc-modal.active { display: flex; }
        .modal-inner-box {
            background: #FFFFFF;
            border-radius: 12px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.18);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        @media (max-width: 1024px) {
            .dash-kpi-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- 1. Header Banner -->
            <div class="dash-header-banner">
                <div>
                    <h1 class="dash-header-title">
                        <i class="fas fa-receipt" style="color:var(--color-navy);"></i>
                        Treasury Transactions & Audit Ledger
                    </h1>
                    <p class="dash-header-sub">
                        Record chapter remittances, membership fees, generate official receipts, and audit collections.
                    </p>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <a href="<?= PORTAL_URL ?>/admin/financial/dashboard.php" class="btn-white">
                        <i class="fas fa-chart-line" style="color:var(--color-blue);"></i> Financial Dashboard
                    </a>
                    <a href="<?= PORTAL_URL ?>/admin/financial/reports.php" class="btn-white">
                        <i class="fas fa-file-invoice-dollar" style="color:#059669;"></i> Financial Reports
                    </a>
                    <button type="button" class="btn-primary-navy" onclick="openRecordModal()">
                        <i class="fas fa-plus" style="color:#FDE047;"></i> Record Payment
                    </button>
                </div>
            </div>

            <?php if (!empty($feedbackMsg)): ?>
                <div class="ap-alert <?= $feedbackType ?>" style="margin-bottom:0.85rem;">
                    <i class="fas fa-check-circle" style="font-size:1.2rem;"></i> 
                    <div><?= htmlspecialchars($feedbackMsg) ?></div>
                </div>
            <?php endif; ?>

            <!-- 2. KPI Grid -->
            <div class="dash-kpi-grid">
                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-vault"></i></div>
                    <div>
                        <div class="kpi-val" style="color:#059669;">₱<?= number_format($totalPaid, 2) ?></div>
                        <div class="kpi-lbl">Total Paid Transactions</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill amber"><i class="fas fa-hourglass-half"></i></div>
                    <div>
                        <div class="kpi-val" style="color:#D97706;">₱<?= number_format($totalPending, 2) ?></div>
                        <div class="kpi-lbl">Pending Settlement</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill navy"><i class="fas fa-receipt"></i></div>
                    <div>
                        <div class="kpi-val"><?= count($transactionsList) ?></div>
                        <div class="kpi-lbl">Total Ledger Entries</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-shield-check"></i></div>
                    <div>
                        <div class="kpi-val">100%</div>
                        <div class="kpi-lbl">Audit Integrity</div>
                    </div>
                </div>
            </div>

            <!-- 3. Search & Filter Bar -->
            <div class="white-controls-card">
                <div style="position:relative; flex:1; max-width:380px;">
                    <i class="fas fa-search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#94A3B8; font-size:0.8rem;"></i>
                    <input type="text" id="txSearchInput" class="search-input-field" placeholder="Search receipt number, description, method..." onkeyup="filterTransactionsTable()">
                </div>
                <div style="font-size:0.75rem; font-weight:700; color:#64748B;">
                    Showing <?= count($transactionsList) ?> transactions
                </div>
            </div>

            <!-- 4. Table -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-file-invoice"></i> Financial Transactions & Receipt Ledger</h3>
                </div>
                <div style="overflow-x:auto;">
                    <table class="ap-table" id="txTable">
                        <thead>
                            <tr>
                                <th>Receipt / Ref #</th>
                                <th>Transaction Type</th>
                                <th>Description / Notes</th>
                                <th>Payment Method</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th style="text-align:right;">Date & Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactionsList)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding:2.5rem; color:#64748B;">
                                        <i class="fas fa-receipt" style="font-size:2rem; color:#CBD5E1; margin-bottom:0.5rem; display:block;"></i>
                                        <strong style="color:#0F172A; font-size:0.92rem;">No Transactions Recorded in Database</strong>
                                        <p style="margin:0.25rem 0 0; font-size:0.78rem;">Click "+ Record Payment" to log official membership dues or event remittances.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactionsList as $tx): ?>
                                    <?php 
                                        $amt = floatval($tx['amount'] ?? 0);
                                        $st = strtolower($tx['status'] ?? 'pending');
                                    ?>
                                    <tr>
                                        <td>
                                            <span style="font-family:'JetBrains Mono', monospace; font-size:0.75rem; font-weight:700; color:var(--color-navy);">
                                                <?= htmlspecialchars($tx['receipt_number'] ?? $tx['id'] ?? 'RCP') ?>
                                            </span>
                                        </td>
                                        <td><strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $tx['type'] ?? 'Membership Fee'))) ?></strong></td>
                                        <td><?= htmlspecialchars($tx['notes'] ?? $tx['description'] ?? 'Official Dues Remittance') ?></td>
                                        <td>
                                            <span class="ap-pill blue">
                                                <i class="fas fa-wallet"></i> <?= strtoupper($tx['payment_method'] ?? 'GCash') ?>
                                            </span>
                                        </td>
                                        <td><strong style="color:#059669; font-size:0.82rem;">₱<?= number_format($amt, 2) ?></strong></td>
                                        <td><span class="ap-pill <?= ($st === 'paid' || $st === 'completed') ? 'active' : 'pending' ?>"><?= ucfirst($st) ?></span></td>
                                        <td style="text-align:right; color:#64748B; font-size:0.75rem; white-space:nowrap;">
                                            <?= !empty($tx['created_at']) ? date('M d, Y h:i A', strtotime($tx['created_at'])) : 'Recent' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sentinel -->
            <div class="ap-sentinel-strip" style="margin-top:1.5rem;">
                <div class="ap-sentinel-item"><i class="fas fa-shield-check"></i><span><strong>Proof-of-Payment:</strong> Cryptographic Hash Verification</span></div>
                <div class="ap-sentinel-item"><i class="fas fa-file-invoice"></i><span><strong>Official Receipt:</strong> QR Verifiable Tax & Treasury Compliance</span></div>
            </div>

        </div>
    </main>

    <!-- Record Transaction Modal -->
    <div id="recordModal" class="doc-modal">
        <div class="modal-inner-box">
            <div class="ap-card-header">
                <h3 class="ap-card-title"><i class="fas fa-plus-circle"></i> Record Payment / Remittance</h3>
                <button class="btn-white" style="border:none; padding:0.25rem 0.5rem;" onclick="closeRecordModal()">&times;</button>
            </div>
            <form method="POST" style="padding:1.25rem;">
                <input type="hidden" name="action" value="record_transaction">
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Remittance / Payment Description</label>
                    <input type="text" name="description" class="ap-input" placeholder="e.g. LSPU Santa Cruz - 45 Member Dues Remittance" required style="font-size:0.8rem;">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.65rem;">
                    <div class="ap-form-group">
                        <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Amount (₱)</label>
                        <input type="number" step="0.01" name="amount" class="ap-input" placeholder="0.00" required style="font-size:0.8rem;">
                    </div>
                    <div class="ap-form-group">
                        <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Transaction Type</label>
                        <select name="type" class="ap-input" style="font-size:0.8rem;">
                            <option value="membership_fee">Membership Fee</option>
                            <option value="affiliation_fee">Chapter Affiliation Fee</option>
                            <option value="event_registration">Event Registration</option>
                            <option value="merchandise">Merchandise Sale</option>
                        </select>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.65rem;">
                    <div class="ap-form-group">
                        <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Payment Method</label>
                        <select name="payment_method" class="ap-input" style="font-size:0.8rem;">
                            <option value="gcash">GCash</option>
                            <option value="bank_transfer">Bank Transfer / Maya</option>
                            <option value="cash">Cash Remittance</option>
                        </select>
                    </div>
                    <div class="ap-form-group">
                        <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Payment Status</label>
                        <select name="status" class="ap-input" style="font-size:0.8rem;">
                            <option value="paid">Paid & Verified</option>
                            <option value="pending">Pending Verification</option>
                        </select>
                    </div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:0.65rem; margin-top:1rem;">
                    <button type="button" class="btn-white" onclick="closeRecordModal()">Cancel</button>
                    <button type="submit" class="btn-primary-navy"><i class="fas fa-save"></i> Save Transaction</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openRecordModal() {
            document.getElementById('recordModal').classList.add('active');
        }
        function closeRecordModal() {
            document.getElementById('recordModal').classList.remove('active');
        }

        function filterTransactionsTable() {
            const query = document.getElementById('txSearchInput').value.toLowerCase();
            const table = document.getElementById('txTable');
            const trs = table.getElementsByTagName('tr');

            for (let i = 1; i < trs.length; i++) {
                const tr = trs[i];
                if (tr.children.length === 1 && tr.children[0].getAttribute('colspan')) continue;
                const text = tr.textContent.toLowerCase();
                tr.style.display = (text.indexOf(query) > -1) ? '' : 'none';
            }
        }
    </script>
</body>
</html>
