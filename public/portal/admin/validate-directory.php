<?php
/**
 * Validate Member Directory
 * Post-approval page for validating and assigning membership IDs
 */

require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../src/lib/SupabaseClient.php';
require_role(['admin', 'registration']);

$application_id = $_GET['application_id'] ?? null;

if (!$application_id) {
    header('Location: affiliations.php');
    exit;
}

// Use Supabase for data
$config = require __DIR__ . '/../../../includes/supabase.php';
$supabase = new SupabaseClient($config['url'], $config['anon_key']);

// Fetch application details from Supabase
try {
    $applications = $supabase->select('pending_affiliations', ['id' => "eq.{$application_id}", 'status' => 'eq.approved']);
    $application = !empty($applications) ? $applications[0] : null;
} catch (Exception $e) {
    error_log("Error fetching application: " . $e->getMessage());
    $application = null;
}

if (!$application) {
    die('Application not found or not approved');
}

// For now, members array is empty since we're using Supabase
// In the future, you can store member directory data in Supabase tables
$members = [];

$user = get_user_info();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validate Member Directory – IECEP-LSC</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/dashboard.css">
    <style>
        :root {
            --navy: #0A2F6C;
            --gold: #F5A623;
            --success: #10b981;
            --error: #ef4444;
        }
        .dashboard-content { padding: 40px; max-width: 1600px; margin: 0 auto; }
        .header-section { margin-bottom: 32px; }
        .header-section h2 { font-size: 2rem; font-weight: 700; color: var(--navy); margin-bottom: 8px; }
        .header-section p { color: #64748b; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 32px; }
        .stat-card { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .stat-card .stat-value { font-size: 2rem; font-weight: 700; color: var(--navy); }
        .stat-card .stat-label { font-size: 0.875rem; color: #64748b; margin-top: 4px; }
        .members-table { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .members-table table { width: 100%; border-collapse: collapse; }
        .members-table th { background: var(--navy); color: white; padding: 16px; text-align: left; font-weight: 600; font-size: 0.875rem; }
        .members-table td { padding: 16px; border-bottom: 1px solid #e2e8f0; font-size: 0.875rem; }
        .members-table tr:hover { background: #f8fafc; }
        .checkbox-cell { width: 40px; text-align: center; }
        .select-cell { width: 120px; }
        .select-cell select { padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.875rem; }
        .btn { padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; }
        .btn-primary { background: var(--navy); color: white; }
        .btn-primary:hover { background: #1e4a8a; }
        .btn-success { background: var(--success); color: white; }
        .btn-success:hover { background: #059669; }
        .actions-bar { display: flex; gap: 12px; margin-bottom: 24px; align-items: center; }
        .badge { padding: 4px 12px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; }
        .badge-paid { background: #d1fae5; color: #065f46; }
        .badge-unpaid { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <header class="dashboard-header">
            <div class="header-content">
                <div>
                    <h1>Validate Member Directory</h1>
                    <p class="welcome-message"><?php echo htmlspecialchars($application['institution_name'] ?? 'Institution'); ?></p>
                </div>
            </div>
        </header>

        <div class="dashboard-content">
            <div class="header-section">
                <h2>Member Directory Validation</h2>
                <p>Review members, mark payment status, assign membership IDs</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($members); ?></div>
                    <div class="stat-label">Total Members</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="stat-validated">0</div>
                    <div class="stat-label">Validated</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="stat-pending"><?php echo count($members); ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>

            <div class="actions-bar">
                <button class="btn btn-primary" onclick="selectAll()">
                    <i class="fas fa-check-square"></i> Select All
                </button>
                <button class="btn btn-primary" onclick="markAllPaid()">
                    <i class="fas fa-dollar-sign"></i> Mark All Paid
                </button>
                <button class="btn btn-success" onclick="bulkAssignIds()">
                    <i class="fas fa-id-card"></i> Assign IDs to Selected
                </button>
                <button class="btn btn-success" onclick="exportDirectory()">
                    <i class="fas fa-download"></i> Export Excel
                </button>
            </div>

            <div class="members-table">
                <table>
                    <thead>
                        <tr>
                            <th class="checkbox-cell"><input type="checkbox" id="select-all" onchange="toggleAll(this)"></th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Birthday</th>
                            <th>Year Level</th>
                            <th>Payment</th>
                            <th class="select-cell">Type</th>
                            <th>Membership ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $member): ?>
                        <tr data-id="<?php echo $member['id']; ?>">
                            <td class="checkbox-cell">
                                <input type="checkbox" class="member-checkbox" value="<?php echo $member['id']; ?>">
                            </td>
                            <td><?php echo htmlspecialchars($member['name']); ?></td>
                            <td><?php echo htmlspecialchars($member['email']); ?></td>
                            <td><?php echo htmlspecialchars($member['birthday']); ?></td>
                            <td><?php echo htmlspecialchars($member['sheet_name']); ?></td>
                            <td>
                                <input type="checkbox" class="paid-checkbox" data-id="<?php echo $member['id']; ?>" 
                                    <?php echo $member['is_paid'] ? 'checked' : ''; ?>>
                                <span class="badge <?php echo $member['is_paid'] ? 'badge-paid' : 'badge-unpaid'; ?>">
                                    <?php echo $member['is_paid'] ? 'Paid' : 'Unpaid'; ?>
                                </span>
                            </td>
                            <td class="select-cell">
                                <select class="member-type-select" data-id="<?php echo $member['id']; ?>">
                                    <option value="">--</option>
                                    <option value="new" <?php echo $member['member_type'] === 'new' ? 'selected' : ''; ?>>New</option>
                                    <option value="old" <?php echo $member['member_type'] === 'old' ? 'selected' : ''; ?>>Old</option>
                                </select>
                            </td>
                            <td>
                                <span class="membership-id">
                                    <?php echo $member['assigned_membership_id'] ? htmlspecialchars($member['assigned_membership_id']) : '—'; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
    const applicationId = '<?php echo $application_id; ?>';

    function toggleAll(checkbox) {
        document.querySelectorAll('.member-checkbox').forEach(cb => cb.checked = checkbox.checked);
    }

    function selectAll() {
        document.querySelectorAll('.member-checkbox').forEach(cb => cb.checked = true);
    }

    function markAllPaid() {
        document.querySelectorAll('.paid-checkbox').forEach(cb => cb.checked = true);
    }

    async function bulkAssignIds() {
        const selected = Array.from(document.querySelectorAll('.member-checkbox:checked')).map(cb => {
            const row = cb.closest('tr');
            const id = cb.value;
            const isPaid = row.querySelector('.paid-checkbox').checked;
            const memberType = row.querySelector('.member-type-select').value;
            return { id, is_paid: isPaid, member_type: memberType };
        });

        if (selected.length === 0) {
            alert('Please select at least one member');
            return;
        }

        const invalidRows = selected.filter(r => !r.is_paid || !r.member_type);
        if (invalidRows.length > 0) {
            alert('All selected members must be marked as paid and have a member type selected');
            return;
        }

        if (!confirm(`Assign membership IDs to ${selected.length} member(s)?`)) return;

        try {
            const response = await fetch('/IECEP-LSC-MEMSYS/public/api/admin/bulk-assign-ids.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    application_id: applicationId,
                    rows: JSON.stringify(selected),
                    csrf_token: '<?php echo generate_csrf_token(); ?>'
                })
            });

            const result = await response.json();
            if (result.success) {
                alert(`Successfully assigned ${result.assigned_count} membership ID(s)`);
                location.reload();
            } else {
                alert('Error: ' + (result.message || 'Failed to assign IDs'));
            }
        } catch (error) {
            alert('Network error: ' + error.message);
        }
    }

    async function exportDirectory() {
        window.location.href = `/IECEP-LSC-MEMSYS/public/api/admin/export-validated-directory.php?application_id=${applicationId}`;
    }
    </script>
</body>
</html>
