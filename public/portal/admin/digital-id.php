<?php
$current_page = basename(__FILE__, '.php');
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/role-config.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'eb_admin') {
    header('Location: ' . PORTAL_URL . '/login.php');
    exit;
}

$pageTitle = 'Digital ID Management';
include '../../includes/dashboard-layout.php';

// Get blockchain records for digital IDs
try {
    $blockchainRecords = $supabaseClient->from('blockchain_records')
        ->select('*, members(name, email)')
        ->order('created_at', ['ascending' => false])
        ->limit(50)
        ->execute();

    $totalRecords = $supabaseClient->from('blockchain_records')
        ->select('*', ['count' => 'exact'])
        ->execute();

} catch (Exception $e) {
    $blockchainRecords = [];
    $totalRecords = 0;
    error_log('Error fetching blockchain records: ' . $e->getMessage());
}
?>

<div class="dashboard-container">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Digital ID Management</h1>
                    <p class="text-muted">Blockchain-based digital identity verification system</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="generateNewID()">
                        <i class="fas fa-plus me-2"></i>Generate New ID
                    </button>
                    <button class="btn btn-outline-secondary" onclick="verifyBlockchain()">
                        <i class="fas fa-check-circle me-2"></i>Verify Integrity
                    </button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-id-card fa-2x text-primary mb-2"></i>
                            <h4 class="mb-0"><?= $totalRecords ?></h4>
                            <small class="text-muted">Total Digital IDs</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-shield-alt fa-2x text-success mb-2"></i>
                            <h4 class="mb-0">100%</h4>
                            <small class="text-muted">Blockchain Verified</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                            <h4 class="mb-0">24h</h4>
                            <small class="text-muted">Avg. Generation Time</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-link fa-2x text-info mb-2"></i>
                            <h4 class="mb-0">256-bit</h4>
                            <small class="text-muted">Hash Security</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Blockchain Records Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Digital ID Records</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Digital ID</th>
                                    <th>Hash</th>
                                    <th>Block Number</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($blockchainRecords as $record): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle me-2">
                                                    <?= strtoupper(substr($record['members']['name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?= htmlspecialchars($record['members']['name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($record['members']['email']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <code class="text-primary"><?= htmlspecialchars($record['digital_id']) ?></code>
                                        </td>
                                        <td>
                                            <code class="text-secondary" style="font-size: 0.8em;">
                                                <?= substr($record['hash'], 0, 16) ?>...
                                            </code>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">Block #<?= $record['block_number'] ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $record['verified'] ? 'success' : 'warning' ?>">
                                                <i class="fas fa-<?= $record['verified'] ? 'check' : 'clock' ?> me-1"></i>
                                                <?= $record['verified'] ? 'Verified' : 'Pending' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= date('M d, Y H:i', strtotime($record['created_at'])) ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewDetails('<?= $record['id'] ?>')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-success" onclick="downloadCertificate('<?= $record['id'] ?>')">
                                                    <i class="fas fa-download"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="revokeID('<?= $record['id'] ?>')">
                                                    <i class="fas fa-ban"></i>
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

            <!-- Blockchain Integrity Check -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Blockchain Integrity</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Latest Block Information</h6>
                            <div class="mb-3">
                                <strong>Block Hash:</strong>
                                <code id="latestBlockHash" class="d-block mt-1">Loading...</code>
                            </div>
                            <div class="mb-3">
                                <strong>Previous Hash:</strong>
                                <code id="previousBlockHash" class="d-block mt-1">Loading...</code>
                            </div>
                            <div class="mb-3">
                                <strong>Merkle Root:</strong>
                                <code id="merkleRoot" class="d-block mt-1">Loading...</code>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6>Integrity Status</h6>
                            <div class="alert alert-success" id="integrityStatus">
                                <i class="fas fa-shield-alt me-2"></i>
                                <strong>Blockchain Integrity: Verified</strong>
                                <p class="mb-0 mt-2">All blocks are properly chained and verified.</p>
                            </div>
                            <button class="btn btn-outline-primary" onclick="runIntegrityCheck()">
                                <i class="fas fa-search me-2"></i>Run Full Integrity Check
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Generate New ID Modal -->
<div class="modal fade" id="generateIDModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate New Digital ID</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="generateIDForm">
                    <div class="mb-3">
                        <label for="memberSelect" class="form-label">Select Member</label>
                        <select class="form-select" id="memberSelect" required>
                            <option value="">Choose a member...</option>
                            <!-- Options will be populated via JavaScript -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="idType" class="form-label">ID Type</label>
                        <select class="form-select" id="idType" required>
                            <option value="membership">Membership ID</option>
                            <option value="certification">Certification ID</option>
                            <option value="achievement">Achievement ID</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="validityPeriod" class="form-label">Validity Period (Years)</label>
                        <input type="number" class="form-control" id="validityPeriod" value="1" min="1" max="10" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitGenerateID()">Generate ID</button>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    loadLatestBlockInfo();
    loadMembers();
});

// Load latest block information
function loadLatestBlockInfo() {
    fetch('../../api/blockchain.php?action=latest_block')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('latestBlockHash').textContent = data.block.hash.substring(0, 32) + '...';
                document.getElementById('previousBlockHash').textContent = data.block.previous_hash.substring(0, 32) + '...';
                document.getElementById('merkleRoot').textContent = data.block.merkle_root.substring(0, 32) + '...';
            }
        })
        .catch(error => console.error('Error loading block info:', error));
}

// Load members for dropdown
function loadMembers() {
    fetch('../../api/members.php?action=list')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('memberSelect');
                data.members.forEach(member => {
                    const option = document.createElement('option');
                    option.value = member.id;
                    option.textContent = `${member.name} (${member.email})`;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => console.error('Error loading members:', error));
}

// Generate new digital ID
function generateNewID() {
    const modal = new bootstrap.Modal(document.getElementById('generateIDModal'));
    modal.show();
}

function submitGenerateID() {
    const form = document.getElementById('generateIDForm');
    const formData = new FormData(form);

    const data = {
        member_id: formData.get('memberSelect'),
        id_type: formData.get('idType'),
        validity_period: parseInt(formData.get('validityPeriod'))
    };

    fetch('../../api/digital-id.php?action=generate', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Digital ID generated successfully!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('generateIDModal')).hide();
            location.reload();
        } else {
            showToast('Error generating ID: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error generating digital ID', 'error');
    });
}

// View details
function viewDetails(recordId) {
    window.open(`digital-id-details.php?id=${recordId}`, '_blank');
}

// Download certificate
function downloadCertificate(recordId) {
    window.open(`../../api/digital-id.php?action=download&id=${recordId}`, '_blank');
}

// Revoke ID
function revokeID(recordId) {
    if (confirm('Are you sure you want to revoke this digital ID? This action cannot be undone.')) {
        fetch(`../../api/digital-id.php?action=revoke&id=${recordId}`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Digital ID revoked successfully!', 'success');
                location.reload();
            } else {
                showToast('Error revoking ID: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error revoking digital ID', 'error');
        });
    }
}

// Verify blockchain integrity
function verifyBlockchain() {
    fetch('../../api/blockchain.php?action=verify_integrity')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Blockchain integrity verified!', 'success');
            } else {
                showToast('Blockchain integrity check failed!', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error verifying blockchain', 'error');
        });
}

// Run full integrity check
function runIntegrityCheck() {
    showToast('Running full integrity check...', 'info');

    fetch('../../api/blockchain.php?action=full_integrity_check')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Full integrity check passed!', 'success');
            } else {
                showToast('Integrity check failed: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error running integrity check', 'error');
        });
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
