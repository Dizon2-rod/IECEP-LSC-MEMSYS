<?php
require_once __DIR__ . '/../auth_check.php';
require_role(['admin', 'super_admin', 'eb_secretary']);

require_once __DIR__ . '/../../includes/db.php';

$db = Database::getInstance();

// Get filters
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where = ['1=1'];
$params = [];

if (!empty($category)) {
    $where[] = "category = ?";
    $params[] = $category;
}

if (!empty($search)) {
    $where[] = "(title LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = implode(' AND ', $where);

// Get documents
$documents = $db->fetchAll("SELECT d.*, i.name as institution_name, up.full_name as uploaded_by_name
    FROM documents d
    LEFT JOIN institutions i ON d.institution_id = i.id
    LEFT JOIN user_profiles up ON d.uploaded_by = up.user_id
    WHERE $whereClause
    ORDER BY d.created_at DESC
    LIMIT 100", $params);

// Get categories
$categories = $db->fetchAll("SELECT DISTINCT category FROM documents WHERE category IS NOT NULL ORDER BY category");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Repository - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/professional.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/font-awesome.css">
    <style>
        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        .filter-group input, .filter-group select {
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
        .category-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-medium);
            background: var(--gray-100);
            color: var(--gray-700);
        }
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
            min-height: 80px;
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
                    <h1>Document Repository</h1>
                    <p class="text-gray">Manage and organize chapter documents</p>
                </div>
                <button onclick="openUploadModal()" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Upload Document
                </button>
            </div>

            <div class="filters">
                <div class="filter-group">
                    <label>Category</label>
                    <select id="categoryFilter">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" id="searchInput" placeholder="Search documents..." value="<?php echo htmlspecialchars($search); ?>">
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
                            <th>Category</th>
                            <th>Institution</th>
                            <th>Uploaded By</th>
                            <th>Version</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($documents)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2rem;">No documents found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($doc['title']); ?></strong>
                                    <?php if (!empty($doc['description'])): ?>
                                        <br><small class="text-gray"><?php echo htmlspecialchars(substr($doc['description'], 0, 50)); ?>...</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="category-badge"><?php echo htmlspecialchars($doc['category'] ?? 'Uncategorized'); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($doc['institution_name'] ?? 'General'); ?></td>
                                <td><?php echo htmlspecialchars($doc['uploaded_by_name'] ?? 'System'); ?></td>
                                <td>v<?php echo $doc['version']; ?></td>
                                <td><?php echo date('M j, Y', strtotime($doc['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button onclick="downloadDocument('<?php echo $doc['id']; ?>')" class="btn btn-sm btn-secondary" title="Download">
                                            <i class="fas fa-download"></i>
                                        </button>
                                        <button onclick="viewDocument('<?php echo $doc['id']; ?>')" class="btn btn-sm btn-secondary" title="View">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="deleteDocument('<?php echo $doc['id']; ?>')" class="btn btn-sm btn-danger" title="Delete">
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

    <!-- Upload Modal -->
    <div class="modal" id="uploadModal">
        <div class="modal-content">
            <h2>Upload Document</h2>
            <form id="uploadForm">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" required>
                        <option value="">Select Category</option>
                        <option value="Constitution">Constitution</option>
                        <option value="Bylaws">Bylaws</option>
                        <option value="Minutes">Minutes</option>
                        <option value="Reports">Reports</option>
                        <option value="Forms">Forms</option>
                        <option value="Policies">Policies</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Institution (Optional)</label>
                    <select name="institution_id">
                        <option value="">General (All)</option>
                        <?php 
                        $institutions = $db->fetchAll("SELECT id, name FROM institutions ORDER BY name");
                        foreach ($institutions as $inst): ?>
                            <option value="<?php echo $inst['id']; ?>"><?php echo htmlspecialchars($inst['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description"></textarea>
                </div>
                <div class="form-group">
                    <label>File</label>
                    <input type="file" name="file" required>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="closeUploadModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function applyFilters() {
            const category = document.getElementById('categoryFilter').value;
            const search = document.getElementById('searchInput').value;
            
            const params = new URLSearchParams();
            if (category) params.set('category', category);
            if (search) params.set('search', search);
            
            window.location.href = '?' + params.toString();
        }

        function openUploadModal() {
            document.getElementById('uploadModal').classList.add('active');
        }

        function closeUploadModal() {
            document.getElementById('uploadModal').classList.remove('active');
        }

        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('<?php echo BASE_URL; ?>/api/upload-document.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    closeUploadModal();
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            });
        });

        function downloadDocument(id) {
            window.location.href = '<?php echo BASE_URL; ?>/api/download-document.php?id=' + id;
        }

        function viewDocument(id) {
            window.open('<?php echo BASE_URL; ?>/api/view-document.php?id=' + id, '_blank');
        }

        function deleteDocument(id) {
            if (confirm('Are you sure you want to delete this document?')) {
                fetch('<?php echo BASE_URL; ?>/api/delete-document.php', {
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
