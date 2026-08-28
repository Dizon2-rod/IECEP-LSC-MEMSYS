<?php
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/middleware/auth.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'member') {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$current_page = 'dashboard';
$user = $_SESSION['user'];
$displayName = $user['name'] ?? $user['email'] ?? 'Member';

$supabase = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);

$member = [];
try {
    $memberData = $supabase->select('members', [
        'user_id' => 'eq.' . ($user['id'] ?? '')
    ]);
    $member = $memberData[0] ?? [];
} catch (Exception $e) {
    $member = [];
}

if (empty($member)) {
    try {
        $allMembers = $supabase->select('members', [], 'id', 'DESC');
        foreach ($allMembers as $m) {
            if (($m['email'] ?? '') === $user['email']) {
                $member = $m;
                break;
            }
        }
    } catch (Exception $e) {
        $member = [];
    }
}

$events = [];
try {
    $events = $supabase->select('events', [
        'is_public' => 'eq.true',
        'start_date' => 'gte.' . date('c'),
        'order' => 'start_date.asc',
        'limit' => '5'
    ]);
} catch (Exception $e) {
    $events = [];
}

$announcements = [];
try {
    $announcements = $supabase->select('announcements', [
        'target_audience' => 'ilike.%member%',
        'order' => 'created_at.desc',
        'limit' => '3'
    ]);
} catch (Exception $e) {
    $announcements = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/../../../includes/head-meta.php'; ?>
    <title>Dashboard - Member Portal</title>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once __DIR__ . '/../../../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="container py-4">
                <!-- Hero Welcome Card -->
                <div class="card card-gold-top mb-4" style="background: linear-gradient(135deg, var(--memsys-navy) 0%, var(--memsys-navy-light) 100%); color: #ffffff;">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div>
                            <span class="badge" style="background: rgba(212,175,55,0.25); color: var(--memsys-gold); border: 1px solid rgba(212,175,55,0.4); margin-bottom: 0.5rem;">
                                <i class="fas fa-shield-alt me-1"></i> Verified Student Member
                            </span>
                            <h1 style="color: #ffffff; margin-bottom: 0.25rem;">Welcome back, <?= htmlspecialchars($member['full_name'] ?? 'Member') ?>!</h1>
                            <p style="color: rgba(255,255,255,0.8); margin: 0;">Today is <?= date('F j, Y'); ?> — Access your digital ID, upcoming events, and official chapter activities.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="<?= BASE_URL ?>/public/portal/member/digital-id.php" class="btn btn-secondary">
                                <i class="fas fa-id-card"></i> View Digital ID
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="stats-grid mb-4">
                    <div class="stat-card">
                        <div class="stat-icon icon-gold">
                            <i class="fas fa-id-badge"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-label">Membership ID</div>
                            <div class="stat-value" style="font-size: 1.35rem; font-family: monospace;"><?= htmlspecialchars($member['membership_id'] ?? 'N/A') ?></div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon icon-blue">
                            <i class="fas fa-university"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-label">Institution</div>
                            <div class="stat-value" style="font-size: 1.15rem; font-weight: 700;"><?= htmlspecialchars($member['school_affiliate'] ?? 'N/A') ?></div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon <?= ($member['payment_status'] ?? false) ? 'icon-emerald' : 'icon-amber' ?>">
                            <i class="fas <?= ($member['payment_status'] ?? false) ? 'fa-check-circle' : 'fa-clock' ?>"></i>
                        </div>
                        <div class="stat-details">
                            <div class="stat-label">Payment Status</div>
                            <div class="stat-value" style="font-size: 1.35rem;">
                                <?php if ($member['payment_status'] ?? false): ?>
                                    <span class="text-success">Paid</span>
                                <?php else: ?>
                                    <span class="text-warning">Pending</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Upcoming Events -->
                    <div class="col-md-6 mb-4">
                        <div class="content-card card-navy-top h-100">
                            <h2>
                                <i class="fas fa-calendar-alt" style="color: var(--memsys-gold);"></i>
                                Upcoming Events
                            </h2>
                            <?php if (empty($events)): ?>
                                <div class="empty-state py-4">
                                    <i class="fas fa-calendar-times"></i>
                                    <p>No upcoming events at this time.</p>
                                </div>
                            <?php else: ?>
                                <div class="d-flex flex-column gap-3">
                                    <?php foreach ($events as $event): ?>
                                        <div class="p-3 border rounded-3 bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                                            <div>
                                                <h4 class="mb-1" style="font-size: 1.05rem;"><?= htmlspecialchars($event['title'] ?? 'Event') ?></h4>
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?= date('M d, Y - g:i A', strtotime($event['start_date'] ?? '')) ?>
                                                </small>
                                                <?php if ($event['venue'] ?? null): ?>
                                                    <small class="text-muted d-block">
                                                        <i class="fas fa-map-marker-alt me-1"></i>
                                                        <?= htmlspecialchars($event['venue']) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                            <a href="<?= BASE_URL ?>/public/portal/member/events.php" class="btn btn-sm btn-outline-primary">Register</a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Announcements -->
                    <div class="col-md-6 mb-4">
                        <div class="content-card card-gold-top h-100">
                            <h2>
                                <i class="fas fa-bullhorn" style="color: var(--memsys-gold);"></i>
                                Recent Announcements
                            </h2>
                            <?php if (empty($announcements)): ?>
                                <div class="empty-state py-4">
                                    <i class="fas fa-bell-slash"></i>
                                    <p>No recent announcements.</p>
                                </div>
                            <?php else: ?>
                                <div class="d-flex flex-column gap-3">
                                    <?php foreach ($announcements as $announcement): ?>
                                        <div class="p-3 border rounded-3 bg-light">
                                            <h4 class="mb-1" style="font-size: 1.05rem;"><?= htmlspecialchars($announcement['title'] ?? 'Announcement') ?></h4>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?= date('M d, Y', strtotime($announcement['created_at'] ?? '')) ?>
                                            </small>
                                            <p class="mb-0 mt-2 text-muted small">
                                                <?= substr(htmlspecialchars($announcement['content'] ?? ''), 0, 120) ?>...
                                            </p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
