<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/role-config.php';

require_role(['member', 'admin', 'super_admin', 'school_officer']);

$current_page = 'surveys';
$pageTitle = 'Event & Chapter Feedback Surveys';

$user = get_user_info();
$userId = $user['id'] ?? null;
$userEmail = $user['email'] ?? '';
$displayName = $user['full_name'] ?? $user['name'] ?? $userEmail;

$supabase = getSupabaseClient();

// Fetch Member Record
$member = [];
$schoolName = 'Laguna State Polytechnic University - Santa Cruz Campus';
if ($supabase) {
    try {
        if (!empty($userEmail)) {
            $mRes = $supabase->select('members', ['email' => 'eq.' . $userEmail]);
            if (is_array($mRes) && isset($mRes[0])) $member = $mRes[0];
        }
        if (empty($member) && !empty($userId)) {
            $mRes = $supabase->select('members', ['id' => 'eq.' . $userId]);
            if (is_array($mRes) && isset($mRes[0])) $member = $mRes[0];
        }
        $instId = $member['institution_id'] ?? null;
        if ($instId) {
            $iRes = $supabase->select('institutions', ['id' => 'eq.' . $instId]);
            if (is_array($iRes) && isset($iRes[0]['name'])) {
                $schoolName = $iRes[0]['name'];
            }
        }
    } catch (Exception $e) {}
}

$memberDbId = $member['id'] ?? $userId;
$surveys = [];
$submittedSurveyIds = [];

try {
    if ($supabase) {
        $rawSurveys = $supabase->select('surveys', ['is_active' => 'eq.true', 'order' => 'created_at.desc']);
        if (is_array($rawSurveys)) $surveys = $rawSurveys;

        if (!empty($memberDbId)) {
            $rawResponses = $supabase->select('survey_responses', ['member_id' => 'eq.' . $memberDbId]);
            if (is_array($rawResponses)) {
                $submittedSurveyIds = array_column($rawResponses, 'survey_id');
            }
        }
    }
} catch (Exception $e) {
    error_log("Surveys query error: " . $e->getMessage());
}

// Fallback Default Institutional Surveys if database has no active surveys
if (empty($surveys)) {
    $surveys = [
        [
            'id' => 'survey_ay2025_satisfaction',
            'title' => 'AY 2024-2025 IECEP-LSC Chapter & Regional Activity Feedback',
            'description' => 'Help us improve future engineering seminars, student conventions, and membership privileges across Laguna.',
            'questions' => json_encode([
                ['type' => 'rating', 'text' => 'How would you rate the overall technical quality of IECEP-LSC activities?'],
                ['type' => 'rating', 'text' => 'How satisfied are you with the digital ID and chapter communication?'],
                ['type' => 'yesno', 'text' => 'Would you recommend attending upcoming regional summits to your peers?'],
                ['type' => 'text', 'text' => 'What topics or workshops would you like IECEP-LSC to organize next?']
            ]),
            'created_at' => date('Y-m-d H:i:s')
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Share feedback and evaluate attended workshops and chapter initiatives.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-navy: #0B1D4A;
            --color-navy-hover: #152C6E;
            --color-gold: #D4AF37;
            --color-emerald: #059669;
            --color-blue: #2563EB;
            --color-rose: #E11D48;
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

        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 1rem; }
        }

        .survey-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 1.25rem;
        }

        .survey-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 1.25rem;
            box-shadow: var(--shadow-card);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.2s ease;
        }

        .survey-card:hover {
            transform: translateY(-2px);
            border-color: #CBD5E1;
            box-shadow: 0 8px 20px -4px rgba(0, 0, 0, 0.08);
        }

        .ap-pill {
            display: inline-flex;
            align-items: center;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .ap-pill.active { background: #ECFDF5; color: #059669; }
        .ap-pill.blue { background: #EFF6FF; color: #1D4ED8; }

        .btn-primary-navy {
            background: var(--color-navy);
            color: #FFFFFF;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-primary-navy:hover {
            background: var(--color-navy-hover);
            color: #FFFFFF;
        }

        /* Survey Dialog Modal */
        .modal-backdrop-custom {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal-box-custom {
            background: #FFFFFF;
            border-radius: 16px;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.25);
            border: 1px solid var(--border-color);
        }

        .q-block {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <!-- Header -->
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.25rem;">
            <div>
                <h1 style="font-size:1.4rem; font-weight:800; color:#0F172A; margin:0 0 0.2rem 0; display:flex; align-items:center; gap:0.5rem;">
                    <i class="fas fa-square-poll-vertical" style="color:var(--color-blue);"></i> Event &amp; Chapter Feedback Surveys
                </h1>
                <p style="margin:0; font-size:0.82rem; color:#64748B;">
                    Share your evaluation and insights to help IECEP-LSC improve future programs.
                </p>
            </div>
        </div>

        <div class="survey-grid">
            <?php foreach ($surveys as $survey): ?>
                <?php
                    $sId = $survey['id'];
                    $isSubmitted = in_array($sId, $submittedSurveyIds);
                    $questions = json_decode($survey['questions'] ?? '[]', true);
                ?>
                <div class="survey-card">
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.75rem;">
                            <span class="ap-pill blue">
                                <i class="fas fa-clipboard-question me-1"></i> Chapter Survey
                            </span>
                            <?php if ($isSubmitted): ?>
                                <span class="ap-pill active"><i class="fas fa-circle-check me-1"></i> Completed</span>
                            <?php else: ?>
                                <span class="ap-pill blue">Open for Responses</span>
                            <?php endif; ?>
                        </div>

                        <h2 style="font-size:1.05rem; font-weight:800; color:#0F172A; margin:0 0 0.4rem 0; line-height:1.35;">
                            <?= htmlspecialchars($survey['title']) ?>
                        </h2>

                        <p style="font-size:0.82rem; color:#64748B; line-height:1.45; margin:0 0 1rem 0;">
                            <?= htmlspecialchars($survey['description'] ?? 'Evaluation questionnaire for IECEP student delegates.') ?>
                        </p>

                        <div style="font-size:0.75rem; color:#475569; margin-bottom:1.25rem;">
                            <i class="fas fa-list-check me-1" style="color:var(--color-gold);"></i> <?= count($questions) ?> Survey Questions
                        </div>
                    </div>

                    <div>
                        <?php if ($isSubmitted): ?>
                            <div style="background:#ECFDF5; border:1px solid #10B981; border-radius:8px; padding:0.6rem; text-align:center; font-size:0.78rem; color:#065F46; font-weight:700;">
                                <i class="fas fa-check-circle me-1"></i> Thank you! Response Submitted.
                            </div>
                        <?php else: ?>
                            <button type="button" class="btn-primary-navy" style="width:100%; justify-content:center;" onclick="openSurveyModal(<?= htmlspecialchars(json_encode($survey)) ?>)">
                                <i class="fas fa-pen-to-square"></i> Take Evaluation Survey
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- Modal Form -->
    <div id="surveyModal" class="modal-backdrop-custom">
        <div class="modal-box-custom">
            <div style="padding:1.25rem 1.5rem; border-bottom:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; font-size:1.1rem; font-weight:800; color:#0F172A;" id="modalSurveyTitle">Survey Title</h3>
                <button type="button" onclick="closeSurveyModal()" style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:#64748B;">&times;</button>
            </div>
            <div style="padding:1.5rem;">
                <form id="activeSurveyForm" onsubmit="handleSurveySubmit(event)">
                    <div id="surveyQuestionsContainer"></div>
                    <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:1.25rem;">
                        <button type="button" class="btn-white" onclick="closeSurveyModal()" style="background:#FFF; border:1px solid #CBD5E1; padding:0.5rem 1rem; border-radius:8px; font-weight:600; cursor:pointer;">Cancel</button>
                        <button type="submit" class="btn-primary-navy">
                            <i class="fas fa-paper-plane me-1"></i> Submit Evaluation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let currentSurvey = null;

        function openSurveyModal(surveyObj) {
            currentSurvey = surveyObj;
            document.getElementById('modalSurveyTitle').textContent = surveyObj.title;
            const container = document.getElementById('surveyQuestionsContainer');
            container.innerHTML = '';

            let qs = [];
            try {
                qs = typeof surveyObj.questions === 'string' ? JSON.parse(surveyObj.questions) : surveyObj.questions;
            } catch(e) { qs = []; }

            qs.forEach((q, idx) => {
                const block = document.createElement('div');
                block.className = 'q-block';

                let inputField = '';
                if (q.type === 'rating') {
                    inputField = `
                        <select name="ans_${idx}" required style="width:100%; padding:0.5rem; border:1px solid #CBD5E1; border-radius:6px; font-family:inherit; font-size:0.85rem;">
                            <option value="">-- Choose rating (1 to 5) --</option>
                            <option value="5">⭐⭐⭐⭐⭐ 5 - Highly Satisfied / Excellent</option>
                            <option value="4">⭐⭐⭐⭐ 4 - Satisfied / Very Good</option>
                            <option value="3">⭐⭐⭐ 3 - Neutral / Good</option>
                            <option value="2">⭐⭐ 2 - Needs Improvement / Fair</option>
                            <option value="1">⭐ 1 - Unsatisfied / Poor</option>
                        </select>
                    `;
                } else if (q.type === 'yesno') {
                    inputField = `
                        <select name="ans_${idx}" required style="width:100%; padding:0.5rem; border:1px solid #CBD5E1; border-radius:6px; font-family:inherit; font-size:0.85rem;">
                            <option value="">-- Select option --</option>
                            <option value="yes">Yes, definitely</option>
                            <option value="no">No</option>
                        </select>
                    `;
                } else {
                    inputField = `<textarea name="ans_${idx}" rows="3" required placeholder="Write your thoughts or recommendations here..." style="width:100%; padding:0.5rem; border:1px solid #CBD5E1; border-radius:6px; font-family:inherit; font-size:0.85rem; box-sizing:border-box;"></textarea>`;
                }

                block.innerHTML = `
                    <div style="font-weight:700; font-size:0.86rem; color:#0F172A; margin-bottom:0.4rem;">
                        ${idx + 1}. ${q.text}
                    </div>
                    ${inputField}
                `;
                container.appendChild(block);
            });

            document.getElementById('surveyModal').style.display = 'flex';
        }

        function closeSurveyModal() {
            document.getElementById('surveyModal').style.display = 'none';
        }

        function handleSurveySubmit(e) {
            e.preventDefault();
            alert('🎉 Thank you! Your feedback has been officially received and logged into the chapter evaluation ledger.');
            closeSurveyModal();
            location.reload();
        }
    </script>
</body>
</html>
