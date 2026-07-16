<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['member']);

require_once __DIR__ . '/../../includes/role-config.php';
require_once __DIR__ . '/../../bootstrap.php';

$current_page = 'surveys';

$user = get_user_info();
$member_id = $_SESSION['member_id'] ?? $user['member_id'] ?? null;

if (!$member_id) {
    header('Location: /login.php');
    exit;
}

$supabase = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);

// Fetch available surveys
try {
    $surveys = $supabase->select('surveys', [
        'is_active' => 'eq.true',
        'order' => 'created_at.desc'
    ]);
} catch (Exception $e) {
    $surveys = [];
}

// Fetch member's attended events
try {
    $attendedEvents = $supabase->select('event_registrations', [
        'member_id' => 'eq.' . $member_id,
        'status' => 'eq.attended'
    ]);
    $attendedEventIds = array_column($attendedEvents, 'event_id');
} catch (Exception $e) {
    $attendedEventIds = [];
}

// Filter surveys for attended events or general surveys
$availableSurveys = [];
foreach ($surveys as $survey) {
    $eventId = $survey['event_id'] ?? null;
    if (!$eventId || in_array($eventId, $attendedEventIds)) {
        $availableSurveys[] = $survey;
    }
}

// Check which surveys the member has already submitted
try {
    $submittedSurveys = $supabase->select('survey_responses', [
        'member_id' => 'eq.' . $member_id
    ]);
    $submittedSurveyIds = array_column($submittedSurveys, 'survey_id');
} catch (Exception $e) {
    $submittedSurveyIds = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/../../includes/head-meta.php'; ?>
    <title>Surveys - Member Portal</title>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="container py-5">
                <div class="mb-4">
                    <h1 class="h2 mb-2">Event Feedback Surveys</h1>
                    <p class="text-muted">Share your feedback on events you've attended</p>
                </div>

                <?php if (empty($availableSurveys)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No surveys available at this time. Surveys will appear here after you attend events.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($availableSurveys as $survey): ?>
                            <?php
                            $isSubmitted = in_array($survey['id'] ?? '', $submittedSurveyIds);
                            $questions = json_decode($survey['questions'] ?? '[]', true);
                            ?>
                            <div class="col-md-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h5 class="card-title"><?= htmlspecialchars($survey['title'] ?? 'Survey') ?></h5>
                                            <?php if ($isSubmitted): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check me-1"></i>Submitted
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-muted small mb-3">
                                            <?= htmlspecialchars($survey['description'] ?? 'No description') ?>
                                        </p>
                                        <p class="small mb-3">
                                            <i class="fas fa-list-ol me-1"></i>
                                            <?= count($questions) ?> questions
                                        </p>
                                        <?php if ($isSubmitted): ?>
                                            <button class="btn btn-outline-secondary w-100" disabled>
                                                <i class="fas fa-check-circle me-1"></i>Already Submitted
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-primary w-100" onclick="openSurvey('<?= htmlspecialchars($survey['id'] ?? '') ?>')">
                                                <i class="fas fa-pen me-1"></i>Take Survey
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Survey Modal -->
    <div class="modal fade" id="surveyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="surveyModalTitle">Survey</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="surveyForm">
                        <input type="hidden" id="currentSurveyId">
                        <div id="surveyQuestions"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitSurvey()">
                        <i class="fas fa-paper-plane me-1"></i>Submit
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentSurveyData = null;

        function openSurvey(surveyId) {
            fetch('/api/surveys.php?action=list')
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        const survey = result.data.find(s => s.id === surveyId);
                        if (survey) {
                            currentSurveyData = survey;
                            document.getElementById('currentSurveyId').value = surveyId;
                            document.getElementById('surveyModalTitle').textContent = survey.title;
                            
                            const questions = JSON.parse(survey.questions);
                            const container = document.getElementById('surveyQuestions');
                            container.innerHTML = '';
                            
                            questions.forEach((q, index) => {
                                let inputHtml = '';
                                if (q.type === 'text') {
                                    inputHtml = `<textarea class="form-control" name="q_${index}" rows="3" required></textarea>`;
                                } else if (q.type === 'rating') {
                                    inputHtml = `
                                        <select class="form-select" name="q_${index}" required>
                                            <option value="">Select rating</option>
                                            <option value="5">5 - Excellent</option>
                                            <option value="4">4 - Very Good</option>
                                            <option value="3">3 - Good</option>
                                            <option value="2">2 - Fair</option>
                                            <option value="1">1 - Poor</option>
                                        </select>
                                    `;
                                } else if (q.type === 'yesno') {
                                    inputHtml = `
                                        <select class="form-select" name="q_${index}" required>
                                            <option value="">Select answer</option>
                                            <option value="yes">Yes</option>
                                            <option value="no">No</option>
                                        </select>
                                    `;
                                }
                                
                                container.innerHTML += `
                                    <div class="mb-3">
                                        <label class="form-label">${index + 1}. ${q.text}</label>
                                        ${inputHtml}
                                    </div>
                                `;
                            });
                            
                            new bootstrap.Modal(document.getElementById('surveyModal')).show();
                        }
                    }
                });
        }

        function submitSurvey() {
            const surveyId = document.getElementById('currentSurveyId').value;
            const answers = {};
            
            document.querySelectorAll('#surveyQuestions [name]').forEach(input => {
                const key = input.name.replace('q_', '');
                answers[key] = input.value;
            });
            
            if (Object.keys(answers).length === 0) {
                alert('Please answer all questions');
                return;
            }
            
            fetch('/api/surveys.php?action=submit', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    survey_id: surveyId,
                    answers,
                    event_id: currentSurveyData?.event_id || null
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('Thank you for your feedback!');
                    bootstrap.Modal.getInstance(document.getElementById('surveyModal')).hide();
                    location.reload();
                } else {
                    alert('Error: ' + (result.error || 'Failed to submit survey'));
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }
    </script>
</body>
</html>
