<?php
if (!isset($current_page)) { $current_page = 'profile'; }
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/role-config.php';

require_role(['school_officer', 'admin', 'super_admin']);

$user = $_SESSION['user'] ?? [];
$userId = $user['id'] ?? $_SESSION['user_id'] ?? null;
$institutionId = $_SESSION['institution_id'] ?? $user['institution_id'] ?? null;

$supabase = getSupabaseClient() ?? new \App\Lib\SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);
$school = [];

if ($institutionId && $supabase) {
    try {
        $schoolData = $supabase->select('institutions', [
            'id' => 'eq.' . $institutionId,
            'limit' => 1
        ]);
        if (is_array($schoolData) && isset($schoolData[0])) {
            $school = $schoolData[0];
        }
    } catch (Exception $e) {}
}

if (empty($school) && !empty($user['email']) && $supabase) {
    try {
        $schoolData = $supabase->select('institutions', [
            'email' => 'eq.' . $user['email'],
            'limit' => 1
        ]);
        if (is_array($schoolData) && isset($schoolData[0])) {
            $school = $schoolData[0];
        }
    } catch (Exception $e) {}
}

if (empty($school) && $supabase) {
    try {
        $schoolData = $supabase->select('institutions', [
            'status' => 'eq.active',
            'limit' => 1
        ]);
        if (is_array($schoolData) && isset($schoolData[0])) {
            $school = $schoolData[0];
        }
    } catch (Exception $e) {}
}

if (empty($school)) {
    $school = [
        'name' => 'Laguna State Polytechnic University - Santa Cruz Campus',
        'acronym' => 'LSPU-SCC',
        'type' => 'State University',
        'address' => 'Brgy. Bubukal',
        'city' => 'Santa Cruz',
        'province' => 'Laguna',
        'contact_person' => $user['user_metadata']['full_name'] ?? $user['name'] ?? 'Chapter President',
        'email' => $user['email'] ?? 'lspu.chapter@iecep-lsc.org',
        'phone' => '+63 (049) 501-1234',
        'website' => 'https://lspu.edu.ph',
        'status' => 'active'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chapter Profile — IECEP-LSC MEMSYS</title>
    <?php require_once __DIR__ . '/../../../includes/head-meta.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/css/admin-portal.css">
    <style>
        :root {
            --bg-page: #F8FAFC;
            --bg-surface: #FFFFFF;
            --border-light: #E2E8F0;
            --text-heading: #0B1D4A;
            --text-primary: #0F172A;
            --text-muted: #64748B;
        }

        body {
            background-color: var(--bg-page) !important;
            font-family: 'DM Sans', 'Inter', -apple-system, sans-serif;
            color: var(--text-primary);
        }

        .profile-white-card {
            background: #FFFFFF;
            border: 1px solid var(--border-light);
            border-radius: 16px;
            padding: 1.75rem 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
        }

        .table-profile th {
            width: 28%;
            background: #F8FAFC;
            color: var(--text-muted);
            font-weight: 700;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 0.85rem 1.25rem;
            border-bottom: 1px solid var(--border-light);
        }

        .table-profile td {
            padding: 0.85rem 1.25rem;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-light);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php require_once __DIR__ . '/../../../includes/sidebar.php'; ?>

        <main class="main-content ap-scope">
            <div class="container py-4">
                <!-- Clean Page Header -->
                <div class="ap-page-header">
                    <div class="ap-title-block">
                        <div class="text-muted small mb-1">
                            <a href="<?= BASE_URL ?>/public/portal/school-officer/dashboard.php" class="text-muted text-decoration-none">School Portal</a>
                            <span class="mx-1">/</span>
                            <span class="text-dark fw-bold">Institutional Profile</span>
                        </div>
                        <h1 class="ap-page-title">
                            <i class="fas fa-university text-primary"></i> Institutional School Chapter Profile
                        </h1>
                        <p class="ap-page-subtitle">
                            Accredited Chapter Information • IECEP Laguna Section Chapter Registry
                        </p>
                    </div>
                    <div class="ap-header-actions">
                        <span class="badge bg-success px-3 py-2">
                            <i class="fas fa-check-circle me-1"></i> Active Charter
                        </span>
                        <a href="<?= BASE_URL ?>/public/portal/school-officer/dashboard.php" class="ap-btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Dashboard
                        </a>
                    </div>
                </div>

                <!-- 3 KPI Stat Cards -->
                <div class="ap-kpi-grid-3 mb-4">
                    <div class="ap-stat-card">
                        <div class="ap-stat-header">
                            <div class="ap-stat-icon navy"><i class="fas fa-school"></i></div>
                            <div class="ap-stat-title">Chapter Code</div>
                        </div>
                        <div class="ap-stat-val"><?= htmlspecialchars($school['acronym'] ?? 'CHAPTER') ?></div>
                        <div class="small text-muted mt-1">Official chapter identifier</div>
                    </div>

                    <div class="ap-stat-card">
                        <div class="ap-stat-header">
                            <div class="ap-stat-icon emerald"><i class="fas fa-shield-alt"></i></div>
                            <div class="ap-stat-title">Accreditation Status</div>
                        </div>
                        <div class="ap-stat-val text-success">Accredited</div>
                        <div class="small text-muted mt-1">Good chapter standing</div>
                    </div>

                    <div class="ap-stat-card">
                        <div class="ap-stat-header">
                            <div class="ap-stat-icon gold"><i class="fas fa-map-marker-alt"></i></div>
                            <div class="ap-stat-title">Jurisdiction</div>
                        </div>
                        <div class="ap-stat-val" style="color: #B8860B; font-size: 1.25rem;">Laguna Section</div>
                        <div class="small text-muted mt-1">Region IV-A CALABARZON</div>
                    </div>
                </div>

                <!-- Profile Information Card -->
                <div class="profile-white-card">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <h4 class="fw-bold text-dark mb-0" style="font-size: 1.15rem;">
                            <i class="fas fa-info-circle text-primary me-2"></i>Institutional Accreditation Details
                        </h4>
                        <span class="badge bg-light text-dark border">AY <?= date('Y') ?>–<?= date('Y') + 1 ?></span>
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle table-profile mb-0">
                            <tbody>
                                <tr>
                                    <th>School / University</th>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($school['name'] ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <th>Chapter Acronym</th>
                                    <td><span class="badge bg-primary px-3 py-2"><?= htmlspecialchars($school['acronym'] ?? 'N/A') ?></span></td>
                                </tr>
                                <tr>
                                    <th>Institution Type</th>
                                    <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $school['type'] ?? 'State University'))) ?></td>
                                </tr>
                                <tr>
                                    <th>Campus Address</th>
                                    <td><?= htmlspecialchars($school['address'] ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <th>Municipality / City</th>
                                    <td><?= htmlspecialchars($school['city'] ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <th>Province</th>
                                    <td><?= htmlspecialchars($school['province'] ?? 'Laguna') ?></td>
                                </tr>
                                <tr>
                                    <th>Lead Chapter Representative</th>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($school['contact_person'] ?? 'Chapter Officer') ?></td>
                                </tr>
                                <tr>
                                    <th>Official Contact Email</th>
                                    <td><code class="fw-bold text-dark"><?= htmlspecialchars($school['contact_email'] ?? $school['email'] ?? 'N/A') ?></code></td>
                                </tr>
                                <tr>
                                    <th>Official Contact Phone</th>
                                    <td><?= htmlspecialchars($school['contact_phone'] ?? $school['phone'] ?? '—') ?></td>
                                </tr>
                                <tr>
                                    <th>Official Institutional Website</th>
                                    <td>
                                        <?php if (!empty($school['website'])): ?>
                                            <a href="<?= htmlspecialchars($school['website']) ?>" target="_blank" class="fw-bold text-primary text-decoration-none">
                                                <?= htmlspecialchars($school['website']) ?> <i class="fas fa-external-link-alt small ms-1"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
