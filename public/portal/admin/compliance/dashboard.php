<?php
if (!isset($current_page)) { $current_page = basename(__FILE__, '.php'); }
require_once __DIR__ . '/../../auth_check.php';
require_role(['admin', 'super_admin', 'eb_president']);

require_once __DIR__ . '/../../../../includes/db.php';

$db = Database::getInstance();

// Get academic year range
$today = new DateTime();
$month = (int) $today->format('n');
$year = (int) $today->format('Y');

if ($month >= 7) {
    $startYear = $year;
    $endYear = $year + 1;
} else {
    $startYear = $year - 1;
    $endYear = $year;
}

$startDate = "$startYear-07-01";
$endDate = "$endYear-06-30";

// Get institutions
$institutions = $db->fetchAll("SELECT id, name, status, created_at FROM institutions ORDER BY name");

// Get member counts per institution
$memberCounts = $db->fetchAll("SELECT institution_id, COUNT(*) as count FROM members GROUP BY institution_id");
$memberCountMap = [];
foreach ($memberCounts as $mc) {
    $memberCountMap[$mc['institution_id']] = $mc['count'];
}

// Get events in academic year
$events = $db->fetchAll("SELECT id, institution_id, start_date, status FROM events WHERE start_date >= ? AND start_date <= ?", [$startDate, $endDate]);
$eventIds = array_column($events, 'id');
$eventsByInstitution = [];
foreach ($events as $event) {
    if ($event['institution_id']) {
        $eventsByInstitution[$event['institution_id']] = ($eventsByInstitution[$event['institution_id']] ?? 0) + 1;
    }
}

// Get attendance
$attendance = $db->fetchAll("SELECT ea.event_id, ea.member_id, ea.check_in_time, ea.created_at, m.institution_id 
    FROM event_attendees ea 
    JOIN members m ON ea.member_id = m.id
    WHERE ea.event_id IN ('" . implode("','", array_map([$db, 'escape'], $eventIds)) . "')");

$attendanceByInstitution = [];
$latestActivityByInstitution = [];
foreach ($attendance as $att) {
    $instId = $att['institution_id'];
    $eventId = $att['event_id'];
    $attendanceByInstitution[$instId][$eventId] = true;
    
    $activityTime = $att['check_in_time'] ?? $att['created_at'];
    if ($activityTime) {
        $currentLatest = $latestActivityByInstitution[$instId] ?? '1970-01-01 00:00:00';
        if ($activityTime > $currentLatest) {
            $latestActivityByInstitution[$instId] = $activityTime;
        }
    }
}

// Build compliance data
$complianceData = [];
$totalEvents = count($events);

foreach ($institutions as $inst) {
    $instId = $inst['id'];
    $attendedEvents = isset($attendanceByInstitution[$instId]) ? count($attendanceByInstitution[$instId]) : 0;
    $participationRate = $totalEvents > 0 ? min(100, round(($attendedEvents / $totalEvents) * 100, 1)) : 0;
    $hostedEvents = $eventsByInstitution[$instId] ?? 0;
    
    $statusLabel = 'Non-compliant';
    $badgeClass = 'danger';
    if ($inst['status'] === 'active' && $participationRate >= 75 && $hostedEvents > 0) {
        $statusLabel = 'Compliant';
        $badgeClass = 'success';
    } elseif ($inst['status'] === 'active') {
        $statusLabel = 'At Risk';
        $badgeClass = 'warning';
    }
    
    $complianceData[] = [
        'id' => $instId,
        'name' => $inst['name'],
        'status' => $inst['status'],
        'badge' => $badgeClass,
        'status_label' => $statusLabel,
        'member_count' => $memberCountMap[$instId] ?? 0,
        'participation_rate' => $participationRate,
        'hosted_events' => $hostedEvents,
        'last_activity' => $latestActivityByInstitution[$instId] ?? $inst['created_at'],
        'created_at' => $inst['created_at']
    ];
}

$summary = [
    'total_institutions' => count($institutions),
    'active_institutions' => count(array_filter($institutions, fn($i) => $i['status'] === 'active')),
    'total_members' => array_sum($memberCountMap),
    'average_participation' => count($institutions) > 0 ? round(array_sum(array_column($complianceData, 'participation_rate')) / count($institutions), 1) : 0
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compliance Dashboard - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/professional.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/font-awesome.css">
    <style>
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .summary-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            text-align: center;
        }
        .summary-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-navy);
        }
        .summary-label {
            font-size: var(--font-size-sm);
            color: var(--gray-600);
            margin-top: 0.5rem;
        }
        .table-container {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            background: var(--primary-navy);
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: var(--font-weight-semibold);
        }
        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-medium);
        }
        .status-compliant { background: var(--success-light); color: var(--success-dark); }
        .status-at-risk { background: var(--warning-light); color: var(--warning-dark); }
        .status-non-compliant { background: var(--error-light); color: var(--error-dark); }
        .progress-bar {
            height: 8px;
            background: var(--gray-200);
            border-radius: var(--radius-full);
            overflow: hidden;
            width: 100px;
        }
        .progress-fill {
            height: 100%;
            transition: width 0.3s;
        }
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1>Compliance Dashboard</h1>
                    <p class="text-gray">Monitor institution compliance, participation rates, and event hosting</p>
                </div>
                <div class="action-buttons">
                    <button onclick="location.reload()" class="btn btn-secondary">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <button onclick="exportCSV()" class="btn btn-secondary">
                        <i class="fas fa-download"></i> Export CSV
                    </button>
                </div>
            </div>

            <div class="summary-cards">
                <div class="summary-card">
                    <div class="summary-value"><?php echo $summary['total_institutions']; ?></div>
                    <div class="summary-label">Total Institutions</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value"><?php echo $summary['active_institutions']; ?></div>
                    <div class="summary-label">Active Institutions</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value"><?php echo $summary['total_members']; ?></div>
                    <div class="summary-label">Total Members</div>
                </div>
                <div class="summary-card">
                    <div class="summary-value"><?php echo $summary['average_participation']; ?>%</div>
                    <div class="summary-label">Avg Participation</div>
                </div>
            </div>

            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Institution</th>
                            <th>Status</th>
                            <th>Members</th>
                            <th>Participation Rate</th>
                            <th>Hosted Events</th>
                            <th>Last Activity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($complianceData)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 2rem;">No compliance data available</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($complianceData as $inst): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($inst['name']); ?></strong></td>
                                <td>
                                    <span class="status-badge status-<?php echo str_replace(' ', '-', $inst['status_label']); ?>">
                                        <?php echo htmlspecialchars($inst['status_label']); ?>
                                    </span>
                                </td>
                                <td><?php echo $inst['member_count']; ?> members</td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $inst['participation_rate']; ?>%; background: <?php echo $inst['participation_rate'] >= 75 ? 'var(--success)' : ($inst['participation_rate'] >= 50 ? 'var(--warning)' : 'var(--error)'); ?>;"></div>
                                        </div>
                                        <small><?php echo $inst['participation_rate']; ?>%</small>
                                    </div>
                                </td>
                                <td><?php echo $inst['hosted_events']; ?></td>
                                <td><?php echo $inst['last_activity'] ? date('M j, Y', strtotime($inst['last_activity'])) : 'N/A'; ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button onclick="viewDetails('<?php echo $inst['id']; ?>')" class="btn btn-sm btn-secondary" title="View">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="sendReminder('<?php echo $inst['id']; ?>')" class="btn btn-sm btn-primary" title="Send Reminder">
                                            <i class="fas fa-bell"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Supabase CDN + realtime engine -->
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <script>
        window.IECEP_CONFIG = window.IECEP_CONFIG || {
            SUPABASE_URL: <?php echo json_encode(SUPABASE_URL); ?>,
            SUPABASE_ANON_KEY: <?php echo json_encode(SUPABASE_ANON_KEY); ?>
        };
    </script>
    <script src="/IECEP-LSC-MEMSYS/public/assets/js/realtime.js" defer></script>
    <script src="/IECEP-LSC-MEMSYS/public/js/realtime.js" defer></script>

    <script>
        function exportCSV() {
            const data = <?php echo json_encode($complianceData); ?>;
            const csv = [
                ['Institution', 'Status', 'Members', 'Participation Rate', 'Hosted Events', 'Last Activity'],
                ...data.map(item => [
                    item.name,
                    item.status_label,
                    item.member_count,
                    item.participation_rate + '%',
                    item.hosted_events,
                    item.last_activity ? new Date(item.last_activity).toLocaleDateString() : 'N/A'
                ])
            ].map(row => row.map(value => `"${value}"`).join(',')).join('\n');

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `compliance-report-${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function viewDetails(id) {
            alert('View details for institution: ' + id);
        }

        function sendReminder(id) {
            if (confirm('Send compliance reminder to this institution?')) {
                fetch('<?php echo BASE_URL; ?>/api/send-reminder.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ institution_id: id, type: 'compliance' })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Reminder sent successfully!');
                    } else {
                        alert('Error: ' + data.error);
                    }
                });
            }
        }
    </script>

    <!-- Realtime script for compliance page -->
    <script>
    /**
     * Compliance Dashboard — Realtime block
     * Subscribes to: attendance (INSERT/UPDATE/DELETE), events (INSERT/UPDATE)
     * On change: reload page to re-calculate compliance from MySQL database.
     */
    (function () {
        'use strict';

        function boot() {
            const RT = window.IECEP_REALTIME;
            if (!RT) return;

            // — attendance changes affect compliance —————————————
            RT.subscribe('attendance', ['INSERT', 'UPDATE', 'DELETE'], (payload) => {
                if (!RT.validatePayload(payload, ['id'])) return;
                RT.showToast('Attendance updated — refreshing compliance data', 'info');
                // Reload page to recalculate compliance from MySQL
                setTimeout(() => location.reload(), 1000);
            });

            // — events changes affect compliance ————————————————
            RT.subscribe('events', ['INSERT', 'UPDATE', 'DELETE'], (payload) => {
                if (!RT.validatePayload(payload, ['id'])) return;
                RT.showToast('Events updated — refreshing compliance data', 'info');
                // Reload page to recalculate compliance from MySQL
                setTimeout(() => location.reload(), 1000);
            });
        }

        if (window.IECEP_REALTIME) {
            boot();
        } else {
            window.addEventListener('iecep:realtime:ready', boot, { once: true });
        }
    })();
    </script>
</body>
</html>
