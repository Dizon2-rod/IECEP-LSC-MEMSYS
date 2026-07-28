<?php
if (!isset($current_page)) { $current_page = basename(__FILE__, '.php'); }
require_once __DIR__ . '/../auth_check.php';
require_role(['admin', 'super_admin', 'eb_secretary']);

require_once __DIR__ . '/../../includes/db.php';

$db = Database::getInstance();

// Get filters
$status = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';

// Build query
$where = ['1=1'];
$params = [];

if (!empty($status)) {
    $where[] = "status = ?";
    $params[] = $status;
}

if (!empty($priority)) {
    $where[] = "priority = ?";
    $params[] = $priority;
}

$whereClause = implode(' AND ', $where);

// Get announcements
$announcements = $db->fetchAll("SELECT a.*, i.name as institution_name, up.full_name as created_by_name
    FROM announcements a
    LEFT JOIN institutions i ON a.institution_id = i.id
    LEFT JOIN user_profiles up ON a.created_by = up.user_id
    WHERE $whereClause
    ORDER BY a.created_at DESC
    LIMIT 100", $params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcement System - IECEP-LSC MEMSYS</title>
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
        .status-published { background: var(--success-light); color: var(--success-dark); }
        .status-archived { background: var(--warning-light); color: var(--warning-dark); }
        .priority-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-md);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-semibold);
        }
        .priority-high { background: var(--error-light); color: var(--error-dark); }
        .priority-medium { background: var(--warning-light); color: var(--warning-dark); }
        .priority-low { background: var(--info-light); color: var(--info-dark); }
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
            min-height: 150px;
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
                    <h1>Announcement System</h1>
                    <p class="text-gray">Create and manage chapter announcements</p>
                </div>
                <button onclick="openCreateModal()" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Announcement
                </button>
            </div>

            <div class="filters">
                <div class="filter-group">
                    <label>Status</label>
                    <select id="statusFilter">
                        <option value="">All Status</option>
                        <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo $status === 'published' ? 'selected' : ''; ?>>Published</option>
                        <option value="archived" <?php echo $status === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Priority</label>
                    <select id="priorityFilter">
                        <option value="">All Priority</option>
                        <option value="high" <?php echo $priority === 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="medium" <?php echo $priority === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="low" <?php echo $priority === 'low' ? 'selected' : ''; ?>>Low</option>
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
                            <th>Title</th>
                            <th>Priority</th>
                            <th>Institution</th>
                            <th>Publish Date</th>
                            <th>Expiry Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($announcements)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2rem;">No announcements found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($announcements as $announcement): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($announcement['title']); ?></strong>
                                    <br><small class="text-gray"><?php echo htmlspecialchars(substr($announcement['content'], 0, 50)); ?>...</small>
                                </td>
                                <td>
                                    <span class="priority-badge priority-<?php echo $announcement['priority']; ?>">
                                        <?php echo ucfirst($announcement['priority']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($announcement['institution_name'] ?? 'All'); ?></td>
                                <td><?php echo $announcement['publish_date'] ? date('M j, Y', strtotime($announcement['publish_date'])) : 'N/A'; ?></td>
                                <td><?php echo $announcement['expiry_date'] ? date('M j, Y', strtotime($announcement['expiry_date'])) : 'N/A'; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $announcement['status']; ?>">
                                        <?php echo ucfirst($announcement['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button onclick="viewAnnouncement('<?php echo $announcement['id']; ?>')" class="btn btn-sm btn-secondary" title="View">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="editAnnouncement('<?php echo $announcement['id']; ?>')" class="btn btn-sm btn-secondary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="publishAnnouncement('<?php echo $announcement['id']; ?>')" class="btn btn-sm btn-primary" title="Publish">
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
            <h2>Create Announcement</h2>
            <form id="createForm">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" required>
                </div>
                <div class="form-group">
                    <label>Priority</label>
                    <select name="priority" required>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Institution (Optional)</label>
                    <select name="institution_id">
                        <option value="">All Institutions</option>
                        <?php 
                        $institutions = $db->fetchAll("SELECT id, name FROM institutions ORDER BY name");
                        foreach ($institutions as $inst): ?>
                            <option value="<?php echo $inst['id']; ?>"><?php echo htmlspecialchars($inst['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Publish Date</label>
                    <input type="date" name="publish_date" required>
                </div>
                <div class="form-group">
                    <label>Expiry Date (Optional)</label>
                    <input type="date" name="expiry_date">
                </div>
                <div class="form-group">
                    <label>Content</label>
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
            const priority = document.getElementById('priorityFilter').value;
            
            const params = new URLSearchParams();
            if (status) params.set('status', status);
            if (priority) params.set('priority', priority);
            
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
            
            fetch('<?php echo BASE_URL; ?>/api/create-announcement.php', {
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

        function viewAnnouncement(id) {
            window.open('<?php echo BASE_URL; ?>/api/view-announcement.php?id=' + id, '_blank');
        }

        function editAnnouncement(id) {
            window.location.href = 'edit-announcement.php?id=' + id;
        }

        function publishAnnouncement(id) {
            if (confirm('Publish this announcement?')) {
                fetch('<?php echo BASE_URL; ?>/api/publish-announcement.php', {
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
