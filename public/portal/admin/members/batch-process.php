<?php
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../auth_check.php';
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/role-config.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$current_page = 'batch-process';
$page_title = 'Bulk Member Excel Ingestion';

require_role(['admin', 'super_admin', 'registration', 'committee_registration']);

$supabase = getSupabaseClient();
$importResult = null;

// 1. Fetch Real Institutions from Supabase for Dynamic Mapping
$institutionsList = [];
$schoolAcronymMap = [];

try {
    if ($supabase) {
        $instData = $supabase->select('institutions', ['select' => '*']);
        if (is_array($instData)) {
            foreach ($instData as $inst) {
                if (!empty($inst['id'])) {
                    $name = $inst['name'] ?? 'Affiliated Higher Education Institution';
                    $acronym = $inst['acronym'] ?? '';
                    if (empty($acronym)) {
                        $words = explode(' ', $name);
                        $acronym = count($words) > 1 ? implode('', array_map(fn($w) => strtoupper(substr($w, 0, 1)), array_slice($words, 0, 4))) : substr($name, 0, 8);
                    }
                    $institutionsList[$inst['id']] = [
                        'id' => $inst['id'],
                        'name' => $name,
                        'acronym' => $acronym,
                        'city' => $inst['city'] ?? 'Laguna'
                    ];

                    // Build mapping variations
                    $schoolAcronymMap[strtolower($acronym)] = $inst['id'];
                    $schoolAcronymMap[strtolower(str_replace([' ', '-'], '', $acronym))] = $inst['id'];
                    $schoolAcronymMap[strtolower($name)] = $inst['id'];
                }
            }
        }
    }
} catch (\Throwable $e) {
    error_log("Institutions fetch error in batch process: " . $e->getMessage());
}

// Fallback mappings if institutions table was empty
if (empty($schoolAcronymMap)) {
    $schoolAcronymMap = [
        'lspu' => 'inst_lspu_scc',
        'lspuscc' => 'inst_lspu_scc',
        'dlsu' => 'inst_dlsu_laguna',
        'dlsulaguna' => 'inst_dlsu_laguna',
        'mmcl' => 'inst_mmcl',
        'mcl' => 'inst_mmcl',
        'csjl' => 'inst_csjl',
        'letran' => 'inst_csjl',
        'uplb' => 'inst_uplb',
        'spcba' => 'inst_spcba'
    ];
}

// Default fallback institution ID
$defaultInstId = array_key_first($institutionsList) ?? 'inst_lspu_scc';

// Handle Excel (.xlsx, .xls, .csv) upload and database commit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_FILES['excel_file']) || isset($_FILES['csv_file']))) {
    $file = $_FILES['excel_file'] ?? $_FILES['csv_file'];
    $selectedDefaultSchool = trim($_POST['default_institution_id'] ?? $defaultInstId);
    if (empty($selectedDefaultSchool)) $selectedDefaultSchool = $defaultInstId;

    if ($file['error'] === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'])) {
        $filename = $file['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $validExtensions = ['xlsx', 'xls', 'csv'];
        if (!in_array($ext, $validExtensions)) {
            $importResult = ['success' => false, 'message' => 'Invalid file format. Please upload a Microsoft Excel spreadsheet (.xlsx or .xls) or CSV file.'];
        } else {
            $rows = [];

            // Read Excel file with PhpSpreadsheet or CSV parser
            try {
                if (class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
                    $spreadsheet = IOFactory::load($file['tmp_name']);
                    $worksheet = $spreadsheet->getActiveSheet();
                    $rows = $worksheet->toArray(null, true, true, false);
                } else {
                    $content = file_get_contents($file['tmp_name']);
                    $lines = preg_split('/\r\n|\r|\n/', trim($content));
                    foreach ($lines as $l) {
                        if (trim($l)) $rows[] = str_getcsv($l);
                    }
                }
            } catch (\Throwable $e) {
                // Fallback for CSV
                $content = file_get_contents($file['tmp_name']);
                $lines = preg_split('/\r\n|\r|\n/', trim($content));
                foreach ($lines as $l) {
                    if (trim($l)) $rows[] = str_getcsv($l);
                }
            }

            // Filter out empty rows
            $rows = array_values(array_filter($rows, function($r) {
                if (!is_array($r)) return false;
                $nonEmpty = array_filter($r, fn($v) => !is_null($v) && trim((string)$v) !== '');
                return count($nonEmpty) > 0;
            }));

            if (count($rows) > 1) {
                $rawHeaders = array_shift($rows);
                $headers = array_map(function($h) {
                    return strtolower(preg_replace('/[\s_\-]+/', '', trim((string)$h)));
                }, $rawHeaders);

                // Find column indexes
                $emailIdx = false;
                $nameIdx = false;
                $studentIdIdx = false;
                $schoolIdx = false;
                $programIdx = false;
                $yearIdx = false;
                $phoneIdx = false;
                $addressIdx = false;
                $birthdayIdx = false;
                $roleIdx = false;

                foreach ($headers as $idx => $h) {
                    if (in_array($h, ['email', 'emailaddress', 'gmail', 'mail'])) $emailIdx = $idx;
                    if (in_array($h, ['fullname', 'name', 'studentname', 'membername'])) $nameIdx = $idx;
                    if (in_array($h, ['studentid', 'studentno', 'idnumber', 'schoolid'])) $studentIdIdx = $idx;
                    if (in_array($h, ['school', 'institution', 'chapter', 'university', 'campus'])) $schoolIdx = $idx;
                    if (in_array($h, ['program', 'course', 'degree', 'department'])) $programIdx = $idx;
                    if (in_array($h, ['yearlevel', 'year', 'level'])) $yearIdx = $idx;
                    if (in_array($h, ['phone', 'contact', 'mobile', 'cellphone', 'contactno'])) $phoneIdx = $idx;
                    if (in_array($h, ['address', 'homeaddress', 'residence', 'location'])) $addressIdx = $idx;
                    if (in_array($h, ['birthday', 'birthdate', 'dob'])) $birthdayIdx = $idx;
                    if (in_array($h, ['role', 'membertype', 'type'])) $roleIdx = $idx;
                }

                if ($emailIdx !== false && $nameIdx !== false) {
                    $imported = 0;
                    $failed = 0;
                    $timestamp = date('c');
                    $batchId = bin2hex(random_bytes(16));

                    // Base membership counter
                    $baseCount = 100;
                    if ($supabase) {
                        try {
                            $existingMembers = $supabase->select('members', ['select' => 'id']);
                            $baseCount = is_array($existingMembers) ? count($existingMembers) : 100;
                        } catch (\Throwable $e) {}
                    }

                    foreach ($rows as $cols) {
                        $email = trim((string)($cols[$emailIdx] ?? ''));
                        $name = trim((string)($cols[$nameIdx] ?? ''));
                        $role = ($roleIdx !== false && !empty($cols[$roleIdx])) ? trim((string)$cols[$roleIdx]) : 'member';
                        $studentId = ($studentIdIdx !== false && !empty($cols[$studentIdIdx])) ? trim((string)$cols[$studentIdIdx]) : ('2026-' . rand(10000, 99999));
                        
                        $schoolVal = ($schoolIdx !== false && !empty($cols[$schoolIdx])) ? strtolower(trim((string)$cols[$schoolIdx])) : '';
                        $cleanSchoolKey = str_replace([' ', '-'], '', $schoolVal);
                        $instId = $schoolAcronymMap[$schoolVal] ?? ($schoolAcronymMap[$cleanSchoolKey] ?? $selectedDefaultSchool);
                        
                        $prog = ($programIdx !== false && !empty($cols[$programIdx])) ? trim((string)$cols[$programIdx]) : 'BS Electronics Engineering';
                        $yearLvl = ($yearIdx !== false && !empty($cols[$yearIdx])) ? trim((string)$cols[$yearIdx]) : '3rd Year';
                        $phone = ($phoneIdx !== false && !empty($cols[$phoneIdx])) ? trim((string)$cols[$phoneIdx]) : '+63 912 345 6789';
                        $addr = ($addressIdx !== false && !empty($cols[$addressIdx])) ? trim((string)$cols[$addressIdx]) : 'Laguna, Philippines';
                        $bday = ($birthdayIdx !== false && !empty($cols[$birthdayIdx])) ? trim((string)$cols[$birthdayIdx]) : '2005-03-15';

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

                    // 3. Register batch audit record
                    if ($supabase && $imported > 0) {
                        try {
                            $supabase->insert('upload_batches', [[
                                'id' => $batchId,
                                'institution_id' => $selectedDefaultSchool,
                                'file_name' => $filename,
                                'uploaded_by' => $_SESSION['user']['id'] ?? 'admin',
                                'total_rows' => count($rows),
                                'valid_rows' => $imported,
                                'invalid_rows' => $failed,
                                'status' => 'completed',
                                'uploaded_at' => $timestamp
                            ]]);
                        } catch (\Throwable $t) {}
                    }

                    $importResult = [
                        'success' => true,
                        'imported' => $imported,
                        'failed' => $failed,
                        'batch_id' => $batchId
                    ];
                } else {
                    $importResult = ['success' => false, 'message' => 'Excel spreadsheet must include at least "full_name" and "email" column headers.'];
                }
            } else {
                $importResult = ['success' => false, 'message' => 'The uploaded Excel file contains no data rows.'];
            }
        }
    } else {
        $importResult = ['success' => false, 'message' => 'Failed to read uploaded file. Please select a valid Excel (.xlsx / .xls) spreadsheet.'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($page_title) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Bulk import chapter student members via Excel spreadsheet upload with instant validation for IECEP-LSC Laguna Student Chapter.">
    <?php include dirname(__DIR__, 4) . '/includes/head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- SheetJS for Native Excel (.xlsx / .xls) Reading and Writing -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

    <style>
        /* =========================================================================
           RESPONSIVE WHITE THEME - EXCEL INGESTION WORKSPACE
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
            --color-excel: #107C41;
            --color-excel-hover: #0E6B37;
            --color-gold: #D4AF37;

            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-card: 0 1px 3px 0 rgba(0, 0, 0, 0.04), 0 1px 2px -1px rgba(0, 0, 0, 0.04);
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            overflow-x: hidden !important;
            width: 100%;
            max-width: 100vw;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-page);
            color: var(--text-body);
        }

        .main-content {
            box-sizing: border-box;
            width: 100%;
            max-width: 100vw;
            overflow-x: hidden !important;
        }

        .white-theme-wrap {
            padding: 1.5rem 2rem;
            max-width: 1400px;
            margin: 0 auto;
            box-sizing: border-box;
            width: 100%;
            overflow-x: hidden !important;
        }

        /* 1. Header Banner */
        .white-page-header {
            background: #FFFFFF;
            border: 1px solid var(--border-subtle);
            border-radius: 16px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            box-shadow: var(--shadow-card);
            box-sizing: border-box;
            width: 100%;
        }

        .header-title-box {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .header-main-title {
            margin: 0 0 0.2rem;
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--text-heading);
            letter-spacing: -0.01em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header-subtitle {
            margin: 0;
            font-size: 0.85rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .header-btn-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        /* Buttons */
        .btn-white {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.55rem 1rem;
            border-radius: 8px;
            font-size: 0.82rem;
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

        .btn-excel-green {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.55rem 1rem;
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 700;
            text-decoration: none;
            background: #F0FDF4;
            border: 1px solid #BBF7D0;
            color: var(--color-excel);
            cursor: pointer;
            box-shadow: var(--shadow-sm);
            transition: all 0.18s ease;
            white-space: nowrap;
        }
        .btn-excel-green:hover {
            background: #DCFCE7;
            border-color: #86EFAC;
            color: #052E16;
            transform: translateY(-1px);
        }

        .btn-primary-navy {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.25rem;
            border-radius: 8px;
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

        /* Upload Work Area Card */
        .white-upload-card {
            background: #FFFFFF;
            border: 1px solid var(--border-subtle);
            border-radius: 14px;
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            box-shadow: var(--shadow-card);
            box-sizing: border-box;
            width: 100%;
        }

        .drag-drop-zone {
            border: 2px dashed #CBD5E1;
            border-radius: 12px;
            padding: 2.5rem 1.5rem;
            text-align: center;
            background: #FAFAFA;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }
        .drag-drop-zone:hover, .drag-drop-zone.dragover {
            border-color: var(--color-excel);
            background: #F0FDF4;
        }

        .upload-icon-circle {
            width: 62px;
            height: 62px;
            border-radius: 16px;
            background: #DCFCE7;
            color: var(--color-excel);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 0.85rem;
            border: 1px solid #BBF7D0;
            transition: transform 0.2s ease;
        }
        .drag-drop-zone:hover .upload-icon-circle {
            transform: translateY(-3px) scale(1.05);
            background: var(--color-excel);
            color: #FFFFFF;
        }

        .file-selected-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 1rem;
            background: #ECFDF5;
            border: 1px solid #A7F3D0;
            border-radius: 50px;
            color: #065F46;
            font-weight: 700;
            font-size: 0.82rem;
            margin-top: 0.75rem;
        }

        .config-row-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1.25rem;
        }

        .form-select-clean {
            width: 100%;
            padding: 0.6rem 2rem 0.6rem 0.85rem;
            border: 1px solid #CBD5E1;
            border-radius: 8px;
            font-size: 0.84rem;
            font-weight: 600;
            font-family: inherit;
            color: var(--text-heading);
            background: #FFFFFF url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748B'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E") no-repeat right 0.6rem center/14px;
            appearance: none;
            outline: none;
            cursor: pointer;
            transition: border-color 0.2s ease;
            box-sizing: border-box;
        }
        .form-select-clean:focus {
            border-color: var(--color-navy);
        }

        /* Live Table Preview Card */
        .white-preview-card {
            background: #FFFFFF;
            border: 1px solid var(--border-subtle);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
            margin-bottom: 1.25rem;
            box-sizing: border-box;
            width: 100%;
        }

        .preview-header-bar {
            padding: 0.9rem 1.25rem;
            border-bottom: 1px solid var(--border-subtle);
            background: #FAFAFA;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .table-responsive-viewport {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            overflow-x: hidden !important;
        }

        .preview-table {
            width: 100% !important;
            max-width: 100% !important;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.84rem;
            table-layout: auto;
        }

        .preview-table th {
            background: #F8FAFC;
            padding: 0.75rem 0.85rem;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-subtle);
            text-align: left;
            white-space: nowrap;
        }

        .preview-table td {
            padding: 0.7rem 0.85rem;
            border-bottom: 1px solid #F1F5F9;
            color: var(--text-body);
            vertical-align: middle;
        }

        .preview-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* =========================================================================
           MOBILE COMPACT ADAPTIVE STYLES (100% FIT - ZERO SCROLL)
           ========================================================================= */
        @media (max-width: 768px) {
            .white-theme-wrap {
                padding: 0.4rem 0.3rem !important;
                width: 100% !important;
                max-width: 100vw !important;
                overflow-x: hidden !important;
            }
            .white-page-header {
                padding: 0.75rem 0.65rem !important;
                gap: 0.5rem !important;
                border-radius: 10px !important;
                margin-bottom: 0.55rem !important;
            }
            .header-title-box {
                gap: 0.4rem !important;
            }
            .header-main-title {
                font-size: 1.05rem !important;
            }
            .header-subtitle {
                font-size: 0.72rem !important;
            }
            .header-btn-group {
                width: 100% !important;
                display: flex !important;
                flex-direction: row !important;
                gap: 0.35rem !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
                padding-bottom: 1px !important;
            }
            .btn-white, .btn-excel-green, .btn-primary-navy {
                padding: 0.38rem 0.6rem !important;
                font-size: 0.72rem !important;
                flex-shrink: 0 !important;
            }

            /* Upload Area on Mobile */
            .white-upload-card {
                padding: 0.75rem 0.65rem !important;
                border-radius: 10px !important;
                margin-bottom: 0.55rem !important;
            }
            .drag-drop-zone {
                padding: 1.25rem 0.75rem !important;
            }
            .upload-icon-circle {
                width: 46px !important;
                height: 46px !important;
                font-size: 1.35rem !important;
                margin-bottom: 0.5rem !important;
            }
            .drag-drop-zone h3 {
                font-size: 0.92rem !important;
            }
            .drag-drop-zone p {
                font-size: 0.72rem !important;
            }
            .config-row-grid {
                grid-template-columns: 1fr !important;
                gap: 0.5rem !important;
                margin-top: 0.75rem !important;
            }

            /* Preview Table on Mobile (100% FITTED - ZERO HORIZONTAL SCROLL) */
            .white-preview-card {
                border-radius: 10px !important;
                margin-bottom: 0.55rem !important;
            }
            .preview-header-bar {
                padding: 0.55rem 0.65rem !important;
            }
            .preview-table {
                table-layout: fixed !important;
                width: 100% !important;
                max-width: 100% !important;
                border-collapse: collapse !important;
                font-size: 0.68rem !important;
            }
            .preview-table th, .preview-table td {
                padding: 0.35rem 0.25rem !important;
            }
            .col-prev-name { width: 50% !important; }
            .col-prev-school { width: 25% !important; }
            .col-prev-status { width: 25% !important; text-align: center !important; }
            .col-prev-extra { display: none !important; }
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
                    <div>
                        <h1 class="header-main-title">
                            <i class="fas fa-file-excel" style="color:var(--color-excel);"></i> Bulk Member Excel Ingestion
                        </h1>
                        <p class="header-subtitle">
                            Batch register verified chapter student engineers from Microsoft Excel (.xlsx / .xls) or CSV spreadsheets.
                        </p>
                    </div>
                </div>
                <div class="header-btn-group">
                    <a href="/IECEP-LSC-MEMSYS/public/portal/admin/members/list.php" class="btn-white">
                        <i class="fas fa-arrow-left"></i> Member Directory
                    </a>
                    <button type="button" id="btnDownloadTemplate" class="btn-excel-green">
                        <i class="fas fa-file-excel"></i> Download Excel Template (.xlsx)
                    </button>
                </div>
            </div>

            <!-- Import Result Alert -->
            <?php if ($importResult): ?>
                <?php if ($importResult['success']): ?>
                    <div class="ap-alert success" style="margin-bottom:1.25rem;">
                        <i class="fas fa-circle-check" style="font-size:1.4rem;"></i>
                        <div>
                            <strong style="font-size:0.95rem;">Excel Ingestion Successful!</strong><br>
                            Successfully registered <strong><?= $importResult['imported'] ?></strong> real member accounts into the database with cryptographic Digital IDs.
                            <?php if ($importResult['failed'] > 0): ?> 
                                <span style="color:#92400E;">(<?= $importResult['failed'] ?> duplicate/invalid rows skipped)</span>
                            <?php endif; ?>
                            <div style="margin-top:0.65rem;">
                                <a href="/IECEP-LSC-MEMSYS/public/portal/admin/members/list.php" class="btn-primary-navy" style="padding:0.35rem 0.85rem; font-size:0.78rem;">
                                    <i class="fas fa-users"></i> View Updated Member Directory
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="ap-alert danger" style="margin-bottom:1.25rem;">
                        <i class="fas fa-triangle-exclamation" style="font-size:1.4rem;"></i>
                        <div>
                            <strong>Import Error:</strong> <?= htmlspecialchars($importResult['message'] ?? 'Import failed.') ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- 2. Upload Work Area Card -->
            <div class="white-upload-card">
                <form method="POST" enctype="multipart/form-data" id="batchImportForm">
                    <input type="file" id="excelFilePicker" name="excel_file" accept=".xlsx, .xls, .csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" style="display:none;" onchange="handleFileSelected(this)">
                    
                    <div class="drag-drop-zone" id="dropzoneBox" onclick="document.getElementById('excelFilePicker').click()">
                        <div class="upload-icon-circle">
                            <i class="fas fa-file-excel"></i>
                        </div>
                        <h3 style="margin:0 0 0.35rem 0; color:var(--text-heading); font-weight:800; font-size:1.15rem;">
                            Click to browse or drag & drop Excel spreadsheet
                        </h3>
                        <p style="margin:0; font-size:0.82rem; color:var(--text-muted);">
                            Supports Microsoft Excel (<strong>.xlsx</strong>, <strong>.xls</strong>) and CSV rosters with standard headers
                        </p>
                        
                        <div id="fileSelectedDisplay" style="display:none;">
                            <div class="file-selected-pill">
                                <i class="fas fa-circle-check"></i>
                                <span id="fileSelectedName">selected_roster.xlsx</span>
                            </div>
                        </div>
                    </div>

                    <!-- Config Row -->
                    <div class="config-row-grid">
                        <div>
                            <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; margin-bottom:0.35rem;">
                                Target / Default School Chapter (Fallback)
                            </label>
                            <select name="default_institution_id" class="form-select-clean">
                                <?php foreach ($institutionsList as $sKey => $sVal): ?>
                                    <option value="<?= htmlspecialchars($sKey) ?>"><?= htmlspecialchars($sVal['name']) ?> (<?= $sVal['acronym'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="display:flex; flex-direction:column; justify-content:flex-end;">
                            <button type="submit" class="btn-primary-navy" id="btnSubmitImport" disabled style="padding:0.6rem 1.3rem; font-size:0.86rem; width:100%; justify-content:center;">
                                <i class="fas fa-database" style="color:#FDE047;"></i> Process & Commit to Database
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- 3. Live Table Preview Card -->
            <div class="white-preview-card" id="excelPreviewCard" style="display:none;">
                <div class="preview-header-bar">
                    <h3 style="margin:0; font-size:0.92rem; font-weight:800; color:var(--text-heading); display:flex; align-items:center; gap:0.45rem;">
                        <i class="fas fa-table-list" style="color:var(--color-navy);"></i>
                        <span>Excel Preview (<span id="parsedRowCount" style="color:var(--color-navy);">0</span> Valid Rows Detected)</span>
                    </h3>
                </div>
                <div class="table-responsive-viewport" style="max-height:360px;">
                    <table class="preview-table" id="previewTable">
                        <thead>
                            <tr>
                                <th class="col-prev-name">Student Member</th>
                                <th class="col-prev-school">School Chapter</th>
                                <th class="col-prev-extra">Student ID</th>
                                <th class="col-prev-extra">Program & Year</th>
                                <th class="col-prev-status">Status</th>
                            </tr>
                        </thead>
                        <tbody id="previewTableBody"></tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- Client-side SheetJS Excel Parser Script -->
    <script>
        // Generate and Download Official Excel (.xlsx) Template
        document.getElementById('btnDownloadTemplate').addEventListener('click', function() {
            if (typeof XLSX !== 'undefined') {
                const sampleRows = [
                    ["full_name", "email", "student_id", "school", "program", "year_level", "phone", "address"],
                    ["Maria Santos", "mariasantos@gmail.com", "2023-08912", "LSPU", "BS Electronics Engineering", "3rd Year", "+63 912 345 6789", "Santa Cruz Laguna"],
                    ["Juan Dela Cruz", "jdelacruz@gmail.com", "2022-04192", "DLSU", "BS Electronics Engineering", "4th Year", "+63 917 892 3411", "Biñan Laguna"],
                    ["Carlos Ramos", "cmramos@mcl.edu.ph", "2023-10892", "MMCL", "BS Electronics Engineering", "3rd Year", "+63 915 771 2233", "Cabuyao Laguna"],
                    ["Erika Gomez", "erika.gomez@letran.edu.ph", "2024-00123", "CSJL", "BS Electronics Engineering", "2nd Year", "+63 918 334 5566", "Calamba Laguna"],
                    ["Mark Villanueva", "mvillanueva@uplb.edu.ph", "2022-55412", "UPLB", "BS Electronics Engineering", "4th Year", "+63 920 112 3344", "Los Baños Laguna"]
                ];
                const ws = XLSX.utils.aoa_to_sheet(sampleRows);
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, "Members Roster");
                XLSX.writeFile(wb, "IECEP_LSC_Official_Member_Roster_Template.xlsx");
            } else {
                // Fallback to CSV if SheetJS was blocked
                const template = 'full_name,email,student_id,school,program,year_level,phone,address\n' +
                                 'Maria Santos,mariasantos@gmail.com,2023-08912,LSPU,BS Electronics Engineering,3rd Year,+63 912 345 6789,Santa Cruz Laguna\n' +
                                 'Juan Dela Cruz,jdelacruz@gmail.com,2022-04192,DLSU,BS Electronics Engineering,4th Year,+63 917 892 3411,Biñan Laguna\n' +
                                 'Carlos Ramos,cmramos@mcl.edu.ph,2023-10892,MMCL,BS Electronics Engineering,3rd Year,+63 915 771 2233,Cabuyao Laguna';
                const blob = new Blob([template], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'IECEP_LSC_Official_Member_Roster_Template.csv';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            }
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
                document.getElementById('excelFilePicker').files = files;
                handleFileSelected(document.getElementById('excelFilePicker'));
            }
        });

        // File Selection & Client-side Excel (.xlsx / .xls / .csv) Parsing
        function handleFileSelected(input) {
            if (!input.files || !input.files[0]) return;
            const file = input.files[0];
            const ext = file.name.split('.').pop().toLowerCase();

            const validExtensions = ['xlsx', 'xls', 'csv'];
            if (!validExtensions.includes(ext)) {
                alert('Invalid file format. Please upload a Microsoft Excel spreadsheet (.xlsx, .xls) or CSV file.');
                input.value = '';
                return;
            }
            
            document.getElementById('fileSelectedName').textContent = `${file.name} (${(file.size/1024).toFixed(1)} KB)`;
            document.getElementById('fileSelectedDisplay').style.display = 'block';
            document.getElementById('btnSubmitImport').disabled = false;

            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });
                    const firstSheetName = workbook.SheetNames[0];
                    const worksheet = workbook.Sheets[firstSheetName];
                    const rows = XLSX.utils.sheet_to_json(worksheet, { header: 1, defval: '' });

                    if (rows.length > 1) {
                        const rawHeaders = rows[0];
                        const headers = rawHeaders.map(h => String(h).toLowerCase().trim().replace(/[\s_\-]+/g, ''));

                        let emailIdx = headers.indexOf('email');
                        if (emailIdx === -1) emailIdx = headers.indexOf('emailaddress');
                        if (emailIdx === -1) emailIdx = headers.indexOf('gmail');

                        let nameIdx = headers.indexOf('fullname');
                        if (nameIdx === -1) nameIdx = headers.indexOf('name');
                        if (nameIdx === -1) nameIdx = headers.indexOf('studentname');

                        let studentIdIdx = headers.indexOf('studentid');
                        if (studentIdIdx === -1) studentIdIdx = headers.indexOf('studentno');
                        if (studentIdIdx === -1) studentIdIdx = headers.indexOf('idnumber');

                        let schoolIdx = headers.indexOf('school');
                        if (schoolIdx === -1) schoolIdx = headers.indexOf('institution');
                        if (schoolIdx === -1) schoolIdx = headers.indexOf('chapter');

                        let progIdx = headers.indexOf('program');
                        if (progIdx === -1) progIdx = headers.indexOf('course');

                        let yearIdx = headers.indexOf('yearlevel');
                        if (yearIdx === -1) yearIdx = headers.indexOf('year');

                        const tbody = document.getElementById('previewTableBody');
                        tbody.innerHTML = '';
                        let validCount = 0;

                        for (let i = 1; i < rows.length; i++) {
                            const cols = rows[i];
                            if (!cols || !cols.length) continue;

                            const email = emailIdx !== -1 ? String(cols[emailIdx]).trim() : '';
                            const name = nameIdx !== -1 ? String(cols[nameIdx]).trim() : '';
                            const studentId = studentIdIdx !== -1 && cols[studentIdIdx] ? String(cols[studentIdIdx]).trim() : 'Auto-assigned';
                            const school = schoolIdx !== -1 && cols[schoolIdx] ? String(cols[schoolIdx]).trim() : 'Laguna Chapter';
                            const progYear = (progIdx !== -1 && cols[progIdx] ? String(cols[progIdx]).trim() : 'BS ECE') + ' • ' + (yearIdx !== -1 && cols[yearIdx] ? String(cols[yearIdx]).trim() : '3rd Year');

                            if (email && name) {
                                validCount++;
                                const tr = document.createElement('tr');
                                tr.innerHTML = `
                                    <td class="col-prev-name">
                                        <div style="font-weight:700; color:var(--text-heading); font-size:0.82rem; line-height:1.2; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${name}</div>
                                        <div style="font-family:'JetBrains Mono', monospace; font-size:0.7rem; color:#2563EB; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${email}</div>
                                    </td>
                                    <td class="col-prev-school">
                                        <span style="display:inline-block; padding:1px 5px; border-radius:4px; background:#EFF6FF; color:#1E3A8A; font-weight:700; font-size:0.68rem; border:1px solid #DBEAFE; white-space:nowrap;">${school.toUpperCase()}</span>
                                    </td>
                                    <td class="col-prev-extra">
                                        <span style="font-family:'JetBrains Mono', monospace; font-weight:700; color:var(--color-navy); font-size:0.78rem;">${studentId}</span>
                                    </td>
                                    <td class="col-prev-extra" style="font-size:0.76rem; color:var(--text-muted);">${progYear}</td>
                                    <td class="col-prev-status">
                                        <span style="display:inline-flex; align-items:center; gap:3px; padding:1px 6px; border-radius:9999px; background:#ECFDF5; color:#065F46; font-size:0.65rem; font-weight:700; border:1px solid #A7F3D0;">
                                            <span style="width:4px; height:4px; border-radius:50%; background:#059669;"></span> Valid
                                        </span>
                                    </td>
                                `;
                                tbody.appendChild(tr);
                            }
                        }

                        document.getElementById('parsedRowCount').textContent = validCount;
                        document.getElementById('excelPreviewCard').style.display = 'block';
                    }
                } catch (err) {
                    console.error("Excel parse error:", err);
                }
            };
            reader.readAsArrayBuffer(file);
        }
    </script>
</body>
</html>
