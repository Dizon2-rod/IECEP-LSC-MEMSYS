<?php
if (!isset($current_page)) { $current_page = 'financial-reports'; }
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../auth_check.php';
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/role-config.php';

require_role(['school_officer', 'admin', 'super_admin']);

// Get institution ID and user info
$user = $_SESSION['user'] ?? [];
$userId = $user['id'] ?? $_SESSION['user_id'] ?? null;
$institutionId = $_SESSION['institution_id'] ?? $user['institution_id'] ?? null;
$institutionName = 'Affiliated Institution';

$supabase = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);

if (!$institutionId && $userId) {
    try {
        $userProfile = $supabase->select('user_profiles', [
            'user_id' => 'eq.' . $userId,
            'limit' => 1,
        ]);
        if (is_array($userProfile) && isset($userProfile[0]['institution_id'])) {
            $institutionId = $userProfile[0]['institution_id'];
        }
        if (!$institutionId) {
            $memberData = $supabase->select('members', [
                'user_id' => 'eq.' . $userId,
                'limit' => 1,
            ]);
            if (is_array($memberData) && isset($memberData[0]['institution_id'])) {
                $institutionId = $memberData[0]['institution_id'];
            }
        }
    } catch (Exception $e) {}
}

if (!$institutionId) {
    try {
        $instList = $supabase->select('institutions', ['status' => 'eq.active', 'limit' => 1]);
        if (is_array($instList) && isset($instList[0]['id'])) {
            $institutionId = $instList[0]['id'];
            $institutionName = $instList[0]['name'] ?? 'Affiliated Institution';
        }
    } catch (Exception $e) {}
}

if ($institutionId) {
    $_SESSION['institution_id'] = $institutionId;
    try {
        $instResult = $supabase->select('institutions', [
            'id' => 'eq.' . $institutionId,
            'limit' => 1,
        ]);
        if (is_array($instResult) && isset($instResult[0]['name'])) {
            $institutionName = $instResult[0]['name'];
        }
    } catch (Exception $e) {}
}

// Get member count
$member_count = 0;
try {
    $members = $supabase->select('members', [
        'institution_id' => 'eq.' . $institutionId,
    ]);
    $member_count = is_array($members) ? count($members) : 0;
} catch (Exception $e) {}

// Get fee bracket
$fee_per_member = 50; // Default fallback
try {
    $fee_bracket = $supabase->select('fee_brackets', [
        'min_members' => 'lte.' . $member_count,
        'max_members' => 'gte.' . $member_count,
        'limit' => 1,
    ]);
    if (is_array($fee_bracket) && isset($fee_bracket[0]['fee_per_member'])) {
        $fee_per_member = (float)$fee_bracket[0]['fee_per_member'];
    }
} catch (Exception $e) {}

$total_annual_fee = $fee_per_member * $member_count;

// Get transactions
$transactions = [];
$total_paid = 0;
try {
    $transactions = $supabase->select('transactions', [
        'institution_id' => 'eq.' . $institutionId,
        'order' => 'created_at.desc',
    ]);
    if (is_array($transactions)) {
        foreach ($transactions as $tx) {
            if (($tx['status'] ?? '') === 'paid') {
                $total_paid += (float)($tx['amount'] ?? 0);
            }
        }
    } else {
        $transactions = [];
    }
} catch (Exception $e) {
    $transactions = [];
}

$balance_due = max(0, $total_annual_fee - $total_paid);
$paid_percentage = $total_annual_fee > 0 ? min(100, round(($total_paid / $total_annual_fee) * 100)) : 100;

// Get event payments
$event_payments = [];
try {
    $event_payments = $supabase->select('transactions', [
        'institution_id' => 'eq.' . $institutionId,
        'type' => 'eq.event_fee',
        'order' => 'created_at.desc',
    ]);
    if (!is_array($event_payments)) {
        $event_payments = [];
    }
} catch (Exception $e) {
    $event_payments = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports - IECEP-LSC MEMSYS</title>
    <?php require_once __DIR__ . '/../../../../includes/head-meta.php'; ?>
    <style>
        .statement-card {
            background: linear-gradient(135deg, rgba(11, 29, 74, 0.03) 0%, rgba(212, 175, 55, 0.06) 100%);
            border: 1px solid var(--memsys-border);
            border-radius: var(--radius-lg);
            padding: 1.75rem;
        }

        .stat-icon.icon-blue { background: rgba(30, 58, 110, 0.12); color: #1e3a6e; }
        .stat-icon.icon-gold { background: rgba(212, 175, 55, 0.15); color: #b8960c; }
        .stat-icon.icon-emerald { background: rgba(16, 185, 129, 0.15); color: #059669; }
        .stat-icon.icon-amber { background: rgba(245, 158, 11, 0.15); color: #d97706; }

        .progress-bar-custom {
            height: 10px;
            border-radius: 5px;
            background: #e2e8f0;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981, #059669);
            border-radius: 5px;
            transition: width 0.6s ease;
        }

        @media print {
            #sidebar, .sidebar-toggle, .header-actions, .no-print {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            .card {
                box-shadow: none !important;
                border: 1px solid #ccc !important;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once __DIR__ . '/../../../../includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="container py-4">
                <!-- Clean Page Header -->
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 pb-2 border-bottom">
                    <div>
                        <div class="text-muted small mb-1">
                            <a href="<?= BASE_URL ?>/public/portal/school-officer/dashboard.php" class="text-muted text-decoration-none">School Portal</a>
                            <span class="mx-1">/</span>
                            <span>Financial</span>
                            <span class="mx-1">/</span>
                            <span class="text-dark fw-semibold">Reports & Statement</span>
                        </div>
                        <h2 class="fw-bold text-dark mb-1">
                            <i class="fas fa-file-invoice-dollar text-primary me-2"></i>Financial Reports & Billing
                        </h2>
                        <div class="d-flex align-items-center gap-2 text-muted small">
                            <span>Affiliated Chapter:</span>
                            <strong class="text-dark"><i class="fas fa-university me-1 text-secondary"></i><?= htmlspecialchars($institutionName) ?></strong>
                            <span>• AY <?= date('Y') ?>–<?= date('Y') + 1 ?></span>
                        </div>
                    </div>
                    <div class="d-flex gap-2 header-actions">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                            <i class="fas fa-print me-1"></i> Print Statement
                        </button>
                        <a href="<?= BASE_URL ?>/public/portal/school-officer/financial/receipts.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-receipt me-1"></i> View Receipts
                        </a>
                        <a href="<?= BASE_URL ?>/public/portal/school-officer/dashboard.php" class="btn btn-sm btn-outline">
                            <i class="fas fa-arrow-left me-1"></i> Dashboard
                        </a>
                    </div>
                </div>

                <!-- Executive Summary Stat Cards -->
                <div class="stats-grid mb-4">
                    <div class="stat-card">
                        <div class="stat-icon icon-blue">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-label">Total Active Members</div>
                            <div class="stat-value"><?= number_format($member_count) ?></div>
                            <div class="stat-desc">Enrolled student roster</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon icon-gold">
                            <i class="fas fa-tag"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-label">Fee Rate Per Member</div>
                            <div class="stat-value">₱<?= number_format($fee_per_member, 2) ?></div>
                            <div class="stat-desc">Standard chapter tier rate</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon icon-blue">
                            <i class="fas fa-calculator"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-label">Total Annual Assessment</div>
                            <div class="stat-value">₱<?= number_format($total_annual_fee, 2) ?></div>
                            <div class="stat-desc"><?= $member_count ?> × ₱<?= number_format($fee_per_member) ?></div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon icon-emerald">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-label">Total Remitted & Paid</div>
                            <div class="stat-value text-success">₱<?= number_format($total_paid, 2) ?></div>
                            <div class="stat-desc"><?= $paid_percentage ?>% settled</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon <?= $balance_due > 0 ? 'icon-amber' : 'icon-emerald' ?>">
                            <i class="fas <?= $balance_due > 0 ? 'fa-exclamation-circle' : 'fa-check-double' ?>"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-label">Outstanding Balance</div>
                            <div class="stat-value <?= $balance_due > 0 ? 'text-warning' : 'text-success' ?>">
                                ₱<?= number_format($balance_due, 2) ?>
                            </div>
                            <div class="stat-desc">
                                <?= $balance_due > 0 ? 'Payment required' : 'Fully settled' ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statement of Account Overview -->
                <div class="statement-card mb-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="fas fa-file-invoice me-2 text-primary"></i>Affiliation Dues Summary
                        </h5>
                        <span class="badge <?= $balance_due == 0 ? 'bg-success' : 'bg-warning text-dark' ?>">
                            <i class="fas <?= $balance_due == 0 ? 'fa-check-circle' : 'fa-clock' ?> me-1"></i>
                            <?= $balance_due == 0 ? 'Account in Good Standing' : 'Pending Remittance' ?>
                        </span>
                    </div>

                    <div class="row align-items-center g-3">
                        <div class="col-md-7">
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span>Remittance Progress</span>
                                <span class="fw-bold text-dark"><?= $paid_percentage ?>% Settled (₱<?= number_format($total_paid, 2) ?> / ₱<?= number_format($total_annual_fee, 2) ?>)</span>
                            </div>
                            <div class="progress-bar-custom">
                                <div class="progress-fill" style="width: <?= $paid_percentage ?>%;"></div>
                            </div>
                        </div>
                        <div class="col-md-5 text-md-end">
                            <?php if ($balance_due > 0): ?>
                                <a href="<?= BASE_URL ?>/public/portal/school-officer/financial/receipts.php" class="btn btn-sm btn-primary">
                                    <i class="fas fa-money-bill-wave me-1"></i> Submit Payment Proof
                                </a>
                            <?php else: ?>
                                <span class="text-success small fw-semibold">
                                    <i class="fas fa-check-circle me-1"></i> No outstanding dues for this academic period.
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Transaction History Card -->
                <div class="card mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="fas fa-history me-2 text-muted"></i>Transaction & Remittance History
                        </h5>
                        <span class="text-muted small"><?= count($transactions) ?> record(s)</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Receipt #</th>
                                    <th>Transaction Type</th>
                                    <th>Amount</th>
                                    <th>Payment Status</th>
                                    <th>Blockchain Audit</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transactions)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            <i class="fas fa-receipt fa-2x mb-2 d-block text-secondary opacity-50"></i>
                                            No transaction records found for this institution.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transactions as $tx): ?>
                                        <tr>
                                            <td class="small text-muted">
                                                <?= date('M d, Y - h:i A', strtotime($tx['created_at'] ?? 'now')) ?>
                                            </td>
                                            <td>
                                                <code class="fw-bold"><?= htmlspecialchars($tx['receipt_number'] ?? 'N/A') ?></code>
                                            </td>
                                            <td>
                                                <span class="fw-semibold text-dark">
                                                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $tx['type'] ?? 'Annual Dues'))) ?>
                                                </span>
                                            </td>
                                            <td class="fw-bold text-dark">
                                                ₱<?= number_format((float)($tx['amount'] ?? 0), 2) ?>
                                            </td>
                                            <td>
                                                <?php
                                                $status = $tx['status'] ?? 'pending';
                                                $badgeClass = match($status) {
                                                    'paid', 'completed', 'approved' => 'badge-success',
                                                    'rejected', 'failed' => 'badge-danger',
                                                    default => 'badge-warning'
                                                };
                                                ?>
                                                <span class="badge <?= $badgeClass ?>">
                                                    <?= htmlspecialchars(ucfirst($status)) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (!empty($tx['blockchain_hash'])): ?>
                                                    <span class="badge badge-success" title="<?= htmlspecialchars($tx['blockchain_hash']) ?>">
                                                        <i class="fas fa-shield-alt me-1"></i> Verified
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">
                                                        <i class="fas fa-minus me-1"></i> Not Anchored
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <a href="<?= BASE_URL ?>/public/portal/school-officer/financial/receipts.php" 
                                                   class="btn btn-sm btn-outline-secondary" title="View in receipts">
                                                    <i class="fas fa-eye me-1"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Event Participation Fees Section -->
                <?php if (!empty($event_payments)): ?>
                <div class="card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="fas fa-calendar-alt me-2 text-muted"></i>Event Participation Fees
                        </h5>
                        <span class="text-muted small"><?= count($event_payments) ?> event payment(s)</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Event Title</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Blockchain Proof</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($event_payments as $tx): ?>
                                    <tr>
                                        <td class="small text-muted"><?= date('M d, Y - h:i A', strtotime($tx['created_at'] ?? 'now')) ?></td>
                                        <td class="fw-semibold text-dark"><?= htmlspecialchars($tx['event_name'] ?? 'Event Registration') ?></td>
                                        <td class="fw-bold text-dark">₱<?= number_format((float)($tx['amount'] ?? 0), 2) ?></td>
                                        <td>
                                            <?php
                                            $st = $tx['status'] ?? 'pending';
                                            $badgeClass = match($st) {
                                                'paid', 'completed' => 'badge-success',
                                                default => 'badge-warning'
                                            };
                                            ?>
                                            <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($st)) ?></span>
                                        </td>
                                        <td>
                                            <?php if (!empty($tx['blockchain_hash'])): ?>
                                                <span class="badge badge-success"><i class="fas fa-shield-alt me-1"></i> Verified</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary"><i class="fas fa-minus me-1"></i> None</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>

