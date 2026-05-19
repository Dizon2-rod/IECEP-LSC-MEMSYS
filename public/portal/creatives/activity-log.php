<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../auth_check.php';
require_once INCLUDES_PATH . 'config.php';

// Access Control: Committee Head and Creatives members
require_role(['eb_pro_1', 'committee_creatives']);
$current_page = 'activity-log';

$user = get_user_info();
$displayName = $_SESSION['full_name'] ?? $_SESSION['email'] ?? $user['user_metadata']['full_name'] ?? $user['email'] ?? 'Creative';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log | IECEP-LSC</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/font-awesome.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>

    <style>
        :root {
            --c-navy: #0B1D4A;
            --c-gold: #D4AF37;
            --c-slate-50: #F8FAFC;
            --c-slate-100: #F1F5F9;
            --c-slate-200: #E2E8F0;
            --c-slate-400: #94A3B8;
            --c-slate-600: #475569;
            --c-slate-900: #0F172A;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Sidebar Fix */
        .sidebar { width: 260px !important; position: fixed !important; left: 0; top: 0; bottom: 0; z-index: 1000; }
        .main-content { margin-left: 260px !important; width: calc(100% - 260px) !important; min-height: 100vh; }

        .log-scope {
            font-family: 'Inter', sans-serif;
            background-color: var(--c-slate-50);
            padding: 2rem 3rem;
            color: var(--c-slate-900);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2.5rem;
        }

        .page-header h1 {
            font-size: 1.875rem;
            font-weight: 800;
            color: var(--c-navy);
            margin: 0;
        }

        .back-btn {
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--c-slate-600);
            font-size: 0.875rem;
            font-weight: 500;
            transition: var(--transition);
        }
        .back-btn:hover { color: var(--c-navy); }

        /* Activity Table Style */
        .activity-container {
            background: white;
            border-radius: 16px;
            border: 1px solid var(--c-slate-200);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .activity-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .activity-table th {
            background: var(--c-slate-100);
            padding: 1rem 1.5rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--c-slate-600);
            font-weight: 700;
            border-bottom: 1px solid var(--c-slate-200);
        }

        .activity-table td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--c-slate-50);
            font-size: 0.875rem;
            color: var(--c-slate-600);
        }

        .activity-table tr:hover {
            background-color: var(--c-slate-50);
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-success { background: rgba(16, 185, 129, 0.1); color: #059669; }
        .badge-warning { background: rgba(245, 158, 11, 0.1); color: #D97706; }

        .icon-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }

        @media (max-width: 768px) {
            .log-scope { padding: 1.5rem; }
            .sidebar { width: 80px !important; }
            .main-content { margin-left: 80px !important; width: calc(100% - 80px) !important; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <div class="log-scope">
            <header class="page-header">
                <div>
                    <a href="/IECEP-LSC-MEMSYS/public/portal/creatives/dashboard.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <h1>Creative Activity Log</h1>
                </div>
                <div class="actions">
                    <!-- Search bar for professional touch -->
                    <input type="text" id="activitySearch" placeholder="Search activities..." 
                           style="padding: 8px 16px; border-radius: 8px; border: 1px solid var(--c-slate-200); outline: none; font-size: 0.875rem;">
                </div>
            </header>

            <div class="activity-container">
                <table class="activity-table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Description</th>
                            <th>User</th>
                            <th>Date & Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="activity-list">
                        <!-- Data will be loaded here by JavaScript -->
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 3rem;">
                                <i class="fas fa-spinner fa-spin"></i> Loading activities...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        window.IECEP_CONFIG = {
            SUPABASE_URL: <?php echo json_encode(SUPABASE_URL); ?>,
            SUPABASE_ANON_KEY: <?php echo json_encode(SUPABASE_ANON_KEY); ?>
        };

        let supabase;

        // Wait for Supabase library to load
        function initSupabase() {
            if (typeof window.supabase === 'undefined' || typeof window.supabase.createClient === 'undefined') {
                console.log('Waiting for Supabase library...');
                setTimeout(initSupabase, 100);
                return;
            }

            supabase = window.supabase.createClient(
                window.IECEP_CONFIG.SUPABASE_URL,
                window.IECEP_CONFIG.SUPABASE_ANON_KEY
            );
            
            console.log('Supabase client initialized');
            fetchActivities();
        }

        async function fetchActivities() {
            const listElement = document.getElementById('activity-list');
            
            if (!supabase) {
                listElement.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:3rem;">Initializing...</td></tr>';
                return;
            }
            
            try {
                // Fetch from audit_logs table for all recent activities
                const { data, error } = await supabase
                    .from('audit_logs') 
                    .select('*')
                    .order('created_at', { ascending: false })
                    .limit(100);

                if (error) throw error;

                if (!data || data.length === 0) {
                    listElement.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:3rem;">No activities found.</td></tr>';
                    return;
                }

                listElement.innerHTML = data.map(item => {
                    const actionIcon = getActionIcon(item.action);
                    const statusBadge = getStatusBadge(item.action);
                    return `
                    <tr>
                        <td>
                            <div class="icon-circle" style="background: rgba(212, 175, 55, 0.1); color: var(--c-gold);">
                                <i class="fas ${actionIcon}"></i>
                            </div>
                            <strong>${item.action || 'Update'}</strong>
                        </td>
                        <td>${item.details || item.description || 'No description provided'}</td>
                        <td>${item.user_email || 'System'}</td>
                        <td>${new Date(item.created_at).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' })}</td>
                        <td><span class="status-badge ${statusBadge}">Completed</span></td>
                    </tr>
                `}).join('');

            } catch (err) {
                console.error('Error:', err);
                listElement.innerHTML = `<tr><td colspan="5" style="text-align:center; color: red; padding:3rem;">Error loading data: ${err.message}</td></tr>`;
            }
        }

        function getActionIcon(action) {
            const icons = {
                'login': 'fa-sign-in-alt',
                'logout': 'fa-sign-out-alt',
                'create': 'fa-plus-circle',
                'update': 'fa-edit',
                'delete': 'fa-trash',
                'upload': 'fa-upload',
                'download': 'fa-download',
                'approve': 'fa-check-circle',
                'reject': 'fa-times-circle'
            };
            return icons[action?.toLowerCase()] || 'fa-info-circle';
        }

        function getStatusBadge(action) {
            if (action?.toLowerCase().includes('delete') || action?.toLowerCase().includes('reject')) {
                return 'badge-warning';
            }
            return 'badge-success';
        }

        // Simple Search Filter
        document.getElementById('activitySearch').addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#activity-list tr');
            
            rows.forEach(row => {
                row.style.display = row.innerText.toLowerCase().includes(term) ? '' : 'none';
            });
        });

        // Initialize when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initSupabase);
        } else {
            initSupabase();
        }

        // Real-time subscription for new activities
        function setupRealtime() {
            if (!supabase) {
                setTimeout(setupRealtime, 500);
                return;
            }
            
            const channel = supabase
                .channel('activity-log-changes')
                .on('postgres_changes', {
                    event: 'INSERT',
                    schema: 'public',
                    table: 'audit_logs'
                }, (payload) => {
                    console.log('New activity:', payload);
                    fetchActivities(); // Refresh the list
                })
                .subscribe();
        }
        
        setupRealtime();
    </script>
</body>
</html>
