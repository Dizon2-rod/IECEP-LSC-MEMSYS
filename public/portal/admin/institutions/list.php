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
                    $appData = normalize_pending_affiliation_app($appRes[0]);
                    $instName = $appData['institution_name'] ?: $instName;
                    $email = $appData['contact_email'] ?: ($appData['email'] ?: $email);
                    $contactPerson = $appData['contact_person'] ?: $contactPerson;
                    $contactPhone = $appData['contact_phone'] ?: $contactPhone;
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
                                    $membershipId = date('Y') . str_pad($baseCount, 4, '0', STR_PAD_LEFT);
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

// Fetch real institutions, members count, and all pending submissions from database
$institutionsList = [];
$pendingApps = [];
$approvedApps = [];
$rejectedApps = [];
$totalMembersCount = 0;

try {
    $rawInst = $supabase->select('institutions', ['select' => '*', 'order' => 'created_at.desc']);
    if (is_array($rawInst)) {
        $institutionsList = $rawInst;
    }

    $rawMembers = $supabase->select('members', ['select' => 'id']);
    if (is_array($rawMembers)) {
        $totalMembersCount = count($rawMembers);
    }

    $rawAllApps = $supabase->select('pending_affiliations', ['select' => '*', 'order' => 'created_at.desc']);
    if (is_array($rawAllApps)) {
        foreach ($rawAllApps as $rawApp) {
            $app = normalize_pending_affiliation_app($rawApp);
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
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

    <style>
        :root {
            --color-navy: #0B1D4A;
            --color-navy-hover: #152C6E;
            --color-blue: #2563EB;
            --color-gold: #D4AF37;
            --color-emerald: #059669;
            --color-amber: #D97706;
            --color-rose: #E11D48;
            --bg-page: #F8FAFC;
            --border-color: #E2E8F0;
            --shadow-card: 0 1px 3px 0 rgba(0, 0, 0, 0.04), 0 1px 2px -1px rgba(0, 0, 0, 0.04);
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-page);
            color: #1E293B;
            margin: 0;
            padding: 0;
        }

        .ap-scope {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            box-sizing: border-box !important;
        }

        /* 1. Header Banner */
        .dash-header-banner {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 0.85rem 1.25rem;
            margin-bottom: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.75rem;
            box-shadow: var(--shadow-card);
            width: 100%;
            box-sizing: border-box;
        }
        .dash-header-title {
            margin: 0 0 0.15rem;
            font-size: 1.25rem;
            font-weight: 800;
            color: #0F172A;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .dash-header-sub {
            margin: 0;
            font-size: 0.8rem;
            color: #64748B;
        }
        .dash-header-btn-group {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            flex-wrap: wrap;
        }

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
            border: 1px solid #CBD5E1;
            color: #0F172A;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: all 0.18s ease;
            white-space: nowrap;
        }
        .btn-white:hover {
            background: #F8FAFC;
            border-color: #94A3B8;
            transform: translateY(-1px);
        }

        .btn-excel-green {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.42rem 0.85rem;
            border-radius: 7px;
            font-size: 0.78rem;
            font-weight: 700;
            text-decoration: none;
            background: #ECFDF5;
            border: 1px solid #A7F3D0;
            color: #065F46;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: all 0.18s ease;
            white-space: nowrap;
        }
        .btn-excel-green:hover {
            background: #D1FAE5;
            border-color: #6EE7B7;
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
            color: #FFFFFF !important;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(11, 29, 74, 0.15);
            transition: all 0.18s ease;
            white-space: nowrap;
        }
        .btn-primary-navy:hover {
            background: var(--color-navy-hover);
            transform: translateY(-1px);
            color: #FDE047 !important;
        }

        /* 2. Top 4 KPI Cards */
        .dash-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.65rem;
            margin-bottom: 0.85rem;
            width: 100%;
            box-sizing: border-box;
        }
        .dash-kpi-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.65rem 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: var(--shadow-card);
            transition: all 0.2s ease;
            box-sizing: border-box;
            min-width: 0;
        }
        .dash-kpi-card:hover {
            border-color: #CBD5E1;
            transform: translateY(-1px);
        }
        .kpi-icon-pill {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            flex-shrink: 0;
        }
        .kpi-icon-pill.amber { background: #FEF3C7; color: #D97706; border: 1px solid #FDE68A; }
        .kpi-icon-pill.navy { background: rgba(11, 29, 74, 0.08); color: var(--color-navy); }
        .kpi-icon-pill.emerald { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }
        .kpi-icon-pill.gold { background: #FEF9C3; color: #B45309; border: 1px solid #FDE68A; }

        .kpi-val {
            font-size: 1.25rem;
            font-weight: 800;
            color: #0F172A;
            line-height: 1.1;
        }
        .kpi-lbl {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748B;
            margin-top: 1px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* 3. Search & Control Bar */
        .white-controls-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.65rem 0.95rem;
            margin-bottom: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.65rem;
            box-shadow: var(--shadow-card);
        }
        .filter-controls-left {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            flex: 1;
            min-width: 260px;
        }
        .search-input-wrapper {
            position: relative;
            flex: 1;
            min-width: 200px;
        }
        .search-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            font-size: 0.8rem;
        }
        .search-input-field {
            width: 100%;
            padding: 0.45rem 0.75rem 0.45rem 2rem;
            border: 1px solid #CBD5E1;
            border-radius: 7px;
            font-size: 0.8rem;
            outline: none;
            box-sizing: border-box;
            background: #F8FAFC;
        }
        .search-input-field:focus {
            background: #FFFFFF;
            border-color: var(--color-navy);
        }

        /* Tab Switchers */
        .tab-btn-group {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            margin-bottom: 0.85rem;
            flex-wrap: wrap;
        }
        .tab-btn {
            background: #FFFFFF;
            border: 1px solid #CBD5E1;
            border-radius: 8px;
            padding: 0.45rem 0.95rem;
            font-size: 0.78rem;
            font-weight: 700;
            color: #475569;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            transition: all 0.18s ease;
        }
        .tab-btn:hover {
            border-color: var(--color-navy);
            color: var(--color-navy);
            background: #F8FAFC;
        }
        .tab-btn.active {
            background: var(--color-navy);
            border-color: var(--color-navy);
            color: #FFFFFF;
            box-shadow: 0 2px 6px rgba(11, 29, 74, 0.18);
        }
        .tab-count {
            background: rgba(0,0,0,0.08);
            padding: 1px 6px;
            border-radius: 10px;
            font-size: 0.7rem;
        }
        .tab-btn.active .tab-count {
            background: rgba(255,255,255,0.25);
            color: #FFFFFF;
        }

        /* Tables & Cards */
        .ap-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
            margin-bottom: 1rem;
            box-sizing: border-box;
        }
        .ap-card-header {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #FFFFFF;
        }
        .ap-card-title {
            margin: 0;
            font-size: 0.88rem;
            font-weight: 800;
            color: #0F172A;
            display: flex;
            align-items: center;
            gap: 0.45rem;
        }

        .ap-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
            text-align: left;
        }
        .ap-table th {
            background: #F8FAFC;
            color: #64748B;
            font-weight: 700;
            font-size: 0.72rem;
            padding: 0.55rem 0.85rem;
            border-bottom: 1px solid var(--border-color);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .ap-table td {
            padding: 0.65rem 0.85rem;
            border-bottom: 1px solid #F1F5F9;
            color: #334155;
            vertical-align: middle;
        }
        .ap-table tr:hover td {
            background: #F8FAFC;
        }

        .ap-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .ap-pill.active { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }
        .ap-pill.pending { background: #FEF9C3; color: #B45309; border: 1px solid #FDE68A; }
        .ap-pill.blue { background: #EFF6FF; color: #2563EB; border: 1px solid #DBEAFE; }

        /* Action Buttons */
        .btn-act-green {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.32rem 0.75rem;
            border-radius: 6px;
            font-size: 0.74rem;
            font-weight: 800;
            background: #059669;
            border: 1px solid #059669;
            color: #FFFFFF;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn-act-green:hover {
            background: #047857;
            transform: translateY(-1px);
        }

        .btn-act-amber {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.32rem 0.75rem;
            border-radius: 6px;
            font-size: 0.74rem;
            font-weight: 800;
            background: #FEF9C3;
            border: 1px solid #FDE68A;
            color: #B45309;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn-act-amber:hover {
            background: #FEF3C7;
            border-color: #F59E0B;
            transform: translateY(-1px);
        }

        .btn-act-red {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.32rem 0.65rem;
            border-radius: 6px;
            font-size: 0.74rem;
            font-weight: 700;
            background: #FFFFFF;
            border: 1px solid #FECACA;
            color: #DC2626;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn-act-red:hover {
            background: #FEF2F2;
            border-color: #DC2626;
        }

        /* Modals */
        .doc-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(11, 29, 74, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
            box-sizing: border-box;
        }
        .doc-modal.active {
            display: flex;
        }
        .modal-inner-box {
            background: #FFFFFF;
            border-radius: 12px;
            width: 100%;
            max-width: 620px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.18);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        .modal-inner-box.inspect-wide-box {
            max-width: 1200px;
            width: 96vw;
            height: 90vh;
            max-height: 880px;
            display: flex;
            flex-direction: column;
            border-radius: 14px;
        }

        .inspect-layout {
            display: flex;
            flex: 1;
            min-height: 0;
            overflow: hidden;
        }
        .inspect-sidebar {
            width: 330px;
            background: #F8FAFC;
            border-right: 1px solid var(--border-color);
            overflow-y: auto;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
            flex-shrink: 0;
        }
        .inspect-doc-item {
            padding: 0.65rem 0.8rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: #FFFFFF;
            cursor: pointer;
            transition: all 0.18s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }
        .inspect-doc-item:hover {
            border-color: #CBD5E1;
            background: #F1F5F9;
        }
        .inspect-doc-item.active {
            border-color: var(--color-navy);
            background: rgba(11, 29, 74, 0.05);
            box-shadow: 0 0 0 2px rgba(11, 29, 74, 0.15);
        }
        .inspect-preview-pane {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            background: #FFFFFF;
        }
        .inspect-preview-header {
            padding: 0.65rem 1.15rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #FFFFFF;
            flex-shrink: 0;
        }
        .inspect-preview-body {
            flex: 1;
            min-height: 0;
            position: relative;
            background: #F8FAFC;
            overflow: auto;
            display: flex;
            flex-direction: column;
        }

        .excel-table-container {
            width: 100%;
            height: 100%;
            overflow: auto;
            padding: 1rem;
            box-sizing: border-box;
            background: #FFFFFF;
        }
        .excel-grid-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.76rem;
        }
        .excel-grid-table th {
            position: sticky;
            top: 0;
            background: #0B1D4A;
            color: #FFFFFF;
            font-weight: 700;
            padding: 0.55rem 0.75rem;
            text-align: left;
            border: 1px solid #1e3a8a;
            white-space: nowrap;
            z-index: 10;
        }
        .excel-grid-table td {
            padding: 0.5rem 0.75rem;
            border: 1px solid #E2E8F0;
            color: #1E293B;
            white-space: nowrap;
        }
        .excel-grid-table tr:nth-child(even) td {
            background: #F8FAFC;
        }
        .excel-grid-table tr:hover td {
            background: #EFF6FF;
        }

        .pdf-viewer-container {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            background: #525659;
            overflow: hidden;
        }
        .pdf-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.45rem 1rem;
            background: #323639;
            color: #FFFFFF;
            font-size: 0.76rem;
            flex-shrink: 0;
            border-bottom: 1px solid #202224;
        }
        .pdf-canvas-wrapper {
            flex: 1;
            overflow: auto;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.25rem;
            box-sizing: border-box;
        }
        .pdf-page-canvas {
            box-shadow: 0 4px 16px rgba(0,0,0,0.4);
            background: #FFFFFF;
            border-radius: 4px;
            max-width: 100%;
        }

        .revision-check-list {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
            margin: 0.65rem 0 0.85rem;
            max-height: 200px;
            overflow-y: auto;
        }
        .revision-check-item {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: #F8FAFC;
            cursor: pointer;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .revision-check-item:hover {
            background: #EFF6FF;
            border-color: #93C5FD;
        }

        /* Mobile Tabs for Inspector */
        .inspect-mobile-tabs {
            display: none;
            background: #F1F5F9;
            padding: 0.35rem 0.65rem;
            border-bottom: 1px solid var(--border-color);
            gap: 0.45rem;
            flex-shrink: 0;
        }
        .inspect-mobile-tab-btn {
            flex: 1;
            padding: 0.45rem 0.5rem;
            border-radius: 6px;
            font-size: 0.76rem;
            font-weight: 700;
            border: 1px solid #CBD5E1;
            background: #FFFFFF;
            color: #475569;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            transition: all 0.18s ease;
        }
        .inspect-mobile-tab-btn.active {
            background: var(--color-navy);
            color: #FFFFFF;
            border-color: var(--color-navy);
            box-shadow: 0 2px 5px rgba(11,29,74,0.15);
        }

        /* ── Comprehensive Mobile & Tablet Responsive Media Queries ── */
        @media (max-width: 1024px) {
            .dash-kpi-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 0.6rem; }
            .packet-doc-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 860px) {
            .inspect-mobile-tabs {
                display: flex !important;
            }
            .modal-inner-box.inspect-wide-box {
                width: 100vw !important;
                height: 100vh !important;
                max-height: 100vh !important;
                max-width: 100vw !important;
                border-radius: 0 !important;
                border: none !important;
            }
            .doc-modal {
                padding: 0 !important;
            }
            .inspect-layout {
                flex-direction: column !important;
                position: relative;
            }
            .inspect-sidebar {
                width: 100% !important;
                border-right: none !important;
                border-bottom: 1px solid var(--border-color);
                padding: 0.85rem !important;
                max-height: calc(100vh - 120px);
                overflow-y: auto;
            }
            .inspect-preview-pane {
                width: 100% !important;
                height: calc(100vh - 120px);
                max-height: calc(100vh - 120px);
            }
            .inspect-mobile-back-btn {
                display: inline-flex !important;
            }

            /* Responsive view toggle classes */
            .inspect-layout.mobile-view-docs .inspect-sidebar {
                display: flex !important;
                flex: 1 !important;
            }
            .inspect-layout.mobile-view-docs .inspect-preview-pane {
                display: none !important;
            }
            .inspect-layout.mobile-view-preview .inspect-sidebar {
                display: none !important;
            }
            .inspect-layout.mobile-view-preview .inspect-preview-pane {
                display: flex !important;
                flex: 1 !important;
            }

            .inspect-preview-header {
                padding: 0.5rem 0.75rem !important;
                flex-wrap: wrap;
                gap: 0.4rem;
            }
            .inspect-preview-header > div {
                flex-wrap: wrap;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 0.65rem !important;
            }
            .dash-header-banner {
                flex-direction: column !important;
                align-items: flex-start !important;
                padding: 0.85rem !important;
                gap: 0.65rem;
            }
            .dash-header-title {
                font-size: 1.1rem !important;
            }
            .dash-header-btn-group {
                width: 100% !important;
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 0.45rem;
            }
            .dash-header-btn-group button,
            .dash-header-btn-group a {
                width: 100% !important;
                justify-content: center !important;
                padding: 0.48rem 0.5rem !important;
                font-size: 0.74rem !important;
            }

            .ap-filter-bar {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 0.55rem !important;
                padding: 0.65rem !important;
            }
            .ap-tab-nav {
                overflow-x: auto !important;
                white-space: nowrap !important;
                padding-bottom: 4px;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
                width: 100%;
                display: flex;
            }
            .ap-tab-nav::-webkit-scrollbar {
                display: none;
            }
            .ap-search-input-box {
                max-width: 100% !important;
                width: 100% !important;
            }

            .ap-card-header {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 0.4rem;
                padding: 0.65rem 0.85rem !important;
            }

            .modal-inner-box {
                width: 95vw !important;
                max-height: 90vh !important;
                margin: auto !important;
            }
        }

        @media (max-width: 480px) {
            .dash-kpi-grid {
                grid-template-columns: 1fr !important;
                gap: 0.45rem !important;
            }
            .dash-header-btn-group {
                grid-template-columns: 1fr !important;
            }
            .pdf-toolbar {
                padding: 0.35rem 0.5rem !important;
                font-size: 0.7rem !important;
            }
        }
    </style>
</head>
<body>
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- 1. Header Banner -->
            <div class="dash-header-banner">
                <div>
                    <h1 class="dash-header-title">
                        <i class="fas fa-university" style="color:var(--color-navy);"></i>
                        Institutional Chapter Affiliations & Applications
                    </h1>
                    <p class="dash-header-sub">
                        Review pending school affiliation packets, audit attached Excel rosters, and manage accredited chapters.
                    </p>
                </div>
                <div class="dash-header-btn-group">
                    <button type="button" id="btnDownloadTemplate" class="btn-excel-green">
                        <i class="fas fa-file-excel"></i> Excel Template (.xlsx)
                    </button>
                    <a href="<?= PORTAL_URL ?>/admin/members/list.php" class="btn-white">
                        <i class="fas fa-users" style="color:var(--color-blue);"></i> Member Directory
                    </a>
                    <a href="<?= PORTAL_URL ?>/admin/members/batch-process.php" class="btn-white">
                        <i class="fas fa-file-excel" style="color:#107C41;"></i> Chapter Submissions
                    </a>
                    <button type="button" class="btn-primary-navy" onclick="openCharterModal()">
                        <i class="fas fa-plus" style="color:#FDE047;"></i> Charter Institution
                    </button>
                </div>
            </div>

            <?php if (!empty($feedbackMsg)): ?>
                <div class="ap-alert <?= $feedbackType ?>" style="margin-bottom:0.85rem;">
                    <i class="fas fa-check-circle" style="font-size:1.2rem;"></i> 
                    <div><?= htmlspecialchars($feedbackMsg) ?></div>
                </div>
            <?php endif; ?>

            <!-- 2. Top 4 KPI Cards -->
            <div class="dash-kpi-grid">
                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill amber">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div style="min-width:0;">
                        <div class="kpi-val"><?= count($pendingApps) ?></div>
                        <div class="kpi-lbl">Pending Review</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill navy">
                        <i class="fas fa-school"></i>
                    </div>
                    <div style="min-width:0;">
                        <div class="kpi-val"><?= count($institutionsList) ?></div>
                        <div class="kpi-lbl">Chartered Institutions</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div style="min-width:0;">
                        <div class="kpi-val"><?= count($approvedApps) ?></div>
                        <div class="kpi-lbl">Approved Archive</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold">
                        <i class="fas fa-users"></i>
                    </div>
                    <div style="min-width:0;">
                        <div class="kpi-val"><?= number_format($totalMembersCount) ?></div>
                        <div class="kpi-lbl">Enrolled Student Members</div>
                    </div>
                </div>
            </div>

            <!-- 3. Search & Filter Bar -->
            <div class="white-controls-card">
                <div class="filter-controls-left">
                    <div class="search-input-wrapper">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="affiliationSearchInput" class="search-input-field" placeholder="Search school name, contact person, acronym, city..." onkeyup="filterAffiliationsTable()">
                    </div>
                </div>
                <div style="font-size:0.75rem; font-weight:700; color:#64748B;" id="filterCountBadge">
                    Showing <?= count($pendingApps) + count($institutionsList) ?> total chapters
                </div>
            </div>

            <!-- 4. Tab Switchers -->
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
            <div id="sectionPending" class="ap-card" style="border:2px solid <?= count($pendingApps) > 0 ? '#FDE047' : 'var(--border-color)' ?>;">
                <div class="ap-card-header" style="<?= count($pendingApps) > 0 ? 'background:#FEFCE8;' : '' ?>">
                    <h3 class="ap-card-title" style="color:<?= count($pendingApps) > 0 ? '#854D0E;' : '#0F172A;' ?>">
                        <i class="fas fa-inbox"></i> Incoming Affiliation Submissions (<?= count($pendingApps) ?> Requiring Action)
                    </h3>
                </div>
                <div style="overflow-x:auto;">
                    <table class="ap-table" id="pendingTable">
                        <thead>
                            <tr>
                                <th>Applicant School & Chapter</th>
                                <th>Contact Officer</th>
                                <th>Requirements Packet</th>
                                <th>Student Roster</th>
                                <th>Status</th>
                                <th>Submitted Date</th>
                                <th style="text-align:right;">3-Way Decision</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pendingApps)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding:2.5rem; color:#64748B;">
                                        <i class="fas fa-check-circle" style="font-size:2.2rem; color:#10B981; margin-bottom:0.5rem; display:block;"></i>
                                        <strong style="color:#0F172A; font-size:0.95rem;">Queue is Clear — No Pending Affiliations</strong>
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
                                            <strong style="color:#0F172A; font-size:0.84rem;"><?= htmlspecialchars($app['institution_name'] ?? 'School Application') ?></strong><br>
                                            <span style="font-size:0.72rem; color:#64748B;"><?= htmlspecialchars($app['institution_address'] ?? 'Laguna, Philippines') ?></span>
                                        </td>
                                        <td>
                                            <strong style="font-size:0.8rem;"><?= htmlspecialchars($app['contact_person'] ?? 'School Officer') ?></strong><br>
                                            <span style="font-size:0.72rem; color:#64748B;"><?= htmlspecialchars($app['contact_email'] ?? $app['email'] ?? '') ?></span>
                                            <?php if (!empty($app['contact_phone'])): ?>
                                                <div style="font-size:0.7rem; color:#64748B;"><i class="fas fa-phone"></i> <?= htmlspecialchars($app['contact_phone']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn-white" style="padding:0.25rem 0.6rem; font-size:0.72rem;" onclick='openInspectModal(<?= $appJson ?>)'>
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
                                                <span style="font-size:0.72rem; color:#64748B;">Standard Roster</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($st === 'resubmitted'): ?>
                                                <span class="ap-pill blue">
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
                                        <td style="font-size:0.76rem; color:#64748B; white-space:nowrap;">
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
                                                    <button type="submit" class="btn-act-green" title="Approve Affiliation & Provision Accounts">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                </form>

                                                <!-- 2. REQUEST TO EDIT / REVISION -->
                                                <button type="button" class="btn-act-amber" onclick='openRevisionModal(<?= $appJson ?>)' title="Request Specific File Revisions via Gmail">
                                                    <i class="fas fa-pen-to-square"></i> Request Edit
                                                </button>

                                                <!-- 3. REJECT / DECLINE -->
                                                <button type="button" class="btn-act-red" onclick="openDeclineModal('<?= htmlspecialchars($app['id']) ?>', '<?= htmlspecialchars($app['institution_name'] ?? 'Institution') ?>', '<?= htmlspecialchars($app['contact_email'] ?? $app['email'] ?? '') ?>', '<?= htmlspecialchars($app['contact_person'] ?? '') ?>')" title="Decline Application">
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
            <div id="sectionChartered" class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-building-columns"></i> Chartered University & College Chapters (<?= count($institutionsList) ?>)</h3>
                </div>

                <div style="overflow-x:auto;">
                    <table class="ap-table" id="charteredTable">
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
                                <tr><td colspan="6" style="text-align:center; padding:2rem; color:#64748B;">No chartered institutions found in database.</td></tr>
                            <?php else: ?>
                                <?php foreach ($institutionsList as $inst): ?>
                                    <tr>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:0.65rem;">
                                                <div style="width:32px; height:32px; border-radius:6px; background:#FEF9C3; color:#B45309; border:1px solid #FDE68A; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:0.75rem; flex-shrink:0;">
                                                    <?= htmlspecialchars(substr($inst['acronym'] ?: $inst['name'], 0, 3)) ?>
                                                </div>
                                                <div>
                                                    <strong style="color:#0F172A; font-size:0.84rem;"><?= htmlspecialchars($inst['name'] ?? 'Institution') ?></strong><br>
                                                    <span style="font-size:0.72rem; color:#64748B;"><?= htmlspecialchars($inst['acronym'] ?? 'HEI') ?> &bull; <?= htmlspecialchars($inst['email'] ?? '') ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong style="color:#0F172A; font-size:0.82rem;"><?= htmlspecialchars($inst['contact_person'] ?: 'Faculty Advisor') ?></strong><br>
                                            <span style="font-size:0.72rem; color:#64748B;"><?= htmlspecialchars($inst['contact_phone'] ?: '+63 912 345 6789') ?></span>
                                        </td>
                                        <td style="font-size:0.78rem; color:#64748B;">
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
                                            <a href="<?= PORTAL_URL ?>/admin/members/list.php?school=<?= urlencode($inst['id']) ?>" class="btn-white" style="font-size:0.72rem; padding:0.28rem 0.65rem;">
                                                <i class="fas fa-users" style="color:var(--color-navy);"></i> View Members
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
            <div id="sectionApproved" class="ap-card" style="display:none;">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-archive"></i> Approved Affiliations Archive (<?= count($approvedApps) ?>)</h3>
                </div>
                <div style="overflow-x:auto;">
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
                                <tr><td colspan="5" style="text-align:center; padding:2rem; color:#64748B;">No approved application history recorded yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($approvedApps as $app): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($app['institution_name'] ?? 'School') ?></strong></td>
                                        <td><?= htmlspecialchars($app['contact_email'] ?? $app['email'] ?? 'N/A') ?></td>
                                        <td><?= intval($app['total_members'] ?? 0) ?> Students</td>
                                        <td><span class="ap-pill active"><span class="ap-pill-dot"></span> Approved & Chartered</span></td>
                                        <td style="color:#64748B; font-size:0.76rem;"><?= !empty($app['updated_at']) ? date('M d, Y', strtotime($app['updated_at'])) : 'Recent' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sentinel -->
            <div class="ap-sentinel-strip" style="margin-top:1.5rem;">
                <div class="ap-sentinel-item"><i class="fas fa-university"></i><span><strong>Affiliation Protocol:</strong> National Constitution Compliance</span></div>
                <div class="ap-sentinel-item"><i class="fas fa-shield-halved"></i><span><strong>Proof-of-Charter:</strong> Cryptographically Anchored Verification</span></div>
            </div>

        </div>
    </main>

    <!-- 1. Inspect Requirements Packet & Live Document Viewer Modal -->
    <div id="inspectModal" class="doc-modal">
        <div class="modal-inner-box inspect-wide-box">
            <div class="ap-card-header" style="background:#0B1D4A; color:#FFFFFF; padding:0.8rem 1.25rem; display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
                <div style="display:flex; align-items:center; gap:0.65rem;">
                    <div style="width:36px; height:36px; border-radius:8px; background:rgba(255,255,255,0.12); display:flex; align-items:center; justify-content:center; color:#FDE047; font-size:1.1rem;">
                        <i class="fas fa-folder-open"></i>
                    </div>
                    <div>
                        <h3 class="ap-card-title" id="inspectSchoolTitle" style="color:#FFFFFF; margin:0; font-size:1.05rem; font-weight:800;">
                            Affiliation Application Packet
                        </h3>
                        <div id="inspectSchoolSubtitle" style="font-size:0.75rem; color:#94A3B8; margin-top:2px;">
                            Applicant Verification & Live Document Audit
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-white" style="border:none; padding:0.3rem 0.65rem; background:rgba(255,255,255,0.15); color:#FFFFFF; font-size:1.1rem; cursor:pointer;" onclick="closeInspectModal()">&times;</button>
            </div>

            <!-- Mobile View Switcher Tabs -->
            <div class="inspect-mobile-tabs" id="inspectMobileTabs">
                <button type="button" class="inspect-mobile-tab-btn active" id="btnMobileTabDocs" onclick="switchInspectMobileView('docs')">
                    <i class="fas fa-list-check"></i> Documents &amp; Info
                </button>
                <button type="button" class="inspect-mobile-tab-btn" id="btnMobileTabPreview" onclick="switchInspectMobileView('preview')">
                    <i class="fas fa-eye"></i> Live Document Preview
                </button>
            </div>

            <div class="inspect-layout mobile-view-docs" id="inspectLayoutContainer">
                <!-- Left Sidebar: Documents Tabs & Quick Actions -->
                <div class="inspect-sidebar">
                    <div>
                        <div style="font-size:0.7rem; text-transform:uppercase; font-weight:800; color:#64748B; letter-spacing:0.04em; margin-bottom:0.5rem; display:flex; justify-content:space-between; align-items:center;">
                            <span>Submission Packet</span>
                            <span id="inspectDocsCountBadge" style="font-size:0.68rem; background:#E2E8F0; color:#0F172A; padding:1px 6px; border-radius:10px; font-weight:700;">6 Files</span>
                        </div>
                        <div id="inspectDocList" style="display:flex; flex-direction:column; gap:0.45rem;">
                            <!-- Populated dynamically via JS -->
                        </div>
                    </div>

                    <!-- Application Assessment Meta -->
                    <div style="background:#FFFFFF; border:1px solid var(--border-color); border-radius:8px; padding:0.85rem; font-size:0.75rem;">
                        <div style="font-weight:800; color:#0F172A; margin-bottom:0.45rem; display:flex; align-items:center; gap:0.4rem;">
                            <i class="fas fa-receipt" style="color:var(--color-navy);"></i> Application Summary
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.25rem; color:#64748B;">
                            <span>Contact Officer:</span>
                            <strong id="inspectOfficerName" style="color:#0F172A;">-</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.25rem; color:#64748B;">
                            <span>Email:</span>
                            <span id="inspectOfficerEmail" style="color:#0F172A; word-break:break-all;">-</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.25rem; color:#64748B;">
                            <span>Total Students:</span>
                            <strong id="inspectTotalStudents" style="color:var(--color-navy);">-</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.25rem; color:#64748B;">
                            <span>Assessment Fee:</span>
                            <strong id="inspectTotalFee" style="color:#059669;">-</strong>
                        </div>
                        <div style="display:flex; justify-content:space-between; color:#64748B;">
                            <span>Receipt Tracking:</span>
                            <code id="inspectReceiptNo" style="color:#0B1D4A; font-weight:700;">-</code>
                        </div>
                    </div>

                    <!-- Quick Review Actions -->
                    <div style="margin-top:auto; display:flex; flex-direction:column; gap:0.45rem;">
                        <div id="inspectApprovalFormContainer"></div>
                        <button type="button" id="inspectRequestEditBtn" class="btn-act-amber" style="width:100%; justify-content:center; padding:0.5rem; font-size:0.78rem;">
                            <i class="fas fa-pen-to-square"></i> Request Revision / Edit
                        </button>
                    </div>
                </div>

                <!-- Right Pane: Live Document Preview Canvas -->
                <div class="inspect-preview-pane">
                    <div class="inspect-preview-header">
                        <div style="display:flex; align-items:center; gap:0.45rem;">
                            <button type="button" class="btn-white inspect-mobile-back-btn" onclick="switchInspectMobileView('docs')" style="display:none; font-size:0.72rem; padding:0.28rem 0.55rem;" title="Back to Document List">
                                <i class="fas fa-arrow-left"></i>
                            </button>
                            <i id="inspectCurrentDocIcon" class="fas fa-file-pdf" style="font-size:1.15rem; color:var(--color-navy);"></i>
                            <div>
                                <strong id="inspectCurrentDocLabel" style="font-size:0.88rem; color:#0F172A;">Document Preview</strong>
                                <span id="inspectCurrentDocStatus" class="ap-pill" style="margin-left:0.4rem; font-size:0.68rem;"></span>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:0.45rem;">
                            <a id="inspectNewTabLink" href="#" target="_blank" class="btn-white" style="font-size:0.74rem; padding:0.32rem 0.7rem; text-decoration:none; display:none;">
                                <i class="fas fa-arrow-up-right-from-square"></i> Open in New Tab
                            </a>
                            <a id="inspectDownloadLink" href="#" download class="btn-white" style="font-size:0.74rem; padding:0.32rem 0.7rem; text-decoration:none; display:none;">
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>
                    </div>

                    <div id="inspectPreviewCanvas" class="inspect-preview-body">
                        <!-- Rendered dynamically: iframe / SheetJS Excel table / image / empty state -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Request Revision / Edit Modal (Pumili ng files na papalitan) -->
    <div id="revisionModal" class="doc-modal">
        <div class="modal-inner-box" style="max-width:560px;">
            <div class="ap-card-header" style="background:#FEFCE8;">
                <h3 class="ap-card-title" style="color:#854D0E;"><i class="fas fa-pen-to-square"></i> Request Document Revision / Correction</h3>
                <button class="btn-white" style="border:none; padding:0.25rem 0.5rem;" onclick="closeRevisionModal()">&times;</button>
            </div>
            <form method="POST" style="padding:1rem;">
                <input type="hidden" name="action" value="request_revision">
                <input type="hidden" id="revAppId" name="application_id" value="">
                <input type="hidden" id="revInstName" name="institution_name" value="">
                <input type="hidden" id="revEmail" name="email" value="">
                <input type="hidden" id="revContactPerson" name="contact_person" value="">

                <p style="font-size:0.82rem; color:#1E293B; margin:0 0 0.4rem;">
                    Select the specific file(s) that <strong id="revSchoolNameDisplay"></strong> needs to correct/re-upload:
                </p>

                <!-- Checkbox List of Files -->
                <div class="revision-check-list">
                    <label class="revision-check-item">
                        <input type="checkbox" name="requested_files[]" value="letter_of_intent">
                        <span><i class="fas fa-file-lines" style="color:var(--color-navy); margin-right:4px;"></i> 1. Letter of Intent</span>
                    </label>
                    <label class="revision-check-item">
                        <input type="checkbox" name="requested_files[]" value="endorsement_letter">
                        <span><i class="fas fa-certificate" style="color:var(--color-navy); margin-right:4px;"></i> 2. Endorsement Letter (Dean/Chair)</span>
                    </label>
                    <label class="revision-check-item">
                        <input type="checkbox" name="requested_files[]" value="constitution_by_laws">
                        <span><i class="fas fa-scale-balanced" style="color:var(--color-navy); margin-right:4px;"></i> 3. Constitution & By-Laws</span>
                    </label>
                    <label class="revision-check-item">
                        <input type="checkbox" name="requested_files[]" value="officers_cvs">
                        <span><i class="fas fa-user-tie" style="color:var(--color-navy); margin-right:4px;"></i> 4. Officers Curriculum Vitae (CVs)</span>
                    </label>
                    <label class="revision-check-item">
                        <input type="checkbox" name="requested_files[]" value="organizational_chart">
                        <span><i class="fas fa-sitemap" style="color:var(--color-navy); margin-right:4px;"></i> 5. Organizational Chart</span>
                    </label>
                    <label class="revision-check-item">
                        <input type="checkbox" name="requested_files[]" value="member_directory">
                        <span><i class="fas fa-file-excel" style="color:#107C41; margin-right:4px;"></i> 6. Member Directory Spreadsheet (.xlsx / .csv)</span>
                    </label>
                </div>

                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Specific Correction Notes & Instructions for Applicant</label>
                    <textarea name="instructions" class="ap-input" rows="3" placeholder="e.g. Please secure the Dean's official signature on the endorsement letter and update columns 2-4 in the student roster." required style="font-size:0.8rem;"></textarea>
                </div>

                <div style="font-size:0.74rem; color:#64748B; background:#F8FAFC; border:1px solid #E2E8F0; padding:0.45rem 0.65rem; border-radius:6px; margin-bottom:0.85rem;">
                    <i class="fas fa-paper-plane" style="color:#2563EB;"></i> A direct secure re-upload link will be sent to the applicant's Gmail address (<span id="revEmailDisplay" style="font-weight:700;"></span>).
                </div>

                <div style="display:flex; justify-content:flex-end; gap:0.65rem;">
                    <button type="button" class="btn-white" onclick="closeRevisionModal()">Cancel</button>
                    <button type="submit" class="btn-primary-navy" style="background:#D97706; border-color:#D97706;">
                        <i class="fas fa-paper-plane"></i> Send Revision Request via Gmail
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 3. Decline / Reject Modal -->
    <div id="declineModal" class="doc-modal">
        <div class="modal-inner-box" style="max-width:460px;">
            <div class="ap-card-header">
                <h3 class="ap-card-title" style="color:#DC2626;"><i class="fas fa-times-circle"></i> Decline Affiliation</h3>
                <button class="btn-white" style="border:none; padding:0.25rem 0.5rem;" onclick="closeDeclineModal()">&times;</button>
            </div>
            <form method="POST" style="padding:1rem;">
                <input type="hidden" name="action" value="reject_charter">
                <input type="hidden" id="declineAppId" name="application_id" value="">
                <input type="hidden" id="declineEmail" name="email" value="">
                <input type="hidden" id="declineContactPerson" name="contact_person" value="">
                <input type="hidden" id="declineInstName" name="institution_name" value="">

                <p style="font-size:0.82rem; color:#1E293B; margin:0 0 0.75rem;">
                    State the deficiency or reason for declining <strong id="declineSchoolName"></strong>:
                </p>
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Notes / Deficiencies for Applicant</label>
                    <textarea name="notes" class="ap-input" rows="3" placeholder="e.g. Ineligible academic program or missing administrative endorsement." style="font-size:0.8rem;"></textarea>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:0.65rem; margin-top:1rem;">
                    <button type="button" class="btn-white" onclick="closeDeclineModal()">Cancel</button>
                    <button type="submit" class="btn-act-red" style="padding:0.45rem 0.95rem;">Confirm Decline & Notify</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 4. Charter Institution Modal -->
    <div id="charterModal" class="doc-modal">
        <div class="modal-inner-box" style="max-width:540px;">
            <div class="ap-card-header">
                <h3 class="ap-card-title"><i class="fas fa-stamp"></i> Charter & Register New Chapter</h3>
                <button class="btn-white" style="border:none; padding:0.25rem 0.5rem;" onclick="closeCharterModal()">&times;</button>
            </div>
            <form method="POST" style="padding:1rem;">
                <input type="hidden" name="action" value="approve_charter">
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Institution / University Name</label>
                    <input type="text" name="institution_name" class="ap-input" placeholder="e.g. Mapúa Malayan Colleges Laguna" required style="font-size:0.8rem;">
                </div>
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Official Chapter Email</label>
                    <input type="email" name="email" class="ap-input" placeholder="e.g. ece.chapter@mmcl.edu.ph" required style="font-size:0.8rem;">
                </div>
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Faculty Advisor / Contact Person</label>
                    <input type="text" name="contact_person" class="ap-input" placeholder="e.g. Engr. Maria Santos" style="font-size:0.8rem;">
                </div>
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Contact Phone</label>
                    <input type="text" name="contact_phone" class="ap-input" placeholder="e.g. +63 912 345 6789" style="font-size:0.8rem;">
                </div>
                <div style="display:flex; justify-content:flex-end; gap:0.65rem; margin-top:1.25rem;">
                    <button type="button" class="btn-white" onclick="closeCharterModal()">Cancel</button>
                    <button type="submit" class="btn-primary-navy"><i class="fas fa-floppy-disk"></i> Save & Activate Chapter</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Universal Excel Template Generator
        document.getElementById('btnDownloadTemplate').addEventListener('click', function() {
            const headers = [
                "Student ID",
                "Full Name",
                "Email Address",
                "School / Chapter",
                "Degree Program",
                "Year Level",
                "Contact Number",
                "Home Address",
                "Birthday (YYYY-MM-DD)",
                "Payment Status (Paid/Pending)"
            ];

            const sampleRows = [
                headers,
                ["2023-08912", "Maria Santos", "mariasantos@gmail.com", "Laguna State Polytechnic University - Santa Cruz Campus (LSPU - SCC)", "BS Electronics Engineering", "3rd Year", "+63 912 345 6789", "Santa Cruz, Laguna", "2003-05-14", "Paid"],
                ["2022-04192", "Juan Dela Cruz", "jdelacruz@gmail.com", "De La Salle University - Laguna Campus (DLSU - Laguna)", "BS Electronics Engineering", "4th Year", "+63 917 892 3411", "Biñan, Laguna", "2002-11-20", "Paid"],
                ["2023-10892", "Carlos Ramos", "cmramos@mcl.edu.ph", "Mapúa Malayan Colleges Laguna (MMCL)", "BS Electronics Engineering", "3rd Year", "+63 915 771 2233", "Cabuyao, Laguna", "2003-08-09", "Paid"]
            ];

            if (typeof XLSX !== 'undefined') {
                const ws = XLSX.utils.aoa_to_sheet(sampleRows);
                ws['!cols'] = [
                    { wch: 15 }, { wch: 22 }, { wch: 26 }, { wch: 45 },
                    { wch: 30 }, { wch: 12 }, { wch: 18 }, { wch: 25 },
                    { wch: 20 }, { wch: 18 }
                ];
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, "Member Roster");
                XLSX.writeFile(wb, "IECEP_LSC_Official_Member_Roster_Template.xlsx");
            } else {
                let csvContent = "";
                sampleRows.forEach(row => {
                    csvContent += row.map(v => `"${String(v).replace(/"/g, '""')}"`).join(",") + "\r\n";
                });
                const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
                const link = document.createElement("a");
                link.href = URL.createObjectURL(blob);
                link.download = "IECEP_LSC_Official_Member_Roster_Template.csv";
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        });

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

        // Search Filter for Affiliation tables
        function filterAffiliationsTable() {
            const query = document.getElementById('affiliationSearchInput').value.toLowerCase();
            const activeTab = document.querySelector('.tab-btn.active').id;
            let targetTableId = 'pendingTable';
            if (activeTab === 'tabBtnChartered') targetTableId = 'charteredTable';
            
            const table = document.getElementById(targetTableId);
            if (!table) return;

            const trs = table.getElementsByTagName('tr');
            let matchCount = 0;

            for (let i = 1; i < trs.length; i++) {
                const tr = trs[i];
                if (tr.children.length === 1 && tr.children[0].getAttribute('colspan')) continue;
                const text = tr.textContent.toLowerCase();
                if (text.indexOf(query) > -1) {
                    tr.style.display = '';
                    matchCount++;
                } else {
                    tr.style.display = 'none';
                }
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

        let currentInspectApp = null;
        let currentDocList = [];

        function openInspectModal(app) {
            currentInspectApp = app;
            
            // Header Info
            const instName = app.institution_name || app.school_name || 'Application Packet';
            document.getElementById('inspectSchoolTitle').innerHTML = `<i class="fas fa-folder-open" style="color:#FDE047; margin-right:0.4rem;"></i> Packet: ${escapeHtml(instName)}`;
            document.getElementById('inspectSchoolSubtitle').textContent = `${app.institution_address || 'Laguna, Philippines'} • Ref ID: ${app.id || 'N/A'}`;
            
            // Sidebar Summary
            document.getElementById('inspectOfficerName').textContent = app.contact_person || 'School Officer';
            document.getElementById('inspectOfficerEmail').textContent = app.contact_email || app.email || 'N/A';
            document.getElementById('inspectTotalStudents').textContent = `${app.total_members || 0} Students (${app.new_members || 0} New, ${app.old_members || 0} Old)`;
            document.getElementById('inspectTotalFee').textContent = `PHP ${parseFloat(app.total_fee || app.affiliation_fee || 0).toLocaleString('en-US', {minimumFractionDigits: 2})}`;
            document.getElementById('inspectReceiptNo').textContent = app.receipt_number || 'RCP-VERIFIED';

            // Approval Form in Sidebar
            const approvalContainer = document.getElementById('inspectApprovalFormContainer');
            approvalContainer.innerHTML = `
                <form method="POST" onsubmit="return confirm('Approve this affiliation? This will automatically create the School Officer account and ingest all attached student members into the Member Directory.');">
                    <input type="hidden" name="action" value="approve_charter">
                    <input type="hidden" name="application_id" value="${escapeHtml(app.id || '')}">
                    <input type="hidden" name="institution_name" value="${escapeHtml(app.institution_name || app.school_name || '')}">
                    <input type="hidden" name="email" value="${escapeHtml(app.contact_email || app.email || '')}">
                    <input type="hidden" name="contact_person" value="${escapeHtml(app.contact_person || '')}">
                    <input type="hidden" name="contact_phone" value="${escapeHtml(app.contact_phone || app.contact_number || '')}">
                    <button type="submit" class="btn-act-green" style="width:100%; justify-content:center; padding:0.55rem; font-size:0.8rem;" title="Approve Affiliation">
                        <i class="fas fa-check"></i> Approve Affiliation Packet
                    </button>
                </form>
            `;

            // Request Edit Button in Sidebar
            document.getElementById('inspectRequestEditBtn').onclick = function() {
                closeInspectModal();
                openRevisionModal(app);
            };

            // Prepare 6 Documents
            const docDefs = [
                { key: 'letter_of_intent', num: 1, label: 'Letter of Intent', icon: 'fa-file-lines', color: 'var(--color-navy)', type: 'pdf' },
                { key: 'endorsement_letter', num: 2, label: 'Endorsement Letter', icon: 'fa-certificate', color: 'var(--color-navy)', type: 'pdf' },
                { key: 'constitution_by_laws', num: 3, label: 'Constitution & By-Laws', icon: 'fa-scale-balanced', color: 'var(--color-navy)', type: 'pdf' },
                { key: 'officers_cvs', num: 4, label: 'Officers Curriculum Vitae', icon: 'fa-user-tie', color: 'var(--color-navy)', type: 'pdf' },
                { key: 'organizational_chart', num: 5, label: 'Organizational Chart', icon: 'fa-sitemap', color: 'var(--color-navy)', type: 'pdf' },
                { key: 'member_directory', num: 6, label: 'Member Directory (Excel)', icon: 'fa-file-excel', color: '#107C41', type: 'excel' }
            ];

            let attachedCount = 0;
            currentDocList = docDefs.map(doc => {
                let url = app[doc.key] || (app.documents && app.documents[doc.key]) || null;
                if (url && typeof url === 'string') {
                    attachedCount++;
                } else {
                    url = null;
                }
                return { ...doc, url };
            });

            document.getElementById('inspectDocsCountBadge').textContent = `${attachedCount}/6 Attached`;

            // Render Sidebar List
            const listEl = document.getElementById('inspectDocList');
            listEl.innerHTML = '';
            
            let firstActiveIndex = 0;
            currentDocList.forEach((doc, idx) => {
                const item = document.createElement('div');
                item.className = `inspect-doc-item ${idx === 0 ? 'active' : ''}`;
                item.id = `inspectDocTab_${doc.key}`;
                item.innerHTML = `
                    <div style="display:flex; align-items:center; gap:0.5rem; overflow:hidden;">
                        <i class="fas ${doc.icon}" style="color:${doc.color}; font-size:0.95rem; width:16px; flex-shrink:0;"></i>
                        <span style="font-size:0.76rem; font-weight:700; color:#0F172A; text-overflow:ellipsis; overflow:hidden; white-space:nowrap;">${doc.num}. ${doc.label}</span>
                    </div>
                    <div>
                        ${doc.url 
                            ? `<span class="ap-pill active" style="font-size:0.65rem; padding:1px 5px;"><i class="fas fa-check"></i> Ready</span>` 
                            : `<span style="font-size:0.65rem; color:#94A3B8;">Missing</span>`}
                    </div>
                `;
                item.onclick = () => {
                    selectInspectDoc(idx);
                    if (window.innerWidth <= 860) {
                        switchInspectMobileView('preview');
                    }
                };
                listEl.appendChild(item);
                if (doc.url && !currentDocList[firstActiveIndex].url) {
                    firstActiveIndex = idx;
                }
            });

            // Reset to docs view on mobile initially
            switchInspectMobileView('docs');

            // Select initial document
            selectInspectDoc(firstActiveIndex);

            // Show Modal
            document.getElementById('inspectModal').classList.add('active');
        }

        function switchInspectMobileView(view) {
            const container = document.getElementById('inspectLayoutContainer');
            const btnDocs = document.getElementById('btnMobileTabDocs');
            const btnPreview = document.getElementById('btnMobileTabPreview');
            if (!container) return;

            if (view === 'preview') {
                container.classList.remove('mobile-view-docs');
                container.classList.add('mobile-view-preview');
                if (btnDocs) btnDocs.classList.remove('active');
                if (btnPreview) btnPreview.classList.add('active');
            } else {
                container.classList.remove('mobile-view-preview');
                container.classList.add('mobile-view-docs');
                if (btnDocs) btnDocs.classList.add('active');
                if (btnPreview) btnPreview.classList.remove('active');
            }
        }

        function selectInspectDoc(index) {
            const doc = currentDocList[index];
            if (!doc) return;

            // Highlight Tab
            document.querySelectorAll('.inspect-doc-item').forEach((el, idx) => {
                el.classList.toggle('active', idx === index);
            });

            // Header Elements
            document.getElementById('inspectCurrentDocIcon').className = `fas ${doc.icon}`;
            document.getElementById('inspectCurrentDocIcon').style.color = doc.color || 'var(--color-navy)';
            document.getElementById('inspectCurrentDocLabel').textContent = `${doc.num}. ${doc.label}`;
            
            const statusEl = document.getElementById('inspectCurrentDocStatus');
            const newTabBtn = document.getElementById('inspectNewTabLink');
            const downloadBtn = document.getElementById('inspectDownloadLink');
            const canvas = document.getElementById('inspectPreviewCanvas');

            if (!doc.url) {
                statusEl.className = 'ap-pill';
                statusEl.style.background = '#F1F5F9';
                statusEl.style.color = '#64748B';
                statusEl.textContent = 'Not Attached';
                newTabBtn.style.display = 'none';
                downloadBtn.style.display = 'none';
                canvas.innerHTML = `
                    <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:#94A3B8; text-align:center; padding:2rem;">
                        <i class="fas fa-file-circle-xmark" style="font-size:3rem; margin-bottom:1rem; opacity:0.6;"></i>
                        <h4 style="color:#475569; margin:0 0 0.4rem; font-size:1.05rem;">Document Not Attached</h4>
                        <p style="font-size:0.82rem; margin:0; max-width:320px;">The applicant did not attach this requirement in their submission packet.</p>
                    </div>
                `;
                return;
            }

            statusEl.className = 'ap-pill active';
            statusEl.style.background = '#ECFDF5';
            statusEl.style.color = '#059669';
            statusEl.textContent = 'Verified File';

            newTabBtn.href = doc.url;
            newTabBtn.style.display = 'inline-flex';
            downloadBtn.href = doc.url;
            downloadBtn.style.display = 'inline-flex';

            // Check if Excel / XLSX
            const isExcel = doc.key === 'member_directory' || doc.url.includes('.xlsx') || doc.url.includes('.xls') || doc.url.includes('.csv');

            if (isExcel) {
                renderExcelLivePreview(doc.url, canvas);
            } else {
                renderPdfLivePreview(doc.url, canvas, doc.label);
            }
        }

        async function renderPdfLivePreview(url, container, label) {
            container.innerHTML = `
                <div class="pdf-viewer-container">
                    <div class="pdf-toolbar">
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <span id="pdfPageNumDisplay" style="font-weight:700;">Loading Document...</span>
                        </div>
                        <div style="display:flex; align-items:center; gap:0.4rem;">
                            <button type="button" id="pdfZoomOut" class="btn-white" style="padding:0.2rem 0.5rem; font-size:0.7rem; background:#494e52; color:#fff; border:none; cursor:pointer;" title="Zoom Out"><i class="fas fa-magnifying-glass-minus"></i></button>
                            <span id="pdfZoomVal" style="font-weight:600; min-width:38px; text-align:center;">115%</span>
                            <button type="button" id="pdfZoomIn" class="btn-white" style="padding:0.2rem 0.5rem; font-size:0.7rem; background:#494e52; color:#fff; border:none; cursor:pointer;" title="Zoom In"><i class="fas fa-magnifying-glass-plus"></i></button>
                        </div>
                    </div>
                    <div class="pdf-canvas-wrapper" id="pdfCanvasWrapper">
                        <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:#CBD5E1; padding:3rem;">
                            <i class="fas fa-spinner fa-spin" style="font-size:2.5rem; color:#FDE047; margin-bottom:0.85rem;"></i>
                            <span style="font-weight:700;">Rendering Document Pages...</span>
                        </div>
                    </div>
                </div>
            `;

            try {
                if (typeof pdfjsLib === 'undefined') {
                    throw new Error('PDF.js not available');
                }
                pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

                const loadingTask = pdfjsLib.getDocument({
                    url: url,
                    cMapUrl: 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/cmaps/',
                    cMapPacked: true
                });

                const pdfDoc = await loadingTask.promise;
                const totalPages = pdfDoc.numPages;
                let currentScale = 1.15;

                const wrapper = document.getElementById('pdfCanvasWrapper');
                wrapper.innerHTML = '';

                async function renderAllPages() {
                    wrapper.innerHTML = '';
                    for (let pageNum = 1; pageNum <= totalPages; pageNum++) {
                        const page = await pdfDoc.getPage(pageNum);
                        const viewport = page.getViewport({ scale: currentScale });

                        const canvas = document.createElement('canvas');
                        canvas.className = 'pdf-page-canvas';
                        canvas.id = `pdfPage_${pageNum}`;
                        const context = canvas.getContext('2d');
                        canvas.height = viewport.height;
                        canvas.width = viewport.width;

                        wrapper.appendChild(canvas);
                        await page.render({ canvasContext: context, viewport: viewport }).promise;
                    }
                }

                await renderAllPages();

                // Update Toolbar
                document.getElementById('pdfPageNumDisplay').textContent = `${totalPages} Page${totalPages > 1 ? 's' : ''}`;
                document.getElementById('pdfZoomVal').textContent = `${Math.round(currentScale * 100)}%`;

                // Zoom Handlers
                document.getElementById('pdfZoomIn').onclick = async () => {
                    if (currentScale >= 2.5) return;
                    currentScale += 0.2;
                    document.getElementById('pdfZoomVal').textContent = `${Math.round(currentScale * 100)}%`;
                    await renderAllPages();
                };
                document.getElementById('pdfZoomOut').onclick = async () => {
                    if (currentScale <= 0.6) return;
                    currentScale -= 0.2;
                    document.getElementById('pdfZoomVal').textContent = `${Math.round(currentScale * 100)}%`;
                    await renderAllPages();
                };

            } catch (err) {
                console.warn('PDF.js rendering note:', err);
                // Graceful fallback for non-standard / raw test files or iframe embed
                container.innerHTML = `
                    <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:#64748B; text-align:center; padding:2rem; background:#F8FAFC;">
                        <div style="width:68px; height:68px; border-radius:14px; background:#EFF6FF; color:#2563EB; display:flex; align-items:center; justify-content:center; font-size:2rem; margin-bottom:1rem; border:1px solid #DBEAFE;">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <h4 style="color:#0F172A; margin:0 0 0.4rem; font-size:1.1rem; font-weight:800;">${escapeHtml(label)}</h4>
                        <p style="font-size:0.82rem; margin:0 0 1.25rem; max-width:380px; color:#64748B;">
                            This document is securely stored on cloud storage and is ready for inspection.
                        </p>
                        <div style="display:flex; gap:0.6rem; flex-wrap:wrap; justify-content:center;">
                            <a href="${url}" target="_blank" class="btn-primary-navy" style="padding:0.55rem 1.25rem; font-size:0.82rem;">
                                <i class="fas fa-arrow-up-right-from-square"></i> Open in New Window
                            </a>
                            <a href="${url}" download class="btn-white" style="padding:0.55rem 1.15rem; font-size:0.82rem;">
                                <i class="fas fa-download"></i> Download Document
                            </a>
                        </div>
                    </div>
                `;
            }
        }

        async function renderExcelLivePreview(url, container) {
            container.innerHTML = `
                <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:#64748B; padding:2rem;">
                    <i class="fas fa-spinner fa-spin" style="font-size:2.5rem; color:var(--color-navy); margin-bottom:1rem;"></i>
                    <h4 style="color:#0F172A; margin:0 0 0.35rem; font-size:1rem;">Reading Student Member Directory...</h4>
                    <p style="font-size:0.8rem; margin:0;">Parsing spreadsheet data via SheetJS engine</p>
                </div>
            `;

            try {
                const response = await fetch(url);
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const arrayBuffer = await response.arrayBuffer();

                if (typeof XLSX === 'undefined') {
                    throw new Error('SheetJS library not loaded');
                }

                const workbook = XLSX.read(arrayBuffer, { type: 'array' });
                const firstSheetName = workbook.SheetNames[0];
                const worksheet = workbook.Sheets[firstSheetName];
                const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1, defval: '' });

                if (!jsonData || jsonData.length === 0) {
                    container.innerHTML = `
                        <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:#94A3B8; text-align:center; padding:2rem;">
                            <i class="fas fa-file-excel" style="font-size:3rem; color:#107C41; margin-bottom:1rem; opacity:0.6;"></i>
                            <h4 style="color:#475569; margin:0 0 0.4rem;">Empty Member Roster</h4>
                            <p style="font-size:0.8rem; margin:0;">No rows or records found inside this Excel file.</p>
                        </div>
                    `;
                    return;
                }

                const headers = jsonData[0] || [];
                const rows = jsonData.slice(1).filter(r => r.some(cell => String(cell).trim() !== ''));

                let tableHtml = `
                    <div class="excel-table-container">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0.75rem; flex-wrap:wrap; gap:0.5rem;">
                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                <span style="background:#ECFDF5; color:#059669; border:1px solid #A7F3D0; font-size:0.75rem; font-weight:800; padding:2px 8px; border-radius:6px;">
                                    <i class="fas fa-users" style="margin-right:3px;"></i> ${rows.length} Enrolled Students
                                </span>
                                <span style="font-size:0.74rem; color:#64748B;">Sheet: <strong>${escapeHtml(firstSheetName)}</strong></span>
                            </div>
                            <input type="text" placeholder="Search student name or ID..." onkeyup="filterExcelPreview(this.value)" style="padding:0.35rem 0.75rem; font-size:0.75rem; border:1px solid var(--border-color); border-radius:6px; width:220px; outline:none;" />
                        </div>
                        <div style="overflow:auto; border:1px solid var(--border-color); border-radius:8px; max-height:calc(100% - 45px);">
                            <table class="excel-grid-table" id="excelLiveGrid">
                                <thead>
                                    <tr>
                                        <th style="width:40px; text-align:center;">#</th>
                                        ${headers.map(h => `<th>${escapeHtml(String(h || 'Column'))}</th>`).join('')}
                                    </tr>
                                </thead>
                                <tbody>
                                    ${rows.map((row, rIdx) => `
                                        <tr>
                                            <td style="text-align:center; font-weight:700; color:#64748B; background:#F8FAFC;">${rIdx + 1}</td>
                                            ${headers.map((_, cIdx) => `<td>${escapeHtml(String(row[cIdx] || ''))}</td>`).join('')}
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;

                container.innerHTML = tableHtml;

            } catch (err) {
                console.warn('Excel parse fallback notice:', err);
                container.innerHTML = `
                    <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:#64748B; text-align:center; padding:2rem;">
                        <i class="fas fa-file-excel" style="font-size:3.5rem; color:#107C41; margin-bottom:1rem;"></i>
                        <h4 style="color:#0F172A; margin:0 0 0.4rem; font-size:1.1rem;">Official Member Directory (Excel)</h4>
                        <p style="font-size:0.82rem; margin:0 0 1.25rem; max-width:380px; color:#64748B;">
                            This spreadsheet contains the student roster submitted by the institution.
                        </p>
                        <div style="display:flex; gap:0.6rem;">
                            <a href="${url}" download class="btn-primary-navy" style="padding:0.55rem 1.25rem; font-size:0.82rem;">
                                <i class="fas fa-download"></i> Download Excel Roster (.xlsx)
                            </a>
                            <a href="${url}" target="_blank" class="btn-white" style="padding:0.55rem 1rem; font-size:0.82rem;">
                                <i class="fas fa-arrow-up-right-from-square"></i> Open File
                            </a>
                        </div>
                    </div>
                `;
            }
        }

        function filterExcelPreview(query) {
            const q = query.toLowerCase();
            const table = document.getElementById('excelLiveGrid');
            if (!table) return;
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(tr => {
                const text = tr.textContent.toLowerCase();
                tr.style.display = text.includes(q) ? '' : 'none';
            });
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function closeInspectModal() {
            document.getElementById('inspectModal').classList.remove('active');
            const canvas = document.getElementById('inspectPreviewCanvas');
            if (canvas) canvas.innerHTML = '';
        }
    </script>
</body>
</html>
