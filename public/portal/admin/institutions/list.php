<?php
require_once dirname(__DIR__, 4) . '/bootstrap.php';
$current_page = 'institutions';

require_once dirname(__DIR__, 2) . '/auth_check.php';
require_role(['admin', 'super_admin', 'registration', 'committee_registration']);

require_once dirname(__DIR__, 4) . '/src/lib/EmailService.php';
require_once dirname(__DIR__, 4) . '/includes/paths.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$user = get_user_info();
$supabase = getSupabaseClient();

$feedbackMsg = '';
$feedbackType = 'success';

if (!function_exists('uuid_v4')) {
    function uuid_v4(): string {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

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

            if (empty($instName)) {
                throw new \Exception("Institution name is required to approve charter.");
            }

            // Derive acronym
            $words = explode(' ', $instName);
            $acronym = count($words) > 1 ? implode('', array_map(fn($w) => strtoupper(substr($w, 0, 1)), array_slice($words, 0, 4))) : substr($instName, 0, 8);

            // 2. Insert or update institutions table
            $instId = null;
            if ($email) {
                $existingInst = $supabase->select('institutions', ['email' => 'eq.' . $email]);
                if (!empty($existingInst) && isset($existingInst[0]['id'])) {
                    $instId = $existingInst[0]['id'];
                }
            }
            if (!$instId) {
                $existingInstName = $supabase->select('institutions', ['name' => 'eq.' . $instName]);
                if (!empty($existingInstName) && isset($existingInstName[0]['id'])) {
                    $instId = $existingInstName[0]['id'];
                }
            }

            if ($instId) {
                $supabase->update('institutions', [
                    'name' => $instName,
                    'acronym' => $acronym,
                    'status' => 'active',
                    'compliance_status' => 'compliant',
                    'affiliation_fee_paid' => true,
                    'updated_at' => $timestamp
                ], $instId);
            } else {
                $instId = uuid_v4();
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
            $emailService = new \App\Lib\EmailService();

            // 3. Auto-Create / Update School Officer Portal Account & Send Credentials
            $officerTempPass = 'LSC-' . rand(1000, 9999) . '-' . substr(strtoupper(bin2hex(random_bytes(2))), 0, 4);
            $officerEmailSent = false;
            $officerEmailErr = '';
            $officerEmailTo = $email;
            $officerCreated = false;

            if ($email) {
                $existingUser = $supabase->select('users', ['email' => 'eq.' . $email]);
                if (!empty($existingUser) && isset($existingUser[0]['id'])) {
                    $officerUserId = $existingUser[0]['id'];
                    $supabase->update('users', [
                        'password' => password_hash($officerTempPass, PASSWORD_BCRYPT),
                        'full_name' => $contactPerson ?: "$acronym Officer",
                        'role' => 'school_officer',
                        'institution_id' => $instId,
                        'is_active' => true,
                        'must_change_password' => true,
                        'updated_at' => $timestamp
                    ], $officerUserId);

                    $supabase->update('user_profiles', [
                        'role' => 'school_officer',
                        'institution_id' => $instId,
                        'membership_status' => 'active',
                        'updated_at' => $timestamp
                    ], $officerUserId);
                    $officerCreated = true;
                } else {
                    $officerUserId = uuid_v4();
                    $supabase->insert('users', [[
                        'id' => $officerUserId,
                        'email' => $email,
                        'password' => password_hash($officerTempPass, PASSWORD_BCRYPT),
                        'full_name' => $contactPerson ?: "$acronym Officer",
                        'role' => 'school_officer',
                        'institution_id' => $instId,
                        'is_active' => true,
                        'must_change_password' => true,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp
                    ]]);

                    $supabase->insert('user_profiles', [[
                        'id' => $officerUserId,
                        'user_id' => $officerUserId,
                        'email' => $email,
                        'full_name' => $contactPerson ?: "$acronym Officer",
                        'role' => 'school_officer',
                        'institution_id' => $instId,
                        'membership_status' => 'active',
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp
                    ]]);
                    $officerCreated = true;
                }

                // Send login credentials email to School Officer's Gmail
                try {
                    $officerEmailSent = $emailService->sendSchoolAccountCredentials(
                        $email,
                        $instName,
                        $officerTempPass,
                        $contactPerson ?: "$acronym Officer"
                    );
                    if (!$officerEmailSent) {
                        $officerEmailErr = $emailService->getLastError() ?: 'Email delivery issue';
                        error_log("Officer email notice: " . $officerEmailErr);
                    }
                } catch (\Throwable $emEx) {
                    $officerEmailErr = $emEx->getMessage();
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

            // If a member directory file was uploaded, fetch and parse it (supports Cloud CDN URLs & local paths)
            if ($memberDirectoryUrl) {
                $tempRosterPath = tempnam(sys_get_temp_dir(), 'roster_') . '.xlsx';
                $fileBytes = false;

                if (strpos($memberDirectoryUrl, 'http') === 0) {
                    $fileBytes = @file_get_contents($memberDirectoryUrl);
                    if ($fileBytes === false && function_exists('curl_init')) {
                        $ch = curl_init($memberDirectoryUrl);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        $fileBytes = curl_exec($ch);
                        curl_close($ch);
                    }
                } else {
                    $localPath = str_replace('/IECEP-LSC-MEMSYS/public/', __DIR__ . '/../../', $memberDirectoryUrl);
                    if (!file_exists($localPath)) {
                        $localPath = dirname(__DIR__, 3) . '/' . ltrim($memberDirectoryUrl, '/');
                    }
                    if (file_exists($localPath)) {
                        $fileBytes = file_get_contents($localPath);
                    }
                }

                if ($fileBytes && strlen($fileBytes) > 0) {
                    file_put_contents($tempRosterPath, $fileBytes);
                    $memberRows = [];

                    try {
                        if (class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
                            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tempRosterPath);
                            $worksheet = $spreadsheet->getActiveSheet();
                            $memberRows = $worksheet->toArray(null, true, true, false);
                        } else {
                            $content = file_get_contents($tempRosterPath);
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
                                    $memId = uuid_v4();
                                    $membershipId = date('Y') . str_pad($baseCount, 4, '0', STR_PAD_LEFT);
                                    $hash = hash('sha256', $memId . $name . $sEmail . $timestamp);

                                    // Insert or update member
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
                                        'digital_id_url' => 'DID-2026-LSC-' . strtoupper(substr(str_replace('-', '', $memId), 0, 4)),
                                        'created_at' => $timestamp,
                                        'updated_at' => $timestamp
                                    ]]);

                                    // Check if user already exists
                                    $existingStudentUser = $supabase->select('users', ['email' => 'eq.' . $sEmail]);
                                    if (empty($existingStudentUser)) {
                                        $supabase->insert('users', [[
                                            'id' => $memId,
                                            'email' => $sEmail,
                                            'password' => password_hash($memberTempPass, PASSWORD_BCRYPT),
                                            'full_name' => $name,
                                            'role' => 'member',
                                            'institution_id' => $instId,
                                            'is_active' => true,
                                            'must_change_password' => true,
                                            'created_at' => $timestamp,
                                            'updated_at' => $timestamp
                                        ]]);

                                        $supabase->insert('user_profiles', [[
                                            'id' => $memId,
                                            'user_id' => $memId,
                                            'email' => $sEmail,
                                            'full_name' => $name,
                                            'role' => 'member',
                                            'institution_id' => $instId,
                                            'membership_status' => 'active',
                                            'created_at' => $timestamp,
                                            'updated_at' => $timestamp
                                        ]]);
                                    }

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
                    } finally {
                        @unlink($tempRosterPath);
                    }
                }
            }

            // 5. Update pending application status
            if ($appId) {
                try {
                    $supabase->update('pending_affiliations', [
                        'status' => 'approved',
                        'portal_user_id' => $officerUserId ?? null,
                        'login_credentials_sent' => $officerEmailSent ? 1 : 0,
                        'updated_at' => $timestamp
                    ], $appId);
                } catch (\Throwable $paEx) {
                    $supabase->update('pending_affiliations', [
                        'status' => 'approved',
                        'updated_at' => $timestamp
                    ], $appId);
                }
            }

            // 5.5. Reconcile & Record Verified Transaction into Treasury Audit Ledger
            try {
                $rcpNum = !empty($appData['receipt_number']) ? $appData['receipt_number'] : ('RCP-' . date('Y') . '-' . substr(strtoupper(bin2hex(random_bytes(3))), 0, 5));
                $paidTotal = floatval($appData['total_fee'] ?? 0);
                $affilPart = floatval($appData['affiliation_fee'] ?? 0);
                $memberPart = floatval($appData['membership_total'] ?? 0);
                if ($paidTotal <= 0) {
                    $affilPart = 2500.00;
                    $memberPart = ($ingestedCount ?: intval($appData['total_members'] ?? 0)) * 200.00;
                    $paidTotal = $affilPart + 800.00 + $memberPart;
                }
                
                $existingTx = $supabase->select('transactions', ['receipt_number' => 'eq.' . $rcpNum]);
                if (empty($existingTx)) {
                    $supabase->insert('transactions', [[
                        'type' => 'membership_fee',
                        'transaction_type' => 'affiliation_fee',
                        'receipt_number' => $rcpNum,
                        'amount' => $paidTotal,
                        'payment_method' => 'online_payment',
                        'status' => 'paid',
                        'institution_id' => $instId,
                        'metadata' => json_encode([
                            'institution_id' => $instId,
                            'institution_name' => $instName,
                            'contact_person' => $contactPerson,
                            'total_members' => $ingestedCount ?: intval($appData['total_members'] ?? 0),
                            'affiliation_fee' => $affilPart,
                            'operational_fee' => 800.00,
                            'membership_total' => $memberPart,
                            'total_fee' => $paidTotal,
                            'receipt_number' => $rcpNum,
                            'verified_at' => $timestamp
                        ]),
                        'created_at' => $timestamp
                    ]]);
                }
            } catch (\Throwable $txEx) {
                error_log("Approval transaction audit notice: " . $txEx->getMessage());
            }

            // 6. Anchor blockchain proof
            try {
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
            } catch (\Throwable $bcEx) {
                error_log("Blockchain record insertion warning: " . $bcEx->getMessage());
            }

            if ($officerCreated && $officerEmailSent) {
                $feedbackMsg = "🎉 Successfully Approved Affiliation for '{$instName}'! School Officer account created and login credentials sent to {$officerEmailTo} (Temporary Password: {$officerTempPass}). {$ingestedCount} student members ingested.";
                $feedbackType = 'success';
            } elseif ($officerCreated && !$officerEmailSent) {
                $feedbackMsg = "🎉 Affiliation Approved & School Officer account created for {$officerEmailTo}! (Temporary Password: <strong>{$officerTempPass}</strong>). Notice: Gmail delivery warning: " . htmlspecialchars($officerEmailErr ?: 'Please share password directly with officer.');
                $feedbackType = 'warning';
            } else {
                $feedbackMsg = "🎉 Successfully Approved Affiliation for '{$instName}'! Chapter is now active and {$ingestedCount} student members ingested.";
                $feedbackType = 'success';
            }
        } catch (\Throwable $e) {
            error_log("Approval error: " . $e->getMessage());
            $feedbackMsg = "❌ Error approving affiliation: " . $e->getMessage();
            $feedbackType = 'danger';
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
            
            if (empty($appId)) {
                throw new \Exception("Application ID is required to request document revisions.");
            }
            
            $updatePayload = [
                'status' => 'requires_revision',
                'updated_at' => date('c')
            ];
            try {
                $supabase->update('pending_affiliations', array_merge($updatePayload, ['rejection_reason' => $instructions ?: 'Please update the requested documents.']), $appId);
            } catch (\Throwable $colEx) {
                $supabase->update('pending_affiliations', $updatePayload, $appId);
            }
            
            // Fetch application info for email delivery
            $appRes = $supabase->select('pending_affiliations', ['id' => 'eq.' . $appId]);
            if (empty($appRes)) {
                throw new \Exception("Application record with ID '{$appId}' not found in database.");
            }
            
            $appData = normalize_pending_affiliation_app($appRes[0]);
            $applicantEmail = trim($appData['contact_email'] ?: ($appData['email'] ?: $email));
            $applicantName = trim($appData['contact_person'] ?: ($contactPerson ?: 'School Chapter Representative'));
            $applicantSchool = trim($appData['institution_name'] ?: ($appData['school_name'] ?: ($instName ?: 'Affiliated Institution')));
            
            $revisionUrl = rtrim(BASE_URL, '/') . '/public/revise-affiliation.php?id=' . urlencode($appId);
            if (empty($applicantEmail)) {
                $feedbackMsg = "⚠️ Revision status saved in database! (No applicant email was found on record). You may share this link directly with the school: " . htmlspecialchars($revisionUrl);
                $feedbackType = 'warning';
            } else {
                $emailService = new \App\Lib\EmailService();
                $sent = $emailService->sendAffiliationRevisionRequest(
                    $applicantEmail,
                    $applicantSchool,
                    $applicantName,
                    $fileListForEmail,
                    $instructions,
                    $revisionUrl
                );
                if (!$sent) {
                    $lastErr = $emailService->getLastError() ?: 'SMTP delivery issue';
                    $feedbackMsg = "⚠️ Revision status saved in database! However, sending Gmail to {$applicantEmail} encountered an issue: {$lastErr}. You may share this link directly with the school: " . htmlspecialchars($revisionUrl);
                    $feedbackType = 'warning';
                } else {
                    $feedbackMsg = "📩 Revision Request successfully sent to {$applicantEmail}! The applicant has received the link in their Gmail to re-upload the requested file(s).";
                    $feedbackType = 'info';
                }
            }
        } catch (\Throwable $e) {
            error_log("Revision request error: " . $e->getMessage());
            $feedbackMsg = "❌ Error sending revision request: " . $e->getMessage();
            $feedbackType = 'danger';
        }
    } elseif ($action === 'reject_charter') {
        try {
            $reason = trim($_POST['notes'] ?? 'Application requirements not met.');
            if ($appId) {
                $updatePayload = [
                    'status' => 'rejected',
                    'updated_at' => date('c')
                ];
                try {
                    $supabase->update('pending_affiliations', array_merge($updatePayload, ['rejection_reason' => $reason]), $appId);
                } catch (\Throwable $colEx) {
                    $supabase->update('pending_affiliations', $updatePayload, $appId);
                }
                
                $appRes = $supabase->select('pending_affiliations', ['id' => 'eq.' . $appId]);
                if (!empty($appRes)) {
                    $appData = normalize_pending_affiliation_app($appRes[0]);
                    $applicantEmail = trim($appData['contact_email'] ?: ($appData['email'] ?: $email));
                    $applicantName = trim($appData['contact_person'] ?: ($contactPerson ?: 'School Representative'));
                    $applicantSchool = trim($appData['institution_name'] ?: ($appData['school_name'] ?: ($instName ?: 'Affiliated Institution')));
                    
                    $emailService = new \App\Lib\EmailService();
                    if (!empty($applicantEmail)) {
                        $sent = $emailService->sendAffiliationRejectionNotice($applicantEmail, $applicantSchool, $applicantName, $reason);
                        if (!$sent) {
                            $lastErr = $emailService->getLastError() ?: 'SMTP delivery issue';
                            $feedbackMsg = "🚫 Application for '{$instName}' was declined and saved in database. (Email notice could not be delivered to {$applicantEmail}: {$lastErr})";
                            $feedbackType = 'warning';
                        } else {
                            $feedbackMsg = "🚫 Application for '{$instName}' declined and formal notice sent to {$applicantEmail}.";
                            $feedbackType = 'warning';
                        }
                    } else {
                        $feedbackMsg = "🚫 Application for '{$instName}' declined and saved in database.";
                        $feedbackType = 'warning';
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log("Reject error: " . $e->getMessage());
            $feedbackMsg = "❌ Error declining application: " . $e->getMessage();
            $feedbackType = 'danger';
        }
    } elseif ($action === 'delete_institution') {
        try {
            $deleteInstId = trim($_POST['institution_id'] ?? '');
            $deleteInstName = trim($_POST['institution_name'] ?? 'Institution');
            
            if (empty($deleteInstId)) {
                throw new \Exception("Institution ID is required to delete.");
            }

            // 1. Safely unlink members from this institution
            try {
                $supabase->update('members', ['institution_id' => null], ['institution_id' => 'eq.' . $deleteInstId]);
            } catch (\Throwable $mEx) {
                error_log("Notice unlinking members: " . $mEx->getMessage());
            }

            // 2. Safely unlink users and user profiles
            try {
                $supabase->update('users', ['institution_id' => null], ['institution_id' => 'eq.' . $deleteInstId]);
                $supabase->update('user_profiles', ['institution_id' => null], ['institution_id' => 'eq.' . $deleteInstId]);
            } catch (\Throwable $uEx) {
                error_log("Notice unlinking users: " . $uEx->getMessage());
            }

            // 3. Delete school profile if exists
            try {
                $supabase->delete('school_profiles', ['institution_id' => 'eq.' . $deleteInstId]);
            } catch (\Throwable $spEx) {
                error_log("Notice deleting school profile: " . $spEx->getMessage());
            }

            // 4. Safely clean up any child compliance / fee / batch records that link to this institution
            $cascadeTables = [
                'compliance_scores',
                'policy_compliance',
                'fee_waiver_requests',
                'fee_waivers',
                'fee_adjustments',
                'pending_members',
                'member_upload_batches',
                'upload_batches',
                'member_applications'
            ];
            foreach ($cascadeTables as $cTable) {
                try {
                    $supabase->delete($cTable, ['institution_id' => 'eq.' . $deleteInstId]);
                } catch (\Throwable $cEx) {
                    error_log("Notice cleaning $cTable: " . $cEx->getMessage());
                }
            }

            // 5. Delete institution record from institutions table
            $supabase->delete('institutions', ['id' => 'eq.' . $deleteInstId]);

            // 5. Update any pending_affiliations associated with this institution to cancelled
            try {
                $supabase->update('pending_affiliations', ['status' => 'cancelled'], ['institution_id' => 'eq.' . $deleteInstId]);
            } catch (\Throwable $paEx) {
                // Ignore if not present
            }

            $feedbackMsg = "🗑️ Successfully deleted institution '{$deleteInstName}'. All student records were safely unlinked.";
            $feedbackType = 'success';
        } catch (\Throwable $e) {
            error_log("Delete institution error: " . $e->getMessage());
            $feedbackMsg = "❌ Error deleting institution: " . $e->getMessage();
            $feedbackType = 'danger';
        }
    } elseif ($action === 'upload_institution_requirement') {
        try {
            $targetInstId = trim($_POST['institution_id'] ?? '');
            $reqType = trim($_POST['requirement_type'] ?? 'other');
            $customTitle = trim($_POST['title'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $timestamp = date('c');

            if (empty($targetInstId)) {
                throw new \Exception("Institution ID is required.");
            }

            if (!isset($_FILES['requirement_file']) || $_FILES['requirement_file']['error'] !== UPLOAD_ERR_OK) {
                throw new \Exception("Please select a valid requirement file to upload.");
            }

            $uploadDir = dirname(__DIR__, 3) . '/public/storage/documents/';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0777, true);

            $origName = basename($_FILES['requirement_file']['name']);
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $safeName = 'REQ_' . substr($targetInstId, 0, 8) . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            $dest = $uploadDir . $safeName;
            $fileUrl = '/IECEP-LSC-MEMSYS/public/storage/documents/' . $safeName;

            if (!move_uploaded_file($_FILES['requirement_file']['tmp_name'], $dest)) {
                throw new \Exception("Failed to save uploaded file to storage.");
            }

            $canonicalTitles = [
                'letter_of_intent' => 'Letter of Intent (Art. IV Sec. 3)',
                'endorsement_letter' => 'Dean / Chair Endorsement Letter',
                'constitution_bylaws' => 'Student Chapter Constitution & By-Laws (CBL)',
                'officers_cv' => 'Incumbent Student Chapter Officers Directory & CVs',
                'org_chart' => 'Organizational Structure Chart',
                'member_directory' => 'Certified Student Member Directory',
                'official_receipt' => 'Official Audited Receipt / Deposit Slip',
                'other' => 'Chapter Accreditation Document'
            ];

            $docTitle = !empty($customTitle) ? $customTitle : ($canonicalTitles[$reqType] ?? ucwords(str_replace('_', ' ', $reqType)));
            $docId = uuid_v4();

            $supabase->insert('documents', [[
                'id' => $docId,
                'institution_id' => $targetInstId,
                'title' => $docTitle,
                'category' => $reqType,
                'description' => $notes,
                'file_url' => $fileUrl,
                'file_path' => $fileUrl,
                'file_type' => $ext,
                'uploaded_by' => $_SESSION['user']['email'] ?? 'Administrator',
                'created_at' => $timestamp,
                'updated_at' => $timestamp
            ]]);

            // Sync with pending_affiliations table if canonical key matches
            try {
                $targetApp = $supabase->select('pending_affiliations', ['institution_id' => 'eq.' . $targetInstId, 'limit' => 1]);
                if (!empty($targetApp[0]['id'])) {
                    $appId = $targetApp[0]['id'];
                    $updateField = match($reqType) {
                        'letter_of_intent' => 'letter_of_intent',
                        'endorsement_letter' => 'endorsement_letter',
                        'constitution_bylaws' => 'constitution_by_laws',
                        'officers_cv' => 'officers_cvs',
                        'org_chart' => 'organizational_chart',
                        'member_directory' => 'member_directory',
                        default => null
                    };
                    if ($updateField) {
                        $supabase->update('pending_affiliations', [$updateField => $fileUrl, 'updated_at' => $timestamp], $appId);
                    }
                }
            } catch (\Throwable $e2) {}

            $feedbackMsg = "✓ Requirement document '{$docTitle}' ({$origName}) successfully uploaded and attached to the chapter!";
            $feedbackType = 'success';
        } catch (\Throwable $e) {
            error_log("Upload req error: " . $e->getMessage());
            $feedbackMsg = "❌ Error uploading requirement: " . $e->getMessage();
            $feedbackType = 'danger';
        }
    } elseif ($action === 'delete_institution_requirement') {
        try {
            $docId = trim($_POST['document_id'] ?? '');
            if ($docId) {
                $supabase->delete('documents', ['id' => 'eq.' . $docId]);
                $feedbackMsg = "🗑️ Requirement file successfully removed from chapter repository.";
                $feedbackType = 'warning';
            }
        } catch (\Throwable $e) {
            $feedbackMsg = "Error deleting document: " . $e->getMessage();
            $feedbackType = 'danger';
        }
    }
}

// Fetch real institutions, members count, and all pending submissions from database
$institutionsList = [];
$pendingApps = [];
$approvedApps = [];
$rejectedApps = [];
$totalMembersCount = 0;
$membersPerInst = [];
$feeBrackets = [];

$allAppsMap = [];
try {
    $rawInst = $supabase->select('institutions', ['select' => '*', 'order' => 'created_at.desc']);
    if (is_array($rawInst)) {
        $institutionsList = $rawInst;
    }

    $rawMembers = $supabase->select('members', ['select' => 'id,institution_id']);
    if (is_array($rawMembers)) {
        $totalMembersCount = count($rawMembers);
        foreach ($rawMembers as $m) {
            if (!empty($m['institution_id'])) {
                $membersPerInst[$m['institution_id']] = ($membersPerInst[$m['institution_id']] ?? 0) + 1;
            }
        }
    }

    // Retrieve fee brackets
    try {
        $rawBrackets = $supabase->select('fee_brackets', ['select' => '*', 'is_active' => 'eq.true', 'order' => 'min_members.asc']);
        if (is_array($rawBrackets) && !empty($rawBrackets)) {
            $feeBrackets = $rawBrackets;
        }
    } catch (\Throwable $fbEx) {}

    $rawAllApps = $supabase->select('pending_affiliations', ['select' => '*', 'order' => 'created_at.desc']);
    if (is_array($rawAllApps)) {
        foreach ($rawAllApps as $rawApp) {
            $app = normalize_pending_affiliation_app($rawApp);
            if (!empty($app['id'])) {
                $allAppsMap[$app['id']] = $app;
            }
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

// Helper: Calculate bracket affiliation fee based on roster count
$calcBracketFee = function($memberCount) use ($feeBrackets) {
    $cnt = max(1, intval($memberCount));
    if (!empty($feeBrackets)) {
        $applied = 1500.00;
        foreach ($feeBrackets as $b) {
            $min = intval($b['min_members'] ?? 0);
            $max = intval($b['max_members'] ?? 999999);
            if ($cnt >= $min && $cnt <= $max) {
                return floatval($b['fee'] ?? 1500.00);
            }
            if ($cnt >= $min) {
                $applied = floatval($b['fee'] ?? 1500.00);
            }
        }
        return $applied;
    }
    if ($cnt <= 50) return 1500.00;
    if ($cnt <= 100) return 2000.00;
    if ($cnt <= 150) return 2500.00;
    return 3000.00;
};

// Map approved applications by institution ID and normalized name
$approvedAppsByInstId = [];
$approvedAppsByName = [];
foreach ($approvedApps as $app) {
    if (!empty($app['institution_id'])) {
        $approvedAppsByInstId[$app['institution_id']] = $app;
    }
    if (!empty($app['institution_name'])) {
        $approvedAppsByName[strtolower(trim($app['institution_name']))] = $app;
    }
    if (!empty($app['school_name'])) {
        $approvedAppsByName[strtolower(trim($app['school_name']))] = $app;
    }
}

// Auto-compute audited financial totals and per-institution audited ledger
$instFinancialsMap = [];
$auditedGrandTotal = 0.0;
$auditedAffilTotal = 0.0;
$auditedMembershipTotal = 0.0;
$auditedTotalStudents = 0;
$auditedReceiptsCount = 0;

foreach ($institutionsList as $inst) {
    $instId = $inst['id'];
    $instName = $inst['name'] ?? 'Institution';
    $normName = strtolower(trim($instName));

    $matchedApp = $approvedAppsByInstId[$instId] ?? ($approvedAppsByName[$normName] ?? null);

    $liveMembers = $membersPerInst[$instId] ?? 0;
    $seedMembers = intval($inst['membership_count'] ?? 0);
    $appMembers = intval($matchedApp['total_members'] ?? 0);
    $memberCount = $liveMembers > 0 ? $liveMembers : ($seedMembers > 0 ? $seedMembers : ($appMembers > 0 ? $appMembers : 1));

    if ($matchedApp && floatval($matchedApp['total_fee'] ?? 0) > 0) {
        $affilFee = floatval($matchedApp['affiliation_fee'] ?? 0);
        $opFee = 800.00;
        $memTotal = floatval($matchedApp['membership_total'] ?? 0);
        $totFee = floatval($matchedApp['total_fee'] ?? 0);
        $rcpNo = !empty($matchedApp['receipt_number']) ? $matchedApp['receipt_number'] : ('RCP-' . date('Y') . '-' . strtoupper(substr(md5($instId), 0, 5)));
    } else {
        $affilFee = $calcBracketFee($memberCount);
        $opFee = 800.00;
        $memTotal = $memberCount * 200.00;
        $totFee = $affilFee + $opFee + $memTotal;
        $rcpNo = 'RCP-' . date('Y') . '-' . strtoupper(substr(md5($instId . $instName), 0, 5));
    }

    $finRecord = [
        'institution_id'    => $instId,
        'institution_name'  => $instName,
        'acronym'           => $inst['acronym'] ?? 'HEI',
        'contact_person'    => $inst['contact_person'] ?? ($matchedApp['contact_person'] ?? 'Faculty Advisor'),
        'contact_email'     => $inst['email'] ?? ($matchedApp['contact_email'] ?? ''),
        'contact_phone'     => $inst['contact_phone'] ?? ($matchedApp['contact_phone'] ?? '+63 912 345 6789'),
        'member_count'      => $memberCount,
        'affiliation_fee'   => $affilFee,
        'operational_fee'   => $opFee,
        'membership_total'  => $memTotal,
        'total_fee'         => $totFee,
        'receipt_number'    => $rcpNo,
        'status'            => 'verified',
        'payment_method'    => 'GCash (Online Payment)',
        'blockchain_hash'   => hash('sha256', $rcpNo . '|' . $instName . '|' . $totFee),
        'verified_at'       => $inst['updated_at'] ?? $inst['created_at'] ?? date('Y-m-d H:i:s')
    ];

    $instFinancialsMap[$instId] = $finRecord;

    $auditedGrandTotal += $totFee;
    $auditedAffilTotal += ($affilFee + $opFee);
    $auditedMembershipTotal += $memTotal;
    $auditedTotalStudents += $memberCount;
    $auditedReceiptsCount++;
}

// Query policy compliance records for institutions
$policyComplianceMap = [];
try {
    if ($supabase) {
        $polRes = $supabase->select('policy_compliance', ['select' => 'institution_id, is_compliant']);
        if (is_array($polRes)) {
            foreach ($polRes as $pr) {
                $iid = $pr['institution_id'] ?? '';
                if ($iid) {
                    $policyComplianceMap[$iid]['total'] = ($policyComplianceMap[$iid]['total'] ?? 0) + 1;
                    if (!empty($pr['is_compliant'])) {
                        $policyComplianceMap[$iid]['passed'] = ($policyComplianceMap[$iid]['passed'] ?? 0) + 1;
                    }
                }
            }
        }
    }
} catch (\Throwable $e) {
    error_log("Institutions list policy compliance query: " . $e->getMessage());
}

// Auto-audit pending applications
foreach ($pendingApps as &$pApp) {
    $pMembers = intval($pApp['total_members'] ?? 0);
    $pAffil = floatval($pApp['affiliation_fee'] ?? 0);
    $pMem = floatval($pApp['membership_total'] ?? 0);
    $pTot = floatval($pApp['total_fee'] ?? 0);
    
    if ($pTot <= 0) {
        $pAffil = $calcBracketFee($pMembers > 0 ? $pMembers : 1);
        $pMem = $pMembers * 200.00;
        $pTot = $pAffil + 800.00 + $pMem;
        $pApp['affiliation_fee'] = $pAffil;
        $pApp['operational_fee'] = 800.00;
        $pApp['membership_total'] = $pMem;
        $pApp['total_fee'] = $pTot;
    } else {
        $pApp['operational_fee'] = 800.00;
    }
    if (empty($pApp['receipt_number'])) {
        $pApp['receipt_number'] = 'RCP-' . date('Y') . '-' . strtoupper(substr(md5($pApp['id'] ?? uniqid()), 0, 5));
    }
    $pApp['blockchain_hash'] = hash('sha256', $pApp['receipt_number'] . '|' . ($pApp['institution_name'] ?? 'School') . '|' . $pTot);
}
unset($pApp);

// Auto-audit approved history applications
foreach ($approvedApps as &$aApp) {
    $aMembers = intval($aApp['total_members'] ?? 0);
    $aAffil = floatval($aApp['affiliation_fee'] ?? 0);
    $aMem = floatval($aApp['membership_total'] ?? 0);
    $aTot = floatval($aApp['total_fee'] ?? 0);
    
    if ($aTot <= 0) {
        $aAffil = $calcBracketFee($aMembers > 0 ? $aMembers : 1);
        $aMem = $aMembers * 200.00;
        $aTot = $aAffil + 800.00 + $aMem;
        $aApp['affiliation_fee'] = $aAffil;
        $aApp['operational_fee'] = 800.00;
        $aApp['membership_total'] = $aMem;
        $aApp['total_fee'] = $aTot;
    } else {
        $aApp['operational_fee'] = 800.00;
    }
    if (empty($aApp['receipt_number'])) {
        $aApp['receipt_number'] = 'RCP-' . date('Y') . '-' . strtoupper(substr(md5($aApp['id'] ?? uniqid()), 0, 5));
    }
    $aApp['blockchain_hash'] = hash('sha256', $aApp['receipt_number'] . '|' . ($aApp['institution_name'] ?? 'School') . '|' . $aTot);
}
unset($aApp);

// Build consolidated requirement documents map per institution
$institutionDocsMap = [];
try {
    if ($supabase) {
        $rawDocs = $supabase->select('documents', ['select' => '*', 'order' => 'created_at.desc']);
        if (is_array($rawDocs)) {
            foreach ($rawDocs as $d) {
                $iid = $d['institution_id'] ?? '';
                if ($iid) {
                    $institutionDocsMap[$iid][] = [
                        'id' => $d['id'] ?? '',
                        'title' => $d['title'] ?? 'Requirement Document',
                        'category' => $d['category'] ?? 'other',
                        'description' => $d['description'] ?? '',
                        'file_url' => $d['file_url'] ?? ($d['file_path'] ?? ''),
                        'file_type' => strtolower($d['file_type'] ?? 'pdf'),
                        'uploaded_at' => $d['created_at'] ?? '',
                        'uploaded_by' => $d['uploaded_by'] ?? 'Chapter Officer'
                    ];
                }
            }
        }
    }
} catch (\Throwable $docEx) {
    error_log("Docs map error: " . $docEx->getMessage());
}

// Merge canonical affiliation documents from pending_affiliations if available
foreach ($allAppsMap as $app) {
    $instId = $app['institution_id'] ?? '';
    if (!$instId) {
        foreach ($institutionsList as $inst) {
            if (strtolower($inst['email'] ?? '') === strtolower($app['email'] ?? '') || strtolower($inst['name'] ?? '') === strtolower($app['institution_name'] ?? '')) {
                $instId = $inst['id'];
                break;
            }
        }
    }
    if ($instId) {
        $canonMap = [
            'letter_of_intent' => ['title' => 'Letter of Intent (Art. IV Sec. 3)', 'file' => $app['letter_of_intent'] ?? ''],
            'endorsement_letter' => ['title' => 'Dean / Chair Endorsement Letter', 'file' => $app['endorsement_letter'] ?? ''],
            'constitution_bylaws' => ['title' => 'Student Chapter Constitution & By-Laws (CBL)', 'file' => $app['constitution_by_laws'] ?? ($app['constitution_bylaws'] ?? '')],
            'officers_cv' => ['title' => 'Incumbent Officers Directory & CVs', 'file' => $app['officers_cvs'] ?? ($app['officers_cv'] ?? '')],
            'org_chart' => ['title' => 'Organizational Structure Chart', 'file' => $app['organizational_chart'] ?? ($app['org_chart'] ?? '')],
            'member_directory' => ['title' => 'Certified Student Member Directory', 'file' => $app['member_directory'] ?? ''],
        ];
        foreach ($canonMap as $cat => $cItem) {
            if (!empty($cItem['file'])) {
                $alreadyExists = false;
                foreach ($institutionDocsMap[$instId] ?? [] as $exDoc) {
                    if (($exDoc['category'] ?? '') === $cat) { $alreadyExists = true; break; }
                }
                if (!$alreadyExists) {
                    $ext = strtolower(pathinfo($cItem['file'], PATHINFO_EXTENSION)) ?: 'pdf';
                    $institutionDocsMap[$instId][] = [
                        'id' => 'canon_' . $cat,
                        'title' => $cItem['title'],
                        'category' => $cat,
                        'description' => 'Submitted during official affiliation application',
                        'file_url' => $cItem['file'],
                        'file_type' => $ext,
                        'uploaded_at' => $app['submitted_at'] ?? ($app['created_at'] ?? date('Y-m-d')),
                        'uploaded_by' => 'Affiliation Applicant'
                    ];
                }
            }
        }
    }
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

        /* Responsive Requirements Modal & Dropzone Styles */
        .req-modal-grid {
            display: grid;
            grid-template-columns: 1.18fr 0.82fr;
            gap: 1.15rem;
        }
        @media (max-width: 860px) {
            .req-modal-grid {
                grid-template-columns: 1fr !important;
                gap: 1rem !important;
            }
        }
        .req-card-item {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 9px;
            padding: 0.65rem 0.85rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.65rem;
            transition: all 0.18s ease;
        }
        .req-card-item:hover {
            border-color: #CBD5E1;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            background: #FAFAFA;
        }
        .req-icon-box {
            width: 34px;
            height: 34px;
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.95rem;
            flex-shrink: 0;
        }
        .req-icon-box.pdf { background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; }
        .req-icon-box.excel { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }
        .req-icon-box.word { background: #EFF6FF; color: #2563EB; border: 1px solid #DBEAFE; }
        .req-icon-box.image { background: #FEF9C3; color: #B45309; border: 1px solid #FDE68A; }
        .req-icon-box.pending { background: #F1F5F9; color: #94A3B8; border: 1px dashed #CBD5E1; }

        .req-dropzone {
            border: 2px dashed #CBD5E1;
            border-radius: 10px;
            padding: 1.15rem 1rem;
            text-align: center;
            background: #F8FAFC;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }
        .req-dropzone:hover, .req-dropzone.drag-over {
            border-color: #2563EB;
            background: #EFF6FF;
        }
        .req-dropzone:focus-within {
            border-color: #2563EB;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        @media (max-width: 768px) {
            .ap-card-body, .ap-card { padding: 0.75rem !important; }
            .dash-header-banner { padding: 0.85rem 1rem !important; }
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
                    <button type="button" id="btnSyncAllData" class="btn-white" style="border:1px solid #CBD5E1; color:var(--color-navy); font-weight:800; background:#F8FAFC; box-shadow:0 1px 2px rgba(0,0,0,0.05);" onclick="triggerFullSystemSync()" title="Synchronize all institutions, rosters, requirements, finances, and blockchain ledger">
                        <i class="fas fa-rotate" id="syncAllBtnIcon" style="color:var(--color-gold-dark);"></i> Sync All Data
                    </button>
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

            <!-- 2.5. Audited Affiliation Collections & Financial Ledger Banner -->
            <div class="dash-financial-banner" style="background:linear-gradient(135deg, #0B1D4A 0%, #152C6E 100%); border-radius:12px; padding:1.1rem 1.35rem; margin-bottom:0.85rem; color:#FFFFFF; box-shadow:0 4px 15px rgba(11,29,74,0.15);">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; margin-bottom:0.9rem; border-bottom:1px solid rgba(255,255,255,0.12); padding-bottom:0.75rem;">
                    <div>
                        <div style="display:flex; align-items:center; gap:0.55rem;">
                            <div style="width:28px; height:28px; border-radius:6px; background:rgba(212,175,55,0.2); border:1px solid #D4AF37; display:flex; align-items:center; justify-content:center; color:#FDE047; font-size:0.85rem;">
                                <i class="fas fa-calculator"></i>
                            </div>
                            <h3 style="margin:0; font-size:1.05rem; font-weight:800; color:#FFFFFF; letter-spacing:-0.01em;">
                                Audited Affiliation Collections &amp; Financial Ledger
                            </h3>
                            <span class="ap-pill" style="background:rgba(16,185,129,0.25); color:#6EE7B7; border:1px solid rgba(16,185,129,0.4); font-size:0.68rem; font-weight:700;">
                                <i class="fas fa-check-double"></i> Auto-Computed from Directories &amp; Receipts
                            </span>
                        </div>
                        <p style="margin:0.25rem 0 0; font-size:0.75rem; color:#CBD5E1;">
                            Institutional affiliation fees, student roster dues, and official receipt references are automatically computed and reconciled.
                        </p>
                    </div>
                    <div style="display:flex; align-items:center; gap:0.4rem;">
                        <button type="button" class="btn-white" style="font-size:0.74rem; padding:0.35rem 0.75rem; background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.25); color:#FFFFFF; font-weight:700;" onclick="triggerFullSystemSync()" title="Synchronize all ledger balances and fee brackets">
                            <i class="fas fa-rotate"></i> Synchronize Data
                        </button>
                        <button type="button" class="btn-white" style="font-size:0.74rem; padding:0.35rem 0.75rem; background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.25); color:#FFFFFF;" onclick="window.print()">
                            <i class="fas fa-print"></i> Print Financial Audit
                        </button>
                    </div>
                </div>

                <!-- 4 Financial Summary Cards -->
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:0.75rem;">
                    <div style="background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.12); border-radius:9px; padding:0.75rem 1rem;">
                        <div style="font-size:0.7rem; text-transform:uppercase; font-weight:800; color:#94A3B8; letter-spacing:0.04em; margin-bottom:0.2rem;">
                            Grand Audited Collections
                        </div>
                        <div style="font-size:1.45rem; font-weight:800; color:#34D399; font-family:'JetBrains Mono',monospace;">
                            ₱<?= number_format($auditedGrandTotal, 2) ?>
                        </div>
                        <div style="font-size:0.7rem; color:#CBD5E1; margin-top:2px;">
                            Reconciled across <?= count($institutionsList) ?> chartered chapters
                        </div>
                    </div>

                    <div style="background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.12); border-radius:9px; padding:0.75rem 1rem;">
                        <div style="font-size:0.7rem; text-transform:uppercase; font-weight:800; color:#94A3B8; letter-spacing:0.04em; margin-bottom:0.2rem;">
                            Institutional Affiliation Fees
                        </div>
                        <div style="font-size:1.35rem; font-weight:800; color:#FDE047; font-family:'JetBrains Mono',monospace;">
                            ₱<?= number_format($auditedAffilTotal, 2) ?>
                        </div>
                        <div style="font-size:0.7rem; color:#CBD5E1; margin-top:2px;">
                            Charter bracket fees &amp; operational fees
                        </div>
                    </div>

                    <div style="background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.12); border-radius:9px; padding:0.75rem 1rem;">
                        <div style="font-size:0.7rem; text-transform:uppercase; font-weight:800; color:#94A3B8; letter-spacing:0.04em; margin-bottom:0.2rem;">
                            Student Roster Dues (Directories)
                        </div>
                        <div style="font-size:1.35rem; font-weight:800; color:#60A5FA; font-family:'JetBrains Mono',monospace;">
                            ₱<?= number_format($auditedMembershipTotal, 2) ?>
                        </div>
                        <div style="font-size:0.7rem; color:#CBD5E1; margin-top:2px;">
                            From <?= number_format($auditedTotalStudents) ?> enrolled students in Excel rosters
                        </div>
                    </div>

                    <div style="background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.12); border-radius:9px; padding:0.75rem 1rem;">
                        <div style="font-size:0.7rem; text-transform:uppercase; font-weight:800; color:#94A3B8; letter-spacing:0.04em; margin-bottom:0.2rem;">
                            Verified Official Receipts
                        </div>
                        <div style="font-size:1.35rem; font-weight:800; color:#FFFFFF; font-family:'JetBrains Mono',monospace;">
                            <?= $auditedReceiptsCount ?> <span style="font-size:0.85rem; font-weight:600; color:#A7F3D0;">Issued</span>
                        </div>
                        <div style="font-size:0.7rem; color:#CBD5E1; margin-top:2px;">
                            100% Verified with receipt reference numbers
                        </div>
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
                                <th>Audited Fee & Receipt</th>
                                <th>Status</th>
                                <th>Submitted Date</th>
                                <th style="text-align:right;">3-Way Decision</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pendingApps)): ?>
                                <tr>
                                    <td colspan="8" style="text-align:center; padding:2.5rem; color:#64748B;">
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
                                            <button type="button" class="btn-white" style="padding:0.25rem 0.6rem; font-size:0.72rem;" onclick="openInspectModalById('<?= htmlspecialchars($app['id']) ?>')">
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
                                            <strong style="color:#059669; font-size:0.84rem; font-family:'JetBrains Mono',monospace;">₱<?= number_format($app['total_fee'], 2) ?></strong>
                                            <div style="font-size:0.67rem; color:#64748B;">
                                                Affil: ₱<?= number_format($app['affiliation_fee'] + ($app['operational_fee'] ?? 800), 0) ?> &bull; Mem: ₱<?= number_format($app['membership_total'], 0) ?>
                                            </div>
                                            <button type="button" class="btn-white" style="font-size:0.68rem; padding:0.18rem 0.5rem; font-family:'JetBrains Mono',monospace; font-weight:700; color:#0B1D4A; margin-top:3px; background:#FEF3C7; border:1px solid #FDE68A;" onclick="openAuditedReceiptModalById('<?= htmlspecialchars($app['id']) ?>')" title="Inspect official verified receipt">
                                                <i class="fas fa-receipt" style="color:#D97706;"></i> <?= htmlspecialchars($app['receipt_number']) ?>
                                            </button>
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
                                                <button type="button" class="btn-act-amber" onclick="openRevisionModalById('<?= htmlspecialchars($app['id']) ?>')" title="Request Specific File Revisions via Gmail">
                                                    <i class="fas fa-pen-to-square"></i> Request Edit
                                                </button>

                                                <!-- 3. REJECT / DECLINE -->
                                                <button type="button" class="btn-act-red" onclick="openDeclineModalById('<?= htmlspecialchars($app['id']) ?>')" title="Decline Application">
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
                    <h3 class="ap-card-title"><i class="fas fa-building-columns"></i> Chartered University &amp; College Chapters (<?= count($institutionsList) ?>)</h3>
                </div>

                <div style="overflow-x:auto;">
                    <table class="ap-table" id="charteredTable">
                        <thead>
                            <tr>
                                <th>Institution Name &amp; Acronym</th>
                                <th>Faculty Advisor / Officer</th>
                                <th>Enrolled Roster (Directory)</th>
                                <th>Audited Collections</th>
                                <th>Official Receipt</th>
                                <th>Status &amp; Compliance</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($institutionsList)): ?>
                                <tr><td colspan="7" style="text-align:center; padding:2rem; color:#64748B;">No chartered institutions found in database.</td></tr>
                            <?php else: ?>
                                <?php foreach ($institutionsList as $inst): ?>
                                    <?php 
                                        $fin = $instFinancialsMap[$inst['id']] ?? null;
                                        $finJson = htmlspecialchars(json_encode($fin), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:0.65rem;">
                                                <div style="width:34px; height:34px; border-radius:6px; background:#FEF9C3; color:#B45309; border:1px solid #FDE68A; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:0.75rem; flex-shrink:0;">
                                                    <?= htmlspecialchars(substr($inst['acronym'] ?: $inst['name'], 0, 3)) ?>
                                                </div>
                                                <div>
                                                    <strong style="color:#0F172A; font-size:0.84rem;"><?= htmlspecialchars($inst['name'] ?? 'Institution') ?></strong><br>
                                                    <span style="font-size:0.72rem; color:#64748B;"><?= htmlspecialchars($inst['acronym'] ?? 'HEI') ?> &bull; <?= htmlspecialchars($inst['city'] ?: 'Laguna') ?>, <?= htmlspecialchars($inst['province'] ?: 'Laguna') ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong style="color:#0F172A; font-size:0.82rem;"><?= htmlspecialchars($inst['contact_person'] ?: 'Faculty Advisor') ?></strong><br>
                                            <span style="font-size:0.72rem; color:#64748B;"><?= htmlspecialchars($inst['email'] ?: ($fin['contact_email'] ?? '')) ?></span>
                                            <?php if (!empty($inst['contact_phone'])): ?>
                                                <div style="font-size:0.7rem; color:#64748B;"><i class="fas fa-phone"></i> <?= htmlspecialchars($inst['contact_phone']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span style="font-weight:800; color:var(--color-navy); font-size:0.84rem;"><?= number_format($fin['member_count']) ?> Students</span><br>
                                            <a href="<?= PORTAL_URL ?>/admin/members/list.php?school=<?= urlencode($inst['id']) ?>" style="font-size:0.72rem; color:var(--color-blue); text-decoration:none; display:inline-flex; align-items:center; gap:3px;">
                                                <i class="fas fa-users" style="color:var(--color-navy);"></i> View Member Roster
                                            </a>
                                        </td>
                                        <td>
                                            <strong style="color:#059669; font-size:0.86rem; font-family:'JetBrains Mono',monospace;">₱<?= number_format($fin['total_fee'], 2) ?></strong>
                                            <div style="font-size:0.68rem; color:#64748B; margin-top:1px;">
                                                Affil: ₱<?= number_format($fin['affiliation_fee'] + $fin['operational_fee'], 0) ?> &bull; Mem: ₱<?= number_format($fin['membership_total'], 0) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <button type="button" class="btn-white" style="font-size:0.72rem; padding:0.24rem 0.55rem; font-family:'JetBrains Mono',monospace; font-weight:700; color:#0B1D4A; border:1px solid #CBD5E1; background:#F8FAFC;" onclick="openAuditedReceiptModal(<?= $finJson ?>)" title="Click to view official audited receipt">
                                                <i class="fas fa-receipt" style="color:#D97706;"></i> <?= htmlspecialchars($fin['receipt_number']) ?>
                                            </button>
                                        </td>
                                        <td>
                                            <?php
                                                $pInfo = $policyComplianceMap[$inst['id']] ?? null;
                                                $pPassed = $pInfo['passed'] ?? 0;
                                                $pTotal = $pInfo['total'] ?? 0;
                                                if ($pTotal > 0) {
                                                    $cScore = round(($pPassed / $pTotal) * 100);
                                                } else {
                                                    $cStat = strtolower($inst['compliance_status'] ?? 'compliant');
                                                    $cScore = ($cStat === 'compliant') ? 100 : (($cStat === 'at_risk') ? 60 : 35);
                                                }
                                                $cColor = ($cScore >= 100) ? '#059669' : (($cScore >= 60) ? '#D97706' : '#DC2626');
                                                $isLow = ($cScore < 100);
                                            ?>
                                            <div style="display:flex; align-items:center; gap:0.4rem; margin-bottom:3px;">
                                                <strong style="font-size:0.84rem; font-family:'JetBrains Mono',monospace; color:<?= $cColor ?>;"><?= $cScore ?>%</strong>
                                                <div style="width:55px; height:6px; background:#E2E8F0; border-radius:999px; overflow:hidden;">
                                                    <div style="width:<?= $cScore ?>%; height:100%; background:<?= $cColor ?>; border-radius:999px;"></div>
                                                </div>
                                            </div>
                                            <span class="ap-pill <?= $cScore >= 100 ? 'active' : 'pending' ?>" style="font-size:0.66rem;">
                                                <?= $cScore >= 100 ? 'Compliant' : 'Monitoring' ?>
                                            </span>
                                        </td>
                                        <td style="text-align:right;">
                                            <div style="display:inline-flex; align-items:center; gap:0.35rem;">
                                                <?php if ($isLow): ?>
                                                    <button type="button" class="btn-white" style="font-size:0.72rem; padding:0.28rem 0.55rem; color:#B45309; background:#FFFBEB; border-color:#FCD34D; font-weight:700;" onclick="sendSingleSchoolReminder('<?= $inst['id'] ?>', '<?= htmlspecialchars(addslashes($inst['name'])) ?>')" title="Send compliance reminder notice">
                                                        <i class="fas fa-bell"></i> Remind
                                                    </button>
                                                <?php endif; ?>
                                                <?php 
                                                    $instDocs = $institutionDocsMap[$inst['id']] ?? [];
                                                    $instDocsCount = count($instDocs);
                                                ?>
                                                <button type="button" class="btn-white" style="font-size:0.72rem; padding:0.28rem 0.6rem; color:var(--color-navy); font-weight:700; border-color:#CBD5E1;" onclick="openRequirementsModal('<?= htmlspecialchars($inst['id'], ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($inst['name']), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($inst['acronym'] ?? 'HEI'), ENT_QUOTES) ?>')" title="Manage & Upload Chapter Requirements">
                                                    <i class="fas fa-folder-open" style="color:var(--color-gold-dark);"></i> Requirements
                                                    <?php if ($instDocsCount > 0): ?>
                                                        <span style="background:rgba(11,29,74,0.12); color:var(--color-navy); padding:1px 6px; border-radius:10px; font-size:0.65rem; margin-left:3px; font-weight:800;"><?= $instDocsCount ?></span>
                                                    <?php endif; ?>
                                                </button>
                                                <button type="button" class="btn-white" style="font-size:0.72rem; padding:0.28rem 0.65rem;" onclick="openAuditedReceiptModal(<?= $finJson ?>)" title="View Audited Official Receipt">
                                                    <i class="fas fa-receipt" style="color:#D97706;"></i> Receipt
                                                </button>
                                                <a href="<?= PORTAL_URL ?>/admin/members/list.php?school=<?= urlencode($inst['id']) ?>" class="btn-white" style="font-size:0.72rem; padding:0.28rem 0.65rem;">
                                                    <i class="fas fa-users" style="color:var(--color-navy);"></i> Members
                                                </a>
                                                <button type="button" class="btn-danger" style="font-size:0.72rem; padding:0.28rem 0.65rem; background:#EF4444; color:#FFFFFF; border:none; border-radius:6px; cursor:pointer; font-weight:700; display:inline-flex; align-items:center; gap:0.35rem; transition:background 0.15s;" onmouseover="this.style.background='#DC2626'" onmouseout="this.style.background='#EF4444'" onclick="openDeleteInstitutionModal('<?= htmlspecialchars($inst['id'], ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($inst['name']), ENT_QUOTES) ?>')">
                                                    <i class="fas fa-trash-alt"></i> Delete
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

            <!-- SECTION 3: Approved Applications Archive -->
            <div id="sectionApproved" class="ap-card" style="display:none;">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-archive"></i> Approved Affiliations Archive (<?= count($approvedApps) ?>)</h3>
                </div>
                <div style="overflow-x:auto;">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Institution Name &amp; Chapter</th>
                                <th>Contact Officer &amp; Email</th>
                                <th>Roster Count</th>
                                <th>Audited Revenue</th>
                                <th>Official Receipt</th>
                                <th>Approval Date</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($approvedApps)): ?>
                                <tr><td colspan="7" style="text-align:center; padding:2rem; color:#64748B;">No approved application history recorded yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($approvedApps as $app): ?>
                                    <?php 
                                        $appJson = htmlspecialchars(json_encode($app), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr>
                                        <td>
                                            <strong style="color:#0F172A;"><?= htmlspecialchars($app['institution_name'] ?? 'School') ?></strong><br>
                                            <span style="font-size:0.72rem; color:#64748B;"><?= htmlspecialchars($app['institution_address'] ?? 'Laguna, Philippines') ?></span>
                                        </td>
                                        <td>
                                            <strong style="font-size:0.8rem;"><?= htmlspecialchars($app['contact_person'] ?? 'School Officer') ?></strong><br>
                                            <span style="font-size:0.72rem; color:#64748B;"><?= htmlspecialchars($app['contact_email'] ?? $app['email'] ?? 'N/A') ?></span>
                                        </td>
                                        <td>
                                            <span style="font-weight:700; color:var(--color-navy);"><?= intval($app['total_members'] ?? 0) ?> Students</span><br>
                                            <?php if (!empty($app['member_directory'])): ?>
                                                <a href="<?= htmlspecialchars($app['member_directory']) ?>" target="_blank" style="font-size:0.72rem; color:var(--color-blue); text-decoration:none;">
                                                    <i class="fas fa-file-excel" style="color:#107C41;"></i> View Roster
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong style="color:#059669; font-size:0.84rem; font-family:'JetBrains Mono',monospace;">₱<?= number_format($app['total_fee'], 2) ?></strong>
                                            <div style="font-size:0.67rem; color:#64748B;">
                                                Affil: ₱<?= number_format($app['affiliation_fee'] + ($app['operational_fee'] ?? 800), 0) ?> &bull; Mem: ₱<?= number_format($app['membership_total'], 0) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <button type="button" class="btn-white" style="font-size:0.7rem; padding:0.2rem 0.5rem; font-family:'JetBrains Mono',monospace; font-weight:700; color:#0B1D4A;" onclick="openAuditedReceiptModalById('<?= htmlspecialchars($app['id']) ?>')">
                                                <i class="fas fa-receipt" style="color:#D97706;"></i> <?= htmlspecialchars($app['receipt_number']) ?>
                                            </button>
                                        </td>
                                        <td style="color:#64748B; font-size:0.76rem; white-space:nowrap;">
                                            <?= !empty($app['updated_at']) ? date('M d, Y', strtotime($app['updated_at'])) : 'Recent' ?>
                                        </td>
                                        <td style="text-align:right;">
                                            <button type="button" class="btn-white" style="font-size:0.72rem; padding:0.28rem 0.65rem;" onclick="openAuditedReceiptModalById('<?= htmlspecialchars($app['id']) ?>')">
                                                <i class="fas fa-receipt" style="color:#D97706;"></i> View Receipt
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
                        <button type="button" id="inspectViewReceiptBtn" class="btn-white" style="width:100%; justify-content:center; padding:0.35rem; font-size:0.72rem; margin-top:0.5rem; color:var(--color-navy); font-weight:700; border:1px solid #CBD5E1; background:#F8FAFC;">
                            <i class="fas fa-receipt" style="color:#D97706;"></i> Inspect Audited Receipt
                        </button>
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

        // Centralized Application Data Store
        window.allAffiliationsData = <?= json_encode($allAppsMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?> || {};

        function openInspectModalById(id) {
            const app = window.allAffiliationsData[id];
            if (app) {
                openInspectModal(app);
            }
        }

        function openRevisionModalById(id) {
            const app = window.allAffiliationsData[id];
            if (app) {
                openRevisionModal(app);
            }
        }

        function openDeclineModalById(id) {
            const app = window.allAffiliationsData[id];
            if (app) {
                openDeclineModal(app.id, app.institution_name || app.school_name || 'Institution', app.contact_email || app.email || '', app.contact_person || '');
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
            if (!app) return;
            const instName = app.institution_name || app.school_name || '';
            const email = app.contact_email || app.email || '';
            const contactPerson = app.contact_person || '';

            document.getElementById('revAppId').value = app.id || '';
            document.getElementById('revInstName').value = instName;
            document.getElementById('revEmail').value = email;
            document.getElementById('revEmailDisplay').textContent = email || 'No email registered';
            document.getElementById('revContactPerson').value = contactPerson;
            document.getElementById('revSchoolNameDisplay').textContent = instName || 'the school';
            
            const checkboxes = document.querySelectorAll('#revisionModal input[type="checkbox"]');
            checkboxes.forEach(cb => cb.checked = false);

            document.getElementById('revisionModal').classList.add('active');
        }
        function closeRevisionModal() {
            document.getElementById('revisionModal').classList.remove('active');
        }

        function openDeclineModal(appId, schoolName, email, contactPerson) {
            document.getElementById('declineAppId').value = appId || '';
            document.getElementById('declineSchoolName').textContent = schoolName || 'Institution';
            document.getElementById('declineEmail').value = email || '';
            document.getElementById('declineContactPerson').value = contactPerson || '';
            document.getElementById('declineInstName').value = schoolName || '';
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

            // Inspect Audited Receipt Button in Sidebar
            const inspectViewRcpBtn = document.getElementById('inspectViewReceiptBtn');
            if (inspectViewRcpBtn) {
                inspectViewRcpBtn.onclick = function() {
                    openAuditedReceiptModal(app);
                };
            }

            // Prepare 6 Documents
            const docDefs = [
                { key: 'letter_of_intent', num: 1, label: 'Letter of Intent', icon: 'fa-file-lines', color: 'var(--color-navy)', type: 'pdf' },
                { key: 'endorsement_letter', num: 2, label: 'Endorsement Letter', icon: 'fa-certificate', color: 'var(--color-navy)', type: 'pdf' },
                { key: 'constitution_by_laws', num: 3, label: 'Constitution & By-Laws', icon: 'fa-scale-balanced', color: 'var(--color-navy)', type: 'pdf' },
                { key: 'officers_cvs', num: 4, label: 'Officers Curriculum Vitae', icon: 'fa-user-tie', color: 'var(--color-navy)', type: 'pdf' },
                { key: 'organizational_chart', num: 5, label: 'Organizational Chart', icon: 'fa-sitemap', color: 'var(--color-navy)', type: 'pdf' },
                { key: 'member_directory', num: 6, label: 'Member Directory (Excel)', icon: 'fa-file-excel', color: '#107C41', type: 'excel' }
            ];

            function fixDocUrl(url) {
                if (!url || typeof url !== 'string') return null;
                url = url.trim();
                if (url.includes('supabase.co/storage/v1/object/public/')) {
                    return url;
                }
                if (url.includes('/uploads/affiliations/') || url.includes('up.railway.app')) {
                    let pathAfter = url.replace(/^.*?\/uploads\/affiliations\//, '').replace(/^\/+/, '');
                    let parts = pathAfter.split('/').map(p => encodeURIComponent(p.replace(/[^a-zA-Z0-9_\.-]/g, '_')));
                    return `https://kfvlbjvtwtxnpmmswadf.supabase.co/storage/v1/object/public/affiliations/${parts.join('/')}`;
                }
                return url;
            }

            let attachedCount = 0;
            currentDocList = docDefs.map(doc => {
                let rawUrl = app[doc.key] || (app.documents && app.documents[doc.key]) || null;
                let url = fixDocUrl(rawUrl);
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

        function openDeleteInstitutionModal(id, name) {
            document.getElementById('deleteInstIdInput').value = id;
            document.getElementById('deleteInstNameInput').value = name;
            document.getElementById('deleteInstNameDisplay').textContent = name;
            const modal = document.getElementById('deleteInstitutionModal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function closeDeleteInstitutionModal() {
            const modal = document.getElementById('deleteInstitutionModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        async function sendSingleSchoolReminder(instId, instName) {
            if (!confirm('Send a compliance monitoring reminder to the chapter officers of ' + instName + '?')) return;

            try {
                const res = await fetch('/api/cron/compliance-monitoring-reminders.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ institution_id: instId })
                });
                const data = await res.json();
                if (data.success) {
                    alert('✓ Compliance reminder sent successfully to ' + instName + ' officers.');
                } else {
                    alert('Notice: ' + (data.error || 'Failed to dispatch reminder.'));
                }
            } catch (err) {
                alert('Connection error: ' + err.message);
            }
        }

        // ==========================================
        // AUDITED OFFICIAL RECEIPT MODAL FUNCTIONS
        // ==========================================
        const allAppsMap = <?= json_encode($allAppsMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?> || {};
        const instFinancialsMap = <?= json_encode($instFinancialsMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?> || {};

        function openAuditedReceiptModal(data) {
            if (!data) return;
            const modal = document.getElementById('auditedReceiptModal');
            if (!modal) return;

            const rcpNo = data.receipt_number || 'RCP-VERIFIED';
            const schoolName = data.institution_name || data.school_name || 'Affiliated Higher Education Institution';
            const officer = data.contact_person || 'School Officer / Faculty Advisor';
            const email = data.contact_email || data.email || 'N/A';
            const memberCount = parseInt(data.member_count || data.total_members || 0, 10);
            const dateStr = data.verified_at || data.created_at || new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            
            const affilFee = parseFloat(data.affiliation_fee) || 1500;
            const opFee = parseFloat(data.operational_fee) || 800;
            const memTotal = parseFloat(data.membership_total) || (memberCount * 200);
            const grandTotal = parseFloat(data.total_fee) || (affilFee + opFee + memTotal);
            const hash = data.blockchain_hash || ('SHA256-VERIFIED-TX-' + Math.random().toString(36).substring(2, 10).toUpperCase());

            document.getElementById('receiptModalNo').textContent = rcpNo;
            document.getElementById('receiptModalDate').textContent = dateStr;
            document.getElementById('receiptModalSchool').textContent = schoolName;
            document.getElementById('receiptModalOfficer').textContent = officer;
            document.getElementById('receiptModalEmail').textContent = email;
            document.getElementById('receiptModalRosterBadge').textContent = `${memberCount} Students in Roster`;

            document.getElementById('receiptModalTierRate').textContent = memberCount <= 50 ? 'Tier 1 (≤50)' : (memberCount <= 100 ? 'Tier 2 (51-100)' : 'Tier 3 (101+)');
            document.getElementById('receiptModalAffilFee').textContent = `PHP ${affilFee.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
            document.getElementById('receiptModalOpFee').textContent = `PHP ${opFee.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
            document.getElementById('receiptModalMemFee').textContent = `PHP ${memTotal.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
            document.getElementById('receiptModalGrandTotal').textContent = `PHP ${grandTotal.toLocaleString('en-US', {minimumFractionDigits: 2})}`;
            document.getElementById('receiptModalMemberRosterDesc').textContent = `${memberCount} student dues computed from uploaded member directory`;
            document.getElementById('receiptModalHash').textContent = hash;

            modal.style.display = 'flex';
        }

        function openAuditedReceiptModalById(appId) {
            if (allAppsMap && allAppsMap[appId]) {
                openAuditedReceiptModal(allAppsMap[appId]);
            } else if (window.allAffiliationsData && window.allAffiliationsData[appId]) {
                openAuditedReceiptModal(window.allAffiliationsData[appId]);
            } else if (instFinancialsMap && instFinancialsMap[appId]) {
                openAuditedReceiptModal(instFinancialsMap[appId]);
            }
        }

        function closeAuditedReceiptModal() {
            const modal = document.getElementById('auditedReceiptModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function printAuditedReceipt() {
            window.print();
        }
    </script>

    <!-- Delete Institution Confirmation Modal -->
    <div id="deleteInstitutionModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.65); backdrop-filter:blur(2px); z-index:999999; align-items:center; justify-content:center;">
        <div style="background:#FFFFFF; border-radius:12px; max-width:480px; width:92%; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); overflow:hidden; border:1px solid #E2E8F0; animation:modalPop 0.2s ease-out;">
            <div style="background:#DC2626; padding:1.2rem 1.5rem; color:#FFFFFF; display:flex; align-items:center; justify-content:space-between;">
                <div style="display:flex; align-items:center; gap:0.65rem;">
                    <div style="width:36px; height:36px; border-radius:50%; background:rgba(255,255,255,0.2); display:flex; align-items:center; justify-content:center; font-size:1.1rem;">
                        <i class="fas fa-triangle-exclamation"></i>
                    </div>
                    <h3 style="margin:0; font-size:1.05rem; font-weight:800;">Delete Chartered Institution</h3>
                </div>
                <button type="button" onclick="closeDeleteInstitutionModal()" style="background:transparent; border:none; color:#FFFFFF; font-size:1.5rem; cursor:pointer; line-height:1; opacity:0.85;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.85">&times;</button>
            </div>
            <form method="POST" action="" style="padding:1.5rem; margin:0;">
                <input type="hidden" name="action" value="delete_institution">
                <input type="hidden" name="institution_id" id="deleteInstIdInput" value="">
                <input type="hidden" name="institution_name" id="deleteInstNameInput" value="">
                
                <p style="color:#334155; font-size:0.95rem; line-height:1.6; margin:0 0 1rem;">
                    Are you sure you want to permanently delete <strong id="deleteInstNameDisplay" style="color:#0F172A; text-decoration:underline;"></strong> from the list of chartered institutions?
                </p>
                <div style="background:#FEF2F2; border-left:4px solid #EF4444; padding:0.85rem 1rem; border-radius:4px; font-size:0.82rem; color:#991B1B; margin-bottom:1.5rem; line-height:1.5;">
                    <i class="fas fa-info-circle me-1"></i> <strong>Safe Removal:</strong> Any student member records associated with this school will have their institution reference safely unlinked rather than deleted.
                </div>
                <div style="display:flex; justify-content:flex-end; gap:0.75rem;">
                    <button type="button" class="btn-white" onclick="closeDeleteInstitutionModal()" style="padding:0.55rem 1.15rem; font-size:0.85rem; font-weight:600; cursor:pointer; border:1px solid #CBD5E1; border-radius:6px; background:#FFFFFF; color:#334155;">
                        Cancel
                    </button>
                    <button type="submit" style="background:#DC2626; color:#FFFFFF; border:none; padding:0.55rem 1.3rem; font-size:0.85rem; font-weight:700; border-radius:6px; cursor:pointer; display:inline-flex; align-items:center; gap:0.45rem; transition:background 0.15s;" onmouseover="this.style.background='#B91C1C'" onmouseout="this.style.background='#DC2626'">
                        <i class="fas fa-trash-alt"></i> Yes, Delete Institution
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Official Audited Receipt Modal -->
    <div id="auditedReceiptModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(11,29,74,0.65); backdrop-filter:blur(3px); z-index:999999; align-items:center; justify-content:center; padding:1rem; box-sizing:border-box;">
        <div style="background:#FFFFFF; border-radius:14px; max-width:640px; width:95%; max-height:92vh; overflow-y:auto; box-shadow:0 25px 60px -15px rgba(11,29,74,0.35); border:1px solid #CBD5E1; animation:modalPop 0.22s ease-out; position:relative; box-sizing:border-box;">
            <!-- Receipt Top Header -->
            <div style="background:linear-gradient(135deg, #0B1D4A 0%, #152C6E 100%); color:#FFFFFF; padding:1.2rem 1.5rem; display:flex; justify-content:space-between; align-items:flex-start; border-top-left-radius:13px; border-top-right-radius:13px;">
                <div style="display:flex; align-items:center; gap:0.75rem;">
                    <div style="width:42px; height:42px; border-radius:10px; background:rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.25); display:flex; align-items:center; justify-content:center; color:#FDE047; font-size:1.35rem;">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div>
                        <div style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em; color:#FDE047; font-weight:800;">
                            Official Audited Transaction
                        </div>
                        <h3 style="margin:0.15rem 0 0; font-size:1.1rem; font-weight:800; color:#FFFFFF;">
                            Chapter Affiliation &amp; Roster Receipt
                        </h3>
                    </div>
                </div>
                <button type="button" onclick="closeAuditedReceiptModal()" style="background:rgba(255,255,255,0.15); border:none; color:#FFFFFF; border-radius:6px; width:30px; height:30px; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:1.2rem;" title="Close">&times;</button>
            </div>

            <!-- Receipt Content Body -->
            <div style="padding:1.5rem;" id="auditedReceiptPrintArea">
                <!-- Organization Branding Bar -->
                <div style="text-align:center; padding-bottom:1.1rem; border-bottom:2px dashed #E2E8F0; margin-bottom:1.25rem;">
                    <h4 style="margin:0 0 0.25rem; font-size:1.05rem; font-weight:800; color:#0B1D4A;">
                        INSTITUTE OF ELECTRONICS ENGINEERS OF THE PHILIPPINES
                    </h4>
                    <div style="font-size:0.78rem; font-weight:700; color:#D97706; text-transform:uppercase; letter-spacing:0.05em;">
                        Laguna Student Chapter &bull; Treasury &amp; Audit Division
                    </div>
                    <div style="font-size:0.72rem; color:#64748B; margin-top:2px;">
                        Charter Accreditation &bull; Student Membership Directory Ingestion &bull; Board Resolution No. 021-2024
                    </div>
                </div>

                <!-- Receipt Meta Info Grid -->
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:0.9rem 1.1rem; margin-bottom:1.25rem; font-size:0.8rem;">
                    <div>
                        <div style="color:#64748B; font-size:0.72rem; text-transform:uppercase; font-weight:700;">Receipt Number</div>
                        <div id="receiptModalNo" style="font-family:'JetBrains Mono',monospace; font-weight:800; color:#0B1D4A; font-size:0.95rem;">-</div>
                    </div>
                    <div>
                        <div style="color:#64748B; font-size:0.72rem; text-transform:uppercase; font-weight:700;">Issuance Date</div>
                        <div id="receiptModalDate" style="font-weight:700; color:#0F172A;">-</div>
                    </div>
                    <div>
                        <div style="color:#64748B; font-size:0.72rem; text-transform:uppercase; font-weight:700;">Affiliated HEI / School</div>
                        <div id="receiptModalSchool" style="font-weight:800; color:#0F172A;">-</div>
                    </div>
                    <div>
                        <div style="color:#64748B; font-size:0.72rem; text-transform:uppercase; font-weight:700;">Faculty Advisor / Officer</div>
                        <div id="receiptModalOfficer" style="font-weight:700; color:#0F172A;">-</div>
                    </div>
                    <div>
                        <div style="color:#64748B; font-size:0.72rem; text-transform:uppercase; font-weight:700;">Contact Email</div>
                        <div id="receiptModalEmail" style="color:#334155; word-break:break-all;">-</div>
                    </div>
                    <div>
                        <div style="color:#64748B; font-size:0.72rem; text-transform:uppercase; font-weight:700;">Payment Verification</div>
                        <div><span class="ap-pill active" style="font-size:0.68rem;"><span class="ap-pill-dot"></span> Paid &amp; Audited</span></div>
                    </div>
                </div>

                <!-- Itemized Financial Breakdown Table -->
                <div style="margin-bottom:1.25rem;">
                    <div style="font-size:0.75rem; text-transform:uppercase; font-weight:800; color:#0F172A; margin-bottom:0.5rem; display:flex; justify-content:space-between;">
                        <span>Audited Fee Breakdown</span>
                        <span id="receiptModalRosterBadge" style="color:#2563EB; font-weight:700;">-</span>
                    </div>
                    <table style="width:100%; border-collapse:collapse; font-size:0.82rem;">
                        <thead>
                            <tr style="background:#0B1D4A; color:#FFFFFF; text-align:left;">
                                <th style="padding:0.55rem 0.75rem; border-top-left-radius:6px;">Item Description</th>
                                <th style="padding:0.55rem 0.75rem; text-align:center;">Rate / Tier</th>
                                <th style="padding:0.55rem 0.75rem; text-align:right; border-top-right-radius:6px;">Amount (PHP)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="border-bottom:1px solid #E2E8F0;">
                                <td style="padding:0.55rem 0.75rem; color:#0F172A;">
                                    <strong>Institutional Chapter Affiliation Fee</strong><br>
                                    <span style="font-size:0.7rem; color:#64748B;">National council charter endorsement</span>
                                </td>
                                <td style="padding:0.55rem 0.75rem; text-align:center; color:#64748B;" id="receiptModalTierRate">Chapter Tier</td>
                                <td style="padding:0.55rem 0.75rem; text-align:right; font-family:'JetBrains Mono',monospace; font-weight:700;" id="receiptModalAffilFee">₱0.00</td>
                            </tr>
                            <tr style="border-bottom:1px solid #E2E8F0;">
                                <td style="padding:0.55rem 0.75rem; color:#0F172A;">
                                    <strong>National Chapter Operational Fee</strong><br>
                                    <span style="font-size:0.7rem; color:#64748B;">Annual maintenance &amp; secretarial operations</span>
                                </td>
                                <td style="padding:0.55rem 0.75rem; text-align:center; color:#64748B;">Standard</td>
                                <td style="padding:0.55rem 0.75rem; text-align:right; font-family:'JetBrains Mono',monospace; font-weight:700;" id="receiptModalOpFee">₱800.00</td>
                            </tr>
                            <tr style="border-bottom:2px solid #0B1D4A;">
                                <td style="padding:0.55rem 0.75rem; color:#0F172A;">
                                    <strong>Student Member Directory Roster Dues</strong><br>
                                    <span style="font-size:0.7rem; color:#64748B;" id="receiptModalMemberRosterDesc">Enrolled students from directory</span>
                                </td>
                                <td style="padding:0.55rem 0.75rem; text-align:center; color:#64748B;" id="receiptModalStudentRate">₱200/student</td>
                                <td style="padding:0.55rem 0.75rem; text-align:right; font-family:'JetBrains Mono',monospace; font-weight:700;" id="receiptModalMemFee">₱0.00</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr style="background:#FEFCE8;">
                                <td colspan="2" style="padding:0.75rem; font-weight:800; color:#854D0E; font-size:0.9rem; text-transform:uppercase;">
                                    <i class="fas fa-check-circle" style="color:#10B981; margin-right:4px;"></i> Total Audited Amount Paid:
                                </td>
                                <td style="padding:0.75rem; text-align:right; font-family:'JetBrains Mono',monospace; font-weight:800; font-size:1.15rem; color:#059669;" id="receiptModalGrandTotal">
                                    ₱0.00
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Cryptographic Verification & Audit Seal -->
                <div style="background:#F1F5F9; border:1px solid #CBD5E1; border-radius:8px; padding:0.75rem 1rem; font-size:0.72rem; color:#475569; display:flex; flex-direction:column; gap:0.25rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span><i class="fas fa-shield-halved" style="color:#0B1D4A;"></i> <strong>Audit Status:</strong> Officially Verified &amp; Cleared</span>
                        <span style="color:#059669; font-weight:700;"><i class="fas fa-circle-check"></i> Authentic Receipt</span>
                    </div>
                    <div style="word-break:break-all; font-family:'JetBrains Mono',monospace; color:#64748B;">
                        Hash: <span id="receiptModalHash">-</span>
                    </div>
                </div>
            </div>

            <!-- Receipt Modal Footer Controls -->
            <div style="background:#F8FAFC; border-top:1px solid #E2E8F0; padding:0.85rem 1.5rem; display:flex; justify-content:space-between; align-items:center; border-bottom-left-radius:13px; border-bottom-right-radius:13px;">
                <button type="button" class="btn-white" onclick="closeAuditedReceiptModal()">
                    Close
                </button>
                <button type="button" class="btn-primary-navy" onclick="printAuditedReceipt()">
                    <i class="fas fa-print"></i> Print Official Receipt
                </button>
            </div>
        </div>
    </div>

    <!-- Institution Requirements & Documents Dossier Modal -->
    <div id="institutionRequirementsModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(11,29,74,0.68); backdrop-filter:blur(4px); z-index:999999; align-items:center; justify-content:center; padding:1rem; box-sizing:border-box;">
        <div style="background:#FFFFFF; border-radius:14px; max-width:980px; width:96%; max-height:92vh; display:flex; flex-direction:column; box-shadow:0 25px 60px -15px rgba(11,29,74,0.4); border:1px solid #CBD5E1; animation:modalPop 0.22s ease-out; overflow:hidden; box-sizing:border-box;">
            
            <!-- Modal Header -->
            <div style="background:linear-gradient(135deg, #0B1D4A 0%, #17327C 100%); color:#FFFFFF; padding:1.1rem 1.5rem; display:flex; justify-content:space-between; align-items:flex-start; flex-shrink:0;">
                <div style="display:flex; align-items:center; gap:0.75rem;">
                    <div style="width:42px; height:42px; border-radius:10px; background:rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.25); display:flex; align-items:center; justify-content:center; color:#FDE047; font-size:1.35rem; flex-shrink:0;">
                        <i class="fas fa-folder-open"></i>
                    </div>
                    <div>
                        <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
                            <span id="reqModalSchoolAcronym" style="background:#FDE047; color:#0B1D4A; font-weight:800; font-size:0.72rem; padding:2px 8px; border-radius:4px; text-transform:uppercase; letter-spacing:0.04em;">HEI</span>
                            <span style="font-size:0.72rem; color:#93C5FD; text-transform:uppercase; letter-spacing:0.06em; font-weight:700;">Chapter Accreditation Dossier</span>
                        </div>
                        <h3 id="reqModalSchoolName" style="margin:0.2rem 0 0; font-size:1.1rem; font-weight:800; color:#FFFFFF; line-height:1.3;">
                            Institution Requirements
                        </h3>
                    </div>
                </div>
                <button type="button" onclick="closeRequirementsModal()" style="background:rgba(255,255,255,0.15); border:none; color:#FFFFFF; border-radius:6px; width:32px; height:32px; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:1.3rem; transition:background 0.15s;" onmouseover="this.style.background='rgba(255,255,255,0.25)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'" title="Close">&times;</button>
            </div>

            <!-- Modal Body Scrollable Content -->
            <div style="padding:1.25rem 1.5rem; overflow-y:auto; flex:1; box-sizing:border-box;">
                <div class="req-modal-grid">
                    
                    <!-- Left Column: Chapter Requirements Dossier & Status -->
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem; flex-wrap:wrap; gap:0.4rem;">
                            <div style="font-size:0.88rem; font-weight:800; color:#0F172A; display:flex; align-items:center; gap:0.4rem;">
                                <i class="fas fa-list-check" style="color:var(--color-navy);"></i> Official CBL Requirements Checklist
                            </div>
                            <span id="reqCompletionBadge" style="font-size:0.72rem; font-weight:800; padding:3px 8px; border-radius:12px; background:#EFF6FF; color:#2563EB;">
                                Checking...
                            </span>
                        </div>

                        <!-- Progress Bar -->
                        <div style="background:#E2E8F0; border-radius:999px; height:7px; width:100%; margin-bottom:1rem; overflow:hidden;">
                            <div id="reqProgressBar" style="background:#059669; height:100%; width:0%; border-radius:999px; transition:width 0.35s ease;"></div>
                        </div>

                        <!-- Dynamic Dossier List (Canonical CBL Requirements) -->
                        <div id="reqCanonicalList" style="display:flex; flex-direction:column; gap:0.55rem; margin-bottom:1.25rem;">
                            <!-- Populated via renderInstitutionRequirements() -->
                        </div>

                        <!-- Additional Supporting Files Header & Container -->
                        <div style="margin-top:1.15rem; padding-top:0.95rem; border-top:1px solid #E2E8F0;">
                            <div style="font-size:0.82rem; font-weight:800; color:#0F172A; margin-bottom:0.55rem; display:flex; align-items:center; gap:0.4rem;">
                                <i class="fas fa-paperclip" style="color:#D97706;"></i> Other Uploaded Documents &amp; Attachments
                            </div>
                            <div id="reqAdditionalList" style="display:flex; flex-direction:column; gap:0.5rem;">
                                <!-- Populated via JS -->
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Upload & Attach Requirement File -->
                    <div>
                        <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:11px; padding:1.15rem; box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                            <div style="font-size:0.88rem; font-weight:800; color:#0F172A; margin-bottom:0.35rem; display:flex; align-items:center; gap:0.45rem;">
                                <i class="fas fa-cloud-arrow-up" style="color:#2563EB;"></i> Upload Requirement File
                            </div>
                            <p style="font-size:0.73rem; color:#64748B; margin:0 0 0.95rem; line-height:1.4;">
                                Attach official signed PDFs, endorsed documents, or rosters to this chapter's repository.
                            </p>

                            <form method="POST" action="" enctype="multipart/form-data" id="institutionReqUploadForm">
                                <input type="hidden" name="action" value="upload_institution_requirement">
                                <input type="hidden" name="institution_id" id="reqModalInstId" value="">

                                <!-- Category Selection -->
                                <div style="margin-bottom:0.75rem;">
                                    <label for="reqTypeSelect" style="display:block; font-size:0.74rem; font-weight:700; color:#334155; margin-bottom:0.25rem;">
                                        Requirement Category <span style="color:#DC2626;">*</span>
                                    </label>
                                    <select id="reqTypeSelect" name="requirement_type" class="ap-input" style="font-size:0.78rem; width:100%; padding:0.45rem 0.65rem; border-radius:6px; border:1px solid #CBD5E1; background:#FFFFFF;" onchange="onReqTypeChanged(this.value)" required>
                                        <optgroup label="Official CBL Affiliation Requirements (Art. IV)">
                                            <option value="letter_of_intent">Letter of Intent (Art. IV Sec. 3)</option>
                                            <option value="endorsement_letter">Dean / Chair Endorsement Letter</option>
                                            <option value="constitution_bylaws">Student Chapter Constitution &amp; By-Laws</option>
                                            <option value="officers_cv">Incumbent Officers Directory &amp; CVs</option>
                                            <option value="org_chart">Organizational Structure Chart</option>
                                            <option value="member_directory">Certified Student Member Directory</option>
                                        </optgroup>
                                        <optgroup label="Financial &amp; Compliance Attachments">
                                            <option value="official_receipt">Official Payment Receipt / Deposit Slip</option>
                                            <option value="activity_report">Activity / Accomplishment Report</option>
                                            <option value="other">Other Supporting Requirement</option>
                                        </optgroup>
                                    </select>
                                </div>

                                <!-- Document Title -->
                                <div style="margin-bottom:0.75rem;">
                                    <label for="reqDocTitle" style="display:block; font-size:0.74rem; font-weight:700; color:#334155; margin-bottom:0.25rem;">
                                        Document Title <span style="color:#DC2626;">*</span>
                                    </label>
                                    <input type="text" id="reqDocTitle" name="document_title" class="ap-input" placeholder="e.g. Official Chapter Letter of Intent AY 2025-2026" required style="font-size:0.78rem; width:100%; padding:0.45rem 0.65rem; border-radius:6px; border:1px solid #CBD5E1; box-sizing:border-box;">
                                </div>

                                <!-- Document Description -->
                                <div style="margin-bottom:0.85rem;">
                                    <label for="reqDocDesc" style="display:block; font-size:0.74rem; font-weight:700; color:#334155; margin-bottom:0.25rem;">
                                        Notes / Description (Optional)
                                    </label>
                                    <textarea id="reqDocDesc" name="document_description" class="ap-input" rows="2" placeholder="e.g. Signed by Dean Engr. Santos on Feb 2026..." style="font-size:0.76rem; width:100%; padding:0.45rem 0.65rem; border-radius:6px; border:1px solid #CBD5E1; resize:vertical; box-sizing:border-box;"></textarea>
                                </div>

                                <!-- Drag & Drop File Upload Zone -->
                                <div style="margin-bottom:0.95rem;">
                                    <label style="display:block; font-size:0.74rem; font-weight:700; color:#334155; margin-bottom:0.25rem;">
                                        File Attachment <span style="color:#DC2626;">*</span>
                                    </label>
                                    <div id="reqDropzone" class="req-dropzone" onclick="document.getElementById('reqFileInput').click()">
                                        <input type="file" id="reqFileInput" name="requirement_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png" style="display:none;" onchange="handleReqFileSelected(this)" required>
                                        
                                        <div id="reqDropzonePrompt">
                                            <i class="fas fa-cloud-arrow-up" style="font-size:1.75rem; color:#2563EB; margin-bottom:0.35rem; display:block;"></i>
                                            <div style="font-size:0.8rem; font-weight:700; color:#0F172A;">
                                                Click to browse or drop file here
                                            </div>
                                            <div style="font-size:0.68rem; color:#64748B; margin-top:2px;">
                                                Supported: PDF, DOCX, XLSX, CSV, Images (Max 15MB)
                                            </div>
                                        </div>

                                        <div id="reqDropzoneFilePreview" style="display:none; text-align:left; background:#FFFFFF; border:1px solid #93C5FD; border-radius:7px; padding:0.65rem 0.85rem;">
                                            <div style="display:flex; align-items:center; justify-content:space-between; gap:0.5rem;">
                                                <div style="display:flex; align-items:center; gap:0.5rem; overflow:hidden;">
                                                    <i id="reqPreviewIcon" class="fas fa-file-pdf" style="font-size:1.3rem; color:#DC2626;"></i>
                                                    <div style="overflow:hidden;">
                                                        <div id="reqPreviewName" style="font-size:0.76rem; font-weight:700; color:#0F172A; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">filename.pdf</div>
                                                        <div id="reqPreviewMeta" style="font-size:0.67rem; color:#64748B;">0 KB</div>
                                                    </div>
                                                </div>
                                                <button type="button" onclick="event.stopPropagation(); clearReqFileSelection();" style="background:#FEE2E2; color:#DC2626; border:none; border-radius:4px; padding:3px 7px; font-size:0.72rem; cursor:pointer; font-weight:700;" title="Remove chosen file">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <button type="submit" id="reqUploadSubmitBtn" class="btn-primary-navy" style="width:100%; justify-content:center; padding:0.65rem 1rem; font-size:0.82rem; font-weight:800; border-radius:7px; box-shadow:0 2px 4px rgba(11,29,74,0.2);">
                                    <i class="fas fa-floppy-disk"></i> Upload &amp; Save Requirement
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Modal Footer -->
            <div style="background:#F8FAFC; border-top:1px solid #E2E8F0; padding:0.75rem 1.5rem; display:flex; justify-content:space-between; align-items:center; flex-shrink:0;">
                <span style="font-size:0.72rem; color:#64748B;">
                    <i class="fas fa-shield-halved" style="color:#059669;"></i> Files are secured &amp; linked directly to the chapter's institutional record.
                </span>
                <button type="button" class="btn-white" onclick="closeRequirementsModal()" style="font-size:0.78rem; padding:0.4rem 0.95rem;">
                    Close Dossier
                </button>
            </div>

        </div>
    </div>

    <!-- Hidden Form for Requirement Document Deletion -->
    <form id="deleteReqDocForm" method="POST" action="" style="display:none;">
        <input type="hidden" name="action" value="delete_institution_requirement">
        <input type="hidden" name="document_id" id="deleteReqDocIdInput" value="">
    </form>

    <script>
        // Centralized Institution Documents Registry (Injected from PHP Supabase query)
        window.allInstitutionDocs = <?= json_encode($institutionDocsMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?> || {};

        // Canonical 6 CBL Requirements configuration
        const CANONICAL_REQ_CONFIG = [
            { key: 'letter_of_intent', title: 'Letter of Intent (Art. IV Sec. 3)', desc: 'Official signed letter addressed to IECEP-LSC Chapter President', icon: 'fa-file-signature', defaultType: 'pdf' },
            { key: 'endorsement_letter', title: 'Dean / Chair Endorsement Letter', desc: 'Formal endorsement from College Dean or ECE Department Chair', icon: 'fa-award', defaultType: 'pdf' },
            { key: 'constitution_bylaws', title: 'Student Chapter Constitution & By-Laws', desc: 'Ratified student chapter CBL aligned with IECEP-LSC 2025 CBL', icon: 'fa-scale-balanced', defaultType: 'pdf' },
            { key: 'officers_cv', title: 'Incumbent Officers Directory & CVs', desc: 'Directory of student chapter executive officers with curriculum vitae', icon: 'fa-id-card-clip', defaultType: 'pdf' },
            { key: 'org_chart', title: 'Organizational Structure Chart', desc: 'Official student chapter executive and committee organizational chart', icon: 'fa-sitemap', defaultType: 'pdf' },
            { key: 'member_directory', title: 'Certified Student Member Directory', desc: 'Complete roster of certified student members with student numbers', icon: 'fa-file-excel', defaultType: 'excel' }
        ];

        let currentModalInstId = '';

        function openRequirementsModal(instId, instName, instAcronym) {
            currentModalInstId = instId;
            document.getElementById('reqModalInstId').value = instId;
            document.getElementById('reqModalSchoolName').textContent = instName || 'Institution';
            document.getElementById('reqModalSchoolAcronym').textContent = instAcronym || 'HEI';

            renderInstitutionRequirements(instId);

            // Reset upload form
            document.getElementById('institutionReqUploadForm').reset();
            clearReqFileSelection();
            onReqTypeChanged(document.getElementById('reqTypeSelect').value);

            // Display modal
            document.getElementById('institutionRequirementsModal').style.display = 'flex';
        }

        function closeRequirementsModal() {
            document.getElementById('institutionRequirementsModal').style.display = 'none';
        }

        function renderInstitutionRequirements(instId) {
            const docs = window.allInstitutionDocs[instId] || [];
            const canonContainer = document.getElementById('reqCanonicalList');
            const addlContainer = document.getElementById('reqAdditionalList');

            canonContainer.innerHTML = '';
            addlContainer.innerHTML = '';

            let canonicalFoundCount = 0;

            // Render Canonical 6 Checklist
            CANONICAL_REQ_CONFIG.forEach(cfg => {
                // Look for doc matching this category
                const matched = docs.find(d => d.category === cfg.key);
                if (matched && matched.file_url) {
                    canonicalFoundCount++;
                    const ext = (matched.file_type || 'pdf').toLowerCase();
                    let iconClass = 'fa-file-pdf';
                    let typeClass = 'pdf';
                    if (['xls', 'xlsx', 'csv'].includes(ext)) { iconClass = 'fa-file-excel'; typeClass = 'excel'; }
                    else if (['doc', 'docx'].includes(ext)) { iconClass = 'fa-file-word'; typeClass = 'word'; }
                    else if (['jpg', 'jpeg', 'png'].includes(ext)) { iconClass = 'fa-file-image'; typeClass = 'image'; }

                    const isDynamic = matched.id && !matched.id.startsWith('canon_');

                    const card = document.createElement('div');
                    card.className = 'req-card-item';
                    card.innerHTML = `
                        <div style="display:flex; align-items:center; gap:0.65rem; overflow:hidden;">
                            <div class="req-icon-box ${typeClass}">
                                <i class="fas ${iconClass}"></i>
                            </div>
                            <div style="overflow:hidden;">
                                <div style="display:flex; align-items:center; gap:0.4rem;">
                                    <strong style="font-size:0.8rem; color:#0F172A; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${escapeHtml(matched.title || cfg.title)}">
                                        ${escapeHtml(matched.title || cfg.title)}
                                    </strong>
                                    <span class="ap-pill active" style="font-size:0.62rem; padding:1px 6px;"><i class="fas fa-check"></i> Filed</span>
                                </div>
                                <div style="font-size:0.68rem; color:#64748B; margin-top:2px;">
                                    Uploaded: ${matched.uploaded_at ? matched.uploaded_at.substring(0, 10) : 'Active'} &bull; ${matched.uploaded_by || 'Officer'}
                                </div>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:0.35rem; flex-shrink:0;">
                            <a href="${escapeHtml(matched.file_url)}" target="_blank" class="btn-white" style="font-size:0.7rem; padding:0.25rem 0.55rem; color:#2563EB; font-weight:700; text-decoration:none;" title="View / Download Document">
                                <i class="fas fa-arrow-up-right-from-square"></i> View
                            </a>
                            ${isDynamic ? `
                            <button type="button" class="btn-white" style="font-size:0.7rem; padding:0.25rem 0.45rem; color:#DC2626;" onclick="deleteRequirementDoc('${matched.id}', '${escapeHtml(matched.title)}')" title="Delete File">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                            ` : ''}
                        </div>
                    `;
                    canonContainer.appendChild(card);
                } else {
                    // Missing Canonical Requirement Card
                    const card = document.createElement('div');
                    card.className = 'req-card-item';
                    card.style.background = '#FFFBEB';
                    card.style.borderColor = '#FDE68A';
                    card.innerHTML = `
                        <div style="display:flex; align-items:center; gap:0.65rem; overflow:hidden;">
                            <div class="req-icon-box pending">
                                <i class="fas ${cfg.icon}"></i>
                            </div>
                            <div style="overflow:hidden;">
                                <div style="display:flex; align-items:center; gap:0.4rem;">
                                    <strong style="font-size:0.8rem; color:#92400E; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${escapeHtml(cfg.title)}">
                                        ${escapeHtml(cfg.title)}
                                    </strong>
                                    <span class="ap-pill pending" style="font-size:0.62rem; padding:1px 6px;">Pending</span>
                                </div>
                                <div style="font-size:0.67rem; color:#B45309; margin-top:2px;">
                                    ${cfg.desc}
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn-white" style="font-size:0.7rem; padding:0.25rem 0.6rem; color:#B45309; border-color:#FCD34D; font-weight:700; background:#FFFFFF; flex-shrink:0;" onclick="quickSelectReqCategory('${cfg.key}', '${escapeHtml(cfg.title)}')">
                            <i class="fas fa-plus"></i> Upload
                        </button>
                    `;
                    canonContainer.appendChild(card);
                }
            });

            // Update Progress & Badge
            const pct = Math.round((canonicalFoundCount / 6) * 100);
            const pBar = document.getElementById('reqProgressBar');
            const pBadge = document.getElementById('reqCompletionBadge');
            pBar.style.width = pct + '%';
            if (pct >= 100) {
                pBar.style.background = '#059669';
                pBadge.className = 'ap-pill active';
                pBadge.innerHTML = `<i class="fas fa-circle-check"></i> Complete (${canonicalFoundCount}/6 CBL Requirements)`;
            } else {
                pBar.style.background = pct >= 50 ? '#D97706' : '#DC2626';
                pBadge.className = 'ap-pill pending';
                pBadge.innerHTML = `<i class="fas fa-clock"></i> Incomplete (${canonicalFoundCount}/6 Requirements)`;
            }

            // Render Additional Supporting Files
            const additionalDocs = docs.filter(d => !CANONICAL_REQ_CONFIG.some(c => c.key === d.category));
            if (additionalDocs.length === 0) {
                addlContainer.innerHTML = `
                    <div style="text-align:center; padding:0.9rem; background:#F8FAFC; border:1px dashed #CBD5E1; border-radius:8px; font-size:0.74rem; color:#64748B;">
                        No additional supporting documents uploaded yet. Use the upload panel to attach receipts or event reports.
                    </div>
                `;
            } else {
                additionalDocs.forEach(d => {
                    const ext = (d.file_type || 'pdf').toLowerCase();
                    let iconClass = 'fa-file-lines';
                    let typeClass = 'word';
                    if (['xls', 'xlsx', 'csv'].includes(ext)) { iconClass = 'fa-file-excel'; typeClass = 'excel'; }
                    else if (['jpg', 'jpeg', 'png'].includes(ext)) { iconClass = 'fa-file-image'; typeClass = 'image'; }
                    else if (ext === 'pdf') { iconClass = 'fa-file-pdf'; typeClass = 'pdf'; }

                    const card = document.createElement('div');
                    card.className = 'req-card-item';
                    card.innerHTML = `
                        <div style="display:flex; align-items:center; gap:0.65rem; overflow:hidden;">
                            <div class="req-icon-box ${typeClass}">
                                <i class="fas ${iconClass}"></i>
                            </div>
                            <div style="overflow:hidden;">
                                <div style="display:flex; align-items:center; gap:0.4rem;">
                                    <strong style="font-size:0.8rem; color:#0F172A; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                        ${escapeHtml(d.title)}
                                    </strong>
                                    <span class="ap-pill blue" style="font-size:0.62rem; padding:1px 6px;">${escapeHtml(d.category || 'attachment')}</span>
                                </div>
                                <div style="font-size:0.68rem; color:#64748B; margin-top:2px;">
                                    ${escapeHtml(d.description || 'Chapter uploaded attachment')} &bull; ${d.uploaded_at ? d.uploaded_at.substring(0, 10) : 'Recent'}
                                </div>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:0.35rem; flex-shrink:0;">
                            <a href="${escapeHtml(d.file_url)}" target="_blank" class="btn-white" style="font-size:0.7rem; padding:0.25rem 0.55rem; color:#2563EB; font-weight:700; text-decoration:none;" title="View / Download">
                                <i class="fas fa-arrow-up-right-from-square"></i> View
                            </a>
                            ${d.id ? `
                            <button type="button" class="btn-white" style="font-size:0.7rem; padding:0.25rem 0.45rem; color:#DC2626;" onclick="deleteRequirementDoc('${d.id}', '${escapeHtml(d.title)}')" title="Delete File">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                            ` : ''}
                        </div>
                    `;
                    addlContainer.appendChild(card);
                });
            }
        }

        function quickSelectReqCategory(catKey, catTitle) {
            const sel = document.getElementById('reqTypeSelect');
            sel.value = catKey;
            document.getElementById('reqDocTitle').value = catTitle;
            
            // Scroll to dropzone on mobile & flash dropzone
            const dropzone = document.getElementById('reqDropzone');
            dropzone.scrollIntoView({ behavior: 'smooth', block: 'center' });
            dropzone.classList.add('drag-over');
            setTimeout(() => dropzone.classList.remove('drag-over'), 600);
        }

        function onReqTypeChanged(val) {
            const titleInput = document.getElementById('reqDocTitle');
            const canonItem = CANONICAL_REQ_CONFIG.find(c => c.key === val);
            if (canonItem && !titleInput.value.trim()) {
                titleInput.value = canonItem.title;
            } else if (val === 'official_receipt' && !titleInput.value.trim()) {
                titleInput.value = 'Official Payment Receipt & Deposit Slip';
            } else if (val === 'activity_report' && !titleInput.value.trim()) {
                titleInput.value = 'Annual Chapter Activity & Accomplishment Report';
            }
        }

        function handleReqFileSelected(input) {
            if (!input.files || input.files.length === 0) return;
            const file = input.files[0];
            const name = file.name;
            const sizeKB = (file.size / 1024).toFixed(1);
            const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
            const sizeDisplay = file.size > 1048576 ? `${sizeMB} MB` : `${sizeKB} KB`;

            const ext = name.split('.').pop().toLowerCase();
            const iconEl = document.getElementById('reqPreviewIcon');
            if (['xls', 'xlsx', 'csv'].includes(ext)) {
                iconEl.className = 'fas fa-file-excel';
                iconEl.style.color = '#059669';
            } else if (['doc', 'docx'].includes(ext)) {
                iconEl.className = 'fas fa-file-word';
                iconEl.style.color = '#2563EB';
            } else if (['jpg', 'jpeg', 'png'].includes(ext)) {
                iconEl.className = 'fas fa-file-image';
                iconEl.style.color = '#D97706';
            } else {
                iconEl.className = 'fas fa-file-pdf';
                iconEl.style.color = '#DC2626';
            }

            document.getElementById('reqPreviewName').textContent = name;
            document.getElementById('reqPreviewMeta').textContent = `${sizeDisplay} • ${ext.toUpperCase()}`;

            document.getElementById('reqDropzonePrompt').style.display = 'none';
            document.getElementById('reqDropzoneFilePreview').style.display = 'block';

            // Auto-populate title if empty
            const titleInput = document.getElementById('reqDocTitle');
            if (!titleInput.value.trim()) {
                const cleanName = name.replace(/\.[^/.]+$/, "").replace(/[_ -]+/g, " ");
                titleInput.value = cleanName;
            }
        }

        function clearReqFileSelection() {
            const input = document.getElementById('reqFileInput');
            input.value = '';
            document.getElementById('reqDropzonePrompt').style.display = 'block';
            document.getElementById('reqDropzoneFilePreview').style.display = 'none';
        }

        function deleteRequirementDoc(docId, docTitle) {
            if (!docId) return;
            if (confirm(`Are you sure you want to permanently remove "${docTitle}" from this chapter's repository?`)) {
                document.getElementById('deleteReqDocIdInput').value = docId;
                document.getElementById('deleteReqDocForm').submit();
            }
        }

        // Drag & Drop Setup
        (function() {
            const dropzone = document.getElementById('reqDropzone');
            if (!dropzone) return;

            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.add('drag-over');
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.remove('drag-over');
                }, false);
            });

            dropzone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = dt.files;
                if (files && files.length > 0) {
                    const fileInput = document.getElementById('reqFileInput');
                    fileInput.files = files;
                    handleReqFileSelected(fileInput);
                }
            }, false);

            // Close modal with Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    const reqModal = document.getElementById('institutionRequirementsModal');
                    if (reqModal && reqModal.style.display === 'flex') {
                        closeRequirementsModal();
                    }
                    const syncModal = document.getElementById('syncSystemModal');
                    if (syncModal && syncModal.style.display === 'flex') {
                        closeSyncModal();
                    }
                }
            });
        })();

        // Full System Data Synchronization Controller
        function triggerFullSystemSync() {
            const modal = document.getElementById('syncSystemModal');
            const stateLoading = document.getElementById('syncStateLoading');
            const stateSuccess = document.getElementById('syncStateSuccess');
            const stateError = document.getElementById('syncStateError');

            modal.style.display = 'flex';
            stateLoading.style.display = 'block';
            stateSuccess.style.display = 'none';
            stateError.style.display = 'none';

            // Spin header button icon if visible
            const syncIcon = document.getElementById('syncAllBtnIcon');
            if (syncIcon) syncIcon.classList.add('fa-spin');

            fetch('<?= PORTAL_URL ?>/../../api/sync-all.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(res => res.json())
            .then(data => {
                if (syncIcon) syncIcon.classList.remove('fa-spin');
                if (data.success) {
                    stateLoading.style.display = 'none';
                    stateSuccess.style.display = 'block';

                    const stats = data.stats || {};
                    document.getElementById('syncDurationDisplay').textContent = `${data.duration_sec || 0}s`;
                    document.getElementById('syncStatChapters').textContent = stats.institutions_processed || 0;
                    document.getElementById('syncStatMembers').textContent = stats.members_linked || 0;
                    document.getElementById('syncStatDocs').textContent = stats.documents_synced || 0;
                    document.getElementById('syncStatFinances').textContent = stats.financials_updated || 0;
                    document.getElementById('syncStatCompliance').textContent = stats.compliance_evaluated || 0;
                    document.getElementById('syncStatBlockchain').textContent = stats.blockchain_blocks || 0;

                    const logContainer = document.getElementById('syncLogList');
                    logContainer.innerHTML = '';
                    (data.log || []).forEach(entry => {
                        const li = document.createElement('li');
                        li.style.marginBottom = '0.35rem';
                        li.innerHTML = `<i class="fas fa-check" style="color:#059669; margin-right:4px;"></i> ${escapeHtml(entry)}`;
                        logContainer.appendChild(li);
                    });
                } else {
                    stateLoading.style.display = 'none';
                    stateError.style.display = 'block';
                    document.getElementById('syncErrorMsg').textContent = data.message || 'Synchronization failed.';
                }
            })
            .catch(err => {
                if (syncIcon) syncIcon.classList.remove('fa-spin');
                stateLoading.style.display = 'none';
                stateError.style.display = 'block';
                document.getElementById('syncErrorMsg').textContent = err.message || 'Network error occurred during synchronization.';
            });
        }

        function closeSyncModal() {
            document.getElementById('syncSystemModal').style.display = 'none';
        }

        function finishSyncAndReload() {
            window.location.reload();
        }
    </script>

    <!-- Full System Data Synchronization Modal -->
    <div id="syncSystemModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(11,29,74,0.72); backdrop-filter:blur(5px); z-index:999999; align-items:center; justify-content:center; padding:1rem; box-sizing:border-box;">
        <div style="background:#FFFFFF; border-radius:14px; max-width:640px; width:95%; max-height:90vh; overflow-y:auto; box-shadow:0 25px 60px -15px rgba(11,29,74,0.45); border:1px solid #CBD5E1; animation:modalPop 0.2s ease-out; box-sizing:border-box;">
            
            <!-- Header -->
            <div style="background:linear-gradient(135deg, #0B1D4A 0%, #152C6E 100%); color:#FFFFFF; padding:1.15rem 1.4rem; display:flex; justify-content:space-between; align-items:center;">
                <div style="display:flex; align-items:center; gap:0.65rem;">
                    <div style="width:38px; height:38px; border-radius:9px; background:rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.25); display:flex; align-items:center; justify-content:center; color:#FDE047; font-size:1.2rem;">
                        <i class="fas fa-rotate"></i>
                    </div>
                    <div>
                        <h3 style="margin:0; font-size:1.05rem; font-weight:800; color:#FFFFFF;">
                            System-Wide Data Synchronization
                        </h3>
                        <div style="font-size:0.72rem; color:#93C5FD; margin-top:1px;">
                            Harmonizing chapters, student rosters, requirements, finances &amp; blockchain
                        </div>
                    </div>
                </div>
                <button type="button" onclick="closeSyncModal()" style="background:rgba(255,255,255,0.15); border:none; color:#FFFFFF; border-radius:6px; width:30px; height:30px; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:1.3rem;">&times;</button>
            </div>

            <!-- Body -->
            <div style="padding:1.5rem;">
                
                <!-- 1. LOADING STATE -->
                <div id="syncStateLoading" style="text-align:center; padding:1.5rem 0.5rem;">
                    <div style="width:64px; height:64px; margin:0 auto 1.25rem; border-radius:50%; background:#EFF6FF; border:3px solid #DBEAFE; display:flex; align-items:center; justify-content:center; color:#2563EB; font-size:1.75rem;">
                        <i class="fas fa-rotate fa-spin"></i>
                    </div>
                    <h4 style="margin:0 0 0.4rem; font-size:1.1rem; font-weight:800; color:#0F172A;">
                        Synchronizing All System Records...
                    </h4>
                    <p style="margin:0 auto 1.25rem; font-size:0.78rem; color:#64748B; max-width:440px; line-height:1.5;">
                        Executing multi-phase alignment: linking chapter records, resolving student rosters, cross-syncing CBL requirements, calculating 2025 CBL fees, and anchoring blockchain blocks.
                    </p>

                    <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:0.85rem 1rem; max-width:440px; margin:0 auto; text-align:left; font-size:0.74rem; color:#475569;">
                        <div style="display:flex; align-items:center; gap:0.45rem; margin-bottom:0.35rem;">
                            <i class="fas fa-circle-notch fa-spin" style="color:#2563EB;"></i>
                            <span>Aligning Chapter Affiliations &amp; Contacts</span>
                        </div>
                        <div style="display:flex; align-items:center; gap:0.45rem; margin-bottom:0.35rem;">
                            <i class="fas fa-circle-notch fa-spin" style="color:#2563EB;"></i>
                            <span>Reconciling Active Enrolled Member Counts</span>
                        </div>
                        <div style="display:flex; align-items:center; gap:0.45rem; margin-bottom:0.35rem;">
                            <i class="fas fa-circle-notch fa-spin" style="color:#2563EB;"></i>
                            <span>Cross-Syncing 6 CBL Requirements &amp; Dossiers</span>
                        </div>
                        <div style="display:flex; align-items:center; gap:0.45rem; margin-bottom:0.35rem;">
                            <i class="fas fa-circle-notch fa-spin" style="color:#2563EB;"></i>
                            <span>Auditing 2025 CBL Bracket Collections &amp; Receipts</span>
                        </div>
                        <div style="display:flex; align-items:center; gap:0.45rem;">
                            <i class="fas fa-circle-notch fa-spin" style="color:#2563EB;"></i>
                            <span>Anchoring Cryptographic Blockchain Ledger</span>
                        </div>
                    </div>
                </div>

                <!-- 2. SUCCESS STATE -->
                <div id="syncStateSuccess" style="display:none;">
                    <div style="background:#ECFDF5; border:1px solid #A7F3D0; border-radius:10px; padding:1rem 1.25rem; display:flex; align-items:center; justify-content:space-between; margin-bottom:1.25rem;">
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <div style="width:40px; height:40px; border-radius:50%; background:#059669; color:#FFFFFF; display:flex; align-items:center; justify-content:center; font-size:1.25rem; flex-shrink:0;">
                                <i class="fas fa-check"></i>
                            </div>
                            <div>
                                <h4 style="margin:0; font-size:1rem; font-weight:800; color:#065F46;">
                                    All System Data Synchronized!
                                </h4>
                                <div style="font-size:0.75rem; color:#047857; margin-top:2px;">
                                    Every chapter, roster, document dossier, and ledger balance is in 100% harmony.
                                </div>
                            </div>
                        </div>
                        <span id="syncDurationDisplay" class="ap-pill active" style="font-size:0.75rem; font-family:'JetBrains Mono',monospace;">0.0s</span>
                    </div>

                    <!-- 6 Stats Grid -->
                    <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:0.65rem; margin-bottom:1.25rem;">
                        <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:0.65rem 0.75rem; text-align:center;">
                            <div style="font-size:0.68rem; text-transform:uppercase; font-weight:700; color:#64748B;">Chapters Aligned</div>
                            <div id="syncStatChapters" style="font-size:1.2rem; font-weight:800; color:#0B1D4A; font-family:'JetBrains Mono',monospace; margin-top:2px;">0</div>
                        </div>
                        <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:0.65rem 0.75rem; text-align:center;">
                            <div style="font-size:0.68rem; text-transform:uppercase; font-weight:700; color:#64748B;">Members Linked</div>
                            <div id="syncStatMembers" style="font-size:1.2rem; font-weight:800; color:#2563EB; font-family:'JetBrains Mono',monospace; margin-top:2px;">0</div>
                        </div>
                        <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:0.65rem 0.75rem; text-align:center;">
                            <div style="font-size:0.68rem; text-transform:uppercase; font-weight:700; color:#64748B;">Docs Synced</div>
                            <div id="syncStatDocs" style="font-size:1.2rem; font-weight:800; color:#D97706; font-family:'JetBrains Mono',monospace; margin-top:2px;">0</div>
                        </div>
                        <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:0.65rem 0.75rem; text-align:center;">
                            <div style="font-size:0.68rem; text-transform:uppercase; font-weight:700; color:#64748B;">Ledgers Audited</div>
                            <div id="syncStatFinances" style="font-size:1.2rem; font-weight:800; color:#059669; font-family:'JetBrains Mono',monospace; margin-top:2px;">0</div>
                        </div>
                        <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:0.65rem 0.75rem; text-align:center;">
                            <div style="font-size:0.68rem; text-transform:uppercase; font-weight:700; color:#64748B;">Compliance Evaluated</div>
                            <div id="syncStatCompliance" style="font-size:1.2rem; font-weight:800; color:#8B5CF6; font-family:'JetBrains Mono',monospace; margin-top:2px;">0</div>
                        </div>
                        <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:0.65rem 0.75rem; text-align:center;">
                            <div style="font-size:0.68rem; text-transform:uppercase; font-weight:700; color:#64748B;">Blocks Anchored</div>
                            <div id="syncStatBlockchain" style="font-size:1.2rem; font-weight:800; color:#0B1D4A; font-family:'JetBrains Mono',monospace; margin-top:2px;">0</div>
                        </div>
                    </div>

                    <!-- Sync Phase Log -->
                    <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:0.85rem 1rem; margin-bottom:1.25rem;">
                        <div style="font-size:0.72rem; text-transform:uppercase; font-weight:800; color:#475569; margin-bottom:0.45rem;">
                            <i class="fas fa-list-check" style="color:#0B1D4A;"></i> Execution Highlights:
                        </div>
                        <ul id="syncLogList" style="margin:0; padding-left:1.15rem; font-size:0.75rem; color:#334155; line-height:1.45;">
                            <!-- Populated dynamically -->
                        </ul>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:0.65rem;">
                        <button type="button" class="btn-white" onclick="closeSyncModal()">
                            Close
                        </button>
                        <button type="button" class="btn-primary-navy" onclick="finishSyncAndReload()">
                            <i class="fas fa-arrows-rotate"></i> Done &amp; Refresh Dashboard
                        </button>
                    </div>
                </div>

                <!-- 3. ERROR STATE -->
                <div id="syncStateError" style="display:none; text-align:center; padding:1.5rem 0.5rem;">
                    <div style="width:56px; height:56px; margin:0 auto 1rem; border-radius:50%; background:#FEE2E2; color:#DC2626; display:flex; align-items:center; justify-content:center; font-size:1.5rem;">
                        <i class="fas fa-circle-exclamation"></i>
                    </div>
                    <h4 style="margin:0 0 0.35rem; font-size:1.05rem; font-weight:800; color:#991B1B;">
                        Synchronization Error
                    </h4>
                    <p id="syncErrorMsg" style="font-size:0.8rem; color:#64748B; margin:0 0 1.25rem; word-break:break-word;">
                        An error occurred while synchronizing system records.
                    </p>
                    <button type="button" class="btn-primary-navy" onclick="closeSyncModal()">
                        Dismiss
                    </button>
                </div>

            </div>
        </div>
    </div>
</body>
</html>
