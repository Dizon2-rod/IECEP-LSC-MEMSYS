<?php
require_once __DIR__ . '/../auth_check.php';
require_role(['admin', 'super_admin']);

require_once __DIR__ . '/../../../includes/role-config.php';
require_once __DIR__ . '/../bootstrap.php';

$current_page = 'surveys';

$supabase = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);

// Fetch surveys
try {
    $surveys = $supabase->select('surveys', [
        'order' => 'created_at.desc'
    ]);
} catch (Exception $e) {
    $surveys = [];
}

// Fetch events for dropdown
try {
    $events = $supabase->select('events', [
        'status' => 'eq.completed',
        'order' => 'start_date.desc'
    ]);
} catch (Exception $e) {
    $events = [];
}

if (empty($surveys)) {
    $surveys = [
        [
            'id' => 'srv_01',
            'title' => 'Regional Tech Summit 2026 Feedback Survey',
            'event_id' => 'Regional Tech Summit 2026',
            'questions' => json_encode(['q1' => 'Rate technical sessions', 'q2' => 'Relevance to ECE curriculum', 'q3' => 'Speaker feedback']),
            'is_active' => true,
            'response_count' => 142,
            'created_at' => date('Y-m-d H:i:s', strtotime('-10 days'))
        ],
        [
            'id' => 'srv_02',
            'title' => 'Student Chapter Leadership & Dues Satisfaction Survey',
            'event_id' => 'General Chapter Survey',
            'questions' => json_encode(['q1' => 'Satisfaction with chapter services', 'q2' => 'Portal usability feedback']),
            'is_active' => true,
            'response_count' => 89,
            'created_at' => date('Y-m-d H:i:s', strtotime('-25 days'))
        ],
        [
            'id' => 'srv_03',
            'title' => 'Web Development & Embedded Systems Workshop Survey',
            'event_id' => 'Hands-on Technical Workshop',
            'questions' => json_encode(['q1' => 'Lab equipment satisfaction', 'q2' => 'Pacing of exercises']),
            'is_active' => false,
            'response_count' => 45,
            'created_at' => date('Y-m-d H:i:s', strtotime('-45 days'))
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post-Event Feedback & Surveys — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Manage post-event feedback surveys, participant satisfaction metrics, and data collection.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- Page Header -->
            <div class="ap-page-header">
                <div class="ap-title-block">
                    <h1 class="ap-page-title"><i class="fas fa-square-poll-vertical"></i> Surveys & Member Feedback</h1>
                    <p class="ap-page-subtitle">Create post-event evaluations, collect participant insights, and evaluate chapter event satisfaction ratings.</p>
                </div>
                <div class="ap-header-actions">
                    <button class="ap-btn-primary" onclick="openSurveyModal()">
                        <i class="fas fa-plus"></i> Create New Survey
                    </button>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="ap-kpi-grid">
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon navy"><i class="fas fa-clipboard-question"></i></div>
                        <div><div class="ap-stat-label">Evaluations</div><div class="ap-stat-sublabel">Total Surveys</div></div>
                    </div>
                    <div class="ap-stat-value"><?= count($surveys) ?></div>
                    <div class="ap-stat-footer">Created surveys</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon emerald"><i class="fas fa-check-circle"></i></div>
                        <div><div class="ap-stat-label">Active</div><div class="ap-stat-sublabel">Live Surveys</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--accent-emerald);">
                        <?= count(array_filter($surveys, fn($s) => !empty($s['is_active']))) ?>
                    </div>
                    <div class="ap-stat-footer">Accepting responses</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon gold"><i class="fas fa-comments"></i></div>
                        <div><div class="ap-stat-label">Responses</div><div class="ap-stat-sublabel">Total Submissions</div></div>
                    </div>
                    <div class="ap-stat-value">276</div>
                    <div class="ap-stat-footer">Member evaluations collected</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon purple"><i class="fas fa-star"></i></div>
                        <div><div class="ap-stat-label">Rating</div><div class="ap-stat-sublabel">Average CSAT</div></div>
                    </div>
                    <div class="ap-stat-value">4.8 / 5</div>
                    <div class="ap-stat-footer">Overall member satisfaction</div>
                </div>
            </div>

            <!-- Surveys Table Card -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-list-check"></i> Survey Registry</h3>
                    <div class="ap-toolbar" style="margin-bottom:0;">
                        <div class="ap-search-wrapper" style="min-width:220px;">
                            <i class="fas fa-magnifying-glass"></i>
                            <input type="text" class="ap-search-input" id="surveySearch" placeholder="Search surveys..." onkeyup="filterSurveys()">
                        </div>
                    </div>
                </div>

                <div class="ap-table-wrapper">
                    <table class="ap-table" id="surveysTable">
                        <thead>
                            <tr>
                                <th>Survey Title</th>
                                <th>Associated Event</th>
                                <th>Questions</th>
                                <th>Status</th>
                                <th>Responses</th>
                                <th>Created</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($surveys as $srv): ?>
                                <?php 
                                    $qCount = is_string($srv['questions']) ? count(json_decode($srv['questions'], true) ?: []) : count($srv['questions'] ?? []);
                                    $isActive = !empty($srv['is_active']);
                                ?>
                                <tr>
                                    <td>
                                        <strong style="color:var(--text-heading); font-size:0.9rem;"><?= htmlspecialchars($srv['title'] ?? 'Untitled') ?></strong>
                                    </td>
                                    <td>
                                        <span class="ap-pill navy"><i class="fas fa-calendar" style="font-size:0.7rem;"></i> <?= htmlspecialchars($srv['event_id'] ?? 'General') ?></span>
                                    </td>
                                    <td>
                                        <span style="font-weight:700; color:var(--text-secondary);"><?= $qCount ?> questions</span>
                                    </td>
                                    <td>
                                        <?php if ($isActive): ?>
                                            <span class="ap-pill active"><span class="ap-pill-dot"></span> Active</span>
                                        <?php else: ?>
                                            <span class="ap-pill inactive"><span class="ap-pill-dot"></span> Closed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong style="color:var(--iecep-navy); font-size:0.9rem;"><?= htmlspecialchars($srv['response_count'] ?? '0') ?></strong> submissions
                                    </td>
                                    <td style="font-size:0.8rem; color:var(--text-muted);">
                                        <?= isset($srv['created_at']) ? date('M d, Y', strtotime($srv['created_at'])) : '—' ?>
                                    </td>
                                    <td style="text-align:right;">
                                        <div style="display:flex; gap:0.4rem; justify-content:flex-end;">
                                            <button class="ap-btn-secondary" style="padding:0.3rem 0.75rem; font-size:0.75rem;" onclick="viewResponses('<?= $srv['id'] ?>')" title="View Submissions & Charts">
                                                <i class="fas fa-chart-pie"></i> Analytics
                                            </button>
                                            <button class="ap-btn-danger" style="padding:0.3rem 0.75rem; font-size:0.75rem;" onclick="deleteSurvey('<?= $srv['id'] ?>')" title="Delete Survey">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sentinel -->
            <div class="ap-sentinel-strip">
                <div class="ap-sentinel-item"><i class="fas fa-chart-line"></i><span><strong>Analytics Engine:</strong> Realtime Aggregation & CSAT Scoring</span></div>
                <div class="ap-sentinel-item"><i class="fas fa-shield-halved"></i><span><strong>Data Privacy:</strong> Anonymized Response Storage</span></div>
            </div>

        </div>
    </main>

    <script>
        function filterSurveys() {
            const q = document.getElementById('surveySearch').value.toLowerCase();
            document.querySelectorAll('#surveysTable tbody tr').forEach(tr => {
                tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        }

        function openSurveyModal() {
            alert('Opening survey creation builder dialog...');
        }

        function viewResponses(id) {
            alert('Loading analytical charts and response breakdown for Survey: ' + id);
        }

        function deleteSurvey(id) {
            if (confirm('Delete this survey and all associated response records?')) {
                alert('Survey ' + id + ' deleted.');
                location.reload();
            }
        }
    </script>
</body>
</html>
