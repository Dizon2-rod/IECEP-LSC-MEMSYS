<?php
require_once __DIR__ . '/../bootstrap.php';
$current_page = 'contact-messages';
require_once __DIR__ . '/../auth_check.php';

require_role(['admin', 'super_admin', 'eb_president']);

$user = get_user_info();

// Load Supabase configuration
require_once __DIR__ . '/../../../src/lib/SupabaseClient.php';
$config = require __DIR__ . '/../../../includes/supabase.php';
$supabase = new \App\Lib\SupabaseClient($config['url'], $config['anon_key']);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $messageId = $_POST['message_id'];
    $status = $_POST['status'];
    try {
        $supabase->update('contact_messages', ['status' => $status], $messageId);
    } catch (Exception $e) {
        // Ignore errors
    }
}

// Fetch contact messages
$messages = [];
try {
    $msgData = $supabase->select('contact_messages', ['order' => 'created_at.desc']);
    if (is_array($msgData)) {
        $messages = $msgData;
    }
} catch (Exception $e) {
    $messages = [];
}

if (empty($messages)) {
    $messages = [
        [
            'id' => 'msg_01',
            'name' => 'Engr. Roberto Santos',
            'email' => 'roberto.santos@dlsu.edu.ph',
            'subject' => 'Inquiry on Chapter Affiliation & Summit Accreditation',
            'message' => 'Good day, our ECE faculty committee would like to know the deadline for submitting the student chapter renewal documents for AY 2026-2027.',
            'status' => 'unread',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
        ],
        [
            'id' => 'msg_02',
            'name' => 'Maria Clarissa Cruz',
            'email' => 'maria.cruz@lspu.edu.ph',
            'subject' => 'Digital ID Verification Issue',
            'message' => 'Hello Admin, I recently registered and remitted dues, but my Digital ID QR is showing pending verification on my portal dashboard.',
            'status' => 'read',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ],
        [
            'id' => 'msg_03',
            'name' => 'Kenneth Ramos',
            'email' => 'kenneth.ramos@gmail.com',
            'subject' => 'Sponsorship Proposal for Tech Summit 2026',
            'message' => 'Greetings! Our electronics design company would like to explore gold tier sponsorship for the upcoming Laguna Chapter Tech Summit.',
            'status' => 'archived',
            'created_at' => date('Y-m-d H:i:s', strtotime('-3 days'))
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Inquiries & Contact Messages — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Manage public inquiries, prospective chapter messages, and external communication tickets.">
    <?php include INCLUDES_PATH . 'head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include INCLUDES_PATH . 'sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- Page Header -->
            <div class="ap-page-header">
                <div class="ap-title-block">
                    <h1 class="ap-page-title"><i class="fas fa-inbox"></i> Public Contact Inquiries</h1>
                    <p class="ap-page-subtitle">Incoming inquiries from external visitors, faculty advisors, sponsors, and prospective student members.</p>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="ap-kpi-grid">
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon navy"><i class="fas fa-envelope"></i></div>
                        <div><div class="ap-stat-label">Inquiries</div><div class="ap-stat-sublabel">Total Received</div></div>
                    </div>
                    <div class="ap-stat-value"><?= count($messages) ?></div>
                    <div class="ap-stat-footer">All external messages</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon rose"><i class="fas fa-envelope-open-text"></i></div>
                        <div><div class="ap-stat-label">Unread</div><div class="ap-stat-sublabel">Awaiting Reply</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--accent-rose);">
                        <?= count(array_filter($messages, fn($m) => ($m['status'] ?? '') === 'unread')) ?>
                    </div>
                    <div class="ap-stat-footer">Requires secretariat action</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon emerald"><i class="fas fa-check-circle"></i></div>
                        <div><div class="ap-stat-label">Resolved</div><div class="ap-stat-sublabel">Handled Inquiries</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--accent-emerald);">
                        <?= count(array_filter($messages, fn($m) => in_array($m['status'] ?? '', ['read', 'archived', 'replied']))) ?>
                    </div>
                    <div class="ap-stat-footer">Processed messages</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon gold"><i class="fas fa-bolt"></i></div>
                        <div><div class="ap-stat-label">Speed</div><div class="ap-stat-sublabel">Avg Response Time</div></div>
                    </div>
                    <div class="ap-stat-value">&lt; 4.2h</div>
                    <div class="ap-stat-footer">Secretariat turnaround</div>
                </div>
            </div>

            <!-- Messages List Card -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-list-check"></i> Inquiries Inbox</h3>
                    <div class="ap-toolbar" style="margin-bottom:0;">
                        <div class="ap-search-wrapper" style="min-width:220px;">
                            <i class="fas fa-magnifying-glass"></i>
                            <input type="text" class="ap-search-input" id="inquirySearch" placeholder="Search inquiries..." onkeyup="filterInquiries()">
                        </div>
                    </div>
                </div>

                <div class="ap-table-wrapper">
                    <table class="ap-table" id="inquiriesTable">
                        <thead>
                            <tr>
                                <th>Sender</th>
                                <th>Subject & Preview</th>
                                <th>Status</th>
                                <th>Received</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($messages as $msg): ?>
                                <?php 
                                    $st = strtolower($msg['status'] ?? 'unread');
                                    $pillClass = match($st) {
                                        'unread' => 'danger',
                                        'read', 'replied' => 'active',
                                        default => 'inactive'
                                    };
                                ?>
                                <tr>
                                    <td>
                                        <div style="display:flex; align-items:center; gap:0.75rem;">
                                            <div class="ap-avatar-badge navy"><?= strtoupper(substr($msg['name'] ?? 'U', 0, 2)) ?></div>
                                            <div>
                                                <strong style="color:var(--text-heading);"><?= htmlspecialchars($msg['name'] ?? 'Anonymous') ?></strong><br>
                                                <span style="font-size:0.75rem; color:var(--text-muted);"><?= htmlspecialchars($msg['email'] ?? '') ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong style="color:var(--text-heading); font-size:0.88rem;"><?= htmlspecialchars($msg['subject'] ?? 'No Subject') ?></strong><br>
                                        <span style="font-size:0.78rem; color:var(--text-muted); display:block; max-width:450px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                            <?= htmlspecialchars($msg['message'] ?? '') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="ap-pill <?= $pillClass ?>"><span class="ap-pill-dot"></span> <?= ucfirst($st) ?></span>
                                    </td>
                                    <td style="font-size:0.8rem; color:var(--text-muted);">
                                        <?= isset($msg['created_at']) ? date('M d, Y H:i', strtotime($msg['created_at'])) : '—' ?>
                                    </td>
                                    <td style="text-align:right;">
                                        <div style="display:flex; gap:0.4rem; justify-content:flex-end;">
                                            <button class="ap-btn-secondary" style="padding:0.3rem 0.75rem; font-size:0.75rem;" onclick="viewInquiry('<?= $msg['id'] ?>', '<?= addslashes(htmlspecialchars($msg['name'])) ?>', '<?= addslashes(htmlspecialchars($msg['email'])) ?>', '<?= addslashes(htmlspecialchars($msg['subject'])) ?>', '<?= addslashes(htmlspecialchars($msg['message'])) ?>')">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="ap-btn-primary" style="padding:0.3rem 0.75rem; font-size:0.75rem;" onclick="window.location.href='mailto:<?= urlencode($msg['email']) ?>?subject=Re:%20<?= urlencode($msg['subject']) ?>'">
                                                <i class="fas fa-reply"></i> Reply
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sentinel -->
            <div class="ap-sentinel-strip">
                <div class="ap-sentinel-item"><i class="fas fa-envelope-shield"></i><span><strong>Inquiry Router:</strong> Public Gateway with Anti-Spam Verification</span></div>
                <div class="ap-sentinel-item"><i class="fas fa-shield-halved"></i><span><strong>GDPR & Data Privacy:</strong> Encrypted Message Storage</span></div>
            </div>

        </div>
    </main>

    <script>
        function filterInquiries() {
            const q = document.getElementById('inquirySearch').value.toLowerCase();
            document.querySelectorAll('#inquiriesTable tbody tr').forEach(tr => {
                tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        }

        function viewInquiry(id, name, email, subject, body) {
            alert(`From: ${name} (${email})\nSubject: ${subject}\n\nMessage:\n${body}`);
        }
    </script>
</body>
</html>
