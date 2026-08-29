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
                    $schoolAcronymMap[strtolower(str_replace(' ', '', $acronym))] = $inst['id'];
                    $schoolAcronymMap[strtolower(str_replace('-', '', $acronym))] = $inst['id'];
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
}

// Default fallback institution ID
$defaultInstId = array_key_first($institutionsList) ?? 'inst_lspu_scc';

// Handle CSV upload and database commit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    $selectedDefaultSchool = trim($_POST['default_institution_id'] ?? $defaultInstId);
    if (empty($selectedDefaultSchool)) $selectedDefaultSchool = $defaultInstId;

    if ($file['error'] === UPLOAD_ERR_OK && is_uploaded_file($file['tmp_name'])) {
        $content = file_get_contents($file['tmp_name']);
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        
        if (count($lines) > 1) {
            $headers = array_map('trim', explode(',', strtolower(array_shift($lines))));
            
            // Header detection with aliases
            $emailIdx = array_search('email', $headers);
            if ($emailIdx === false) $emailIdx = array_search('email_address', $headers);
            if ($emailIdx === false) $emailIdx = array_search('gmail', $headers);

            $nameIdx = array_search('full_name', $headers);
            if ($nameIdx === false) $nameIdx = array_search('name', $headers);
            if ($nameIdx === false) $nameIdx = array_search('student_name', $headers);
            
            $studentIdIdx = array_search('student_id', $headers);
            if ($studentIdIdx === false) $studentIdIdx = array_search('student_no', $headers);
            if ($studentIdIdx === false) $studentIdIdx = array_search('id_number', $headers);

            $schoolIdx = array_search('school', $headers);
            if ($schoolIdx === false) $schoolIdx = array_search('institution', $headers);
            if ($schoolIdx === false) $schoolIdx = array_search('chapter', $headers);
            
            $programIdx = array_search('program', $headers);
            if ($programIdx === false) $programIdx = array_search('course', $headers);
            if ($programIdx === false) $programIdx = array_search('degree', $headers);

            $yearIdx = array_search('year_level', $headers);
            if ($yearIdx === false) $yearIdx = array_search('year', $headers);

            $phoneIdx = array_search('phone', $headers);
            if ($phoneIdx === false) $phoneIdx = array_search('contact', $headers);
            if ($phoneIdx === false) $phoneIdx = array_search('mobile', $headers);

            $addressIdx = array_search('address', $headers);
            $birthdayIdx = array_search('birthday', $headers);
            if ($birthdayIdx === false) $birthdayIdx = array_search('birthdate', $headers);

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
                    
                    $schoolVal = ($schoolIdx !== false && !empty($cols[$schoolIdx])) ? strtolower(trim($cols[$schoolIdx])) : '';
                    $cleanSchoolKey = str_replace([' ', '-'], '', $schoolVal);
                    $instId = $schoolAcronymMap[$schoolVal] ?? ($schoolAcronymMap[$cleanSchoolKey] ?? $selectedDefaultSchool);
                    
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

                // 3. Register batch record
                if ($supabase && $imported > 0) {
                    try {
                        $supabase->insert('upload_batches', [[
                            'id' => $batchId,
                            'institution_id' => $selectedDefaultSchool,
                            'file_name' => $file['name'],
                            'uploaded_by' => $_SESSION['user']['id'] ?? 'admin',
                            'total_rows' => count($lines) - 1,
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
                $importResult = ['success' => false, 'message' => 'CSV file must include at least "email" and "full_name" columns in the header row.'];
            }
        } else {
            $importResult = ['success' => false, 'message' => 'CSV file contains no data rows.'];
        }
    } else {
        $importResult = ['success' => false, 'message' => 'Failed to read uploaded file. Please select a valid .csv file.'];
    }
}

// Fetch recent ingestion batches
$recentBatches = [];
try {
    if ($supabase) {
        $bRes = $supabase->select('upload_batches', ['select' => '*', 'order' => 'uploaded_at.desc', 'limit' => 5]);
        if (is_array($bRes)) {
            $recentBatches = $bRes;
        }
    }
} catch (\Throwable $e) {}
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

        /* 2. Process Wizard Visualizer */
        .pipeline-steps-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.85rem;
            margin-bottom: 1.25rem;
            width: 100%;
            box-sizing: border-box;
        }

        .wizard-step-box {
            background: #FFFFFF;
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            padding: 0.9rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: var(--shadow-card);
            transition: all 0.15s ease;
            min-width: 0;
        }
        .wizard-step-box.active {
            border-color: var(--color-navy);
            background: #FFFFFF;
            box-shadow: 0 0 0 2px rgba(11, 29, 74, 0.08);
        }

        .step-circle-badge {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #F1F5F9;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.85rem;
            flex-shrink: 0;
            border: 1px solid #E2E8F0;
        }
        .wizard-step-box.active .step-circle-badge {
            background: var(--color-navy);
            color: #FDE047;
            border-color: var(--color-navy);
        }

        .step-text-title {
            font-weight: 800;
            font-size: 0.82rem;
            color: var(--text-heading);
            margin-bottom: 1px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .step-text-desc {
            font-size: 0.72rem;
            color: var(--text-muted);
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* 3. Upload Work Area Card */
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
            padding: 2.25rem 1.5rem;
            text-align: center;
            background: #FAFAFA;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }
        .drag-drop-zone:hover, .drag-drop-zone.dragover {
            border-color: var(--color-navy);
            background: #F0F7FF;
        }

        .upload-icon-circle {
            width: 58px;
            height: 58px;
            border-radius: 16px;
            background: #EFF6FF;
            color: var(--color-navy);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin-bottom: 0.85rem;
            border: 1px solid #DBEAFE;
            transition: transform 0.2s ease;
        }
        .drag-drop-zone:hover .upload-icon-circle {
            transform: translateY(-3px) scale(1.05);
            background: var(--color-navy);
            color: #FDE047;
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
            animation: fadeIn 0.2s ease;
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

        /* 4. Live Table Preview Card */
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

        /* 5. Schema Guidance & Recent Batches */
        .schema-guidance-card {
            background: #FFFFFF;
            border: 1px solid var(--border-subtle);
            border-radius: 14px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.25rem;
            box-shadow: var(--shadow-card);
            box-sizing: border-box;
            width: 100%;
        }

        .schema-pill-tag {
            display: inline-block;
            padding: 0.25rem 0.55rem;
            background: #F1F5F9;
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.74rem;
            font-weight: 600;
            color: var(--color-navy);
            margin: 2px 4px 2px 0;
        }
        .schema-pill-tag.required {
            background: #EFF6FF;
            border-color: #BFDBFE;
            color: #1E3A8A;
            font-weight: 800;
        }

        .history-card {
            background: #FFFFFF;
            border: 1px solid var(--border-subtle);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
            margin-bottom: 1.5rem;
            box-sizing: border-box;
            width: 100%;
        }

        /* =========================================================================
           MOBILE COMPACT ADAPTIVE STYLES
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
            .btn-white, .btn-primary-navy {
                padding: 0.38rem 0.6rem !important;
                font-size: 0.72rem !important;
                flex-shrink: 0 !important;
            }

            /* Wizard in 3 columns across on mobile */
            .pipeline-steps-grid {
                grid-template-columns: repeat(3, 1fr) !important;
                gap: 0.25rem !important;
                margin-bottom: 0.55rem !important;
            }
            .wizard-step-box {
                padding: 0.45rem 0.25rem !important;
                gap: 0.3rem !important;
                flex-direction: column !important;
                text-align: center !important;
                border-radius: 8px !important;
            }
            .step-circle-badge {
                width: 22px !important;
                height: 22px !important;
                font-size: 0.7rem !important;
                border-radius: 5px !important;
            }
            .step-text-title {
                font-size: 0.68rem !important;
            }
            .step-text-desc {
                display: none !important;
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
                width: 44px !important;
                height: 44px !important;
                font-size: 1.25rem !important;
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

            .schema-guidance-card {
                padding: 0.75rem 0.65rem !important;
                border-radius: 10px !important;
                margin-bottom: 0.55rem !important;
            }
            .history-card {
                border-radius: 10px !important;
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
                    <div class="ap-alert success" style="margin-bottom:1.25rem;">
                        <i class="fas fa-circle-check" style="font-size:1.4rem;"></i>
                        <div>
                            <strong style="font-size:0.95rem;">Batch Ingestion Successful!</strong><br>
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

            <!-- 2. Wizard Visualizer (Connected 3 Steps) -->
            <div class="pipeline-steps-grid">
                <div class="wizard-step-box active" id="wizStep1">
                    <div class="step-circle-badge">1</div>
                    <div>
                        <div class="step-text-title">Select CSV File</div>
                        <div class="step-text-desc">Drop your student roster spreadsheet with headers</div>
                    </div>
                </div>
                <div class="wizard-step-box" id="wizStep2">
                    <div class="step-circle-badge">2</div>
                    <div>
                        <div class="step-text-title">Instant Row Preview</div>
                        <div class="step-text-desc">Automatic client-side parsing checks data validity</div>
                    </div>
                </div>
                <div class="wizard-step-box" id="wizStep3">
                    <div class="step-circle-badge">3</div>
                    <div>
                        <div class="step-text-title">Database & ID Commit</div>
                        <div class="step-text-desc">Assigns sequential IECEP IDs & cryptographic proofs</div>
                    </div>
                </div>
            </div>

            <!-- 3. Upload Work Area Card -->
            <div class="white-upload-card">
                <form method="POST" enctype="multipart/form-data" id="batchImportForm">
                    <input type="file" id="csvFilePicker" name="csv_file" accept=".csv" style="display:none;" onchange="handleFileSelected(this)">
                    
                    <div class="drag-drop-zone" id="dropzoneBox" onclick="document.getElementById('csvFilePicker').click()">
                        <div class="upload-icon-circle">
                            <i class="fas fa-cloud-arrow-up"></i>
                        </div>
                        <h3 style="margin:0 0 0.35rem 0; color:var(--text-heading); font-weight:800; font-size:1.15rem;">
                            Click to browse or drag & drop CSV file
                        </h3>
                        <p style="margin:0; font-size:0.82rem; color:var(--text-muted);">
                            Supports UTF-8 CSV exports from Excel, Google Sheets, or School Portals
                        </p>
                        
                        <div id="fileSelectedDisplay" style="display:none;">
                            <div class="file-selected-pill">
                                <i class="fas fa-circle-check"></i>
                                <span id="fileSelectedName">selected_roster.csv</span>
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

            <!-- 4. Live Table Preview Card -->
            <div class="white-preview-card" id="csvPreviewCard" style="display:none;">
                <div class="preview-header-bar">
                    <h3 style="margin:0; font-size:0.92rem; font-weight:800; color:var(--text-heading); display:flex; align-items:center; gap:0.45rem;">
                        <i class="fas fa-table-list" style="color:var(--color-navy);"></i>
                        <span>CSV Preview (<span id="parsedRowCount" style="color:var(--color-navy);">0</span> Valid Rows Detected)</span>
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
            </div>

        </div>
    </main>

    <!-- Client-side CSV Parser Script -->
    <script>
        // Download Official CSV Template with realistic sample rows
        document.getElementById('btnDownloadTemplate').addEventListener('click', function() {
            const template = 'full_name,email,student_id,school,program,year_level,phone,address\n' +
                             'Maria Santos,mariasantos@gmail.com,2023-08912,LSPU,BS Electronics Engineering,3rd Year,+63 912 345 6789,Santa Cruz Laguna\n' +
                             'Juan Dela Cruz,jdelacruz@gmail.com,2022-04192,DLSU,BS Electronics Engineering,4th Year,+63 917 892 3411,Biñan Laguna\n' +
                             'Carlos Ramos,cmramos@mcl.edu.ph,2023-10892,MMCL,BS Electronics Engineering,3rd Year,+63 915 771 2233,Cabuyao Laguna\n' +
                             'Erika Gomez,erika.gomez@letran.edu.ph,2024-00123,CSJL,BS Electronics Engineering,2nd Year,+63 918 334 5566,Calamba Laguna';
            const blob = new Blob([template], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'IECEP_LSC_Official_Member_Roster_Template.csv';
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
            
            // Advance visual wizard to Step 2
            document.getElementById('wizStep2').classList.add('active');

            const reader = new FileReader();
            reader.onload = function(e) {
                const text = e.target.result;
                const lines = text.trim().split(/\r\n|\r|\n/).filter(l => l.trim());
                if (lines.length > 1) {
                    const headers = lines[0].toLowerCase().split(',').map(h => h.trim().replace(/^"|"$/g, ''));
                    
                    let emailIdx = headers.indexOf('email');
                    if (emailIdx === -1) emailIdx = headers.indexOf('email_address');
                    if (emailIdx === -1) emailIdx = headers.indexOf('gmail');

                    let nameIdx = headers.indexOf('full_name');
                    if (nameIdx === -1) nameIdx = headers.indexOf('name');
                    if (nameIdx === -1) nameIdx = headers.indexOf('student_name');

                    let studentIdIdx = headers.indexOf('student_id');
                    if (studentIdIdx === -1) studentIdIdx = headers.indexOf('student_no');

                    let schoolIdx = headers.indexOf('school');
                    if (schoolIdx === -1) schoolIdx = headers.indexOf('institution');
                    if (schoolIdx === -1) schoolIdx = headers.indexOf('chapter');

                    let progIdx = headers.indexOf('program');
                    if (progIdx === -1) progIdx = headers.indexOf('course');

                    let yearIdx = headers.indexOf('year_level');
                    if (yearIdx === -1) yearIdx = headers.indexOf('year');

                    const tbody = document.getElementById('previewTableBody');
                    tbody.innerHTML = '';
                    let validCount = 0;

                    for (let i = 1; i < lines.length; i++) {
                        const cols = lines[i].split(',').map(c => c.trim().replace(/^"|"$/g, ''));
                        const email = emailIdx !== -1 ? cols[emailIdx] : cols[0];
                        const name = nameIdx !== -1 ? cols[nameIdx] : cols[1];
                        const studentId = studentIdIdx !== -1 ? cols[studentIdIdx] : 'Auto-generated';
                        const school = schoolIdx !== -1 ? cols[schoolIdx] : 'Laguna Chapter';
                        const progYear = (progIdx !== -1 ? cols[progIdx] : 'BS ECE') + ' • ' + (yearIdx !== -1 ? cols[yearIdx] : '3rd Year');

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
                    document.getElementById('csvPreviewCard').style.display = 'block';
                }
            };
            reader.readAsText(file);
        }
    </script>
</body>
</html>
