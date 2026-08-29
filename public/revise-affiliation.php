<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/../includes/config.php';

$appId = trim($_GET['id'] ?? '');
$token = trim($_GET['token'] ?? '');
$error = null;
$successMsg = null;
$affiliation = null;

$supabase = getSupabaseClient();

// Fetch application
if ($appId || $token) {
    try {
        if ($supabase) {
            if ($appId) {
                $res = $supabase->select('pending_affiliations', ['id' => 'eq.' . $appId]);
            } else {
                $res = $supabase->select('pending_affiliations', ['edit_token' => 'eq.' . $token]);
            }
            if (!empty($res) && is_array($res)) {
                $affiliation = $res[0];
            }
        }
    } catch (\Throwable $e) {
        error_log("Revise load error: " . $e->getMessage());
    }
}

if (!$affiliation) {
    $error = "Affiliation application record not found or link has expired. Please contact the Registration Committee.";
}

// Handle Form Submission (Re-upload replacement files)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_revision' && $affiliation) {
    try {
        $appId = $affiliation['id'];
        $uploadDir = dirname(__DIR__) . '/storage/affiliations/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }

        $documentFields = [
            'letter_of_intent' => 'Letter of Intent',
            'endorsement_letter' => 'Endorsement Letter',
            'constitution_by_laws' => 'Constitution & By-Laws',
            'officers_cvs' => 'Officers Curriculum Vitae',
            'organizational_chart' => 'Organizational Chart',
            'member_directory' => 'Member Directory Spreadsheet'
        ];

        $updatedDocs = [];
        $filesReplacedCount = 0;

        foreach ($documentFields as $fieldKey => $fieldLabel) {
            if (isset($_FILES[$fieldKey]) && $_FILES[$fieldKey]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$fieldKey];
                $origName = basename($file['name']);
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $safeName = 'REV_' . $fieldKey . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                $destPath = $uploadDir . $safeName;

                if (move_uploaded_file($file['tmp_name'], $destPath)) {
                    $webUrl = PUBLIC_URL . '/storage/affiliations/' . $safeName;
                    $updatedDocs[$fieldKey] = $webUrl;
                    $filesReplacedCount++;
                }
            }
        }

        $updatePayload = [
            'status' => 'resubmitted',
            'resubmitted_at' => date('c'),
            'updated_at' => date('c')
        ];

        foreach ($updatedDocs as $k => $val) {
            $updatePayload[$k] = $val;
        }

        if ($supabase && $appId) {
            $supabase->update('pending_affiliations', $updatePayload, $appId);
        }

        // Send confirmation email
        require_once __DIR__ . '/../src/lib/EmailService.php';
        $emailService = new \App\Lib\EmailService();
        $contactEmail = $affiliation['contact_email'] ?? $affiliation['email'] ?? '';
        $contactPerson = $affiliation['contact_person'] ?? 'School Representative';
        $instName = $affiliation['institution_name'] ?? 'School Chapter';

        try {
            if ($contactEmail) {
                $emailService->sendAffiliationRevisionResubmitted($contactEmail, $instName, $contactPerson);
            }
        } catch (\Throwable $emEx) {}

        $successMsg = "🎉 Your revised document(s) have been successfully submitted! The IECEP-LSC Registration Committee has been notified and will proceed with the final review.";

        // Reload updated affiliation
        $res = $supabase->select('pending_affiliations', ['id' => 'eq.' . $appId]);
        if (!empty($res)) $affiliation = $res[0];

    } catch (\Throwable $e) {
        $error = "Failed to upload files: " . $e->getMessage();
    }
}

// Parse requested files from application notes / flag if present
$notes = $affiliation['notes'] ?? '';
$requestedDocKeys = [];
if (!empty($affiliation['revision_files'])) {
    $requestedDocKeys = is_array($affiliation['revision_files']) ? $affiliation['revision_files'] : explode(',', (string)$affiliation['revision_files']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revise Affiliation Application — IECEP-LSC</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --color-navy: #0B1D4A;
            --color-gold: #D4AF37;
            --color-emerald: #059669;
            --color-amber: #D97706;
            --color-red: #DC2626;
            --bg-page: #F8FAFC;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-page);
            color: #1E293B;
            margin: 0;
            padding: 2rem 1rem;
        }

        .revision-card {
            max-width: 760px;
            margin: 0 auto;
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(11, 29, 74, 0.08);
            overflow: hidden;
        }

        .revision-header {
            background: var(--color-navy);
            color: #FFFFFF;
            padding: 2rem;
            text-align: center;
        }

        .revision-title {
            margin: 0 0 0.5rem;
            font-size: 1.4rem;
            font-weight: 800;
        }

        .revision-sub {
            margin: 0;
            color: var(--color-gold);
            font-size: 0.88rem;
            font-weight: 600;
        }

        .revision-body {
            padding: 2rem;
        }

        .instruction-box {
            background: #FFFBEB;
            border: 1px solid #FDE68A;
            border-radius: 10px;
            padding: 1.25rem;
            margin-bottom: 1.75rem;
        }
        .instruction-title {
            color: #92400E;
            font-weight: 800;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.4rem;
        }
        .instruction-text {
            color: #78350F;
            font-size: 0.88rem;
            line-height: 1.5;
            margin: 0;
        }

        .applicant-info-strip {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.75rem;
        }
        .info-label {
            font-size: 0.72rem;
            font-weight: 700;
            color: #64748B;
            text-transform: uppercase;
        }
        .info-value {
            font-size: 0.88rem;
            font-weight: 700;
            color: #0F172A;
            margin-top: 2px;
        }

        .doc-upload-item {
            background: #FFFFFF;
            border: 1.5px solid #E2E8F0;
            border-radius: 10px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: all 0.2s ease;
        }
        .doc-upload-item.requested {
            border-color: #F59E0B;
            background: #FFFDF5;
        }
        .doc-upload-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }
        .doc-name {
            font-weight: 800;
            font-size: 0.92rem;
            color: #0F172A;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .doc-badge {
            font-size: 0.72rem;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 4px;
        }
        .doc-badge.needs-fix {
            background: #FEF3C7;
            color: #92400E;
            border: 1px solid #FDE68A;
        }
        .doc-badge.current-ok {
            background: #ECFDF5;
            color: #059669;
            border: 1px solid #A7F3D0;
        }

        .file-input-field {
            width: 100%;
            padding: 0.6rem;
            border: 1px dashed #CBD5E1;
            border-radius: 8px;
            background: #FFFFFF;
            font-size: 0.82rem;
            box-sizing: border-box;
        }

        .btn-submit {
            width: 100%;
            background: var(--color-navy);
            color: #FFFFFF;
            border: none;
            border-radius: 10px;
            padding: 0.9rem;
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
            box-shadow: 0 4px 12px rgba(11, 29, 74, 0.2);
            transition: all 0.2s ease;
        }
        .btn-submit:hover {
            background: #152C6E;
            transform: translateY(-1px);
        }

        .alert-box {
            padding: 1rem 1.25rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            font-size: 0.88rem;
        }
        .alert-box.success {
            background: #ECFDF5;
            border: 1px solid #A7F3D0;
            color: #065F46;
        }
        .alert-box.danger {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #991B1B;
        }
    </style>
</head>
<body>

    <div class="revision-card">
        <div class="revision-header">
            <h1 class="revision-title"><i class="fas fa-file-pen"></i> Revise Chapter Affiliation Application</h1>
            <p class="revision-sub">IECEP Laguna Student Chapter (AY 2026-2027)</p>
        </div>

        <div class="revision-body">
            <?php if (!empty($error)): ?>
                <div class="alert-box danger">
                    <i class="fas fa-circle-exclamation" style="font-size:1.2rem; margin-top:2px;"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($successMsg)): ?>
                <div class="alert-box success">
                    <i class="fas fa-circle-check" style="font-size:1.2rem; margin-top:2px;"></i>
                    <div><?= htmlspecialchars($successMsg) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($affiliation): ?>
                <!-- Secretariat Feedback Box -->
                <?php if (!empty($notes)): ?>
                    <div class="instruction-box">
                        <div class="instruction-title">
                            <i class="fas fa-clipboard-list"></i> Secretariat Feedback & Requested Changes:
                        </div>
                        <p class="instruction-text">
                            <?= nl2br(htmlspecialchars($notes)) ?>
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Applicant Summary -->
                <div class="applicant-info-strip">
                    <div>
                        <div class="info-label">Applicant Institution</div>
                        <div class="info-value"><?= htmlspecialchars($affiliation['institution_name'] ?? 'School') ?></div>
                    </div>
                    <div>
                        <div class="info-label">Contact Officer</div>
                        <div class="info-value"><?= htmlspecialchars($affiliation['contact_person'] ?? 'Officer') ?> (<?= htmlspecialchars($affiliation['contact_email'] ?? $affiliation['email'] ?? '') ?>)</div>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="submit_revision">

                    <p style="font-size:0.85rem; font-weight:700; color:#0F172A; margin:0 0 1rem;">
                        Select and re-upload the replacement file(s) below:
                    </p>

                    <!-- 1. Letter of Intent -->
                    <?php $isReq1 = in_array('letter_of_intent', $requestedDocKeys) || empty($requestedDocKeys); ?>
                    <div class="doc-upload-item <?= $isReq1 ? 'requested' : '' ?>">
                        <div class="doc-upload-header">
                            <div class="doc-name">
                                <i class="fas fa-file-lines" style="color:var(--color-navy);"></i>
                                1. Letter of Intent
                            </div>
                            <span class="doc-badge <?= $isReq1 ? 'needs-fix' : 'current-ok' ?>">
                                <?= $isReq1 ? 'Update File' : 'Current File Attached' ?>
                            </span>
                        </div>
                        <input type="file" name="letter_of_intent" class="file-input-field" accept=".pdf,.doc,.docx">
                        <?php if (!empty($affiliation['letter_of_intent'])): ?>
                            <div style="font-size:0.72rem; color:#64748B; margin-top:4px;">
                                Current: <a href="<?= htmlspecialchars($affiliation['letter_of_intent']) ?>" target="_blank" style="color:#2563EB;">View previously uploaded file</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 2. Endorsement Letter -->
                    <?php $isReq2 = in_array('endorsement_letter', $requestedDocKeys); ?>
                    <div class="doc-upload-item <?= $isReq2 ? 'requested' : '' ?>">
                        <div class="doc-upload-header">
                            <div class="doc-name">
                                <i class="fas fa-certificate" style="color:var(--color-navy);"></i>
                                2. Endorsement Letter (from Dean/Chair)
                            </div>
                            <span class="doc-badge <?= $isReq2 ? 'needs-fix' : 'current-ok' ?>">
                                <?= $isReq2 ? 'Update File' : 'Current File Attached' ?>
                            </span>
                        </div>
                        <input type="file" name="endorsement_letter" class="file-input-field" accept=".pdf,.doc,.docx">
                        <?php if (!empty($affiliation['endorsement_letter'])): ?>
                            <div style="font-size:0.72rem; color:#64748B; margin-top:4px;">
                                Current: <a href="<?= htmlspecialchars($affiliation['endorsement_letter']) ?>" target="_blank" style="color:#2563EB;">View previously uploaded file</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 3. Constitution & By-Laws -->
                    <?php $isReq3 = in_array('constitution_by_laws', $requestedDocKeys); ?>
                    <div class="doc-upload-item <?= $isReq3 ? 'requested' : '' ?>">
                        <div class="doc-upload-header">
                            <div class="doc-name">
                                <i class="fas fa-scale-balanced" style="color:var(--color-navy);"></i>
                                3. Chapter Constitution & By-Laws
                            </div>
                            <span class="doc-badge <?= $isReq3 ? 'needs-fix' : 'current-ok' ?>">
                                <?= $isReq3 ? 'Update File' : 'Current File Attached' ?>
                            </span>
                        </div>
                        <input type="file" name="constitution_by_laws" class="file-input-field" accept=".pdf,.doc,.docx">
                        <?php if (!empty($affiliation['constitution_by_laws'])): ?>
                            <div style="font-size:0.72rem; color:#64748B; margin-top:4px;">
                                Current: <a href="<?= htmlspecialchars($affiliation['constitution_by_laws']) ?>" target="_blank" style="color:#2563EB;">View previously uploaded file</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 4. Officers CVs -->
                    <?php $isReq4 = in_array('officers_cvs', $requestedDocKeys); ?>
                    <div class="doc-upload-item <?= $isReq4 ? 'requested' : '' ?>">
                        <div class="doc-upload-header">
                            <div class="doc-name">
                                <i class="fas fa-user-tie" style="color:var(--color-navy);"></i>
                                4. Officers Curriculum Vitae (CVs)
                            </div>
                            <span class="doc-badge <?= $isReq4 ? 'needs-fix' : 'current-ok' ?>">
                                <?= $isReq4 ? 'Update File' : 'Current File Attached' ?>
                            </span>
                        </div>
                        <input type="file" name="officers_cvs" class="file-input-field" accept=".pdf,.doc,.docx">
                        <?php if (!empty($affiliation['officers_cvs'])): ?>
                            <div style="font-size:0.72rem; color:#64748B; margin-top:4px;">
                                Current: <a href="<?= htmlspecialchars($affiliation['officers_cvs']) ?>" target="_blank" style="color:#2563EB;">View previously uploaded file</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 5. Organizational Chart -->
                    <?php $isReq5 = in_array('organizational_chart', $requestedDocKeys); ?>
                    <div class="doc-upload-item <?= $isReq5 ? 'requested' : '' ?>">
                        <div class="doc-upload-header">
                            <div class="doc-name">
                                <i class="fas fa-sitemap" style="color:var(--color-navy);"></i>
                                5. Organizational Chart
                            </div>
                            <span class="doc-badge <?= $isReq5 ? 'needs-fix' : 'current-ok' ?>">
                                <?= $isReq5 ? 'Update File' : 'Current File Attached' ?>
                            </span>
                        </div>
                        <input type="file" name="organizational_chart" class="file-input-field" accept=".pdf,.doc,.docx,.png,.jpg">
                        <?php if (!empty($affiliation['organizational_chart'])): ?>
                            <div style="font-size:0.72rem; color:#64748B; margin-top:4px;">
                                Current: <a href="<?= htmlspecialchars($affiliation['organizational_chart']) ?>" target="_blank" style="color:#2563EB;">View previously uploaded file</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 6. Member Directory Spreadsheet -->
                    <?php $isReq6 = in_array('member_directory', $requestedDocKeys); ?>
                    <div class="doc-upload-item <?= $isReq6 ? 'requested' : '' ?>">
                        <div class="doc-upload-header">
                            <div class="doc-name">
                                <i class="fas fa-file-excel" style="color:#107C41;"></i>
                                6. Official Member Directory Spreadsheet (.xlsx / .csv)
                            </div>
                            <span class="doc-badge <?= $isReq6 ? 'needs-fix' : 'current-ok' ?>">
                                <?= $isReq6 ? 'Update File' : 'Current File Attached' ?>
                            </span>
                        </div>
                        <input type="file" name="member_directory" class="file-input-field" accept=".xlsx,.xls,.csv">
                        <?php if (!empty($affiliation['member_directory'])): ?>
                            <div style="font-size:0.72rem; color:#64748B; margin-top:4px;">
                                Current: <a href="<?= htmlspecialchars($affiliation['member_directory']) ?>" target="_blank" style="color:#107C41;">View current spreadsheet roster</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Submit Corrected Documents to Secretariat
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
