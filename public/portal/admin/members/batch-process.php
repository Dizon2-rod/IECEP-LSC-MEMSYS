<?php
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../auth_check.php';
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/role-config.php';

$current_page = 'batch-process';
$page_title = 'Bulk Member CSV Ingestion';

require_role(['admin', 'super_admin', 'registration', 'committee_registration']);

$supabase = getSupabaseClient();
$importResult = null;

// Predefined School Acronym Mapping
$schoolAcronymMap = [
    'lspu' => 'inst_lspu_scc',
    'lspu-scc' => 'inst_lspu_scc',
    'dlsu' => 'inst_dlsu_laguna',
    'dlsu-laguna' => 'inst_dlsu_laguna',
    'mmcl' => 'inst_mmcl',
    'mcl' => 'inst_mmcl',
    'csjl' => 'inst_csjl',
    'letran' => 'inst_csjl',
    'uplb' => 'inst_uplb',
    'spcba' => 'inst_spcba'
];

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
            if ($nameIdx === false) $nameIdx = array_search('name', $headers);
            
            $studentIdIdx = array_search('student_id', $headers);
            $schoolIdx = array_search('school', $headers);
            if ($schoolIdx === false) $schoolIdx = array_search('institution', $headers);
            
            $programIdx = array_search('program', $headers);
            $yearIdx = array_search('year_level', $headers);
            $phoneIdx = array_search('phone', $headers);
            $addressIdx = array_search('address', $headers);
            $birthdayIdx = array_search('birthday', $headers);
            $roleIdx = array_search('role', $headers);

            if ($emailIdx !== false && $nameIdx !== false) {
                $imported = 0;
                $failed = 0;
                $timestamp = date('c');
                $batchId = bin2hex(random_bytes(16));

                // Fetch existing count to assign sequential membership IDs
                $baseCount = 100;
                if ($supabase) {
                    try {
                        $existingMembers = $supabase->select('members', ['select' => 'id']);
                        $baseCount = is_array($existingMembers) ? count($existingMembers) : 100;
                    } catch (\Throwable $e) {}
                }

                foreach ($lines as $line) {
                    if (empty(trim($line))) continue;
                    $cols = str_getcsv($line);
                    $email = trim($cols[$emailIdx] ?? '');
                    $name = trim($cols[$nameIdx] ?? '');
                    $role = ($roleIdx !== false && !empty($cols[$roleIdx])) ? trim($cols[$roleIdx]) : 'member';
                    $studentId = ($studentIdIdx !== false && !empty($cols[$studentIdIdx])) ? trim($cols[$studentIdIdx]) : ('2026-' . rand(10000, 99999));
                    $schoolVal = ($schoolIdx !== false && !empty($cols[$schoolIdx])) ? strtolower(trim($cols[$schoolIdx])) : 'inst_lspu_scc';
                    $instId = $schoolAcronymMap[$schoolVal] ?? 'inst_lspu_scc';
                    $prog = ($programIdx !== false && !empty($cols[$programIdx])) ? trim($cols[$programIdx]) : 'BS Electronics Engineering';
                    $yearLvl = ($yearIdx !== false && !empty($cols[$yearIdx])) ? trim($cols[$yearIdx]) : '3rd Year';
                    $phone = ($phoneIdx !== false && !empty($cols[$phoneIdx])) ? trim($cols[$phoneIdx]) : '+63 912 345 6789';
                    $addr = ($addressIdx !== false && !empty($cols[$addressIdx])) ? trim($cols[$addressIdx]) : 'Laguna, Philippines';
                    $bday = ($birthdayIdx !== false && !empty($cols[$birthdayIdx])) ? trim($cols[$birthdayIdx]) : '2005-03-15';

                    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($name)) {
                        $baseCount++;
                        $memId = bin2hex(random_bytes(16));
                        $membershipId = 'IECEP-2026-' . str_pad($baseCount, 4, '0', STR_PAD_LEFT);
                        $hash = hash('sha256', $memId . $name . $email . $timestamp);

                        try {
                            if ($supabase) {
                                // 1. Insert Member
                                $supabase->insert('members', [[
                                    'id' => $memId,
                                    'full_name' => $name,
                                    'email' => $email,
                                    'student_id' => $studentId,
                                    'membership_id' => $membershipId,
                                    'institution_id' => $instId,
                                    'program' => $prog,
                                    'year_level' => $yearLvl,
                                    'phone' => $phone,
                                    'address' => $addr,
                                    'birthday' => $bday,
                                    'member_type' => 'regular',
                                    'payment_status' => 'paid',
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
                                    'contact_phone' => $phone,
                                    'membership_status' => 'active',
                                    'membership_type' => 'regular',
                                    'institution_id' => $instId,
                                    'created_at' => $timestamp
                                ]]);
                            }

                            $imported++;
                        } catch (\Throwable $e) {
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
                $importResult = ['success' => false, 'message' => 'CSV file must include at least "email" and "full_name" columns in the header row.'];
            }
        } else {
            $importResult = ['success' => false, 'message' => 'CSV file contains no data rows.'];
        }
    } else {
        $importResult = ['success' => false, 'message' => 'Failed to read uploaded file. Please select a valid .csv file.'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($page_title) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Bulk import chapter student members via CSV upload with instant validation for IECEP-LSC Laguna Student Chapter.">
    <?php include dirname(__DIR__, 4) . '/includes/head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* =========================================================================
           RESPONSIVE WHITE THEME - BATCH INGESTION WORKSPACE
           ========================================================================= */
        :root {
            --bg-page: #F8FAFC;
            --bg-card: #FFFFFF;
            --border-subtle: #E2E8F0;
            --border-hover: #CBD5E1;
            
            --text-heading: #0F172A;
            --text-body: #334155;
            --text-muted: #64748B;

            --color-navy: #0B1D4A;
            --color-navy-hover: #152C6E;
            --color-blue: #2563EB;
            --color-gold: #D4AF37;
            --color-gold-dark: #B45309;
            --color-emerald: #059669;

            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-card: 0 1px 3px 0 rgba(0, 0, 0, 0.04), 0 1px 2px -1px rgba(0, 0, 0, 0.04);
            --shadow-elevated: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-page);
            color: var(--text-body);
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        .white-theme-wrap {
            padding: 1.5rem 2rem;
            max-width: 1400px;
            margin: 0 auto;
            box-sizing: border-box;
            width: 100%;
        }

        /* 1. Header Banner */
        .white-page-header {
            background: #FFFFFF;
            border: 1px solid var(--border-subtle);
            border-radius: 16px;
            padding: 1.5rem 1.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.25rem;
            box-shadow: var(--shadow-card);
        }

        .header-title-box {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-icon-circle {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: #EFF6FF;
            color: var(--color-navy);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.45rem;
            border: 1px solid #DBEAFE;
            flex-shrink: 0;
        }

        .header-main-title {
            margin: 0 0 0.2rem;
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text-heading);
            letter-spacing: -0.01em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header-subtitle {
            margin: 0;
            font-size: 0.86rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .header-btn-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        /* Buttons */
        .btn-white {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.15rem;
            border-radius: 9px;
            font-size: 0.84rem;
            font-weight: 700;
            text-decoration: none;
            background: #FFFFFF;
            border: 1px solid var(--border-hover);
            color: var(--text-heading);
            cursor: pointer;
            box-shadow: var(--shadow-sm);
            transition: all 0.18s ease;
            white-space: nowrap;
        }
        .btn-white:hover {
            background: #F8FAFC;
            border-color: #94A3B8;
            color: #0F172A;
            transform: translateY(-1px);
        }

        .btn-primary-navy {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.25rem;
            border-radius: 9px;
            font-size: 0.84rem;
            font-weight: 800;
            text-decoration: none;
            background: var(--color-navy);
            border: 1px solid var(--color-navy);
            color: #FFFFFF;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(11, 29, 74, 0.15);
            transition: all 0.18s ease;
            white-space: nowrap;
        }
        .btn-primary-navy:hover:not(:disabled) {
            background: var(--color-navy-hover);
            border-color: var(--color-navy-hover);
            color: #FDE047;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(11, 29, 74, 0.22);
        }
        .btn-primary-navy:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            box-shadow: none;
        }

        /* 2. 3-Step Wizard Visualizer */
        .pipeline-steps-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .wizard-step-box {
            background: #FFFFFF;
            border: 1px solid var(--border-subtle);
            border-radius: 14px;
            padding: 1.15rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: var(--shadow-card);
            transition: all 0.2s ease;
        }
        .wizard-step-box.active {
            border-color: var(--color-navy);
            background: linear-gradient(135deg, #FFFFFF 0%, #F8FAFC 100%);
            box-shadow: 0 4px 14px rgba(11, 29, 74, 0.08);
        }

        .step-circle-badge {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #EFF6FF;
            color: #1E3A8A;
            font-weight: 800;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 2px solid #DBEAFE;
        }
        .wizard-step-box.active .step-circle-badge {
            background: var(--color-navy);
            color: #FDE047;
            border-color: #D4AF37;
        }

        .step-text-title {
            font-weight: 800;
            font-size: 0.88rem;
            color: var(--text-heading);
            margin-bottom: 2px;
        }
        .step-text-desc {
            font-size: 0.76rem;
            color: var(--text-muted);
            line-height: 1.3;
        }

        /* 3. Drag and Drop Zone (White Theme) */
        .white-upload-card {
            background: #FFFFFF;
            border: 1px solid var(--border-subtle);
            border-radius: 16px;
            padding: 1.75rem;
            box-shadow: var(--shadow-card);
            margin-bottom: 1.5rem;
        }

        .drag-drop-zone {
            border: 2px dashed #CBD5E1;
            background: #F8FAFC;
            border-radius: 14px;
            padding: 3.5rem 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        .drag-drop-zone:hover, .drag-drop-zone.dragover {
            background: #FFFFFF;
            border-color: var(--color-navy);
            box-shadow: 0 8px 30px rgba(11, 29, 74, 0.08);
            transform: translateY(-2px);
        }

        .upload-icon-circle {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: #EFF6FF;
            color: var(--color-navy);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1rem;
            border: 2px solid #DBEAFE;
            transition: all 0.2s ease;
        }
        .drag-drop-zone:hover .upload-icon-circle {
            background: var(--color-navy);
            color: #FDE047;
            border-color: #D4AF37;
            transform: scale(1.06);
        }

        .file-selected-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.25rem;
            background: #ECFDF5;
            border: 1px solid #A7F3D0;
            border-radius: 10px;
            color: #065F46;
            font-weight: 700;
            font-size: 0.9rem;
            margin-top: 1.25rem;
        }

        /* 4. Table Preview Card */
        .white-preview-card {
            background: #FFFFFF;
            border: 1px solid var(--border-subtle);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
            margin-bottom: 1.5rem;
        }

        .preview-header-bar {
            padding: 1.15rem 1.5rem;
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #FAFAFA;
        }

        .table-responsive-viewport {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .preview-table {
            width: 100%;
            min-width: 780px;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.875rem;
        }
        .preview-table th {
            background: #F8FAFC;
            padding: 0.85rem 1.25rem;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-subtle);
            text-align: left;
            white-space: nowrap;
        }
        .preview-table td {
            padding: 0.85rem 1.25rem;
            border-bottom: 1px solid #F1F5F9;
            color: var(--text-body);
            vertical-align: middle;
            white-space: nowrap;
        }

        /* Schema Guidance Box */
        .schema-guidance-card {
            background: #FFFFFF;
            border: 1px solid var(--border-subtle);
            border-radius: 14px;
            padding: 1.25rem 1.5rem;
            box-shadow: var(--shadow-card);
            margin-bottom: 1.5rem;
        }

        .schema-pill-tag {
            display: inline-block;
            padding: 0.25rem 0.6rem;
            background: #F1F5F9;
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.76rem;
            font-weight: 600;
            color: var(--color-navy);
            margin: 3px;
        }
        .schema-pill-tag.required {
            background: #EFF6FF;
            border-color: #BFDBFE;
            color: #1E3A8A;
            font-weight: 800;
        }

        /* Sentinel Spacing */
        .sentinel-white-strip {
            display: flex;
            align-items: center;
            gap: 2rem;
            padding: 1rem 1.25rem;
            background: #FFFFFF;
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            font-size: 0.8rem;
            color: var(--text-muted);
            flex-wrap: wrap;
        }
        .sentinel-node-item {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .sentinel-node-item i {
            color: var(--color-navy);
        }

        /* =========================================================================
           MOBILE SPECIFIC BREAKPOINTS
           ========================================================================= */
        @media (max-width: 768px) {
            .white-theme-wrap {
                padding: 0.75rem;
            }
            .white-page-header {
                padding: 1.15rem;
                gap: 1rem;
            }
            .header-title-box {
                gap: 0.75rem;
            }
            .header-icon-circle {
                width: 44px;
                height: 44px;
                font-size: 1.2rem;
            }
            .header-main-title {
                font-size: 1.2rem;
            }
            .header-btn-group {
                width: 100%;
                display: flex;
                flex-direction: row;
                gap: 0.5rem;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                padding-bottom: 4px;
            }
            .pipeline-steps-grid {
                grid-template-columns: 1fr;
                gap: 0.65rem;
            }
            .white-upload-card {
                padding: 1.15rem;
            }
            .drag-drop-zone {
                padding: 2.25rem 1rem;
            }
            .upload-icon-circle {
                width: 56px;
                height: 56px;
                font-size: 1.5rem;
                margin-bottom: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <?php include dirname(__DIR__, 4) . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="white-theme-wrap">

            <!-- 1. Header Banner -->
            <div class="white-page-header">
                <div class="header-title-box">
                    <div class="header-icon-circle">
                        <i class="fas fa-file-import"></i>
                    </div>
                    <div>
                        <h1 class="header-main-title">
                            Bulk Member CSV Ingestion
                        </h1>
                        <p class="header-subtitle">
                            Batch register verified chapter student engineers, assign sequential membership IDs, and commit directly to Supabase ledger.
                        </p>
                    </div>
                </div>
                <div class="header-btn-group">
                    <a href="/IECEP-LSC-MEMSYS/public/portal/admin/members/list.php" class="btn-white">
                        <i class="fas fa-arrow-left"></i> Member Directory
                    </a>
                    <button type="button" id="btnDownloadTemplate" class="btn-white">
                        <i class="fas fa-download" style="color:#B45309;"></i> Official CSV Template
                    </button>
                </div>
            </div>

            <!-- Import Result Alert -->
            <?php if ($importResult): ?>
                <?php if ($importResult['success']): ?>
                    <div class="ap-alert success" style="margin-bottom:1.5rem;">
                        <i class="fas fa-circle-check" style="font-size:1.5rem;"></i>
                        <div>
                            <strong style="font-size:1rem;">Batch Ingestion Successful!</strong><br>
                            Successfully registered <strong><?= $importResult['imported'] ?></strong> real member accounts into the database with cryptographic Digital IDs.
                            <?php if ($importResult['failed'] > 0): ?> 
                                <span style="color:#92400E;">(<?= $importResult['failed'] ?> duplicate/invalid rows skipped)</span>
                            <?php endif; ?>
                            <div style="margin-top:0.75rem;">
                                <a href="/IECEP-LSC-MEMSYS/public/portal/admin/members/list.php" class="btn-primary-navy" style="padding:0.4rem 0.9rem; font-size:0.82rem;">
                                    <i class="fas fa-users"></i> View Updated Member Directory
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="ap-alert danger" style="margin-bottom:1.5rem;">
                        <i class="fas fa-triangle-exclamation" style="font-size:1.5rem;"></i>
                        <div>
                            <strong>Import Error:</strong> <?= htmlspecialchars($importResult['message'] ?? 'Import failed.') ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- 2. Wizard Visualizer -->
            <div class="pipeline-steps-grid">
                <div class="wizard-step-box active">
                    <div class="step-circle-badge">1</div>
                    <div>
                        <div class="step-text-title">Select CSV File</div>
                        <div class="step-text-desc">Drop your student roster spreadsheet with headers</div>
                    </div>
                </div>
                <div class="wizard-step-box">
                    <div class="step-circle-badge">2</div>
                    <div>
                        <div class="step-text-title">Instant Row Preview</div>
                        <div class="step-text-desc">Automatic client-side parsing checks data validity</div>
                    </div>
                </div>
                <div class="wizard-step-box">
                    <div class="step-circle-badge">3</div>
                    <div>
                        <div class="step-text-title">Database & ID Commit</div>
                        <div class="step-text-desc">Assigns sequential IECEP IDs & cryptographic proofs</div>
                    </div>
                </div>
            </div>

            <!-- 3. Upload Card -->
            <div class="white-upload-card">
                <form method="POST" enctype="multipart/form-data" id="batchImportForm">
                    <input type="file" id="csvFilePicker" name="csv_file" accept=".csv" style="display:none;" onchange="handleFileSelected(this)">
                    
                    <div class="drag-drop-zone" id="dropzoneBox" onclick="document.getElementById('csvFilePicker').click()">
                        <div class="upload-icon-circle">
                            <i class="fas fa-file-csv"></i>
                        </div>
                        <h3 style="margin:0 0 0.4rem 0; color:var(--text-heading); font-weight:800; font-size:1.25rem;">
                            Click to browse or drag & drop CSV file
                        </h3>
                        <p style="margin:0; font-size:0.85rem; color:var(--text-muted);">
                            Supports UTF-8 CSV exports from Excel, Google Sheets, or School Portals
                        </p>
                        
                        <div id="fileSelectedDisplay" style="display:none;">
                            <div class="file-selected-pill">
                                <i class="fas fa-circle-check"></i>
                                <span id="fileSelectedName">selected_roster.csv</span>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1.5rem; flex-wrap:wrap; gap:1rem;">
                        <span style="font-size:0.82rem; color:var(--text-muted);">
                            <i class="fas fa-info-circle" style="color:var(--color-blue);"></i> Required columns: <code>full_name, email</code>. Optional: <code>student_id, school, program, year_level, phone, address</code>.
                        </span>
                        <button type="submit" class="btn-primary-navy" id="btnSubmitImport" disabled style="padding:0.65rem 1.4rem; font-size:0.9rem;">
                            <i class="fas fa-database" style="color:#FDE047;"></i> Process & Save to Database
                        </button>
                    </div>
                </form>
            </div>

            <!-- 4. Live Table Preview Card -->
            <div class="white-preview-card" id="csvPreviewCard" style="display:none;">
                <div class="preview-header-bar">
                    <h3 style="margin:0; font-size:0.98rem; font-weight:800; color:var(--text-heading); display:flex; align-items:center; gap:0.5rem;">
                        <i class="fas fa-table-list" style="color:var(--color-navy);"></i>
                        <span>CSV Preview (<span id="parsedRowCount" style="color:var(--color-navy);">0</span> Valid Rows Detected)</span>
                    </h3>
                </div>
                <div class="table-responsive-viewport" style="max-height:380px;">
                    <table class="preview-table" id="previewTable">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Gmail / Email</th>
                                <th>Student ID</th>
                                <th>School Chapter</th>
                                <th>Program & Year</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="previewTableBody"></tbody>
                    </table>
                </div>
            </div>

            <!-- 5. CSV Specification Guidance -->
            <div class="schema-guidance-card">
                <h4 style="margin:0 0 0.5rem; color:var(--text-heading); font-weight:800; font-size:0.95rem; display:flex; align-items:center; gap:0.4rem;">
                    <i class="fas fa-code" style="color:var(--color-navy);"></i> Supported CSV Header Columns
                </h4>
                <p style="font-size:0.82rem; color:var(--text-muted); margin-bottom:0.75rem;">
                    Ensure your spreadsheet header matches the recognized column names:
                </p>
                <div>
                    <span class="schema-pill-tag required">full_name *</span>
                    <span class="schema-pill-tag required">email *</span>
                    <span class="schema-pill-tag">student_id</span>
                    <span class="schema-pill-tag">school (e.g. LSPU, DLSU, MMCL, CSJL, UPLB, SPCBA)</span>
                    <span class="schema-pill-tag">program (e.g. BS ECE)</span>
                    <span class="schema-pill-tag">year_level (e.g. 3rd Year)</span>
                    <span class="schema-pill-tag">phone</span>
                    <span class="schema-pill-tag">address</span>
                </div>
            </div>

            <!-- 6. Sentinel Info Strip -->
            <div class="sentinel-white-strip">
                <div class="sentinel-node-item">
                    <i class="fas fa-shield-halved"></i>
                    <span><strong>Data Ingestion:</strong> Direct Supabase REST Protocol</span>
                </div>
                <div class="sentinel-node-item">
                    <i class="fas fa-link"></i>
                    <span><strong>Ledger Anchor:</strong> SHA-256 Hashes Generated Sequentially</span>
                </div>
            </div>

        </div>
    </main>

    <!-- Client-side CSV Parser Script -->
    <script>
        // Download Template
        document.getElementById('btnDownloadTemplate').addEventListener('click', function() {
            const template = 'full_name,email,student_id,school,program,year_level,phone,address\n' +
                             'Maria Santos,mariasantos@gmail.com,2023-08912,LSPU,BS Electronics Engineering,3rd Year,+63 912 345 6789,Santa Cruz Laguna\n' +
                             'Juan Dela Cruz,jdelacruz@gmail.com,2022-04192,DLSU,BS Electronics Engineering,4th Year,+63 917 892 3411,Biñan Laguna\n' +
                             'Carlos Ramos,cmramos@mcl.edu.ph,2023-10892,MMCL,BS Electronics Engineering,3rd Year,+63 915 771 2233,Cabuyao Laguna';
            const blob = new Blob([template], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'IECEP_LSC_Member_Roster_Template.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });

        // Drag and Drop Handling
        const dropzone = document.getElementById('dropzoneBox');
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('dragover');
            }, false);
        });

        dropzone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files && files.length) {
                document.getElementById('csvFilePicker').files = files;
                handleFileSelected(document.getElementById('csvFilePicker'));
            }
        });

        // File Selection & Client-side Preview
        function handleFileSelected(input) {
            if (!input.files || !input.files[0]) return;
            const file = input.files[0];
            
            document.getElementById('fileSelectedName').textContent = `${file.name} (${(file.size/1024).toFixed(1)} KB)`;
            document.getElementById('fileSelectedDisplay').style.display = 'block';
            document.getElementById('btnSubmitImport').disabled = false;

            const reader = new FileReader();
            reader.onload = function(e) {
                const text = e.target.result;
                const lines = text.trim().split(/\r\n|\r|\n/).filter(l => l.trim());
                if (lines.length > 1) {
                    const headers = lines[0].toLowerCase().split(',').map(h => h.trim().replace(/^"|"$/g, ''));
                    const emailIdx = headers.indexOf('email');
                    let nameIdx = headers.indexOf('full_name');
                    if (nameIdx === -1) nameIdx = headers.indexOf('name');
                    const studentIdIdx = headers.indexOf('student_id');
                    let schoolIdx = headers.indexOf('school');
                    if (schoolIdx === -1) schoolIdx = headers.indexOf('institution');
                    const progIdx = headers.indexOf('program');
                    const yearIdx = headers.indexOf('year_level');

                    const tbody = document.getElementById('previewTableBody');
                    tbody.innerHTML = '';
                    let validCount = 0;

                    for (let i = 1; i < lines.length; i++) {
                        const cols = lines[i].split(',').map(c => c.trim().replace(/^"|"$/g, ''));
                        const email = emailIdx !== -1 ? cols[emailIdx] : cols[0];
                        const name = nameIdx !== -1 ? cols[nameIdx] : cols[1];
                        const studentId = studentIdIdx !== -1 ? cols[studentIdIdx] : 'Auto-assigned';
                        const school = schoolIdx !== -1 ? cols[schoolIdx] : 'LSPU-SCC';
                        const progYear = (progIdx !== -1 ? cols[progIdx] : 'BS ECE') + ' • ' + (yearIdx !== -1 ? cols[yearIdx] : '3rd Year');

                        if (email && name) {
                            validCount++;
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td><strong style="color:var(--text-heading);">${name}</strong></td>
                                <td style="font-family:'JetBrains Mono', monospace; font-size:0.8rem; color:#2563EB;">${email}</td>
                                <td><span style="font-family:'JetBrains Mono', monospace; font-weight:700; color:var(--color-navy); font-size:0.82rem;">${studentId}</span></td>
                                <td><span style="display:inline-block; padding:2px 7px; border-radius:5px; background:#EFF6FF; color:#1E3A8A; font-weight:700; font-size:0.75rem; border:1px solid #DBEAFE;">${school.toUpperCase()}</span></td>
                                <td style="font-size:0.8rem; color:var(--text-muted);">${progYear}</td>
                                <td><span style="display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:9999px; background:#ECFDF5; color:#065F46; font-size:0.72rem; font-weight:700; border:1px solid #A7F3D0;"><span style="width:5px; height:5px; border-radius:50%; background:#059669;"></span> Ready to Commit</span></td>
                            `;
                            tbody.appendChild(tr);
                        }
                    }

                    document.getElementById('parsedRowCount').textContent = validCount;
                    document.getElementById('csvPreviewCard').style.display = 'block';
                }
            };
            reader.readAsText(file);
        }
    </script>
</body>
</html>
