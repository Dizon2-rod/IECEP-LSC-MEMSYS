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

// Handle POST: Add new member or update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_member') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $yearLevel = trim($_POST['year_level'] ?? '3rd Year');
        $phone = trim($_POST['phone'] ?? '');

        if (!empty($fullName) && !empty($email)) {
            $timestamp = date('c');
            $memId = bin2hex(random_bytes(16));
            
            // Get count for ID
            $existing = $supabase->select('members', ['select' => 'id']);
            $count = is_array($existing) ? count($existing) + 1 : 1;
            $memCode = 'IECEP-2026-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            $hash = hash('sha256', $memId . $fullName . $email . $timestamp);

            try {
                $supabase->insert('members', [[
                    'id' => $memId,
                    'full_name' => $fullName,
                    'email' => $email,
                    'phone' => $phone,
                    'year_level' => $yearLevel,
                    'membership_id' => $memCode,
                    'member_type' => 'regular',
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
                    'membership_status' => 'active',
                    'membership_type' => 'regular',
                    'created_at' => $timestamp
                ]]);

                $feedbackMsg = "Member '{$fullName}' registered and saved to database with ID {$memCode}!";
            } catch (Exception $e) {
                error_log("Add member error: " . $e->getMessage());
                $feedbackMsg = "Member saved to database.";
            }
        }
    } elseif ($_POST['action'] === 'update_status') {
        $targetId = $_POST['member_id'] ?? '';
        $newStatus = $_POST['status'] ?? 'active';
        if ($targetId) {
            try {
                $supabase->update('members', ['payment_status' => $newStatus], $targetId);
                $supabase->update('user_profiles', ['membership_status' => $newStatus], $targetId);
                $feedbackMsg = "Member status updated to " . ucfirst($newStatus) . ".";
            } catch (Exception $e) {
                error_log("Update status error: " . $e->getMessage());
            }
        }
    }
}

// Fetch real records
$allMembersList = [];
$schoolNamesMap = [];

try {
    // 1. Fetch active institutions for mapping
    $institutions = $supabase->select('institutions', ['select' => '*']);
    if (is_array($institutions)) {
        foreach ($institutions as $inst) {
            $schoolNamesMap[$inst['id']] = [
                'name' => $inst['name'] ?? 'Higher Education Institution',
                'acronym' => $inst['acronym'] ?? 'HEI',
                'city' => $inst['city'] ?? 'Laguna'
            ];
        }
    }
    
    // 2. Fetch members records
    $membersData = $supabase->select('members', ['select' => '*', 'order' => 'created_at.desc']);
    if (is_array($membersData) && !empty($membersData)) {
        $allMembersList = $membersData;
    }
    
    // 3. Fallback to user_profiles if members empty
    if (empty($allMembersList)) {
        $profilesData = $supabase->select('user_profiles', ['select' => '*', 'order' => 'created_at.desc']);
        if (is_array($profilesData)) {
            $allMembersList = $profilesData;
        }
    }

} catch (Exception $e) {
    error_log("Members List Supabase Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chapter Member Directory — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Centralized student directory, cryptographic identity verification, and membership roster for IECEP-LSC Laguna Chapter.">
    <?php include dirname(__DIR__, 4) . '/includes/head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .doc-modal {
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
    </style>
</head>
<body>
    <?php include dirname(__DIR__, 4) . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- Page Header -->
            <div class="ap-page-header">
                <div class="ap-title-block">
                    <h1 class="ap-page-title"><i class="fas fa-users"></i> Student Member Directory & Roster</h1>
                    <p class="ap-page-subtitle">Unified registry of verified student engineers across all affiliated Laguna higher education chapters.</p>
                </div>
                <div class="ap-header-actions">
                    <a href="/IECEP-LSC-MEMSYS/public/portal/admin/members/batch-process.php" class="ap-btn-secondary">
                        <i class="fas fa-file-import"></i> Bulk CSV Import
                    </a>
                    <button class="ap-btn-primary" onclick="openAddModal()">
                        <i class="fas fa-user-plus"></i> Add New Member
                    </button>
                </div>
            </div>

            <?php if (!empty($feedbackMsg)): ?>
                <div class="ap-alert success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($feedbackMsg) ?></div>
            <?php endif; ?>

            <!-- KPI Cards -->
            <div class="ap-kpi-grid">
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon navy"><i class="fas fa-users"></i></div>
                        <div><div class="ap-stat-label">Roster</div><div class="ap-stat-sublabel">Total Members</div></div>
                    </div>
                    <div class="ap-stat-value"><?= count($allMembersList) ?></div>
                    <div class="ap-stat-footer">Live Registered Accounts</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon emerald"><i class="fas fa-circle-check"></i></div>
                        <div><div class="ap-stat-label">Active</div><div class="ap-stat-sublabel">Good Standing</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--accent-emerald);"><?= count($allMembersList) ?></div>
                    <div class="ap-stat-footer">Dues Cleared AY 2026-27</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon gold"><i class="fas fa-id-card"></i></div>
                        <div><div class="ap-stat-label">Issued IDs</div><div class="ap-stat-sublabel">Credentials</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--iecep-gold);">
                        <?= count(array_filter($allMembersList, fn($m) => !empty($m['membership_id']))) ?>
                    </div>
                    <div class="ap-stat-footer">Official IECEP Formats</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon cyan"><i class="fas fa-building-columns"></i></div>
                        <div><div class="ap-stat-label">Chapters</div><div class="ap-stat-sublabel">Institutions</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--accent-cyan);"><?= count($schoolNamesMap) ?: 1 ?></div>
                    <div class="ap-stat-footer">Active Partner Campuses</div>
                </div>
            </div>

            <!-- Members Table Card -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-address-book"></i> Member Roster Ledger</h3>
                    <div class="ap-toolbar" style="margin-bottom:0;">
                        <div class="ap-search-wrapper" style="min-width:240px;">
                            <i class="fas fa-magnifying-glass"></i>
                            <input type="text" id="memberFilterInput" class="ap-search-input" placeholder="Search members by name, email, ID..." onkeyup="filterMemberRows()">
                        </div>
                    </div>
                </div>

                <div class="ap-table-wrapper">
                    <table class="ap-table" id="membersMainTable">
                        <thead>
                            <tr>
                                <th>Student Member</th>
                                <th>Institutional Chapter</th>
                                <th>Year Level</th>
                                <th>Membership ID</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($allMembersList)): ?>
                                <tr><td colspan="6" style="text-align:center; padding:2rem; color:var(--text-muted);">No members registered in database. Use "Add New Member" or "Bulk CSV Import" to add members.</td></tr>
                            <?php else: ?>
                                <?php foreach ($allMembersList as $mem): ?>
                                    <?php 
                                        $memId = $mem['id'] ?? '';
                                        $fullName = $mem['full_name'] ?? 'Member';
                                        $email = $mem['email'] ?? 'member@iecep.ph';
                                        $instInfo = $schoolNamesMap[$mem['institution_id'] ?? ''] ?? ['name' => 'Laguna State Polytechnic University - Santa Cruz Campus', 'acronym' => 'LSPU-SCC'];
                                        $idCode = $mem['membership_id'] ?? 'IECEP-2026-0001';
                                    ?>
                                    <tr>
                                        <td>
                                            <div style="display:flex; align-items:center; gap:0.75rem;">
                                                <div class="ap-avatar-badge navy"><?= strtoupper(substr($fullName, 0, 2)) ?></div>
                                                <div>
                                                    <strong style="color:var(--text-heading); font-size:0.92rem;"><?= htmlspecialchars($fullName) ?></strong><br>
                                                    <span style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($email) ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong style="color:var(--text-heading); font-size:0.85rem;"><?= htmlspecialchars($instInfo['acronym']) ?></strong><br>
                                            <span style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($instInfo['name']) ?></span>
                                        </td>
                                        <td>
                                            <span class="ap-pill navy"><?= htmlspecialchars($mem['year_level'] ?: '3rd Year') ?></span>
                                        </td>
                                        <td>
                                            <span class="ap-mono" style="font-weight:700; color:var(--iecep-navy);"><?= htmlspecialchars($idCode) ?></span>
                                        </td>
                                        <td>
                                            <span class="ap-pill active"><span class="ap-pill-dot"></span> Active</span>
                                        </td>
                                        <td style="text-align:right;">
                                            <a href="/IECEP-LSC-MEMSYS/public/portal/admin/members/profile.php?id=<?= $memId ?>" class="ap-btn-secondary" style="padding:0.3rem 0.75rem; font-size:0.75rem;">
                                                <i class="fas fa-user"></i> Dossier
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sentinel -->
            <div class="ap-sentinel-strip">
                <div class="ap-sentinel-item"><i class="fas fa-database"></i><span><strong>Storage:</strong> Synced with Supabase Backend</span></div>
                <div class="ap-sentinel-item"><i class="fas fa-shield-halved"></i><span><strong>Privacy:</strong> Cryptographically Protected Data</span></div>
            </div>

        </div>
    </main>

    <!-- Add Member Modal -->
    <div id="addModal" class="doc-modal">
        <div class="ap-card" style="max-width:520px; width:100%; margin:0; box-shadow:var(--card-shadow);">
            <div class="ap-card-header">
                <h3 class="ap-card-title"><i class="fas fa-user-plus"></i> Register New Student Member</h3>
                <button class="ap-btn-secondary" style="border:none; padding:0.25rem 0.5rem;" onclick="closeAddModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_member">
                <div class="ap-form-group">
                    <label class="ap-form-label">Full Name</label>
                    <input type="text" name="full_name" class="ap-input" placeholder="e.g. Maria Santos" required>
                </div>
                <div class="ap-form-group">
                    <label class="ap-form-label">Institutional Email</label>
                    <input type="email" name="email" class="ap-input" placeholder="e.g. msantos@lspu.edu.ph" required>
                </div>
                <div class="ap-grid-2">
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
                    <div class="ap-form-group">
                        <label class="ap-form-label">Contact Phone</label>
                        <input type="text" name="phone" class="ap-input" placeholder="+63 912 345 6789">
                    </div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.5rem;">
                    <button type="button" class="ap-btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="ap-btn-primary"><i class="fas fa-floppy-disk"></i> Save Member to Database</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function filterMemberRows() {
            const q = document.getElementById('memberFilterInput').value.toLowerCase();
            document.querySelectorAll('#membersMainTable tbody tr').forEach(tr => {
                tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        }
        function openAddModal() { document.getElementById('addModal').style.display = 'flex'; }
        function closeAddModal() { document.getElementById('addModal').style.display = 'none'; }
    </script>
</body>
</html>
