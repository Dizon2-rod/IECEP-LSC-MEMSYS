<?php
require_once __DIR__ . '/../auth_check.php';
require_role(['eb_president', 'admin']);

require_once __DIR__ . '/../../../src/lib/SupabaseClient.php';

// Load Supabase credentials
$supabaseConfig = require __DIR__ . '/../../../src/config/supabase.php';

$user = get_user_info();

$pendingAffiliationsCount = 0;
try {
    $supabase = new SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);
    $pendingAffiliations = $supabase->select('pending_affiliations', ['status' => 'eq.pending_review']);
    if (is_array($pendingAffiliations)) {
        $pendingAffiliationsCount = count($pendingAffiliations);
    }
} catch (Exception $e) {
    $pendingAffiliationsCount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - IECEP-LSC MEMSYS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --navy: #0A2F6C;
            --gold: #F5A623;
            --gray-50: #f8fafc;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-50);
            color: #334155;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }

        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: var(--navy);
            font-size: 1.8rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--navy);
            font-weight: 600;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid var(--gold);
        }

        .stat-card h3 {
            color: var(--navy);
            font-size: 2rem;
            margin-bottom: 5px;
        }

        .stat-card p {
            color: #64748b;
            font-size: 0.9rem;
        }

        .content-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .content-card h2 {
            color: var(--navy);
            margin-bottom: 20px;
        }

        .btn-logout {
            background: #dc2626;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .btn-logout:hover {
            background: #b91c1c;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../sidebar_admin.php'; ?>

        <div class="main-content">
            <div class="header">
                <h1>Admin Dashboard</h1>
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($user['email']); ?>!</span>
                    <div class="user-avatar"><?php echo strtoupper(substr($user['email'], 0, 1)); ?></div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>150</h3>
                    <p>Total Members</p>
                </div>
                <div class="stat-card">
                    <h3>12</h3>
                    <p>Schools</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo htmlspecialchars($pendingAffiliationsCount); ?></h3>
                    <p>Pending Affiliations</p>
                </div>
                <div class="stat-card">
                    <h3>₱125,000</h3>
                    <p>Total Collections</p>
                </div>
            </div>

            <div class="content-card">
                <h2>Recent Activities</h2>
                <p>Welcome to the Admin Dashboard. From here you can manage users, monitor system activities, and oversee all IECEP-LSC operations.</p>
                <p style="margin-top:18px; font-weight:600;">Pending affiliation requests: <?php echo htmlspecialchars($pendingAffiliationsCount); ?></p>
                <p><a href="affiliations.php" style="display:inline-block; margin-top:12px; padding:10px 16px; background:var(--navy); color:white; border-radius:8px; text-decoration:none;">View Affiliation Requests</a></p>
            </div>
        </div>
    </div>
</body>
</html>
