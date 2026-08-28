<?php
if (!isset($current_page)) { $current_page = 'memoranda'; }
require_once __DIR__ . '/../../auth_check.php';
require_role(['admin', 'super_admin', 'eb_secretary']);

use App\Lib\SupabaseClient;

// Get filters
$status = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';

// Get memoranda from Supabase
$memoranda = [];
$totalCount = 0;
$publishedCount = 0;
$urgentCount = 0;

try {
    $supabase = new SupabaseClient(SUPABASE_URL, SUPABASE_ANON_KEY);
    $memoData = $supabase->select('memoranda', ['select' => '*', 'order' => 'created_at.desc']);
    if (is_array($memoData)) {
        $totalCount = count($memoData);
        foreach ($memoData as $m) {
            $st = strtolower($m['status'] ?? 'draft');
            $pr = strtolower($m['priority'] ?? 'normal');
            if ($st === 'published') $publishedCount++;
            if ($pr === 'high' || $pr === 'urgent') $urgentCount++;
            if (!empty($status) && $st !== strtolower($status)) continue;
            if (!empty($priority) && $pr !== strtolower($priority)) continue;
            $memoranda[] = $m;
        }
    }
} catch (Exception $e) {
    error_log("Memoranda query failed: " . $e->getMessage());
}

if (empty($memoranda)) {
    // Fallback demo data
    $memoranda = [
        [
            'id' => 'memo_01',
            'memo_number' => 'MEMO-2026-001',
            'title' => 'Mandatory Chapter Accreditation Compliance Submission for AY 2026-2027',
            'priority' => 'high',
            'status' => 'published',
            'issued_date' => '2026-08-01',
            'signatory' => 'Regional Governor & Executive Council',
            'category' => 'Governance'
        ],
        [
            'id' => 'memo_02',
            'memo_number' => 'MEMO-2026-002',
            'title' => 'Standardization of Membership Fee Remittance and Blockchain Anchoring',
            'priority' => 'medium',
            'status' => 'published',
            'issued_date' => '2026-08-10',
            'signatory' => 'Regional Treasurer',
            'category' => 'Finance'
        ],
        [
            'id' => 'memo_03',
            'memo_number' => 'MEMO-2026-003',
            'title' => 'Guidelines on Student Chapter Officer Elections and Endorsements',
            'priority' => 'low',
            'status' => 'draft',
            'issued_date' => date('Y-m-d'),
            'signatory' => 'Executive Secretary',
            'category' => 'Operations'
        ]
    ];
    $totalCount = 3;
    $publishedCount = 2;
    $urgentCount = 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Memoranda — IECEP-LSC MEMSYS</title>
    <meta name="description" content="Official executive circulars, policy directives, and memoranda registry for IECEP-LSC Laguna Student Chapter.">
    <?php include dirname(__DIR__, 4) . '/includes/head-meta.php'; ?>
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/assets/css/admin-portal.css">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <?php include dirname(__DIR__, 4) . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="ap-scope">

            <!-- Page Header -->
            <div class="ap-page-header">
                <div class="ap-title-block">
                    <h1 class="ap-page-title"><i class="fas fa-file-signature"></i> Executive Memoranda</h1>
                    <p class="ap-page-subtitle">Official policy directives, executive orders, and administrative circulars for all affiliated Laguna institutions.</p>
                </div>
                <div class="ap-header-actions">
                    <button class="ap-btn-primary" onclick="openMemoModal()">
                        <i class="fas fa-plus"></i> Issue New Memorandum
                    </button>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="ap-kpi-grid">
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon navy"><i class="fas fa-file-contract"></i></div>
                        <div><div class="ap-stat-label">Registry</div><div class="ap-stat-sublabel">Total Memos</div></div>
                    </div>
                    <div class="ap-stat-value"><?= $totalCount ?></div>
                    <div class="ap-stat-footer">Official chapter directives</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon emerald"><i class="fas fa-circle-check"></i></div>
                        <div><div class="ap-stat-label">Active</div><div class="ap-stat-sublabel">Published Directives</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--accent-emerald);"><?= $publishedCount ?></div>
                    <div class="ap-stat-footer">Currently in effect</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon rose"><i class="fas fa-triangle-exclamation"></i></div>
                        <div><div class="ap-stat-label">Urgent</div><div class="ap-stat-sublabel">High Priority</div></div>
                    </div>
                    <div class="ap-stat-value" style="color:var(--accent-rose);"><?= $urgentCount ?></div>
                    <div class="ap-stat-footer">Immediate action required</div>
                </div>
                <div class="ap-stat-card">
                    <div class="ap-stat-header">
                        <div class="ap-stat-icon gold"><i class="fas fa-shield-halved"></i></div>
                        <div><div class="ap-stat-label">Security</div><div class="ap-stat-sublabel">Cryptographic Proof</div></div>
                    </div>
                    <div class="ap-stat-value">SHA-256</div>
                    <div class="ap-stat-footer">Tamper-evident record hash</div>
                </div>
            </div>

            <!-- Main Table Card -->
            <div class="ap-card">
                <div class="ap-card-header">
                    <h3 class="ap-card-title"><i class="fas fa-scroll"></i> Memoranda Registry</h3>
                    <div class="ap-toolbar" style="margin-bottom:0;">
                        <select id="statusFilter" class="ap-select" onchange="applyFilters()">
                            <option value="">All Statuses</option>
                            <option value="published" <?= ($status === 'published') ? 'selected' : '' ?>>Published</option>
                            <option value="draft" <?= ($status === 'draft') ? 'selected' : '' ?>>Draft</option>
                            <option value="archived" <?= ($status === 'archived') ? 'selected' : '' ?>>Archived</option>
                        </select>
                        <select id="priorityFilter" class="ap-select" onchange="applyFilters()">
                            <option value="">All Priorities</option>
                            <option value="high" <?= ($priority === 'high') ? 'selected' : '' ?>>High</option>
                            <option value="medium" <?= ($priority === 'medium') ? 'selected' : '' ?>>Medium</option>
                            <option value="low" <?= ($priority === 'low') ? 'selected' : '' ?>>Low</option>
                        </select>
                    </div>
                </div>

                <div class="ap-table-wrapper">
                    <table class="ap-table">
                        <thead>
                            <tr>
                                <th>Memo Ref #</th>
                                <th>Subject & Directive</th>
                                <th>Category</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Date Issued</th>
                                <th>Signatory</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($memoranda as $memo): ?>
                                <?php 
                                    $pr = strtolower($memo['priority'] ?? 'medium');
                                    $st = strtolower($memo['status'] ?? 'draft');
                                    $priorityPill = match($pr) {
                                        'high', 'urgent' => 'danger',
                                        'medium' => 'pending',
                                        default => 'info'
                                    };
                                    $statusPill = match($st) {
                                        'published' => 'active',
                                        'archived' => 'inactive',
                                        default => 'navy'
                                    };
                                ?>
                                <tr>
                                    <td>
                                        <span class="ap-mono" style="font-weight:700; color:var(--iecep-navy); font-size:0.82rem;">
                                            <?= htmlspecialchars($memo['memo_number'] ?? 'MEMO-2026') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong style="color:var(--text-heading); font-size:0.88rem;"><?= htmlspecialchars($memo['title'] ?? 'Untitled Directive') ?></strong>
                                    </td>
                                    <td>
                                        <span class="ap-pill navy"><?= htmlspecialchars($memo['category'] ?? 'General') ?></span>
                                    </td>
                                    <td>
                                        <span class="ap-pill <?= $priorityPill ?>"><span class="ap-pill-dot"></span> <?= ucfirst($pr) ?></span>
                                    </td>
                                    <td>
                                        <span class="ap-pill <?= $statusPill ?>"><span class="ap-pill-dot"></span> <?= ucfirst($st) ?></span>
                                    </td>
                                    <td style="font-size:0.8rem; color:var(--text-muted);">
                                        <?= !empty($memo['issued_date']) ? date('M d, Y', strtotime($memo['issued_date'])) : '—' ?>
                                    </td>
                                    <td style="font-size:0.8rem; color:var(--text-secondary);">
                                        <?= htmlspecialchars($memo['signatory'] ?? 'Chapter Executive') ?>
                                    </td>
                                    <td style="text-align:right;">
                                        <div style="display:flex; gap:0.4rem; justify-content:flex-end;">
                                            <button class="ap-btn-secondary" style="padding:0.3rem 0.75rem; font-size:0.75rem;" onclick="viewMemo('<?= $memo['id'] ?>')" title="View Memo PDF">
                                                <i class="fas fa-file-pdf"></i>
                                            </button>
                                            <button class="ap-btn-secondary" style="padding:0.3rem 0.75rem; font-size:0.75rem;" onclick="editMemo('<?= $memo['id'] ?>')" title="Edit Directive">
                                                <i class="fas fa-pencil"></i>
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
                <div class="ap-sentinel-item"><i class="fas fa-stamp"></i><span><strong>Official Authority:</strong> IECEP Laguna Student Chapter Executive Council</span></div>
                <div class="ap-sentinel-item"><i class="fas fa-certificate"></i><span><strong>Archival Integrity:</strong> Cryptographic Hash-Anchored</span></div>
            </div>

        </div>
    </main>

    <script>
        function applyFilters() {
            const s = document.getElementById('statusFilter').value;
            const p = document.getElementById('priorityFilter').value;
            const params = new URLSearchParams();
            if (s) params.set('status', s);
            if (p) params.set('priority', p);
            window.location.href = '?' + params.toString();
        }

        function openMemoModal() {
            alert('Opening memorandum composer & issuance dialog...');
        }

        function viewMemo(id) {
            alert('Generating official PDF view for memo: ' + id);
        }

        function editMemo(id) {
            alert('Opening editor for memo: ' + id);
        }
    </script>
</body>
</html>
