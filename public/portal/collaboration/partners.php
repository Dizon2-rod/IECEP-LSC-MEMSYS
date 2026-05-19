<?php
require_once __DIR__ . '/../bootstrap.php';
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/role-config.php';

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['eb_admin', 'eb_president', 'eb_vp_internal'])) {
    header('Location: ' . PORTAL_URL . '/login.php');
    exit;
}

$pageTitle = 'Partner Chapters Management';
include '../../includes/dashboard-layout.php';

// Get partner chapters
try {
    $partnerChapters = $supabaseClient->from('partner_chapters')
        ->select('*')
        ->order('created_at', ['ascending' => false])
        ->execute();

} catch (Exception $e) {
    $partnerChapters = [];
    error_log('Error fetching partner chapters: ' . $e->getMessage());
}
?>

<div class="dashboard-container">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Partner Chapters Management</h1>
                    <p class="text-muted">Manage partnerships with other IECEP chapters and institutions</p>
                </div>
                <button class="btn btn-primary" onclick="showAddPartnerModal()">
                    <i class="fas fa-plus me-2"></i>Add Partner
                </button>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-handshake fa-2x text-primary mb-2"></i>
                            <h4 class="mb-0"><?= count($partnerChapters) ?></h4>
                            <small class="text-muted">Total Partners</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-university fa-2x text-success mb-2"></i>
                            <h4 class="mb-0"><?= count(array_filter($partnerChapters, fn($p) => $p['status'] === 'active')) ?></h4>
                            <small class="text-muted">Active Partners</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                            <h4 class="mb-0"><?= count(array_filter($partnerChapters, fn($p) => $p['status'] === 'pending')) ?></h4>
                            <small class="text-muted">Pending</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-globe fa-2x text-info mb-2"></i>
                            <h4 class="mb-0"><?= count(array_unique(array_column($partnerChapters, 'region'))) ?></h4>
                            <small class="text-muted">Regions</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Partners Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Partner Chapters</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Chapter Name</th>
                                    <th>Institution</th>
                                    <th>Contact</th>
                                    <th>Region</th>
                                    <th>Status</th>
                                    <th>Partnership Type</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($partnerChapters as $partner): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle me-2">
                                                    <?= strtoupper(substr($partner['name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?= htmlspecialchars($partner['name']) ?></div>
                                                    <small class="text-muted">Chapter ID: <?= htmlspecialchars($partner['chapter_id']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($partner['institution']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($partner['location']) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <div><?= htmlspecialchars($partner['contact_person']) ?></div>
                                                <small class="text-muted">
                                                    <i class="fas fa-envelope me-1"></i><?= htmlspecialchars($partner['contact_email']) ?>
                                                </small>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="fas fa-phone me-1"></i><?= htmlspecialchars($partner['contact_phone']) ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($partner['region']) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= getStatusColor($partner['status']) ?>">
                                                <?= ucfirst($partner['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?= ucfirst(str_replace('_', ' ', $partner['partnership_type'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= date('M d, Y', strtotime($partner['created_at'])) ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewPartner('<?= $partner['id'] ?>')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success" onclick="editPartner('<?= $partner['id'] ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-warning" onclick="sendMessage('<?= $partner['id'] ?>')">
                                                    <i class="fas fa-envelope"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="removePartner('<?= $partner['id'] ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Partnership Statistics -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Partnership Types</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="partnershipTypesChart" width="300" height="200"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Regional Distribution</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="regionalDistributionChart" width="300" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Partner Modal -->
<div class="modal fade" id="addPartnerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Partner Chapter</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addPartnerForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="chapterName" class="form-label">Chapter Name *</label>
                                <input type="text" class="form-control" id="chapterName" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="chapterId" class="form-label">Chapter ID</label>
                                <input type="text" class="form-control" id="chapterId" placeholder="Auto-generated if empty">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="institution" class="form-label">Institution *</label>
                        <input type="text" class="form-control" id="institution" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="location" class="form-label">Location *</label>
                                <input type="text" class="form-control" id="location" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="region" class="form-label">Region *</label>
                                <select class="form-select" id="region" required>
                                    <option value="">Select region...</option>
                                    <option value="NCR">National Capital Region</option>
                                    <option value="Region I">Ilocos Region</option>
                                    <option value="Region II">Cagayan Valley</option>
                                    <option value="Region III">Central Luzon</option>
                                    <option value="Region IV-A">CALABARZON</option>
                                    <option value="Region IV-B">MIMAROPA</option>
                                    <option value="Region V">Bicol Region</option>
                                    <option value="Region VI">Western Visayas</option>
                                    <option value="Region VII">Central Visayas</option>
                                    <option value="Region VIII">Eastern Visayas</option>
                                    <option value="Region IX">Zamboanga Peninsula</option>
                                    <option value="Region X">Northern Mindanao</option>
                                    <option value="Region XI">Davao Region</option>
                                    <option value="Region XII">SOCCSKSARGEN</option>
                                    <option value="Region XIII">Caraga</option>
                                    <option value="BARMM">Bangsamoro</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="contactPerson" class="form-label">Contact Person *</label>
                                <input type="text" class="form-control" id="contactPerson" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="contactEmail" class="form-label">Contact Email *</label>
                                <input type="email" class="form-control" id="contactEmail" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="contactPhone" class="form-label">Contact Phone</label>
                        <input type="tel" class="form-control" id="contactPhone">
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="partnershipType" class="form-label">Partnership Type *</label>
                                <select class="form-select" id="partnershipType" required>
                                    <option value="">Select type...</option>
                                    <option value="strategic_alliance">Strategic Alliance</option>
                                    <option value="academic_exchange">Academic Exchange</option>
                                    <option value="research_collaboration">Research Collaboration</option>
                                    <option value="student_exchange">Student Exchange</option>
                                    <option value="joint_events">Joint Events</option>
                                    <option value="resource_sharing">Resource Sharing</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status">
                                    <option value="pending">Pending</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="notes" class="form-label">Additional Notes</label>
                        <textarea class="form-control" id="notes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitPartner()">Add Partner</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    loadCharts();
});

// Load charts
function loadCharts() {
    // Partnership Types Chart
    const partnershipData = <?= json_encode(array_count_values(array_column($partnerChapters, 'partnership_type'))) ?>;
    const partnershipCtx = document.getElementById('partnershipTypesChart').getContext('2d');
    const partnershipChart = new Chart(partnershipCtx, {
        type: 'pie',
        data: {
            labels: Object.keys(partnershipData).map(type => type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())),
            datasets: [{
                data: Object.values(partnershipData),
                backgroundColor: [
                    '#0B1D4A', '#D4AF37', '#28a745', '#dc3545', '#ffc107', '#6c757d'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Regional Distribution Chart
    const regionData = <?= json_encode(array_count_values(array_column($partnerChapters, 'region'))) ?>;
    const regionCtx = document.getElementById('regionalDistributionChart').getContext('2d');
    const regionChart = new Chart(regionCtx, {
        type: 'bar',
        data: {
            labels: Object.keys(regionData),
            datasets: [{
                label: 'Number of Chapters',
                data: Object.values(regionData),
                backgroundColor: '#0B1D4A'
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// Show add partner modal
function showAddPartnerModal() {
    const modal = new bootstrap.Modal(document.getElementById('addPartnerModal'));
    modal.show();
}

// Submit new partner
function submitPartner() {
    const formData = {
        name: document.getElementById('chapterName').value,
        chapter_id: document.getElementById('chapterId').value,
        institution: document.getElementById('institution').value,
        location: document.getElementById('location').value,
        region: document.getElementById('region').value,
        contact_person: document.getElementById('contactPerson').value,
        contact_email: document.getElementById('contactEmail').value,
        contact_phone: document.getElementById('contactPhone').value,
        partnership_type: document.getElementById('partnershipType').value,
        status: document.getElementById('status').value,
        notes: document.getElementById('notes').value
    };

    fetch('../../api/partners.php?action=add', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Partner added successfully!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('addPartnerModal')).hide();
            document.getElementById('addPartnerForm').reset();
            location.reload();
        } else {
            showToast('Error adding partner: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error adding partner', 'error');
    });
}

// View partner details
function viewPartner(partnerId) {
    window.open(`partner-details.php?id=${partnerId}`, '_blank');
}

// Edit partner
function editPartner(partnerId) {
    showToast('Edit feature coming soon!', 'info');
}

// Send message to partner
function sendMessage(partnerId) {
    showToast('Messaging feature coming soon!', 'info');
}

// Remove partner
function removePartner(partnerId) {
    if (confirm('Are you sure you want to remove this partner? This action cannot be undone.')) {
        fetch(`../../api/partners.php?action=remove&id=${partnerId}`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Partner removed successfully!', 'success');
                location.reload();
            } else {
                showToast('Error removing partner', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error removing partner', 'error');
        });
    }
}

// Get status color helper
function getStatusColor(status) {
    const colors = {
        'active': 'success',
        'pending': 'warning',
        'inactive': 'secondary'
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
function getStatusColor($status) {
    $colors = [
        'active' => 'success',
        'pending' => 'warning',
        'inactive' => 'secondary'
    ];
    return $colors[$status] ?? 'secondary';
}
?>