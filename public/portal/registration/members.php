<?php
require_once __DIR__ . '/../bootstrap.php';
$current_page = basename(__FILE__, '.php');
/**
 * Registration Members - Batch Processing Panel for Registration Committee
 */

require_once __DIR__ . '/../auth_check.php';
require_role(['registration', 'committee_registration', 'admin', 'super_admin']);

require_once __DIR__ . '/../../../includes/paths.php';

$user = get_user_info();
$currentRole = $_SESSION['user']['role'] ?? '';
$selectedBatchId = trim($_GET['batch_id'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Member Batch - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/font-awesome.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/dashboard.css">
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>
        
        <main class="main-content main-content-with-sidebar">
            <header class="dashboard-header">
                <h1>Confirm &amp; Process Member Batch</h1>
                <p>Use this panel to convert a pending member upload batch into membership accounts.</p>
            </header>
            
            <div class="dashboard-content">
                <div class="card">
                    <div class="card-body" style="padding: 40px;">
                        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;justify-content:center;">
                            <i class="fas fa-file-upload" style="font-size: 3rem; color: var(--accent);"></i>
                            <div style="max-width:720px;text-align:left;">
                                <h3>Batch Processing</h3>
                                <p style="color: var(--neutral-600);">Enter the batch ID of a pending member upload. The system will create accounts, insert member records, record blockchain entries, and send email notifications.</p>
                            </div>
                        </div>

                        <form id="batch-process-form" style="margin-top: 28px; text-align: left; max-width: 720px; margin-left:auto; margin-right:auto;">
                            <label for="batchId" style="display:block;font-weight:600;margin-bottom:8px;color:#0c2461;">Batch ID</label>
                            <input id="batchId" name="batch_id" type="text" value="<?= htmlspecialchars($selectedBatchId, ENT_QUOTES) ?>" placeholder="Enter member batch ID" style="width:100%;max-width:420px;padding:14px 16px;border:1px solid #d1d5db;border-radius:14px;font-size:1rem;">

                            <?php if (in_array($currentRole, ['committee_registration', 'admin'], true)): ?>
                                <button type="button" id="processBatchButton" class="btn btn-primary" style="margin-top: 20px;">
                                    <i class="fas fa-check-circle"></i> Confirm &amp; Process Batch
                                </button>
                            <?php else: ?>
                                <div style="margin-top:20px;padding:18px;background:#eef2ff;border-radius:16px;color:#1e40af;max-width:420px;">
                                    Only <strong>Committee Registration</strong> or <strong>Admin</strong> users may process member batches.
                                </div>
                            <?php endif; ?>

                            <div id="batchSummary" style="display:none;margin-top:24px;"></div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const processButton = document.getElementById('processBatchButton');
            const batchIdInput = document.getElementById('batchId');
            const summaryPanel = document.getElementById('batchSummary');

            if (!processButton) {
                return;
            }

            processButton.addEventListener('click', async function () {
                const batchId = batchIdInput.value.trim();
                if (!batchId) {
                    alert('Please enter a batch ID before processing.');
                    batchIdInput.focus();
                    return;
                }

                if (!confirm('This will create accounts and send emails. Proceed?')) {
                    return;
                }

                processButton.disabled = true;
                processButton.textContent = 'Processing…';
                summaryPanel.style.display = 'none';
                summaryPanel.innerHTML = '';

                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    const response = await fetch('<?= BASE_URL ?>/public/api/process-member-batch.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({ batch_id: batchId })
                    });

                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error(data.error || 'Unable to process the batch.');
                    }

                    summaryPanel.innerHTML = renderSummary(data.summary || {});
                    summaryPanel.style.display = 'block';
                } catch (error) {
                    summaryPanel.innerHTML = '<div style="padding:18px;background:#fee2e2;border-radius:16px;color:#991b1b;">' + escapeHtml(error.message) + '</div>';
                    summaryPanel.style.display = 'block';
                } finally {
                    processButton.disabled = false;
                    processButton.textContent = 'Confirm & Process Batch';
                }
            });

            function renderSummary(summary) {
                const errors = Array.isArray(summary.errors) ? summary.errors : [];
                return `
                    <div style="padding:24px;background:#ffffff;border-radius:18px;box-shadow:0 18px 40px rgba(15,23,42,0.08);">
                        <h3 style="margin-top:0;color:#0f172a;">Batch Processing Summary</h3>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-top:20px;">
                            <div style="background:#f8fafc;border:1px solid #e2e8f0;padding:18px;border-radius:16px;">
                                <strong>Total Rows</strong>
                                <div style="font-size:1.8rem;margin-top:10px;color:#0b2447;">${summary.total_members ?? 0}</div>
                            </div>
                            <div style="background:#ecfdf5;border:1px solid #d1fae5;padding:18px;border-radius:16px;">
                                <strong>New Accounts</strong>
                                <div style="font-size:1.8rem;margin-top:10px;color:#166534;">${summary.new_accounts_created ?? 0}</div>
                            </div>
                            <div style="background:#f0f9ff;border:1px solid #bae6fd;padding:18px;border-radius:16px;">
                                <strong>Renewals</strong>
                                <div style="font-size:1.8rem;margin-top:10px;color:#0369a1;">${summary.renewed ?? 0}</div>
                            </div>
                            <div style="background:#fff7ed;border:1px solid #ffedd5;padding:18px;border-radius:16px;">
                                <strong>Skipped</strong>
                                <div style="font-size:1.8rem;margin-top:10px;color:#b45309;">${summary.skipped ?? 0}</div>
                            </div>
                        </div>
                        ${errors.length ? `<div style="margin-top:24px;padding:18px;background:#fff1f2;border-radius:16px;color:#b91c1c;"><strong>Errors:</strong><ul style="margin:12px 0 0 18px;">${errors.map(e => `<li>${escapeHtml(e)}</li>`).join('')}</ul></div>` : ''}
                    </div>
                `;
            }

            function escapeHtml(value) {
                return value
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }
        });
    </script>
</body>
</html>

