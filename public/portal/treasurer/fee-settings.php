<?php
require_once __DIR__ . '/../bootstrap.php';
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/role-config.php';

// Check if user is logged in and has treasurer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'eb_treasurer') {
    header('Location: ' . PORTAL_URL . '/login.php');
    exit;
}

// Initialize FeeCalculator
$feeCalculator = new \App\Lib\FeeCalculator($supabaseClient);

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_bracket':
                $bracket = [
                    'min_members' => (int) $_POST['min_members'],
                    'max_members' => (int) $_POST['max_members'],
                    'fee_amount' => (float) $_POST['fee_amount'],
                    'description' => $_POST['description'] ?? ''
                ];

                if ($feeCalculator->updateBracket($bracket)) {
                    $message = 'Fee bracket added successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to add fee bracket.';
                    $messageType = 'error';
                }
                break;

            case 'update_bracket':
                $bracket = [
                    'id' => (int) $_POST['bracket_id'],
                    'min_members' => (int) $_POST['min_members'],
                    'max_members' => (int) $_POST['max_members'],
                    'fee_amount' => (float) $_POST['fee_amount'],
                    'description' => $_POST['description'] ?? ''
                ];

                if ($feeCalculator->updateBracket($bracket)) {
                    $message = 'Fee bracket updated successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update fee bracket.';
                    $messageType = 'error';
                }
                break;

            case 'delete_bracket':
                if ($feeCalculator->deleteBracket((int) $_POST['bracket_id'])) {
                    $message = 'Fee bracket deleted successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to delete fee bracket.';
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get all fee brackets
$feeBrackets = $feeCalculator->getAllBrackets();

$pageTitle = 'Fee Settings';
include '../../includes/dashboard-layout.php';
?>

<div class="dashboard-container">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Fee Settings</h1>
                    <p class="text-muted">Manage membership fee brackets</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBracketModal">
                    <i class="fas fa-plus me-2"></i>Add Fee Bracket
                </button>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Current Fee Brackets</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($feeBrackets)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calculator fa-3x text-muted mb-3"></i>
                            <h5>No Fee Brackets Configured</h5>
                            <p class="text-muted">Add your first fee bracket to get started.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Member Range</th>
                                        <th>Fee Amount</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($feeBrackets as $bracket): ?>
                                        <tr>
                                            <td>
                                                <?= $bracket['min_members'] ?> - <?= $bracket['max_members'] ?> members
                                            </td>
                                            <td>₱<?= number_format($bracket['fee_amount'], 2) ?></td>
                                            <td><?= htmlspecialchars($bracket['description'] ?? '') ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary me-2"
                                                        onclick="editBracket(<?= htmlspecialchars(json_encode($bracket)) ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger"
                                                        onclick="deleteBracket(<?= $bracket['id'] ?>, '<?= htmlspecialchars($bracket['description'] ?? 'this bracket') ?>')">
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

<!-- Add Bracket Modal -->
<div class="modal fade" id="addBracketModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Fee Bracket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_bracket">

                    <div class="mb-3">
                        <label for="min_members" class="form-label">Minimum Members</label>
                        <input type="number" class="form-control" id="min_members" name="min_members" required min="1">
                    </div>

                    <div class="mb-3">
                        <label for="max_members" class="form-label">Maximum Members</label>
                        <input type="number" class="form-control" id="max_members" name="max_members" required min="1">
                    </div>

                    <div class="mb-3">
                        <label for="fee_amount" class="form-label">Fee Amount (₱)</label>
                        <input type="number" class="form-control" id="fee_amount" name="fee_amount" required min="0" step="0.01">
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description (Optional)</label>
                        <input type="text" class="form-control" id="description" name="description">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Bracket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Bracket Modal -->
<div class="modal fade" id="editBracketModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Fee Bracket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_bracket">
                    <input type="hidden" name="bracket_id" id="edit_bracket_id">

                    <div class="mb-3">
                        <label for="edit_min_members" class="form-label">Minimum Members</label>
                        <input type="number" class="form-control" id="edit_min_members" name="min_members" required min="1">
                    </div>

                    <div class="mb-3">
                        <label for="edit_max_members" class="form-label">Maximum Members</label>
                        <input type="number" class="form-control" id="edit_max_members" name="max_members" required min="1">
                    </div>

                    <div class="mb-3">
                        <label for="edit_fee_amount" class="form-label">Fee Amount (₱)</label>
                        <input type="number" class="form-control" id="edit_fee_amount" name="fee_amount" required min="0" step="0.01">
                    </div>

                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description (Optional)</label>
                        <input type="text" class="form-control" id="edit_description" name="description">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Bracket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_bracket">
                    <input type="hidden" name="bracket_id" id="delete_bracket_id">
                    <p>Are you sure you want to delete <strong id="delete_bracket_name"></strong>? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editBracket(bracket) {
    document.getElementById('edit_bracket_id').value = bracket.id;
    document.getElementById('edit_min_members').value = bracket.min_members;
    document.getElementById('edit_max_members').value = bracket.max_members;
    document.getElementById('edit_fee_amount').value = bracket.fee_amount;
    document.getElementById('edit_description').value = bracket.description || '';

    new bootstrap.Modal(document.getElementById('editBracketModal')).show();
}

function deleteBracket(id, name) {
    document.getElementById('delete_bracket_id').value = id;
    document.getElementById('delete_bracket_name').textContent = name;

    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include '../../includes/footer.php'; ?>