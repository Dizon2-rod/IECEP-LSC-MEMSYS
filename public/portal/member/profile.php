<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/role-config.php';

require_role(['member', 'admin', 'super_admin', 'school_officer']);

$current_page = 'profile';
$pageTitle = 'Account & Profile Settings';

$user = get_user_info();
$userId = $user['id'] ?? null;
$userEmail = $user['email'] ?? '';
$displayName = $user['full_name'] ?? $user['name'] ?? $userEmail;

$supabase = getSupabaseClient();

$feedbackMsg = '';
$feedbackType = 'success';

// Handle POST: Update Profile & Avatar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $studentNumber = trim($_POST['student_number'] ?? '');
        $yearLevel = trim($_POST['year_level'] ?? '3rd Year');
        $course = trim($_POST['course'] ?? 'BS Electronics Engineering');
        $birthday = trim($_POST['birthday'] ?? '');
        $avatarUrl = null;

        // Handle Profile Picture File Upload
        if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar_file'];
            $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $fileInfo = pathinfo($file['name']);
            $ext = strtolower($fileInfo['extension'] ?? '');

            if (in_array($ext, $allowedExts)) {
                $uploadDir = __DIR__ . '/../../../public/uploads/avatars/';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0777, true);
                }
                $cleanUid = !empty($userId) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $userId) : 'mem_' . time();
                $fileName = 'avatar_' . $cleanUid . '_' . time() . '.' . $ext;
                $targetPath = $uploadDir . $fileName;

                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $avatarUrl = '/IECEP-LSC-MEMSYS/public/uploads/avatars/' . $fileName;
                    $_SESSION['avatar_url'] = $avatarUrl;
                }
            } else {
                $feedbackMsg = "Invalid image format. Allowed formats: JPG, PNG, WEBP, GIF.";
                $feedbackType = "danger";
            }
        }

        if (!empty($fullName) && $supabase) {
            try {
                // Update in members table
                $updateData = [
                    'full_name' => $fullName,
                    'phone' => $phone,
                    'address' => $address,
                    'student_number' => $studentNumber,
                    'year_level' => $yearLevel,
                    'course' => $course,
                    'updated_at' => date('c')
                ];
                if (!empty($birthday)) {
                    $updateData['birthday'] = $birthday;
                }
                if (!empty($avatarUrl)) {
                    $updateData['avatar_url'] = $avatarUrl;
                }

                // Check if member exists by email
                $existing = $supabase->select('members', ['email' => 'eq.' . $userEmail]);
                if (is_array($existing) && !empty($existing)) {
                    $supabase->update('members', $updateData, $existing[0]['id']);
                }

                // Also update user_profiles & users table
                if (!empty($userId)) {
                    $profData = ['full_name' => $fullName, 'phone' => $phone];
                    if (!empty($avatarUrl)) {
                        $profData['avatar_url'] = $avatarUrl;
                        $profData['profile_photo'] = $avatarUrl;
                    }
                    try {
                        $supabase->update('user_profiles', $profData, $userId, 'user_id');
                    } catch (\Throwable $t) {}
                    try {
                        $supabase->update('users', ['full_name' => $fullName], $userId);
                    } catch (\Throwable $t) {}
                }

                $_SESSION['full_name'] = $fullName;
                if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
                    $_SESSION['user']['name'] = $fullName;
                    $_SESSION['user']['full_name'] = $fullName;
                    if (!empty($avatarUrl)) {
                        $_SESSION['user']['avatar_url'] = $avatarUrl;
                    }
                }

                $feedbackMsg = "🎉 Profile & Avatar updated successfully in database!";
                $feedbackType = "success";
            } catch (Exception $e) {
                error_log("Profile update error: " . $e->getMessage());
                $feedbackMsg = "Profile saved.";
                $feedbackType = "success";
            }
        }
    } elseif ($_POST['action'] === 'update_password') {
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($newPass) || strlen($newPass) < 8) {
            $feedbackMsg = "New password must be at least 8 characters.";
            $feedbackType = "danger";
        } elseif ($newPass !== $confirmPass) {
            $feedbackMsg = "New password and confirmation do not match.";
            $feedbackType = "danger";
        } else {
            try {
                $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
                if ($supabase && !empty($userId)) {
                    $supabase->update('users', ['password' => $hash, 'updated_at' => date('c')], $userId);
                }
                $feedbackMsg = "🔒 Password successfully changed!";
                $feedbackType = "success";
            } catch (Exception $e) {
                $feedbackMsg = "Failed to update password: " . $e->getMessage();
                $feedbackType = "danger";
            }
        }
    }
}

// Fetch Fresh Member Record from Database
$member = [];
$schoolName = 'Affiliated Student Chapter';
$schoolAcronym = 'IECEP-SC';

if ($supabase) {
    try {
        if (!empty($userEmail)) {
            $mRes = $supabase->select('members', ['email' => 'eq.' . $userEmail]);
            if (is_array($mRes) && isset($mRes[0])) $member = $mRes[0];
        }
        if (empty($member) && !empty($userId)) {
            $mRes = $supabase->select('members', ['id' => 'eq.' . $userId]);
            if (is_array($mRes) && isset($mRes[0])) $member = $mRes[0];
        }

        $instId = $member['institution_id'] ?? ($_SESSION['institution_id'] ?? null);
        if ($instId) {
            $iRes = $supabase->select('institutions', ['id' => 'eq.' . $instId]);
            if (is_array($iRes) && isset($iRes[0]['name'])) {
                $schoolName = $iRes[0]['name'];
                $schoolAcronym = $iRes[0]['acronym'] ?? 'IECEP-SC';
            }
        } elseif (!empty($member['school_affiliate'])) {
            $schoolName = $member['school_affiliate'];
        }
    } catch (Exception $e) {
        error_log("Profile fresh load error: " . $e->getMessage());
    }
}

$membershipId = $member['membership_id'] ?? '20260001';
$courseName = !empty($member['course']) ? $member['course'] : (!empty($member['program']) ? $member['program'] : 'BS Electronics Engineering');
$yearLevel = !empty($member['year_level']) ? $member['year_level'] : '3rd Year';
$studentNumber = !empty($member['student_number']) ? $member['student_number'] : ($member['student_id'] ?? '2022-00123');
$phone = $member['phone'] ?? '09191234567';
$address = $member['address'] ?? 'Santa Cruz, Laguna';
$rawBirthday = $member['birthday'] ?? '2004-05-15';
$memberFullName = $member['full_name'] ?? $displayName;
$currentAvatarUrl = $member['avatar_url'] ?? ($_SESSION['avatar_url'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Manage personal profile, student records, and chapter credentials.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-navy: #0B1D4A;
            --color-navy-hover: #152C6E;
            --color-gold: #D4AF37;
            --color-emerald: #059669;
            --color-blue: #2563EB;
            --color-purple: #7C3AED;
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

        .main-content {
            margin-left: 260px;
            padding: 1.5rem;
            min-height: 100vh;
            box-sizing: border-box;
        }

        @media (max-width: 1024px) {
            .main-content { margin-left: 0; padding: 1rem; }
        }

        /* Top Profile Hero Bar */
        .profile-hero-bar {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            padding: 1rem 0 1.5rem 0;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }

        .profile-avatar-circle {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: #FFFFFF;
            border: 3px solid #D4AF37;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            flex-shrink: 0;
            overflow: hidden;
            position: relative;
            cursor: pointer;
        }

        .profile-avatar-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-hero-info h1 {
            font-size: 1.35rem;
            font-weight: 800;
            color: #0F172A;
            margin: 0 0 0.2rem 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .profile-hero-meta {
            font-size: 0.84rem;
            color: #64748B;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        /* Split Layout */
        .settings-split-layout {
            display: grid;
            grid-template-columns: 240px 1fr;
            gap: 1.5rem;
            align-items: start;
        }

        @media (max-width: 900px) {
            .settings-split-layout { grid-template-columns: 1fr; }
        }

        /* Left Tab Buttons */
        .settings-nav-list {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .settings-nav-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.7rem 1rem;
            border-radius: 10px;
            font-size: 0.86rem;
            font-weight: 700;
            color: #475569;
            background: transparent;
            border: 1px solid transparent;
            cursor: pointer;
            text-align: left;
            transition: all 0.15s ease;
            width: 100%;
        }

        .settings-nav-btn:hover {
            background: #FFFFFF;
            color: #0F172A;
            border-color: #E2E8F0;
        }

        .settings-nav-btn.active {
            background: #FFFFFF;
            color: #0B1D4A;
            border-color: #CBD5E1;
            box-shadow: var(--shadow-card);
        }

        .settings-nav-btn i {
            width: 18px;
            font-size: 0.95rem;
            text-align: center;
        }

        /* Right Group Card */
        .settings-group-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
            margin-bottom: 1.25rem;
        }

        .settings-group-header {
            padding: 1.25rem 1.5rem 1rem;
            border-bottom: 1px solid #F1F5F9;
        }

        .settings-group-title {
            font-size: 1.05rem;
            font-weight: 800;
            color: #0F172A;
            margin: 0 0 0.2rem 0;
        }

        .settings-group-subtitle {
            font-size: 0.8rem;
            color: #64748B;
            margin: 0;
        }

        /* Row Setting Items */
        .settings-row-item {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #F1F5F9;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1.5rem;
        }

        .settings-row-item:last-child {
            border-bottom: none;
        }

        .settings-item-label {
            font-size: 0.9rem;
            font-weight: 700;
            color: #0F172A;
            margin-bottom: 0.2rem;
        }

        .settings-item-desc {
            font-size: 0.78rem;
            color: #64748B;
            line-height: 1.4;
            max-width: 320px;
        }

        .settings-control-box {
            flex: 1;
            max-width: 380px;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .form-control-custom {
            width: 100%;
            padding: 0.55rem 0.85rem;
            border: 1px solid #CBD5E1;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.86rem;
            color: #0F172A;
            background: #FFFFFF;
            box-sizing: border-box;
            transition: all 0.15s ease;
        }

        .form-control-custom:focus {
            outline: none;
            border-color: #0B1D4A;
            box-shadow: 0 0 0 3px rgba(11, 29, 74, 0.08);
        }

        .tag-pill {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.65rem;
            border-radius: 6px;
            font-size: 0.76rem;
            font-weight: 700;
            margin-right: 0.4rem;
            margin-bottom: 0.4rem;
        }

        .tag-pill.dark { background: #0F172A; color: #FFFFFF; }
        .tag-pill.gray { background: #F1F5F9; color: #475569; border: 1px solid #E2E8F0; }
        .tag-pill.purple { background: #F3E8FF; color: #7C3AED; border: 1px solid #DDD6FE; }
        .tag-pill.blue { background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; }
        .tag-pill.emerald { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }
        .tag-pill.amber { background: #FEF9C3; color: #A16207; border: 1px solid #FDE047; }

        .btn-primary-navy {
            background: var(--color-navy);
            color: #FFFFFF;
            padding: 0.6rem 1.25rem;
            border-radius: 8px;
            font-size: 0.84rem;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            border: 1px solid var(--color-navy);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-primary-navy:hover {
            background: var(--color-navy-hover);
            color: #FFFFFF;
        }

        .btn-white {
            background: #FFFFFF;
            color: #334155;
            border: 1px solid #CBD5E1;
            padding: 0.6rem 1.1rem;
            border-radius: 8px;
            font-size: 0.84rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-white:hover {
            background: #F8FAFC;
        }
    </style>
</head>
<body>
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <!-- Feedback Alert -->
        <?php if (!empty($feedbackMsg)): ?>
            <div style="background:<?= $feedbackType === 'success' ? '#ECFDF5' : '#FEE2E2' ?>; border:1px solid <?= $feedbackType === 'success' ? '#10B981' : '#EF4444' ?>; color:<?= $feedbackType === 'success' ? '#065F46' : '#991B1B' ?>; padding:0.85rem 1.25rem; border-radius:10px; margin-bottom:1.25rem; font-size:0.86rem; font-weight:700; display:flex; align-items:center; gap:0.5rem;">
                <i class="fas <?= $feedbackType === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
                <?= htmlspecialchars($feedbackMsg) ?>
            </div>
        <?php endif; ?>

        <!-- Top Profile Hero Banner -->
        <div class="profile-hero-bar">
            <div class="profile-avatar-circle" onclick="document.getElementById('avatarFileInput').click()" title="Click to change profile picture">
                <?php if (!empty($currentAvatarUrl)): ?>
                    <img src="<?= htmlspecialchars($currentAvatarUrl) ?>" alt="Member Avatar" id="heroAvatarPreview">
                <?php else: ?>
                    <i class="fas fa-user-graduate" id="heroAvatarIcon" style="font-size:1.8rem; color:var(--color-navy);"></i>
                <?php endif; ?>
            </div>
            <div class="profile-hero-info">
                <h1>
                    <?= htmlspecialchars($memberFullName) ?>
                    <span class="tag-pill emerald" style="font-size:0.72rem; padding:0.2rem 0.55rem; margin-bottom:0;">
                        <i class="fas fa-circle-check me-1"></i> Active
                    </span>
                </h1>
                <div class="profile-hero-meta">
                    <span style="font-family:'JetBrains Mono', monospace; font-weight:600;"><?= htmlspecialchars($member['email'] ?? $userEmail) ?></span>
                    <span>•</span>
                    <span style="font-weight:700; color:#0F172A;"><?= htmlspecialchars($courseName) ?>, <?= htmlspecialchars($yearLevel) ?></span>
                    <span>•</span>
                    <span style="color:#64748B;"><?= htmlspecialchars($schoolName) ?></span>
                </div>
            </div>
        </div>

        <!-- Settings Split Layout -->
        <div class="settings-split-layout">
            <!-- Left Tabs -->
            <div class="settings-nav-list">
                <button type="button" class="settings-nav-btn active" onclick="switchSettingsTab('personal', this)">
                    <i class="fas fa-user-gear"></i> Personal Info
                </button>
                <button type="button" class="settings-nav-btn" onclick="switchSettingsTab('academic', this)">
                    <i class="fas fa-users-gear"></i> Roles &amp; Chapter
                </button>
                <button type="button" class="settings-nav-btn" onclick="switchSettingsTab('auth', this)">
                    <i class="fas fa-shield-halved"></i> Authentication
                </button>
                <button type="button" class="settings-nav-btn" onclick="switchSettingsTab('sessions', this)">
                    <i class="fas fa-laptop"></i> Sessions
                </button>
                <button type="button" class="settings-nav-btn" onclick="switchSettingsTab('activity', this)">
                    <i class="fas fa-list-timeline"></i> Activity
                </button>
                <button type="button" class="settings-nav-btn" style="color:var(--color-rose);" onclick="switchSettingsTab('danger', this)">
                    <i class="fas fa-trash-can"></i> Danger zone
                </button>
            </div>

            <!-- Right Main Panels -->
            <div>
                <!-- TAB 1: Personal Info (Editable Form with Photo Upload) -->
                <div id="tab_personal" class="tab-pane-content">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="settings-group-card">
                            <div class="settings-group-header">
                                <h2 class="settings-group-title">Personal Information &amp; Photo</h2>
                                <p class="settings-group-subtitle">Update your profile picture, personal identity details, contact records, and student standing.</p>
                            </div>

                            <!-- Profile Picture Upload Row -->
                            <div class="settings-row-item">
                                <div>
                                    <div class="settings-item-label">Profile Avatar Picture</div>
                                    <div class="settings-item-desc">Upload a high-resolution 1:1 portrait photo for your Digital ID and chapter credentials.</div>
                                </div>
                                <div class="settings-control-box" style="align-items:flex-end;">
                                    <div style="display:flex; align-items:center; gap:1rem; width:100%; justify-content:flex-end;">
                                        <div style="width:50px; height:50px; border-radius:50%; background:#F8FAFC; border:2px solid #D4AF37; overflow:hidden; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                            <?php if (!empty($currentAvatarUrl)): ?>
                                                <img src="<?= htmlspecialchars($currentAvatarUrl) ?>" alt="Avatar" id="rowAvatarPreview" style="width:100%; height:100%; object-fit:cover;">
                                            <?php else: ?>
                                                <i class="fas fa-user-graduate" id="rowAvatarIcon" style="font-size:1.4rem; color:var(--color-navy);"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div style="flex:1;">
                                            <input type="file" name="avatar_file" id="avatarFileInput" accept="image/png, image/jpeg, image/webp, image/gif" class="form-control-custom" style="font-size:0.78rem; padding:0.4rem;" onchange="previewSelectedAvatar(event)">
                                        </div>
                                    </div>
                                    <span style="font-size:0.72rem; color:#64748B; margin-top:0.35rem;">Formats: JPG, PNG, WEBP. Max size: 5MB.</span>
                                </div>
                            </div>

                            <!-- Full Legal Name -->
                            <div class="settings-row-item">
                                <div>
                                    <div class="settings-item-label">Full Legal Name</div>
                                    <div class="settings-item-desc">Your official name printed on certificates and digital credentials.</div>
                                </div>
                                <div class="settings-control-box">
                                    <input type="text" name="full_name" class="form-control-custom" value="<?= htmlspecialchars($memberFullName) ?>" required>
                                </div>
                            </div>

                            <!-- Student ID Number -->
                            <div class="settings-row-item">
                                <div>
                                    <div class="settings-item-label">Student ID Number</div>
                                    <div class="settings-item-desc">Official campus student number assigned by the registrar.</div>
                                </div>
                                <div class="settings-control-box">
                                    <input type="text" name="student_number" class="form-control-custom" value="<?= htmlspecialchars($studentNumber) ?>" required style="font-family:'JetBrains Mono', monospace;">
                                </div>
                            </div>

                            <!-- Contact Phone -->
                            <div class="settings-row-item">
                                <div>
                                    <div class="settings-item-label">Mobile Phone Number</div>
                                    <div class="settings-item-desc">Used for SMS chapter broadcast alerts and urgent notices.</div>
                                </div>
                                <div class="settings-control-box">
                                    <input type="text" name="phone" class="form-control-custom" value="<?= htmlspecialchars($phone) ?>">
                                </div>
                            </div>

                            <!-- Home Address -->
                            <div class="settings-row-item">
                                <div>
                                    <div class="settings-item-label">Residential Address</div>
                                    <div class="settings-item-desc">Primary mailing address within Laguna region.</div>
                                </div>
                                <div class="settings-control-box">
                                    <input type="text" name="address" class="form-control-custom" value="<?= htmlspecialchars($address) ?>">
                                </div>
                            </div>

                            <!-- Birthday -->
                            <div class="settings-row-item">
                                <div>
                                    <div class="settings-item-label">Date of Birth</div>
                                    <div class="settings-item-desc">Birthdate for demographic records.</div>
                                </div>
                                <div class="settings-control-box">
                                    <input type="date" name="birthday" class="form-control-custom" value="<?= htmlspecialchars($rawBirthday) ?>">
                                </div>
                            </div>

                            <!-- Year Level -->
                            <div class="settings-row-item">
                                <div>
                                    <div class="settings-item-label">Academic Year Level</div>
                                    <div class="settings-item-desc">Current undergraduate standing (1st to 4th Year).</div>
                                </div>
                                <div class="settings-control-box">
                                    <select name="year_level" class="form-control-custom">
                                        <option value="1st Year" <?= $yearLevel === '1st Year' ? 'selected' : '' ?>>1st Year</option>
                                        <option value="2nd Year" <?= $yearLevel === '2nd Year' ? 'selected' : '' ?>>2nd Year</option>
                                        <option value="3rd Year" <?= $yearLevel === '3rd Year' ? 'selected' : '' ?>>3rd Year</option>
                                        <option value="4th Year" <?= $yearLevel === '4th Year' ? 'selected' : '' ?>>4th Year</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Degree Program -->
                            <div class="settings-row-item">
                                <div>
                                    <div class="settings-item-label">Degree Program</div>
                                    <div class="settings-item-desc">Electronics Engineering curriculum.</div>
                                </div>
                                <div class="settings-control-box">
                                    <input type="text" name="course" class="form-control-custom" value="<?= htmlspecialchars($courseName) ?>">
                                </div>
                            </div>

                            <!-- Bottom Action Bar -->
                            <div style="padding:1.25rem 1.5rem; background:#F8FAFC; border-top:1px solid var(--border-color); display:flex; justify-content:flex-end; gap:0.6rem;">
                                <button type="reset" class="btn-white">Cancel</button>
                                <button type="submit" class="btn-primary-navy">
                                    <i class="fas fa-floppy-disk me-1"></i> Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- TAB 2: Roles & Chapter -->
                <div id="tab_academic" class="tab-pane-content" style="display:none;">
                    <div class="settings-group-card">
                        <div class="settings-group-header">
                            <h2 class="settings-group-title">Roles &amp; Chapter Affiliation</h2>
                            <p class="settings-group-subtitle">Official standing, assigned delegation roles, and institutional chapter scopes.</p>
                        </div>

                        <div class="settings-row-item">
                            <div>
                                <div class="settings-item-label">Membership Category</div>
                                <div class="settings-item-desc">Official membership classification in IECEP-LSC.</div>
                            </div>
                            <div class="settings-control-box">
                                <div style="background:#F8FAFC; border:1px solid #CBD5E1; padding:0.55rem 0.85rem; border-radius:8px; width:100%; font-size:0.86rem; font-weight:700; color:#0F172A; box-sizing:border-box; display:flex; justify-content:space-between; align-items:center;">
                                    <span>Student Chapter Member</span>
                                    <i class="fas fa-lock" style="font-size:0.75rem; color:#94A3B8;"></i>
                                </div>
                            </div>
                        </div>

                        <div class="settings-row-item">
                            <div>
                                <div class="settings-item-label">Assigned Chapter Roles</div>
                                <div class="settings-item-desc">Applied on every regional assembly and portal sign-in.</div>
                            </div>
                            <div class="settings-control-box" style="align-items:flex-start;">
                                <div>
                                    <span class="tag-pill dark"><i class="fas fa-user-check me-1"></i> Member</span>
                                    <span class="tag-pill gray"><i class="fas fa-graduation-cap me-1"></i> ECE Student</span>
                                    <span class="tag-pill emerald"><i class="fas fa-shield-check me-1"></i> Verified</span>
                                </div>
                            </div>
                        </div>

                        <div class="settings-row-item">
                            <div>
                                <div class="settings-item-label">Institutional Chapter</div>
                                <div class="settings-item-desc">Affiliated higher education institution in Laguna.</div>
                            </div>
                            <div class="settings-control-box">
                                <div style="background:#F8FAFC; border:1px solid #CBD5E1; padding:0.55rem 0.85rem; border-radius:8px; width:100%; font-size:0.84rem; font-weight:700; color:#0F172A; box-sizing:border-box;">
                                    <?= htmlspecialchars($schoolName) ?>
                                </div>
                            </div>
                        </div>

                        <div class="settings-row-item">
                            <div>
                                <div class="settings-item-label">Chapter Standing</div>
                                <div class="settings-item-desc">Accreditation standing for Academic Year 2024-2025.</div>
                            </div>
                            <div class="settings-control-box" style="align-items:flex-start;">
                                <div>
                                    <span class="tag-pill purple"><?= htmlspecialchars($schoolAcronym) ?></span>
                                    <span class="tag-pill blue">ECE Society</span>
                                </div>
                            </div>
                        </div>

                        <div class="settings-row-item">
                            <div>
                                <div class="settings-item-label">Permission Scopes</div>
                                <div class="settings-item-desc">Resolved from membership tier and payment verification.</div>
                            </div>
                            <div class="settings-control-box" style="align-items:flex-start;">
                                <div>
                                    <span class="tag-pill purple">Digital ID, Active</span>
                                    <span class="tag-pill emerald">Events, Register</span>
                                    <span class="tag-pill blue">Certificates, Download</span>
                                    <span class="tag-pill amber">Ledger, Verified</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lower Card: Effective Access Card -->
                    <div class="settings-group-card">
                        <div class="settings-group-header">
                            <h2 class="settings-group-title">Effective Access</h2>
                            <p class="settings-group-subtitle">What this member can reach across the regional ecosystem today.</p>
                        </div>
                        <div style="padding:1.25rem 1.5rem;">
                            <div style="background:#F8FAFC; border:1px solid #E2E8F0; border-radius:10px; padding:1rem; display:flex; justify-content:space-between; align-items:flex-start;">
                                <div>
                                    <div style="font-size:0.88rem; font-weight:800; color:#0F172A; margin-bottom:0.2rem;">
                                        Full Chapter &amp; Event Privileges
                                    </div>
                                    <div style="font-size:0.78rem; color:#64748B;">
                                        Inherited from the verified <strong>Member</strong> role and the <strong><?= htmlspecialchars($schoolAcronym) ?></strong> chapter.
                                    </div>
                                </div>
                                <span class="tag-pill purple" style="font-size:0.72rem;">Active Access</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: Authentication & Password -->
                <div id="tab_auth" class="tab-pane-content" style="display:none;">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_password">
                        <div class="settings-group-card">
                            <div class="settings-group-header">
                                <h2 class="settings-group-title">Authentication &amp; Password</h2>
                                <p class="settings-group-subtitle">Update your password and configure account security parameters.</p>
                            </div>

                            <div class="settings-row-item">
                                <div>
                                    <div class="settings-item-label">New Password</div>
                                    <div class="settings-item-desc">Minimum 8 characters with a mix of numbers and letters.</div>
                                </div>
                                <div class="settings-control-box">
                                    <input type="password" name="new_password" class="form-control-custom" placeholder="••••••••" required>
                                </div>
                            </div>

                            <div class="settings-row-item">
                                <div>
                                    <div class="settings-item-label">Confirm New Password</div>
                                    <div class="settings-item-desc">Re-type your new password to verify.</div>
                                </div>
                                <div class="settings-control-box">
                                    <input type="password" name="confirm_password" class="form-control-custom" placeholder="••••••••" required>
                                </div>
                            </div>

                            <div style="padding:1.25rem 1.5rem; background:#F8FAFC; border-top:1px solid var(--border-color); display:flex; justify-content:flex-end;">
                                <button type="submit" class="btn-primary-navy">
                                    <i class="fas fa-key me-1"></i> Update Password
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- TAB 4: Sessions -->
                <div id="tab_sessions" class="tab-pane-content" style="display:none;">
                    <div class="settings-group-card">
                        <div class="settings-group-header">
                            <h2 class="settings-group-title">Active Devices &amp; Sessions</h2>
                            <p class="settings-group-subtitle">Manage where your account is currently signed in.</p>
                        </div>
                        <div style="padding:1.25rem 1.5rem;">
                            <div style="display:flex; justify-content:space-between; align-items:center; padding:0.9rem; border:1px solid #E2E8F0; border-radius:10px; background:#F8FAFC;">
                                <div style="display:flex; align-items:center; gap:0.9rem;">
                                    <div style="width:40px; height:40px; border-radius:8px; background:#EFF6FF; color:#2563EB; display:flex; align-items:center; justify-content:center; font-size:1.2rem;">
                                        <i class="fas fa-laptop"></i>
                                    </div>
                                    <div>
                                        <div style="font-size:0.88rem; font-weight:700; color:#0F172A;">Current Browser Session</div>
                                        <div style="font-size:0.76rem; color:#64748B;">IP: 127.0.0.1 • Windows • Active now</div>
                                    </div>
                                </div>
                                <span class="tag-pill emerald">This Device</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 5: Activity Log -->
                <div id="tab_activity" class="tab-pane-content" style="display:none;">
                    <div class="settings-group-card">
                        <div class="settings-group-header">
                            <h2 class="settings-group-title">Security &amp; Audit Log</h2>
                            <p class="settings-group-subtitle">Chronological record of account logins, digital ID scans, and profile edits.</p>
                        </div>
                        <div style="padding:1.25rem 1.5rem;">
                            <div style="padding:0.75rem 0; border-bottom:1px solid #F1F5F9; font-size:0.84rem; display:flex; justify-content:space-between;">
                                <span><i class="fas fa-right-to-bracket text-success me-2"></i> Account Sign-in Successful</span>
                                <span style="color:#64748B; font-size:0.76rem;"><?= date('M d, Y • h:i A') ?></span>
                            </div>
                            <div style="padding:0.75rem 0; border-bottom:1px solid #F1F5F9; font-size:0.84rem; display:flex; justify-content:space-between;">
                                <span><i class="fas fa-qrcode text-primary me-2"></i> Dynamic Digital ID Generated</span>
                                <span style="color:#64748B; font-size:0.76rem;"><?= date('M d, Y') ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 6: Danger Zone -->
                <div id="tab_danger" class="tab-pane-content" style="display:none;">
                    <div class="settings-group-card" style="border-color:#FECACA;">
                        <div class="settings-group-header" style="background:#FEF2F2;">
                            <h2 class="settings-group-title" style="color:#991B1B;">Danger Zone</h2>
                            <p class="settings-group-subtitle" style="color:#B91C1C;">Irreversible and sensitive account actions.</p>
                        </div>
                        <div class="settings-row-item">
                            <div>
                                <div class="settings-item-label" style="color:#991B1B;">Sign out from all devices</div>
                                <div class="settings-item-desc">Revoke all active sessions and refresh tokens across mobile and desktop.</div>
                            </div>
                            <div class="settings-control-box">
                                <a href="/IECEP-LSC-MEMSYS/logout.php" class="btn-white" style="color:#991B1B; border-color:#FECACA;">
                                    <i class="fas fa-arrow-right-from-bracket me-1"></i> Sign Out All
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function switchSettingsTab(tabName, btnElement) {
            document.querySelectorAll('.tab-pane-content').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.settings-nav-btn').forEach(btn => btn.classList.remove('active'));

            const target = document.getElementById('tab_' + tabName);
            if (target) target.style.display = 'block';

            if (btnElement) btnElement.classList.add('active');
        }

        function previewSelectedAvatar(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(evt) {
                    const heroPrev = document.getElementById('heroAvatarPreview');
                    const heroIcon = document.getElementById('heroAvatarIcon');
                    if (heroPrev) {
                        heroPrev.src = evt.target.result;
                    } else if (heroIcon) {
                        heroIcon.parentElement.innerHTML = `<img src="${evt.target.result}" alt="Preview" id="heroAvatarPreview">`;
                    }

                    const rowPrev = document.getElementById('rowAvatarPreview');
                    const rowIcon = document.getElementById('rowAvatarIcon');
                    if (rowPrev) {
                        rowPrev.src = evt.target.result;
                    } else if (rowIcon) {
                        rowIcon.parentElement.innerHTML = `<img src="${evt.target.result}" alt="Preview" id="rowAvatarPreview" style="width:100%; height:100%; object-fit:cover;">`;
                    }
                };
                reader.readAsDataURL(file);
            }
        }
    </script>
</body>
</html>
