<?php
$current_page = basename(__FILE__, '.php');
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/role-config.php';

// Check if user is logged in and has admin permissions
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['eb_admin', 'eb_president', 'eb_vp_internal'])) {
    header('Location: ' . PORTAL_URL . '/login.php');
    exit;
}

$pageTitle = 'User Management';
include '../../includes/dashboard-layout.php';

// Get users with pagination and filtering
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$roleFilter = $_GET['role'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$searchFilter = $_GET['search'] ?? '';

try {
    $query = $supabaseClient->from('user_profiles')
        ->select('*, auth.users(email)', ['count' => 'exact']);

    // Apply filters
    if ($roleFilter !== 'all') {
        $query->eq('role', $roleFilter);
    }

    if ($statusFilter !== 'all') {
        $query->eq('verification_status', $statusFilter);
    }

    if (!empty($searchFilter)) {
        $query->or("name.ilike.%{$searchFilter}%,email.ilike.%{$searchFilter}%");
    }

    $users = $query->order('created_at', ['ascending' => false])
        ->range($offset, $offset + $perPage - 1)
        ->execute();

    $totalUsers = $users['count'] ?? 0;
    $totalPages = ceil($totalUsers / $perPage);

    // Get user statistics
    $stats = $supabaseClient->from('user_profiles')
        ->select('role, verification_status')
        ->execute();

    $userStats = [
        'total' => count($stats),
        'by_role' => [],
        'by_status' => []
    ];

    foreach ($stats as $user) {
        $role = $user['role'] ?? 'unknown';
        $status = $user['verification_status'] ?? 'pending';

        if (!isset($userStats['by_role'][$role])) {
            $userStats['by_role'][$role] = 0;
        }
        $userStats['by_role'][$role]++;

        if (!isset($userStats['by_status'][$status])) {
            $userStats['by_status'][$status] = 0;
        }
        $userStats['by_status'][$status]++;
    }

} catch (Exception $e) {
    $users = [];
    $userStats = ['total' => 0, 'by_role' => [], 'by_status' => []];
    $totalUsers = 0;
    $totalPages = 0;
    error_log('Error fetching users: ' . $e->getMessage());
}
?>

<div class="dashboard-container">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">User Management</h1>
                    <p class="text-muted">Manage system users and their permissions</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="exportUsers()">
                        <i class="fas fa-download me-2"></i>Export Users
                    </button>
                    <button class="btn btn-primary" onclick="showAddUserModal()">
                        <i class="fas fa-plus me-2"></i>Add User
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h3 class="text-primary"><?= $userStats['total'] ?></h3>
                            <p class="text-muted mb-0">Total Users</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h3 class="text-success"><?= $userStats['by_status']['verified'] ?? 0 ?></h3>
                            <p class="text-muted mb-0">Verified Users</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h3 class="text-warning"><?= $userStats['by_status']['pending'] ?? 0 ?></h3>
                            <p class="text-muted mb-0">Pending Verification</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <h3 class="text-info"><?= $userStats['by_role']['member'] ?? 0 ?></h3>
                            <p class="text-muted mb-0">Active Members</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" id="roleFilter" onchange="applyFilters()">
                                <option value="all">All Roles</option>
                                <option value="eb_admin">Administrator</option>
                                <option value="eb_president">President</option>
                                <option value="eb_vp_internal">VP Internal</option>
                                <option value="eb_treasurer">Treasurer</option>
                                <option value="eb_secretary">Secretary</option>
                                <option value="member">Member</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="statusFilter" onchange="applyFilters()">
                                <option value="all">All Status</option>
                                <option value="verified">Verified</option>
                                <option value="pending">Pending</option>
                                <option value="revoked">Revoked</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" id="searchFilter" placeholder="Search by name or email..." onkeyup="applyFilters()">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button class="btn btn-outline-secondary w-100" onclick="clearFilters()">
                                <i class="fas fa-times me-1"></i>Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Institution</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No users found</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle me-3">
                                                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?= htmlspecialchars($user['name']) ?></div>
                                                        <small class="text-muted"><?= htmlspecialchars($user['email'] ?? 'N/A') ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= getRoleColor($user['role']) ?>">
                                                    <?= ucfirst(str_replace('eb_', '', $user['role'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= getStatusColor($user['verification_status']) ?>">
                                                    <?= ucfirst($user['verification_status'] ?? 'pending') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?= htmlspecialchars($user['institution'] ?? 'N/A') ?></small>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= date('M d, Y', strtotime($user['created_at'])) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewUser('<?= $user['id'] ?>')" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-secondary" onclick="editUser('<?= $user['id'] ?>')" title="Edit User">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if (($user['verification_status'] ?? 'pending') === 'verified'): ?>
                                                        <button class="btn btn-sm btn-outline-warning" onclick="revokeUser('<?= $user['id'] ?>')" title="Revoke Access">
                                                            <i class="fas fa-ban"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-success" onclick="verifyUser('<?= $user['id'] ?>')" title="Verify User">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($user['role'] !== 'eb_admin'): ?>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteUser('<?= $user['id'] ?>')" title="Delete User">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Users pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>" <?= $page <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Previous</a>
                                </li>

                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>

                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>" <?= $page >= $totalPages ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Next</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add/Edit User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalTitle">Add User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="userForm">
                <div class="modal-body">
                    <input type="hidden" id="userId" name="userId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="userName" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" id="userEmail" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role *</label>
                            <select class="form-select" id="userRole" name="role" required>
                                <option value="member">Member</option>
                                <option value="eb_secretary">Secretary</option>
                                <option value="eb_treasurer">Treasurer</option>
                                <option value="eb_vp_internal">VP Internal</option>
                                <option value="eb_president">President</option>
                                <option value="eb_admin">Administrator</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Institution</label>
                            <input type="text" class="form-control" id="userInstitution" name="institution">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" id="userAddress" name="address" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="userPhone" name="phone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Verification Status</label>
                            <select class="form-select" id="userStatus" name="verification_status">
                                <option value="pending">Pending</option>
                                <option value="verified">Verified</option>
                                <option value="revoked">Revoked</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Set initial filter values
    document.getElementById('roleFilter').value = '<?= $roleFilter ?>';
    document.getElementById('statusFilter').value = '<?= $statusFilter ?>';
    document.getElementById('searchFilter').value = '<?= htmlspecialchars($searchFilter) ?>';

    // Handle form submission
    document.getElementById('userForm').addEventListener('submit', handleUserSubmit);
});

// Apply filters
function applyFilters() {
    const role = document.getElementById('roleFilter').value;
    const status = document.getElementById('statusFilter').value;
    const search = document.getElementById('searchFilter').value;

    const params = new URLSearchParams();
    if (role !== 'all') params.set('role', role);
    if (status !== 'all') params.set('status', status);
    if (search) params.set('search', search);

    window.location.search = params.toString();
}

// Clear filters
function clearFilters() {
    window.location.href = window.location.pathname;
}

// Show add user modal
function showAddUserModal() {
    document.getElementById('userModalTitle').textContent = 'Add User';
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = '';

    new bootstrap.Modal(document.getElementById('userModal')).show();
}

// Edit user
function editUser(userId) {
    fetch(`../../api/users.php?action=get_user&id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const user = data.user;
                document.getElementById('userModalTitle').textContent = 'Edit User';
                document.getElementById('userId').value = user.id;
                document.getElementById('userName').value = user.name;
                document.getElementById('userEmail').value = user.email;
                document.getElementById('userRole').value = user.role;
                document.getElementById('userInstitution').value = user.institution || '';
                document.getElementById('userAddress').value = user.address || '';
                document.getElementById('userPhone').value = user.phone || '';
                document.getElementById('userStatus').value = user.verification_status || 'pending';

                new bootstrap.Modal(document.getElementById('userModal')).show();
            } else {
                showToast('Error loading user data', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error loading user data', 'error');
        });
}

// Handle user form submission
function handleUserSubmit(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const userData = Object.fromEntries(formData.entries());
    const isEdit = userData.userId !== '';

    const url = '../../api/users.php?action=' + (isEdit ? 'update_user&id=' + userData.userId : 'create_user');
    const method = isEdit ? 'PUT' : 'POST';

    fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(userData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('userModal')).hide();
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Error saving user', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error saving user', 'error');
    });
}

// View user details
function viewUser(userId) {
    window.open(`user-details.php?id=${userId}`, '_blank');
}

// Verify user
function verifyUser(userId) {
    if (confirm('Are you sure you want to verify this user?')) {
        fetch(`../../api/users.php?action=verify_user&id=${userId}`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('User verified successfully', 'success');
                location.reload();
            } else {
                showToast('Error verifying user', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error verifying user', 'error');
        });
    }
}

// Revoke user
function revokeUser(userId) {
    const reason = prompt('Enter reason for revocation:');
    if (reason !== null) {
        fetch(`../../api/users.php?action=revoke_user&id=${userId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ reason: reason })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('User access revoked', 'success');
                location.reload();
            } else {
                showToast('Error revoking user access', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error revoking user access', 'error');
        });
    }
}

// Delete user
function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        fetch(`../../api/users.php?action=delete_user&id=${userId}`, {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('User deleted successfully', 'success');
                location.reload();
            } else {
                showToast('Error deleting user', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error deleting user', 'error');
        });
    }
}

// Export users
function exportUsers() {
    window.open('../../api/users.php?action=export_users', '_blank');
}

// Helper functions
function getRoleColor(role) {
    const colors = {
        'eb_admin': 'danger',
        'eb_president': 'primary',
        'eb_vp_internal': 'info',
        'eb_treasurer': 'success',
        'eb_secretary': 'warning',
        'member': 'secondary'
    };
    return colors[role] || 'secondary';
}

function getStatusColor(status) {
    const colors = {
        'verified': 'success',
        'pending': 'warning',
        'revoked': 'danger'
    };
    return colors[status] || 'secondary';
}

// Toast notification helper
function showToast(message, type) {
    // Assuming toast.js is available
    if (typeof showToast === 'function') {
        showToast(message, type);
    } else {
        alert(message);
    }
}
</script>

<?php include '../../includes/footer.php'; ?>

<?php
function getRoleColor($role) {
    $colors = [
        'eb_admin' => 'danger',
        'eb_president' => 'primary',
        'eb_vp_internal' => 'info',
        'eb_treasurer' => 'success',
        'eb_secretary' => 'warning',
        'member' => 'secondary'
    ];
    return $colors[$role] ?? 'secondary';
}

function getStatusColor($status) {
    $colors = [
        'verified' => 'success',
        'pending' => 'warning',
        'revoked' => 'danger'
    ];
    return $colors[$status] ?? 'secondary';
}
?>
