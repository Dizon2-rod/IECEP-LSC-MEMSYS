<?php
if (!isset($current_page)) { $current_page = basename(__FILE__, '.php'); }
require_once __DIR__ . '/../auth_check.php';
require_role(['admin', 'super_admin', 'eb_secretary']);

require_once __DIR__ . '/../../includes/db.php';

$db = Database::getInstance();

// Get filters
$status = $_GET['status'] ?? '';

// Build query
$where = ['1=1'];
$params = [];

if (!empty($status)) {
    $where[] = "status = ?";
    $params[] = $status;
}

$whereClause = implode(' AND ', $where);

// Get newsletters
$newsletters = $db->fetchAll("SELECT n.*, up.full_name as created_by_name
    FROM newsletters n
    LEFT JOIN user_profiles up ON n.created_by = up.user_id
    WHERE $whereClause
    ORDER BY n.created_at DESC
    LIMIT 50", $params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsletter System - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/professional.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/font-awesome.css">
    <style>
        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1.5rem;
            background: var(--gray-50);
            border-radius: var(--radius-lg);
        }
        .filter-group label {
            display: block;
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-medium);
            color: var(--gray-600);
            margin-bottom: 0.5rem;
        }
        .filter-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
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
        .status-draft { background: var(--gray-200); color: var(--gray-700); }
        .status-sent { background: var(--success-light); color: var(--success-dark); }
        .status-scheduled { background: var(--info-light); color: var(--info-dark); }
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            text-align: center;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-navy);
        }
        .stat-label {
            font-size: var(--font-size-sm);
            color: var(--gray-600);
            margin-top: 0.5rem;
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
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
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
            min-height: 200px;
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
                    <h1>Newsletter System</h1>
                    <p class="text-gray">Create and send newsletters to chapter members</p>
                </div>
                <button onclick="openCreateModal()" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Newsletter
                </button>
            </div>

            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($newsletters); ?></div>
                    <div class="stat-label">Total Newsletters</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count(array_filter($newsletters, fn($n) => $n['status'] === 'sent')); ?></div>
                    <div class="stat-label">Sent</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo count(array_filter($newsletters, fn($n) => $n['status'] === 'scheduled')); ?></div>
                    <div class="stat-label">Scheduled</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo array_sum(array_column($newsletters, 'recipient_count')); ?></div>
                    <div class="stat-label">Total Recipients</div>
                </div>
            </div>

            <div class="filters">
                <div class="filter-group">
                    <label>Status</label>
                    <select id="statusFilter">
                        <option value="">All Status</option>
                        <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="scheduled" <?php echo $status === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                        <option value="sent" <?php echo $status === 'sent' ? 'selected' : ''; ?>>Sent</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button onclick="applyFilters()" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                </div>
            </div>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Send Date</th>
                            <th>Recipients</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($newsletters)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2rem;">No newsletters found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($newsletters as $newsletter): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($newsletter['subject']); ?></strong></td>
                                <td><?php echo $newsletter['scheduled_date'] ? date('M j, Y g:i A', strtotime($newsletter['scheduled_date'])) : 'N/A'; ?></td>
                                <td><?php echo $newsletter['recipient_count']; ?> recipients</td>
                                <td>
                                    <span class="status-badge status-<?php echo $newsletter['status']; ?>">
                                        <?php echo ucfirst($newsletter['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($newsletter['created_by_name'] ?? 'System'); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button onclick="viewNewsletter('<?php echo $newsletter['id']; ?>')" class="btn btn-sm btn-secondary" title="View">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="sendNewsletter('<?php echo $newsletter['id']; ?>')" class="btn btn-sm btn-primary" title="Send">
                                            <i class="fas fa-paper-plane"></i>
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

    <!-- Create Modal -->
    <div class="modal" id="createModal">
        <div class="modal-content">
            <h2>Create Newsletter</h2>
            <form id="createForm">
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject" required>
                </div>
                <div class="form-group">
                    <label>Target Audience</label>
                    <select name="target_audience" required>
                        <option value="all">All Members</option>
                        <option value="active">Active Members Only</option>
                        <option value="institution">Specific Institution</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Scheduled Date</label>
                    <input type="datetime-local" name="scheduled_date">
                </div>
                <div class="form-group">
                    <label>Content (HTML supported)</label>
                    <textarea name="content" required></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="closeCreateModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function applyFilters() {
            const status = document.getElementById('statusFilter').value;
            
            const params = new URLSearchParams();
            if (status) params.set('status', status);
            
            window.location.href = '?' + params.toString();
        }

        function openCreateModal() {
            document.getElementById('createModal').classList.add('active');
        }

        function closeCreateModal() {
            document.getElementById('createModal').classList.remove('active');
        }

        document.getElementById('createForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            
            fetch('<?php echo BASE_URL; ?>/api/create-newsletter.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    closeCreateModal();
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            });
        });

        function viewNewsletter(id) {
            window.open('<?php echo BASE_URL; ?>/api/view-newsletter.php?id=' + id, '_blank');
        }

        function sendNewsletter(id) {
            if (confirm('Send this newsletter now?')) {
                fetch('<?php echo BASE_URL; ?>/api/send-newsletter.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ newsletter_id: id })
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('Newsletter sent successfully to ' + result.recipients + ' recipients!');
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
