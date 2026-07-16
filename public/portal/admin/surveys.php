<?php
require_once __DIR__ . '/../auth_check.php';
require_role(['admin']);

require_once __DIR__ . '/../../includes/role-config.php';
require_once __DIR__ . '/../../bootstrap.php';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/../../includes/head-meta.php'; ?>
    <title>Surveys - Admin Portal</title>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="container py-5">
                <div class="mb-4">
                    <h1 class="h2 mb-2">Post-Event Surveys</h1>
                    <p class="text-muted">Create and manage event feedback surveys</p>
                </div>

                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Create New Survey</h5>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSurveyModal">
                            <i class="fas fa-plus me-1"></i>New Survey
                        </button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <?php if (empty($surveys)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                No surveys created yet.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Event</th>
                                            <th>Questions</th>
                                            <th>Status</th>
                                            <th>Responses</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($surveys as $survey): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($survey['title'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($survey['event_id'] ?? 'General') ?></td>
                                                <td><?= count(json_decode($survey['questions'] ?? '[]', true)) ?></td>
                                                <td>
                                                    <?php if ($survey['is_active'] ?? false): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="#" onclick="viewResponses('<?= htmlspecialchars($survey['id'] ?? '') ?>')" class="btn btn-sm btn-outline-primary">
                                                        View
                                                    </a>
                                                </td>
                                                <td><?= date('M d, Y', strtotime($survey['created_at'] ?? '')) ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-secondary" onclick="editSurvey('<?= htmlspecialchars($survey['id'] ?? '') ?>')">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteSurvey('<?= htmlspecialchars($survey['id'] ?? '') ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Survey Modal -->
    <div class="modal fade" id="createSurveyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Survey</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="createSurveyForm">
                        <div class="mb-3">
                            <label class="form-label">Survey Title</label>
                            <input type="text" class="form-control" id="surveyTitle" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="surveyDescription" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Link to Event (Optional)</label>
                            <select class="form-select" id="surveyEvent">
                                <option value="">General Survey</option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?= htmlspecialchars($event['id'] ?? '') ?>">
                                        <?= htmlspecialchars($event['title'] ?? '') ?> - <?= date('M d, Y', strtotime($event['start_date'] ?? '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Questions</label>
                            <div id="questionsContainer">
                                <div class="question-item mb-3">
                                    <input type="text" class="form-control mb-2" placeholder="Question 1" required>
                                    <select class="form-select">
                                        <option value="text">Text Answer</option>
                                        <option value="rating">Rating (1-5)</option>
                                        <option value="yesno">Yes/No</option>
                                    </select>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addQuestion()">
                                <i class="fas fa-plus me-1"></i>Add Question
                            </button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="createSurvey()">
                        <i class="fas fa-save me-1"></i>Create Survey
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let questionCount = 1;

        function addQuestion() {
            questionCount++;
            const container = document.getElementById('questionsContainer');
            const questionHtml = `
                <div class="question-item mb-3">
                    <input type="text" class="form-control mb-2" placeholder="Question ${questionCount}" required>
                    <select class="form-select">
                        <option value="text">Text Answer</option>
                        <option value="rating">Rating (1-5)</option>
                        <option value="yesno">Yes/No</option>
                    </select>
                    <button type="button" class="btn btn-sm btn-outline-danger mt-2" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i> Remove
                    </button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', questionHtml);
        }

        async function createSurvey() {
            const title = document.getElementById('surveyTitle').value;
            const description = document.getElementById('surveyDescription').value;
            const eventId = document.getElementById('surveyEvent').value;
            
            const questions = [];
            document.querySelectorAll('.question-item').forEach(item => {
                const questionText = item.querySelector('input[type="text"]').value;
                const questionType = item.querySelector('select').value;
                if (questionText) {
                    questions.push({
                        text: questionText,
                        type: questionType
                    });
                }
            });

            if (!title || questions.length === 0) {
                alert('Please provide a title and at least one question');
                return;
            }

            try {
                const response = await fetch('/api/surveys.php?action=create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        title,
                        description,
                        event_id: eventId || null,
                        questions,
                        target_roles: ['member']
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('Survey created successfully');
                    location.reload();
                } else {
                    alert('Error: ' + (result.error || 'Failed to create survey'));
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }

        function viewResponses(surveyId) {
            window.open(`/portal/admin/survey-responses.php?survey_id=${surveyId}`, '_blank');
        }

        function editSurvey(surveyId) {
            alert('Edit functionality coming soon');
        }

        function deleteSurvey(surveyId) {
            if (confirm('Are you sure you want to delete this survey?')) {
                alert('Delete functionality coming soon');
            }
        }
    </script>
</body>
</html>
