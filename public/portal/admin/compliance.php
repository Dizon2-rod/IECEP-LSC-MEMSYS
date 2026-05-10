<?php
$current_page = basename(__FILE__, '.php');
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/role-config.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'eb_president', 'super_admin'])) {
    header('Location: ' . PORTAL_URL . '/login.php');
    exit;
}

$pageTitle = 'Compliance Dashboard';
include '../../includes/dashboard-layout.php';

// Get institutions with member counts and compliance data
try {
    $institutions = $supabaseClient->from('institutions')
        ->select('id, name, status, created_at, members(count), attendance(count)')
        ->order('name');

    // Calculate participation rates (mock data for now - would need actual attendance data)
    $complianceData = [];
    foreach ($institutions as $inst) {
        $memberCount = $inst['members'][0]['count'] ?? 0;
        $attendanceCount = $inst['attendance'][0]['count'] ?? 0;

        // Mock participation rate calculation
        $participationRate = $memberCount > 0 ? min(100, ($attendanceCount / $memberCount) * 100) : 0;

        $complianceData[] = [
            'id' => $inst['id'],
            'name' => $inst['name'],
            'status' => $inst['status'],
            'member_count' => $memberCount,
            'participation_rate' => round($participationRate, 1),
            'created_at' => $inst['created_at']
        ];
    }

} catch (Exception $e) {
    $complianceData = [];
    error_log('Error fetching compliance data: ' . $e->getMessage());
}
?>

<div class="dashboard-container">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Compliance Dashboard</h1>
                    <p class="text-muted">Monitor institution status and participation rates</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="refreshData()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh
                    </button>
                    <button class="btn btn-outline-secondary" onclick="exportReport()">
                        <i class="fas fa-download me-2"></i>Export Report
                    </button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-building fa-2x text-primary mb-2"></i>
                            <h4 class="mb-0"><?= count($complianceData) ?></h4>
                            <small class="text-muted">Total Institutions</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <h4 class="mb-0"><?= count(array_filter($complianceData, fn($i) => $i['status'] === 'active')) ?></h4>
                            <small class="text-muted">Active Institutions</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-users fa-2x text-info mb-2"></i>
                            <h4 class="mb-0"><?= array_sum(array_column($complianceData, 'member_count')) ?></h4>
                            <small class="text-muted">Total Members</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-chart-line fa-2x text-warning mb-2"></i>
                            <h4 class="mb-0">
                                <?= count($complianceData) > 0 ? round(array_sum(array_column($complianceData, 'participation_rate')) / count($complianceData), 1) : 0 ?>%
                            </h4>
                            <small class="text-muted">Avg Participation</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Compliance Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Institution Compliance Overview</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Institution</th>
                                    <th>Status</th>
                                    <th>Members</th>
                                    <th>Participation Rate</th>
                                    <th>Last Activity</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($complianceData as $institution): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($institution['name']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $institution['status'] === 'active' ? 'success' : 'warning' ?>">
                                                <?= ucfirst($institution['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?= $institution['member_count'] ?> members
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                    <div class="progress-bar bg-<?= $institution['participation_rate'] >= 75 ? 'success' : ($institution['participation_rate'] >= 50 ? 'warning' : 'danger') ?>"
                                                         style="width: <?= $institution['participation_rate'] ?>%"></div>
                                                </div>
                                                <span class="small text-muted">
                                                    <?= $institution['participation_rate'] ?>%
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?= !empty($institution['created_at']) ? date('M j, Y', strtotime($institution['created_at'])) : 'N/A' ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary btn-sm"
                                                        onclick="viewDetails(<?= $institution['id'] ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-warning btn-sm"
                                                        onclick="sendReminder(<?= $institution['id'] ?>, '<?= htmlspecialchars($institution['name']) ?>')">
                                                    <i class="fas fa-bell"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (empty($complianceData)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                            <h5>No Compliance Data Available</h5>
                            <p class="text-muted">Compliance data will appear here once institutions are registered.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Institution Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Institution Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="institutionDetails">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Real-time updates using Supabase
let complianceChannel;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize real-time subscriptions
    initializeRealtimeUpdates();
});

function initializeRealtimeUpdates() {
    if (typeof supabase !== 'undefined') {
        complianceChannel = supabase
            .channel('compliance-updates')
            .on('postgres_changes',
                { event: '*', schema: 'public', table: 'institutions' },
                handleInstitutionUpdate
            )
            .on('postgres_changes',
                { event: '*', schema: 'public', table: 'attendance' },
                handleAttendanceUpdate
            )
            .subscribe();
    }
}

function handleInstitutionUpdate(payload) {
    console.log('Institution update:', payload);
    // Refresh data when institutions table changes
    if (payload.eventType !== 'SELECT') {
        location.reload();
    }
}

function handleAttendanceUpdate(payload) {
    console.log('Attendance update:', payload);
    // Refresh data when attendance table changes
    if (payload.eventType !== 'SELECT') {
        location.reload();
    }
}

function refreshData() {
    location.reload();
}

function exportReport() {
    // Generate and download compliance report
    const data = <?= json_encode($complianceData) ?>;
    const csvContent = generateCSV(data);
    downloadCSV(csvContent, 'compliance-report-' + new Date().toISOString().split('T')[0] + '.csv');
}

function generateCSV(data) {
    const headers = ['Institution', 'Status', 'Members', 'Participation Rate', 'Last Activity'];
    const rows = data.map(item => [
        item.name,
        item.status,
        item.member_count,
        item.participation_rate + '%',
        item.created_at ? new Date(item.created_at).toLocaleDateString() : 'N/A'
    ]);

    return [headers, ...rows].map(row => row.map(field => `"${field}"`).join(',')).join('\n');
}

function downloadCSV(content, filename) {
    const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function viewDetails(institutionId) {
    const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    const detailsDiv = document.getElementById('institutionDetails');

    detailsDiv.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';

    // Fetch detailed institution data
    fetch(`/IECEP-LSC-MEMSYS/public/api/institution-details.php?id=${institutionId}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                detailsDiv.innerHTML = `<div class="alert alert-danger">${data.message || 'Unable to load institution details.'}</div>`;
                return;
            }

            const institution = data.institution || {};
            detailsDiv.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Institution Information</h6>
                        <p><strong>Name:</strong> ${institution.name || 'N/A'}</p>
                        <p><strong>Status:</strong> <span class="badge bg-${institution.status === 'active' ? 'success' : 'warning'}">${institution.status || 'N/A'}</span></p>
                        <p><strong>Members:</strong> ${institution.member_count || 0}</p>
                        <p><strong>Joined:</strong> ${institution.created_at ? new Date(institution.created_at).toLocaleDateString() : 'N/A'}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Compliance Metrics</h6>
                        <p><strong>Participation Rate:</strong> ${institution.participation_rate || 0}%</p>
                        <p><strong>Events Attended:</strong> ${institution.attendance_count || 0}</p>
                        <p><strong>Last Activity:</strong> ${institution.latest_activity ? new Date(institution.latest_activity).toLocaleDateString() : 'N/A'}</p>
                    </div>
                </div>
            `;
        })
        .catch(error => {
            detailsDiv.innerHTML = '<div class="alert alert-danger">Error loading institution details.</div>';
            console.error('Error:', error);
        });

    modal.show();
}

function sendReminder(institutionId, institutionName) {
    if (confirm(`Send compliance reminder to ${institutionName}?`)) {
        // Send reminder notification
        fetch('/IECEP-LSC-MEMSYS/public/api/send-reminder.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                institution_id: institutionId,
                type: 'compliance'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                IECEP_Toast.show('Reminder sent successfully!', 'success');
            } else {
                IECEP_Toast.show('Failed to send reminder.', 'error');
            }
        })
        .catch(error => {
            IECEP_Toast.show('Error sending reminder.', 'error');
            console.error('Error:', error);
        });
    }
}
</script>

<?php include '../../includes/footer.php'; ?>
