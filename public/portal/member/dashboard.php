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
            <div class="container py-5">
                <div class="mb-4">
                    <h1 class="h2 mb-2">Welcome back, <?= htmlspecialchars($member['full_name'] ?? 'Member') ?>!</h1>
                    <p class="text-muted">Today is <?= date('F j, Y'); ?> — Manage your member profile, digital ID, events, and payments in one place.</p>
                </div>

                <!-- Summary Cards -->
                <div class="grid-responsive mb-5">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="fas fa-id-card fa-2x" style="color: var(--accent)"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Membership ID</h6>
                                    <strong><code><?= htmlspecialchars($member['membership_id'] ?? 'N/A') ?></code></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="fas fa-building fa-2x" style="color: var(--accent)"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Institution</h6>
                                    <strong><?= htmlspecialchars($member['school_affiliate'] ?? 'N/A') ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="fas fa-check-circle fa-2x" style="color: var(--accent)"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Payment Status</h6>
                                    <?php if ($member['payment_status'] ?? false): ?>
                                        <strong class="text-success">Paid</strong>
                                    <?php else: ?>
                                        <strong class="text-warning">Pending</strong>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="fas fa-qrcode fa-2x" style="color: var(--accent)"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">Digital ID</h6>
                                    <a href="/portal/member/digital-id.php" class="btn btn-sm btn-primary">View ID</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid-2">
                    <!-- Upcoming Events -->
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-4">
                                <i class="fas fa-calendar-alt me-2" style="color: var(--accent)"></i>
                                Upcoming Events
                            </h5>
                            <?php if (empty($events)): ?>
                                <p class="text-muted">No upcoming events at this time.</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($events as $event): ?>
                                        <div class="list-group-item">
                                            <h6 class="mb-1"><?= htmlspecialchars($event['title'] ?? 'Event') ?></h6>
                                            <small class="text-muted">
                                                <i class="fas fa-clock me-1"></i>
                                                <?= date('M d, Y - g:i A', strtotime($event['start_date'] ?? '')) ?>
                                            </small>
                                            <?php if ($event['venue'] ?? null): ?>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                    <?= htmlspecialchars($event['venue']) ?>
                                                </small>
                                            <?php endif; ?>
                                            <div class="mt-2">
                                                <a href="/portal/member/events.php" class="btn btn-sm btn-outline">Register</a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Announcements -->
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-4">
                                <i class="fas fa-bullhorn me-2" style="color: var(--accent)"></i>
                                Recent Announcements
                            </h5>
                            <?php if (empty($announcements)): ?>
                                <p class="text-muted">No recent announcements.</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($announcements as $announcement): ?>
                                        <div class="list-group-item">
                                            <h6 class="mb-1"><?= htmlspecialchars($announcement['title'] ?? 'Announcement') ?></h6>
                                            <small class="text-muted">
                                                <?= date('M d, Y', strtotime($announcement['created_at'] ?? '')) ?>
                                            </small>
                                            <p class="mb-0 mt-2 text-muted small">
                                                <?= substr(htmlspecialchars($announcement['content'] ?? ''), 0, 100) ?>...
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
