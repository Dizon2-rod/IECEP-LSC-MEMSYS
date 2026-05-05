<?php
session_start();
require_once __DIR__ . '/../../../autoload.php';
require_once __DIR__ . '/../../../includes/supabase.php';
require_once __DIR__ . '/../../../includes/paths.php';

// Check authentication and role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'creatives' && $_SESSION['role'] !== 'super_admin')) {
    header('Location: ' . PORTAL_URL . '/dashboard.php?error=unauthorized');
    exit;
}

require_once __DIR__ . '/../../../src/lib/SupabaseClient.php';
$config = require __DIR__ . '/../../../includes/supabase.php';
$supabaseClient = new SupabaseClient($config['url'], $config['anon_key']);

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $awardYear = !empty($_POST['award_year']) ? (int)$_POST['award_year'] : null;
        $awardId = isset($_POST['award_id']) ? (int)$_POST['award_id'] : null;
        
        if (empty($title)) {
            $message = 'Title is required.';
            $messageType = 'error';
        } else {
            $imageUrl = null;
            
            // Handle image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../../public/assets/uploads/awards/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $filename = uniqid('award_') . '.' . $extension;
                $filepath = $uploadDir . $filename;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
                    $imageUrl = '/assets/uploads/awards/' . $filename;
                } else {
                    $message = 'Failed to upload image.';
                    $messageType = 'error';
                }
            } elseif ($action === 'edit' && isset($_POST['keep_image']) && $_POST['keep_image'] === '1') {
                $imageUrl = $_POST['existing_image'] ?? null;
            }
            
            if ($messageType !== 'error') {
                try {
                    $data = [
                        'title' => $title,
                        'description' => $description,
                        'award_year' => $awardYear
                    ];
                    
                    if ($imageUrl) {
                        $data['image_url'] = $imageUrl;
                    }
                    
                    if ($action === 'add') {
                        $supabaseClient->insert('awards_distinctions', $data);
                        $message = 'Award added successfully!';
                        $messageType = 'success';
                    } else {
                        $supabaseClient->update('awards_distinctions', $data, ['id' => $awardId]);
                        $message = 'Award updated successfully!';
                        $messageType = 'success';
                    }
                } catch (Exception $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $messageType = 'error';
                }
            }
        }
    } elseif ($action === 'delete') {
        $awardId = isset($_POST['award_id']) ? (int)$_POST['award_id'] : null;
        
        if ($awardId) {
            try {
                // Get award to delete image if exists
                $awards = $supabaseClient->select('awards_distinctions', null, null, null, ['id' => $awardId]);
                if (!empty($awards) && !empty($awards[0]['image_url'])) {
                    $imageUrl = $awards[0]['image_url'];
                    if (strpos($imageUrl, 'http') !== 0) {
                        $fullPath = __DIR__ . '/../../../public' . $imageUrl;
                        if (file_exists($fullPath)) {
                            unlink($fullPath);
                        }
                    }
                }
                
                $supabaseClient->delete('awards_distinctions', ['id' => $awardId]);
                $message = 'Award deleted successfully!';
                $messageType = 'success';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Fetch all awards
try {
    $awards = $supabaseClient->select('awards_distinctions', null, null, ['award_year' => 'desc']);
} catch (Exception $e) {
    $awards = [];
    $message = 'Error loading awards: ' . $e->getMessage();
    $messageType = 'error';
}

// Get award for editing
$editAward = null;
if (isset($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    try {
        $editAwards = $supabaseClient->select('awards_distinctions', null, null, null, ['id' => $editId]);
        if (!empty($editAwards)) {
            $editAward = $editAwards[0];
        }
    } catch (Exception $e) {
        // Ignore error
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Awards - IECEP-LSC MEMSYS</title>
    <?php include __DIR__ . '/../../../includes/head-meta.php'; ?>
    <style>
        .dashboard-layout {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 280px;
            background: var(--primary);
            color: var(--white);
            padding: var(--space-6) var(--space-4);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar-brand {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: var(--space-8);
            padding-bottom: var(--space-4);
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-nav {
            list-style: none;
        }
        
        .sidebar-nav li {
            margin-bottom: var(--space-2);
        }
        
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            padding: var(--space-3);
            border-radius: var(--radius-md);
            transition: all var(--transition-base);
        }
        
        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: rgba(255,255,255,0.1);
            color: var(--white);
        }
        
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: var(--space-8);
            background: var(--neutral-100);
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-6);
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-3) var(--space-6);
            font-size: 1rem;
            font-weight: 600;
            border-radius: var(--radius-md);
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: all var(--transition-base);
        }
        
        .btn-primary {
            background: var(--accent);
            color: var(--primary);
        }
        
        .btn-primary:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: var(--white);
            color: var(--neutral-700);
            border: 2px solid var(--neutral-300);
        }
        
        .btn-secondary:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-danger {
            background: #DC2626;
            color: var(--white);
        }
        
        .btn-danger:hover {
            background: #B91C1C;
        }
        
        .btn-sm {
            padding: var(--space-2) var(--space-4);
            font-size: 0.875rem;
        }
        
        .card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .alert {
            padding: var(--space-4);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-6);
        }
        
        .alert-success {
            background: #D4EDDA;
            border: 1px solid #C3E6CB;
            color: #155724;
        }
        
        .alert-error {
            background: #F8D7DA;
            border: 1px solid #F5C6CB;
            color: #721C24;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: var(--space-4);
            text-align: left;
            border-bottom: 1px solid var(--neutral-200);
        }
        
        .table th {
            background: var(--neutral-100);
            font-weight: 600;
            color: var(--neutral-700);
        }
        
        .table img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: var(--radius-md);
        }
        
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: var(--space-4);
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: var(--white);
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: var(--radius-xl);
            padding: var(--space-6);
            position: relative;
        }
        
        .modal-close {
            position: absolute;
            top: var(--space-4);
            right: var(--space-4);
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--neutral-500);
            cursor: pointer;
        }
        
        .form-group {
            margin-bottom: var(--space-4);
        }
        
        .form-label {
            display: block;
            margin-bottom: var(--space-2);
            font-weight: 600;
            color: var(--neutral-700);
        }
        
        .form-input,
        .form-textarea {
            width: 100%;
            padding: var(--space-3);
            border: 2px solid var(--neutral-300);
            border-radius: var(--radius-md);
            font-family: inherit;
            transition: border-color var(--transition-base);
        }
        
        .form-input:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .image-preview {
            width: 100%;
            max-width: 200px;
            height: 150px;
            object-fit: cover;
            border-radius: var(--radius-md);
            margin-top: var(--space-2);
            display: none;
        }
        
        .image-preview.visible {
            display: block;
        }
        
        .empty-state {
            text-align: center;
            padding: var(--space-12);
            color: var(--neutral-500);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: var(--space-4);
            opacity: 0.3;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                Creatives Portal
            </div>
            <ul class="sidebar-nav">
                <li><a href="<?php echo PORTAL_URL; ?>/dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="<?php echo PORTAL_URL; ?>/creatives/manage-calendar.php"><i class="fas fa-calendar"></i> Calendar</a></li>
                <li><a href="<?php echo PORTAL_URL; ?>/creatives/affiliated-schools-management.php"><i class="fas fa-university"></i> Schools</a></li>
                <li><a href="<?php echo PORTAL_URL; ?>/creatives/manage-awards.php" class="active"><i class="fas fa-trophy"></i> Awards</a></li>
                <li><a href="<?php echo BASE_URL; ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">Manage Awards & Distinctions</h1>
                <button class="btn btn-primary" onclick="openModal()">
                    <i class="fas fa-plus"></i> Add New Award
                </button>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <?php if (!empty($awards)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Title</th>
                                <th>Year</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($awards as $award): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($award['image_url'])): ?>
                                            <?php 
                                            $imgSrc = strpos($award['image_url'], 'http') === 0 
                                                ? $award['image_url'] 
                                                : BASE_URL . '/' . ltrim($award['image_url'], '/');
                                            ?>
                                            <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo htmlspecialchars($award['title']); ?>">
                                        <?php else: ?>
                                            <div style="width: 60px; height: 60px; background: var(--neutral-200); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center;">
                                                <i class="fas fa-trophy" style="color: var(--neutral-400);"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($award['title']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo $award['award_year'] ? htmlspecialchars($award['award_year']) : '-'; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $desc = $award['description'] ?? '';
                                        echo htmlspecialchars(mb_substr($desc, 0, 100)) . (mb_strlen($desc) > 100 ? '...' : '');
                                        ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-secondary btn-sm" onclick="editAward(<?php echo $award['id']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-danger btn-sm" onclick="confirmDelete(<?php echo $award['id']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-trophy"></i>
                        <h3>No Awards Yet</h3>
                        <p>Click "Add New Award" to create your first award.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Add/Edit Modal -->
    <div class="modal" id="awardModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal()">&times;</button>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--primary); margin-bottom: var(--space-6); text-align: center;">
                <?php echo $editAward ? 'Edit Award' : 'Add New Award'; ?>
            </h2>
            
            <form method="POST" enctype="multipart/form-data" id="awardForm">
                <input type="hidden" name="action" value="<?php echo $editAward ? 'edit' : 'add'; ?>">
                <input type="hidden" name="award_id" value="<?php echo $editAward ? $editAward['id'] : ''; ?>">
                <input type="hidden" name="keep_image" value="<?php echo $editAward && !empty($editAward['image_url']) ? '1' : '0'; ?>">
                <input type="hidden" name="existing_image" value="<?php echo $editAward['image_url'] ?? ''; ?>">
                
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" class="form-input" name="title" required value="<?php echo $editAward ? htmlspecialchars($editAward['title']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Year</label>
                    <input type="number" class="form-input" name="award_year" value="<?php echo $editAward ? htmlspecialchars($editAward['award_year'] ?? '') : ''; ?>" placeholder="e.g., 2024">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea class="form-textarea" name="description" placeholder="Describe the award..."><?php echo $editAward ? htmlspecialchars($editAward['description'] ?? '') : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Image</label>
                    <input type="file" class="form-input" name="image" accept="image/*" onchange="previewImage(this)">
                    <?php if ($editAward && !empty($editAward['image_url'])): ?>
                        <?php 
                        $existingImgSrc = strpos($editAward['image_url'], 'http') === 0 
                            ? $editAward['image_url'] 
                            : BASE_URL . '/' . ltrim($editAward['image_url'], '/');
                        ?>
                        <img src="<?php echo htmlspecialchars($existingImgSrc); ?>" alt="Current image" class="image-preview visible" id="previewImage">
                        <small style="color: var(--neutral-500); display: block; margin-top: var(--space-2);">
                            Leave empty to keep current image
                        </small>
                    <?php else: ?>
                        <img src="" alt="Preview" class="image-preview" id="previewImage">
                    <?php endif; ?>
                </div>
                
                <div style="display: flex; gap: var(--space-3); margin-top: var(--space-6);">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-save"></i> <?php echo $editAward ? 'Update' : 'Add'; ?> Award
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content" style="max-width: 400px;">
            <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--primary); margin-bottom: var(--space-4); text-align: center;">
                Confirm Delete
            </h2>
            <p style="text-align: center; color: var(--neutral-500); margin-bottom: var(--space-6);">
                Are you sure you want to delete this award? This action cannot be undone.
            </p>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="award_id" id="deleteAwardId">
                <div style="display: flex; gap: var(--space-3);">
                    <button type="submit" class="btn btn-danger" style="flex: 1;">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openModal() {
            document.getElementById('awardModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('awardModal').classList.remove('active');
            document.getElementById('awardForm').reset();
            document.getElementById('previewImage').classList.remove('visible');
        }
        
        function editAward(id) {
            window.location.href = '?edit_id=' + id;
        }
        
        function confirmDelete(id) {
            document.getElementById('deleteAwardId').value = id;
            document.getElementById('deleteModal').classList.add('active');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
        
        function previewImage(input) {
            const preview = document.getElementById('previewImage');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.add('visible');
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.classList.remove('visible');
            }
        }
        
        // Close modal on outside click
        document.getElementById('awardModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>
