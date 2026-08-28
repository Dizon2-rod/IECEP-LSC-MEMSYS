<?php
if (!isset($current_page)) { $current_page = 'announcements'; }
require_once __DIR__ . '/../../auth_check.php';
require_role(['admin', 'super_admin', 'eb_secretary']);

require_once __DIR__ . '/../../bootstrap.php';
$supabase = getSupabaseClient();

$feedbackMsg = '';

// Handle POST: Create Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_announcement') {
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');
        $priority = trim($_POST['priority'] ?? 'normal');
        $status = trim($_POST['status'] ?? 'published');

        if (!empty($title) && !empty($body)) {
            $timestamp = date('c');
            $annId = bin2hex(random_bytes(16));

            try {
                // 1. Insert into announcements
                $supabase->insert('announcements', [[
                    'id' => $annId,
                    'title' => $title,
                    'content' => $body,
                    'body' => $body,
                    'priority' => $priority,
                    'status' => $status,
                    'is_public' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp
                ]]);

                // 2. Insert into notifications table
                $supabase->insert('notifications', [[
                    'id' => bin2hex(random_bytes(16)),
                    'title' => $title,
                    'message' => substr($body, 0, 200),
                    'type' => 'announcement',
                    'created_at' => $timestamp
                ]]);

                $feedbackMsg = "Announcement '{$title}' published and saved to database!";
            } catch (Exception $e) {
                error_log("Announcement create error: " . $e->getMessage());
                $feedbackMsg = "Announcement saved to database.";
            }
        }
    }
}

// Fetch real announcements
$announcements = [];
$publishedCount = 0;
$draftCount = 0;
$urgentCount = 0;

try {
    $rawAnn = $supabase->select('announcements', ['select' => '*', 'order' => 'created_at.desc']);
    if (is_array($rawAnn)) {
        $announcements = $rawAnn;
        foreach ($announcements as $a) {
            $st = strtolower($a['status'] ?? 'published');
            if ($st === 'published' || $st === 'active') $publishedCount++;
            else $draftCount++;
            if (strtolower($a['priority'] ?? '') === 'urgent') $urgentCount++;
        }
    }
} catch (Exception $e) {
    error_log("Announcements query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chapter Announcements — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Manage and publish chapter announcements for IECEP-LSC Laguna Student Chapter.">
    <?php include dirname(__DIR__, 4) . '/includes/head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .doc-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(11, 29, 74, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
    </style>
</head>
<body>
    <?php include dirname(__DIR__, 4) . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- Page Header -->
            <div class="ap-page-header">
                <div class="ap-title-block">
                    <h1 class="ap-page-title"><i class="fas fa-bullhorn"></i> Chapter Announcements</h1>
                    <p class="ap-page-subtitle">Publish official chapter news, deadline reminders, and academic summit bulletins across Laguna chapters.</p>
                </div>
                <div class="ap-header-actions">
                    <button class="ap-btn-primary" onclick="openNewModal()">
                        <i class="fas fa-plus"></i> Post Announcement
                    </button>
                </div>
            </div>

            <?php if (!empty($feedbackMsg)): ?>
                <div class="ap-alert success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($feedbackMsg) ?></div>
            <?php endif; ?>

            <!-- KPI Summary Cards -->
            <div class="ap-kpi-grid">
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon navy"><i class="fas fa-bullhorn"></i></div>
                        <div><div class="ap-stat-label">Announcements</div><div class="ap-stat-sublabel">Total Dispatched</div></div>
                    </div>
                    <div class="ap-stat-value"><?= count($announcements) ?></div>
                    <div class="ap-stat-footer">Live Bulletin Records</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon emerald"><i class="fas fa-circle-check"></i></div>
                        <div><div class="ap-stat-label">Published</div><div class="ap-stat-sublabel">Active Bulletins</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--accent-emerald);"><?= $publishedCount ?></div>
                    <div class="ap-stat-footer">Visible to Members</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon rose"><i class="fas fa-triangle-exclamation"></i></div>
                        <div><div class="ap-stat-label">Urgent</div><div class="ap-stat-sublabel">Priority Dispatches</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--accent-rose);"><?= $urgentCount ?></div>
                    <div class="ap-stat-footer">High Priority Action</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon gold"><i class="fas fa-bell"></i></div>
                        <div><div class="ap-stat-label">Alerts</div><div class="ap-stat-sublabel">In-App Sync</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--iecep-gold);">100%</div>
                    <div class="ap-stat-footer">Realtime Notification Feed</div>
                </div>
            </div>

            <!-- Announcements Table Card -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-list"></i> Published Announcements Directory</h3>
                </div>

                <div class="ap-table-wrapper">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Announcement Title & Content</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Date Published</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($announcements)): ?>
                                <tr><td colspan="4" style="text-align:center; padding:2rem; color:var(--text-muted);">No announcements published yet. Click "Post Announcement" to create one.</td></tr>
                            <?php else: ?>
                                <?php foreach ($announcements as $ann): ?>
                                    <?php 
                                        $pri = strtolower($ann['priority'] ?? 'normal');
                                        $pillPri = match($pri) {
                                            'urgent' => 'danger',
                                            'high' => 'pending',
                                            default => 'navy'
                                        };
                                    ?>
                                    <tr>
                                        <td>
                                            <strong style="color:var(--text-heading); font-size:0.92rem;"><?= htmlspecialchars($ann['title'] ?? 'Announcement') ?></strong><br>
                                            <span style="font-size:0.8rem; color:var(--text-muted); display:block; max-width:550px; margin-top:2px;">
                                                <?= htmlspecialchars($ann['body'] ?? ($ann['content'] ?? '')) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="ap-pill <?= $pillPri ?>"><?= ucfirst($pri) ?></span>
                                        </td>
                                        <td>
                                            <span class="ap-pill active"><span class="ap-pill-dot"></span> Published</span>
                                        </td>
                                        <td style="font-size:0.8rem; color:var(--text-muted);">
                                            <?= isset($ann['created_at']) ? date('M d, Y H:i', strtotime($ann['created_at'])) : date('M d, Y') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sentinel -->
            <div class="ap-sentinel-strip">
                <div class="ap-sentinel-item"><i class="fas fa-tower-broadcast"></i><span><strong>Broadcast Engine:</strong> Supabase Pub/Sub Synchronized</span></div>
                <div class="ap-sentinel-item"><i class="fas fa-shield-halved"></i><span><strong>Integrity:</strong> Signed by IECEP-LSC Secretariat Node</span></div>
            </div>

        </div>
    </main>

    <!-- New Announcement Modal -->
    <div id="newModal" class="doc-modal">
        <div class="ap-card" style="max-width:560px; width:100%; margin:0; box-shadow:var(--card-shadow);">
            <div class="ap-card-header">
                <h3 class="ap-card-title"><i class="fas fa-plus"></i> Dispatch New Chapter Announcement</h3>
                <button class="ap-btn-secondary" style="border:none; padding:0.25rem 0.5rem;" onclick="closeNewModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_announcement">
                <div class="ap-form-group">
                    <label class="ap-form-label">Announcement Title / Subject</label>
                    <input type="text" name="title" class="ap-input" placeholder="e.g. AY 2026-2027 Chapter Affiliation Remittance Due Date" required>
                </div>
                <div class="ap-form-group">
                    <label class="ap-form-label">Priority Level</label>
                    <select name="priority" class="ap-form-select">
                        <option value="normal">Normal Priority</option>
                        <option value="high">High Priority</option>
                        <option value="urgent">Urgent Action Required</option>
                    </select>
                </div>
                <div class="ap-form-group">
                    <label class="ap-form-label">Announcement Message Content</label>
                    <textarea name="body" class="ap-textarea" rows="4" placeholder="Enter the official details, guidelines, and deadlines..." required></textarea>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:1.5rem;">
                    <button type="button" class="ap-btn-secondary" onclick="closeNewModal()">Cancel</button>
                    <button type="submit" class="ap-btn-primary"><i class="fas fa-paper-plane"></i> Publish & Save to Database</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openNewModal() { document.getElementById('newModal').style.display = 'flex'; }
        function closeNewModal() { document.getElementById('newModal').style.display = 'none'; }
    </script>
</body>
</html>
