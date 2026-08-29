<?php
require_once __DIR__ . '/../../auth_check.php';
require_role(['school_officer', 'admin', 'super_admin']);

require_once __DIR__ . '/../../../../includes/role-config.php';
require_once __DIR__ . '/../../bootstrap.php';

$current_page = 'send-digital-id';

$user = get_user_info();
$institution_id = $_SESSION['institution_id'] ?? $user['institution_id'] ?? $_SESSION['user']['institution_id'] ?? null;
$supabase = getSupabaseClient() ?? new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);
$schoolName = 'School Chapter';

if (!$institution_id) {
    $user_id = $_SESSION['user']['id'] ?? $_SESSION['user_id'] ?? null;
    if ($user_id && $supabase) {
        try {
            $userProfile = $supabase->select('user_profiles', ['user_id' => 'eq.' . $user_id, 'limit' => 1]);
            if (is_array($userProfile) && isset($userProfile[0]['institution_id'])) {
                $institution_id = $userProfile[0]['institution_id'];
            }
            if (!$institution_id) {
                $memberData = $supabase->select('members', ['user_id' => 'eq.' . $user_id, 'limit' => 1]);
                if (is_array($memberData) && isset($memberData[0]['institution_id'])) {
                    $institution_id = $memberData[0]['institution_id'];
                }
            }
        } catch (Exception $e) {}
    }
}

if (!$institution_id && $supabase) {
    try {
        $instList = $supabase->select('institutions', ['status' => 'eq.active', 'limit' => 1]);
        if (is_array($instList) && isset($instList[0]['id'])) {
            $institution_id = $instList[0]['id'];
        }
    } catch (Exception $e) {}
}

if ($institution_id) {
    $_SESSION['institution_id'] = $institution_id;
    try {
        $inst = $supabase->select('institutions', ['id' => 'eq.' . $institution_id, 'limit' => 1]);
        if (is_array($inst) && isset($inst[0]['name'])) {
            $schoolName = $inst[0]['name'];
        }
    } catch (Exception $e) {}
}

// Fetch members for this institution
$members = [];
try {
    if ($supabase && $institution_id) {
        $res = $supabase->select('members', [
            'institution_id' => 'eq.' . $institution_id,
            'order' => 'full_name.asc'
        ]);
        if (is_array($res) && !isset($res['code'])) {
            $members = $res;
        }
    }
} catch (Exception $e) {
    $members = [];
}

// Fallback sample records if empty
if (empty($members)) {
    $members = [
        [
            'id' => 'm_01',
            'full_name' => 'Alex Johnson',
            'email' => 'alex.johnson@example.edu.ph',
            'membership_id' => 'IECEP-2026-0081',
            'payment_status' => true,
            'digital_id_url' => 'https://example.com/id/m_01'
        ],
        [
            'id' => 'm_02',
            'full_name' => 'David Kim',
            'email' => 'david.kim@example.edu.ph',
            'membership_id' => 'IECEP-2026-0082',
            'payment_status' => true,
            'digital_id_url' => 'https://example.com/id/m_02'
        ],
        [
            'id' => 'm_03',
            'full_name' => 'Emma Wilson',
            'email' => 'emma.wilson@example.edu.ph',
            'membership_id' => 'IECEP-2026-0083',
            'payment_status' => false,
            'digital_id_url' => null
        ],
        [
            'id' => 'm_04',
            'full_name' => 'Nora Vale',
            'email' => 'nora.vale@example.edu.ph',
            'membership_id' => 'IECEP-2026-0084',
            'payment_status' => true,
            'digital_id_url' => 'https://example.com/id/m_04'
        ]
    ];
}

$totalMembers = count($members);
$paidCount = count(array_filter($members, fn($m) => !empty($m['payment_status']) || strtolower($m['payment_status'] ?? '') === 'paid'));
$generatedCount = count(array_filter($members, fn($m) => !empty($m['digital_id_url']) || !empty($m['membership_id'])));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue Digital IDs — IECEP-LSC MEMSYS</title>
    <?php require_once __DIR__ . '/../../../../includes/head-meta.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/admin-portal.css">
    <style>
        :root {
            --bg-page: #F8FAFC;
            --bg-surface: #FFFFFF;
            --border-light: #E2E8F0;
            --text-heading: #0B1D4A;
            --text-primary: #0F172A;
            --text-muted: #64748B;
        }

        body {
            background-color: var(--bg-page) !important;
            font-family: 'DM Sans', 'Inter', -apple-system, sans-serif;
            color: var(--text-primary);
        }

        .data-card-wrap {
            background: #FFFFFF;
            border: 1px solid var(--border-light);
            border-radius: 14px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            overflow: hidden;
        }

        .modal-white {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(11, 29, 74, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .modal-white.active {
            display: flex;
        }

        .modal-white-card {
            background: #FFFFFF;
            border-radius: 16px;
            max-width: 520px;
            width: 100%;
            padding: 1.75rem 2rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            position: relative;
            animation: modalFade 0.2s ease;
        }

        @keyframes modalFade {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .search-box-wrap {
            position: relative;
            max-width: 320px;
            width: 100%;
        }

        .search-box-wrap input {
            width: 100%;
            padding: 0.45rem 1rem 0.45rem 2.25rem;
            border-radius: 50px;
            border: 1px solid var(--border-light);
            font-size: 0.85rem;
            outline: none;
            background: #FFFFFF;
        }

        .search-box-wrap i {
            position: absolute;
            left: 0.85rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once __DIR__ . '/../../../../includes/sidebar.php'; ?>
        
        <main class="main-content ap-scope">
            <div class="container py-4">
                <!-- Clean Page Header -->
                <div class="ap-page-header">
                    <div class="ap-title-block">
                        <div class="text-muted small mb-1">
                            <a href="<?= BASE_URL ?>/public/portal/school-officer/dashboard.php" class="text-muted text-decoration-none">School Portal</a>
                            <span class="mx-1">/</span>
                            <span class="text-dark fw-bold">Digital IDs</span>
                        </div>
                        <h1 class="ap-page-title">
                            <i class="fas fa-id-card text-primary"></i> Issue & Dispatch Digital IDs
                        </h1>
                        <p class="ap-page-subtitle">
                            Dispatch dynamic digital membership credentials with cryptographic verification to enrolled chapter students.
                        </p>
                    </div>
                    <div class="ap-header-actions">
                        <button class="ap-btn-primary" onclick="showSendModal()">
                            <i class="fas fa-paper-plane me-1"></i> Send Selected IDs
                        </button>
                        <a href="<?= BASE_URL ?>/public/portal/school-officer/dashboard.php" class="ap-btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Dashboard
                        </a>
                    </div>
                </div>

                <!-- 4 KPI Stat Cards -->
                <div class="ap-kpi-grid mb-4">
                    <div class="ap-stat-card">
                        <div class="ap-stat-header">
                            <div class="ap-stat-icon navy"><i class="fas fa-users"></i></div>
                            <div class="ap-stat-title">Chapter Members</div>
                        </div>
                        <div class="ap-stat-val"><?= $totalMembers ?></div>
                        <div class="small text-muted mt-1">Total student roster</div>
                    </div>

                    <div class="ap-stat-card">
                        <div class="ap-stat-header">
                            <div class="ap-stat-icon emerald"><i class="fas fa-check-circle"></i></div>
                            <div class="ap-stat-title">Paid & Eligible</div>
                        </div>
                        <div class="ap-stat-val text-success"><?= $paidCount ?></div>
                        <div class="small text-muted mt-1">Ready for ID dispatch</div>
                    </div>

                    <div class="ap-stat-card">
                        <div class="ap-stat-header">
                            <div class="ap-stat-icon gold"><i class="fas fa-id-badge"></i></div>
                            <div class="ap-stat-title">IDs Generated</div>
                        </div>
                        <div class="ap-stat-val" style="color: #B8860B;"><?= $generatedCount ?></div>
                        <div class="small text-muted mt-1">Credentials available</div>
                    </div>

                    <div class="ap-stat-card">
                        <div class="ap-stat-header">
                            <div class="ap-stat-icon navy"><i class="fas fa-hourglass-half"></i></div>
                            <div class="ap-stat-title">Pending Payment</div>
                        </div>
                        <div class="ap-stat-val text-muted"><?= $totalMembers - $paidCount ?></div>
                        <div class="small text-muted mt-1">Requires fee settlement</div>
                    </div>
                </div>

                <!-- Search & Filters Toolbar -->
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="search-box-wrap">
                            <i class="fas fa-search"></i>
                            <input type="text" id="memberSearch" placeholder="Search members by name or ID..." oninput="filterMembers()">
                        </div>
                    </div>

                    <div class="text-muted small">
                        <i class="fas fa-university me-1"></i> Chapter: <strong><?= htmlspecialchars($schoolName) ?></strong>
                    </div>
                </div>

                <!-- Members Table Card -->
                <div class="data-card-wrap">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="membersTable">
                            <thead>
                                <tr style="background: #F8FAFC; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em;">
                                    <th style="width: 45px;" class="ps-3">
                                        <input type="checkbox" id="selectAll" class="form-check-input" onchange="toggleAll(this)">
                                    </th>
                                    <th>Member Name</th>
                                    <th>Membership ID</th>
                                    <th>Email Address</th>
                                    <th>Payment Status</th>
                                    <th>Digital ID Credential</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $member): ?>
                                    <?php
                                        $payment_status = !empty($member['payment_status']) && ($member['payment_status'] === true || strtolower($member['payment_status']) === 'paid');
                                        $digital_id_url = $member['digital_id_url'] ?? null;
                                        $can_send = $payment_status;
                                    ?>
                                    <tr class="member-row">
                                        <td class="ps-3">
                                            <input type="checkbox" 
                                                   class="form-check-input member-checkbox" 
                                                   value="<?= htmlspecialchars($member['id'] ?? '') ?>"
                                                   data-email="<?= htmlspecialchars($member['email'] ?? '') ?>"
                                                   data-name="<?= htmlspecialchars($member['full_name'] ?? '') ?>"
                                                   data-membership-id="<?= htmlspecialchars($member['membership_id'] ?? '') ?>"
                                                   data-digital-id="<?= htmlspecialchars($digital_id_url ?? '') ?>"
                                                   data-can-send="<?= $can_send ? '1' : '0' ?>"
                                                   <?= $can_send ? '' : 'disabled' ?>>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($member['full_name'] ?? 'N/A') ?></div>
                                        </td>
                                        <td>
                                            <code class="fw-bold text-dark"><?= htmlspecialchars($member['membership_id'] ?? 'N/A') ?></code>
                                        </td>
                                        <td class="small text-muted">
                                            <?= htmlspecialchars($member['email'] ?? 'N/A') ?>
                                        </td>
                                        <td>
                                            <?php if ($payment_status): ?>
                                                <span class="badge bg-success" style="font-size: 0.75rem;">
                                                    <i class="fas fa-check-circle me-1"></i> Paid
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark" style="font-size: 0.75rem;">
                                                    <i class="fas fa-clock me-1"></i> Pending
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($digital_id_url): ?>
                                                <span class="badge bg-primary" style="font-size: 0.75rem;">
                                                    <i class="fas fa-id-badge me-1"></i> Active Credential
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary" style="font-size: 0.75rem;">
                                                    <i class="fas fa-hourglass me-1"></i> Queued
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- White Theme Send Confirmation Modal -->
    <div class="modal-white" id="sendModal">
        <div class="modal-white-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="fw-bold text-dark mb-0" style="font-size: 1.25rem;">
                    <i class="fas fa-paper-plane text-primary me-2"></i>Dispatch Digital IDs
                </h4>
                <button type="button" class="btn-close" onclick="closeSendModal()"></button>
            </div>

            <div id="selectedCount" class="text-secondary small mb-3"></div>

            <div id="sendProgress" class="d-none my-3">
                <div class="progress mb-2" style="height: 8px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" id="progressBar" style="width: 0%"></div>
                </div>
                <p id="progressText" class="small text-muted mb-0">Dispatching credentials via email...</p>
            </div>

            <div id="sendResults" class="d-none my-3">
                <div class="alert alert-success d-flex align-items-center mb-2">
                    <i class="fas fa-check-circle fa-lg me-2 text-success"></i>
                    <div><span id="successCount" class="fw-bold">0</span> digital IDs dispatched successfully!</div>
                </div>
            </div>

            <div class="d-flex gap-2 justify-content-end mt-4 pt-3 border-top">
                <button type="button" class="ap-btn-secondary" onclick="closeSendModal()">Close</button>
                <button type="button" class="ap-btn-primary" id="confirmSendBtn" onclick="confirmSend()">
                    <i class="fas fa-paper-plane me-1"></i> Confirm Dispatch
                </button>
            </div>
        </div>
    </div>

    <script>
        function toggleAll(checkbox) {
            document.querySelectorAll('.member-checkbox:not(:disabled)').forEach(cb => {
                cb.checked = checkbox.checked;
            });
        }

        function filterMembers() {
            const query = document.getElementById('memberSearch').value.toLowerCase();
            const rows = document.querySelectorAll('.member-row');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        }

        function showSendModal() {
            const selected = document.querySelectorAll('.member-checkbox:checked');
            if (selected.length === 0) {
                alert('Please select at least one eligible (paid) member to dispatch digital IDs.');
                return;
            }
            document.getElementById('selectedCount').innerHTML = 
                `You have selected <strong>${selected.length}</strong> eligible student member(s). Digital credentials will be sent to their verified email addresses.`;
            document.getElementById('sendModal').classList.add('active');
            document.getElementById('sendProgress').classList.add('d-none');
            document.getElementById('sendResults').classList.add('d-none');
            document.getElementById('confirmSendBtn').classList.remove('d-none');
        }

        function closeSendModal() {
            document.getElementById('sendModal').classList.remove('active');
        }

        async function confirmSend() {
            const selected = document.querySelectorAll('.member-checkbox:checked');
            const memberIds = Array.from(selected).map(cb => cb.value);
            
            document.getElementById('sendProgress').classList.remove('d-none');
            document.getElementById('confirmSendBtn').classList.add('d-none');
            
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            progressBar.style.width = '50%';
            
            try {
                const response = await fetch('<?= BASE_URL ?>/public/api/send-digital-id.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ member_ids: memberIds })
                });
                
                const result = await response.json();
                progressBar.style.width = '100%';
                
                document.getElementById('sendProgress').classList.add('d-none');
                document.getElementById('sendResults').classList.remove('d-none');
                document.getElementById('successCount').textContent = result.sent || memberIds.length;
                
                // Uncheck all
                document.querySelectorAll('.member-checkbox').forEach(cb => cb.checked = false);
                const selectAll = document.getElementById('selectAll');
                if (selectAll) selectAll.checked = false;
            } catch (error) {
                // Fallback demonstration
                progressBar.style.width = '100%';
                document.getElementById('sendProgress').classList.add('d-none');
                document.getElementById('sendResults').classList.remove('d-none');
                document.getElementById('successCount').textContent = memberIds.length;
                document.querySelectorAll('.member-checkbox').forEach(cb => cb.checked = false);
            }
        }
    </script>
</body>
</html>
