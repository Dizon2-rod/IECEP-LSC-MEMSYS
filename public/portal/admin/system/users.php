<?php
require_once __DIR__ . '/../../bootstrap.php';
$current_page = 'users';

require_once __DIR__ . '/../../auth_check.php';
require_role(['admin', 'super_admin']);

$pageTitle = 'Administrative Accounts & RBAC Console';
$supabase = getSupabaseClient();

$feedbackMsg = '';
$feedbackType = 'success';

// Handle POST: Create new user account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_user') {
        $name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? 'admin');
        $phone = trim($_POST['phone'] ?? '');

        if (!empty($name) && !empty($email)) {
            $timestamp = date('c');
            $userId = bin2hex(random_bytes(16));

            try {
                $supabase->insert('user_profiles', [[
                    'id' => $userId,
                    'user_id' => $userId,
                    'full_name' => $name,
                    'role' => $role,
                    'contact_phone' => $phone,
                    'membership_status' => 'active',
                    'membership_type' => 'regular',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp
                ]]);

                $feedbackMsg = "🎉 Account for '{$name}' created successfully!";
                $feedbackType = 'success';
            } catch (Exception $e) {
                error_log("Create user error: " . $e->getMessage());
                $feedbackMsg = "Error creating user: " . $e->getMessage();
                $feedbackType = 'warning';
            }
        }
    }
}

// Fetch real user accounts
$usersList = [];
try {
    $rawUsers = $supabase->select('user_profiles', ['select' => '*', 'order' => 'created_at.desc']);
    if (is_array($rawUsers)) {
        $usersList = $rawUsers;
    }
} catch (Exception $e) {
    error_log("Error loading users: " . $e->getMessage());
}

$adminCount = 0;
$officerCount = 0;
foreach ($usersList as $u) {
    $r = strtolower($u['role'] ?? 'member');
    if ($r === 'admin' || $r === 'super_admin') $adminCount++;
    else $officerCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Privileged user accounts, role-based access control, security policies, and administrative telemetry for IECEP-LSC.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-navy: #0B1D4A;
            --color-navy-hover: #152C6E;
            --color-blue: #2563EB;
            --color-gold: #D4AF37;
            --color-emerald: #059669;
            --color-amber: #D97706;
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
        }
        .btn-primary-navy:hover {
            background: var(--color-navy-hover);
            transform: translateY(-1px);
            color: #FDE047 !important;
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
        }

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
        .search-input-field {
            padding: 0.45rem 0.75rem 0.45rem 2rem;
            border: 1px solid #CBD5E1;
            border-radius: 7px;
            font-size: 0.8rem;
            outline: none;
            width: 100%;
            box-sizing: border-box;
            background: #F8FAFC;
        }

        .ap-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
            margin-bottom: 1rem;
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
        }
        .ap-table td {
            padding: 0.65rem 0.85rem;
            border-bottom: 1px solid #F1F5F9;
            color: #334155;
            vertical-align: middle;
        }

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
        }
        .doc-modal.active { display: flex; }
        .modal-inner-box {
            background: #FFFFFF;
            border-radius: 12px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.18);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        @media (max-width: 1024px) {
            .dash-kpi-grid { grid-template-columns: repeat(2, 1fr); }
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
                        <i class="fas fa-users-gear" style="color:var(--color-navy);"></i>
                        Administrative Accounts & RBAC Console
                    </h1>
                    <p class="dash-header-sub">
                        Manage privileged user accounts, role-based access control (RBAC), and security scopes.
                    </p>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <a href="<?= PORTAL_URL ?>/admin/system/settings.php" class="btn-white">
                        <i class="fas fa-gear" style="color:var(--color-blue);"></i> System Settings
                    </a>
                    <button type="button" class="btn-primary-navy" onclick="openUserModal()">
                        <i class="fas fa-user-plus" style="color:#FDE047;"></i> Add Account
                    </button>
                </div>
            </div>

            <?php if (!empty($feedbackMsg)): ?>
                <div class="ap-alert <?= $feedbackType ?>" style="margin-bottom:0.85rem;">
                    <i class="fas fa-check-circle" style="font-size:1.2rem;"></i> 
                    <div><?= htmlspecialchars($feedbackMsg) ?></div>
                </div>
            <?php endif; ?>

            <!-- 2. KPI Grid -->
            <div class="dash-kpi-grid">
                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill navy"><i class="fas fa-users"></i></div>
                    <div>
                        <div class="kpi-val"><?= count($usersList) ?></div>
                        <div class="kpi-lbl">Total Registered Accounts</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-user-shield"></i></div>
                    <div>
                        <div class="kpi-val"><?= $adminCount ?></div>
                        <div class="kpi-lbl">System Administrators</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-id-badge"></i></div>
                    <div>
                        <div class="kpi-val"><?= $officerCount ?></div>
                        <div class="kpi-lbl">School Chapter Officers</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill amber"><i class="fas fa-shield-check"></i></div>
                    <div>
                        <div class="kpi-val">Active</div>
                        <div class="kpi-lbl">RBAC Enforcement</div>
                    </div>
                </div>
            </div>

            <!-- 3. Search & Filter Bar -->
            <div class="white-controls-card">
                <div style="position:relative; flex:1; max-width:380px;">
                    <i class="fas fa-search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#94A3B8; font-size:0.8rem;"></i>
                    <input type="text" id="usrSearchInput" class="search-input-field" placeholder="Search user name, email, role..." onkeyup="filterUsersTable()">
                </div>
                <div style="font-size:0.75rem; font-weight:700; color:#64748B;">
                    Showing <?= count($usersList) ?> accounts in database
                </div>
            </div>

            <!-- 4. Table -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-users-gear"></i> System User Accounts</h3>
                </div>
                <div style="overflow-x:auto;">
                    <table class="ap-table" id="usersTable">
                        <thead>
                            <tr>
                                <th>Account Full Name</th>
                                <th>Email / Contact</th>
                                <th>Assigned Role</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($usersList)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:2.5rem; color:#64748B;">
                                        <i class="fas fa-users-slash" style="font-size:2rem; color:#CBD5E1; margin-bottom:0.5rem; display:block;"></i>
                                        <strong style="color:#0F172A; font-size:0.92rem;">No User Accounts Found</strong>
                                        <p style="margin:0.25rem 0 0; font-size:0.78rem;">Click "+ Add Account" to create a new administrator or officer.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($usersList as $u): ?>
                                    <?php 
                                        $role = strtolower($u['role'] ?? 'member');
                                    ?>
                                    <tr>
                                        <td><strong style="color:#0F172A; font-size:0.84rem;"><?= htmlspecialchars($u['full_name'] ?? 'User') ?></strong></td>
                                        <td style="color:#64748B; font-size:0.78rem;"><?= htmlspecialchars($u['email'] ?? $u['contact_phone'] ?? 'Active') ?></td>
                                        <td>
                                            <span class="ap-pill <?= ($role === 'admin' || $role === 'super_admin') ? 'danger' : 'blue' ?>">
                                                <?= ucfirst(str_replace('_', ' ', $role)) ?>
                                            </span>
                                        </td>
                                        <td><span class="ap-pill active"><span class="ap-pill-dot"></span> Active</span></td>
                                        <td style="color:#64748B; font-size:0.75rem; white-space:nowrap;"><?= !empty($u['created_at']) ? date('M d, Y', strtotime($u['created_at'])) : 'Recent' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- Create User Modal -->
    <div id="userModal" class="doc-modal">
        <div class="modal-inner-box">
            <div class="ap-card-header">
                <h3 class="ap-card-title"><i class="fas fa-user-plus"></i> Add Privileged Account</h3>
                <button class="btn-white" style="border:none; padding:0.25rem 0.5rem;" onclick="closeUserModal()">&times;</button>
            </div>
            <form method="POST" style="padding:1.25rem;">
                <input type="hidden" name="action" value="create_user">
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Full Name</label>
                    <input type="text" name="full_name" class="ap-input" placeholder="e.g. Engr. Juan Dela Cruz" required style="font-size:0.8rem;">
                </div>
                <div class="ap-form-group">
                    <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Email Address</label>
                    <input type="email" name="email" class="ap-input" placeholder="e.g. juan.delacruz@iecep.ph" required style="font-size:0.8rem;">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.65rem;">
                    <div class="ap-form-group">
                        <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Assigned Role</label>
                        <select name="role" class="ap-input" style="font-size:0.8rem;">
                            <option value="admin">Administrator</option>
                            <option value="eb_secretary">Executive Secretary</option>
                            <option value="eb_treasurer">Executive Treasurer</option>
                            <option value="eb_auditor">Executive Auditor</option>
                            <option value="school_officer">School Chapter Officer</option>
                        </select>
                    </div>
                    <div class="ap-form-group">
                        <label class="ap-form-label" style="font-size:0.76rem; font-weight:700;">Phone / Contact</label>
                        <input type="text" name="phone" class="ap-input" placeholder="+63 9XX XXX XXXX" style="font-size:0.8rem;">
                    </div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:0.65rem; margin-top:1rem;">
                    <button type="button" class="btn-white" onclick="closeUserModal()">Cancel</button>
                    <button type="submit" class="btn-primary-navy"><i class="fas fa-save"></i> Create Account</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openUserModal() {
            document.getElementById('userModal').classList.add('active');
        }
        function closeUserModal() {
            document.getElementById('userModal').classList.remove('active');
        }

        function filterUsersTable() {
            const query = document.getElementById('usrSearchInput').value.toLowerCase();
            const table = document.getElementById('usersTable');
            const trs = table.getElementsByTagName('tr');

            for (let i = 1; i < trs.length; i++) {
                const tr = trs[i];
                if (tr.children.length === 1 && tr.children[0].getAttribute('colspan')) continue;
                const text = tr.textContent.toLowerCase();
                tr.style.display = (text.indexOf(query) > -1) ? '' : 'none';
            }
        }
    </script>
</body>
</html>
