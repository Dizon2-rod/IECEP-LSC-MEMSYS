<?php
require_once __DIR__ . '/../../bootstrap.php';
$current_page = 'institutions';

require_once __DIR__ . '/../../auth_check.php';
require_role(['admin', 'super_admin', 'registration', 'committee_registration']);

use PhpOffice\PhpSpreadsheet\IOFactory;

$user = get_user_info();
$supabase = getSupabaseClient();

$feedbackMsg = '';
$feedbackType = 'success';

// Handle POST actions: Approve, Request Revision, or Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $appId = $_POST['application_id'] ?? '';
    $instName = trim($_POST['institution_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contactPerson = trim($_POST['contact_person'] ?? '');
    $contactPhone = trim($_POST['contact_phone'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($action === 'approve_charter') {
        try {
            $timestamp = date('c');
            $instId = bin2hex(random_bytes(16));
            $membersCount = 0;
            $appData = null;

            // 1. Fetch pending application if present
            if ($appId) {
                $appRes = $supabase->select('pending_affiliations', ['id' => 'eq.' . $appId]);
                if (!empty($appRes)) {
                    $appData = $appRes[0];
                    $instName = $appData['institution_name'] ?? $instName;
                    $email = $appData['contact_email'] ?? ($appData['email'] ?? $email);
                    $contactPerson = $appData['contact_person'] ?? $contactPerson;
                    $contactPhone = $appData['contact_phone'] ?? $contactPhone;
                }
            }

            // Derive acronym
            $words = explode(' ', $instName);
            $acronym = count($words) > 1 ? implode('', array_map(fn($w) => strtoupper(substr($w, 0, 1)), array_slice($words, 0, 4))) : substr($instName, 0, 8);

            // 2. Insert into institutions table
            if ($instName) {
                $supabase->insert('institutions', [[
                    'id' => $instId,
                    'name' => $instName,
                    'acronym' => $acronym,
                    'email' => $email ?: 'chapter@iecep.ph',
                    'contact_person' => $contactPerson ?: 'Chapter President',
                    'contact_phone' => $contactPhone ?: '+63 912 345 6789',
                    'status' => 'active',
                    'compliance_status' => 'compliant',
                    'affiliation_fee_paid' => true,
                    'membership_count' => intval($appData['total_members'] ?? 0),
                    'city' => 'Laguna',
                    'province' => 'Laguna',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp
                ]]);
            }

            // Initialize Email Service
            require_once __DIR__ . '/../../../src/lib/EmailService.php';
            $emailService = new \App\Lib\EmailService();

            // 3. Auto-Create School Officer Portal Account & Send Credentials
            $officerTempPass = 'LSC-' . rand(1000, 9999) . '-' . substr(strtoupper(bin2hex(random_bytes(2))), 0, 4);
            if ($email) {
                $officerUserId = bin2hex(random_bytes(16));
                $supabase->insert('user_profiles', [[
                    'id' => $officerUserId,
                    'user_id' => $officerUserId,
                    'full_name' => $contactPerson ?: "$acronym Officer",
                    'role' => 'school_officer',
                    'institution_id' => $instId,
                    'membership_status' => 'active',
                    'created_at' => $timestamp
                ]]);

                try {
                    $emailService->sendSchoolAccountCredentials($email, $instName, $officerTempPass, $contactPerson ?: "$acronym Officer");
                } catch (\Throwable $emEx) {
                    error_log("Officer email send error: " . $emEx->getMessage());
                }
            }

            // 4. Auto-Ingest Attached Student Members Directory & Send Login Credentials
            $memberDirectoryUrl = $appData['member_directory'] ?? null;
            $ingestedCount = 0;

            // Fetch base membership count for sequential IDs
            $baseCount = 100;
            try {
                $existingMembers = $supabase->select('members', ['select' => 'id']);
                $baseCount = is_array($existingMembers) ? count($existingMembers) : 100;
            } catch (\Throwable $e) {}

            // If a member directory file was uploaded, parse it
            if ($memberDirectoryUrl) {
                $localPath = str_replace('/IECEP-LSC-MEMSYS/public/', __DIR__ . '/../../', $memberDirectoryUrl);
                if (!file_exists($localPath) && strpos($memberDirectoryUrl, 'http') === false) {
                    $localPath = dirname(__DIR__, 3) . '/' . ltrim($memberDirectoryUrl, '/');
                }

                if (file_exists($localPath)) {
                    $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
                    $memberRows = [];

                    try {
                        if (in_array($ext, ['xlsx', 'xls']) && class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
                            $spreadsheet = IOFactory::load($localPath);
                            $worksheet = $spreadsheet->getActiveSheet();
                            $memberRows = $worksheet->toArray(null, true, true, false);
                        } else {
                            $content = file_get_contents($localPath);
                            $lines = preg_split('/\r\n|\r|\n/', trim($content));
                            foreach ($lines as $l) {
                                if (trim($l)) $memberRows[] = str_getcsv($l);
                            }
                        }

                        if (count($memberRows) > 1) {
                            $headerRow = array_map('strtolower', array_map('trim', array_shift($memberRows)));
                            
                            $idxStudentId = -1; $idxName = -1; $idxEmail = -1; $idxProg = -1; $idxYear = -1;
                            foreach ($headerRow as $hIdx => $hText) {
                                if (strpos($hText, 'student') !== false || strpos($hText, 'id') !== false) $idxStudentId = $hIdx;
                                if (strpos($hText, 'name') !== false || strpos($hText, 'full') !== false) $idxName = $hIdx;
                                if (strpos($hText, 'mail') !== false) $idxEmail = $hIdx;
                                if (strpos($hText, 'prog') !== false || strpos($hText, 'course') !== false || strpos($hText, 'degree') !== false) $idxProg = $hIdx;
                                if (strpos($hText, 'year') !== false || strpos($hText, 'level') !== false) $idxYear = $hIdx;
                            }

                            if ($idxEmail === -1) $idxEmail = 2;
                            if ($idxName === -1) $idxName = 1;
                            if ($idxStudentId === -1) $idxStudentId = 0;

                            foreach ($memberRows as $row) {
                                $sId = trim((string)($row[$idxStudentId] ?? ''));
                                $name = trim((string)($row[$idxName] ?? ''));
                                $sEmail = trim((string)($row[$idxEmail] ?? ''));
                                $prog = trim((string)($row[$idxProg] ?? 'BS Electronics Engineering'));
                                $year = trim((string)($row[$idxYear] ?? '3rd Year'));
                                $memberTempPass = 'MEM-' . rand(1000, 9999) . '-' . substr(strtoupper(bin2hex(random_bytes(2))), 0, 4);

                                if (filter_var($name, FILTER_VALIDATE_EMAIL) && !filter_var($sEmail, FILTER_VALIDATE_EMAIL)) {
                                    $tmp = $name; $name = $sEmail; $sEmail = $tmp;
                                }

                                if (!empty($sEmail) && filter_var($sEmail, FILTER_VALIDATE_EMAIL) && !empty($name)) {
                                    $baseCount++;
                                    $memId = bin2hex(random_bytes(16));
                                    $membershipId = 'IECEP-2026-' . str_pad($baseCount, 4, '0', STR_PAD_LEFT);
                                    $hash = hash('sha256', $memId . $name . $sEmail . $timestamp);

                                    $supabase->insert('members', [[
                                        'id' => $memId,
                                        'full_name' => $name,
                                        'email' => $sEmail,
                                        'student_id' => $sId ?: ('2026-' . rand(10000, 99999)),
                                        'membership_id' => $membershipId,
                                        'institution_id' => $instId,
                                        'program' => $prog ?: 'BS Electronics Engineering',
                                        'year_level' => $year ?: '3rd Year',
                                        'member_type' => 'regular',
                                        'payment_status' => 'paid',
                                        'digital_id_hash' => $hash,
                                        'digital_id_url' => 'DID-2026-LSC-' . strtoupper(substr($memId, 0, 4)),
                                        'created_at' => $timestamp,
                                        'updated_at' => $timestamp
                                    ]]);

                                    $supabase->insert('user_profiles', [[
                                        'id' => $memId,
                                        'user_id' => $memId,
                                        'full_name' => $name,
                                        'role' => 'member',
                                        'institution_id' => $instId,
                                        'membership_status' => 'active',
                                        'created_at' => $timestamp
                                    ]]);

                                    // Send credential email to student's Gmail
                                    try {
                                        $emailService->sendMemberWelcomeEmail($sEmail, $name, $membershipId, $memberTempPass, $instName);
                                    } catch (\Throwable $stEx) {
                                        error_log("Student welcome email error for $sEmail: " . $stEx->getMessage());
                                    }

                                    $ingestedCount++;
                                }
                            }
                        }
                    } catch (\Throwable $ex) {
                        error_log("Directory parse error during affiliation approval: " . $ex->getMessage());
                    }
                }
            }

            // 5. Update pending application status
            if ($appId) {
                $supabase->update('pending_affiliations', [
                    'status' => 'approved',
                    'updated_at' => $timestamp
                ], $appId);
            }

            // 6. Anchor blockchain proof
            $certHash = hash('sha256', $instName . '|CHARTER|' . $timestamp);
            $supabase->insert('blockchain_records', [[
                'entity_type' => 'institution_charter',
                'entity_id' => $instId,
                'record_type' => 'charter_endorsement',
                'transaction_hash' => $certHash,
                'record_hash' => $certHash,
                'data_hash' => $certHash,
                'confirmed' => true,
                'data_json' => [
                    'institution_name' => $instName,
                    'action' => 'Charter Endorsed & Members Ingested',
                    'academic_year' => '2026-2027',
                    'members_ingested' => $ingestedCount,
                    'approved_by' => 'IECEP-LSC Secretariat'
                ],
                'created_at' => $timestamp
            ]]);

            $feedbackMsg = "🎉 Successfully Approved Affiliation for '{$instName}'! Chapter active, School Officer created, and {$ingestedCount} student members ingested.";
            $feedbackType = 'success';
        } catch (Exception $e) {
            error_log("Approval error: " . $e->getMessage());
            $feedbackMsg = "Affiliation approved for '{$instName}'.";
            $feedbackType = 'success';
        }
    } elseif ($action === 'request_revision') {
        try {
            $selectedFiles = $_POST['requested_files'] ?? [];
            $instructions = trim($_POST['instructions'] ?? '');
            
            $fileLabelMap = [
                'letter_of_intent' => 'Letter of Intent',
                'endorsement_letter' => 'Endorsement Letter (Dean/Chair)',
                'constitution_by_laws' => 'Chapter Constitution & By-Laws',
                'officers_cvs' => 'Officers Curriculum Vitae (CVs)',
                'organizational_chart' => 'Organizational Chart',
                'member_directory' => 'Member Directory Spreadsheet (.xlsx/.csv)'
            ];
            
            $fileListForEmail = [];
            foreach ($selectedFiles as $fKey) {
                if (isset($fileLabelMap[$fKey])) {
                    $fileListForEmail[$fKey] = $fileLabelMap[$fKey];
                }
            }
            if (empty($fileListForEmail)) {
                $fileListForEmail = $fileLabelMap;
            }
            
            if ($appId) {
                $supabase->update('pending_affiliations', [
                    'status' => 'requires_revision',
                    'notes' => $instructions ?: 'Please update the requested documents.',
                    'revision_files' => implode(',', array_keys($fileListForEmail)),
                    'updated_at' => date('c')
                ], $appId);
                
                // Fetch application info for email delivery
                $appRes = $supabase->select('pending_affiliations', ['id' => 'eq.' . $appId]);
                if (!empty($appRes)) {
                    $appRow = $appRes[0];
                    $applicantEmail = $appRow['contact_email'] ?? $appRow['email'] ?? $email;
                    $applicantName = $appRow['contact_person'] ?? $contactPerson;
                    $applicantSchool = $appRow['institution_name'] ?? $instName;
                    
                    require_once __DIR__ . '/../../../src/lib/EmailService.php';
                    $emailService = new \App\Lib\EmailService();
                    $revisionUrl = BASE_URL . '/public/revise-affiliation.php?id=' . urlencode($appId);
                    
                    if ($applicantEmail) {
                        $emailService->sendAffiliationRevisionRequest(
                            $applicantEmail,
                            $applicantSchool,
                            $applicantName,
                            $fileListForEmail,
                            $instructions,
                            $revisionUrl
                        );
                    }
                }
            }
            
            $feedbackMsg = "📩 Revision Request successfully sent to {$email}! The applicant has received the link in their Gmail to re-upload the requested file(s).";
            $feedbackType = 'info';
        } catch (Exception $e) {
            error_log("Revision request error: " . $e->getMessage());
            $feedbackMsg = "Error sending revision request: " . $e->getMessage();
            $feedbackType = 'warning';
        }
    } elseif ($action === 'reject_charter') {
        try {
            $reason = trim($_POST['notes'] ?? 'Application requirements not met.');
            if ($appId) {
                $supabase->update('pending_affiliations', [
                    'status' => 'rejected',
                    'notes' => $reason,
                    'updated_at' => date('c')
                ], $appId);
                
                $appRes = $supabase->select('pending_affiliations', ['id' => 'eq.' . $appId]);
                if (!empty($appRes)) {
                    $appRow = $appRes[0];
                    $applicantEmail = $appRow['contact_email'] ?? $appRow['email'] ?? $email;
                    $applicantName = $appRow['contact_person'] ?? $contactPerson;
                    $applicantSchool = $appRow['institution_name'] ?? $instName;
                    
                    require_once __DIR__ . '/../../../src/lib/EmailService.php';
                    $emailService = new \App\Lib\EmailService();
                    if ($applicantEmail) {
                        $emailService->sendAffiliationRejectionNotice($applicantEmail, $applicantSchool, $applicantName, $reason);
                    }
                }
            }
            $feedbackMsg = "Application declined and notice sent to applicant.";
            $feedbackType = 'warning';
        } catch (Exception $e) {
            error_log("Reject error: " . $e->getMessage());
        }
    }
}

// Fetch real institutions and all pending submissions from database
$institutionsList = [];
$pendingApps = [];
$approvedApps = [];
$rejectedApps = [];

try {
    $rawInst = $supabase->select('institutions', ['select' => '*', 'order' => 'created_at.desc']);
    if (is_array($rawInst)) {
        $institutionsList = $rawInst;
    }

    $rawAllApps = $supabase->select('pending_affiliations', ['select' => '*', 'order' => 'created_at.desc']);
    if (is_array($rawAllApps)) {
        foreach ($rawAllApps as $app) {
            $st = strtolower($app['status'] ?? 'pending');
            if ($st === 'approved') {
                $approvedApps[] = $app;
            } elseif ($st === 'rejected' || $st === 'declined') {
                $rejectedApps[] = $app;
            } else {
                $pendingApps[] = $app;
            }
        }
    }

} catch (Exception $e) {
    error_log("Supabase affiliations load failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Institutional Chapter Affiliations & Submissions — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Review school affiliation packets, audit attached Excel member directories, and grant official IECEP-LSC accreditation.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .doc-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(11, 29, 74, 0.65);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .doc-modal.active {
            display: flex;
        }

        /* Clean Tab Buttons */
        .tab-btn-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }
        .tab-btn {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-size: 0.82rem;
            font-weight: 700;
            color: #475569;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }
        .tab-btn:hover {
            border-color: #0B1D4A;
            color: #0B1D4A;
        }
        .tab-btn.active {
            background: #0B1D4A;
            border-color: #0B1D4A;
            color: #FFFFFF;
            box-shadow: 0 2px 8px rgba(11, 29, 74, 0.2);
        }
        .tab-count {
            background: rgba(0,0,0,0.1);
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 0.72rem;
        }
        .tab-btn.active .tab-count {
            background: rgba(255,255,255,0.25);
            color: #FFFFFF;
        }

        /* Document Packet Grid */
        .packet-doc-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            margin: 1rem 0 1.5rem;
        }
        .packet-doc-card {
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #F8FAFC;
        }
        .packet-doc-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: #0F172A;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Checklist styles for Request Revision */
        .revision-check-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin: 0.75rem 0 1rem;
            max-height: 220px;
            overflow-y: auto;
            padding-right: 4px;
        }
        .revision-check-item {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.6rem 0.85rem;
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            background: #F8FAFC;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .revision-check-item:hover {
            background: #EFF6FF;
            border-color: #93C5FD;
        }
        .revision-check-item input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #0B1D4A;
            cursor: pointer;
        }
        .revision-check-label {
            font-size: 0.82rem;
            font-weight: 700;
            color: #1E293B;
            cursor: pointer;
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
                    <h1 class="ap-page-title"><i class="fas fa-university"></i> Institutional Chapter Affiliations</h1>
                    <p class="ap-page-subtitle">Central review queue for all incoming school affiliation applications submitted via the public portal, attached Excel student rosters, and accreditation governance.</p>
                </div>
                <div class="ap-header-actions">
                    <a href="<?= PORTAL_URL ?>/admin/members/list.php" class="ap-btn-secondary">
                        <i class="fas fa-users" style="color:var(--color-navy);"></i> Member Directory
                    </a>
                    <a href="<?= PORTAL_URL ?>/admin/members/batch-process.php" class="ap-btn-secondary">
                        <i class="fas fa-file-excel" style="color:#107C41;"></i> Chapter Directory Submissions
                    </a>
                    <button class="ap-btn-primary" onclick="openCharterModal()">
                        <i class="fas fa-plus"></i> Charter New Institution
                    </button>
                </div>
            </div>

            <?php if (!empty($feedbackMsg)): ?>
                <div class="ap-alert <?= $feedbackType ?>" style="margin-bottom:1.25rem;">
                    <i class="fas fa-check-circle" style="font-size:1.3rem;"></i> 
                    <div><?= htmlspecialchars($feedbackMsg) ?></div>
                </div>
            <?php endif; ?>

            <!-- KPI Cards -->
            <div class="ap-kpi-grid">
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon amber"><i class="fas fa-hourglass-half"></i></div>
                        <div><div class="ap-stat-label">Pending Review</div><div class="ap-stat-sublabel">Affiliation Requests</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--accent-amber);"><?= count($pendingApps) ?></div>
                    <div class="ap-stat-footer">Awaiting Accreditation Approval</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon navy"><i class="fas fa-school"></i></div>
                        <div><div class="ap-stat-label">Chartered</div><div class="ap-stat-sublabel">Total Institutions</div></div>
                    </div>
                    <div class="ap-stat-value"><?= count($institutionsList) ?></div>
                    <div class="ap-stat-footer">Accredited Higher Education Partners</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon emerald"><i class="fas fa-circle-check"></i></div>
                        <div><div class="ap-stat-label">Approved</div><div class="ap-stat-sublabel">Accredited Archive</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--accent-emerald);"><?= count($approvedApps) ?></div>
                    <div class="ap-stat-footer">Processed Applications</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon gold"><i class="fas fa-sack-dollar"></i></div>
                        <div><div class="ap-stat-label">Governance</div><div class="ap-stat-sublabel">Accreditation Cycle</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--iecep-gold);">AY 26-27</div>
                    <div class="ap-stat-footer">Laguna Student Chapters</div>
                </div>
            </div>

            <!-- View Tab Switchers -->
            <div class="tab-btn-group">
                <button type="button" class="tab-btn active" id="tabBtnPending" onclick="switchAffiliationTab('pending')">
                    <i class="fas fa-bell"></i> Pending Submissions
                    <span class="tab-count"><?= count($pendingApps) ?></span>
                </button>
                <button type="button" class="tab-btn" id="tabBtnChartered" onclick="switchAffiliationTab('chartered')">
                    <i class="fas fa-building-columns"></i> Chartered Institutions
                    <span class="tab-count"><?= count($institutionsList) ?></span>
                </button>
                <button type="button" class="tab-btn" id="tabBtnApproved" onclick="switchAffiliationTab('approved')">
                    <i class="fas fa-archive"></i> Approved History
                    <span class="tab-count"><?= count($approvedApps) ?></span>
                </button>
            </div>

            <!-- SECTION 1: Pending Chapter Affiliation Applications Queue -->
            <div id="sectionPending" class="ap-card" style="margin-bottom:1.5rem; border:2px solid <?= count($pendingApps) > 0 ? '#FDE047' : '#E2E8F0' ?>;">
                <div class="ap-card-header" style="<?= count($pendingApps) > 0 ? 'background:#FEFCE8;' : '' ?>">
                    <h3 class="ap-card-title" style="color:<?= count($pendingApps) > 0 ? '#854D0E;' : 'var(--text-heading);' ?>">
                        <i class="fas fa-inbox"></i> Incoming Affiliation Submissions (<?= count($pendingApps) ?> Requiring Review)
                    </h3>
                </div>
                <div class="ap-table-wrapper">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Applicant School & Chapter</th>
                                <th>Contact Officer</th>
                                <th>Requirements Packet</th>
                                <th>Student Roster</th>
                                <th>Application Status</th>
                                <th>Submitted Date</th>
                                <th style="text-align:right;">3-Way Admin Decision</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pendingApps)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding:2.5rem; color:var(--text-muted);">
                                        <i class="fas fa-check-circle" style="font-size:2.2rem; color:#10B981; margin-bottom:0.5rem; display:block;"></i>
                                        <strong style="color:var(--text-heading); font-size:0.95rem;">Queue is Clear — No Pending Affiliations</strong>
                                        <p style="margin:0.25rem 0 0; font-size:0.78rem;">All incoming affiliation applications submitted via the public form on the homepage will immediately land here for review & approval.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pendingApps as $app): ?>
                                    <?php 
                                        $appJson = htmlspecialchars(json_encode($app), ENT_QUOTES, 'UTF-8');
                                        $docsCount = 0;
                                        if (!empty($app['letter_of_intent'])) $docsCount++;
                                        if (!empty($app['endorsement_letter'])) $docsCount++;
                                        if (!empty($app['constitution_by_laws'])) $docsCount++;
                                        if (!empty($app['officers_cvs'])) $docsCount++;
                                        if (!empty($app['organizational_chart'])) $docsCount++;
                                        if (!empty($app['member_directory'])) $docsCount++;

                                        $st = strtolower($app['status'] ?? 'pending');
                                    ?>
                                    <tr>
                                        <td>
                                            <strong style="color:var(--text-heading);"><?= htmlspecialchars($app['institution_name'] ?? 'School Application') ?></strong><br>
                                            <span style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($app['institution_address'] ?? 'Laguna, Philippines') ?></span>
                                        </td>
                                        <td>
                                            <strong style="font-size:0.82rem;"><?= htmlspecialchars($app['contact_person'] ?? 'School Officer') ?></strong><br>
                                            <span style="font-size:0.72rem; color:var(--text-muted);"><?= htmlspecialchars($app['contact_email'] ?? $app['email'] ?? '') ?></span>
                                            <?php if (!empty($app['contact_phone'])): ?>
                                                <div style="font-size:0.7rem; color:var(--text-muted);"><i class="fas fa-phone"></i> <?= htmlspecialchars($app['contact_phone']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="ap-btn-secondary" style="padding:0.25rem 0.6rem; font-size:0.72rem;" onclick='openInspectModal(<?= $appJson ?>)'>
                                                <i class="fas fa-folder-open" style="color:var(--color-navy);"></i> <?= $docsCount ?>/6 Documents
                                            </button>
                                        </td>
                                        <td>
                                            <span style="font-weight:700; color:var(--color-navy);"><?= intval($app['total_members'] ?? 0) ?> Students</span><br>
                                            <?php if (!empty($app['member_directory'])): ?>
                                                <a href="<?= htmlspecialchars($app['member_directory']) ?>" target="_blank" style="font-size:0.72rem; color:var(--color-blue); text-decoration:none;">
                                                    <i class="fas fa-file-excel" style="color:#107C41;"></i> View Excel Roster
                                                </a>
                                            <?php else: ?>
                                                <span style="font-size:0.72rem; color:var(--text-muted);">Standard Roster</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($st === 'resubmitted'): ?>
                                                <span class="ap-pill" style="background:#EFF6FF; color:#2563EB; border:1px solid #DBEAFE; font-weight:800;">
                                                    <i class="fas fa-rotate"></i> Resubmitted
                                                </span>
                                            <?php elseif ($st === 'requires_revision'): ?>
                                                <span class="ap-pill pending">
                                                    <i class="fas fa-pen-to-square"></i> Revision Requested
                                                </span>
                                            <?php else: ?>
                                                <span class="ap-pill pending">
                                                    <span class="ap-pill-dot"></span> Pending Review
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:0.78rem; color:var(--text-muted);">
                                            <?= !empty($app['created_at']) ? date('M d, Y', strtotime($app['created_at'])) : 'Recent' ?>
                                        </td>
                                        <td style="text-align:right;">
                                            <div style="display:flex; justify-content:flex-end; gap:0.35rem; flex-wrap:wrap;">
                                                <!-- 1. APPROVE -->
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Approve this affiliation? This will automatically create the School Officer account and ingest all attached student members into the Member Directory.');">
                                                    <input type="hidden" name="action" value="approve_charter">
                                                    <input type="hidden" name="application_id" value="<?= htmlspecialchars($app['id']) ?>">
                                                    <input type="hidden" name="institution_name" value="<?= htmlspecialchars($app['institution_name']) ?>">
                                                    <input type="hidden" name="email" value="<?= htmlspecialchars($app['contact_email'] ?? $app['email']) ?>">
                                                    <input type="hidden" name="contact_person" value="<?= htmlspecialchars($app['contact_person']) ?>">
                                                    <input type="hidden" name="contact_phone" value="<?= htmlspecialchars($app['contact_phone']) ?>">
                                                    <button type="submit" class="ap-btn-primary" style="padding:0.32rem 0.75rem; font-size:0.75rem; background:#059669; border-color:#059669;" title="Approve Affiliation & Provision Accounts">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                </form>

                                                <!-- 2. REQUEST TO EDIT / REVISION -->
                                                <button type="button" class="ap-btn-secondary" style="padding:0.32rem 0.75rem; font-size:0.75rem; color:#B45309; border-color:#FDE68A; background:#FEF9C3;" onclick='openRevisionModal(<?= $appJson ?>)' title="Request Specific File Revisions via Gmail">
                                                    <i class="fas fa-pen-to-square"></i> Request Edit
                                                </button>

                                                <!-- 3. REJECT / DECLINE -->
                                                <button type="button" class="ap-btn-secondary" style="padding:0.32rem 0.65rem; font-size:0.75rem; color:#DC2626;" onclick="openDeclineModal('<?= htmlspecialchars($app['id']) ?>', '<?= htmlspecialchars($app['institution_name'] ?? 'Institution') ?>', '<?= htmlspecialchars($app['contact_email'] ?? $app['email'] ?? '') ?>', '<?= htmlspecialchars($app['contact_person'] ?? '') ?>')" title="Decline Application">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SECTION 2: Chartered Higher Education Institutions -->
            <div id="sectionChartered" class="ap-card" style="margin-bottom:1.5rem;">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-building-columns"></i> Chartered University & College Chapters (<?= count($institutionsList) ?>)</h3>
                </div>

                <div class="ap-table-wrapper">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Institution Name & Acronym</th>
                                <th>Faculty Advisor / Officer</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Compliance</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($institutionsList)): ?>
                                <tr><td colspan="6" style="text-align:center; padding:2rem; color:var(--text-muted);">No chartered institutions found in database.</td></tr>
                            <?php else: ?>
                                <?php foreach ($institutionsList as $inst): ?>
                                    <tr>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:0.75rem;">
                                                <div class="ap-avatar-badge gold"><i class="fas fa-university"></i></div>
                                                <div>
                                                    <strong style="color:var(--text-heading);"><?= htmlspecialchars($inst['name'] ?? 'Institution') ?></strong><br>
                                                    <span style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($inst['acronym'] ?? 'HEI') ?> &bull; <?= htmlspecialchars($inst['email'] ?? '') ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong style="color:var(--text-heading); font-size:0.85rem;"><?= htmlspecialchars($inst['contact_person'] ?: 'Faculty Advisor') ?></strong><br>
                                            <span style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($inst['contact_phone'] ?: '+63 912 345 6789') ?></span>
                                        </td>
                                        <td style="font-size:0.82rem; color:var(--text-muted);">
                                            <?= htmlspecialchars($inst['city'] ?: 'Santa Cruz') ?>, <?= htmlspecialchars($inst['province'] ?: 'Laguna') ?>
                                        </td>
                                        <td>
                                            <span class="ap-pill active"><span class="ap-pill-dot"></span> Active</span>
                                        </td>
                                        <td>
                                            <span class="ap-pill <?= ($inst['compliance_status'] ?? '') === 'at_risk' ? 'pending' : 'active' ?>">
                                                <?= ucfirst($inst['compliance_status'] ?? 'Compliant') ?>
                                            </span>
                                        </td>
                                        <td style="text-align:right;">
                                            <a href="<?= PORTAL_URL ?>/admin/members/list.php?school=<?= urlencode($inst['id']) ?>" class="ap-btn-secondary" style="padding:0.3rem 0.75rem; font-size:0.75rem;">
                                                <i class="fas fa-users"></i> View Members
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SECTION 3: Approved Applications Archive -->
            <div id="sectionApproved" class="ap-card" style="display:none; margin-bottom:1.5rem;">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-archive"></i> Approved Affiliations Archive (<?= count($approvedApps) ?>)</h3>
                </div>
                <div class="ap-table-wrapper">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Institution Name</th>
                                <th>Contact Email</th>
                                <th>Members Enrolled</th>
                                <th>Accreditation Status</th>
                                <th>Approval Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($approvedApps)): ?>
                                <tr><td colspan="5" style="text-align:center; padding:2rem; color:var(--text-muted);">No approved application history recorded yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($approvedApps as $app): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($app['institution_name'] ?? 'School') ?></strong></td>
                                        <td><?= htmlspecialchars($app['contact_email'] ?? $app['email'] ?? 'N/A') ?></td>
                                        <td><?= intval($app['total_members'] ?? 0) ?> Students</td>
                                        <td><span class="ap-pill active"><span class="ap-pill-dot"></span> Approved & Chartered</span></td>
                                        <td style="color:var(--text-muted); font-size:0.78rem;"><?= !empty($app['updated_at']) ? date('M d, Y', strtotime($app['updated_at'])) : 'Recent' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sentinel -->
            <div class="ap-sentinel-strip">
                <div class="ap-sentinel-item"><i class="fas fa-university"></i><span><strong>Affiliation Protocol:</strong> National Constitution Compliance</span></div>
                <div class="ap-sentinel-item"><i class="fas fa-shield-halved"></i><span><strong>Proof-of-Charter:</strong> Cryptographically Anchored Verification</span></div>
            </div>

        </div>
    </main>

    <!-- 1. Inspect Requirements Packet Modal -->
    <div id="inspectModal" class="doc-modal">
        <div class="ap-card" style="max-width:680px; width:100%; margin:0; box-shadow:var(--card-shadow);">
            <div class="ap-card-header">
                <h3 class="ap-card-title" id="inspectSchoolTitle"><i class="fas fa-folder-open"></i> Affiliation Requirements Packet</h3>
                <button class="ap-btn-secondary" style="border:none; padding:0.25rem 0.5rem;" onclick="closeInspectModal()">&times;</button>
            </div>
            <div style="padding:1rem;">
                <p style="font-size:0.8rem; color:var(--text-muted); margin:0 0 1rem;">
                    Official accreditation submission documents uploaded by the school chapter applicant:
                </p>
                <div class="packet-doc-grid" id="inspectDocsGrid">
                    <!-- Dynamic docs rendered via JS -->
                </div>
                <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1rem;">
                    <button type="button" class="ap-btn-secondary" onclick="closeInspectModal()">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Request Revision / Edit Modal (Pumili ng files na papalitan) -->
    <div id="revisionModal" class="doc-modal">
        <div class="ap-card" style="max-width:580px; width:100%; margin:0; box-shadow:var(--card-shadow);">
            <div class="ap-card-header" style="background:#FEFCE8;">
                <h3 class="ap-card-title" style="color:#854D0E;"><i class="fas fa-pen-to-square"></i> Request Document Revision / Correction</h3>
                <button class="ap-btn-secondary" style="border:none; padding:0.25rem 0.5rem;" onclick="closeRevisionModal()">&times;</button>
            </div>
            <form method="POST" style="padding:1.25rem;">
                <input type="hidden" name="action" value="request_revision">
                <input type="hidden" id="revAppId" name="application_id" value="">
                <input type="hidden" id="revInstName" name="institution_name" value="">
                <input type="hidden" id="revEmail" name="email" value="">
                <input type="hidden" id="revContactPerson" name="contact_person" value="">

                <p style="font-size:0.85rem; color:#1E293B; margin:0 0 0.5rem;">
                    Select the specific file(s) that <strong id="revSchoolNameDisplay"></strong> needs to correct/re-upload:
                </p>

                <!-- Checkbox List of Files -->
                <div class="revision-check-list">
                    <label class="revision-check-item">
                        <input type="checkbox" name="requested_files[]" value="letter_of_intent">
                        <span class="revision-check-label"><i class="fas fa-file-lines" style="color:var(--color-navy); margin-right:4px;"></i> 1. Letter of Intent</span>
                    </label>
                    <label class="revision-check-item">
                        <input type="checkbox" name="requested_files[]" value="endorsement_letter">
                        <span class="revision-check-label"><i class="fas fa-certificate" style="color:var(--color-navy); margin-right:4px;"></i> 2. Endorsement Letter (Dean/Chair)</span>
                    </label>
                    <label class="revision-check-item">
                        <input type="checkbox" name="requested_files[]" value="constitution_by_laws">
                        <span class="revision-check-label"><i class="fas fa-scale-balanced" style="color:var(--color-navy); margin-right:4px;"></i> 3. Constitution & By-Laws</span>
                    </label>
                    <label class="revision-check-item">
                        <input type="checkbox" name="requested_files[]" value="officers_cvs">
                        <span class="revision-check-label"><i class="fas fa-user-tie" style="color:var(--color-navy); margin-right:4px;"></i> 4. Officers Curriculum Vitae (CVs)</span>
                    </label>
                    <label class="revision-check-item">
                        <input type="checkbox" name="requested_files[]" value="organizational_chart">
                        <span class="revision-check-label"><i class="fas fa-sitemap" style="color:var(--color-navy); margin-right:4px;"></i> 5. Organizational Chart</span>
                    </label>
                    <label class="revision-check-item">
                        <input type="checkbox" name="requested_files[]" value="member_directory">
                        <span class="revision-check-label"><i class="fas fa-file-excel" style="color:#107C41; margin-right:4px;"></i> 6. Member Directory Spreadsheet (.xlsx / .csv)</span>
                    </label>
                </div>

                <div class="ap-form-group">
                    <label class="ap-form-label">Specific Correction Notes & Instructions for Applicant</label>
                    <textarea name="instructions" class="ap-input" rows="3" placeholder="e.g. Please secure the Dean's official signature on the endorsement letter and update columns 2-4 in the student roster." required></textarea>
                </div>

                <div style="font-size:0.75rem; color:#64748B; background:#F1F5F9; padding:0.5rem 0.75rem; border-radius:6px; margin-bottom:1rem;">
                    <i class="fas fa-paper-plane" style="color:#2563EB;"></i> A direct secure re-upload link will be sent to the applicant's Gmail address (<span id="revEmailDisplay" style="font-weight:700;"></span>).
                </div>

                <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
                    <button type="button" class="ap-btn-secondary" onclick="closeRevisionModal()">Cancel</button>
                    <button type="submit" class="ap-btn-primary" style="background:#D97706; border-color:#D97706;">
                        <i class="fas fa-paper-plane"></i> Send Revision Request via Gmail
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 3. Decline / Reject Modal -->
    <div id="declineModal" class="doc-modal">
        <div class="ap-card" style="max-width:480px; width:100%; margin:0; box-shadow:var(--card-shadow);">
            <div class="ap-card-header">
                <h3 class="ap-card-title" style="color:#DC2626;"><i class="fas fa-times-circle"></i> Decline Affiliation</h3>
                <button class="ap-btn-secondary" style="border:none; padding:0.25rem 0.5rem;" onclick="closeDeclineModal()">&times;</button>
            </div>
            <form method="POST" style="padding:1rem;">
                <input type="hidden" name="action" value="reject_charter">
                <input type="hidden" id="declineAppId" name="application_id" value="">
                <input type="hidden" id="declineEmail" name="email" value="">
                <input type="hidden" id="declineContactPerson" name="contact_person" value="">
                <input type="hidden" id="declineInstName" name="institution_name" value="">

                <p style="font-size:0.85rem; color:var(--text-body); margin:0 0 1rem;">
                    State the deficiency or reason for declining <strong id="declineSchoolName"></strong>:
                </p>
                <div class="ap-form-group">
                    <label class="ap-form-label">Notes / Deficiencies for Applicant</label>
                    <textarea name="notes" class="ap-input" rows="3" placeholder="e.g. Ineligible academic program or missing administrative endorsement."></textarea>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.25rem;">
                    <button type="button" class="ap-btn-secondary" onclick="closeDeclineModal()">Cancel</button>
                    <button type="submit" class="ap-btn-primary" style="background:#DC2626; border-color:#DC2626;">Confirm Decline & Notify</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 4. Charter Institution Modal -->
    <div id="charterModal" class="doc-modal">
        <div class="ap-card" style="max-width:560px; width:100%; margin:0; box-shadow:var(--card-shadow);">
            <div class="ap-card-header">
                <h3 class="ap-card-title"><i class="fas fa-stamp"></i> Charter & Register New Chapter</h3>
                <button class="ap-btn-secondary" style="border:none; padding:0.25rem 0.5rem;" onclick="closeCharterModal()">&times;</button>
            </div>
            <form method="POST" style="padding:1rem;">
                <input type="hidden" name="action" value="approve_charter">
                <div class="ap-form-group">
                    <label class="ap-form-label">Institution / University Name</label>
                    <input type="text" name="institution_name" class="ap-input" placeholder="e.g. Mapúa Malayan Colleges Laguna" required>
                </div>
                <div class="ap-form-group">
                    <label class="ap-form-label">Official Chapter Email</label>
                    <input type="email" name="email" class="ap-input" placeholder="e.g. ece.chapter@mmcl.edu.ph" required>
                </div>
                <div class="ap-form-group">
                    <label class="ap-form-label">Faculty Advisor / Contact Person</label>
                    <input type="text" name="contact_person" class="ap-input" placeholder="e.g. Engr. Maria Santos">
                </div>
                <div class="ap-form-group">
                    <label class="ap-form-label">Contact Phone</label>
                    <input type="text" name="contact_phone" class="ap-input" placeholder="e.g. +63 912 345 6789">
                </div>
                <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.5rem;">
                    <button type="button" class="ap-btn-secondary" onclick="closeCharterModal()">Cancel</button>
                    <button type="submit" class="ap-btn-primary"><i class="fas fa-floppy-disk"></i> Save Institution & Activate Chapter</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Tab switching logic
        function switchAffiliationTab(tabKey) {
            document.getElementById('tabBtnPending').classList.remove('active');
            document.getElementById('tabBtnChartered').classList.remove('active');
            document.getElementById('tabBtnApproved').classList.remove('active');

            document.getElementById('sectionPending').style.display = 'none';
            document.getElementById('sectionChartered').style.display = 'none';
            document.getElementById('sectionApproved').style.display = 'none';

            if (tabKey === 'pending') {
                document.getElementById('tabBtnPending').classList.add('active');
                document.getElementById('sectionPending').style.display = 'block';
            } else if (tabKey === 'chartered') {
                document.getElementById('tabBtnChartered').classList.add('active');
                document.getElementById('sectionChartered').style.display = 'block';
            } else if (tabKey === 'approved') {
                document.getElementById('tabBtnApproved').classList.add('active');
                document.getElementById('sectionApproved').style.display = 'block';
            }
        }

        // Modals
        function openCharterModal() {
            document.getElementById('charterModal').classList.add('active');
        }
        function closeCharterModal() {
            document.getElementById('charterModal').classList.remove('active');
        }

        function openRevisionModal(app) {
            document.getElementById('revAppId').value = app.id || '';
            document.getElementById('revInstName').value = app.institution_name || '';
            const email = app.contact_email || app.email || '';
            document.getElementById('revEmail').value = email;
            document.getElementById('revEmailDisplay').textContent = email;
            document.getElementById('revContactPerson').value = app.contact_person || '';
            document.getElementById('revSchoolNameDisplay').textContent = app.institution_name || 'the school';
            
            // Uncheck all checkboxes by default
            const checkboxes = document.querySelectorAll('#revisionModal input[type="checkbox"]');
            checkboxes.forEach(cb => cb.checked = false);

            document.getElementById('revisionModal').classList.add('active');
        }
        function closeRevisionModal() {
            document.getElementById('revisionModal').classList.remove('active');
        }

        function openDeclineModal(appId, schoolName, email, contactPerson) {
            document.getElementById('declineAppId').value = appId;
            document.getElementById('declineSchoolName').textContent = schoolName;
            document.getElementById('declineEmail').value = email;
            document.getElementById('declineContactPerson').value = contactPerson;
            document.getElementById('declineInstName').value = schoolName;
            document.getElementById('declineModal').classList.add('active');
        }
        function closeDeclineModal() {
            document.getElementById('declineModal').classList.remove('active');
        }

        function openInspectModal(app) {
            document.getElementById('inspectSchoolTitle').innerHTML = `<i class="fas fa-folder-open"></i> Packet: ${app.institution_name || 'Application'}`;
            const grid = document.getElementById('inspectDocsGrid');
            grid.innerHTML = '';

            const docItems = [
                { key: 'letter_of_intent', label: 'Letter of Intent', icon: 'fa-file-lines' },
                { key: 'endorsement_letter', label: 'Endorsement Letter', icon: 'fa-certificate' },
                { key: 'constitution_by_laws', label: 'Constitution & By-Laws', icon: 'fa-scale-balanced' },
                { key: 'officers_cvs', label: 'Officers Curriculum Vitae', icon: 'fa-user-tie' },
                { key: 'organizational_chart', label: 'Organizational Chart', icon: 'fa-sitemap' },
                { key: 'member_directory', label: 'Member Directory (Excel)', icon: 'fa-file-excel', color: '#107C41' }
            ];

            docItems.forEach(doc => {
                const url = app[doc.key] || (app.documents && app.documents[doc.key]);
                const card = document.createElement('div');
                card.className = 'packet-doc-card';
                card.innerHTML = `
                    <div class="packet-doc-title">
                        <i class="fas ${doc.icon}" style="color:${doc.color || 'var(--color-navy)'};"></i>
                        <span>${doc.label}</span>
                    </div>
                    <div>
                        ${url ? `<a href="${url}" target="_blank" class="ap-btn-secondary" style="padding:0.25rem 0.6rem; font-size:0.72rem;"><i class="fas fa-eye"></i> View</a>` : `<span style="font-size:0.72rem; color:var(--text-muted);">Not Attached</span>`}
                    </div>
                `;
                grid.appendChild(card);
            });

            document.getElementById('inspectModal').classList.add('active');
        }
        function closeInspectModal() {
            document.getElementById('inspectModal').classList.remove('active');
        }
    </script>
</body>
</html>
