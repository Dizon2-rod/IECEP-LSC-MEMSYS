<?php
require_once __DIR__ . '/../auth_check.php';
require_role(['admin', 'super_admin']);

require_once __DIR__ . '/../../includes/db.php';

$db = Database::getInstance();

// Get filters
$entityType = $_GET['entity_type'] ?? '';
$confirmed = $_GET['confirmed'] ?? '';

// Build query
$where = ['1=1'];
$params = [];

if (!empty($entityType)) {
    $where[] = "entity_type = ?";
    $params[] = $entityType;
}

if ($confirmed !== '') {
    $where[] = "confirmed = ?";
    $params[] = $confirmed === 'true' ? 1 : 0;
}

$whereClause = implode(' AND ', $where);

// Get blockchain records
$records = $db->fetchAll("SELECT br.*, i.name as institution_name 
    FROM blockchain_records br 
    LEFT JOIN institutions i ON br.institution_id = i.id
    WHERE $whereClause
    ORDER BY br.created_at DESC
    LIMIT 100", $params);

// Get chain integrity check
$totalRecords = $db->fetchOne("SELECT COUNT(*) as count FROM blockchain_records")['count'];
$confirmedRecords = $db->fetchOne("SELECT COUNT(*) as count FROM blockchain_records WHERE confirmed = 1")['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blockchain Explorer - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/professional.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/font-awesome.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-navy);
        }
        .stat-label {
            font-size: var(--font-size-sm);
            color: var(--gray-600);
            margin-top: 0.5rem;
        }
        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .filter-group select {
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
        .hash-text {
            font-family: monospace;
            font-size: var(--font-size-sm);
        }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-medium);
        }
        .badge-confirmed { background: var(--success-light); color: var(--success-dark); }
        .badge-pending { background: var(--warning-light); color: var(--warning-dark); }
        .integrity-check {
            background: linear-gradient(135deg, var(--success) 0%, var(--success-dark) 100%);
            color: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>Blockchain Explorer</h1>
                    <p class="text-gray">View and verify blockchain records</p>
                </div>
                <button onclick="verifyChain()" class="btn btn-primary">
                    <i class="fas fa-shield-alt"></i> Verify Chain Integrity
                </button>
            </div>

            <div class="integrity-check" id="integrityCheck">
                <h2><i class="fas fa-check-circle"></i> Chain Status: Valid</h2>
                <p><?php echo $confirmedRecords; ?> of <?php echo $totalRecords; ?> records confirmed</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $totalRecords; ?></div>
                    <div class="stat-label">Total Records</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $confirmedRecords; ?></div>
                    <div class="stat-label">Confirmed Records</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $totalRecords - $confirmedRecords; ?></div>
                    <div class="stat-label">Pending Records</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $totalRecords > 0 ? round(($confirmedRecords / $totalRecords) * 100, 1) : 0; ?>%</div>
                    <div class="stat-label">Confirmation Rate</div>
                </div>
            </div>

            <div class="filters">
                <div class="filter-group">
                    <select id="entityTypeFilter" onchange="applyFilters()">
                        <option value="">All Entity Types</option>
                        <option value="membership_id" <?php echo $entityType === 'membership_id' ? 'selected' : ''; ?>>Membership ID</option>
                        <option value="member" <?php echo $entityType === 'member' ? 'selected' : ''; ?>>Member</option>
                        <option value="transaction" <?php echo $entityType === 'transaction' ? 'selected' : ''; ?>>Transaction</option>
                        <option value="certificate" <?php echo $entityType === 'certificate' ? 'selected' : ''; ?>>Certificate</option>
                        <option value="document" <?php echo $entityType === 'document' ? 'selected' : ''; ?>>Document</option>
                    </select>
                </div>
                <div class="filter-group">
                    <select id="confirmedFilter" onchange="applyFilters()">
                        <option value="">All Status</option>
                        <option value="true" <?php echo $confirmed === 'true' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="false" <?php echo $confirmed === 'false' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
            </div>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Entity Type</th>
                            <th>Entity ID</th>
                            <th>Transaction Hash</th>
                            <th>Block Number</th>
                            <th>Institution</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2rem;">No blockchain records found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($records as $record): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($record['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($record['entity_type']); ?></td>
                                <td class="hash-text"><?php echo htmlspecialchars(substr($record['entity_id'], 0, 20)) . '...'; ?></td>
                                <td class="hash-text"><?php echo htmlspecialchars(substr($record['transaction_hash'], 0, 30)) . '...'; ?></td>
                                <td><?php echo htmlspecialchars($record['block_number'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($record['institution_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($record['confirmed']): ?>
                                        <span class="badge badge-confirmed">Confirmed</span>
                                    <?php else: ?>
                                        <span class="badge badge-pending">Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        function applyFilters() {
            const entityType = document.getElementById('entityTypeFilter').value;
            const confirmed = document.getElementById('confirmedFilter').value;
            
            const params = new URLSearchParams();
            if (entityType) params.set('entity_type', entityType);
            if (confirmed) params.set('confirmed', confirmed);
            
            window.location.href = '?' + params.toString();
        }

        function verifyChain() {
            const integrityCheck = document.getElementById('integrityCheck');
            integrityCheck.innerHTML = '<h2><i class="fas fa-spinner fa-spin"></i> Verifying...</h2>';
            
            fetch('<?php echo BASE_URL; ?>/api/verify-chain.php')
                .then(response => response.json())
                .then(data => {
                    if (data.valid) {
                        integrityCheck.innerHTML = '<h2><i class="fas fa-check-circle"></i> Chain Status: Valid</h2><p>All records verified successfully</p>';
                        integrityCheck.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
                    } else {
                        integrityCheck.innerHTML = '<h2><i class="fas fa-exclamation-triangle"></i> Chain Status: Invalid</h2><p>' + (data.message || 'Chain integrity check failed') + '</p>';
                        integrityCheck.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
                    }
                })
                .catch(error => {
                    integrityCheck.innerHTML = '<h2><i class="fas fa-times-circle"></i> Verification Failed</h2><p>Unable to verify chain integrity</p>';
                    integrityCheck.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
                });
        }
    </script>
</body>
</html>
