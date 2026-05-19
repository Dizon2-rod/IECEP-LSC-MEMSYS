<?php
require_once __DIR__ . '/../bootstrap.php';
$current_page = basename(__FILE__, '.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../../includes/paths.php';
require_once __DIR__ . '/../../../autoload.php';
require_once __DIR__ . '/../../../includes/supabase.php';

// Check if user is logged in and is a creative
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

if ($_SESSION['role'] !== 'eb_pro_1' && $_SESSION['role'] !== 'eb_president') {
    header('Location: ' . PORTAL_URL . '/dashboard.php');
    exit;
}

$supabase = new \App\Lib\Supabase();
$message = '';
$messageType = '';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $data = [
                        'name' => $_POST['name'],
                        'facebook_url' => $_POST['facebook_url'],
                        'member_count' => (int)$_POST['member_count'],
                        'status' => 'active',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    $supabase->from('affiliated_schools')->insert($data);
                    $message = 'School added successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'edit':
                    $id = $_POST['id'];
                    $data = [
                        'name' => $_POST['name'],
                        'facebook_url' => $_POST['facebook_url'],
                        'member_count' => (int)$_POST['member_count'],
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    $supabase->from('affiliated_schools')->update($data)->eq('id', $id);
                    $message = 'School updated successfully!';
                    $messageType = 'success';
                    break;
                    
                case 'delete':
                    $id = $_POST['id'];
                    $supabase->from('affiliated_schools')->delete()->eq('id', $id);
                    $message = 'School deleted successfully!';
                    $messageType = 'success';
                    break;
            }
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Fetch all schools
$schools = [];
try {
    $result = $supabase->from('affiliated_schools')
        ->select('*')
        ->order('name', true)
        ->get(true);
    $schools = $result['data'] ?? [];
} catch (Exception $e) {
    $schools = [];
}

// Load schools mapping for logo paths
$schoolsMapping = require __DIR__ . '/../../../includes/data/schools_mapping.php';
$schoolLogos = [];
foreach ($schoolsMapping as $name => $path) {
    $schoolLogos[$name] = ASSETS_URL . '/icons/' . basename($path);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Affiliated Schools - IECEP-LSC</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0B1D4A;
            --primary-light: #1E3A6E;
            --accent: #D4AF37;
            --accent-hover: #C4A030;
            --white: #FFFFFF;
            --neutral-100: #F5F5F5;
            --neutral-200: #E5E5E5;
            --neutral-500: #6B7280;
            --neutral-700: #374151;
            --neutral-900: #111827;
            --success: #22C55E;
            --error: #DC2626;
            --space-2: 8px;
            --space-3: 12px;
            --space-4: 16px;
            --space-6: 24px;
            --space-8: 32px;
            --space-12: 48px;
            --radius-md: 8px;
            --radius-lg: 12px;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, sans-serif; background: var(--neutral-100); color: var(--neutral-900); }
        
        /* Header */
        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: var(--white);
            padding: var(--space-8) var(--space-4);
            margin-bottom: var(--space-6);
        }
        .page-header h1 { font-size: 2rem; font-weight: 700; margin-bottom: var(--space-2); }
        .page-header p { opacity: 0.9; }
        
        /* Container */
        .container { max-width: 1200px; margin: 0 auto; padding: 0 var(--space-4); }
        
        /* Message Box */
        .message {
            padding: var(--space-4);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-6);
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }
        .message.success { background: #F0FDF4; color: var(--success); border: 1px solid #BBF7D0; }
        .message.error { background: #FEF2F2; color: var(--error); border: 1px solid #FECACA; }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-4);
            margin-bottom: var(--space-6);
        }
        .stat-card {
            background: var(--white);
            padding: var(--space-6);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--neutral-200);
        }
        .stat-value { font-size: 2rem; font-weight: 700; color: var(--primary); margin-bottom: var(--space-2); }
        .stat-label { color: var(--neutral-500); font-size: 0.9rem; }
        
        /* Table */
        .table-container {
            background: var(--white);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--neutral-200);
        }
        table { width: 100%; border-collapse: collapse; }
        th { background: var(--neutral-100); padding: var(--space-4); text-align: left; font-weight: 600; color: var(--neutral-700); border-bottom: 1px solid var(--neutral-200); }
        td { padding: var(--space-4); border-bottom: 1px solid var(--neutral-200); }
        tr:hover { background: var(--neutral-100); }
        
        /* School Logo */
        .school-logo-cell { display: flex; align-items: center; gap: var(--space-3); }
        .school-logo { width: 40px; height: 40px; border-radius: var(--radius-md); object-fit: contain; }
        
        /* Actions */
        .actions { display: flex; gap: var(--space-2); }
        .btn {
            padding: var(--space-2) var(--space-4);
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
        }
        .btn-primary { background: var(--primary); color: var(--white); }
        .btn-primary:hover { background: var(--primary-light); }
        .btn-edit { background: #3B82F6; color: var(--white); }
        .btn-edit:hover { background: #2563EB; }
        .btn-delete { background: #EF4444; color: var(--white); }
        .btn-delete:hover { background: #DC2626; }
        .btn-add { background: var(--success); color: var(--white); margin-bottom: var(--space-6); }
        .btn-add:hover { background: #16A34A; }
        
        /* Modal */
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: var(--space-8);
            max-width: 500px;
            width: 90%;
            max-height: 90vh; overflow-y: auto;
        }
        .modal-header { margin-bottom: var(--space-6); }
        .modal-title { font-size: 1.5rem; font-weight: 700; color: var(--primary); }
        .form-group { margin-bottom: var(--space-4); }
        .form-label { display: block; margin-bottom: var(--space-2); font-weight: 600; color: var(--neutral-700); }
        .form-input { width: 100%; padding: var(--space-3); border: 1px solid var(--neutral-200); border-radius: var(--radius-md); font-size: 1rem; }
        .form-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(11,29,74,0.1); }
        .modal-footer { display: flex; gap: var(--space-3); justify-content: flex-end; margin-top: var(--space-6); }
        .btn-cancel { background: var(--neutral-200); color: var(--neutral-700); }
        .btn-cancel:hover { background: var(--neutral-300); }
        
        /* Empty State */
        .empty-state { text-align: center; padding: var(--space-12) var(--space-4); }
        .empty-state i { font-size: 4rem; color: var(--neutral-200); margin-bottom: var(--space-4); }
        .empty-state h3 { font-size: 1.5rem; color: var(--neutral-700); margin-bottom: var(--space-2); }
        .empty-state p { color: var(--neutral-500); }
    </style>
</head>
<body>
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1>Manage Affiliated Schools</h1>
            <p>Add, edit, and delete affiliated schools information</p>
        </div>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo count($schools); ?></div>
                <div class="stat-label">Total Schools</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo array_sum(array_column($schools, 'member_count')); ?></div>
                <div class="stat-label">Total Members</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count(array_filter($schools, fn($s) => !empty($s['facebook_url']))); ?></div>
                <div class="stat-label">With Facebook Links</div>
            </div>
        </div>
        
        <!-- Add Button -->
        <button class="btn btn-add" onclick="openModal()">
            <i class="fas fa-plus"></i> Add New School
        </button>
        
        <!-- Schools Table -->
        <div class="table-container">
            <?php if (!empty($schools)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>School</th>
                            <th>Members</th>
                            <th>Facebook</th>
                            <th>Member Since</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schools as $school): ?>
                            <tr>
                                <td>
                                    <div class="school-logo-cell">
                                        <?php 
                                        $logoPath = isset($schoolLogos[$school['name']]) ? $schoolLogos[$school['name']] : ASSETS_URL . '/icons/iecep-logo.png';
                                        ?>
                                        <img src="<?php echo $logoPath; ?>" alt="<?php echo htmlspecialchars($school['name']); ?>" class="school-logo">
                                        <span><?php echo htmlspecialchars($school['name']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo number_format($school['member_count']); ?></td>
                                <td>
                                    <?php if (!empty($school['facebook_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($school['facebook_url']); ?>" target="_blank" class="btn btn-edit" style="font-size: 0.8rem; padding: 4px 8px;">
                                            <i class="fab fa-facebook"></i> Visit
                                        </a>
                                    <?php else: ?>
                                        <span style="color: var(--neutral-500);">No link</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('Y', strtotime($school['created_at'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <button class="btn btn-edit" onclick="editSchool(<?php echo $school['id']; ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-delete" onclick="deleteSchool(<?php echo $school['id']; ?>)">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-school"></i>
                    <h3>No Schools Found</h3>
                    <p>Start by adding your first affiliated school</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal -->
    <div id="schoolModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Add New School</h2>
            </div>
            <form id="schoolForm" method="POST">
                <input type="hidden" id="schoolId" name="id">
                <input type="hidden" id="formAction" name="action" value="add">
                
                <div class="form-group">
                    <label class="form-label" for="name">School Name *</label>
                    <input type="text" id="name" name="name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="facebook_url">Facebook URL</label>
                    <input type="url" id="facebook_url" name="facebook_url" class="form-input" placeholder="https://www.facebook.com/school-page">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="member_count">Member Count *</label>
                    <input type="number" id="member_count" name="member_count" class="form-input" min="0" required>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Modal functions
        function openModal() {
            document.getElementById('schoolModal').classList.add('active');
            document.getElementById('modalTitle').textContent = 'Add New School';
            document.getElementById('formAction').value = 'add';
            document.getElementById('schoolForm').reset();
        }
        
        function closeModal() {
            document.getElementById('schoolModal').classList.remove('active');
        }
        
        function editSchool(id) {
            // Find school data
            const schools = <?php echo json_encode($schools); ?>;
            const school = schools.find(s => s.id === id);
            
            if (school) {
                document.getElementById('schoolModal').classList.add('active');
                document.getElementById('modalTitle').textContent = 'Edit School';
                document.getElementById('formAction').value = 'edit';
                document.getElementById('schoolId').value = school.id;
                document.getElementById('name').value = school.name;
                document.getElementById('facebook_url').value = school.facebook_url || '';
                document.getElementById('member_count').value = school.member_count;
            }
        }
        
        function deleteSchool(id) {
            if (confirm('Are you sure you want to delete this school? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Close modal on outside click
        document.getElementById('schoolModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>

