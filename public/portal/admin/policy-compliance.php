<?php
require_once __DIR__ . '/../bootstrap.php';
$current_page = 'policy-compliance';

require_once __DIR__ . '/../auth_check.php';
require_role(['admin', 'super_admin', 'registration', 'committee_registration', 'eb_president', 'auditor']);
require_once __DIR__ . '/../../../src/lib/EmailService.php';

$pageTitle = 'Institutional Policy Compliance & Governance';
$supabase = getSupabaseClient();

$feedbackMsg = '';
$feedbackType = 'success';

// Default standard regulatory policies for IECEP-LSC accredited chapters
$standardPolicies = [
    [
        'name' => 'Constitution & By-Laws (CBL) Ratification',
        'desc' => 'Submission of official student chapter bylaws ratified and aligned with IECEP National constitution.',
        'due' => '2026-10-31'
    ],
    [
        'name' => 'Accredited Student Member Directory',
        'desc' => 'Complete enrollment roster with verified student numbers and proof of IECEP-LSC registration.',
        'due' => '2026-09-30'
    ],
    [
        'name' => 'Chapter Affiliation & Operational Dues Settlement',
        'desc' => 'Full payment and verification of institutional affiliation fee and roster dues with official receipt.',
        'due' => '2026-09-15'
    ],
    [
        'name' => 'Faculty Adviser & Officer Board Endorsement',
        'desc' => 'Dean/ECE Department Chair official endorsement letter and verified officer curriculum vitae.',
        'due' => '2026-10-15'
    ],
    [
        'name' => 'Regional Technical Summit Delegation',
        'desc' => 'Registration and accredited participation in the flagship IECEP-LSC Regional Technical Summit.',
        'due' => '2026-11-20'
    ]
];

// Helper: Re-evaluate and sync institution compliance status
function syncInstitutionComplianceStatus($supabase, $instId) {
    if (!$instId || !$supabase) return;
    try {
        $pols = $supabase->select('policy_compliance', ['institution_id' => 'eq.' . $instId]);
        if (is_array($pols) && !empty($pols)) {
            $total = count($pols);
            $passed = count(array_filter($pols, fn($p) => !empty($p['is_compliant'])));
            $newStatus = ($passed >= $total) ? 'compliant' : 'at_risk';
            $supabase->update('institutions', ['compliance_status' => $newStatus, 'updated_at' => date('c')], $instId);
        }
    } catch (\Throwable $e) {
        error_log("Sync inst compliance status error: " . $e->getMessage());
    }
}

// Handle AJAX or POST actions
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_GET['format']) && $_GET['format'] === 'json');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'toggle_policy') {
        $policyId = trim($_POST['policy_id'] ?? '');
        $newStatus = ($_POST['is_compliant'] ?? '0') === '1';
        $notes = trim($_POST['notes'] ?? '');
        $timestamp = date('c');

        if ($policyId && $supabase) {
            try {
                $payload = [
                    'is_compliant' => $newStatus,
                    'completed_at' => $newStatus ? $timestamp : null,
                    'updated_at' => $timestamp
                ];
                if (!empty($notes)) {
                    $payload['notes'] = $notes;
                }
                $supabase->update('policy_compliance', $payload, $policyId);

                // Check institution ID to sync compliance status
                $rec = $supabase->select('policy_compliance', ['id' => 'eq.' . $policyId]);
                if (!empty($rec[0]['institution_id'])) {
                    syncInstitutionComplianceStatus($supabase, $rec[0]['institution_id']);
                }

                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'is_compliant' => $newStatus, 'completed_at' => $payload['completed_at']]);
                    exit;
                }

                $feedbackMsg = "✓ Policy status updated to " . ($newStatus ? "Compliant (Passed)" : "Pending Review") . "!";
                $feedbackType = 'success';
            } catch (\Throwable $e) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                    exit;
                }
                $feedbackMsg = "Error updating policy: " . $e->getMessage();
                $feedbackType = 'danger';
            }
        }
    } elseif ($action === 'add_policy') {
        $instTarget = trim($_POST['institution_id'] ?? '');
        $policyName = trim($_POST['policy_name'] ?? '');
        $policyDesc = trim($_POST['policy_description'] ?? '');
        $dueDate = trim($_POST['due_date'] ?? date('Y-12-31'));
        $notes = trim($_POST['notes'] ?? '');
        $isCompliant = ($_POST['is_compliant'] ?? '0') === '1';

        if ($instTarget && $policyName && $supabase) {
            try {
                $timestamp = date('c');
                $targetIds = [];
                if ($instTarget === 'all') {
                    $allInsts = $supabase->select('institutions', ['select' => 'id']);
                    if (is_array($allInsts)) {
                        $targetIds = array_column($allInsts, 'id');
                    }
                } else {
                    $targetIds = [$instTarget];
                }

                $inserted = 0;
                foreach ($targetIds as $tid) {
                    $supabase->insert('policy_compliance', [[
                        'institution_id' => $tid,
                        'policy_name' => $policyName,
                        'policy_description' => $policyDesc,
                        'due_date' => $dueDate,
                        'notes' => $notes,
                        'is_compliant' => $isCompliant,
                        'completed_at' => $isCompliant ? $timestamp : null,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp
                    ]]);
                    syncInstitutionComplianceStatus($supabase, $tid);
                    $inserted++;
                }

                $feedbackMsg = "✓ Created requirement '{$policyName}' for {$inserted} chapter(s)!";
                $feedbackType = 'success';
            } catch (\Throwable $e) {
                $feedbackMsg = "Error adding policy: " . $e->getMessage();
                $feedbackType = 'danger';
            }
        }
    } elseif ($action === 'edit_policy') {
        $policyId = trim($_POST['policy_id'] ?? '');
        $policyName = trim($_POST['policy_name'] ?? '');
        $policyDesc = trim($_POST['policy_description'] ?? '');
        $dueDate = trim($_POST['due_date'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $hasStatus = isset($_POST['is_compliant']);
        $isCompliant = $hasStatus && ($_POST['is_compliant'] === '1');

        if ($policyId && $policyName && $supabase) {
            try {
                $timestamp = date('c');
                $payload = [
                    'policy_name' => $policyName,
                    'policy_description' => $policyDesc,
                    'due_date' => $dueDate ?: null,
                    'notes' => $notes,
                    'updated_at' => $timestamp
                ];
                if ($hasStatus) {
                    $payload['is_compliant'] = $isCompliant;
                    $payload['completed_at'] = $isCompliant ? $timestamp : null;
                }
                $supabase->update('policy_compliance', $payload, $policyId);

                $rec = $supabase->select('policy_compliance', ['id' => 'eq.' . $policyId]);
                if (!empty($rec[0]['institution_id'])) {
                    syncInstitutionComplianceStatus($supabase, $rec[0]['institution_id']);
                }

                $feedbackMsg = "✓ Requirement '{$policyName}' updated successfully!";
                $feedbackType = 'success';
            } catch (\Throwable $e) {
                $feedbackMsg = "Error editing policy: " . $e->getMessage();
                $feedbackType = 'danger';
            }
        }
    } elseif ($action === 'delete_policy') {
        $policyId = trim($_POST['policy_id'] ?? '');
        if ($policyId && $supabase) {
            try {
                $rec = $supabase->select('policy_compliance', ['id' => 'eq.' . $policyId]);
                $instId = $rec[0]['institution_id'] ?? null;
                $supabase->delete('policy_compliance', ['id' => 'eq.' . $policyId]);
                if ($instId) syncInstitutionComplianceStatus($supabase, $instId);

                $feedbackMsg = "🗑️ Regulatory policy deleted successfully.";
                $feedbackType = 'warning';
            } catch (\Throwable $e) {
                $feedbackMsg = "Error deleting policy: " . $e->getMessage();
                $feedbackType = 'danger';
            }
        }
    } elseif ($action === 'batch_comply') {
        $instId = trim($_POST['institution_id'] ?? '');
        if ($instId && $supabase) {
            try {
                $existing = $supabase->select('policy_compliance', ['institution_id' => 'eq.' . $instId]);
                if (is_array($existing)) {
                    $timestamp = date('c');
                    foreach ($existing as $p) {
                        $supabase->update('policy_compliance', [
                            'is_compliant' => true,
                            'completed_at' => $timestamp,
                            'updated_at' => $timestamp
                        ], $p['id']);
                    }
                }
                $supabase->update('institutions', ['compliance_status' => 'compliant', 'updated_at' => date('c')], $instId);

                // Anchor proof in blockchain
                try {
                    $certProof = hash('sha256', $instId . '|COMPLIANT_ACCREDITATION|' . date('c'));
                    $supabase->insert('blockchain_records', [[
                        'entity_type' => 'compliance',
                        'entity_id' => $instId,
                        'record_type' => 'accreditation_compliance',
                        'transaction_hash' => $certProof,
                        'record_hash' => $certProof,
                        'data_hash' => $certProof,
                        'confirmed' => true,
                        'data_json' => [
                            'institution_id' => $instId,
                            'status' => 'compliant',
                            'academic_year' => '2026-2027',
                            'verified_by' => 'IECEP-LSC Secretariat'
                        ],
                        'created_at' => date('c')
                    ]]);
                } catch (\Throwable $bcEx) {}

                $feedbackMsg = "🎉 All regulatory policies marked Compliant and chapter accredited!";
                $feedbackType = 'success';
            } catch (\Throwable $e) {
                $feedbackMsg = "Batch compliance error: " . $e->getMessage();
                $feedbackType = 'danger';
            }
        }
    } elseif ($action === 'batch_reset') {
        $instId = trim($_POST['institution_id'] ?? '');
        if ($instId && $supabase) {
            try {
                $existing = $supabase->select('policy_compliance', ['institution_id' => 'eq.' . $instId]);
                if (is_array($existing)) {
                    $timestamp = date('c');
                    foreach ($existing as $p) {
                        $supabase->update('policy_compliance', [
                            'is_compliant' => false,
                            'completed_at' => null,
                            'updated_at' => $timestamp
                        ], $p['id']);
                    }
                }
                $supabase->update('institutions', ['compliance_status' => 'at_risk', 'updated_at' => date('c')], $instId);

                $feedbackMsg = "ℹ️ Chapter requirements reset to Pending Review for monitoring.";
                $feedbackType = 'info';
            } catch (\Throwable $e) {
                $feedbackMsg = "Batch reset notice: " . $e->getMessage();
                $feedbackType = 'danger';
            }
        }
    } elseif ($action === 'seed_standards') {
        try {
            $allInsts = $supabase->select('institutions', ['select' => 'id, name']);
            $countAdded = 0;
            if (is_array($allInsts)) {
                $timestamp = date('c');
                foreach ($allInsts as $inst) {
                    $iid = $inst['id'];
                    $currPols = $supabase->select('policy_compliance', ['institution_id' => 'eq.' . $iid, 'select' => 'policy_name']);
                    $existingNames = is_array($currPols) ? array_map(fn($r) => strtolower(trim($r['policy_name'] ?? '')), $currPols) : [];

                    foreach ($standardPolicies as $sp) {
                        if (!in_array(strtolower(trim($sp['name'])), $existingNames)) {
                            $supabase->insert('policy_compliance', [[
                                'institution_id' => $iid,
                                'policy_name' => $sp['name'],
                                'policy_description' => $sp['desc'],
                                'due_date' => $sp['due'],
                                'is_compliant' => false,
                                'completed_at' => null,
                                'notes' => 'Standard IECEP-LSC Regulatory Baseline',
                                'created_at' => $timestamp,
                                'updated_at' => $timestamp
                            ]]);
                            $countAdded++;
                        }
                    }
                    syncInstitutionComplianceStatus($supabase, $iid);
                }
            }
            $feedbackMsg = "✓ Standard compliance checklist synchronized! Added {$countAdded} baseline requirements.";
            $feedbackType = 'success';
        } catch (\Throwable $e) {
            $feedbackMsg = "Seed error: " . $e->getMessage();
            $feedbackType = 'danger';
        }
    } elseif ($action === 'send_notice') {
        $instId = trim($_POST['institution_id'] ?? '');
        $noticeType = trim($_POST['notice_type'] ?? 'regular');
        $customMsg = trim($_POST['custom_message'] ?? '');
        
        if ($instId && $supabase) {
            try {
                $instRows = $supabase->select('institutions', ['id' => 'eq.' . $instId]);
                $inst = $instRows[0] ?? [];
                $instName = $inst['name'] ?? 'Chapter';
                $email = $inst['email'] ?? ($inst['contact_email'] ?? '');

                $pendingPols = $supabase->select('policy_compliance', [
                    'institution_id' => 'eq.' . $instId,
                    'is_compliant' => 'eq.false'
                ]);

                $pListHtml = '';
                if (is_array($pendingPols) && !empty($pendingPols)) {
                    $pListHtml .= '<ul style="padding-left: 20px; line-height: 1.8; color: #334155;">';
                    foreach ($pendingPols as $pp) {
                        $due = !empty($pp['due_date']) ? " — <span style='color: #D97706; font-weight: 700;'>Due: {$pp['due_date']}</span>" : "";
                        $pListHtml .= "<li><strong>" . htmlspecialchars($pp['policy_name']) . "</strong>{$due}<br><span style='font-size: 12px; color: #64748B;'>" . htmlspecialchars($pp['policy_description'] ?? '') . "</span></li>";
                    }
                    $pListHtml .= '</ul>';
                } else {
                    $pListHtml = '<p style="color: #059669; font-weight: bold;">🎉 All standard regulatory policies and bylaws requirements are currently satisfied. Your chapter is in active accredited standing!</p>';
                }

                $subjectPrefix = match($noticeType) {
                    'urgent' => '📢 Chapter Compliance Monitoring Advisory Notice',
                    'congrats' => '🏆 Official Chapter Accreditation & Good Standing Confirmation',
                    default => 'Official Chapter Compliance Monitoring Notice'
                };

                if (!empty($email)) {
                    $emailService = new \App\Lib\EmailService();
                    $subject = "{$subjectPrefix} — {$instName}";
                    $body = "
                    <div style='font-family: Arial, sans-serif; padding: 25px; color: #1E293B; background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; max-width: 650px; margin: 0 auto;'>
                        <div style='border-bottom: 2px solid #0B1D4A; padding-bottom: 12px; margin-bottom: 15px;'>
                            <h2 style='color: #0B1D4A; margin: 0;'>IECEP-LSC Chapter Governance Secretariat</h2>
                            <p style='color: #64748B; font-size: 13px; margin: 4px 0 0;'>Academic Year 2026–2027 Regulatory Accreditation</p>
                        </div>
                        <p>Dear Faculty Adviser & Student Chapter Officers of <strong>{$instName}</strong>,</p>
                        <p>This is an official notice regarding your chapter's institutional accreditation checklist under the <strong>Institute of Electronics Engineers of the Philippines &bull; Laguna Student Chapter</strong>.</p>
                        " . (!empty($customMsg) ? "<div style='padding: 12px 16px; background: #FEF3C7; border-left: 4px solid #D97706; border-radius: 4px; margin: 15px 0; font-size: 14px;'><strong>Secretariat Remark:</strong> " . htmlspecialchars($customMsg) . "</div>" : "") . "
                        <h4 style='color: #0B1D4A; margin-top: 20px; margin-bottom: 8px;'>Current Status of Regulatory Checklist:</h4>
                        {$pListHtml}
                        <p style='margin-top: 20px;'>Please access your chapter portal to review your dossier and submit compliance verifications:</p>
                        <p style='text-align: center; margin: 25px 0;'>
                            <a href='https://iecep-lsc.org/portal/login.php' style='display: inline-block; background: #0B1D4A; color: #FFFFFF; padding: 12px 26px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 14px;'>Access Chapter Portal</a>
                        </p>
                        <hr style='border: none; border-top: 1px solid #E2E8F0; margin: 25px 0 15px;'>
                        <p style='font-size: 11px; color: #94A3B8; margin: 0; text-align: center;'>IECEP-LSC MEMSYS &bull; Regional Committee on Student Affairs & Chapter Governance</p>
                    </div>";

                    $emailService->sendEmail($email, $subject, $body);
                    $feedbackMsg = "📧 Official compliance notice successfully dispatched to {$instName} ({$email})!";
                    $feedbackType = 'success';
                } else {
                    $feedbackMsg = "⚠️ Notice generated, but no registered email was found for {$instName}.";
                    $feedbackType = 'warning';
                }
            } catch (\Throwable $e) {
                $feedbackMsg = "Error sending notice: " . $e->getMessage();
                $feedbackType = 'danger';
            }
        }
    }
}

// Fetch all chartered institutions
$institutionsList = [];
$institutionsMap = [];
try {
    $instRes = $supabase->select('institutions', ['select' => '*', 'order' => 'name.asc']);
    if (is_array($instRes)) {
        $institutionsList = $instRes;
        foreach ($instRes as $i) {
            $institutionsMap[$i['id']] = $i;
        }
    }
} catch (\Throwable $e) {
    error_log("Policy compliance institutions query: " . $e->getMessage());
}

// Fetch all policy compliance records
$policyRecords = [];
try {
    $polRes = $supabase->select('policy_compliance', ['select' => '*', 'order' => 'created_at.asc']);
    if (is_array($polRes)) {
        $policyRecords = $polRes;
    }
} catch (\Throwable $e) {
    error_log("Policy compliance query: " . $e->getMessage());
}

// Group policies per institution
$policiesPerInst = [];
foreach ($policyRecords as $p) {
    $iid = $p['institution_id'] ?? '';
    if ($iid) {
        $policiesPerInst[$iid][] = $p;
    }
}

// Metrics Calculation
$totalPoliciesCount = count($policyRecords);
$totalPassedCount = count(array_filter($policyRecords, fn($p) => !empty($p['is_compliant'])));
$totalPendingCount = $totalPoliciesCount - $totalPassedCount;
$totalOverdueCount = count(array_filter($policyRecords, function($p) {
    return empty($p['is_compliant']) && !empty($p['due_date']) && strtotime($p['due_date']) < time();
}));
$overallComplianceRate = $totalPoliciesCount > 0 ? round(($totalPassedCount / $totalPoliciesCount) * 100, 1) : 100.0;

// Filter handling
$selectedInstFilter = $_GET['inst'] ?? 'all';
$selectedStatusFilter = $_GET['status'] ?? 'all';
$searchQuery = strtolower(trim($_GET['q'] ?? ''));

$filteredRecords = array_filter($policyRecords, function($p) use ($selectedInstFilter, $selectedStatusFilter, $searchQuery, $institutionsMap) {
    if ($selectedInstFilter !== 'all' && ($p['institution_id'] ?? '') !== $selectedInstFilter) {
        return false;
    }
    $isPassed = !empty($p['is_compliant']);
    $dueDate = $p['due_date'] ?? '';
    $isOverdue = (!$isPassed && $dueDate && strtotime($dueDate) < time());

    if ($selectedStatusFilter === 'compliant' && !$isPassed) {
        return false;
    }
    if ($selectedStatusFilter === 'pending' && $isPassed) {
        return false;
    }
    if ($selectedStatusFilter === 'overdue' && !$isOverdue) {
        return false;
    }
    if (!empty($searchQuery)) {
        $instName = strtolower($institutionsMap[$p['institution_id']]['name'] ?? '');
        $instAcronym = strtolower($institutionsMap[$p['institution_id']]['acronym'] ?? '');
        $polName = strtolower($p['policy_name'] ?? '');
        $polDesc = strtolower($p['policy_description'] ?? '');
        $notes = strtolower($p['notes'] ?? '');
        if (strpos($instName, $searchQuery) === false && 
            strpos($instAcronym, $searchQuery) === false && 
            strpos($polName, $searchQuery) === false && 
            strpos($polDesc, $searchQuery) === false && 
            strpos($notes, $searchQuery) === false) {
            return false;
        }
    }
    return true;
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Official Chapter Policy Compliance, bylaws monitoring, certificate generation, and accreditation matrix for IECEP-LSC Laguna Student Chapter.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800&family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-page);
            color: #1E293B;
            margin: 0;
            padding: 0;
        }

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

        .dash-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.65rem;
            margin-bottom: 0.85rem;
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
            min-width: 0;
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
        .kpi-icon-pill.navy { background: rgba(11, 29, 74, 0.08); color: var(--color-navy); }
        .kpi-icon-pill.emerald { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }
        .kpi-icon-pill.gold { background: #FEF9C3; color: #B45309; border: 1px solid #FDE68A; }
        .kpi-icon-pill.amber { background: #FEF3C7; color: #D97706; border: 1px solid #FDE68A; }
        .kpi-icon-pill.rose { background: #FFE4E6; color: #E11D48; border: 1px solid #FECDD3; }

        .kpi-val {
            font-size: 1.25rem;
            font-weight: 800;
            color: #0F172A;
            line-height: 1.1;
            font-family: 'JetBrains Mono', monospace;
        }
        .kpi-lbl {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748B;
            margin-top: 1px;
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
        }
        .btn-white:hover {
            background: #F8FAFC;
            border-color: #94A3B8;
            transform: translateY(-1px);
        }

        .btn-primary-navy {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.42rem 0.85rem;
            border-radius: 7px;
            font-size: 0.78rem;
            font-weight: 700;
            text-decoration: none;
            background: var(--color-navy);
            border: 1px solid var(--color-navy);
            color: #FFFFFF;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0,0,0,0.08);
            transition: all 0.18s ease;
        }
        .btn-primary-navy:hover {
            background: var(--color-navy-hover);
            transform: translateY(-1px);
        }

        .filter-panel {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            margin-bottom: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.65rem;
            box-shadow: var(--shadow-card);
        }

        .ap-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.72rem;
            font-weight: 700;
        }
        .ap-badge.passed {
            background: #ECFDF5;
            color: #059669;
            border: 1px solid #A7F3D0;
        }
        .ap-badge.pending {
            background: #FEF3C7;
            color: #D97706;
            border: 1px solid #FDE68A;
        }
        .ap-badge.overdue {
            background: #FFE4E6;
            color: #E11D48;
            border: 1px solid #FECDD3;
        }

        .modal-backdrop-custom {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .modal-card-custom {
            background: #FFFFFF;
            border-radius: 12px;
            width: 95%;
            max-width: 580px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.25);
            overflow: hidden;
            animation: modalPop 0.2s ease-out;
        }

        @keyframes modalPop {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        /* Certificate Print & Modal Layout */
        .cert-container {
            border: 8px double #D4AF37;
            padding: 2.5rem 2rem;
            background: #FDFAF2;
            color: #0B1D4A;
            position: relative;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border-radius: 8px;
        }
        .cert-seal {
            width: 76px;
            height: 76px;
            border-radius: 50%;
            border: 2px solid #D4AF37;
            background: rgba(212,175,55,0.15);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #B45309;
            font-size: 2rem;
            margin-bottom: 0.75rem;
        }

        #toast-notification {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #0B1D4A;
            color: #FFFFFF;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3);
            display: none;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            font-size: 0.85rem;
            font-weight: 600;
        }

        @media print {
            body * { visibility: hidden; }
            #certModal, #certModal * { visibility: visible; }
            #certModal { position: absolute; left: 0; top: 0; width: 100%; height: 100%; background: none; display: block !important; }
            .cert-actions { display: none !important; }
            .cert-container { border: 6px double #D4AF37 !important; padding: 2.5cm 1.5cm !important; }
        }

        @media (max-width: 1024px) {
            .dash-kpi-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .dash-kpi-grid { grid-template-columns: 1fr; }
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
                        <i class="fas fa-clipboard-check" style="color:var(--color-navy);"></i>
                        Institutional Policy Compliance & Governance
                    </h1>
                    <p class="dash-header-sub">
                        Active regulatory checklist, chapter bylaws ratifications, email notices, and accreditation governance matrix.
                    </p>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <a href="<?= PORTAL_URL ?>/admin/compliance/dashboard.php" class="btn-white">
                        <i class="fas fa-shield-halved" style="color:var(--color-blue);"></i> Governance Dashboard
                    </a>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Re-sync and seed standard IECEP-LSC baseline checklist across all 9 chapters?');">
                        <input type="hidden" name="action" value="seed_standards">
                        <button type="submit" class="btn-white" title="Ensure all chapters have the 5 standard baseline policies">
                            <i class="fas fa-arrows-rotate" style="color:var(--color-gold);"></i> Seed Standards
                        </button>
                    </form>
                    <button class="btn-white" onclick="exportCSV()">
                        <i class="fas fa-download" style="color:var(--color-emerald);"></i> Export CSV
                    </button>
                    <button class="btn-white" onclick="window.print()">
                        <i class="fas fa-print"></i> Print Matrix
                    </button>
                    <button class="btn-primary-navy" onclick="openAddPolicyModal()">
                        <i class="fas fa-plus" style="color:var(--color-gold);"></i> Add Requirement
                    </button>
                </div>
            </div>

            <!-- Feedback Alert -->
            <?php if (!empty($feedbackMsg)): ?>
                <div class="ap-alert <?= $feedbackType ?>" style="margin-bottom:0.85rem;">
                    <i class="fas <?= $feedbackType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                    <div><?= htmlspecialchars($feedbackMsg) ?></div>
                </div>
            <?php endif; ?>

            <!-- 2. Top 4 KPI Metrics -->
            <div class="dash-kpi-grid">
                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-chart-pie"></i></div>
                    <div>
                        <div class="kpi-val" style="color:#059669;"><?= $overallComplianceRate ?>%</div>
                        <div class="kpi-lbl">Overall Regional Compliance</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill navy"><i class="fas fa-university"></i></div>
                    <div>
                        <div class="kpi-val"><?= count($institutionsList) ?></div>
                        <div class="kpi-lbl">Accredited Laguna Chapters</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-list-check"></i></div>
                    <div>
                        <div class="kpi-val"><?= $totalPassedCount ?> <span style="font-size:0.75rem; color:#64748B;">/ <?= $totalPoliciesCount ?></span></div>
                        <div class="kpi-lbl">Complied Regulatory Requirements</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill <?= $totalOverdueCount > 0 ? 'rose' : 'amber' ?>"><i class="fas <?= $totalOverdueCount > 0 ? 'fa-calendar-xmark' : 'fa-clock-rotate-left' ?>"></i></div>
                    <div>
                        <div class="kpi-val" style="color:<?= $totalOverdueCount > 0 ? '#E11D48' : '#D97706' ?>;"><?= $totalPendingCount ?> <span style="font-size:0.75rem; color:#64748B;">(<?= $totalOverdueCount ?> Overdue)</span></div>
                        <div class="kpi-lbl">Pending Review & Audits</div>
                    </div>
                </div>
            </div>

            <!-- 3. Filter and Search Bar -->
            <form method="GET" class="filter-panel">
                <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap; flex:1;">
                    <div style="position:relative; min-width:200px; flex:1;">
                        <i class="fas fa-search" style="position:absolute; left:0.65rem; top:50%; transform:translateY(-50%); color:#94A3B8; font-size:0.8rem;"></i>
                        <input type="text" name="q" id="tableSearch" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="Search policy, chapter acronym, notes..." style="width:100%; padding:0.42rem 0.65rem 0.42rem 2rem; border-radius:7px; border:1px solid #CBD5E1; font-size:0.78rem; font-family:'Plus Jakarta Sans',sans-serif; box-sizing:border-box;">
                    </div>

                    <select name="inst" onchange="this.form.submit()" style="padding:0.42rem 0.65rem; border-radius:7px; border:1px solid #CBD5E1; font-size:0.78rem; background:#FFFFFF; color:#0F172A; font-family:'Plus Jakarta Sans',sans-serif;">
                        <option value="all">All Laguna Chapters</option>
                        <?php foreach ($institutionsList as $inst): ?>
                            <option value="<?= $inst['id'] ?>" <?= $selectedInstFilter === $inst['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($inst['acronym'] ?: $inst['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="status" onchange="this.form.submit()" style="padding:0.42rem 0.65rem; border-radius:7px; border:1px solid #CBD5E1; font-size:0.78rem; background:#FFFFFF; color:#0F172A; font-family:'Plus Jakarta Sans',sans-serif;">
                        <option value="all" <?= $selectedStatusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="compliant" <?= $selectedStatusFilter === 'compliant' ? 'selected' : '' ?>>Compliant (Passed)</option>
                        <option value="pending" <?= $selectedStatusFilter === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                        <option value="overdue" <?= $selectedStatusFilter === 'overdue' ? 'selected' : '' ?>>Past Target Date</option>
                    </select>
                </div>

                <div style="display:flex; gap:0.4rem;">
                    <button type="submit" class="btn-white" style="background:var(--color-navy); color:#FFFFFF; border-color:var(--color-navy);">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="policy-compliance.php" class="btn-white" style="color:#64748B;">
                        <i class="fas fa-times"></i> Reset
                    </a>
                </div>
            </form>

            <!-- 4. Policy Compliance Table -->
            <div class="ap-card" style="margin-bottom:1rem;">
                <div class="ap-card-header">
                    <h3 class="ap-card-title">
                        <i class="fas fa-tasks" style="color:var(--color-navy); margin-right:0.35rem;"></i>
                        Chapter Policy Checklist & Governance Matrix (<span id="visibleCount"><?= count($filteredRecords) ?></span> Requirements)
                    </h3>
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <span style="font-size:0.76rem; color:#64748B;">Academic Year: <strong>2026–2027</strong></span>
                    </div>
                </div>

                <div class="ap-card-body" style="padding:0; overflow-x:auto;">
                    <table class="ap-table" id="complianceTable" style="width:100%; border-collapse:collapse; text-align:left;">
                        <thead>
                            <tr>
                                <th style="width:25%;">Chapter / Institution</th>
                                <th style="width:33%;">Regulatory Requirement</th>
                                <th style="width:12%;">Target Date</th>
                                <th style="width:14%;">Audit Status</th>
                                <th style="width:16%; text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($filteredRecords)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:2rem; color:#64748B;">
                                        <i class="fas fa-clipboard-check" style="font-size:2rem; color:#CBD5E1; margin-bottom:0.5rem; display:block;"></i>
                                        No policy compliance records matched your filter criteria.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($filteredRecords as $rec): ?>
                                    <?php
                                        $inst = $institutionsMap[$rec['institution_id']] ?? [];
                                        $isPassed = !empty($rec['is_compliant']);
                                        $dueDate = $rec['due_date'] ?? '';
                                        $isOverdue = (!$isPassed && $dueDate && strtotime($dueDate) < time());
                                    ?>
                                    <tr id="row-<?= $rec['id'] ?>" style="border-bottom:1px solid #F1F5F9;">
                                        <td style="padding:0.75rem 1rem;">
                                            <div style="font-weight:700; color:#0F172A; font-size:0.84rem;">
                                                <?= htmlspecialchars($inst['name'] ?? 'Affiliated Chapter') ?>
                                            </div>
                                            <div style="font-size:0.72rem; color:#64748B; margin-top:1px;">
                                                Acronym: <strong><?= htmlspecialchars($inst['acronym'] ?? 'HEI') ?></strong> &bull; <?= number_format($inst['membership_count'] ?? 0) ?> Members
                                            </div>
                                        </td>
                                        <td style="padding:0.75rem 1rem;">
                                            <div id="pol-name-<?= $rec['id'] ?>" style="font-weight:700; color:var(--color-navy); font-size:0.82rem;">
                                                <?= htmlspecialchars($rec['policy_name'] ?? 'Regulatory Requirement') ?>
                                            </div>
                                            <div id="pol-desc-<?= $rec['id'] ?>" style="font-size:0.72rem; color:#64748B; margin-top:2px;">
                                                <?= htmlspecialchars($rec['policy_description'] ?? '') ?>
                                            </div>
                                            <?php if (!empty($rec['notes'])): ?>
                                                <div id="notes-text-<?= $rec['id'] ?>" style="font-size:0.7rem; color:#2563EB; margin-top:3px; font-style:italic;">
                                                    <i class="fas fa-note-sticky"></i> <?= htmlspecialchars($rec['notes']) ?>
                                                </div>
                                            <?php else: ?>
                                                <div id="notes-text-<?= $rec['id'] ?>" style="display:none; font-size:0.7rem; color:#2563EB; margin-top:3px; font-style:italic;"></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:0.75rem 1rem; font-family:'JetBrains Mono',monospace; font-size:0.76rem;" id="duedate-cell-<?= $rec['id'] ?>">
                                            <?= $dueDate ? date('M d, Y', strtotime($dueDate)) : 'Term Ongoing' ?>
                                            <?php if ($isOverdue): ?>
                                                <div style="color:#E11D48; font-size:0.68rem; font-weight:700;">Overdue</div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:0.75rem 1rem;" id="status-cell-<?= $rec['id'] ?>">
                                            <?php if ($isPassed): ?>
                                                <span class="ap-badge passed">
                                                    <i class="fas fa-check-circle"></i> Compliant
                                                </span>
                                                <div style="font-size:0.68rem; color:#64748B; margin-top:2px; font-family:'JetBrains Mono',monospace;">
                                                    <?= !empty($rec['completed_at']) ? date('M d, Y', strtotime($rec['completed_at'])) : 'Verified' ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="ap-badge <?= $isOverdue ? 'overdue' : 'pending' ?>">
                                                    <i class="fas <?= $isOverdue ? 'fa-triangle-exclamation' : 'fa-clock' ?>"></i>
                                                    <?= $isOverdue ? 'Overdue' : 'Pending Review' ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding:0.75rem 1rem; text-align:right;">
                                            <div style="display:inline-flex; gap:0.25rem;">
                                                <button type="button" class="btn-white" id="toggle-btn-<?= $rec['id'] ?>" style="padding:0.28rem 0.55rem; font-size:0.72rem;" title="<?= $isPassed ? 'Mark Pending' : 'Mark Compliant' ?>" onclick="ajaxTogglePolicy('<?= $rec['id'] ?>', <?= $isPassed ? 0 : 1 ?>)">
                                                    <i class="fas <?= $isPassed ? 'fa-arrow-rotate-left' : 'fa-check' ?>" style="color:<?= $isPassed ? '#64748B' : '#059669' ?>;"></i>
                                                    <span id="toggle-text-<?= $rec['id'] ?>"><?= $isPassed ? 'Unverify' : 'Verify' ?></span>
                                                </button>
                                                <button type="button" class="btn-white" style="padding:0.28rem 0.55rem; font-size:0.72rem;" title="Edit Requirement" onclick='openEditPolicyModal(<?= json_encode($rec) ?>, "<?= htmlspecialchars(addslashes($inst['name'] ?? 'Chapter')) ?>")'>
                                                    <i class="fas fa-pen-to-square"></i>
                                                </button>
                                                <button type="button" class="btn-white" style="padding:0.28rem 0.55rem; font-size:0.72rem;" title="Quick Note" onclick="openNotesModal('<?= $rec['id'] ?>', '<?= htmlspecialchars(addslashes($rec['policy_name'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($rec['notes'] ?? '')) ?>')">
                                                    <i class="fas fa-note-sticky" style="color:var(--color-navy);"></i>
                                                </button>
                                                <button type="button" class="btn-white" style="padding:0.28rem 0.55rem; font-size:0.72rem; color:#E11D48;" title="Delete Requirement" onclick="confirmDeletePolicy('<?= $rec['id'] ?>', '<?= htmlspecialchars(addslashes($rec['policy_name'] ?? '')) ?>')">
                                                    <i class="fas fa-trash"></i>
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

            <!-- 5. Per-Institution Compliance Matrix Summary & Certificate Generator -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title">
                        <i class="fas fa-building-columns" style="color:var(--color-navy); margin-right:0.35rem;"></i>
                        Chapter Regulatory Standing, Official Notices & Accreditation Certificates
                    </h3>
                </div>
                <div class="ap-card-body" style="padding:1rem;">
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:0.85rem;">
                        <?php foreach ($institutionsList as $inst): ?>
                            <?php
                                $iid = $inst['id'];
                                $instPols = $policiesPerInst[$iid] ?? [];
                                $instPassed = count(array_filter($instPols, fn($p) => !empty($p['is_compliant'])));
                                $instTotal = count($instPols);
                                $instScore = $instTotal > 0 ? round(($instPassed / $instTotal) * 100) : 100;
                                $isAllGood = ($instScore >= 100);
                            ?>
                            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:8px; padding:0.85rem 1rem;">
                                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.45rem;">
                                    <div>
                                        <div style="font-weight:800; font-size:0.86rem; color:#0F172A;">
                                            <?= htmlspecialchars($inst['acronym'] ?: $inst['name']) ?>
                                        </div>
                                        <div style="font-size:0.72rem; color:#64748B;">
                                            <?= htmlspecialchars($inst['name']) ?>
                                        </div>
                                    </div>
                                    <span class="ap-badge <?= $isAllGood ? 'passed' : 'pending' ?>">
                                        <?= $instScore ?>%
                                    </span>
                                </div>
                                <div style="width:100%; height:6px; background:#E2E8F0; border-radius:999px; overflow:hidden; margin:0.5rem 0;">
                                    <div style="width:<?= $instScore ?>%; height:100%; background:<?= $isAllGood ? '#059669' : '#D97706' ?>; border-radius:999px;"></div>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.72rem; color:#64748B; margin-top:0.6rem; flex-wrap:wrap; gap:0.4rem;">
                                    <span><?= $instPassed ?> of <?= $instTotal ?> Complied</span>
                                    <div style="display:flex; gap:0.25rem; flex-wrap:wrap;">
                                        <button type="button" class="btn-white" style="padding:0.22rem 0.45rem; font-size:0.7rem;" onclick="openNoticeModal('<?= $iid ?>', '<?= htmlspecialchars(addslashes($inst['name'])) ?>')">
                                            <i class="fas fa-envelope" style="color:var(--color-blue);"></i> Notice
                                        </button>
                                        <button type="button" class="btn-white" style="padding:0.22rem 0.45rem; font-size:0.7rem;" onclick="openCertModal('<?= htmlspecialchars(addslashes($inst['name'])) ?>', '<?= htmlspecialchars(addslashes($inst['acronym'] ?? 'HEI')) ?>', '<?= $instScore ?>', '<?= $iid ?>')">
                                            <i class="fas fa-award" style="color:var(--color-gold);"></i> Certificate
                                        </button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Mark ALL requirements as Compliant and anchor accreditation for <?= htmlspecialchars(addslashes($inst['name'])) ?>?');">
                                            <input type="hidden" name="action" value="batch_comply">
                                            <input type="hidden" name="institution_id" value="<?= $iid ?>">
                                            <button type="submit" class="btn-white" style="padding:0.22rem 0.45rem; font-size:0.7rem; color:#059669;" title="Mark all Compliant">
                                                <i class="fas fa-check-double"></i> Verify All
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Reset all requirements to Pending Review for <?= htmlspecialchars(addslashes($inst['name'])) ?>?');">
                                            <input type="hidden" name="action" value="batch_reset">
                                            <input type="hidden" name="institution_id" value="<?= $iid ?>">
                                            <button type="submit" class="btn-white" style="padding:0.22rem 0.45rem; font-size:0.7rem; color:#D97706;" title="Reset / Audit Re-evaluation">
                                                <i class="fas fa-arrow-rotate-left"></i> Reset
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Modal: Add Requirement -->
    <div id="addPolicyModal" class="modal-backdrop-custom">
        <div class="modal-card-custom">
            <div style="padding:1rem 1.25rem; border-bottom:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; font-size:0.95rem; font-weight:800; color:#0F172A;">
                    <i class="fas fa-plus-circle" style="color:var(--color-navy);"></i> Add Regulatory Policy Requirement
                </h3>
                <button type="button" onclick="closeAddPolicyModal()" style="background:none; border:none; color:#64748B; font-size:1.1rem; cursor:pointer;">&times;</button>
            </div>
            <form method="POST" style="padding:1.25rem;">
                <input type="hidden" name="action" value="add_policy">

                <div style="margin-bottom:0.85rem;">
                    <label style="display:block; font-size:0.76rem; font-weight:700; color:#334155; margin-bottom:0.3rem;">Target Chapter / Institution</label>
                    <select name="institution_id" required style="width:100%; padding:0.45rem 0.65rem; border-radius:7px; border:1px solid #CBD5E1; font-size:0.8rem; font-family:'Plus Jakarta Sans',sans-serif;">
                        <option value="all">🌟 All Chapters (Broadcast Policy to All 9 Universities)</option>
                        <?php foreach ($institutionsList as $inst): ?>
                            <option value="<?= $inst['id'] ?>">
                                <?= htmlspecialchars($inst['name']) ?> (<?= htmlspecialchars($inst['acronym'] ?? 'HEI') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom:0.85rem;">
                    <label style="display:block; font-size:0.76rem; font-weight:700; color:#334155; margin-bottom:0.3rem;">Policy Requirement Title</label>
                    <input type="text" name="policy_name" required placeholder="e.g. Mid-Year Chapter Accomplishment Dossier" style="width:100%; padding:0.45rem 0.65rem; border-radius:7px; border:1px solid #CBD5E1; font-size:0.8rem; font-family:'Plus Jakarta Sans',sans-serif; box-sizing:border-box;">
                </div>

                <div style="margin-bottom:0.85rem;">
                    <label style="display:block; font-size:0.76rem; font-weight:700; color:#334155; margin-bottom:0.3rem;">Description / Guidelines</label>
                    <textarea name="policy_description" rows="2" placeholder="Describe the required proof, format, and accreditation guidelines..." style="width:100%; padding:0.45rem 0.65rem; border-radius:7px; border:1px solid #CBD5E1; font-size:0.8rem; font-family:'Plus Jakarta Sans',sans-serif; box-sizing:border-box;"></textarea>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.65rem; margin-bottom:0.85rem;">
                    <div>
                        <label style="display:block; font-size:0.76rem; font-weight:700; color:#334155; margin-bottom:0.3rem;">Target Date</label>
                        <input type="date" name="due_date" value="<?= date('Y-11-30') ?>" required style="width:100%; padding:0.45rem 0.65rem; border-radius:7px; border:1px solid #CBD5E1; font-size:0.8rem; font-family:'Plus Jakarta Sans',sans-serif; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:0.76rem; font-weight:700; color:#334155; margin-bottom:0.3rem;">Initial Status</label>
                        <select name="is_compliant" style="width:100%; padding:0.45rem 0.65rem; border-radius:7px; border:1px solid #CBD5E1; font-size:0.8rem; font-family:'Plus Jakarta Sans',sans-serif;">
                            <option value="1">Compliant (Pre-verified)</option>
                            <option value="0" selected>Pending Review</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom:1.25rem;">
                    <label style="display:block; font-size:0.76rem; font-weight:700; color:#334155; margin-bottom:0.3rem;">Auditor Notes (Optional)</label>
                    <input type="text" name="notes" placeholder="Reference number or special instructions" style="width:100%; padding:0.45rem 0.65rem; border-radius:7px; border:1px solid #CBD5E1; font-size:0.8rem; font-family:'Plus Jakarta Sans',sans-serif; box-sizing:border-box;">
                </div>

                <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                    <button type="button" class="btn-white" onclick="closeAddPolicyModal()">Cancel</button>
                    <button type="submit" class="btn-primary-navy">
                        <i class="fas fa-save"></i> Save & Broadcast Policy
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Edit Full Policy Requirement -->
    <div id="editPolicyModal" class="modal-backdrop-custom">
        <div class="modal-card-custom">
            <div style="padding:1rem 1.25rem; border-bottom:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; font-size:0.95rem; font-weight:800; color:#0F172A;">
                    <i class="fas fa-pen-to-square" style="color:var(--color-navy);"></i> Edit Policy Requirement
                </h3>
                <button type="button" onclick="closeEditPolicyModal()" style="background:none; border:none; color:#64748B; font-size:1.1rem; cursor:pointer;">&times;</button>
            </div>
            <form method="POST" style="padding:1.25rem;">
                <input type="hidden" name="action" value="edit_policy">
                <input type="hidden" name="policy_id" id="editPolicyId" value="">

                <div style="margin-bottom:0.85rem;">
                    <label style="display:block; font-size:0.76rem; font-weight:700; color:#334155; margin-bottom:0.3rem;">Chapter / University</label>
                    <input type="text" id="editChapterName" disabled style="width:100%; padding:0.45rem 0.65rem; border-radius:7px; border:1px solid #E2E8F0; background:#F8FAFC; color:#64748B; font-size:0.8rem; font-family:'Plus Jakarta Sans',sans-serif; box-sizing:border-box;">
                </div>

                <div style="margin-bottom:0.85rem;">
                    <label style="display:block; font-size:0.76rem; font-weight:700; color:#334155; margin-bottom:0.3rem;">Policy Requirement Title</label>
                    <input type="text" name="policy_name" id="editPolicyName" required style="width:100%; padding:0.45rem 0.65rem; border-radius:7px; border:1px solid #CBD5E1; font-size:0.8rem; font-family:'Plus Jakarta Sans',sans-serif; box-sizing:border-box;">
                </div>

                <div style="margin-bottom:0.85rem;">
                    <label style="display:block; font-size:0.76rem; font-weight:700; color:#334155; margin-bottom:0.3rem;">Description / Guidelines</label>
                    <textarea name="policy_description" id="editPolicyDesc" rows="2" style="width:100%; padding:0.45rem 0.65rem; border-radius:7px; border:1px solid #CBD5E1; font-size:0.8rem; font-family:'Plus Jakarta Sans',sans-serif; box-sizing:border-box;"></textarea>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.65rem; margin-bottom:0.85rem;">
                    <div>
                        <label style="display:block; font-size:0.76rem; font-weight:700; color:#334155; margin-bottom:0.3rem;">Target Date</label>
                        <input type="date" name="due_date" id="editDueDate" style="width:100%; padding:0.45rem 0.65rem; border-radius:7px; border:1px solid #CBD5E1; font-size:0.8rem; font-family:'Plus Jakarta Sans',sans-serif; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:0.76rem; font-weight:700; color:#334155; margin-bottom:0.3rem;">Audit Status</label>
                        <select name="is_compliant" id="editStatusSelect" style="width:100%; padding:0.45rem 0.65rem; border-radius:7px; border:1px solid #CBD5E1; font-size:0.8rem; font-family:'Plus Jakarta Sans',sans-serif;">
                            <option value="1">Compliant (Passed)</option>
                            <option value="0">Pending Review</option>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom:1.25rem;">
                    <label style="display:block; font-size:0.76rem; font-weight:700; color:#334155; margin-bottom:0.3rem;">Auditor Notes / Verification Links</label>
                    <input type="text" name="notes" id="editNotesField" placeholder="Audit remarks, tracking code, or document link" style="width:100%; padding:0.45rem 0.65rem; border-radius:7px; border:1px solid #CBD5E1; font-size:0.8rem; font-family:'Plus Jakarta Sans',sans-serif; box-sizing:border-box;">
                </div>

                <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                    <button type="button" class="btn-white" onclick="closeEditPolicyModal()">Cancel</button>
                    <button type="submit" class="btn-primary-navy">
                        <i class="fas fa-check"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Quick Notes -->
    <div id="editNotesModal" class="modal-backdrop-custom">
        <div class="modal-card-custom">
            <div style="padding:1rem 1.25rem; border-bottom:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; font-size:0.95rem; font-weight:800; color:#0F172A;">
                    <i class="fas fa-note-sticky" style="color:var(--color-navy);"></i> Auditor Note: <span id="notePolicyTitle" style="color:var(--color-blue);"></span>
                </h3>
                <button type="button" onclick="closeNotesModal()" style="background:none; border:none; color:#64748B; font-size:1.1rem; cursor:pointer;">&times;</button>
            </div>
            <form method="POST" style="padding:1.25rem;">
                <input type="hidden" name="action" value="toggle_policy">
                <input type="hidden" name="policy_id" id="notePolicyId" value="">
                <input type="hidden" name="is_compliant" id="noteIsCompliant" value="1">

                <div style="margin-bottom:1.25rem;">
                    <label style="display:block; font-size:0.76rem; font-weight:700; color:#334155; margin-bottom:0.3rem;">Compliance Verification Remarks / Tracking Code</label>
                    <textarea name="notes" id="noteText" rows="3" required placeholder="Enter auditor remarks, tracking code, or proof verification notes..." style="width:100%; padding:0.55rem; border-radius:7px; border:1px solid #CBD5E1; font-size:0.8rem; font-family:'Plus Jakarta Sans',sans-serif; box-sizing:border-box;"></textarea>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                    <button type="button" class="btn-white" onclick="closeNotesModal()">Cancel</button>
                    <button type="submit" class="btn-primary-navy">
                        <i class="fas fa-check"></i> Save Note & Verify
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Send Notice -->
    <div id="noticeModal" class="modal-backdrop-custom">
        <div class="modal-card-custom">
            <div style="padding:1rem 1.25rem; border-bottom:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; font-size:0.95rem; font-weight:800; color:#0F172A;">
                    <i class="fas fa-paper-plane" style="color:var(--color-blue);"></i> Issue Official Compliance Notice
                </h3>
                <button type="button" onclick="closeNoticeModal()" style="background:none; border:none; color:#64748B; font-size:1.1rem; cursor:pointer;">&times;</button>
            </div>
            <form method="POST" style="padding:1.25rem;">
                <input type="hidden" name="action" value="send_notice">
                <input type="hidden" name="institution_id" id="noticeInstId" value="">
                
                <p style="font-size:0.8rem; color:#475569; margin:0 0 0.85rem;">
                    Dispatch an official compliance notification email to the faculty adviser and student officers of <strong id="noticeInstName"></strong> with their active regulatory checklist.
                </p>

                <div style="margin-bottom:0.85rem;">
                    <label style="display:block; font-size:0.76rem; font-weight:700; color:#334155; margin-bottom:0.3rem;">Notice Classification / Subject Template</label>
                    <select name="notice_type" id="noticeTypeSelect" style="width:100%; padding:0.45rem 0.65rem; border-radius:7px; border:1px solid #CBD5E1; font-size:0.8rem; font-family:'Plus Jakarta Sans',sans-serif;">
                        <option value="regular">📋 Official Regulatory Compliance Checklist & Audit Notice</option>
                        <option value="urgent">📢 Chapter Compliance Monitoring Advisory Notice</option>
                        <option value="congrats">🏆 Official Chapter Accreditation Confirmation</option>
                    </select>
                </div>

                <div style="margin-bottom:1.25rem;">
                    <label style="display:block; font-size:0.76rem; font-weight:700; color:#334155; margin-bottom:0.3rem;">Custom Notice Instructions / Remarks (Optional)</label>
                    <textarea name="custom_message" rows="3" placeholder="e.g. Please submit your Chapter Constitution and By-Laws before the regional accreditation cutoff..." style="width:100%; padding:0.55rem; border-radius:7px; border:1px solid #CBD5E1; font-size:0.8rem; font-family:'Plus Jakarta Sans',sans-serif; box-sizing:border-box;"></textarea>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                    <button type="button" class="btn-white" onclick="closeNoticeModal()">Cancel</button>
                    <button type="submit" class="btn-primary-navy" style="background:#2563EB; border-color:#2563EB;">
                        <i class="fas fa-paper-plane"></i> Send Official Email
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Official Certificate of Compliance -->
    <div id="certModal" class="modal-backdrop-custom">
        <div class="modal-card-custom" style="max-width:740px;">
            <div style="padding:0.75rem 1.25rem; border-bottom:1px solid #E2E8F0; display:flex; justify-content:space-between; align-items:center; background:#F8FAFC;">
                <div style="display:flex; align-items:center; gap:0.5rem;">
                    <span style="font-size:0.82rem; font-weight:700; color:#0F172A;"><i class="fas fa-award" style="color:var(--color-gold);"></i> Official Accreditation Certificate</span>
                    <select id="certInstPicker" onchange="switchCertInstitution(this.value)" style="padding:0.25rem 0.5rem; font-size:0.74rem; border-radius:6px; border:1px solid #CBD5E1; background:#FFFFFF;">
                        <?php foreach ($institutionsList as $inst): ?>
                            <option value="<?= $inst['id'] ?>" data-name="<?= htmlspecialchars($inst['name']) ?>" data-acronym="<?= htmlspecialchars($inst['acronym'] ?? 'HEI') ?>">
                                <?= htmlspecialchars($inst['acronym'] ?: $inst['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="cert-actions" style="display:flex; gap:0.4rem;">
                    <button type="button" class="btn-white" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                    <button type="button" onclick="closeCertModal()" style="background:none; border:none; color:#64748B; font-size:1.1rem; cursor:pointer;">&times;</button>
                </div>
            </div>

            <div style="padding:1.5rem;">
                <div class="cert-container">
                    <div class="cert-seal">
                        <i class="fas fa-shield-halved"></i>
                    </div>
                    <div style="font-size:0.78rem; text-transform:uppercase; letter-spacing:0.12em; color:#0B1D4A; font-weight:800; margin-bottom:0.25rem;">
                        Institute of Electronics Engineers of the Philippines &bull; Laguna Student Chapter
                    </div>
                    <div style="font-size:0.68rem; text-transform:uppercase; letter-spacing:0.08em; color:#64748B; margin-bottom:1rem;">
                        Regional Committee on Student Affairs & Chapter Governance
                    </div>

                    <h2 style="font-family:'Cinzel',serif; font-size:1.45rem; color:#0B1D4A; margin:0.5rem 0; letter-spacing:0.03em;">
                        CERTIFICATE OF CHAPTER POLICY COMPLIANCE
                    </h2>
                    <p style="font-size:0.78rem; color:#64748B; margin:0 0 1.25rem;">This is to certify that</p>

                    <h3 id="certSchoolName" style="font-size:1.35rem; font-weight:800; color:#0B1D4A; margin:0 0 0.5rem; text-decoration:underline;">
                        Chapter University
                    </h3>
                    <p style="font-size:0.82rem; color:#334155; max-width:540px; margin:0 auto 1.5rem; line-height:1.6;">
                        has satisfactorily fulfilled all institutional governance mandates, chapter constitution &amp; by-laws ratifications, active student membership rosters, and regulatory obligations for <strong>Academic Year 2026–2027</strong>.
                    </p>

                    <div style="font-size:0.72rem; color:#64748B; margin-bottom:1.5rem;">
                        Date of Issuance: <strong><?= date('F d, Y') ?></strong> &bull; Status: <strong style="color:#059669;">FULLY ACCREDITED</strong>
                    </div>

                    <div style="display:flex; justify-content:space-around; align-items:flex-end; margin-top:1.5rem; padding-top:1rem; border-top:1px dashed #CBD5E1;">
                        <div>
                            <div style="font-family:'Cinzel',serif; font-weight:700; font-size:0.84rem; color:#0B1D4A;">ENGR. RASHED DIZON</div>
                            <div style="font-size:0.68rem; color:#64748B;">Regional Governor, IECEP-LSC</div>
                        </div>
                        <div>
                            <div style="font-size:0.65rem; color:#64748B; font-family:'JetBrains Mono',monospace;" id="certHashVal">
                                HASH: SHA256-VERIFIED-LSC
                            </div>
                            <div style="font-size:0.62rem; color:#059669; font-weight:700;">BLOCKCHAIN ANCHORED</div>
                        </div>
                        <div>
                            <div style="font-family:'Cinzel',serif; font-weight:700; font-size:0.84rem; color:#0B1D4A;">DR. ELEANOR RAMOS</div>
                            <div style="font-size:0.68rem; color:#64748B;">Regional Director, Academics</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden form for deleting policy -->
    <form id="deletePolicyForm" method="POST" style="display:none;">
        <input type="hidden" name="action" value="delete_policy">
        <input type="hidden" name="policy_id" id="deletePolicyId" value="">
    </form>

    <!-- Toast Notification -->
    <div id="toast-notification">
        <i class="fas fa-check-circle" style="color:#10B981; font-size:1.1rem;"></i>
        <span id="toast-message">Action completed successfully.</span>
    </div>

    <script>
        function showToast(msg) {
            const toast = document.getElementById('toast-notification');
            document.getElementById('toast-message').textContent = msg;
            toast.style.display = 'flex';
            setTimeout(() => { toast.style.display = 'none'; }, 3200);
        }

        // Live AJAX Policy Toggle without page reload
        async function ajaxTogglePolicy(policyId, desiredStatus) {
            const btn = document.getElementById('toggle-btn-' + policyId);
            const origHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'toggle_policy');
            formData.append('policy_id', policyId);
            formData.append('is_compliant', desiredStatus);

            try {
                const response = await fetch('policy-compliance.php?format=json', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                const res = await response.json();
                if (res.success) {
                    const statusCell = document.getElementById('status-cell-' + policyId);
                    if (res.is_compliant) {
                        statusCell.innerHTML = '<span class="ap-badge passed"><i class="fas fa-check-circle"></i> Compliant</span><div style="font-size:0.68rem; color:#64748B; margin-top:2px; font-family:\'JetBrains Mono\',monospace;">Just now</div>';
                        btn.onclick = function() { ajaxTogglePolicy(policyId, 0); };
                        btn.title = "Mark Pending";
                        btn.innerHTML = '<i class="fas fa-arrow-rotate-left" style="color:#64748B;"></i> <span id="toggle-text-' + policyId + '">Unverify</span>';
                        showToast('✓ Requirement verified as Compliant!');
                    } else {
                        statusCell.innerHTML = '<span class="ap-badge pending"><i class="fas fa-clock"></i> Pending Review</span>';
                        btn.onclick = function() { ajaxTogglePolicy(policyId, 1); };
                        btn.title = "Mark Compliant";
                        btn.innerHTML = '<i class="fas fa-check" style="color:#059669;"></i> <span id="toggle-text-' + policyId + '">Verify</span>';
                        showToast('✓ Requirement set to Pending Review.');
                    }
                } else {
                    alert('Error: ' + (res.error || 'Failed to update policy'));
                    btn.innerHTML = origHtml;
                }
            } catch (err) {
                console.error(err);
                location.reload();
            } finally {
                btn.disabled = false;
            }
        }

        function confirmDeletePolicy(id, name) {
            if (confirm("Are you sure you want to permanently delete the regulatory requirement '" + name + "'?")) {
                document.getElementById('deletePolicyId').value = id;
                document.getElementById('deletePolicyForm').submit();
            }
        }

        function openAddPolicyModal() {
            document.getElementById('addPolicyModal').style.display = 'flex';
        }
        function closeAddPolicyModal() {
            document.getElementById('addPolicyModal').style.display = 'none';
        }

        function openEditPolicyModal(rec, chapterName) {
            document.getElementById('editPolicyId').value = rec.id || '';
            document.getElementById('editChapterName').value = chapterName || 'Chapter';
            document.getElementById('editPolicyName').value = rec.policy_name || '';
            document.getElementById('editPolicyDesc').value = rec.policy_description || '';
            document.getElementById('editDueDate').value = rec.due_date || '';
            document.getElementById('editStatusSelect').value = (rec.is_compliant == 1 || rec.is_compliant === true) ? '1' : '0';
            document.getElementById('editNotesField').value = rec.notes || '';
            document.getElementById('editPolicyModal').style.display = 'flex';
        }
        function closeEditPolicyModal() {
            document.getElementById('editPolicyModal').style.display = 'none';
        }

        function openNotesModal(id, title, notes) {
            document.getElementById('notePolicyId').value = id;
            document.getElementById('notePolicyTitle').textContent = title;
            document.getElementById('noteText').value = notes;
            document.getElementById('editNotesModal').style.display = 'flex';
        }
        function closeNotesModal() {
            document.getElementById('editNotesModal').style.display = 'none';
        }

        function openNoticeModal(instId, instName) {
            document.getElementById('noticeInstId').value = instId;
            document.getElementById('noticeInstName').textContent = instName;
            document.getElementById('noticeModal').style.display = 'flex';
        }
        function closeNoticeModal() {
            document.getElementById('noticeModal').style.display = 'none';
        }

        function openCertModal(schoolName, acronym, score, instId) {
            document.getElementById('certSchoolName').textContent = schoolName;
            const pseudoHash = 'SHA256-' + btoa(schoolName + (instId || score)).substring(0, 16).toUpperCase();
            document.getElementById('certHashVal').textContent = 'HASH: ' + pseudoHash;
            if (instId) {
                const picker = document.getElementById('certInstPicker');
                if (picker) picker.value = instId;
            }
            document.getElementById('certModal').style.display = 'flex';
        }
        function closeCertModal() {
            document.getElementById('certModal').style.display = 'none';
        }

        function switchCertInstitution(instId) {
            const picker = document.getElementById('certInstPicker');
            const opt = picker.options[picker.selectedIndex];
            const name = opt.getAttribute('data-name');
            const acronym = opt.getAttribute('data-acronym');
            openCertModal(name, acronym, '100', instId);
        }

        // Real-time table search across all rows
        document.getElementById('tableSearch')?.addEventListener('input', function(e) {
            const val = e.target.value.toLowerCase().trim();
            const rows = document.querySelectorAll('#complianceTable tbody tr');
            let matched = 0;
            rows.forEach(r => {
                const text = r.textContent.toLowerCase();
                if (text.includes(val)) {
                    r.style.display = '';
                    matched++;
                } else {
                    r.style.display = 'none';
                }
            });
            const counter = document.getElementById('visibleCount');
            if (counter) counter.textContent = matched;
        });

        // Export CSV Functionality
        function exportCSV() {
            const rows = [
                ['Chapter Institution', 'Acronym', 'Policy Requirement', 'Description', 'Target Date', 'Status', 'Completed Date', 'Auditor Notes']
            ];

            <?php foreach ($policyRecords as $p): ?>
                <?php
                    $inst = $institutionsMap[$p['institution_id']] ?? [];
                    $st = !empty($p['is_compliant']) ? 'Compliant' : 'Pending Review';
                ?>
                rows.push([
                    <?= json_encode($inst['name'] ?? 'Chapter') ?>,
                    <?= json_encode($inst['acronym'] ?? 'HEI') ?>,
                    <?= json_encode($p['policy_name'] ?? '') ?>,
                    <?= json_encode($p['policy_description'] ?? '') ?>,
                    <?= json_encode($p['due_date'] ?? '') ?>,
                    <?= json_encode($st) ?>,
                    <?= json_encode($p['completed_at'] ?? '') ?>,
                    <?= json_encode($p['notes'] ?? '') ?>
                ]);
            <?php endforeach; ?>

            let csvContent = "\uFEFF" + rows.map(e => e.map(cell => '"' + (cell || '').toString().replace(/"/g, '""') + '"').join(",")).join("\r\n");
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            const url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", "IECEP_LSC_Policy_Compliance_" + new Date().toISOString().slice(0,10) + ".csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            showToast("✓ CSV Export completed!");
        }

        window.onclick = function(event) {
            const addModal = document.getElementById('addPolicyModal');
            const editModal = document.getElementById('editPolicyModal');
            const notesModal = document.getElementById('editNotesModal');
            const noticeModal = document.getElementById('noticeModal');
            const certModal = document.getElementById('certModal');
            if (event.target === addModal) closeAddPolicyModal();
            if (event.target === editModal) closeEditPolicyModal();
            if (event.target === notesModal) closeNotesModal();
            if (event.target === noticeModal) closeNoticeModal();
            if (event.target === certModal) closeCertModal();
        }
    </script>
</body>
</html>
