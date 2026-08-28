<?php
require_once __DIR__ . '/../../bootstrap.php';
$current_page = 'settings';
require_once __DIR__ . '/../../auth_check.php';
require_role(['admin', 'super_admin']);
require_once __DIR__ . '/../../../../includes/paths.php';
$user = get_user_info();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Manage system settings, role permissions, and portal configuration for IECEP-LSC Laguna Student Chapter.">
    <?php include __DIR__ . '/../../../../includes/head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
</head>
<body>
    <?php include __DIR__ . '/../../../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- Page Header -->
            <div class="ap-page-header">
                <div class="ap-title-block">
                    <h1 class="ap-page-title"><i class="fas fa-gear"></i> System Settings</h1>
                    <p class="ap-page-subtitle">Configure portal settings, manage roles, and customize MEMSYS behavior for your chapter.</p>
                </div>
                <div class="ap-header-actions">
                    <a href="/IECEP-LSC-MEMSYS/public/portal/admin/system/users.php" class="ap-btn-secondary">
                        <i class="fas fa-users-gear"></i> Manage Users
                    </a>
                    <button class="ap-btn-primary" onclick="saveSettings()">
                        <i class="fas fa-floppy-disk"></i> Save Changes
                    </button>
                </div>
            </div>

            <!-- Settings Sections Grid -->
            <div class="ap-grid-2">
                <!-- General Settings -->
                <div class="ap-card" style="margin-bottom:0;">
                    <div class="ap-card-header">
                        <h3 class="ap-card-title"><i class="fas fa-sliders"></i> General Settings</h3>
                    </div>
                    <div class="ap-form-group">
                        <label class="ap-form-label">Chapter Name</label>
                        <input type="text" class="ap-input" value="IECEP Laguna Student Chapter" placeholder="Chapter Name">
                    </div>
                    <div class="ap-form-group">
                        <label class="ap-form-label">Academic Year</label>
                        <input type="text" class="ap-input" value="2026–2027" placeholder="AY Format: YYYY-YYYY">
                    </div>
                    <div class="ap-form-group">
                        <label class="ap-form-label">Membership Fee (₱)</label>
                        <input type="number" class="ap-input" value="950" placeholder="950">
                    </div>
                    <div class="ap-form-group">
                        <label class="ap-form-label">Portal Timezone</label>
                        <select class="ap-form-select">
                            <option selected>Asia/Manila (UTC+8)</option>
                            <option>UTC</option>
                        </select>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="ap-card" style="margin-bottom:0;">
                    <div class="ap-card-header">
                        <h3 class="ap-card-title"><i class="fas fa-shield-halved"></i> Security Settings</h3>
                    </div>
                    <div class="ap-form-group">
                        <label class="ap-form-label">Two-Factor Authentication</label>
                        <select class="ap-form-select">
                            <option>Required for Admins</option>
                            <option>Optional for All</option>
                            <option>Required for All</option>
                        </select>
                        <div class="ap-input-help">Controls 2FA enforcement policy across all portal users.</div>
                    </div>
                    <div class="ap-form-group">
                        <label class="ap-form-label">Session Timeout</label>
                        <select class="ap-form-select">
                            <option>30 minutes</option>
                            <option selected>1 hour</option>
                            <option>4 hours</option>
                            <option>8 hours</option>
                        </select>
                    </div>
                    <div class="ap-form-group">
                        <label class="ap-form-label">Admin Login Notification</label>
                        <select class="ap-form-select">
                            <option selected>Email on every login</option>
                            <option>Email on new device only</option>
                            <option>Disabled</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Blockchain Settings -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-link"></i> Blockchain & Ledger Configuration</h3>
                </div>
                <div class="ap-grid-2" style="margin-bottom:0;">
                    <div>
                        <div class="ap-form-group">
                            <label class="ap-form-label">Hashing Algorithm</label>
                            <select class="ap-form-select">
                                <option selected>SHA-256</option>
                                <option>SHA-512</option>
                            </select>
                        </div>
                        <div class="ap-form-group">
                            <label class="ap-form-label">Signing Algorithm</label>
                            <select class="ap-form-select">
                                <option selected>RSA-2048</option>
                                <option>Ed25519</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <div class="ap-form-group">
                            <label class="ap-form-label">Auto-anchor on Member Registration</label>
                            <select class="ap-form-select">
                                <option selected>Enabled (Automatic)</option>
                                <option>Manual Anchor Only</option>
                            </select>
                        </div>
                        <div class="ap-form-group">
                            <label class="ap-form-label">Chain Verification Schedule</label>
                            <select class="ap-form-select">
                                <option>Daily</option>
                                <option selected>Weekly</option>
                                <option>Monthly</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="ap-alert info" style="margin-top:0.5rem;">
                    <i class="fas fa-info-circle"></i>
                    <span>Changes to blockchain settings take effect on next anchor operation. Existing records are not retroactively affected.</span>
                </div>
            </div>

            <!-- Role Management -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-user-tag"></i> Role Configuration</h3>
                    <a href="/IECEP-LSC-MEMSYS/public/portal/admin/system/users.php" class="ap-btn-primary" style="font-size:0.78rem; padding:0.45rem 1rem;">
                        <i class="fas fa-users-gear"></i> Manage Users
                    </a>
                </div>

                <div class="ap-table-wrapper">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Role</th>
                                <th>Description</th>
                                <th>Permissions Level</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $roles = [
                                ['name' => 'super_admin', 'label' => 'Super Administrator', 'desc' => 'Full system access — all modules, settings, and data.', 'level' => 'Full', 'active' => true],
                                ['name' => 'admin', 'label' => 'Administrator', 'desc' => 'Full admin portal access except system settings.', 'level' => 'High', 'active' => true],
                                ['name' => 'eb_president', 'label' => 'Chapter President', 'desc' => 'Access to compliance, events, and announcements.', 'level' => 'Medium', 'active' => true],
                                ['name' => 'eb_secretary', 'label' => 'Chapter Secretary', 'desc' => 'Announcements, newsletter, and member management.', 'level' => 'Medium', 'active' => true],
                                ['name' => 'chapter_officer', 'label' => 'Chapter Officer', 'desc' => 'Limited access to member-facing features.', 'level' => 'Standard', 'active' => true],
                                ['name' => 'member', 'label' => 'Student Member', 'desc' => 'Member portal — profile, digital ID, events.', 'level' => 'Basic', 'active' => true],
                            ];
                            $levelPill = ['Full'=>'danger','High'=>'purple','Medium'=>'cyan','Standard'=>'gold','Basic'=>'info'];
                            foreach ($roles as $role):
                            ?>
                            <tr>
                                <td><span class="ap-mono" style="font-size:0.8rem;"><?= $role['name'] ?></span></td>
                                <td>
                                    <strong style="color:var(--text-heading);"><?= $role['label'] ?></strong><br>
                                    <span style="font-size:0.76rem;color:var(--text-muted);"><?= $role['desc'] ?></span>
                                </td>
                                <td>
                                    <span class="ap-pill <?= $levelPill[$role['level']] ?? 'info' ?>">
                                        <span class="ap-pill-dot"></span>
                                        <?= $role['level'] ?>
                                    </span>
                                </td>
                                <td><span class="ap-pill active"><span class="ap-pill-dot"></span>Active</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="ap-card" style="border-color:rgba(225,29,72,0.25);">
                <div class="ap-card-header">
                    <h3 class="ap-card-title" style="color:var(--accent-rose);"><i class="fas fa-triangle-exclamation"></i> Danger Zone</h3>
                </div>
                <div class="ap-alert danger" style="margin-bottom:1rem;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><strong>Warning:</strong> Actions in this section are irreversible. Proceed only if you fully understand the consequences.</span>
                </div>
                <div style="display:flex; gap:0.75rem; flex-wrap:wrap;">
                    <button class="ap-btn-danger" onclick="if(confirm('Clear all sessions? All users will be logged out.')) alert('Sessions cleared.')">
                        <i class="fas fa-power-off"></i> Clear All Sessions
                    </button>
                    <button class="ap-btn-danger" onclick="if(confirm('This will purge ALL cached data. Continue?')) alert('Cache cleared.')">
                        <i class="fas fa-trash-can"></i> Purge Cache
                    </button>
                </div>
            </div>

            <!-- Sentinel -->
            <div class="ap-sentinel-strip">
                <div class="ap-sentinel-item"><i class="fas fa-user-shield"></i><span><strong>Logged in as:</strong> <?= htmlspecialchars($user['email'] ?? 'Administrator') ?></span></div>
                <div class="ap-sentinel-item"><i class="fas fa-clock"></i><span><strong>Server Time:</strong> <?= date('Y-m-d H:i:s') ?> UTC+8</span></div>
            </div>

        </div>
    </main>

    <script>
        function saveSettings() {
            const btn = event.target;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            btn.disabled = true;
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-check"></i> Saved';
                btn.style.background = 'linear-gradient(135deg, #059669, #10B981)';
                setTimeout(() => {
                    btn.innerHTML = '<i class="fas fa-floppy-disk"></i> Save Changes';
                    btn.style.background = '';
                    btn.disabled = false;
                }, 2000);
            }, 800);
        }
    </script>
</body>
</html>
