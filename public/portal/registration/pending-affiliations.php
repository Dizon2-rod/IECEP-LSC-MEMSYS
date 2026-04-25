<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../../../src/config/config.php';
require_role(['committee_registration', 'eb_vp_internal']);

$user = get_user_info();

require_once __DIR__ . '/../../../src/lib/SupabaseClient.php';
$config = require __DIR__ . '/../../../src/config/supabase.php';
$supabase = new SupabaseClient($config['url'], $config['anon_key']);

$successMessage = '';
$errorMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_notes'])) {
    $applicationId = $_POST['application_id'] ?? '';
    $reviewNotes = trim($_POST['review_notes'] ?? '');

    if (empty($applicationId)) {
        $errorMessage = 'Application ID is required to save notes.';
    } else {
        try {
            $existing = $supabase->select('pending_affiliations', ['id' => 'eq.' . $applicationId]);
            if (empty($existing) || !is_array($existing)) {
                $errorMessage = 'Application not found.';
            } else {
                $applicationData = $existing[0];
                $documents = [];
                if (!empty($applicationData['documents'])) {
                    $documents = json_decode($applicationData['documents'], true) ?: [];
                }
                $documents['review_notes'] = $reviewNotes;

                $updateResult = $supabase->update('pending_affiliations', ['documents' => json_encode($documents)], $applicationId);
                if ($updateResult) {
                    header('Location: pending-affiliations.php?notes_saved=1');
                    exit;
                }
                $errorMessage = 'Unable to save notes at this time.';
            }
        } catch (Exception $e) {
            $errorMessage = 'Error saving notes: ' . $e->getMessage();
        }
    }
}

try {
    $applications = $supabase->select('pending_affiliations', [
        'status' => 'eq.pending_review',
        'order' => 'submitted_at.desc'
    ]);
} catch (Exception $e) {
    $applications = [];
    $loadError = $e->getMessage();
}

if (isset($_GET['notes_saved'])) {
    $successMessage = 'Review notes saved successfully.';
}

function formatDateTime($dateStr) {
    if (empty($dateStr)) {
        return 'N/A';
    }
    $date = new DateTime($dateStr);
    return $date->format('F j, Y \a\t g:i A');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Affiliations - Registration Committee</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/dashboard.css">
    <style>
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 24px;
        }
        .content-header h1 {
            font-size: 2rem;
            margin: 0;
        }
        .applications-list {
            display: grid;
            gap: 20px;
        }
        .application-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
            padding: 24px;
        }
        .application-card header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 20px;
        }
        .application-title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .status-badge.pending_review {
            background: #e0f2fe;
            color: #0c4a6e;
        }
        .application-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
        }
        .application-meta p {
            margin: 0;
            color: #475569;
            font-size: 0.95rem;
        }
        .application-meta strong {
            color: #0f172a;
        }
        .documents-list {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 10px;
        }
        .documents-list li {
            padding: 14px 16px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            font-size: 0.95rem;
        }
        .empty-state {
            text-align: center;
            padding: 60px 24px;
            border: 2px dashed #cbd5e1;
            border-radius: 24px;
            background: #f8fafc;
        }
        .empty-state h2 {
            margin-bottom: 16px;
            font-size: 1.5rem;
            color: #0f172a;
        }
        .empty-state p {
            margin: 0;
            color: #475569;
            font-size: 1rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../sidebar_registration.php'; ?>
        <main class="main-content">
            <header class="dashboard-header">
                <div class="header-content">
                    <div>
                        <h1>Pending Affiliations</h1>
                        <p class="welcome-message">Review all affiliation requests submitted to the Registration Committee.</p>
                    </div>
                </div>
            </header>

            <div class="dashboard-content">
                <?php if (!empty($successMessage)): ?>
                    <div class="application-card" style="border-color:#22c55e; background:#ecfdf5;">
                        <p style="color:#166534;font-weight:700;"><?php echo htmlspecialchars($successMessage); ?></p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($errorMessage)): ?>
                    <div class="application-card" style="border-color:#f87171; background:#fef2f2;">
                        <p style="color:#b91c1c;font-weight:700;"><?php echo htmlspecialchars($errorMessage); ?></p>
                    </div>
                <?php endif; ?>
                <?php if (!empty($loadError)): ?>
                    <div class="application-card">
                        <p style="color:#b91c1c;font-weight:700;">Failed to load affiliation requests.</p>
                        <p><?php echo htmlspecialchars($loadError); ?></p>
                    </div>
                <?php endif; ?>

                <?php if (empty($applications)): ?>
                    <div class="empty-state">
                        <div class="icon" style="font-size: 2.8rem; margin-bottom: 18px;">📬</div>
                        <h2>Walang pending affiliation request</h2>
                        <p>Kapag may nag-submit ng application, lalabas ang request dito para ma-review ng Registration Committee.</p>
                    </div>
                <?php else: ?>
                    <div class="applications-list">
                        <?php foreach ($applications as $application): ?>
                            <?php
                                $documents = [];
                                if (!empty($application['documents'])) {
                                    $documents = json_decode($application['documents'], true);
                                }
                                $reviewNotes = '';
                                if (isset($documents['review_notes'])) {
                                    $reviewNotes = $documents['review_notes'];
                                    unset($documents['review_notes']);
                                }
                                $documentFiles = is_array($documents) ? array_filter($documents, function($value, $key) {
                                    return $key !== 'review_notes';
                                }, ARRAY_FILTER_USE_BOTH) : [];
                                $documentCount = count($documentFiles);
                                $submittedAt = formatDateTime($application['submitted_at'] ?? $application['created_at'] ?? '');
                            ?>
                            <article class="application-card">
                                <header>
                                    <div>
                                        <h2 class="application-title"><?php echo htmlspecialchars($application['institution_name'] ?? 'Unnamed Institution'); ?></h2>
                                        <span class="application-date">Submitted: <?php echo htmlspecialchars($submittedAt); ?></span>
                                    </div>
                                    <span class="status-badge pending_review"><i class="fas fa-hourglass-half"></i> Pending Review</span>
                                </header>
                                <div class="application-meta">
                                    <p><strong>Institution:</strong> <?php echo htmlspecialchars($application['institution_name'] ?? 'N/A'); ?></p>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($application['email'] ?? 'N/A'); ?></p>
                                    <p><strong>Contact Person:</strong> <?php echo htmlspecialchars($application['contact_person'] ?? 'N/A'); ?></p>
                                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($application['contact_phone'] ?? 'N/A'); ?></p>
                                    <p><strong>Address:</strong> <?php echo htmlspecialchars($application['address'] ?? 'N/A'); ?></p>
                                    <p><strong>Document Count:</strong> <?php echo $documentCount; ?></p>
                                </div>

                                <?php if ($documentCount > 0): ?>
                                    <div style="margin-top:20px;">
                                        <h3 style="margin-bottom:12px; font-size:1rem; color:#0f172a;">Submitted Documents</h3>
                                        <ul class="documents-list">
                                            <?php foreach ($documentFiles as $docName => $docMeta): ?>
                                                <?php
                                                    $docLabel = htmlspecialchars($docMeta['name'] ?? $docName);
                                                    $docType = htmlspecialchars($docMeta['type'] ?? 'application/octet-stream');
                                                    $docContent = $docMeta['content'] ?? '';
                                                    $downloadHref = '';
                                                    if (!empty($docContent)) {
                                                        $downloadHref = 'data:' . $docType . ';base64,' . $docContent;
                                                    }
                                                ?>
                                                <li>
                                                    <span><?php echo $docLabel; ?></span>
                                                    <small style="color:#64748b;">(<?php echo $docType; ?>)</small>
                                                    <?php if ($downloadHref): ?>
                                                        <a href="<?php echo $downloadHref; ?>" download="<?php echo $docLabel; ?>" style="margin-left:12px; color:#0a58ca; text-decoration:none;">Download</a>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($documents['changes_instructions'])): ?>
                                    <div style="margin-top:20px; padding:16px; background:#fef3c7; border-left:4px solid #f59e0b; border-radius:8px;">
                                        <h3 style="margin: 0 0 12px 0; font-size:0.95rem; color:#92400e; font-weight:700;">
                                            <i class="fas fa-exclamation-triangle"></i> Changes Required
                                        </h3>
                                        <p style="margin: 0; color:#78350f; line-height: 1.6; white-space: pre-wrap;">
                                            <?php echo htmlspecialchars($documents['changes_instructions']); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>

                                <div style="margin-top:24px;">
                                    <form method="post">
                                        <input type="hidden" name="application_id" value="<?php echo htmlspecialchars($application['id']); ?>">
                                        <div style="margin-bottom:12px;">
                                            <label for="review_notes_<?php echo htmlspecialchars($application['id']); ?>" style="font-weight:700; color:#0f172a;">Review Notes</label>
                                            <textarea id="review_notes_<?php echo htmlspecialchars($application['id']); ?>" name="review_notes" rows="5" style="width:100%; border:1px solid #cbd5e1; border-radius:12px; padding:12px; font-family:Inter, sans-serif; font-size:0.95rem;"><?php echo htmlspecialchars($reviewNotes); ?></textarea>
                                        </div>
                                        <button type="submit" name="save_notes" value="1" style="background:#0A2F6C; color:white; border:none; padding:12px 18px; border-radius:10px; cursor:pointer;">Save Notes</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
