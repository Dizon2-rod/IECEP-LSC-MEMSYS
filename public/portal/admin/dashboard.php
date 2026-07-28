<?php
require_once dirname(__DIR__, 1) . '/public/portal/auth_check.php';

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/middleware/auth.php';

// Check if user is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: /login.php');
    exit;
}

$current_page = 'dashboard';
$user = $_SESSION['user'];
$displayName = $user['user_metadata']['full_name'] ?? $user['email'] ?? 'Administrator';
$roleDisplay = 'Administrator';
$currentDate = date('F j, Y');

try {
    $supabase = new SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);
    $pendingAffiliations = $supabase->select('pending_affiliations', ['status' => 'eq.pending_review']);
    $pendingAffiliationsCount = is_array($pendingAffiliations) ? count($pendingAffiliations) : 0;
    $totalMembersData = $supabase->select('members', ['select' => 'id']); 
    $totalMembersCount = is_array($totalMembersData) ? count($totalMembersData) : 0;
    $institutionsData = $supabase->select('institutions', ['select' => 'id']);
    $totalSchoolsCount = is_array($institutionsData) ? count($institutionsData) : 0;
} catch (Exception $e) {
    $pendingAffiliationsCount = $totalMembersCount = $totalSchoolsCount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | IECEP-LSC MEMSYS</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/font-awesome.css">
    <!-- Supabase JS Client Library (for real-time subscriptions) -->
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    
    <!-- We remove professional.css if it contains global body/main-content styles 
         to prevent it from fighting with sidebar.php -->
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/dashboard.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/styles.css">

    <style>
        /* 
           SOCIALLY ISOLATED CSS 
           Lahat ng styles dito ay nagsisimula sa .dashboard-scope.
           WALA nang styles para sa body, html, o .main-content.
        */
        .dashboard-scope {
            font-family: 'Inter', sans-serif;
            color: #1E293B;
        }

        .dashboard-scope .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .dashboard-scope .header-title h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #0B1D4A;
            margin: 0;
        }

        .dashboard-scope .welcome-badge {
            background: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            color: #64748B;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #E2E8F0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dashboard-scope .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .dashboard-scope .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 1.25rem;
            transition: transform 0.2s ease;
            border: 1px solid #E2E8F0;
        }

        .dashboard-scope .stat-card:hover { transform: translateY(-5px); }

        .dashboard-scope .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: white;
        }

        .dashboard-scope .icon-blue { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .dashboard-scope .icon-indigo { background: linear-gradient(135deg, #6366f1, #4338ca); }
        .dashboard-scope .icon-amber { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .dashboard-scope .icon-emerald { background: linear-gradient(135deg, #10b981, #059669); }

        .dashboard-scope .stat-details h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            color: #0B1D4A;
        }

        .dashboard-scope .stat-details p {
            font-size: 0.875rem;
            color: #64748B;
            margin: 0;
        }

        .dashboard-scope .stat-card.warning-pulse {
            border: 2px solid #F59E0B;
            animation: pulse-border 2s infinite;
        }

        @keyframes pulse-border {
            0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(245, 158, 11, 0); }
            100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
        }

        .dashboard-scope .content-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            border: 1px solid #E2E8F0;
        }

        .dashboard-scope .content-card h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0B1D4A;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dashboard-scope .alert-banner {
            background: #EFF6FF;
            border-left: 4px solid #3B82F6;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            color: #1E40AF;
        }

        .dashboard-scope .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: #0B1D4A;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }

        .dashboard-scope .btn-primary:hover {
            background: #1E3A6E;
            box-shadow: 0 4px 12px rgba(11, 29, 74, 0.2);
        }

        @media (max-width: 768px) {
            .dashboard-scope .dashboard-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
        }
    </style>
</head>
<body>
    <!-- Dynamic Sidebar handles the layout (margin-left, body bg, etc.) -->
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>
    
    <!-- Main content is already styled as .main-content in sidebar.php -->
    <main class="main-content">
        <!-- Wrapper for Dashboard-specific styles only -->
        <div class="dashboard-scope">
            <header class="dashboard-header">
                <div class="header-title">
                    <h1>Admin Dashboard</h1>
                </div>
                <div class="welcome-badge">
                    <i class="fas fa-user-circle"></i>
                    <span><strong><?php echo htmlspecialchars($displayName); ?></strong> • <?php echo htmlspecialchars($roleDisplay); ?></span>
                </div>
                <div class="welcome-badge" style="margin-top: 0.75rem; background: rgba(212,175,55,0.12); color: #0B1D4A; box-shadow: none; border: 1px solid rgba(212,175,55,0.25);">
                    <i class="fas fa-calendar-alt"></i>
                    <span><?php echo htmlspecialchars($currentDate); ?> — Manage members, schools, finances, and compliance from here.</span>
                </div>
            </header>

            <div class="alert-banner">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>System Overview:</strong> Manage your membership base and school affiliations from this panel.
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon icon-blue"><i class="fas fa-users"></i></div>
                    <div class="stat-details">
                        <h3><?php echo $totalMembersCount; ?></h3>
                        <p>Total Members</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon icon-indigo"><i class="fas fa-university"></i></div>
                    <div class="stat-details">
                        <h3><?php echo $totalSchoolsCount; ?></h3>
                        <p>Affiliated Schools</p>
                    </div>
                </div>
                
                <div class="stat-card <?php echo $pendingAffiliationsCount > 0 ? 'warning-pulse' : ''; ?>">
                    <div class="stat-icon icon-amber"><i class="fas fa-clock"></i></div>
                    <div class="stat-details">
                        <h3 id="pending-affiliations-count"><?php echo $pendingAffiliationsCount; ?></h3>
                        <p>Pending Review</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon icon-emerald"><i class="fas fa-wallet"></i></div>
                    <div class="stat-details">
                        <h3>₱0.00</h3>
                        <p>Total Collections</p>
                    </div>
                </div>
            </div>

            <div class="content-card">
                <h2><i class="fas fa-tasks"></i> Priority Actions</h2>
                <div style="color: #64748B; line-height: 1.6;">
                    <p>You have <strong><?php echo $pendingAffiliationsCount; ?></strong> institutional affiliation requests awaiting your approval.</p>
                    
                    <div style="margin-top: 2rem; padding: 1.5rem; background: #F8FAFC; border-radius: 12px; border: 1px dashed #CBD5E1; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="color: #0B1D4A; display: block;">Affiliation Management</strong>
                            <span style="font-size: 0.85rem;">Review and approve school requests to enable student registration.</span>
                        </div>
                        <a href="<?php echo PORTAL_URL; ?>/admin/affiliations.php" class="btn-primary">
                            Manage Requests <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Provide IECEP_CONFIG for this standalone page (no head-meta.php)
        window.IECEP_CONFIG = window.IECEP_CONFIG || {
            SUPABASE_URL: <?php echo json_encode(SUPABASE_URL); ?>,
            SUPABASE_ANON_KEY: <?php echo json_encode(SUPABASE_ANON_KEY); ?>
        };
    </script>
    <script src="/IECEP-LSC-MEMSYS/public/assets/js/realtime.js" defer></script>
    <script src="/IECEP-LSC-MEMSYS/public/js/realtime.js" defer></script>
    <script>
    /**
     * Admin Dashboard — Realtime block
     * Subscribes to: pending_affiliations, members, institutions, transactions, attendance
     * Updates relevant stat-card h3 elements WITHOUT a page reload.
     */
    (function () {
        'use strict';

        // Current stat values seeded from PHP
        var _pendingCount  = <?php echo (int)$pendingAffiliationsCount; ?>;
        var _memberCount   = <?php echo (int)$totalMembersCount; ?>;
        var _schoolCount   = <?php echo (int)$totalSchoolsCount; ?>;

        function setEl(sel, value) {
            const el = document.querySelector(sel);
            if (el) el.textContent = value;
        }

        function bump(sel) {
            if (window.IECEP_REALTIME) IECEP_REALTIME.animateBump(sel);
        }

        // — Update pending count display AND warning-pulse class —
        function renderPending(n) {
            _pendingCount = Math.max(0, n);
            setEl('#pending-affiliations-count', _pendingCount);
            bump('#pending-affiliations-count');
            // Reflect warning-pulse on the parent stat-card
            const card = document.querySelector('#pending-affiliations-count')?.closest('.stat-card');
            if (card) {
                card.classList.toggle('warning-pulse', _pendingCount > 0);
            }
        }

        function boot() {
            const RT = window.IECEP_REALTIME;
            if (!RT) return;

            // ── pending_affiliations ──────────────────────────────────────
            RT.subscribe('pending_affiliations', ['INSERT', 'UPDATE', 'DELETE'], (payload) => {
                if (!RT.validatePayload(payload, ['id'])) return;

                if (payload.eventType === 'INSERT') {
                    renderPending(_pendingCount + 1);
                    RT.showToast('New affiliation application submitted', 'info');
                } else if (payload.eventType === 'UPDATE') {
                    // If moved out of pending_review, decrement
                    const rec = payload.new || {};
                    if (rec.status && rec.status !== 'pending_review') {
                        renderPending(_pendingCount - 1);
                    }
                } else if (payload.eventType === 'DELETE') {
                    renderPending(_pendingCount - 1);
                }
            });

            // ── members ───────────────────────────────────────────────
            RT.subscribe('members', ['INSERT', 'DELETE'], (payload) => {
                if (!RT.validatePayload(payload, ['id'])) return;
                _memberCount += payload.eventType === 'INSERT' ? 1 : -1;
                _memberCount = Math.max(0, _memberCount);
                // First stat-card h3 = total members
                const cards = document.querySelectorAll('.stat-card .stat-details h3');
                if (cards[0]) {
                    cards[0].textContent = _memberCount;
                    bump('.stat-card:nth-child(1) .stat-details h3');
                }
            });

            // ── institutions ────────────────────────────────────────────
            RT.subscribe('institutions', 'INSERT', (payload) => {
                if (!RT.validatePayload(payload, ['id'])) return;
                _schoolCount++;
                const cards = document.querySelectorAll('.stat-card .stat-details h3');
                if (cards[1]) {
                    cards[1].textContent = _schoolCount;
                    bump('.stat-card:nth-child(2) .stat-details h3');
                }
                RT.showToast('New institution affiliated', 'success');
            });

            // ── attendance (compliance impact) ───────────────────────
            RT.subscribe('attendance', ['INSERT', 'UPDATE', 'DELETE'], (payload) => {
                if (!RT.validatePayload(payload, ['id'])) return;
                // Attendance changes affect compliance display — re-fetch stats
                RT.refetchWithRetry(
                    '/IECEP-LSC-MEMSYS/public/api/compliance.php?summary=1',
                    () => { /* data consumed by compliance page */ },
                    'admin-attendance'
                );
            });

            // ── transactions (collections) ──────────────────────────
            RT.subscribe('transactions', 'INSERT', (payload) => {
                if (!RT.validatePayload(payload, ['id', 'amount'])) return;
                const rec = payload.new;
                if (rec.status === 'paid') {
                    // Update the Total Collections card (4th)
                    const cards = document.querySelectorAll('.stat-card .stat-details h3');
                    const collEl = cards[3];
                    if (collEl) {
                        const cur = parseFloat((collEl.textContent || '0').replace(/[^\d.]/g, '')) || 0;
                        const updated = cur + parseFloat(rec.amount || 0);
                        collEl.textContent = '₱' + updated.toLocaleString('en-PH', { minimumFractionDigits: 2 });
                        bump('.stat-card:nth-child(4) .stat-details h3');
                    }
                }
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
