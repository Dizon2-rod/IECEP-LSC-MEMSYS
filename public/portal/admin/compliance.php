<?php
require_once __DIR__ . '/../bootstrap.php';
$current_page = basename(__FILE__, '.php');
require_once '../../includes/config.php';
require_once '../../includes/database.php';
require_once '../../includes/role-config.php';
require_once '../auth_check.php';

require_role(['admin', 'eb_president', 'super_admin']);

$pageTitle = 'Compliance Dashboard';
include '../../includes/dashboard-layout.php';

function getAcademicYearRange(): array
{
    $today = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $year = (int) $today->format('Y');
    $month = (int) $today->format('n');

    if ($month >= 7) {
        $startYear = $year;
        $endYear = $year + 1;
    } else {
        $startYear = $year - 1;
        $endYear = $year;
    }

    return [
        'start' => sprintf('%s-07-01T00:00:00Z', $startYear),
        'end' => sprintf('%s-06-30T23:59:59Z', $endYear),
    ];
}

$complianceData = [];
$summary = [
    'total_institutions' => 0,
    'active_institutions' => 0,
    'total_members' => 0,
    'average_participation' => 0,
];

try {
    $range = getAcademicYearRange();
    $sb = new \App\Lib\Supabase();

    $institutionsResp = $sb->from('institutions')
        ->select('id,name,status,created_at')
        ->order('name')
        ->get(true);

    $institutions = $institutionsResp['error'] ? [] : ($institutionsResp['data'] ?? []);

    $membersResp = $sb->from('members')
        ->select('id,institution_id')
        ->get(true);
    $members = $membersResp['error'] ? [] : ($membersResp['data'] ?? []);

    $memberCounts = [];
    $memberInstitutionById = [];
    foreach ($members as $member) {
        $institutionId = $member['institution_id'] ?? null;
        if (!$institutionId) {
            continue;
        }
        $memberCounts[$institutionId] = ($memberCounts[$institutionId] ?? 0) + 1;
        $memberInstitutionById[$member['id']] = $institutionId;
    }

    $eventsResp = $sb->from('events')
        ->select('id,institution_id,start_date,status')
        ->gte('start_date', $range['start'])
        ->lte('start_date', $range['end'])
        ->get(true);
    $events = $eventsResp['error'] ? [] : ($eventsResp['data'] ?? []);

    $eventIds = array_values(array_filter(array_map(fn($event) => $event['id'] ?? null, $events)));
    $eventsByInstitution = [];
    foreach ($events as $event) {
        $institutionId = $event['institution_id'] ?? null;
        if (!$institutionId) {
            continue;
        }
        $eventsByInstitution[$institutionId] = ($eventsByInstitution[$institutionId] ?? 0) + 1;
    }

    $attendanceEventMap = [];
    $attendanceResp = ['error' => false, 'data' => []];
    if (!empty($eventIds)) {
        $attendanceResp = $sb->from('attendance')
            ->select('event_id,member_id,check_in_time,created_at')
            ->in('event_id', $eventIds)
            ->get(true);
    }
    $attendanceRows = $attendanceResp['error'] ? [] : ($attendanceResp['data'] ?? []);

    $attendanceEventsByInstitution = [];
    $latestActivityByInstitution = [];
    foreach ($attendanceRows as $attendance) {
        $memberId = $attendance['member_id'] ?? null;
        $eventId = $attendance['event_id'] ?? null;
        if (!$memberId || !$eventId || !isset($memberInstitutionById[$memberId])) {
            continue;
        }

        $institutionId = $memberInstitutionById[$memberId];
        $attendanceEventsByInstitution[$institutionId][$eventId] = true;

        $activityTime = $attendance['check_in_time'] ?? $attendance['created_at'] ?? null;
        if ($activityTime) {
            $currentLatest = $latestActivityByInstitution[$institutionId] ?? '1970-01-01T00:00:00Z';
            if ($activityTime > $currentLatest) {
                $latestActivityByInstitution[$institutionId] = $activityTime;
            }
        }
    }

    $institutionsData = [];
    foreach ($institutions as $institution) {
        $instId = $institution['id'] ?? null;
        if (!$instId) {
            continue;
        }

        $totalEvents = count($events);
        $attendedEvents = isset($attendanceEventsByInstitution[$instId]) ? count($attendanceEventsByInstitution[$instId]) : 0;
        $participationRate = $totalEvents > 0 ? min(100, round(($attendedEvents / $totalEvents) * 100, 1)) : 0;
        $hostedEvents = $eventsByInstitution[$instId] ?? 0;

        $statusLabel = 'Non-compliant';
        $badgeClass = 'danger';
        if (($institution['status'] ?? '') === 'active' && $participationRate >= 75 && $hostedEvents > 0) {
            $statusLabel = 'Compliant';
            $badgeClass = 'success';
        } elseif (($institution['status'] ?? '') === 'active') {
            $statusLabel = 'At Risk';
            $badgeClass = 'warning';
        }

        $institutionsData[] = [
            'id' => $instId,
            'name' => $institution['name'] ?? 'Unknown',
            'status' => $institution['status'] ?? 'unknown',
            'badge' => $badgeClass,
            'status_label' => $statusLabel,
            'member_count' => $memberCounts[$instId] ?? 0,
            'participation_rate' => $participationRate,
            'hosted_events' => $hostedEvents,
            'last_activity' => $latestActivityByInstitution[$instId] ?? $institution['created_at'] ?? null,
            'created_at' => $institution['created_at'] ?? null,
        ];
    }

    $summary['total_institutions'] = count($institutionsData);
    $summary['active_institutions'] = count(array_filter($institutionsData, fn($row) => $row['status'] === 'active'));
    $summary['total_members'] = array_sum(array_column($institutionsData, 'member_count'));
    $summary['average_participation'] = $summary['total_institutions'] > 0 ? round(array_sum(array_column($institutionsData, 'participation_rate')) / $summary['total_institutions'], 1) : 0;
    $complianceData = $institutionsData;
} catch (Exception $e) {
    error_log('Error fetching compliance data: ' . $e->getMessage());
    $complianceData = [];
}
?>

<div class="dashboard-container">
    <?php include '../../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Compliance Dashboard</h1>
                    <p class="text-muted">Monitor institution status, hosted event participation, and academic year compliance</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="refreshData()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh
                    </button>
                    <button class="btn btn-outline-secondary" onclick="exportReport()">
                        <i class="fas fa-download me-2"></i>Export CSV
                    </button>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-building fa-2x text-primary mb-2"></i>
                            <h4 class="mb-0"><?= $summary['total_institutions'] ?></h4>
                            <small class="text-muted">Total Institutions</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <h4 class="mb-0"><?= $summary['active_institutions'] ?></h4>
                            <small class="text-muted">Active Institutions</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-users fa-2x text-info mb-2"></i>
                            <h4 class="mb-0"><?= $summary['total_members'] ?></h4>
                            <small class="text-muted">Total Members</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-chart-line fa-2x text-warning mb-2"></i>
                            <h4 class="mb-0"><?= $summary['average_participation'] ?>%</h4>
                            <small class="text-muted">Avg Participation</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Institution Compliance Overview</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Institution</th>
                                    <th>Status</th>
                                    <th>Members</th>
                                    <th>Participation</th>
                                    <th>Hosted Events</th>
                                    <th>Last Activity</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($complianceData as $institution): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($institution['name']) ?></strong></td>
                                        <td><span class="badge bg-<?= $institution['badge'] ?>"><?= htmlspecialchars($institution['status_label']) ?></span></td>
                                        <td><span class="badge bg-info"><?= $institution['member_count'] ?> members</span></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                    <div class="progress-bar bg-<?= $institution['participation_rate'] >= 75 ? 'success' : ($institution['participation_rate'] >= 50 ? 'warning' : 'danger') ?>"
                                                         style="width: <?= $institution['participation_rate'] ?>%"></div>
                                                </div>
                                                <small class="text-muted"><?= $institution['participation_rate'] ?>%</small>
                                            </div>
                                        </td>
                                        <td><?= $institution['hosted_events'] ?></td>
                                        <td><small class="text-muted"><?= $institution['last_activity'] ? date('M j, Y', strtotime($institution['last_activity'])) : 'N/A' ?></small></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary btn-sm" onclick="viewDetails('<?= $institution['id'] ?>')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-warning btn-sm" onclick="sendReminder('<?= $institution['id'] ?>', '<?= htmlspecialchars($institution['name'], ENT_QUOTES) ?>')">
                                                    <i class="fas fa-bell"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (empty($complianceData)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                            <h5>No Compliance Data Available</h5>
                            <p class="text-muted">Compliance data will appear here once institutions and attendance records are available.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Institution Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="institutionDetails">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function refreshData() {
    location.reload();
}

function exportReport() {
    const data = <?= json_encode($complianceData) ?>;
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

function viewDetails(institutionId) {
    const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    const detailsDiv = document.getElementById('institutionDetails');
    detailsDiv.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';

    fetch(`/IECEP-LSC-MEMSYS/public/api/institution-details.php?id=${encodeURIComponent(institutionId)}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                detailsDiv.innerHTML = `<div class="alert alert-danger">${data.message || 'Unable to load institution details.'}</div>`;
                return;
            }

            const institution = data.institution || {};
            detailsDiv.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Institution Information</h6>
                        <p><strong>Name:</strong> ${institution.name || 'N/A'}</p>
                        <p><strong>Status:</strong> <span class="badge bg-${institution.status === 'active' ? 'success' : 'warning'}">${institution.status || 'N/A'}</span></p>
                        <p><strong>Members:</strong> ${institution.member_count || 0}</p>
                        <p><strong>Joined:</strong> ${institution.created_at ? new Date(institution.created_at).toLocaleDateString() : 'N/A'}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Compliance Metrics</h6>
                        <p><strong>Participation Rate:</strong> ${institution.participation_rate || 0}%</p>
                        <p><strong>Events Attended:</strong> ${institution.attendance_count || 0}</p>
                        <p><strong>Last Activity:</strong> ${institution.latest_activity ? new Date(institution.latest_activity).toLocaleDateString() : 'N/A'}</p>
                    </div>
                </div>
            `;
        })
        .catch(() => {
            detailsDiv.innerHTML = '<div class="alert alert-danger">Error loading institution details.</div>';
        });
    modal.show();
}

function sendReminder(institutionId, institutionName) {
    if (!confirm(`Send compliance reminder to ${institutionName}?`)) {
        return;
    }

    fetch('/IECEP-LSC-MEMSYS/public/api/send-reminder.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ institution_id: institutionId, type: 'compliance' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            IECEP_Toast.show('Reminder sent successfully!', 'success');
        } else {
            IECEP_Toast.show('Failed to send reminder.', 'error');
        }
    })
    .catch(() => {
        IECEP_Toast.show('Error sending reminder.', 'error');
    });
}
</script>

<?php include '../../includes/footer.php'; ?>
