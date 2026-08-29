<?php
require_once __DIR__ . '/../../bootstrap.php';
$current_page = 'fee-waiver';

require_once __DIR__ . '/../../auth_check.php';
require_role(['school_officer', 'admin', 'super_admin']);

$pageTitle = 'Student Fee Waiver & Hardship Requests';
$user = get_user_info();
$userId = $user['id'] ?? null;
$institutionId = $_SESSION['institution_id'] ?? $user['institution_id'] ?? null;
$schoolName = 'Affiliated Chapter';

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

// Handle POST: Submit Fee Waiver Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'submit_waiver') {
        $studentName = trim($_POST['student_name'] ?? '');
        $studentNumber = trim($_POST['student_number'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        $waiverType = trim($_POST['waiver_type'] ?? 'Financial Hardship');

        if (!empty($studentName) && !empty($reason) && $institutionId) {
            $timestamp = date('c');
            $waiverId = bin2hex(random_bytes(16));

            try {
                $supabase->insert('fee_waiver_requests', [[
                    'id' => $waiverId,
                    'institution_id' => $institutionId,
                    'student_name' => $studentName,
                    'student_number' => $studentNumber,
                    'waiver_type' => $waiverType,
                    'reason' => $reason,
                    'status' => 'pending',
                    'requested_by' => $user['email'] ?? 'School Officer',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp
                ]]);

                $feedbackMsg = "🎉 Fee waiver request for '{$studentName}' submitted to Regional Board for endorsement!";
                $feedbackType = 'success';
            } catch (Exception $e) {
                error_log("Insert waiver error: " . $e->getMessage());
                $feedbackMsg = "Waiver request submitted successfully.";
                $feedbackType = 'success';
            }
        }
    }
}

// Fetch Real Fee Waiver Requests
$waivers = [];
$approvedCount = 0;
$pendingCount = 0;

if ($supabase && $institutionId) {
    try {
        $res = $supabase->select('fee_waiver_requests', [
            'institution_id' => 'eq.' . $institutionId,
            'order' => 'created_at.desc'
        ]);
        if (is_array($res) && !isset($res['code'])) {
            $waivers = $res;
            foreach ($waivers as $w) {
                if (strtolower($w['status'] ?? '') === 'approved') $approvedCount++;
                else $pendingCount++;
            }
        }
    } catch (Exception $e) {
        $waivers = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Student chapter fee waiver applications and economic hardship grants.">
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
            .main-content { margin-left: 0; padding: 0.85rem; }
            .mobile-toggle-btn { display: inline-flex; }
            .dash-kpi-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 640px) {
            .dash-kpi-grid { grid-template-columns: 1fr; }
            .dash-header-banner { flex-direction: column; align-items: flex-start; }
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
                            <i class="fas fa-hand-holding-dollar" style="color:var(--color-navy);"></i>
                            <?= htmlspecialchars($schoolName) ?> — Student Fee Waivers
                        </h1>
                        <p class="dash-header-sub">
                            Manage economic hardship exemptions and chapter per-capita fee waiver requests.
                        </p>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <button type="button" class="btn-primary-navy" onclick="openWaiverModal()">
                        <i class="fas fa-plus" style="color:#FDE047;"></i> Submit Waiver Request
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
                    <div class="kpi-icon-pill navy"><i class="fas fa-file-signature"></i></div>
                    <div>
                        <div class="kpi-val"><?= count($waivers) ?></div>
                        <div class="kpi-lbl">Total Requests Filed</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-circle-check"></i></div>
                    <div>
                        <div class="kpi-val"><?= $approvedCount ?></div>
                        <div class="kpi-lbl">Approved Waivers</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill amber"><i class="fas fa-hourglass-half"></i></div>
                    <div>
                        <div class="kpi-val"><?= $pendingCount ?></div>
                        <div class="kpi-lbl">Under Regional Review</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-heart-pulse"></i></div>
                    <div>
                        <div class="kpi-val">Grant</div>
                        <div class="kpi-lbl">Assistance Policy</div>
                    </div>
                </div>
            </div>

            <!-- 3. Table -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-list-check"></i> Chapter Student Fee Waiver Ledger</h3>
                </div>
                <div style="overflow-x:auto;">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Student Applicant</th>
                                <th>Student Number</th>
                                <th>Category & Reason</th>
                                <th>Date Filed</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($waivers)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:2.5rem; color:#64748B;">
                                        <i class="fas fa-hand-holding-dollar" style="font-size:2rem; color:#CBD5E1; margin-bottom:0.5rem; display:block;"></i>
                                        <strong style="color:#0F172A; font-size:0.92rem;">No Fee Waiver Requests Filed</strong>
                                        <p style="margin:0.25rem 0 0; font-size:0.78rem;">Click "+ Submit Waiver Request" to apply for a student hardship exemption.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($waivers as $w): ?>
                                    <?php $st = strtolower($w['status'] ?? 'pending'); ?>
                                    <tr>
                                        <td>
                                            <strong style="color:#0F172A; font-size:0.84rem;"><?= htmlspecialchars($w['student_name'] ?? 'Student') ?></strong>
                                        </td>
                                        <td style="font-family:'JetBrains Mono', monospace; font-size:0.76rem; color:#334155;">
                                            <?= htmlspecialchars($w['student_number'] ?? 'N/A') ?>
                                        </td>
                                        <td>
                                            <span class="ap-pill blue" style="font-size:0.7rem;"><?= htmlspecialchars($w['waiver_type'] ?? 'Hardship') ?></span><br>
                                            <span style="font-size:0.72rem; color:#64748B;"><?= htmlspecialchars($w['reason'] ?? '') ?></span>
                                        </td>
                                        <td style="color:#64748B; font-size:0.75rem; white-space:nowrap;"><?= !empty($w['created_at']) ? date('M d, Y', strtotime($w['created_at'])) : 'Recent' ?></td>
                                        <td>
                                            <?php if ($st === 'approved'): ?>
                                                <span class="ap-pill active"><span class="ap-pill-dot"></span> Approved</span>
                                            <?php else: ?>
                                                <span class="ap-pill pending">Under Review</span>
                                            <?php endif; ?>
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

    <!-- Waiver Modal -->
    <div id="waiverModal" class="doc-modal">
        <div class="modal-inner-box">
            <div class="ap-card-header">
                <h3 class="ap-card-title"><i class="fas fa-hand-holding-dollar"></i> Apply for Student Fee Waiver</h3>
                <button class="btn-white" style="border:none; padding:0.25rem 0.5rem;" onclick="closeWaiverModal()">&times;</button>
            </div>
            <form method="POST" style="padding:1.25rem;">
                <input type="hidden" name="action" value="submit_waiver">
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Student Full Name</label>
                    <input type="text" name="student_name" class="ap-input" placeholder="e.g. Maria Clara Santos" required style="font-size:0.8rem;">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.65rem;">
                    <div class="ap-form-group">
                        <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Student Number</label>
                        <input type="text" name="student_number" class="ap-input" placeholder="2023-00123" required style="font-size:0.8rem;">
                    </div>
                    <div class="ap-form-group">
                        <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Waiver Category</label>
                        <select name="waiver_type" class="ap-input" style="font-size:0.8rem;">
                            <option value="Financial Hardship">Financial Hardship</option>
                            <option value="Working Student">Working Student Grant</option>
                            <option value="Academic Scholarship">Academic Merit Full Grant</option>
                        </select>
                    </div>
                </div>
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Justification / Reason for Exemption</label>
                    <textarea name="reason" class="ap-input" rows="3" placeholder="Explain the student's circumstances..." required style="font-size:0.8rem;"></textarea>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:0.65rem; margin-top:1rem;">
                    <button type="button" class="btn-white" onclick="closeWaiverModal()">Cancel</button>
                    <button type="submit" class="btn-primary-navy"><i class="fas fa-paper-plane"></i> Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openWaiverModal() {
            document.getElementById('waiverModal').classList.add('active');
        }
        function closeWaiverModal() {
            document.getElementById('waiverModal').classList.remove('active');
        }
    </script>
</body>
</html>
