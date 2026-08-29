<?php
require_once __DIR__ . '/../bootstrap.php';
$current_page = 'surveys';

require_once __DIR__ . '/../auth_check.php';
require_role(['admin', 'super_admin', 'eb_secretary', 'secretary', 'registration']);

$pageTitle = 'Surveys & Member Feedback';
$supabase = getSupabaseClient();

$feedbackMsg = '';
$feedbackType = 'success';

// Handle POST: Create Survey
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_survey') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (!empty($title)) {
            $timestamp = date('c');
            $srvId = bin2hex(random_bytes(16));

            try {
                $supabase->insert('surveys', [[
                    'id' => $srvId,
                    'title' => $title,
                    'description' => $description,
                    'is_active' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp
                ]]);

                $feedbackMsg = "🎉 Survey '{$title}' created successfully!";
                $feedbackType = 'success';
            } catch (Exception $e) {
                error_log("Survey create error: " . $e->getMessage());
                $feedbackMsg = "Error creating survey: " . $e->getMessage();
                $feedbackType = 'warning';
            }
        }
    }
}

// Fetch real surveys
$surveys = [];
$totalResponses = 0;

try {
    $rawSurveys = $supabase->select('surveys', ['select' => '*', 'order' => 'created_at.desc']);
    if (is_array($rawSurveys)) {
        $surveys = $rawSurveys;
    }

    $rawResp = $supabase->select('survey_responses', ['select' => 'id']);
    if (is_array($rawResp)) {
        $totalResponses = count($rawResp);
    }
} catch (Exception $e) {
    error_log("Surveys load error: " . $e->getMessage());
}

$activeCount = 0;
foreach ($surveys as $s) {
    if (!empty($s['is_active'])) $activeCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Manage post-event feedback surveys, participant satisfaction metrics, and data collection.">
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
                        <i class="fas fa-square-poll-vertical" style="color:var(--color-navy);"></i>
                        Surveys & Member Feedback Operations
                    </h1>
                    <p class="dash-header-sub">
                        Post-event evaluations, chapter satisfaction metrics, and participant feedback polling.
                    </p>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <button type="button" class="btn-primary-navy" onclick="openSurveyModal()">
                        <i class="fas fa-plus" style="color:#FDE047;"></i> Create New Survey
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
                    <div class="kpi-icon-pill navy"><i class="fas fa-clipboard-question"></i></div>
                    <div>
                        <div class="kpi-val"><?= count($surveys) ?></div>
                        <div class="kpi-lbl">Total Surveys</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-circle-check"></i></div>
                    <div>
                        <div class="kpi-val"><?= $activeCount ?></div>
                        <div class="kpi-lbl">Active Surveys</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-comments"></i></div>
                    <div>
                        <div class="kpi-val"><?= number_format($totalResponses) ?></div>
                        <div class="kpi-lbl">Submitted Responses</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill amber"><i class="fas fa-chart-pie"></i></div>
                    <div>
                        <div class="kpi-val">Live</div>
                        <div class="kpi-lbl">Feedback System</div>
                    </div>
                </div>
            </div>

            <!-- 3. Table -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-list"></i> Survey Catalog & Evaluations</h3>
                </div>
                <div style="overflow-x:auto;">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Survey Title</th>
                                <th>Description / Particulars</th>
                                <th>Status</th>
                                <th>Date Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($surveys)): ?>
                                <tr>
                                    <td colspan="4" style="text-align:center; padding:2.5rem; color:#64748B;">
                                        <i class="fas fa-square-poll-vertical" style="font-size:2rem; color:#CBD5E1; margin-bottom:0.5rem; display:block;"></i>
                                        <strong style="color:#0F172A; font-size:0.92rem;">No Surveys in Database</strong>
                                        <p style="margin:0.25rem 0 0; font-size:0.78rem;">Click "+ Create New Survey" to post evaluation forms for chapter events.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($surveys as $s): ?>
                                    <tr>
                                        <td><strong style="color:#0F172A; font-size:0.84rem;"><?= htmlspecialchars($s['title'] ?? 'Survey') ?></strong></td>
                                        <td style="color:#64748B; font-size:0.78rem;"><?= htmlspecialchars($s['description'] ?? 'Member feedback survey') ?></td>
                                        <td><span class="ap-pill <?= !empty($s['is_active']) ? 'active' : 'pending' ?>"><?= !empty($s['is_active']) ? 'Active' : 'Closed' ?></span></td>
                                        <td style="color:#64748B; font-size:0.75rem; white-space:nowrap;"><?= !empty($s['created_at']) ? date('M d, Y', strtotime($s['created_at'])) : 'Recent' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- Create Survey Modal -->
    <div id="surveyModal" class="doc-modal">
        <div class="modal-inner-box">
            <div class="ap-card-header">
                <h3 class="ap-card-title"><i class="fas fa-square-poll-vertical"></i> Create Member Survey</h3>
                <button class="btn-white" style="border:none; padding:0.25rem 0.5rem;" onclick="closeSurveyModal()">&times;</button>
            </div>
            <form method="POST" style="padding:1.25rem;">
                <input type="hidden" name="action" value="create_survey">
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Survey Title</label>
                    <input type="text" name="title" class="ap-input" placeholder="e.g. Regional Tech Summit 2026 Feedback Survey" required style="font-size:0.8rem;">
                </div>
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Description & Instructions</label>
                    <textarea name="description" class="ap-input" rows="3" placeholder="State the purpose of this evaluation..." style="font-size:0.8rem;"></textarea>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:0.65rem; margin-top:1rem;">
                    <button type="button" class="btn-white" onclick="closeSurveyModal()">Cancel</button>
                    <button type="submit" class="btn-primary-navy"><i class="fas fa-save"></i> Publish Survey</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openSurveyModal() {
            document.getElementById('surveyModal').classList.add('active');
        }
        function closeSurveyModal() {
            document.getElementById('surveyModal').classList.remove('active');
        }
    </script>
</body>
</html>
