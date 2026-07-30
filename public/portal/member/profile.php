<?php
require_once __DIR__ . '/../auth_check.php';
require_role(['member']);

require_once __DIR__ . '/../../../includes/role-config.php';
require_once __DIR__ . '/../../../bootstrap.php';

$current_page = 'profile';

$user = get_user_info();
$member_id = $_SESSION['member_id'] ?? $user['member_id'] ?? null;

if (!$member_id) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$supabase = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);

// Fetch member details
try {
    $memberData = $supabase->select('members', [
        'id' => 'eq.' . $member_id
    ]);
    $member = $memberData[0] ?? [];
} catch (Exception $e) {
    $member = [];
}

// Fetch user profile
try {
    $profileData = $supabase->select('user_profiles', [
        'user_id' => 'eq.' . ($user['id'] ?? '')
    ]);
    $profile = $profileData[0] ?? [];
} catch (Exception $e) {
    $profile = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/../../includes/head-meta.php'; ?>
    <title>My Profile - Member Portal</title>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="container py-5">
                <div class="mb-4">
                    <h1 class="h2 mb-2">My Profile</h1>
                    <p class="text-muted">View your personal information and membership details</p>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 text-center mb-4 mb-md-0">
                                <div class="member-photo-large">
                                    <i class="fas fa-user fa-4x"></i>
                                </div>
                                <h5 class="mt-3"><?= htmlspecialchars($member['full_name'] ?? 'Member Name') ?></h5>
                                <p class="text-muted"><code><?= htmlspecialchars($member['membership_id'] ?? 'IECEP-XXXX-XXXX') ?></code></p>
                            </div>
                            <div class="col-md-8">
                                <h5 class="mb-4">Personal Information</h5>
                                
                                <div class="row mb-3">
                                    <div class="col-sm-4">
                                        <label class="fw-bold text-muted">Full Name</label>
                                    </div>
                                    <div class="col-sm-8">
                                        <?= htmlspecialchars($member['full_name'] ?? 'N/A') ?>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-sm-4">
                                        <label class="fw-bold text-muted">Email</label>
                                    </div>
                                    <div class="col-sm-8">
                                        <?= htmlspecialchars($member['email'] ?? 'N/A') ?>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-sm-4">
                                        <label class="fw-bold text-muted">Year Level</label>
                                    </div>
                                    <div class="col-sm-8">
                                        <?= htmlspecialchars($member['year_level'] ?? 'N/A') ?>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-sm-4">
                                        <label class="fw-bold text-muted">Institution</label>
                                    </div>
                                    <div class="col-sm-8">
                                        <?= htmlspecialchars($member['school_affiliate'] ?? 'N/A') ?>
                                    </div>
                                </div>

                                <hr>

                                <h5 class="mb-4">Membership Details</h5>

                                <div class="row mb-3">
                                    <div class="col-sm-4">
                                        <label class="fw-bold text-muted">Membership Type</label>
                                    </div>
                                    <div class="col-sm-8">
                                        <?php
                                        $memberType = $member['member_type'] ?? 'new';
                                        $typeLabels = [
                                            'new' => 'New Member',
                                            'returning' => 'Returning Member',
                                            'honorary' => 'Honorary Member'
                                        ];
                                        ?>
                                        <span class="badge bg-info"><?= htmlspecialchars($typeLabels[$memberType] ?? $memberType) ?></span>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-sm-4">
                                        <label class="fw-bold text-muted">Payment Status</label>
                                    </div>
                                    <div class="col-sm-8">
                                        <?php if ($member['payment_status'] ?? false): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle me-1"></i>Paid
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">
                                                <i class="fas fa-clock me-1"></i>Pending
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-sm-4">
                                        <label class="fw-bold text-muted">Membership Status</label>
                                    </div>
                                    <div class="col-sm-8">
                                        <?php
                                        $status = $profile['membership_status'] ?? 'active';
                                        $statusLabels = [
                                            'active' => 'Active',
                                            'inactive' => 'Inactive',
                                            'suspended' => 'Suspended',
                                            'pending' => 'Pending'
                                        ];
                                        ?>
                                        <span class="badge bg-<?= $status === 'active' ? 'success' : 'secondary' ?>">
                                            <?= htmlspecialchars($statusLabels[$status] ?? $status) ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-sm-4">
                                        <label class="fw-bold text-muted">Member Since</label>
                                    </div>
                                    <div class="col-sm-8">
                                        <?= $member['created_at'] ? date('F d, Y', strtotime($member['created_at'])) : 'N/A' ?>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-sm-4">
                                        <label class="fw-bold text-muted">Last Login</label>
                                    </div>
                                    <div class="col-sm-8">
                                        <?= $profile['last_login'] ? date('F d, Y g:i A', strtotime($profile['last_login'])) : 'First login' ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info mt-4">
                    <i class="fas fa-info-circle me-2"></i>
                    This is a read-only view of your profile. To update your information, please contact your school officer or the IECEP-LSC administration.
                </div>
            </div>
        </main>
    </div>

    <style>
        .member-photo-large {
            width: 150px;
            height: 150px;
            background: linear-gradient(135deg, var(--neutral-200) 0%, var(--neutral-300) 100%);
            border-radius: 50%;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--neutral-500);
            border: 4px solid var(--accent);
        }
    </style>
</body>
</html>

