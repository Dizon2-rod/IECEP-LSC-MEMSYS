<?php
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../auth_check.php';
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/role-config.php';

$current_page = 'batch-process';
$page_title = 'Bulk Member CSV Import & Database Commit';

require_role(['admin', 'super_admin', 'registration', 'committee_registration']);

$supabase = getSupabaseClient();
$importResult = null;

// Handle real CSV upload and database commit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    if ($file['error'] === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'])) {
        $content = file_get_contents($file['tmp_name']);
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        
        if (count($lines) > 1) {
            $headers = array_map('trim', explode(',', strtolower(array_shift($lines))));
            $emailIdx = array_search('email', $headers);
            $nameIdx = array_search('full_name', $headers);
            $roleIdx = array_search('role', $headers);

            if ($nameIdx === false) $nameIdx = array_search('name', $headers);
            if ($emailIdx !== false && $nameIdx !== false) {
                $imported = 0;
                $failed = 0;
                $timestamp = date('c');
                $batchId = bin2hex(random_bytes(16));

                // Fetch existing count to assign sequential membership IDs
                $existingMembers = $supabase->select('members', ['select' => 'id']);
                $baseCount = is_array($existingMembers) ? count($existingMembers) : 1;

                foreach ($lines as $line) {
                    if (empty(trim($line))) continue;
                    $cols = str_getcsv($line);
                    $email = trim($cols[$emailIdx] ?? '');
                    $name = trim($cols[$nameIdx] ?? '');
                    $role = ($roleIdx !== false && !empty($cols[$roleIdx])) ? trim($cols[$roleIdx]) : 'member';

                    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($name)) {
                        $baseCount++;
                        $memId = bin2hex(random_bytes(16));
                        $membershipId = 'IECEP-2026-' . str_pad($baseCount, 4, '0', STR_PAD_LEFT);
                        $hash = hash('sha256', $memId . $name . $email . $timestamp);

                        try {
                            // 1. Insert Member
                            $supabase->insert('members', [[
                                'id' => $memId,
                                'full_name' => $name,
                                'email' => $email,
                                'membership_id' => $membershipId,
                                'member_type' => 'regular',
                                'year_level' => '3rd Year',
                                'digital_id_hash' => $hash,
                                'digital_id_url' => 'DID-2026-LSC-' . strtoupper(substr($memId, 0, 4)),
                                'created_at' => $timestamp,
                                'updated_at' => $timestamp
                            ]]);

                            // 2. Insert User Profile
                            $supabase->insert('user_profiles', [[
                                'id' => $memId,
                                'user_id' => $memId,
                                'full_name' => $name,
                                'role' => $role,
                                'membership_status' => 'active',
                                'membership_type' => 'regular',
                                'created_at' => $timestamp
                            ]]);

                            $imported++;
                        } catch (Exception $e) {
                            error_log("Row import error for $email: " . $e->getMessage());
                            $failed++;
                        }
                    } else {
                        $failed++;
                    }
                }

                $importResult = [
                    'success' => true,
                    'imported' => $imported,
                    'failed' => $failed,
                    'batch_id' => $batchId
                ];
            } else {
                $importResult = ['success' => false, 'message' => 'CSV must include "email" and "full_name" columns.'];
            }
        } else {
            $importResult = ['success' => false, 'message' => 'CSV file contains no data rows.'];
        }
    } else {
        $importResult = ['success' => false, 'message' => 'Failed to read uploaded file.'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Bulk import student members via CSV upload for IECEP-LSC Laguna Student Chapter.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .upload-dropzone {
            border: 2px dashed var(--iecep-gold);
            background: #F8FAFC;
            border-radius: 16px;
            padding: 3rem 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .upload-dropzone:hover, .upload-dropzone.dragover {
            background: #FFFFFF;
            border-color: var(--iecep-navy);
            box-shadow: var(--card-shadow);
        }
    </style>
</head>
<body>
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- Page Header -->
            <div class="ap-page-header">
                <div class="ap-title-block">
                    <h1 class="ap-page-title"><i class="fas fa-file-import"></i> Bulk Member CSV Import & Database Commit</h1>
                    <p class="ap-page-subtitle">Upload chapter student member rosters to create user accounts, assign verified IDs, and save directly to Supabase.</p>
                </div>
                <div class="ap-header-actions">
                    <a href="/IECEP-LSC-MEMSYS/public/portal/admin/members/list.php" class="ap-btn-secondary">
                        <i class="fas fa-arrow-left"></i> View Roster
                    </a>
                    <button type="button" id="downloadTemplate" class="ap-btn-primary">
                        <i class="fas fa-download"></i> Download CSV Template
                    </button>
                </div>
            </div>

            <?php if ($importResult): ?>
                <?php if ($importResult['success']): ?>
                    <div class="ap-alert success">
                        <i class="fas fa-circle-check"></i>
                        <div>
                            <strong>Batch Import Complete!</strong><br>
                            Successfully created and saved <strong><?= $importResult['imported'] ?></strong> real member accounts into the database.
                            <?php if ($importResult['failed'] > 0): ?> (<?= $importResult['failed'] ?> rows skipped/invalid)<?php endif; ?>
                            <div style="margin-top:0.5rem;">
                                <a href="/IECEP-LSC-MEMSYS/public/portal/admin/members/list.php" class="ap-btn-primary" style="padding:0.3rem 0.8rem; font-size:0.78rem;">
                                    <i class="fas fa-users"></i> View Updated Roster
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="ap-alert danger">
                        <i class="fas fa-triangle-exclamation"></i>
                        <span><?= htmlspecialchars($importResult['message'] ?? 'Import failed.') ?></span>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Upload Card -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-cloud-arrow-up"></i> Upload CSV Member Directory</h3>
                </div>

                <form method="POST" enctype="multipart/form-data" id="importForm">
                    <input type="file" id="csvFileInput" name="csv_file" accept=".csv" style="display:none;" onchange="handleFileSelected(this)">
                    
                    <div class="upload-dropzone" id="dropzone" onclick="document.getElementById('csvFileInput').click()">
                        <i class="fas fa-file-csv" style="font-size:3.5rem; color:var(--iecep-gold); margin-bottom:1rem;"></i>
                        <h3 style="margin:0 0 0.5rem 0; color:var(--text-heading); font-size:1.15rem;">Click to browse or drag & drop CSV file</h3>
                        <p style="margin:0; font-size:0.85rem; color:var(--text-muted);">Required CSV columns: <code>email, full_name, role</code></p>
                        <div id="selectedFileInfo" style="display:none; margin-top:1rem; font-weight:700; color:var(--iecep-navy);"></div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.5rem;">
                        <button type="submit" class="ap-btn-primary" id="submitBtn" disabled>
                            <i class="fas fa-database"></i> Process & Save to Database
                        </button>
                    </div>
                </form>
            </div>

            <!-- Preview Card -->
            <div class="ap-card" id="previewCard" style="display:none;">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-table-list"></i> CSV Row Preview (<span id="rowCount">0</span> rows)</h3>
                </div>
                <div class="ap-table-wrapper">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Full Name</th>
                                <th>Institutional Email</th>
                                <th>Role</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="previewTableBody"></tbody>
                    </table>
                </div>
            </div>

            <!-- Sentinel -->
            <div class="ap-sentinel-strip">
                <div class="ap-sentinel-item"><i class="fas fa-shield-halved"></i><span><strong>Data Ingestion:</strong> Direct Supabase REST Protocol</span></div>
                <div class="ap-sentinel-item"><i class="fas fa-link"></i><span><strong>Ledger Anchor:</strong> SHA-256 Hashes Generated Sequentially</span></div>
            </div>

        </div>
    </main>

    <script>
        document.getElementById('downloadTemplate').addEventListener('click', function() {
            const csv = 'email,full_name,role\nexample.student1@lspu.edu.ph,Juan Dela Cruz,member\nexample.student2@lspu.edu.ph,Maria Santos,member';
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'iecep_members_template.csv';
            a.click();
            URL.revokeObjectURL(url);
        });

        function handleFileSelected(input) {
            if (!input.files || !input.files[0]) return;
            const file = input.files[0];
            document.getElementById('selectedFileInfo').textContent = `Selected: ${file.name} (${(file.size/1024).toFixed(1)} KB)`;
            document.getElementById('selectedFileInfo').style.display = 'block';
            document.getElementById('submitBtn').disabled = false;

            // Preview
            const reader = new FileReader();
            reader.onload = function(e) {
                const lines = e.target.result.trim().split('\n').filter(l => l.trim());
                if (lines.length > 1) {
                    const tbody = document.getElementById('previewTableBody');
                    tbody.innerHTML = '';
                    let count = 0;
                    for (let i = 1; i < lines.length; i++) {
                        const cols = lines[i].split(',').map(c => c.trim());
                        if (cols.length >= 2) {
                            count++;
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td><strong>${cols[1] || cols[0]}</strong></td>
                                <td class="ap-mono">${cols[0]}</td>
                                <td><span class="ap-pill navy">${cols[2] || 'member'}</span></td>
                                <td><span class="ap-pill active"><span class="ap-pill-dot"></span> Ready</span></td>
                            `;
                            tbody.appendChild(tr);
                        }
                    }
                    document.getElementById('rowCount').textContent = count;
                    document.getElementById('previewCard').style.display = 'block';
                }
            };
            reader.readAsText(file);
        }
    </script>
</body>
</html>
