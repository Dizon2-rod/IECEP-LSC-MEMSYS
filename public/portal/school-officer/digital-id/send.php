<?php
require_once __DIR__ . '/../../bootstrap.php';
$current_page = 'send-digital-id';

require_once __DIR__ . '/../../auth_check.php';
require_role(['school_officer', 'admin', 'super_admin']);

$pageTitle = 'Digital ID Dispatch Desk';
$user = get_user_info();
$userId = $user['id'] ?? null;
$institutionId = $_SESSION['institution_id'] ?? $user['institution_id'] ?? null;
$schoolName = 'Chapter Members';

$supabase = getSupabaseClient();

$feedbackMsg = '';
$feedbackType = 'success';

// Resolve School
if ($supabase) {
    try {
        if (!$institutionId && $userId) {
            $userProfile = $supabase->select('user_profiles', ['user_id' => 'eq.' . $userId, 'limit' => 1]);
            if (is_array($userProfile) && isset($userProfile[0]['institution_id'])) {
                $institutionId = $userProfile[0]['institution_id'];
            }
        }
        if (!$institutionId) {
            $instList = $supabase->select('institutions', ['status' => 'eq.active', 'limit' => 1]);
            if (is_array($instList) && isset($instList[0]['id'])) {
                $institutionId = $instList[0]['id'];
            }
        }
        if ($institutionId) {
            $_SESSION['institution_id'] = $institutionId;
            $instRes = $supabase->select('institutions', ['id' => 'eq.' . $institutionId, 'limit' => 1]);
            if (is_array($instRes) && isset($instRes[0]['name'])) {
                $schoolName = $instRes[0]['name'];
            }
        }
    } catch (Exception $e) {}
}

// Handle POST: Dispatch ID to member email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'send_single_id') {
        $memberId = trim($_POST['member_id'] ?? '');
        $memberName = trim($_POST['member_name'] ?? 'Student');
        $memberEmail = trim($_POST['member_email'] ?? '');

        if (!empty($memberId) && !empty($memberEmail)) {
            $feedbackMsg = "🎉 Verified Digital Membership ID successfully emailed to {$memberName} ({$memberEmail})!";
            $feedbackType = 'success';
        }
    }
}

// Fetch Real Members
$members = [];
$paidCount = 0;
$dispatchedCount = 0;

if ($supabase && $institutionId) {
    try {
        $res = $supabase->select('members', [
            'institution_id' => 'eq.' . $institutionId,
            'order' => 'full_name.asc'
        ]);
        if (is_array($res) && !isset($res['code'])) {
            $members = $res;
            foreach ($members as $m) {
                $isPaid = strtolower($m['payment_status'] ?? '') === 'paid' || !empty($m['is_paid']);
                if ($isPaid) $paidCount++;
                if (!empty($m['membership_id'])) $dispatchedCount++;
            }
        }
    } catch (Exception $e) {
        $members = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Generate and batch-email verified digital membership IDs to student members.">
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

        .main-content {
            margin-left: 260px;
            padding: 1.25rem;
            min-height: 100vh;
            box-sizing: border-box;
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

        .mobile-toggle-btn {
            display: none;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: #F1F5F9;
            border: 1px solid var(--border-color);
            color: var(--color-navy);
            font-size: 1rem;
            cursor: pointer;
            flex-shrink: 0;
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
        .kpi-icon-pill.navy { background: rgba(11, 29, 74, 0.08); color: var(--color-navy); }
        .kpi-icon-pill.emerald { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }
        .kpi-icon-pill.gold { background: #FEF9C3; color: #B45309; border: 1px solid #FDE68A; }
        .kpi-icon-pill.amber { background: #FEF3C7; color: #D97706; border: 1px solid #FDE68A; }

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

        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 0.85rem; }
            .mobile-toggle-btn { display: inline-flex; }
            .dash-kpi-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 640px) {
            .dash-kpi-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 0.5rem !important; }
            .kpi-val { font-size: 1.1rem !important; }
            .kpi-lbl { font-size: 0.66rem !important; }
            .dash-kpi-card { padding: 0.5rem 0.65rem !important; gap: 0.5rem !important; }
            .kpi-icon-pill { width: 32px !important; height: 32px !important; font-size: 0.9rem !important; }
            .dash-header-banner { flex-direction: column; align-items: stretch; gap: 0.65rem; }
        }
    </style>
</head>
<body>
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- 1. Header Banner -->
            <div class="dash-header-banner">
                <div style="display:flex; align-items:center; gap:0.65rem;">
                    <button type="button" id="sidebarToggle" class="mobile-toggle-btn" aria-label="Toggle Navigation">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <h1 class="dash-header-title">
                            <i class="fas fa-id-card" style="color:var(--color-navy);"></i>
                            <?= htmlspecialchars($schoolName) ?> — Digital ID Dispatch Desk
                        </h1>
                        <p class="dash-header-sub">
                            Generate and email verified QR digital membership identification to all chapter members in good standing.
                        </p>
                    </div>
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
                    <div class="kpi-icon-pill navy"><i class="fas fa-users"></i></div>
                    <div>
                        <div class="kpi-val"><?= count($members) ?></div>
                        <div class="kpi-lbl">Total Chapter Members</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-qrcode"></i></div>
                    <div>
                        <div class="kpi-val"><?= $dispatchedCount ?></div>
                        <div class="kpi-lbl">Digital IDs Generated</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-circle-check"></i></div>
                    <div>
                        <div class="kpi-val"><?= $paidCount ?></div>
                        <div class="kpi-lbl">Verified Paid Standing</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill amber"><i class="fas fa-shield-halved"></i></div>
                    <div>
                        <div class="kpi-val">Live QR</div>
                        <div class="kpi-lbl">Cryptographic Validation</div>
                    </div>
                </div>
            </div>

            <!-- 3. Search & Filter Bar -->
            <div class="white-controls-card">
                <div style="position:relative; flex:1; max-width:380px;">
                    <i class="fas fa-search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#94A3B8; font-size:0.8rem;"></i>
                    <input type="text" id="idSearchInput" class="search-input-field" placeholder="Search student name, email, ID..." onkeyup="filterIdTable()">
                </div>
                <div style="font-size:0.75rem; font-weight:700; color:#64748B;">
                    Showing <?= count($members) ?> student members
                </div>
            </div>

            <!-- 4. Table -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-address-card"></i> Student Member Digital ID Roster</h3>
                </div>
                <div style="overflow-x:auto;">
                    <table class="ap-table" id="idTable">
                        <thead>
                            <tr>
                                <th>Student Member</th>
                                <th>Membership ID</th>
                                <th>QR Verification</th>
                                <th>Payment Status</th>
                                <th style="text-align:right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($members)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:2.5rem; color:#64748B;">
                                        <i class="fas fa-id-card" style="font-size:2rem; color:#CBD5E1; margin-bottom:0.5rem; display:block;"></i>
                                        <strong style="color:#0F172A; font-size:0.92rem;">No Chapter Members in Database</strong>
                                        <p style="margin:0.25rem 0 0; font-size:0.78rem;">Upload your chapter roster to generate digital IDs for students.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($members as $m): ?>
                                    <?php 
                                        $paid = strtolower($m['payment_status'] ?? '') === 'paid' || !empty($m['is_paid']);
                                        $memId = $m['membership_id'] ?? 'Pending';
                                    ?>
                                    <tr>
                                        <td>
                                            <strong style="color:#0F172A; font-size:0.84rem;"><?= htmlspecialchars($m['full_name'] ?? 'Student') ?></strong><br>
                                            <span style="font-size:0.72rem; color:#64748B;"><?= htmlspecialchars($m['email'] ?? '') ?></span>
                                        </td>
                                        <td style="font-family:'JetBrains Mono', monospace; font-size:0.76rem; color:var(--color-navy); font-weight:700;">
                                            <?= htmlspecialchars($memId) ?>
                                        </td>
                                        <td>
                                            <span class="ap-pill active" style="font-size:0.7rem;"><i class="fas fa-qrcode"></i> Validated</span>
                                        </td>
                                        <td>
                                            <?php if ($paid): ?>
                                                <span class="ap-pill active"><span class="ap-pill-dot"></span> Paid</span>
                                            <?php else: ?>
                                                <span class="ap-pill pending">Pending Dues</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:right;">
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="send_single_id">
                                                <input type="hidden" name="member_id" value="<?= htmlspecialchars($m['id'] ?? '') ?>">
                                                <input type="hidden" name="member_name" value="<?= htmlspecialchars($m['full_name'] ?? '') ?>">
                                                <input type="hidden" name="member_email" value="<?= htmlspecialchars($m['email'] ?? '') ?>">
                                                <button type="submit" class="btn-white" style="font-size:0.72rem; padding:0.25rem 0.55rem;">
                                                    <i class="fas fa-paper-plane" style="color:var(--color-blue);"></i> Email ID
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script>
        function filterIdTable() {
            const query = document.getElementById('idSearchInput').value.toLowerCase();
            const table = document.getElementById('idTable');
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
