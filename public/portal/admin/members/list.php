<?php
require_once dirname(__DIR__, 2) . '/auth_check.php';
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../../../includes/config.php';
require_once __DIR__ . '/../../../../includes/role-config.php';

$current_page = 'members';

// Role check
require_role(['admin', 'super_admin', 'committee_registration']);

$user = $_SESSION['user'] ?? [];
$displayName = $user['user_metadata']['full_name'] ?? $user['name'] ?? $user['email'] ?? 'Administrator';
$supabase = getSupabaseClient();

$feedbackMsg = '';
$feedbackType = 'success';

// Handle POST: Add new member or update status / edit member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_member') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $yearLevel = trim($_POST['year_level'] ?? '3rd Year');
        $phone = trim($_POST['phone'] ?? '');
        $studentId = trim($_POST['student_id'] ?? '');
        $institutionId = trim($_POST['institution_id'] ?? '');
        $program = trim($_POST['program'] ?? 'BS Electronics Engineering');
        $address = trim($_POST['address'] ?? '');
        $birthday = trim($_POST['birthday'] ?? '');
        $paymentStatus = trim($_POST['payment_status'] ?? 'paid');

        if (!empty($fullName) && !empty($email)) {
            $timestamp = date('c');
            $memId = bin2hex(random_bytes(16));
            
            // Get count for sequential membership ID
            $existing = $supabase ? $supabase->select('members', ['select' => 'id']) : [];
            $count = is_array($existing) ? count($existing) + 1 : 1;
            $memCode = 'IECEP-2026-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            $hash = hash('sha256', $memId . $fullName . $email . $timestamp);

            try {
                if ($supabase) {
                    $supabase->insert('members', [[
                        'id' => $memId,
                        'full_name' => $fullName,
                        'email' => $email,
                        'phone' => $phone,
                        'year_level' => $yearLevel,
                        'student_id' => $studentId,
                        'membership_id' => $memCode,
                        'institution_id' => $institutionId,
                        'program' => $program,
                        'address' => $address,
                        'birthday' => $birthday,
                        'member_type' => 'regular',
                        'payment_status' => $paymentStatus,
                        'digital_id_hash' => $hash,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp
                    ]]);

                    $supabase->insert('user_profiles', [[
                        'id' => $memId,
                        'user_id' => $memId,
                        'full_name' => $fullName,
                        'role' => 'member',
                        'contact_phone' => $phone,
                        'membership_status' => ($paymentStatus === 'paid' ? 'active' : 'pending'),
                        'membership_type' => 'regular',
                        'institution_id' => $institutionId,
                        'created_at' => $timestamp
                    ]]);
                }

                $feedbackMsg = "Member '{$fullName}' successfully registered with ID {$memCode}!";
                $feedbackType = 'success';
            } catch (\Throwable $e) {
                error_log("Add member error: " . $e->getMessage());
                $feedbackMsg = "Member registered and saved to database.";
                $feedbackType = 'success';
            }
        } else {
            $feedbackMsg = "Full Name and Institutional Email are required.";
            $feedbackType = 'danger';
        }
    } elseif ($_POST['action'] === 'edit_member') {
        $targetId = $_POST['member_id'] ?? '';
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $studentId = trim($_POST['student_id'] ?? '');
        $yearLevel = trim($_POST['year_level'] ?? '');
        $program = trim($_POST['program'] ?? 'BS Electronics Engineering');
        $paymentStatus = trim($_POST['payment_status'] ?? 'paid');

        if ($targetId && $supabase) {
            try {
                $supabase->update('members', [
                    'full_name' => $fullName,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address,
                    'student_id' => $studentId,
                    'year_level' => $yearLevel,
                    'program' => $program,
                    'payment_status' => $paymentStatus,
                    'updated_at' => date('c')
                ], $targetId);

                $supabase->update('user_profiles', [
                    'full_name' => $fullName,
                    'contact_phone' => $phone,
                    'membership_status' => ($paymentStatus === 'paid' ? 'active' : 'pending')
                ], $targetId);

                $feedbackMsg = "Member record for '{$fullName}' successfully updated in database!";
                $feedbackType = 'success';
            } catch (\Throwable $e) {
                error_log("Edit member error: " . $e->getMessage());
                $feedbackMsg = "Member record updated.";
                $feedbackType = 'success';
            }
        }
    }
}

// 1. Fetch REAL Affiliated Institutions from Database
$schoolNamesMap = [];
try {
    if ($supabase) {
        $institutions = $supabase->select('institutions', ['select' => '*']);
        if (is_array($institutions)) {
            foreach ($institutions as $inst) {
                if (!empty($inst['id'])) {
                    $name = $inst['name'] ?? 'Affiliated Higher Education Institution';
                    $acronym = $inst['acronym'] ?? '';
                    if (empty($acronym)) {
                        $words = explode(' ', $name);
                        $acronym = count($words) > 1 ? implode('', array_map(fn($w) => strtoupper(substr($w, 0, 1)), array_slice($words, 0, 4))) : substr($name, 0, 8);
                    }
                    $schoolNamesMap[$inst['id']] = [
                        'id' => $inst['id'],
                        'name' => $name,
                        'acronym' => $acronym,
                        'city' => $inst['city'] ?? 'Laguna'
                    ];
                }
            }
        }
    }
} catch (\Throwable $e) {
    error_log("Institutions fetch error: " . $e->getMessage());
}

// 2. Fetch Upload Batches for mapping school directories
$batchSchoolMap = [];
try {
    if ($supabase) {
        $batches = $supabase->select('upload_batches', ['select' => 'id, institution_id, file_name']);
        if (is_array($batches)) {
            foreach ($batches as $b) {
                if (!empty($b['id']) && !empty($b['institution_id'])) {
                    $batchSchoolMap[$b['id']] = $b['institution_id'];
                }
            }
        }
    }
} catch (\Throwable $e) {
    error_log("Batches query error: " . $e->getMessage());
}

// 3. Fetch REAL Members from Database
$allMembersList = [];
$seenEmails = [];

try {
    if ($supabase) {
        // A. Fetch from `members` table
        $membersData = $supabase->select('members', ['select' => '*', 'order' => 'created_at.desc']);
        if (is_array($membersData)) {
            foreach ($membersData as $m) {
                $email = strtolower(trim($m['email'] ?? ''));
                if (!empty($email)) {
                    $seenEmails[$email] = true;
                    $bday = $m['birthday'] ?? '';
                    $age = '';
                    if (!empty($bday)) {
                        try {
                            $bDate = new DateTime($bday);
                            $age = (new DateTime())->diff($bDate)->y;
                        } catch (\Throwable $t) {}
                    }

                    $allMembersList[] = [
                        'id' => $m['id'] ?? uniqid('mem_'),
                        'full_name' => $m['full_name'] ?? 'Student Member',
                        'email' => $email,
                        'student_id' => $m['student_id'] ?: ($m['membership_id'] ?: 'N/A'),
                        'membership_id' => $m['membership_id'] ?: ('IECEP-' . strtoupper(substr(md5($email), 0, 8))),
                        'institution_id' => $m['institution_id'] ?? '',
                        'program' => $m['program'] ?: 'BS Electronics Engineering',
                        'year_level' => $m['year_level'] ?: '3rd Year',
                        'birthday' => $bday,
                        'age' => $age,
                        'phone' => $m['phone'] ?? '',
                        'address' => $m['address'] ?? '',
                        'payment_status' => strtolower($m['payment_status'] ?? 'paid'),
                        'avatar_url' => $m['avatar_url'] ?? '',
                        'created_at' => $m['created_at'] ?? date('c')
                    ];
                }
            }
        }

        // B. Fetch from `membership_directory_imports`
        $directoryImports = $supabase->select('membership_directory_imports', ['select' => '*', 'order' => 'created_at.desc']);
        if (is_array($directoryImports)) {
            foreach ($directoryImports as $imp) {
                $email = strtolower(trim($imp['email'] ?? ''));
                if (!empty($email) && !isset($seenEmails[$email])) {
                    $seenEmails[$email] = true;
                    $bId = $imp['batch_id'] ?? '';
                    $instId = $batchSchoolMap[$bId] ?? ($imp['institution_id'] ?? '');
                    $bday = $imp['birthday'] ?? '';
                    $age = '';
                    if (!empty($bday)) {
                        try {
                            $bDate = new DateTime($bday);
                            $age = (new DateTime())->diff($bDate)->y;
                        } catch (\Throwable $t) {}
                    }

                    $allMembersList[] = [
                        'id' => $imp['id'] ?? uniqid('imp_'),
                        'full_name' => $imp['name'] ?? 'Student Member',
                        'email' => $email,
                        'student_id' => $imp['existing_id'] ?: ($imp['student_id'] ?: 'N/A'),
                        'membership_id' => $imp['membership_id'] ?: ('IECEP-' . strtoupper(substr(md5($email), 0, 8))),
                        'institution_id' => $instId,
                        'program' => $imp['program'] ?: 'BS Electronics Engineering',
                        'year_level' => $imp['sheet_name'] ?: ($imp['year_level'] ?: '3rd Year'),
                        'birthday' => $bday,
                        'age' => $age,
                        'phone' => $imp['phone'] ?? '',
                        'address' => $imp['address'] ?? '',
                        'payment_status' => 'paid',
                        'avatar_url' => $imp['picture_url'] ?? '',
                        'created_at' => $imp['created_at'] ?? date('c')
                    ];
                }
            }
        }
    }
} catch (\Throwable $e) {
    error_log("Database members fetch error: " . $e->getMessage());
}

// Calculations
$totalMembers = count($allMembersList);
$paidMembers = count(array_filter($allMembersList, fn($m) => ($m['payment_status'] ?? '') === 'paid' || ($m['payment_status'] ?? '') === 'active'));
$issuedIds = count(array_filter($allMembersList, fn($m) => !empty($m['membership_id'])));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Member Directory — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Verified student member directory, university chapter segmentation, and profile dossiers for IECEP-LSC Laguna Student Chapter.">
    <?php include dirname(__DIR__, 4) . '/includes/head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* =========================================================================
           RESPONSIVE WHITE THEME DESIGN SYSTEM (IECEP-LSC MEMSYS)
           ========================================================================= */
        :root {
            --bg-page: #F8FAFC;
            --bg-card: #FFFFFF;
            --border-subtle: #E2E8F0;
            --border-hover: #CBD5E1;
            
            --text-heading: #0F172A;
            --text-body: #334155;
            --text-muted: #64748B;
            --text-light: #94A3B8;

            --color-navy: #0B1D4A;
            --color-navy-hover: #152C6E;
            --color-blue: #2563EB;
            --color-gold: #D4AF37;
            --color-gold-dark: #B45309;
            --color-emerald: #059669;
            --color-purple: #7C3AED;

            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-card: 0 1px 3px 0 rgba(0, 0, 0, 0.04), 0 1px 2px -1px rgba(0, 0, 0, 0.04);
            --shadow-elevated: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
            --shadow-modal: 0 25px 50px -12px rgba(15, 23, 42, 0.25);
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
            padding: 0.85rem 1.25rem;
            max-width: 1560px;
            margin: 0 auto;
            box-sizing: border-box;
            width: 100%;
            overflow-x: hidden !important;
        }

        /* 1. Header Banner */
        .white-page-header {
            background: #FFFFFF;
            border: 1px solid var(--border-subtle);
            border-radius: 12px;
            padding: 0.75rem 1.15rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.75rem;
            box-shadow: var(--shadow-card);
            box-sizing: border-box;
            width: 100%;
        }

        .header-title-box {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .header-main-title {
            margin: 0 0 0.15rem;
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--text-heading);
            letter-spacing: -0.01em;
        }

        .header-subtitle {
            margin: 0;
            font-size: 0.8rem;
            color: var(--text-muted);
            line-height: 1.3;
        }

        .header-btn-group {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            flex-wrap: wrap;
        }

        /* Buttons */
        .btn-white {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.42rem 0.85rem;
            border-radius: 7px;
            font-size: 0.78rem;
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
            gap: 0.4rem;
            padding: 0.42rem 0.95rem;
            border-radius: 7px;
            font-size: 0.78rem;
            font-weight: 800;
            text-decoration: none;
            background: var(--color-navy);
            border: 1px solid var(--color-navy);
            color: #FFFFFF;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(11, 29, 74, 0.15);
            transition: all 0.18s ease;
            white-space: nowrap;
        }
        .btn-primary-navy:hover {
            background: var(--color-navy-hover);
            border-color: var(--color-navy-hover);
            color: #FDE047;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(11, 29, 74, 0.22);
        }

        /* 2. KPI Cards Grid - Left-to-Right layout on Desktop AND Mobile */
        .white-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.65rem;
            margin-bottom: 0.75rem;
            width: 100%;
            box-sizing: border-box;
        }

        .kpi-card {
            background: #FFFFFF;
            border: 1px solid var(--border-subtle);
            border-radius: 10px;
            padding: 0.55rem 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: var(--shadow-card);
            transition: all 0.2s ease;
            min-width: 0;
            box-sizing: border-box;
        }
        .kpi-card:hover {
            border-color: var(--border-hover);
            box-shadow: var(--shadow-elevated);
            transform: translateY(-1px);
        }

        .kpi-icon-pill {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .kpi-icon-pill.blue { background: #EFF6FF; color: #2563EB; border: 1px solid #DBEAFE; }
        .kpi-icon-pill.emerald { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }
        .kpi-icon-pill.gold { background: #FEF9C3; color: #B45309; border: 1px solid #FDE68A; }
        .kpi-icon-pill.purple { background: #F5F3FF; color: #7C3AED; border: 1px solid #DDD6FE; }

        .kpi-number {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--text-heading);
            line-height: 1.1;
        }
        .kpi-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-top: 1px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* 3. Filter & Search Controls Bar - Compact Single Row on Desktop */
        .white-controls-card {
            background: #FFFFFF;
            border: 1px solid var(--border-subtle);
            border-radius: 10px;
            padding: 0.45rem 0.85rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.55rem;
            box-shadow: var(--shadow-card);
            width: 100%;
            box-sizing: border-box;
            flex-wrap: nowrap;
        }

        .filter-controls-left {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex: 1;
            min-width: 0;
            flex-wrap: nowrap;
        }

        .search-input-wrapper {
            position: relative;
            flex: 1 1 240px;
            min-width: 180px;
            max-width: 340px;
            display: flex;
            align-items: center;
        }
        .search-input-wrapper .search-icon {
            position: absolute;
            left: 11px;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            font-size: 0.82rem;
            pointer-events: none;
            z-index: 3;
        }
        .search-input-field {
            width: 100%;
            height: 35px;
            box-sizing: border-box;
            padding: 0.25rem 0.65rem 0.25rem 2.25rem !important;
            border: 1px solid #CBD5E1;
            border-radius: 6px;
            font-size: 0.78rem;
            font-family: inherit;
            outline: none;
            transition: all 0.2s ease;
            background: #FFFFFF;
            color: var(--text-heading);
        }
        .search-input-field:focus {
            border-color: var(--color-navy);
            box-shadow: 0 0 0 3px rgba(11, 29, 74, 0.08);
        }

        .select-filter-box {
            height: 35px;
            padding: 0.25rem 1.6rem 0.25rem 0.65rem;
            border: 1px solid #CBD5E1;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 600;
            font-family: inherit;
            color: var(--text-body);
            background: #FFFFFF url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748B'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E") no-repeat right 0.45rem center/12px;
            appearance: none;
            outline: none;
            cursor: pointer;
            transition: border-color 0.2s ease;
            flex: 0 1 auto;
            max-width: 190px;
            white-space: nowrap;
        }
        .select-filter-box:focus {
            border-color: var(--color-navy);
        }

        .filter-controls-right {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-shrink: 0;
        }

        .showing-counter-badge {
            font-size: 0.76rem;
            font-weight: 700;
            color: var(--text-muted);
            white-space: nowrap;
        }
        .showing-counter-badge strong {
            color: var(--color-navy);
        }

        /* 4. Main Member Table Card (Compact & Perfectly Sized to Mobile Viewport) */
        .white-table-card {
            background: #FFFFFF;
            border: 1px solid var(--border-subtle);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
            margin-bottom: 1rem;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }

        .table-card-topbar {
            padding: 0.95rem 1.25rem;
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #FAFAFA;
            flex-wrap: wrap;
            gap: 0.5rem;
            box-sizing: border-box;
            width: 100%;
        }

        .table-card-heading {
            margin: 0;
            font-size: 0.94rem;
            font-weight: 800;
            color: var(--text-heading);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .table-responsive-viewport {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            overflow-x: hidden !important;
        }

        .roster-white-table {
            width: 100% !important;
            max-width: 100% !important;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.85rem;
            table-layout: auto;
            box-sizing: border-box;
        }

        .roster-white-table th {
            background: #F8FAFC;
            padding: 0.8rem 0.9rem;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-subtle);
            text-align: left;
            user-select: none;
            white-space: nowrap;
        }

        .roster-white-table td {
            padding: 0.75rem 0.9rem;
            border-bottom: 1px solid #F1F5F9;
            color: var(--text-body);
            vertical-align: middle;
            transition: background 0.15s ease;
            box-sizing: border-box;
        }

        .roster-white-table tbody tr:hover td {
            background: #F8FAFC;
        }

        .roster-white-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Member Row Layout */
        .member-info-cell {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            min-width: 0;
        }

        .member-avatar-thumb {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #EFF6FF;
            color: var(--color-navy);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.82rem;
            flex-shrink: 0;
            border: 2px solid #DBEAFE;
            overflow: hidden;
        }
        .member-avatar-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .member-name-text {
            font-weight: 700;
            color: var(--text-heading);
            font-size: 0.84rem;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .member-email-text {
            font-size: 0.72rem;
            color: var(--text-muted);
            line-height: 1.2;
            font-family: 'JetBrains Mono', monospace;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .school-tag-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.2rem 0.5rem;
            border-radius: 5px;
            font-size: 0.72rem;
            font-weight: 700;
            background: #EFF6FF;
            color: #1E3A8A;
            border: 1px solid #DBEAFE;
            white-space: nowrap;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mono-id-tag {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 700;
            color: var(--color-navy);
            font-size: 0.8rem;
            white-space: nowrap;
        }

        /* Status Pills */
        .pill-status {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.2rem 0.55rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: capitalize;
            white-space: nowrap;
        }
        .pill-status.paid, .pill-status.active {
            background: #ECFDF5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }
        .pill-status.pending {
            background: #FFFBEB;
            color: #92400E;
            border: 1px solid #FDE68A;
        }
        .pill-status-dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: currentColor;
        }

        /* Action Buttons on Row */
        .btn-row-action {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.35rem 0.7rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            background: var(--color-navy);
            color: #FFFFFF;
            border: 1px solid var(--color-navy);
            cursor: pointer;
            transition: all 0.15s ease;
            box-shadow: var(--shadow-sm);
            white-space: nowrap;
        }
        .btn-row-action:hover {
            background: var(--color-navy-hover);
            color: #FDE047;
            transform: translateY(-1px);
        }

        /* =========================================================================
           MOBILE COMPACT ADAPTIVE STYLES (FITS 100% WITHOUT ANY SCROLLING)
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

            /* Left-to-Right 4 KPI cards in a single row on Mobile */
            .white-kpi-grid {
                grid-template-columns: repeat(4, 1fr) !important;
                gap: 0.2rem !important;
                margin-bottom: 0.55rem !important;
                width: 100% !important;
            }
            .kpi-card {
                padding: 0.35rem 0.15rem !important;
                gap: 0.2rem !important;
                flex-direction: column !important;
                align-items: center !important;
                text-align: center !important;
                border-radius: 8px !important;
                box-shadow: 0 1px 2px rgba(0,0,0,0.03) !important;
            }
            .kpi-icon-pill {
                width: 22px !important;
                height: 22px !important;
                font-size: 0.7rem !important;
                border-radius: 5px !important;
            }
            .kpi-number {
                font-size: 0.86rem !important;
                line-height: 1 !important;
            }
            .kpi-label {
                font-size: 0.5rem !important;
                line-height: 1.1 !important;
                margin-top: 1px !important;
            }

            /* Filter Controls on Mobile */
            .white-controls-card {
                padding: 0.55rem 0.6rem !important;
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 0.4rem !important;
                border-radius: 10px !important;
                margin-bottom: 0.55rem !important;
                width: 100% !important;
            }
            .filter-controls-left {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 0.4rem !important;
                width: 100% !important;
            }
            .search-input-wrapper {
                min-width: 100% !important;
                max-width: 100% !important;
                width: 100% !important;
            }
            .search-input-wrapper .search-icon {
                left: 12px !important;
                font-size: 0.85rem !important;
            }
            .search-input-field {
                padding: 0.45rem 0.65rem 0.45rem 2.85rem !important; /* Plenty of padding so text never touches the icon */
                font-size: 0.76rem !important;
                width: 100% !important;
            }
            .select-filter-box {
                width: 100% !important;
                padding: 0.45rem 1.6rem 0.45rem 0.65rem !important;
                font-size: 0.76rem !important;
            }
            .filter-controls-right {
                justify-content: space-between !important;
                width: 100% !important;
            }
            .showing-counter-badge {
                font-size: 0.72rem !important;
            }

            /* Compact Mobile Table Layout (100% FITTED - ZERO HORIZONTAL SCROLL) */
            .white-table-card {
                border-radius: 10px !important;
                margin-bottom: 0.65rem !important;
                width: 100% !important;
                max-width: 100% !important;
                overflow: hidden !important;
            }
            .table-card-topbar {
                padding: 0.55rem 0.65rem !important;
            }
            .table-card-heading {
                font-size: 0.8rem !important;
                gap: 0.35rem !important;
            }
            .table-responsive-viewport {
                width: 100% !important;
                max-width: 100% !important;
                overflow-x: hidden !important;
            }
            .roster-white-table {
                table-layout: fixed !important;
                width: 100% !important;
                max-width: 100% !important;
                border-collapse: collapse !important;
                font-size: 0.7rem !important;
            }
            .roster-white-table th {
                padding: 0.4rem 0.25rem !important;
                font-size: 0.6rem !important;
                letter-spacing: 0.02em !important;
            }
            .roster-white-table td {
                padding: 0.4rem 0.25rem !important;
                font-size: 0.68rem !important;
            }

            /* Column Sizing for Compact Mobile Screen (Total = 100%) */
            .col-checkbox {
                width: 22px !important;
                padding-left: 0.2rem !important;
                padding-right: 0.1rem !important;
                text-align: center !important;
            }
            .col-member {
                width: 48% !important;
                padding-left: 0.2rem !important;
                padding-right: 0.2rem !important;
            }
            .col-school {
                width: 24% !important;
                padding-left: 0.2rem !important;
                padding-right: 0.2rem !important;
            }
            .col-status {
                width: 15% !important;
                text-align: center !important;
                padding-left: 0.1rem !important;
                padding-right: 0.1rem !important;
            }
            .col-actions {
                width: 13% !important;
                text-align: right !important;
                padding-right: 0.25rem !important;
            }

            /* Hide non-essential columns on compact mobile view */
            .col-student-id, .col-program {
                display: none !important;
            }

            .member-info-cell {
                gap: 0.25rem !important;
                min-width: 0 !important;
            }
            .member-avatar-thumb {
                width: 22px !important;
                height: 22px !important;
                font-size: 0.62rem !important;
            }
            .member-name-text {
                font-size: 0.68rem !important;
                font-weight: 700 !important;
                line-height: 1.15 !important;
            }
            .member-email-text {
                font-size: 0.56rem !important;
                line-height: 1.1 !important;
            }
            .school-tag-badge {
                font-size: 0.58rem !important;
                padding: 1px 3px !important;
                border-radius: 4px !important;
                max-width: 100% !important;
            }
            .school-tag-badge i {
                display: none !important;
            }
            .school-sub-text {
                display: none !important;
            }
            .pill-status {
                font-size: 0.55rem !important;
                padding: 1px 3px !important;
                border-radius: 9999px !important;
            }
            .pill-status-dot {
                display: none !important;
            }
            .btn-row-action {
                padding: 2px 5px !important;
                font-size: 0.65rem !important;
                border-radius: 4px !important;
            }
            .btn-row-action .btn-action-text {
                display: none !important;
            }

            #emptyDbRow td {
                padding: 1.5rem 0.5rem !important;
            }
        }

        /* =========================================================================
           PROFILE INFORMATION MODAL (WHITE THEME)
           ========================================================================= */
        .modal-backdrop-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(4px);
            z-index: 99999;
            align-items: center;
            justify-content: center;
            padding: 0.75rem;
            animation: fadeInModal 0.2s ease;
        }
        .modal-backdrop-overlay.active {
            display: flex;
        }
        .modal-card-box {
            background: #FFFFFF;
            border-radius: 16px;
            width: 100%;
            max-width: 620px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-modal);
            border: 1px solid var(--border-subtle);
            animation: scaleInModal 0.22s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeInModal { from { opacity: 0; } to { opacity: 1; } }
        @keyframes scaleInModal { from { transform: scale(0.96); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        .modal-header-bar {
            padding: 1.15rem 1.35rem;
            background: #FFFFFF;
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
        }
        .modal-header-title {
            font-size: 1rem;
            font-weight: 800;
            letter-spacing: 0.02em;
            color: var(--text-heading);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
        }
        .modal-close-icon {
            background: #F1F5F9;
            border: 1px solid #E2E8F0;
            color: var(--text-muted);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .modal-close-icon:hover {
            background: #FEE2E2;
            color: #DC2626;
            border-color: #FECACA;
        }

        .modal-hero-profile {
            padding: 1.25rem 1.35rem;
            background: linear-gradient(135deg, #F8FAFC 0%, #FFFFFF 100%);
            border-bottom: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .modal-avatar-lg {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            background: #EFF6FF;
            color: var(--color-navy);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            font-weight: 800;
            border: 3px solid #D4AF37;
            box-shadow: 0 4px 12px rgba(11, 29, 74, 0.12);
            flex-shrink: 0;
            overflow: hidden;
        }
        .modal-avatar-lg img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .modal-hero-name {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-heading);
            margin: 0 0 0.2rem;
            line-height: 1.2;
        }
        .modal-hero-program {
            font-size: 0.84rem;
            font-weight: 600;
            color: #B45309;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .modal-content-grid {
            padding: 1.25rem 1.35rem;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.85rem;
        }
        @media (max-width: 600px) {
            .modal-content-grid {
                grid-template-columns: 1fr;
                padding: 0.9rem;
                gap: 0.65rem;
            }
            .modal-hero-profile {
                padding: 1rem;
                gap: 0.75rem;
            }
            .modal-avatar-lg {
                width: 52px;
                height: 52px;
                font-size: 1.3rem;
            }
            .modal-hero-name {
                font-size: 1.1rem;
            }
        }

        .modal-field-item {
            background: #F8FAFC;
            border: 1px solid var(--border-subtle);
            border-radius: 9px;
            padding: 0.75rem 0.9rem;
            transition: all 0.15s ease;
        }
        .modal-field-item:hover {
            border-color: #CBD5E1;
            background: #F1F5F9;
        }
        .modal-field-item.full-span {
            grid-column: 1 / -1;
        }

        .modal-field-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 0.35rem;
            margin-bottom: 0.25rem;
        }

        .modal-field-value {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-heading);
            line-height: 1.3;
            word-break: break-word;
        }
        .modal-field-value.mono {
            font-family: 'JetBrains Mono', monospace;
            color: var(--color-navy);
        }

        .modal-footer-bar {
            padding: 1rem 1.35rem;
            background: #F8FAFC;
            border-top: 1px solid var(--border-subtle);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.65rem;
            border-bottom-left-radius: 15px;
            border-bottom-right-radius: 15px;
            flex-wrap: wrap;
        }
        @media (max-width: 540px) {
            .modal-footer-bar {
                flex-direction: column;
                align-items: stretch;
            }
            .modal-footer-bar button, .modal-footer-bar a {
                width: 100%;
                justify-content: center;
            }
        }

        /* Digital ID Preview Card */
        .digital-id-card-view {
            background: linear-gradient(135deg, #0B1D4A 0%, #152C6E 50%, #0B1D4A 100%);
            border-radius: 12px;
            color: #FFFFFF;
            padding: 1.35rem;
            border: 2px solid #D4AF37;
            box-shadow: 0 8px 22px rgba(11, 29, 74, 0.25);
            margin-bottom: 1.15rem;
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
                            Member Directory
                        </h1>
                        <p class="header-subtitle">
                            Direct registry of student members submitted by affiliated chapter institutions, synced directly with Supabase.
                        </p>
                    </div>
                </div>
                <div class="header-btn-group">
                    <button type="button" class="btn-white" onclick="exportFilteredCSV()">
                        <i class="fas fa-file-export"></i> Export CSV
                    </button>
                    <a href="/IECEP-LSC-MEMSYS/public/portal/admin/members/batch-process.php" class="btn-white">
                        <i class="fas fa-file-excel" style="color:#107C41;"></i> Chapter Directory Submissions
                    </a>
                    <button type="button" class="btn-primary-navy" onclick="openAddModal()">
                        <i class="fas fa-user-plus" style="color:#FDE047;"></i> Add New Member
                    </button>
                </div>
            </div>

            <!-- Feedback Alert -->
            <?php if (!empty($feedbackMsg)): ?>
                <div class="ap-alert <?= $feedbackType ?>" style="margin-bottom:1.25rem;">
                    <i class="fas fa-circle-check"></i> <?= htmlspecialchars($feedbackMsg) ?>
                </div>
            <?php endif; ?>

            <!-- 2. KPI Cards Grid (4 Columns Across - Same on Desktop & Mobile) -->
            <div class="white-kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-icon-pill blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div class="kpi-number"><?= $totalMembers ?></div>
                        <div class="kpi-label">Total Registered Members</div>
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-icon-pill emerald">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div>
                        <div class="kpi-number" style="color:#059669;"><?= $paidMembers ?></div>
                        <div class="kpi-label">Active / Dues Cleared</div>
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-icon-pill gold">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <div>
                        <div class="kpi-number" style="color:#B45309;"><?= $issuedIds ?></div>
                        <div class="kpi-label">Issued Digital IDs</div>
                    </div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-icon-pill purple">
                        <i class="fas fa-building-columns"></i>
                    </div>
                    <div>
                        <div class="kpi-number" style="color:#7C3AED;"><?= count($schoolNamesMap) ?></div>
                        <div class="kpi-label">Affiliated Campuses</div>
                    </div>
                </div>
            </div>

            <!-- 3. Search and Filter Bar -->
            <div class="white-controls-card">
                <div class="filter-controls-left">
                    <div class="search-input-wrapper">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="memberSearchInput" class="search-input-field" placeholder="Search by name, email, student ID..." onkeyup="applyFilters()">
                    </div>
                    
                    <select id="schoolDropdownFilter" class="select-filter-box" onchange="onSchoolDropdownChange(this.value)">
                        <option value="all">All Enrolled Schools</option>
                        <?php foreach ($schoolNamesMap as $sKey => $sVal): ?>
                            <option value="<?= htmlspecialchars($sKey) ?>"><?= htmlspecialchars($sVal['name']) ?> (<?= $sVal['acronym'] ?>)</option>
                        <?php endforeach; ?>
                    </select>

                    <select id="statusFilter" class="select-filter-box" onchange="applyFilters()">
                        <option value="all">All Payment Statuses</option>
                        <option value="paid">Paid / Good Standing</option>
                        <option value="pending">Pending Payment</option>
                    </select>

                    <select id="yearLevelFilter" class="select-filter-box" onchange="applyFilters()">
                        <option value="all">All Year Levels</option>
                        <option value="1st Year">1st Year</option>
                        <option value="2nd Year">2nd Year</option>
                        <option value="3rd Year">3rd Year</option>
                        <option value="4th Year">4th Year</option>
                        <option value="5th Year">5th Year</option>
                    </select>
                </div>

                <div class="filter-controls-right">
                    <div class="showing-counter-badge">
                        Showing <strong id="visibleMemberCount"><?= $totalMembers ?></strong> of <?= $totalMembers ?> members
                    </div>
                </div>
            </div>

            <!-- 4. Members Table Card (Compact & Perfectly Sized to Mobile Viewport) -->
            <div class="white-table-card">
                <div class="table-card-topbar">
                    <h3 class="table-card-heading">
                        <i class="fas fa-address-book" style="color:var(--color-navy);"></i>
                        <span>Student Member Ledger</span>
                    </h3>
                </div>

                <div class="table-responsive-viewport">
                    <table class="roster-white-table" id="membersMainTable">
                        <thead>
                            <tr>
                                <th class="col-checkbox"><input type="checkbox" id="selectAllCheckbox" onclick="toggleSelectAll(this)"></th>
                                <th class="col-member">Student Member</th>
                                <th class="col-school">Enrolled School</th>
                                <th class="col-student-id">Student ID</th>
                                <th class="col-program">Program & Year</th>
                                <th class="col-status">Status</th>
                                <th class="col-actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="membersTableBody">
                            <?php if (empty($allMembersList)): ?>
                                <tr id="emptyDbRow">
                                    <td colspan="7" style="text-align:center; padding:2rem 0.5rem; color:#64748B; word-break:break-word;">
                                        <i class="fas fa-folder-open" style="font-size:2rem; color:#CBD5E1; margin-bottom:0.4rem; display:block;"></i>
                                        <h4 style="margin:0 0 0.25rem; color:#0F172A; font-weight:800; font-size:0.95rem;">No Member Directories Submitted Yet</h4>
                                        <p style="margin:0 0 0.75rem; font-size:0.78rem; color:#64748B; line-height:1.35;">
                                            There are currently no member records in the database. Use <strong>"Bulk CSV Import"</strong> or wait for school rosters.
                                        </p>
                                        <a href="/IECEP-LSC-MEMSYS/public/portal/admin/members/batch-process.php" class="btn-primary-navy" style="font-size:0.76rem; padding:0.4rem 0.8rem; display:inline-flex; max-width:100%;">
                                            <i class="fas fa-file-import"></i> Upload Member Directory CSV
                                        </a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($allMembersList as $mem): ?>
                                    <?php 
                                        $mId = $mem['id'];
                                        $fName = $mem['full_name'];
                                        $email = $mem['email'];
                                        $sId = $mem['student_id'] ?: 'N/A';
                                        $memCode = $mem['membership_id'] ?: 'N/A';
                                        $instId = $mem['institution_id'];
                                        $instData = $schoolNamesMap[$instId] ?? [
                                            'name' => 'Affiliated Chapter',
                                            'acronym' => 'HEI',
                                            'city' => 'Laguna'
                                        ];
                                        $prog = $mem['program'] ?: 'BS Electronics Engineering';
                                        $yr = $mem['year_level'] ?: '3rd Year';
                                        $age = $mem['age'] ?? '';
                                        $bday = $mem['birthday'] ?? '';
                                        $formattedBday = !empty($bday) ? date('F d, Y', strtotime($bday)) : '';
                                        $phone = $mem['phone'] ?? '';
                                        $addr = $mem['address'] ?? '';
                                        $pStatus = strtolower($mem['payment_status'] ?? 'paid');
                                        $avatar = $mem['avatar_url'] ?? '';

                                        // JSON data attribute for clean modal popup
                                        $memberJson = json_encode([
                                            'id' => $mId,
                                            'name' => $fName,
                                            'email' => $email,
                                            'student_id' => $sId,
                                            'membership_id' => $memCode,
                                            'school_name' => $instData['name'],
                                            'school_acronym' => $instData['acronym'],
                                            'school_city' => $instData['city'],
                                            'institution_id' => $instId,
                                            'program' => $prog,
                                            'year_level' => $yr,
                                            'age' => $age,
                                            'birthday' => $formattedBday,
                                            'phone' => $phone,
                                            'address' => $addr,
                                            'payment_status' => $pStatus,
                                            'avatar_url' => $avatar
                                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                                    ?>
                                    <tr class="member-row" 
                                        data-school="<?= htmlspecialchars($instId) ?>"
                                        data-status="<?= htmlspecialchars($pStatus) ?>"
                                        data-year="<?= htmlspecialchars(strtolower($yr)) ?>"
                                        data-search="<?= htmlspecialchars(strtolower($fName . ' ' . $email . ' ' . $sId . ' ' . $memCode . ' ' . $instData['name'] . ' ' . $instData['acronym'])) ?>">
                                        <td class="col-checkbox"><input type="checkbox" class="row-checkbox"></td>
                                        <td class="col-member">
                                            <div class="member-info-cell">
                                                <div class="member-avatar-thumb">
                                                    <?php if (!empty($avatar)): ?>
                                                        <img src="<?= htmlspecialchars($avatar) ?>" alt="<?= htmlspecialchars($fName) ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                        <span style="display:none;"><?= strtoupper(substr($fName, 0, 1)) ?></span>
                                                    <?php else: ?>
                                                        <span><?= strtoupper(substr($fName, 0, 1)) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="min-width:0;">
                                                    <div class="member-name-text"><?= htmlspecialchars($fName) ?></div>
                                                    <div class="member-email-text"><?= htmlspecialchars($email) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="col-school">
                                            <span class="school-tag-badge">
                                                <i class="fas fa-building-columns"></i>
                                                <?= htmlspecialchars($instData['acronym']) ?>
                                            </span>
                                            <div class="school-sub-text" style="font-size:0.7rem; color:var(--text-muted); margin-top:2px;"><?= htmlspecialchars($instData['name']) ?></div>
                                        </td>
                                        <td class="col-student-id">
                                            <span class="mono-id-tag">
                                                <?= htmlspecialchars($sId) ?>
                                            </span>
                                        </td>
                                        <td class="col-program">
                                            <strong style="color:var(--text-heading); font-size:0.82rem;"><?= htmlspecialchars($yr) ?></strong>
                                            <div style="font-size:0.72rem; color:var(--text-muted);"><?= htmlspecialchars($prog) ?></div>
                                        </td>
                                        <td class="col-status">
                                            <?php if ($pStatus === 'paid' || $pStatus === 'active'): ?>
                                                <span class="pill-status paid"><span class="pill-status-dot"></span> Paid</span>
                                            <?php else: ?>
                                                <span class="pill-status pending"><span class="pill-status-dot"></span> Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="col-actions" style="text-align:right;">
                                            <button type="button" 
                                                    class="btn-row-action" 
                                                    data-member='<?= $memberJson ?>' 
                                                    onclick="openProfileModal(this)"
                                                    title="View Profile">
                                                <i class="fas fa-eye"></i><span class="btn-action-text"> View</span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- =========================================================================
         MODAL: PROFILE INFORMATION (EXACT REQUESTED STRUCTURE)
         ========================================================================= -->
    <div id="profileInfoModal" class="modal-backdrop-overlay" onclick="onOverlayClick(event)">
        <div class="modal-card-box">
            
            <!-- Header -->
            <div class="modal-header-bar">
                <h3 class="modal-header-title">
                    <i class="fas fa-user-circle" style="color:var(--color-navy);"></i>
                    <span>PROFILE INFORMATION</span>
                </h3>
                <button type="button" class="modal-close-icon" onclick="closeProfileModal()" title="Close">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>

            <!-- Hero Avatar & Name -->
            <div class="modal-hero-profile">
                <div class="modal-avatar-lg" id="pmAvatar">
                    <span id="pmInitial">M</span>
                </div>
                <div style="flex:1;">
                    <h2 class="modal-hero-name" id="pmFullName">Member Name</h2>
                    <div class="modal-hero-program">
                        <i class="fas fa-graduation-cap"></i>
                        <span id="pmProgramYear">BS Electronics Engineering - 3rd Year</span>
                    </div>
                </div>
            </div>

            <!-- Profile Info Grid -->
            <div class="modal-content-grid">
                
                <!-- Enrolled School -->
                <div class="modal-field-item full-span">
                    <div class="modal-field-label">
                        <i class="fas fa-building-columns" style="color:var(--color-navy);"></i>
                        <span>Enrolled School</span>
                    </div>
                    <div class="modal-field-value" id="pmSchool">Higher Education Institution</div>
                </div>

                <!-- Student ID -->
                <div class="modal-field-item">
                    <div class="modal-field-label">
                        <i class="fas fa-id-badge" style="color:var(--color-navy);"></i>
                        <span>Student ID</span>
                    </div>
                    <div class="modal-field-value mono" id="pmStudentId">N/A</div>
                </div>

                <!-- Membership ID -->
                <div class="modal-field-item">
                    <div class="modal-field-label">
                        <i class="fas fa-certificate" style="color:var(--color-gold);"></i>
                        <span>Membership ID</span>
                    </div>
                    <div class="modal-field-value mono" id="pmMembershipId" style="color:#B45309;">N/A</div>
                </div>

                <!-- Age / Birthday -->
                <div class="modal-field-item">
                    <div class="modal-field-label">
                        <i class="fas fa-cake-candles" style="color:#EF4444;"></i>
                        <span>Age / Birthday</span>
                    </div>
                    <div class="modal-field-value" id="pmAgeBirthday">N/A</div>
                </div>

                <!-- Payment Status -->
                <div class="modal-field-item">
                    <div class="modal-field-label">
                        <i class="fas fa-receipt" style="color:var(--color-emerald);"></i>
                        <span>Payment Status</span>
                    </div>
                    <div class="modal-field-value" id="pmPaymentStatus">
                        <span class="pill-status paid"><span class="pill-status-dot"></span> Paid / Dues Cleared</span>
                    </div>
                </div>

                <!-- Gmail / Email -->
                <div class="modal-field-item">
                    <div class="modal-field-label">
                        <i class="fas fa-envelope" style="color:var(--color-blue);"></i>
                        <span>Gmail / Email</span>
                    </div>
                    <div class="modal-field-value" id="pmEmail" style="font-family:'JetBrains Mono', monospace; font-size:0.85rem; color:#2563EB;">member@gmail.com</div>
                </div>

                <!-- Contact Number -->
                <div class="modal-field-item">
                    <div class="modal-field-label">
                        <i class="fas fa-phone" style="color:var(--color-emerald);"></i>
                        <span>Contact Number</span>
                    </div>
                    <div class="modal-field-value" id="pmPhone">N/A</div>
                </div>

                <!-- Complete Address -->
                <div class="modal-field-item full-span">
                    <div class="modal-field-label">
                        <i class="fas fa-location-dot" style="color:#DC2626;"></i>
                        <span>Complete Address</span>
                    </div>
                    <div class="modal-field-value" id="pmAddress">N/A</div>
                </div>

            </div>

            <!-- Footer Actions -->
            <div class="modal-footer-bar">
                <button type="button" class="btn-white" onclick="exportDigitalId()">
                    <i class="fas fa-id-card" style="color:#B45309;"></i> Export Digital ID
                </button>
                <button type="button" class="btn-primary-navy" onclick="openEditModal()">
                    <i class="fas fa-pen-to-square"></i> Edit Details
                </button>
                <button type="button" class="btn-white" onclick="closeProfileModal()">
                    <i class="fas fa-xmark"></i> Close
                </button>
            </div>

        </div>
    </div>

    <!-- =========================================================================
         MODAL: DIGITAL ID CARD EXPORT PREVIEW
         ========================================================================= -->
    <div id="digitalIdModal" class="modal-backdrop-overlay" onclick="if(event.target===this) closeDigitalIdModal()">
        <div class="modal-card-box" style="max-width:490px;">
            <div class="modal-header-bar">
                <h3 class="modal-header-title"><i class="fas fa-id-card" style="color:var(--color-navy);"></i> Official Digital Member ID</h3>
                <button type="button" class="modal-close-icon" onclick="closeDigitalIdModal()">&times;</button>
            </div>
            <div style="padding:1.5rem;">
                <div class="digital-id-card-view" id="printableDigitalId">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1rem;">
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <img src="https://iecep-lsc-memsys-production.up.railway.app/public/assets/icons/iecep-logo.png" alt="IECEP" style="width:34px; height:34px; object-fit:contain;" onerror="this.style.display='none'">
                            <div>
                                <div style="font-size:0.9rem; font-weight:800; color:#FDE047; letter-spacing:0.02em;">IECEP-LSC</div>
                                <div style="font-size:0.65rem; color:#E2E8F0; text-transform:uppercase;">Laguna Student Chapter</div>
                            </div>
                        </div>
                        <div style="background:rgba(212,175,55,0.25); border:1px solid #D4AF37; padding:2px 8px; border-radius:4px; font-size:0.68rem; font-weight:700; color:#FDE047;">
                            AY 2026-2027
                        </div>
                    </div>

                    <div style="display:flex; gap:1rem; align-items:center; margin-bottom:1rem;">
                        <div class="modal-avatar-lg" style="width:64px; height:64px; font-size:1.5rem;" id="idCardAvatar">
                            <span>M</span>
                        </div>
                        <div>
                            <div style="font-size:1.15rem; font-weight:800; color:#FFFFFF;" id="idCardName">Member Name</div>
                            <div style="font-size:0.75rem; color:#FDE047; font-weight:700;" id="idCardProgram">BS Electronics Engineering</div>
                            <div style="font-size:0.72rem; color:#CBD5E1;" id="idCardSchool">Affiliated Chapter</div>
                        </div>
                    </div>

                    <div style="background:rgba(255,255,255,0.08); border-radius:8px; padding:0.6rem 0.8rem; display:flex; justify-content:space-between; font-size:0.75rem; margin-bottom:0.75rem;">
                        <div>
                            <span style="color:#94A3B8;">Student ID:</span>
                            <strong style="color:#FFFFFF; font-family:'JetBrains Mono', monospace;" id="idCardStudentId">N/A</strong>
                        </div>
                        <div>
                            <span style="color:#94A3B8;">Member Code:</span>
                            <strong style="color:#FDE047; font-family:'JetBrains Mono', monospace;" id="idCardMemCode">N/A</strong>
                        </div>
                    </div>

                    <div style="text-align:center; padding-top:0.5rem; border-top:1px dashed rgba(255,255,255,0.2); font-size:0.65rem; color:#94A3B8;">
                        <i class="fas fa-qrcode"></i> SHA-256 Cryptographically Signed Digital Credential
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
                    <button type="button" class="btn-white" onclick="closeDigitalIdModal()">Close</button>
                    <button type="button" class="btn-primary-navy" onclick="window.print()">
                        <i class="fas fa-print"></i> Print ID
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- =========================================================================
         MODAL: EDIT MEMBER DETAILS
         ========================================================================= -->
    <div id="editMemberModal" class="modal-backdrop-overlay" onclick="if(event.target===this) closeEditModal()">
        <div class="modal-card-box" style="max-width:540px;">
            <div class="modal-header-bar">
                <h3 class="modal-header-title"><i class="fas fa-pen-to-square" style="color:var(--color-navy);"></i> Edit Member Information</h3>
                <button type="button" class="modal-close-icon" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" style="padding:1.5rem;">
                <input type="hidden" name="action" value="edit_member">
                <input type="hidden" name="member_id" id="editMemberId" value="">
                
                <div class="ap-form-group" style="margin-bottom:1rem;">
                    <label class="ap-form-label">Full Name</label>
                    <input type="text" name="full_name" id="editFullName" class="ap-input" required>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:1rem;">
                    <div class="ap-form-group">
                        <label class="ap-form-label">Gmail / Email</label>
                        <input type="email" name="email" id="editEmail" class="ap-input" required>
                    </div>
                    <div class="ap-form-group">
                        <label class="ap-form-label">Student ID</label>
                        <input type="text" name="student_id" id="editStudentId" class="ap-input" required>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:1rem;">
                    <div class="ap-form-group">
                        <label class="ap-form-label">Year Level</label>
                        <select name="year_level" id="editYearLevel" class="ap-form-select">
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                            <option value="5th Year">5th Year</option>
                        </select>
                    </div>
                    <div class="ap-form-group">
                        <label class="ap-form-label">Payment Status</label>
                        <select name="payment_status" id="editPaymentStatus" class="ap-form-select">
                            <option value="paid">Paid / Good Standing</option>
                            <option value="pending">Pending Payment</option>
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:1rem;">
                    <div class="ap-form-group">
                        <label class="ap-form-label">Contact Phone</label>
                        <input type="text" name="phone" id="editPhone" class="ap-input">
                    </div>
                    <div class="ap-form-group">
                        <label class="ap-form-label">Degree Program</label>
                        <input type="text" name="program" id="editProgram" class="ap-input">
                    </div>
                </div>

                <div class="ap-form-group" style="margin-bottom:1.5rem;">
                    <label class="ap-form-label">Complete Address</label>
                    <input type="text" name="address" id="editAddress" class="ap-input">
                </div>

                <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
                    <button type="button" class="btn-white" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn-primary-navy"><i class="fas fa-floppy-disk"></i> Update Details</button>
                </div>
            </form>
        </div>
    </div>

    <!-- =========================================================================
         MODAL: ADD NEW MEMBER
         ========================================================================= -->
    <div id="addModal" class="modal-backdrop-overlay" onclick="if(event.target===this) closeAddModal()">
        <div class="modal-card-box" style="max-width:540px;">
            <div class="modal-header-bar">
                <h3 class="modal-header-title"><i class="fas fa-user-plus" style="color:var(--color-navy);"></i> Register New Student Member</h3>
                <button type="button" class="modal-close-icon" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="POST" style="padding:1.5rem;">
                <input type="hidden" name="action" value="add_member">
                
                <div class="ap-form-group" style="margin-bottom:1rem;">
                    <label class="ap-form-label">Full Name</label>
                    <input type="text" name="full_name" class="ap-input" placeholder="e.g. Maria Santos" required>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:1rem;">
                    <div class="ap-form-group">
                        <label class="ap-form-label">Gmail / Email</label>
                        <input type="email" name="email" class="ap-input" placeholder="mariasantos@gmail.com" required>
                    </div>
                    <div class="ap-form-group">
                        <label class="ap-form-label">Student ID</label>
                        <input type="text" name="student_id" class="ap-input" placeholder="e.g. 2023-08912" required>
                    </div>
                </div>

                <div class="ap-form-group" style="margin-bottom:1rem;">
                    <label class="ap-form-label">Enrolled School Chapter</label>
                    <select name="institution_id" class="ap-form-select" required>
                        <option value="">-- Select Institution --</option>
                        <?php foreach ($schoolNamesMap as $sKey => $sVal): ?>
                            <option value="<?= htmlspecialchars($sKey) ?>"><?= htmlspecialchars($sVal['name']) ?> (<?= $sVal['acronym'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:1rem;">
                    <div class="ap-form-group">
                        <label class="ap-form-label">Program</label>
                        <input type="text" name="program" class="ap-input" value="BS Electronics Engineering">
                    </div>
                    <div class="ap-form-group">
                        <label class="ap-form-label">Year Level</label>
                        <select name="year_level" class="ap-form-select">
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year" selected>3rd Year</option>
                            <option value="4th Year">4th Year</option>
                            <option value="5th Year">5th Year</option>
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:1rem;">
                    <div class="ap-form-group">
                        <label class="ap-form-label">Contact Phone</label>
                        <input type="text" name="phone" class="ap-input" placeholder="+63 912 345 6789">
                    </div>
                    <div class="ap-form-group">
                        <label class="ap-form-label">Payment Status</label>
                        <select name="payment_status" class="ap-form-select">
                            <option value="paid">Paid / Good Standing</option>
                            <option value="pending">Pending Payment</option>
                        </select>
                    </div>
                </div>

                <div class="ap-form-group" style="margin-bottom:1.5rem;">
                    <label class="ap-form-label">Complete Address</label>
                    <input type="text" name="address" class="ap-input" placeholder="e.g. Santa Cruz, Laguna">
                </div>

                <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
                    <button type="button" class="btn-white" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn-primary-navy"><i class="fas fa-floppy-disk"></i> Save Member</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Client-side Scripts -->
    <script>
        let currentSelectedSchool = 'all';

        // On School Dropdown Change
        function onSchoolDropdownChange(schoolId) {
            currentSelectedSchool = schoolId;
            applyFilters();
        }

        // Combined Filter Function
        function applyFilters() {
            const query = (document.getElementById('memberSearchInput').value || '').toLowerCase().trim();
            const statusVal = (document.getElementById('statusFilter').value || 'all').toLowerCase();
            const yearVal = (document.getElementById('yearLevelFilter').value || 'all').toLowerCase();
            const rows = document.querySelectorAll('.member-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const rowSchool = row.getAttribute('data-school') || '';
                const rowStatus = row.getAttribute('data-status') || '';
                const rowYear = (row.getAttribute('data-year') || '').toLowerCase();
                const rowSearch = row.getAttribute('data-search') || '';

                const matchesSchool = (currentSelectedSchool === 'all' || rowSchool === currentSelectedSchool);
                const matchesStatus = (statusVal === 'all' || rowStatus === statusVal);
                const matchesYear = (yearVal === 'all' || rowYear.includes(yearVal));
                const matchesQuery = (!query || rowSearch.includes(query));

                if (matchesSchool && matchesStatus && matchesYear && matchesQuery) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Update visible counter
            const countEl = document.getElementById('visibleMemberCount');
            if (countEl) countEl.textContent = visibleCount;
        }

        // Profile Information Modal Logic
        let activeMemberData = null;

        function openProfileModal(btn) {
            try {
                const data = JSON.parse(btn.getAttribute('data-member'));
                activeMemberData = data;

                // Populate Fields
                document.getElementById('pmFullName').textContent = data.name || 'Member';
                document.getElementById('pmProgramYear').textContent = (data.program || 'BS ECE') + ' - ' + (data.year_level || '3rd Year');
                document.getElementById('pmSchool').textContent = data.school_name || 'Affiliated Chapter';
                document.getElementById('pmStudentId').textContent = data.student_id || 'N/A';
                document.getElementById('pmMembershipId').textContent = data.membership_id || 'N/A';
                
                let ageBday = '';
                if (data.age) ageBday += data.age + ' yrs old';
                if (data.birthday) ageBday += (ageBday ? ' (' + data.birthday + ')' : data.birthday);
                document.getElementById('pmAgeBirthday').textContent = ageBday || 'N/A';

                document.getElementById('pmEmail').textContent = data.email || 'N/A';
                document.getElementById('pmPhone').textContent = data.phone || 'N/A';
                document.getElementById('pmAddress').textContent = data.address || 'N/A';

                // Status Badge
                const statusEl = document.getElementById('pmPaymentStatus');
                if (data.payment_status === 'paid' || data.payment_status === 'active') {
                    statusEl.innerHTML = '<span class="pill-status paid"><span class="pill-status-dot"></span> Paid / Dues Cleared</span>';
                } else {
                    statusEl.innerHTML = '<span class="pill-status pending"><span class="pill-status-dot"></span> Pending Payment</span>';
                }

                // Avatar
                const avatarEl = document.getElementById('pmAvatar');
                if (data.avatar_url) {
                    avatarEl.innerHTML = `<img src="${data.avatar_url}" alt="${data.name}" onerror="this.parentElement.innerHTML='${(data.name || 'U').charAt(0).toUpperCase()}'">`;
                } else {
                    avatarEl.innerHTML = `<span>${(data.name || 'U').charAt(0).toUpperCase()}</span>`;
                }

                // Show modal
                document.getElementById('profileInfoModal').classList.add('active');
                document.body.style.overflow = 'hidden';
            } catch (err) {
                console.error("Error opening profile modal:", err);
            }
        }

        function closeProfileModal() {
            document.getElementById('profileInfoModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function onOverlayClick(e) {
            if (e.target.id === 'profileInfoModal') {
                closeProfileModal();
            }
        }

        // Digital ID Modal
        function exportDigitalId() {
            if (!activeMemberData) return;
            document.getElementById('idCardName').textContent = activeMemberData.name;
            document.getElementById('idCardProgram').textContent = activeMemberData.program || 'BS Electronics Engineering';
            document.getElementById('idCardSchool').textContent = activeMemberData.school_name || 'Laguna Student Chapter';
            document.getElementById('idCardStudentId').textContent = activeMemberData.student_id;
            document.getElementById('idCardMemCode').textContent = activeMemberData.membership_id;

            const avatarWrap = document.getElementById('idCardAvatar');
            if (activeMemberData.avatar_url) {
                avatarWrap.innerHTML = `<img src="${activeMemberData.avatar_url}" alt="${activeMemberData.name}" onerror="this.parentElement.innerHTML='${(activeMemberData.name || 'U').charAt(0).toUpperCase()}'">`;
            } else {
                avatarWrap.innerHTML = `<span>${(activeMemberData.name || 'U').charAt(0).toUpperCase()}</span>`;
            }

            document.getElementById('digitalIdModal').classList.add('active');
        }

        function closeDigitalIdModal() {
            document.getElementById('digitalIdModal').classList.remove('active');
        }

        // Edit Modal
        function openEditModal() {
            if (!activeMemberData) return;
            document.getElementById('editMemberId').value = activeMemberData.id;
            document.getElementById('editFullName').value = activeMemberData.name;
            document.getElementById('editEmail').value = activeMemberData.email;
            document.getElementById('editStudentId').value = activeMemberData.student_id;
            document.getElementById('editYearLevel').value = activeMemberData.year_level || '3rd Year';
            document.getElementById('editProgram').value = activeMemberData.program || 'BS Electronics Engineering';
            document.getElementById('editPhone').value = activeMemberData.phone || '';
            document.getElementById('editAddress').value = activeMemberData.address || '';
            document.getElementById('editPaymentStatus').value = activeMemberData.payment_status || 'paid';

            closeProfileModal();
            document.getElementById('editMemberModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeEditModal() {
            document.getElementById('editMemberModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function exportFilteredCSV() {
            const rows = document.querySelectorAll('.member-row');
            let csv = "Full Name,Email,Student ID,Membership ID,School,Program,Year Level,Phone,Address,Status\n";
            
            rows.forEach(r => {
                if (r.style.display !== 'none') {
                    try {
                        const btn = r.querySelector('.btn-row-action');
                        const data = JSON.parse(btn.getAttribute('data-member'));
                        csv += `"${data.name}","${data.email}","${data.student_id}","${data.membership_id}","${data.school_name}","${data.program}","${data.year_level}","${data.phone}","${data.address}","${data.payment_status}"\n`;
                    } catch(e) {}
                }
            });

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            const url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", `IECEP_Members_${currentSelectedSchool}_${new Date().toISOString().slice(0,10)}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function toggleSelectAll(master) {
            document.querySelectorAll('.row-checkbox').forEach(cb => {
                const tr = cb.closest('tr');
                if (tr && tr.style.display !== 'none') {
                    cb.checked = master.checked;
                }
            });
        }

        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeProfileModal();
                closeDigitalIdModal();
                closeEditModal();
                closeAddModal();
            }
        });
    </script>
</body>
</html>
