<?php
if (!isset($current_page)) { $current_page = basename(__FILE__, '.php'); }
require_once __DIR__ . '/../auth_check.php';
require_role(['admin', 'super_admin', 'eb_auditor']);

require_once __DIR__ . '/../../includes/db.php';

$db = Database::getInstance();

// Get policies
$policies = $db->fetchAll("SELECT * FROM policy_compliance ORDER BY created_at DESC");

// Get compliance summary
$totalPolicies = count($policies);
$compliantCount = count(array_filter($policies, fn($p) => $p['compliance_status'] === 'compliant'));
$pendingCount = count(array_filter($policies, fn($p) => $p['compliance_status'] === 'pending'));
$nonCompliantCount = count(array_filter($policies, fn($p) => $p['compliance_status'] === 'non_compliant'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Policy Compliance Tracking - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/professional.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/font-awesome.css">
    <style>
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .summary-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            text-align: center;
        }
        .summary-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-navy);
        }
        .summary-label {
            font-size: var(--font-size-sm);
            color: var(--gray-600);
            margin-top: 0.5rem;
        }
        .table-container {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            background: var(--primary-navy);
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: var(--font-weight-semibold);
        }
        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-medium);
        }
        .status-compliant { background: var(--success-light); color: var(--success-dark); }
        .status-pending { background: var(--warning-light); color: var(--warning-dark); }
        .status-non_compliant { background: var(--error-light); color: var(--error-dark); }
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            max-width: 500px;
            width: 90%;
        }
        .form-group { margin-bottom: 1rem; }
        .form-group label {
            display: block;
            font-weight: var(--font-weight-medium);
            margin-bottom: 0.5rem;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>Policy Compliance Tracking</h1>
                    <p class="text-gray">Monitor and track chapter policy compliance</p>
                </div>
                <button onclick="openAddModal()" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Policy
                </button>
            </div>

            <div class="summary-cards">
                <div class="summary-card">
                    <div class="summary-value"><?php echo $totalPolicies; ?></div>
                    <div class="summary-label">Total Policies</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value" style="color: var(--success);"><?php echo $compliantCount; ?></div>
                    <div class="summary-label">Compliant</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value" style="color: var(--warning);"><?php echo $pendingCount; ?></div>
                    <div class="summary-label">Pending Review</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value" style="color: var(--error);"><?php echo $nonCompliantCount; ?></div>
                    <div class="summary-label">Non-Compliant</div>
                </div>
            </div>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Policy Name</th>
                            <th>Category</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Last Reviewed</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($policies)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2rem;">No policies tracked</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($policies as $policy): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($policy['policy_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($policy['category'] ?? 'General'); ?></td>
                                <td><?php echo htmlspecialchars($policy['due_date'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $policy['compliance_status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $policy['compliance_status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo $policy['last_reviewed'] ? date('M j, Y', strtotime($policy['last_reviewed'])) : 'Never'; ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button onclick="reviewPolicy('<?php echo $policy['id']; ?>')" class="btn btn-sm btn-primary" title="Review">
                                            <i class="fas fa-clipboard-check"></i>
                                        </button>
                                        <button onclick="editPolicy('<?php echo $policy['id']; ?>')" class="btn btn-sm btn-secondary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deletePolicy('<?php echo $policy['id']; ?>')" class="btn btn-sm btn-danger" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Add Policy Modal -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <h2>Add Policy</h2>
            <form id="addPolicyForm">
                <div class="form-group">
                    <label>Policy Name</label>
                    <input type="text" name="policy_name" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" required>
                        <option value="Constitutional">Constitutional</option>
                        <option value="Financial">Financial</option>
                        <option value="Operational">Operational</option>
                        <option value="Membership">Membership</option>
                        <option value="Event">Event</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Due Date</label>
                    <input type="date" name="due_date">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description"></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="closeAddModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Policy</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
        }

        document.getElementById('addPolicyForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            
            fetch('<?php echo BASE_URL; ?>/api/add-policy.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    closeAddModal();
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            });
        });

        function reviewPolicy(id) {
            const status = prompt('Enter compliance status (compliant, pending, non_compliant):');
            if (status && ['compliant', 'pending', 'non_compliant'].includes(status)) {
                fetch('<?php echo BASE_URL; ?>/api/update-policy-status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, compliance_status: status })
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + result.error);
                    }
                });
            }
        }

        function editPolicy(id) {
            alert('Edit policy: ' + id);
        }

        function deletePolicy(id) {
            if (confirm('Delete this policy?')) {
                fetch('<?php echo BASE_URL; ?>/api/delete-policy.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + result.error);
                    }
                });
            }
        }
    </script>
</body>
</html>
