<?php
require_once __DIR__ . '/bootstrap.php';
session_start();

// Check if user is logged in and has admin/registration committee role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: /IECEP-LSC-MEMSYS/login.php');
    exit;
}

$userRole = $_SESSION['user']['role'] ?? '';
$allowedRoles = ['admin', 'eb_president', 'school_officer'];

if (!in_array($userRole, $allowedRoles)) {
    http_response_code(403);
    die('Access denied. Only admins and registration committee members can view this page.');
}

require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../../includes/paths.php';
require_once __DIR__ . '/../../src/lib/SupabaseClient.php';

$config = require __DIR__ . '/../../includes/supabase.php';
$sb = new SupabaseClient($config['url'], $config['anon_key']);

// Get all pending affiliations
$affiliations = [];
try {
    $affiliations = $sb->select('pending_affiliations', ['status' => 'eq.pending']);
    if (!is_array($affiliations)) {
        $affiliations = [];
    }
} catch (Exception $e) {
    error_log('Error fetching affiliations: ' . $e->getMessage());
}

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $affiliationId = $_POST['affiliation_id'] ?? null;
    $action = $_POST['action'];
    
    if ($affiliationId && in_array($action, ['approve', 'reject'])) {
        try {
            $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
            $sb->update('pending_affiliations', ['status' => $newStatus], $affiliationId);
            
            // Redirect to refresh
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        } catch (Exception $e) {
            error_log('Error updating affiliation: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Affiliations - IECEP-LSC Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 28px;
            color: #0B1D4A;
            margin-bottom: 5px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
        }
        
        .table-wrapper {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #0B1D4A;
            color: white;
        }
        
        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        tbody tr:hover {
            background: #f9f9f9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-approved {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .actions {
            display: flex;
            gap: 8px;
        }
        
        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-view {
            background: #3b82f6;
            color: white;
        }
        
        .btn-view:hover {
            background: #2563eb;
        }
        
        .btn-approve {
            background: #10b981;
            color: white;
        }
        
        .btn-approve:hover {
            background: #059669;
        }
        
        .btn-reject {
            background: #ef4444;
            color: white;
        }
        
        .btn-reject:hover {
            background: #dc2626;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 8px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }
        
        .modal-header {
            font-size: 20px;
            font-weight: 700;
            color: #0B1D4A;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
        }
        
        .info-group {
            margin-bottom: 20px;
        }
        
        .info-label {
            font-size: 12px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 14px;
            color: #333;
        }
        
        .file-link {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
        }
        
        .file-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1><i class="fas fa-file-contract"></i> Pending Affiliations</h1>
        <p>Review and manage institution affiliation applications</p>
    </div>
    
    <div class="table-wrapper">
        <?php if (empty($affiliations)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>No pending affiliations at this time</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Institution</th>
                        <th>Contact Person</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($affiliations as $aff): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($aff['institution_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($aff['contact_person']); ?></td>
                        <td><?php echo htmlspecialchars($aff['contact_email']); ?></td>
                        <td><?php echo htmlspecialchars($aff['contact_phone']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($aff['submitted_at'])); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($aff['status']); ?>">
                                <?php echo ucfirst($aff['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="actions">
                                <button class="btn btn-view" onclick="viewDetails(<?php echo htmlspecialchars(json_encode($aff)); ?>)">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <?php if ($aff['status'] === 'pending'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="affiliation_id" value="<?php echo $aff['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-approve" onclick="return confirm('Approve this affiliation?')">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="affiliation_id" value="<?php echo $aff['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn-reject" onclick="return confirm('Reject this affiliation?')">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Details Modal -->
<div id="detailsModal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal()">&times;</button>
        <div class="modal-header">
            <i class="fas fa-building"></i>
            <span id="modalTitle">Affiliation Details</span>
        </div>
        
        <div id="modalBody"></div>
    </div>
</div>

<script>
function viewDetails(affiliation) {
    const modal = document.getElementById('detailsModal');
    const body = document.getElementById('modalBody');
    
    const html = `
        <div class="info-group">
            <div class="info-label">Institution Name</div>
            <div class="info-value">${affiliation.institution_name}</div>
        </div>
        
        <div class="info-group">
            <div class="info-label">Address</div>
            <div class="info-value">${affiliation.institution_address}</div>
        </div>
        
        <div class="info-group">
            <div class="info-label">Contact Person</div>
            <div class="info-value">${affiliation.contact_person}</div>
        </div>
        
        <div class="info-group">
            <div class="info-label">Position</div>
            <div class="info-value">${affiliation.contact_position}</div>
        </div>
        
        <div class="info-group">
            <div class="info-label">Email</div>
            <div class="info-value">${affiliation.contact_email}</div>
        </div>
        
        <div class="info-group">
            <div class="info-label">Phone</div>
            <div class="info-value">${affiliation.contact_phone}</div>
        </div>
        
        <div class="info-group">
            <div class="info-label">Submitted</div>
            <div class="info-value">${new Date(affiliation.submitted_at).toLocaleString()}</div>
        </div>
        
        <div class="info-group">
            <div class="info-label">Documents</div>
            <div class="info-value">
                <ul style="list-style: none; padding: 0;">
                    <li><a href="${affiliation.letter_of_intent}" class="file-link" target="_blank"><i class="fas fa-file-pdf"></i> Letter of Intent</a></li>
                    <li><a href="${affiliation.endorsement_letter}" class="file-link" target="_blank"><i class="fas fa-file-pdf"></i> Endorsement Letter</a></li>
                    <li><a href="${affiliation.constitution_by_laws}" class="file-link" target="_blank"><i class="fas fa-file-pdf"></i> Constitution & By-Laws</a></li>
                    <li><a href="${affiliation.officers_cvs}" class="file-link" target="_blank"><i class="fas fa-file-pdf"></i> Officers CVs</a></li>
                    <li><a href="${affiliation.organizational_chart}" class="file-link" target="_blank"><i class="fas fa-file-pdf"></i> Organizational Chart</a></li>
                    <li><a href="${affiliation.member_directory}" class="file-link" target="_blank"><i class="fas fa-file-excel"></i> Member Directory</a></li>
                </ul>
            </div>
        </div>
    `;
    
    body.innerHTML = html;
    modal.classList.add('active');
}

function closeModal() {
    document.getElementById('detailsModal').classList.remove('active');
}

document.getElementById('detailsModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

</body>
</html>
