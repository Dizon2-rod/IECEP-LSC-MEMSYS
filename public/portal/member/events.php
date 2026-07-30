<?php
require_once __DIR__ . '/../auth_check.php';
require_role(['member']);

require_once __DIR__ . '/../../../includes/role-config.php';
require_once __DIR__ . '/../../../bootstrap.php';

$current_page = 'events';

$user = get_user_info();
$member_id = $_SESSION['member_id'] ?? $user['member_id'] ?? null;

if (!$member_id) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$supabase = new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);

// Fetch upcoming events
try {
    $events = $supabase->select('events', [
        'is_public' => 'eq.true',
        'start_date' => 'gte.' . date('c'),
        'order' => 'start_date.asc'
    ]);
} catch (Exception $e) {
    $events = [];
}

// Fetch member's registered events
try {
    $registrations = $supabase->select('event_registrations', [
        'member_id' => 'eq.' . $member_id
    ]);
    $registeredEventIds = array_column($registrations, 'event_id');
} catch (Exception $e) {
    $registeredEventIds = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/../../includes/head-meta.php'; ?>
    <title>Events - Member Portal</title>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="container py-5">
                <div class="mb-4">
                    <h1 class="h2 mb-2">Events</h1>
                    <p class="text-muted">Register for upcoming IECEP-LSC events</p>
                </div>

                <?php if (empty($events)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No upcoming events at this time. Check back later for new events.
                    </div>
                <?php else: ?>
                    <div class="grid-responsive">
                        <?php foreach ($events as $event): ?>
                            <?php
                                $isRegistered = in_array($event['id'] ?? '', $registeredEventIds);
                                $startDate = $event['start_date'] ?? '';
                                $endDate = $event['end_date'] ?? '';
                            ?>
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <span class="badge bg-primary">
                                            <?= htmlspecialchars($event['event_type'] ?? 'General') ?>
                                        </span>
                                        <?php if ($isRegistered): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle me-1"></i>Registered
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <h5 class="card-title mb-2"><?= htmlspecialchars($event['title'] ?? 'Event Title') ?></h5>
                                    <p class="text-muted small mb-3">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?= date('F d, Y', strtotime($startDate)) ?>
                                        <?php if ($endDate && $endDate !== $startDate): ?>
                                            - <?= date('F d, Y', strtotime($endDate)) ?>
                                        <?php endif; ?>
                                        <br>
                                        <i class="fas fa-clock me-1"></i>
                                        <?= date('g:i A', strtotime($startDate)) ?>
                                        <?php if ($event['end_time'] ?? null): ?>
                                            - <?= date('g:i A', strtotime($event['end_time'])) ?>
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($event['venue'] ?? null): ?>
                                        <p class="text-muted small mb-3">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?= htmlspecialchars($event['venue']) ?>
                                        </p>
                                    <?php endif; ?>
                                    <p class="text-muted small mb-4">
                                        <?= substr(htmlspecialchars($event['description'] ?? ''), 0, 100) ?>...
                                    </p>
                                    <?php if ($isRegistered): ?>
                                        <button class="btn btn-outline w-100" disabled>
                                            <i class="fas fa-check me-2"></i>Already Registered
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-primary w-100" onclick="registerEvent('<?= htmlspecialchars($event['id'] ?? '') ?>')">
                                            <i class="fas fa-user-plus me-2"></i>Register
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        async function registerEvent(eventId) {
            if (!confirm('Are you sure you want to register for this event?')) {
                return;
            }

            try {
                const response = await fetch('/api/register-event.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        event_id: eventId
                    })
                });

                const result = await response.json();

                if (result.success) {
                    alert('You have been successfully registered for this event!');
                    location.reload();
                } else {
                    alert('Registration failed: ' + (result.message || 'Unknown error'));
                }
            } catch (error) {
                alert('An error occurred: ' + error.message);
            }
        }
    </script>
</body>
</html>

