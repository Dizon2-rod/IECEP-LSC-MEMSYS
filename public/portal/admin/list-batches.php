<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../portal/auth_check.php';
require_once __DIR__ . '/../../includes/config.php';

require_role(['admin', 'registration']);

$db = getDbConnection();

// Fetch all upload batches
$stmt = $db->prepare("
    SELECT 
        b.id, b.file_name, b.uploaded_at, b.total_rows, b.validated_rows, b.status,
        u.full_name as uploaded_by,
        COUNT(CASE WHEN m.is_valid = true THEN 1 END) as valid_rows
    FROM upload_batches b
    LEFT JOIN user_profiles u ON b.uploaded_by_user_id = u.id
    LEFT JOIN membership_directory_imports m ON b.id = m.batch_id
    GROUP BY b.id, b.file_name, b.uploaded_at, b.total_rows, b.validated_rows, b.status, u.full_name
    ORDER BY b.uploaded_at DESC
");
$stmt->execute();
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Directory Upload Batches - IECEP-LSC MEMSYS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@6.0.0/css/all.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --danger-color: #e74c3c;
            --light-bg: #ecf0f1;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-bg);
        }
        .container-main {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .card-header {
            background-color: var(--primary-color);
            color: white;
        }
        .btn-sm {
            font-size: 0.875rem;
        }
        .status-badge {
            padding: 0.35rem 0.65rem;
            border-radius: 0.25rem;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-in-progress {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        .progress-mini {
            height: 0.5rem;
        }
        table {
            background-color: white;
        }
    </style>
</head>
<body>
    <div class="container-main">
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="fas fa-list"></i> Member Directory Upload Batches</h4>
                        <a href="../../school-officer/upload-directory.php" class="btn btn-light btn-sm">
                            <i class="fas fa-plus"></i> Upload Directory
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($batches)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No upload batches found. 
                                <a href="../../school-officer/upload-directory.php">Upload your first member directory</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Batch ID</th>
                                            <th>Filename</th>
                                            <th>Uploaded By</th>
                                            <th>Uploaded At</th>
                                            <th>Progress</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($batches as $batch): ?>
                                            <?php
                                            $progress_percent = $batch['total_rows'] > 0 
                                                ? round(($batch['validated_rows'] / $batch['total_rows']) * 100) 
                                                : 0;
                                            ?>
                                            <tr>
                                                <td>
                                                    <code><?php echo substr(htmlspecialchars($batch['id']), 0, 12); ?>...</code>
                                                </td>
                                                <td><?php echo htmlspecialchars($batch['file_name']); ?></td>
                                                <td><?php echo htmlspecialchars($batch['uploaded_by'] ?? 'Unknown'); ?></td>
                                                <td>
                                                    <small><?php echo date('M d, Y H:i', strtotime($batch['uploaded_at'])); ?></small>
                                                </td>
                                                <td>
                                                    <div style="width: 150px;">
                                                        <div class="progress progress-mini">
                                                            <div class="progress-bar" role="progressbar" 
                                                                 style="width: <?php echo $progress_percent; ?>%"
                                                                 aria-valuenow="<?php echo $progress_percent; ?>" 
                                                                 aria-valuemin="0" aria-valuemax="100"></div>
                                                        </div>
                                                        <small class="text-muted">
                                                            <?php echo $batch['validated_rows']; ?>/<?php echo $batch['total_rows']; ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="status-badge status-<?php echo htmlspecialchars($batch['status']); ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $batch['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="validate-directory.php?batch_id=<?php echo urlencode($batch['id']); ?>" 
                                                       class="btn btn-primary btn-sm">
                                                        <i class="fas fa-check-square"></i> Validate
                                                    </a>
                                                    <?php if ($batch['validated_rows'] > 0): ?>
                                                        <a href="../../api/admin/export-validated-directory.php?batch_id=<?php echo urlencode($batch['id']); ?>&csrf_token=<?php echo htmlspecialchars(generate_csrf_token()); ?>" 
                                                           class="btn btn-success btn-sm" target="_blank">
                                                            <i class="fas fa-download"></i> Export
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
function getDbConnection() {
    static $db = null;
    if ($db === null) {
        $db = new PDO(
            'pgsql:host=' . env('DB_HOST') . ';port=' . env('DB_PORT', 5432) . ';dbname=' . env('DB_NAME'),
            env('DB_USER'),
            env('DB_PASSWORD')
        );
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $db;
}
?>
