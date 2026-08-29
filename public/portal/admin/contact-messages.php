<?php
require_once __DIR__ . '/../bootstrap.php';
$current_page = 'contact-messages';

require_once __DIR__ . '/../auth_check.php';
require_role(['admin', 'super_admin', 'eb_president', 'eb_secretary', 'registration']);

$pageTitle = 'Public Inquiries & Contact Messages';
$supabase = getSupabaseClient();

$feedbackMsg = '';
$feedbackType = 'success';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $messageId = $_POST['message_id'] ?? '';
    $status = $_POST['status'] ?? 'read';
    if ($messageId) {
        try {
            $supabase->update('contact_messages', ['status' => $status, 'updated_at' => date('c')], $messageId);
            $feedbackMsg = "Message marked as " . ucfirst($status) . "!";
            $feedbackType = 'success';
        } catch (Exception $e) {
            error_log("Update status error: " . $e->getMessage());
        }
    }
}

// Fetch real contact messages
$messages = [];
try {
    $msgData = $supabase->select('contact_messages', ['select' => '*', 'order' => 'created_at.desc']);
    if (is_array($msgData)) {
        $messages = $msgData;
    }
} catch (Exception $e) {
    error_log("Contact messages query error: " . $e->getMessage());
}

$unreadCount = 0;
$readCount = 0;
foreach ($messages as $m) {
    $st = strtolower($m['status'] ?? 'unread');
    if ($st === 'unread' || $st === 'new') $unreadCount++;
    else $readCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= htmlspecialchars($pageTitle) ?> — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Manage public inquiries, prospective chapter messages, and external communication tickets.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --color-navy: #0B1D4A;
            --color-navy-hover: #152C6E;
            --color-blue: #2563EB;
            --color-gold: #D4AF37;
            --color-emerald: #059669;
            --color-amber: #D97706;
            --bg-page: #F8FAFC;
            --border-color: #E2E8F0;
            --shadow-card: 0 1px 3px 0 rgba(0, 0, 0, 0.04), 0 1px 2px -1px rgba(0, 0, 0, 0.04);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-page);
            color: #1E293B;
            margin: 0;
            padding: 0;
        }

        .dash-header-banner {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 0.85rem 1.25rem;
            margin-bottom: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.75rem;
            box-shadow: var(--shadow-card);
        }
        .dash-header-title {
            margin: 0 0 0.15rem;
            font-size: 1.25rem;
            font-weight: 800;
            color: #0F172A;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .dash-header-sub {
            margin: 0;
            font-size: 0.8rem;
            color: #64748B;
        }

        .btn-white {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.42rem 0.85rem;
            border-radius: 7px;
            font-size: 0.78rem;
            font-weight: 700;
            text-decoration: none;
            background: #FFFFFF;
            border: 1px solid #CBD5E1;
            color: #0F172A;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            transition: all 0.18s ease;
        }
        .btn-white:hover {
            background: #F8FAFC;
            border-color: #94A3B8;
            transform: translateY(-1px);
        }

        .dash-kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.65rem;
            margin-bottom: 0.85rem;
        }
        .dash-kpi-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.65rem 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: var(--shadow-card);
            min-width: 0;
        }
        .kpi-icon-pill {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            flex-shrink: 0;
        }
        .kpi-icon-pill.navy { background: rgba(11, 29, 74, 0.08); color: var(--color-navy); }
        .kpi-icon-pill.amber { background: #FEF3C7; color: #D97706; border: 1px solid #FDE68A; }
        .kpi-icon-pill.emerald { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }
        .kpi-icon-pill.gold { background: #FEF9C3; color: #B45309; border: 1px solid #FDE68A; }

        .kpi-val {
            font-size: 1.25rem;
            font-weight: 800;
            color: #0F172A;
            line-height: 1.1;
        }
        .kpi-lbl {
            font-size: 0.7rem;
            font-weight: 600;
            color: #64748B;
            margin-top: 1px;
        }

        .white-controls-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 0.65rem 0.95rem;
            margin-bottom: 0.85rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.65rem;
            box-shadow: var(--shadow-card);
        }
        .search-input-field {
            padding: 0.45rem 0.75rem 0.45rem 2rem;
            border: 1px solid #CBD5E1;
            border-radius: 7px;
            font-size: 0.8rem;
            outline: none;
            width: 100%;
            box-sizing: border-box;
            background: #F8FAFC;
        }

        .ap-card {
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow-card);
            margin-bottom: 1rem;
        }
        .ap-card-header {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #FFFFFF;
        }
        .ap-card-title {
            margin: 0;
            font-size: 0.88rem;
            font-weight: 800;
            color: #0F172A;
        }

        .ap-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.78rem;
            text-align: left;
        }
        .ap-table th {
            background: #F8FAFC;
            color: #64748B;
            font-weight: 700;
            font-size: 0.72rem;
            padding: 0.55rem 0.85rem;
            border-bottom: 1px solid var(--border-color);
            text-transform: uppercase;
        }
        .ap-table td {
            padding: 0.65rem 0.85rem;
            border-bottom: 1px solid #F1F5F9;
            color: #334155;
            vertical-align: middle;
        }

        @media (max-width: 1024px) {
            .dash-kpi-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- 1. Header Banner -->
            <div class="dash-header-banner">
                <div>
                    <h1 class="dash-header-title">
                        <i class="fas fa-inbox" style="color:var(--color-navy);"></i>
                        Public Inquiries & Contact Messages
                    </h1>
                    <p class="dash-header-sub">
                        Inquiries from prospective members, faculty advisors, sponsors, and external visitors.
                    </p>
                </div>
                <div style="display:flex; align-items:center; gap:0.45rem; flex-wrap:wrap;">
                    <a href="<?= PORTAL_URL ?>/admin/messages.php" class="btn-white">
                        <i class="fas fa-comments" style="color:var(--color-blue);"></i> Internal Messages
                    </a>
                </div>
            </div>

            <?php if (!empty($feedbackMsg)): ?>
                <div class="ap-alert <?= $feedbackType ?>" style="margin-bottom:0.85rem;">
                    <i class="fas fa-check-circle" style="font-size:1.2rem;"></i> 
                    <div><?= htmlspecialchars($feedbackMsg) ?></div>
                </div>
            <?php endif; ?>

            <!-- 2. KPI Grid -->
            <div class="dash-kpi-grid">
                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill navy"><i class="fas fa-envelope"></i></div>
                    <div>
                        <div class="kpi-val"><?= count($messages) ?></div>
                        <div class="kpi-lbl">Total Inquiries Received</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill amber"><i class="fas fa-envelope-open-text"></i></div>
                    <div>
                        <div class="kpi-val"><?= $unreadCount ?></div>
                        <div class="kpi-lbl">Unread / Action Required</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill emerald"><i class="fas fa-circle-check"></i></div>
                    <div>
                        <div class="kpi-val"><?= $readCount ?></div>
                        <div class="kpi-lbl">Resolved Inquiries</div>
                    </div>
                </div>

                <div class="dash-kpi-card">
                    <div class="kpi-icon-pill gold"><i class="fas fa-globe"></i></div>
                    <div>
                        <div class="kpi-val">Portal</div>
                        <div class="kpi-lbl">Source Channel</div>
                    </div>
                </div>
            </div>

            <!-- 3. Search & Filter Bar -->
            <div class="white-controls-card">
                <div style="position:relative; flex:1; max-width:380px;">
                    <i class="fas fa-search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#94A3B8; font-size:0.8rem;"></i>
                    <input type="text" id="contactSearchInput" class="search-input-field" placeholder="Search sender name, email, subject..." onkeyup="filterContactTable()">
                </div>
                <div style="font-size:0.75rem; font-weight:700; color:#64748B;">
                    Showing <?= count($messages) ?> inquiries
                </div>
            </div>

            <!-- 4. Table -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-inbox"></i> Incoming Inquiry Queue</h3>
                </div>
                <div style="overflow-x:auto;">
                    <table class="ap-table" id="contactTable">
                        <thead>
                            <tr>
                                <th>Sender Particulars</th>
                                <th>Subject & Inquiry Message</th>
                                <th>Status</th>
                                <th>Date Received</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($messages)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:2.5rem; color:#64748B;">
                                        <i class="fas fa-inbox" style="font-size:2rem; color:#CBD5E1; margin-bottom:0.5rem; display:block;"></i>
                                        <strong style="color:#0F172A; font-size:0.92rem;">Inquiry Queue is Clear</strong>
                                        <p style="margin:0.25rem 0 0; font-size:0.78rem;">Messages submitted via the public contact form will immediately appear here.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($messages as $m): ?>
                                    <?php 
                                        $st = strtolower($m['status'] ?? 'unread');
                                    ?>
                                    <tr>
                                        <td>
                                            <strong style="color:#0F172A; font-size:0.84rem;"><?= htmlspecialchars($m['name'] ?? 'Visitor') ?></strong><br>
                                            <span style="font-size:0.72rem; color:#64748B;"><?= htmlspecialchars($m['email'] ?? '') ?></span>
                                        </td>
                                        <td>
                                            <strong style="color:var(--color-navy); font-size:0.8rem;"><?= htmlspecialchars($m['subject'] ?? 'General Inquiry') ?></strong><br>
                                            <span style="font-size:0.75rem; color:#334155;"><?= htmlspecialchars($m['message'] ?? '') ?></span>
                                        </td>
                                        <td>
                                            <span class="ap-pill <?= ($st === 'unread' || $st === 'new') ? 'pending' : 'active' ?>">
                                                <?= ucfirst($st) ?>
                                            </span>
                                        </td>
                                        <td style="color:#64748B; font-size:0.75rem; white-space:nowrap;"><?= !empty($m['created_at']) ? date('M d, Y h:i A', strtotime($m['created_at'])) : 'Recent' ?></td>
                                        <td style="text-align:right;">
                                            <a href="mailto:<?= htmlspecialchars($m['email'] ?? '') ?>?subject=Re: <?= urlencode($m['subject'] ?? 'IECEP-LSC Inquiry') ?>" class="btn-white" style="font-size:0.72rem; padding:0.25rem 0.55rem;">
                                                <i class="fas fa-reply" style="color:var(--color-blue);"></i> Reply via Gmail
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script>
        function filterContactTable() {
            const query = document.getElementById('contactSearchInput').value.toLowerCase();
            const table = document.getElementById('contactTable');
            const trs = table.getElementsByTagName('tr');

            for (let i = 1; i < trs.length; i++) {
                const tr = trs[i];
                if (tr.children.length === 1 && tr.children[0].getAttribute('colspan')) continue;
                const text = tr.textContent.toLowerCase();
                tr.style.display = (text.indexOf(query) > -1) ? '' : 'none';
            }
        }
    </script>
</body>
</html>
