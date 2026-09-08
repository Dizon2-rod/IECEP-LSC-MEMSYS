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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (($_POST['action'] ?? '') === 'sync_all') {
            $results = $syncService->syncAllInstitutions();
            $failed = count(array_filter($results, static fn(array $result): bool => isset($result['error'])));
            $message = $failed ? "Synchronization completed with {$failed} error(s)." : 'All institution totals synchronized successfully.';
            $messageType = $failed ? 'warning' : 'success';
        } elseif (($_POST['action'] ?? '') === 'sync_institution') {
            $syncService->syncInstitutionTotals(trim((string)($_POST['institution_id'] ?? '')));
            $message = 'Institution totals synchronized successfully.';
        }
    } catch (Throwable $e) {
        $message = 'Synchronization failed: ' . $e->getMessage();
        $messageType = 'error';
    }
}

$institutions = $supabase->select('institutions', ['select' => 'id,name,acronym', 'order' => 'name.asc']);
$totalsRows = $supabase->select('institution_financial_totals', ['select' => '*']);
$totalsByInstitution = [];
foreach (is_array($totalsRows) ? $totalsRows : [] as $row) {
    if (!empty($row['institution_id'])) {
        $totalsByInstitution[$row['institution_id']] = $row;
    }
}
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
function moneyValue($value): string { return 'PHP ' . number_format((float)$value, 2); }
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
        :root { --navy:#0B1D4A; --gold:#D4AF37; --page:#F8FAFC; --line:#E2E8F0; }
        body { margin:0; background:var(--page); color:#1E293B; font-family:Inter,sans-serif; }
        .main-content { margin-left:260px; padding:1.25rem; min-height:100vh; }
        .header, .card { background:#FFF; border:1px solid var(--line); border-radius:10px; box-shadow:0 1px 3px rgba(15,23,42,.05); }
        .header { display:flex; justify-content:space-between; align-items:center; gap:1rem; padding:1rem 1.2rem; margin-bottom:1rem; }
        h1 { color:var(--navy); font-size:1.25rem; margin:0 0 .25rem; } .sub { color:#64748B; font-size:.8rem; margin:0; }
        .btn { border:0; border-radius:7px; padding:.65rem .9rem; font:700 .78rem Inter; cursor:pointer; }
        .btn-primary { background:var(--navy); color:#FFF; } .btn-primary:hover { background:#152C6E; }
        .notice { padding:.75rem 1rem; border-radius:8px; margin-bottom:1rem; font-size:.82rem; } .success { background:#ECFDF5;color:#065F46; } .warning { background:#FFFBEB;color:#92400E; } .error { background:#FEF2F2;color:#991B1B; }
        .card { overflow:auto; } table { width:100%; border-collapse:collapse; font-size:.78rem; } th { background:#F8FAFC; color:#64748B; font-size:.68rem; text-align:left; text-transform:uppercase; padding:.7rem .8rem; } td { border-top:1px solid #F1F5F9; padding:.75rem .8rem; white-space:nowrap; } .institution { color:var(--navy); font-weight:800; } .amount { font-variant-numeric:tabular-nums; } .badge { display:inline-block; border-radius:999px; padding:.25rem .5rem; font-size:.68rem; font-weight:800; } .ok { background:#DCFCE7;color:#166534; } .bad { background:#FEE2E2;color:#991B1B; }
        @media(max-width:900px){.main-content{margin-left:0}.header{align-items:flex-start;flex-direction:column}table{min-width:1050px}}
    </style>
</head>
<body>
<?php include INCLUDES_PATH . 'sidebar.php'; ?>
<main class="main-content">
    <section class="header">
        <div><h1><i class="fas fa-scale-balanced"></i> Financial Audit &amp; Sync</h1><p class="sub">Reconcile stored school totals against raw transaction records.</p></div>
        <form method="post"><input type="hidden" name="action" value="sync_all"><button class="btn btn-primary" type="submit"><i class="fas fa-rotate"></i> Sync All Institutions</button></form>
    </section>
    <?php if ($message !== ''): ?><div class="notice <?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <section class="card">
        <table>
            <thead><tr><th>Institution</th><th>Paid</th><th>Pending</th><th>Refunded</th><th>Cancelled</th><th>Grand Total</th><th>Current Year</th><th>Last Synced</th><th>Integrity</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach (is_array($institutions) ? $institutions : [] as $institution):
                $id = (string)($institution['id'] ?? ''); $row = $totalsByInstitution[$id] ?? []; $check = $verification[$id] ?? ['match' => false]; ?>
                <tr>
                    <td class="institution"><?= htmlspecialchars($institution['name'] ?? 'Unnamed institution') ?></td>
                    <td class="amount"><?= moneyValue($row['total_paid'] ?? 0) ?></td><td class="amount"><?= moneyValue($row['total_pending'] ?? 0) ?></td><td class="amount"><?= moneyValue($row['total_refunded'] ?? 0) ?></td><td class="amount"><?= moneyValue($row['total_cancelled'] ?? 0) ?></td><td class="amount"><?= moneyValue($row['grand_total_all_time'] ?? 0) ?></td><td class="amount"><?= moneyValue($row['current_year_total'] ?? 0) ?></td>
                    <td><?= !empty($row['last_synced_at']) ? htmlspecialchars(date('M d, Y H:i', strtotime($row['last_synced_at']))) : 'Never' ?></td>
                    <td><span class="badge <?= !empty($check['match']) ? 'ok' : 'bad' ?>"><?= !empty($check['match']) ? 'In sync' : 'Mismatch' ?></span></td>
                    <td><form method="post"><input type="hidden" name="action" value="sync_institution"><input type="hidden" name="institution_id" value="<?= htmlspecialchars($id) ?>"><button class="btn btn-primary" type="submit"><i class="fas fa-arrows-rotate"></i> Sync</button></form></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($institutions)): ?><tr><td colspan="10">No institutions found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
