<?php
require_once __DIR__ . '/../../../bootstrap.php';
$current_page = 'financial';
require_once __DIR__ . '/../../auth_check.php';
require_role(['admin', 'super_admin']);
require_once __DIR__ . '/../../../src/lib/FinancialSyncService.php';

$config = require __DIR__ . '/../../../includes/supabase.php';
$supabase = new \App\Lib\SupabaseClient($config['url'], $config['service_role_key']);
$syncService = new \App\Lib\FinancialSyncService($supabase);
$message = '';
$messageType = 'success';
$userId = $_SESSION['user']['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (($_POST['action'] ?? '') === 'sync_all') {
            $results = $syncService->syncAllInstitutions($userId);
            $failed = count(array_filter($results, static fn(array $result): bool => isset($result['error'])));
            $corrected = count(array_filter($results, static fn(array $result): bool => !empty($result['corrections'])));
            $message = $failed
                ? "Synchronization completed with {$failed} error(s)."
                : "All institution totals synchronized. {$corrected} correction(s) applied.";
            $messageType = $failed ? 'warning' : 'success';
        } elseif (($_POST['action'] ?? '') === 'sync_institution') {
            $instId = trim((string)($_POST['institution_id'] ?? ''));
            $result = $syncService->syncInstitutionTotals($instId, $userId);
            $corrCount = count($result['corrections'] ?? []);
            $message = $corrCount
                ? "Institution totals synchronized. {$corrCount} field(s) corrected."
                : 'Institution totals synchronized (no corrections needed).';
        }
    } catch (Throwable $e) {
        $message = 'Synchronization failed: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Load institutions
$institutions = $supabase->select('institutions', ['select' => 'id,name,acronym', 'order' => 'name.asc']);

// Load stored financial totals
$totalsRows = $supabase->select('institution_financial_totals', ['select' => '*']);
$totalsByInstitution = [];
foreach (is_array($totalsRows) ? $totalsRows : [] as $row) {
    if (!empty($row['institution_id'])) {
        $totalsByInstitution[$row['institution_id']] = $row;
    }
}

// Verification
$verification = [];
foreach (is_array($institutions) ? $institutions : [] as $institution) {
    if (!empty($institution['id'])) {
        try {
            $verification[$institution['id']] = $syncService->verifyTotals((string)$institution['id']);
        } catch (Throwable $e) {
            $verification[$institution['id']] = ['match' => false, 'mismatches' => ['error' => ['expected' => '', 'actual' => $e->getMessage()]]];
        }
    }
}

// Load audit log
$filterInstitution = trim($_GET['filter_institution'] ?? '');
$auditLog = $syncService->getAuditLog($filterInstitution ?: null, 50);

// Build institution name lookup
$instNameMap = [];
foreach (is_array($institutions) ? $institutions : [] as $inst) {
    if (!empty($inst['id'])) {
        $instNameMap[$inst['id']] = ($inst['acronym'] ?? '') ? ($inst['acronym'] . ' — ' . $inst['name']) : ($inst['name'] ?? 'Unknown');
    }
}

function moneyValue($value): string { return '₱' . number_format((float)$value, 2); }
function actionBadge(string $action): string {
    $map = [
        'created' => ['bg' => '#DCFCE7', 'fg' => '#166534', 'icon' => 'fa-plus'],
        'updated' => ['bg' => '#DBEAFE', 'fg' => '#1E40AF', 'icon' => 'fa-pen'],
        'marked_paid' => ['bg' => '#D1FAE5', 'fg' => '#065F46', 'icon' => 'fa-check'],
        'refunded' => ['bg' => '#FEF3C7', 'fg' => '#92400E', 'icon' => 'fa-rotate-left'],
        'deleted' => ['bg' => '#FEE2E2', 'fg' => '#991B1B', 'icon' => 'fa-trash'],
        'sync_correction' => ['bg' => '#EDE9FE', 'fg' => '#5B21B6', 'icon' => 'fa-arrows-rotate'],
        'cron_reconciliation' => ['bg' => '#E0E7FF', 'fg' => '#3730A3', 'icon' => 'fa-clock-rotate-left'],
        'transaction_created' => ['bg' => '#DCFCE7', 'fg' => '#166534', 'icon' => 'fa-circle-plus'],
    ];
    $style = $map[strtolower($action)] ?? ['bg' => '#F1F5F9', 'fg' => '#475569', 'icon' => 'fa-circle-info'];
    return '<span style="display:inline-flex;align-items:center;gap:0.3rem;background:' . $style['bg'] . ';color:' . $style['fg'] . ';border-radius:999px;padding:0.2rem 0.55rem;font-size:0.68rem;font-weight:800;">'
         . '<i class="fas ' . $style['icon'] . '"></i>' . htmlspecialchars(str_replace('_', ' ', ucfirst($action)))
         . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Audit & Sync | IECEP-LSC MEMSYS</title>
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --navy:#0B1D4A; --navy-hover:#152C6E; --gold:#D4AF37; --page:#F8FAFC; --line:#E2E8F0; --shadow:0 1px 3px rgba(15,23,42,.05); }
        body { margin:0; background:var(--page); color:#1E293B; font-family:Inter,sans-serif; }
        .main-content { margin-left:260px; padding:1.25rem; min-height:100vh; }
        .header, .card { background:#FFF; border:1px solid var(--line); border-radius:10px; box-shadow:var(--shadow); }
        .header { display:flex; justify-content:space-between; align-items:center; gap:1rem; padding:1rem 1.2rem; margin-bottom:1rem; flex-wrap:wrap; }
        h1 { color:var(--navy); font-size:1.25rem; margin:0 0 .25rem; display:flex; align-items:center; gap:0.5rem; }
        .sub { color:#64748B; font-size:.8rem; margin:0; }
        .btn { border:0; border-radius:7px; padding:.65rem .9rem; font:700 .78rem Inter; cursor:pointer; transition:all .18s; display:inline-flex; align-items:center; gap:0.35rem; }
        .btn-primary { background:var(--navy); color:#FFF; } .btn-primary:hover { background:var(--navy-hover); transform:translateY(-1px); }
        .btn-outline { background:#FFF; border:1px solid #CBD5E1; color:#0F172A; } .btn-outline:hover { background:#F8FAFC; border-color:#94A3B8; }
        .btn-gold { background:var(--gold); color:#0F172A; } .btn-gold:hover { background:#C5A030; }
        .notice { padding:.75rem 1rem; border-radius:8px; margin-bottom:1rem; font-size:.82rem; display:flex; align-items:center; gap:0.5rem; }
        .success { background:#ECFDF5;color:#065F46; } .warning { background:#FFFBEB;color:#92400E; } .error { background:#FEF2F2;color:#991B1B; }
        .card { overflow:auto; margin-bottom:1rem; }
        .card-header { padding:0.75rem 1rem; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; gap:0.75rem; flex-wrap:wrap; }
        .card-title { margin:0; font-size:0.88rem; font-weight:800; color:#0F172A; display:flex; align-items:center; gap:0.4rem; }
        table { width:100%; border-collapse:collapse; font-size:.78rem; }
        th { background:#F8FAFC; color:#64748B; font-size:.68rem; text-align:left; text-transform:uppercase; padding:.7rem .8rem; letter-spacing:0.03em; font-weight:700; }
        td { border-top:1px solid #F1F5F9; padding:.65rem .8rem; white-space:nowrap; }
        .institution { color:var(--navy); font-weight:800; }
        .amount { font-variant-numeric:tabular-nums; }
        .badge { display:inline-flex; align-items:center; gap:0.25rem; border-radius:999px; padding:.25rem .5rem; font-size:.68rem; font-weight:800; }
        .ok { background:#DCFCE7;color:#166534; } .bad { background:#FEE2E2;color:#991B1B; } .never { background:#F1F5F9; color:#64748B; }
        .mismatch-detail { font-size:.72rem; color:#991B1B; max-width:300px; white-space:normal; line-height:1.5; }
        .mismatch-row td { background:#FFF5F5; }
        .audit-json { font-family:'JetBrains Mono',monospace; font-size:0.68rem; color:#475569; max-width:250px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; cursor:help; }
        .filter-form { display:flex; align-items:center; gap:0.5rem; }
        .filter-form select { font:500 .76rem Inter; padding:0.4rem 0.65rem; border:1px solid #CBD5E1; border-radius:6px; background:#FFF; color:#334155; }
        .section-label { font-size:0.72rem; text-transform:uppercase; letter-spacing:0.06em; font-weight:700; color:#94A3B8; margin:1.25rem 0 0.5rem; }
        @media(max-width:900px){.main-content{margin-left:0}.header{align-items:flex-start;flex-direction:column}table{min-width:900px}}
    </style>
</head>
<body>
<?php include INCLUDES_PATH . 'sidebar.php'; ?>
<main class="main-content">

    <!-- Header -->
    <section class="header">
        <div>
            <h1><i class="fas fa-scale-balanced"></i> Financial Audit &amp; Sync</h1>
            <p class="sub">Reconcile stored school totals against raw transaction records. View audit trail history.</p>
        </div>
        <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
            <a href="<?= PORTAL_URL ?>/admin/financial/dashboard.php" class="btn btn-outline"><i class="fas fa-chart-line"></i> Dashboard</a>
            <form method="post" style="margin:0;"><input type="hidden" name="action" value="sync_all"><button class="btn btn-primary" type="submit"><i class="fas fa-rotate"></i> Sync All Institutions</button></form>
        </div>
    </section>

    <?php if ($message !== ''): ?>
        <div class="notice <?= htmlspecialchars($messageType) ?>">
            <i class="fas <?= $messageType === 'success' ? 'fa-circle-check' : ($messageType === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-xmark') ?>"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <!-- Institution Sync Table -->
    <div class="section-label"><i class="fas fa-building-columns"></i> Institution Totals Verification</div>
    <section class="card">
        <table>
            <thead><tr><th>Institution</th><th>Paid</th><th>Pending</th><th>Refunded</th><th>Cancelled</th><th>Grand Total</th><th>Current Year</th><th>Last Synced</th><th>Integrity</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach (is_array($institutions) ? $institutions : [] as $institution):
                $id = (string)($institution['id'] ?? ''); $row = $totalsByInstitution[$id] ?? []; $check = $verification[$id] ?? ['match' => false, 'mismatches' => []];
                $hasMismatch = empty($check['match']);
            ?>
                <tr<?= $hasMismatch && !empty($row) ? ' class="mismatch-row"' : '' ?>>
                    <td class="institution"><?= htmlspecialchars($institution['name'] ?? 'Unnamed') ?>
                        <?php if (!empty($institution['acronym'])): ?><br><span style="font-size:0.68rem;color:#94A3B8;font-weight:500;"><?= htmlspecialchars($institution['acronym']) ?></span><?php endif; ?>
                    </td>
                    <td class="amount"><?= moneyValue($row['total_paid'] ?? 0) ?></td>
                    <td class="amount"><?= moneyValue($row['total_pending'] ?? 0) ?></td>
                    <td class="amount"><?= moneyValue($row['total_refunded'] ?? 0) ?></td>
                    <td class="amount"><?= moneyValue($row['total_cancelled'] ?? 0) ?></td>
                    <td class="amount"><strong><?= moneyValue($row['grand_total_all_time'] ?? 0) ?></strong></td>
                    <td class="amount"><?= moneyValue($row['current_year_total'] ?? 0) ?></td>
                    <td><?= !empty($row['last_synced_at']) ? htmlspecialchars(date('M d, Y H:i', strtotime($row['last_synced_at']))) : '<span class="badge never">Never</span>' ?></td>
                    <td>
                        <?php if (empty($row)): ?>
                            <span class="badge never"><i class="fas fa-minus"></i> No data</span>
                        <?php elseif (!empty($check['match'])): ?>
                            <span class="badge ok"><i class="fas fa-circle-check"></i> In sync</span>
                        <?php else: ?>
                            <span class="badge bad" title="<?= htmlspecialchars(json_encode($check['mismatches'] ?? [])) ?>"><i class="fas fa-triangle-exclamation"></i> Mismatch</span>
                            <?php if (!empty($check['mismatches'])): ?>
                            <div class="mismatch-detail" style="margin-top:0.3rem;">
                                <?php foreach ($check['mismatches'] as $field => $vals): ?>
                                    <div><strong><?= htmlspecialchars(str_replace('_', ' ', $field)) ?>:</strong> stored <?= moneyValue($vals['actual'] ?? 0) ?> → expected <?= moneyValue($vals['expected'] ?? 0) ?></div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" style="margin:0;">
                            <input type="hidden" name="action" value="sync_institution">
                            <input type="hidden" name="institution_id" value="<?= htmlspecialchars($id) ?>">
                            <button class="btn btn-primary" type="submit" style="padding:0.4rem 0.7rem;"><i class="fas fa-arrows-rotate"></i> Sync</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($institutions)): ?><tr><td colspan="10" style="text-align:center; padding:2rem; color:#64748B;">No institutions found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>

    <!-- Audit Log Viewer -->
    <div class="section-label"><i class="fas fa-clipboard-list"></i> Financial Audit Trail</div>
    <section class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-scroll"></i> Recent Financial Changes</h3>
            <form class="filter-form" method="get">
                <select name="filter_institution" onchange="this.form.submit()">
                    <option value="">All Institutions</option>
                    <?php foreach (is_array($institutions) ? $institutions : [] as $fInst): ?>
                        <option value="<?= htmlspecialchars($fInst['id'] ?? '') ?>" <?= ($filterInstitution === ($fInst['id'] ?? '')) ? 'selected' : '' ?>>
                            <?= htmlspecialchars(($fInst['acronym'] ?? '') ?: ($fInst['name'] ?? 'Unknown')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span style="font-size:0.72rem; color:#94A3B8;"><?= count($auditLog) ?> entries</span>
            </form>
        </div>
        <table>
            <thead><tr><th>Timestamp</th><th>Institution</th><th>Transaction</th><th>Action</th><th>Old Value</th><th>New Value</th><th>Performed By</th></tr></thead>
            <tbody>
            <?php if (empty($auditLog)): ?>
                <tr><td colspan="7" style="text-align:center; padding:2rem; color:#64748B;">
                    <i class="fas fa-clipboard-check" style="font-size:1.5rem; color:#CBD5E1; display:block; margin-bottom:0.5rem;"></i>
                    No audit log entries<?= $filterInstitution ? ' for this institution' : '' ?>.
                </td></tr>
            <?php else: ?>
                <?php foreach ($auditLog as $log): ?>
                <tr>
                    <td style="font-size:0.72rem; color:#64748B;">
                        <?= !empty($log['created_at']) ? date('M d, Y H:i:s', strtotime($log['created_at'])) : '—' ?>
                    </td>
                    <td style="font-size:0.75rem; font-weight:600; color:var(--navy);">
                        <?= htmlspecialchars($instNameMap[$log['institution_id'] ?? ''] ?? ($log['institution_id'] ? substr($log['institution_id'], 0, 8) . '…' : '—')) ?>
                    </td>
                    <td style="font-family:'JetBrains Mono',monospace; font-size:0.68rem; color:#475569;">
                        <?= htmlspecialchars($log['transaction_id'] ?? '—') ?>
                    </td>
                    <td><?= actionBadge($log['action'] ?? 'unknown') ?></td>
                    <td>
                        <?php
                        $oldVal = $log['old_value'] ?? null;
                        if ($oldVal) {
                            $decoded = is_string($oldVal) ? json_decode($oldVal, true) : $oldVal;
                            if (is_array($decoded)) {
                                echo '<span class="audit-json" title="' . htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT)) . '">';
                                $items = [];
                                foreach (array_slice($decoded, 0, 3) as $k => $v) {
                                    $items[] = str_replace('_', ' ', $k) . ': ' . (is_numeric($v) ? number_format((float)$v, 2) : $v);
                                }
                                echo htmlspecialchars(implode(', ', $items));
                                if (count($decoded) > 3) echo ' …';
                                echo '</span>';
                            } else {
                                echo '<span class="audit-json">' . htmlspecialchars(substr((string)$oldVal, 0, 60)) . '</span>';
                            }
                        } else {
                            echo '<span style="color:#CBD5E1;">—</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <?php
                        $newVal = $log['new_value'] ?? null;
                        if ($newVal) {
                            $decoded = is_string($newVal) ? json_decode($newVal, true) : $newVal;
                            if (is_array($decoded)) {
                                echo '<span class="audit-json" title="' . htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT)) . '">';
                                $items = [];
                                foreach (array_slice($decoded, 0, 3) as $k => $v) {
                                    $items[] = str_replace('_', ' ', $k) . ': ' . (is_numeric($v) ? number_format((float)$v, 2) : $v);
                                }
                                echo htmlspecialchars(implode(', ', $items));
                                if (count($decoded) > 3) echo ' …';
                                echo '</span>';
                            } else {
                                echo '<span class="audit-json">' . htmlspecialchars(substr((string)$newVal, 0, 60)) . '</span>';
                            }
                        } else {
                            echo '<span style="color:#CBD5E1;">—</span>';
                        }
                        ?>
                    </td>
                    <td style="font-size:0.72rem; color:#64748B;">
                        <?= htmlspecialchars($log['performed_by'] ? substr($log['performed_by'], 0, 8) . '…' : 'System') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

</main>
</body>
</html>
