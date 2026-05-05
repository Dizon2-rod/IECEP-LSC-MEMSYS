<?php
/**
 * Registration Committee - Pending Affiliations Review Dashboard
 * Complete workflow with modals for Request Changes, Approve, and Reject actions
 */

session_start();
require_once __DIR__ . '/../../../autoload.php';
require_once __DIR__ . '/../../../includes/supabase.php';
require_once __DIR__ . '/../../../includes/paths.php';

// Check authentication and role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['registration', 'committee_registration', 'admin', 'super_admin'])) {
    header('Location: ' . PORTAL_URL . '/dashboard.php?error=unauthorized');
    exit;
}

require_once __DIR__ . '/../../../src/lib/SupabaseClient.php';
$config = require __DIR__ . '/../../../includes/supabase.php';
$supabase = new SupabaseClient($config['url'], $config['anon_key']);

$successMessage = '';
$errorMessage = '';

// Fetch applications with pending or resubmitted status
try {
    $applications = [];
    $changesRequestedApps = [];
    
    // Try to get all applications first
    try {
        $allApps = $supabase->select('pending_affiliations', [
            'order' => 'created_at.desc'
        ]);
        
        if (is_array($allApps) && !empty($allApps)) {
            // Filter applications by status
            foreach ($allApps as $app) {
                $status = $app['status'] ?? 'pending';
                
                if ($status === 'pending' || $status === 'resubmitted') {
                    $applications[] = $app;
                } elseif ($status === 'changes_requested') {
                    $changesRequestedApps[] = $app;
                } elseif ($status === 'pending' && !isset($app['status'])) {
                    // If status column doesn't exist, treat as pending
                    $applications[] = $app;
                }
            }
        }
    } catch (Exception $e) {
        $loadError = "Database query failed: " . $e->getMessage();
        error_log("Error fetching applications: " . $e->getMessage());
    }
    
} catch (Exception $e) {
    $applications = [];
    $changesRequestedApps = [];
    $loadError = $e->getMessage();
}

if (isset($_GET['success'])) {
    $successMessage = 'Action completed successfully.';
}
if (isset($_GET['error'])) {
    $errorMessage = htmlspecialchars($_GET['error']);
}

function formatDateTime($dateStr) {
    if (empty($dateStr)) {
        return 'N/A';
    }
    $date = new DateTime($dateStr);
    return $date->format('F j, Y \a\t g:i A');
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending':
        case 'pending_review':
            return 'status-pending';
        case 'resubmitted':
            return 'status-resubmitted';
        case 'changes_requested':
            return 'status-changes';
        case 'approved':
            return 'status-approved';
        case 'rejected':
            return 'status-rejected';
        default:
            return 'status-pending';
    }
}

function getStatusLabel($status) {
    switch ($status) {
        case 'pending':
        case 'pending_review':
            return 'Pending Review';
        case 'resubmitted':
            return 'Resubmitted';
        case 'changes_requested':
            return 'Changes Requested';
        case 'approved':
            return 'Approved';
        case 'rejected':
            return 'Rejected';
        default:
            return 'Pending';
    }
}

function getStatusIcon($status) {
    switch ($status) {
        case 'pending':
        case 'pending_review':
            return 'fa-hourglass-half';
        case 'resubmitted':
            return 'fa-rotate';
        case 'changes_requested':
            return 'fa-exclamation-circle';
        case 'approved':
            return 'fa-check-circle';
        case 'rejected':
            return 'fa-times-circle';
        default:
            return 'fa-hourglass-half';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Pending Affiliations - Registration Committee</title>
    <?php include __DIR__ . '/../../../includes/head-meta.php'; ?>
    <style>
        :root {
            --primary: #0B1D4A;
            --primary-light: #1a3a7a;
            --accent: #D4AF37;
            --accent-hover: #B8962E;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --white: #ffffff;
            --neutral-100: #f8fafc;
            --neutral-200: #e2e8f0;
            --neutral-300: #cbd5e1;
            --neutral-500: #64748b;
            --neutral-700: #334155;
            --neutral-900: #0f172a;
            --radius-lg: 12px;
            --radius-xl: 16px;
            --radius-full: 9999px;
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
            --space-2: 0.5rem;
            --space-3: 0.75rem;
            --space-4: 1rem;
            --space-5: 1.25rem;
            --space-6: 1.5rem;
            --space-8: 2rem;
            --space-12: 3rem;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--neutral-100);
            margin: 0;
            padding: 0;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: var(--space-6);
            transition: margin-left 0.3s ease;
        }
        
        @media (max-width: 767.98px) {
            .main-content {
                margin-left: 0;
                padding: var(--space-4);
            }
        }
        
        .dashboard-header {
            margin-bottom: var(--space-6);
        }
        
        .dashboard-header h1 {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0 0 var(--space-2) 0;
        }
        
        .welcome-message {
            color: var(--neutral-500);
            margin: 0;
            font-size: 1rem;
        }
        
        .applications-list {
            display: grid;
            gap: var(--space-6);
        }
        
        .application-card {
            background: var(--white);
            border: 1px solid var(--neutral-200);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }
        
        .application-card header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: var(--space-4);
            padding: var(--space-6);
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: var(--white);
        }
        
        .application-title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
        }
        
        .application-date {
            font-size: 0.875rem;
            opacity: 0.9;
            margin-top: var(--space-2);
            display: block;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-2) var(--space-3);
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }
        
        .status-pending {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-resubmitted {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-changes {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-approved {
            background: #dcfce7;
            color: #166534;
        }
        
        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .card-body {
            padding: var(--space-6);
        }
        
        .application-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-4);
            margin-bottom: var(--space-6);
        }
        
        .meta-item {
            display: flex;
            flex-direction: column;
            gap: var(--space-1);
        }
        
        .meta-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--neutral-500);
        }
        
        .meta-value {
            font-size: 0.95rem;
            color: var(--neutral-900);
            font-weight: 500;
        }
        
        .documents-section {
            margin-top: var(--space-6);
            padding-top: var(--space-6);
            border-top: 1px solid var(--neutral-200);
        }
        
        .section-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0 0 var(--space-4) 0;
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }
        
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: var(--space-3);
        }
        
        .document-item {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-3);
            background: var(--neutral-100);
            border-radius: var(--radius-lg);
            border: 1px solid var(--neutral-200);
            transition: all 0.2s ease;
        }
        
        .document-item:hover {
            border-color: var(--accent);
            box-shadow: var(--shadow-md);
        }
        
        .document-icon {
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: var(--white);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        
        .document-info {
            flex: 1;
            min-width: 0;
        }
        
        .document-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--neutral-900);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .document-type {
            font-size: 0.75rem;
            color: var(--neutral-500);
        }
        
        .document-download {
            color: var(--accent);
            font-size: 0.875rem;
            text-decoration: none;
            padding: var(--space-2);
            border-radius: var(--radius-lg);
            transition: background 0.2s ease;
        }
        
        .document-download:hover {
            background: rgba(212, 175, 55, 0.1);
        }
        
        .committee-notes {
            margin-top: var(--space-6);
            padding: var(--space-4);
            background: #fef3c7;
            border-left: 4px solid var(--warning);
            border-radius: var(--radius-lg);
        }
        
        .committee-notes-title {
            font-size: 0.875rem;
            font-weight: 700;
            color: #92400e;
            margin: 0 0 var(--space-2) 0;
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }
        
        .committee-notes-text {
            color: #78350f;
            font-size: 0.95rem;
            line-height: 1.6;
            margin: 0;
            white-space: pre-wrap;
        }
        
        .action-buttons {
            display: flex;
            gap: var(--space-3);
            margin-top: var(--space-6);
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-2);
            padding: var(--space-3) var(--space-5);
            font-size: 0.875rem;
            font-weight: 600;
            border-radius: var(--radius-lg);
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .btn-request {
            background: var(--warning);
            color: white;
        }
        
        .btn-request:hover {
            background: #d97706;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-approve {
            background: var(--success);
            color: white;
        }
        
        .btn-approve:hover {
            background: #16a34a;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-reject {
            background: var(--danger);
            color: white;
        }
        
        .btn-reject:hover {
            background: #dc2626;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-secondary {
            background: var(--neutral-200);
            color: var(--neutral-700);
        }
        
        .btn-secondary:hover {
            background: var(--neutral-300);
        }
        
        .empty-state {
            text-align: center;
            padding: var(--space-12) var(--space-4);
            border: 2px dashed var(--neutral-300);
            border-radius: var(--radius-xl);
            background: var(--white);
        }
        
        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: var(--space-4);
        }
        
        .empty-state h2 {
            margin-bottom: var(--space-3);
            font-size: 1.5rem;
            color: var(--neutral-700);
        }
        
        .empty-state p {
            margin: 0;
            color: var(--neutral-500);
            font-size: 1rem;
        }
        
        .alert {
            padding: var(--space-4);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-6);
            display: flex;
            align-items: flex-start;
            gap: var(--space-3);
        }
        
        .alert-success {
            background: #dcfce7;
            border: 1px solid #86efac;
            color: #166534;
        }
        
        .alert-error {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #991b1b;
        }
        
        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            padding: var(--space-4);
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal {
            background: var(--white);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .modal-header {
            padding: var(--space-6);
            border-bottom: 1px solid var(--neutral-200);
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }
        
        .modal-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }
        
        .modal-icon.warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .modal-icon.success {
            background: #dcfce7;
            color: #166534;
        }
        
        .modal-icon.danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--neutral-900);
            margin: 0;
        }
        
        .modal-subtitle {
            font-size: 0.875rem;
            color: var(--neutral-500);
            margin: var(--space-1) 0 0 0;
        }
        
        .modal-body {
            padding: var(--space-6);
        }
        
        .form-group {
            margin-bottom: var(--space-4);
        }
        
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--neutral-700);
            margin-bottom: var(--space-2);
        }
        
        .form-textarea {
            width: 100%;
            padding: var(--space-3);
            border: 1px solid var(--neutral-300);
            border-radius: var(--radius-lg);
            font-family: inherit;
            font-size: 0.95rem;
            resize: vertical;
            min-height: 120px;
            transition: border-color 0.2s ease;
        }
        
        .form-textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }
        
        .form-help {
            font-size: 0.75rem;
            color: var(--neutral-500);
            margin-top: var(--space-2);
        }
        
        .application-preview {
            background: var(--neutral-100);
            border-radius: var(--radius-lg);
            padding: var(--space-4);
            margin-bottom: var(--space-4);
        }
        
        .preview-item {
            display: flex;
            justify-content: space-between;
            padding: var(--space-2) 0;
            border-bottom: 1px solid var(--neutral-200);
        }
        
        .preview-item:last-child {
            border-bottom: none;
        }
        
        .preview-label {
            font-size: 0.875rem;
            color: var(--neutral-500);
        }
        
        .preview-value {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--neutral-900);
            text-align: right;
        }
        
        .modal-footer {
            padding: var(--space-4) var(--space-6);
            border-top: 1px solid var(--neutral-200);
            display: flex;
            justify-content: flex-end;
            gap: var(--space-3);
        }
        
        .btn-full {
            width: 100%;
            justify-content: center;
        }
        
        .loading-spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        .btn.loading .loading-spinner {
            display: inline-block;
        }
        
        .btn.loading .btn-text {
            display: none;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .stats-bar {
            display: flex;
            gap: var(--space-6);
            margin-bottom: var(--space-6);
            flex-wrap: wrap;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: var(--space-3);
            padding: var(--space-3) var(--space-4);
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--neutral-500);
        }
        
        @media (max-width: 640px) {
            .application-card header {
                flex-direction: column;
                gap: var(--space-3);
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
            
            .documents-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>
    
    <main class="main-content">
        <header class="dashboard-header">
            <h1><i class="fas fa-clipboard-check" style="color: var(--accent);"></i> Pending Affiliations</h1>
            <p class="welcome-message">Review and process affiliation applications submitted to the Registration Committee.</p>
        </header>
        
        <?php
        $pendingCount = 0;
        $resubmittedCount = 0;
        foreach ($applications as $app) {
            $status = $app['status'] ?? 'pending';
            if ($status === 'resubmitted') {
                $resubmittedCount++;
            } else {
                $pendingCount++;
            }
        }
        $changesCount = is_array($changesRequestedApps) ? count($changesRequestedApps) : 0;
        ?>
        
        <div class="stats-bar">
            <div class="stat-item">
                <span class="stat-number"><?php echo $pendingCount; ?></span>
                <span class="stat-label">Pending Review</span>
            </div>
            <div class="stat-item">
                <span class="stat-number" style="color: var(--success);"><?php echo $resubmittedCount; ?></span>
                <span class="stat-label">Resubmitted</span>
            </div>
            <div class="stat-item">
                <span class="stat-number" style="color: var(--warning);"><?php echo $changesCount; ?></span>
                <span class="stat-label">Changes Requested</span>
            </div>
        </div>
        
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($successMessage); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($errorMessage); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($loadError)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span>Failed to load applications: <?php echo htmlspecialchars($loadError); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (empty($applications)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📬</div>
                <h2>No Pending Applications</h2>
                <p>There are currently no affiliation applications awaiting review. Check back later or view the changes requested section.</p>
                <?php if (isset($loadError)): ?>
                    <div style="background: #fee2e2; color: #dc2626; padding: 10px; border-radius: 8px; margin-top: 20px;">
                        <strong>Debug Info:</strong> <?php echo htmlspecialchars($loadError); ?>
                    </div>
                <?php endif; ?>
                <div style="background: #f0f9ff; color: #0369a1; padding: 10px; border-radius: 8px; margin-top: 20px;">
                    <strong>Debug:</strong> Found <?php echo count($applications); ?> pending applications and <?php echo count($changesRequestedApps); ?> change requests.
                </div>
            </div>
        <?php else: ?>
            <div class="applications-list">
                <?php foreach ($applications as $application): ?>
                    <?php
                        $documents = [];
                        if (!empty($application['documents'])) {
                            $documents = json_decode($application['documents'], true) ?: [];
                        }
                        
                        // Separate committee notes from documents
                        $committeeNotes = '';
                        if (isset($documents['committee_notes'])) {
                            $committeeNotes = $documents['committee_notes'];
                            unset($documents['committee_notes']);
                        }
                        if (isset($documents['review_notes'])) {
                            $committeeNotes = $documents['review_notes'];
                            unset($documents['review_notes']);
                        }
                        
                        // Filter out non-document entries
                        $documentFiles = [];
                        $docLabels = [
                            'moa' => 'Memorandum of Agreement',
                            'accreditation' => 'Accreditation Certificate',
                            'cor' => 'Certificate of Registration',
                            'school_registration' => 'School Registration',
                            'id_picture' => 'ID Picture',
                            'letter_of_intent' => 'Letter of Intent',
                            'officers_list' => 'List of Officers',
                            'faculty_adviser' => 'Faculty Adviser Profile'
                        ];
                        
                        foreach ($documents as $key => $value) {
                            if (is_array($value) && isset($value['name'])) {
                                $documentFiles[$key] = $value;
                            }
                        }
                        
                        $status = $application['status'] ?? 'pending';
                        $submittedAt = formatDateTime($application['submitted_at'] ?? $application['created_at'] ?? '');
                        $resubmittedAt = !empty($application['resubmitted_at']) ? formatDateTime($application['resubmitted_at']) : null;
                    ?>
                    <article class="application-card" data-application-id="<?php echo htmlspecialchars($application['id']); ?>" data-application-email="<?php echo htmlspecialchars($application['email'] ?? ''); ?>" data-application-name="<?php echo htmlspecialchars($application['institution_name'] ?? ''); ?>">
                        <!-- Debug Info -->
                        <div style="background: #f0f9ff; color: #0369a1; padding: 8px; border-radius: 4px; margin-bottom: 10px; font-size: 12px;">
                            Debug: ID=<?php echo htmlspecialchars($application['id']); ?> | Status=<?php echo htmlspecialchars($status); ?> | Buttons should be visible below
                        </div>
                        <header>
                            <div>
                                <h2 class="application-title"><?php echo htmlspecialchars($application['institution_name'] ?? 'Unnamed Institution'); ?></h2>
                                <span class="application-date">
                                    <i class="fas fa-calendar"></i> Submitted: <?php echo htmlspecialchars($submittedAt); ?>
                                    <?php if ($resubmittedAt): ?>
                                        <br><i class="fas fa-rotate" style="margin-top: 4px;"></i> Resubmitted: <?php echo htmlspecialchars($resubmittedAt); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <span class="status-badge <?php echo getStatusBadgeClass($status); ?>">
                                <i class="fas <?php echo getStatusIcon($status); ?>"></i>
                                <?php echo getStatusLabel($status); ?>
                            </span>
                        </header>
                        
                        <div class="card-body">
                            <div class="application-meta">
                                <div class="meta-item">
                                    <span class="meta-label">Contact Person</span>
                                    <span class="meta-value"><?php echo htmlspecialchars($application['contact_person'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Position</span>
                                    <span class="meta-value"><?php echo htmlspecialchars($application['contact_position'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Email</span>
                                    <span class="meta-value"><?php echo htmlspecialchars($application['email'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Phone</span>
                                    <span class="meta-value"><?php echo htmlspecialchars($application['contact_phone'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Address</span>
                                    <span class="meta-value"><?php echo htmlspecialchars($application['address'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Documents</span>
                                    <span class="meta-value"><?php echo count($documentFiles); ?> file(s)</span>
                                </div>
                            </div>
                            
                            <?php if (!empty($documentFiles)): ?>
                                <div class="documents-section">
                                    <h3 class="section-title"><i class="fas fa-folder-open"></i> Submitted Documents</h3>
                                    <div class="documents-grid">
                                        <?php foreach ($documentFiles as $docKey => $docMeta): ?>
                                            <?php
                                                $label = $docLabels[$docKey] ?? ucfirst(str_replace('_', ' ', $docKey));
                                                $docType = $docMeta['type'] ?? 'application/octet-stream';
                                                $fileName = $docMeta['name'] ?? $docKey;
                                                $docContent = $docMeta['content'] ?? '';
                                                $downloadHref = '';
                                                if (!empty($docContent)) {
                                                    $downloadHref = 'data:' . $docType . ';base64,' . $docContent;
                                                }
                                                
                                                // Determine icon based on file type
                                                $icon = 'fa-file';
                                                if (strpos($docType, 'pdf') !== false) {
                                                    $icon = 'fa-file-pdf';
                                                } elseif (strpos($docType, 'image') !== false) {
                                                    $icon = 'fa-file-image';
                                                } elseif (strpos($docType, 'word') !== false || strpos($docType, 'document') !== false) {
                                                    $icon = 'fa-file-word';
                                                }
                                            ?>
                                            <div class="document-item">
                                                <div class="document-icon">
                                                    <i class="fas <?php echo $icon; ?>"></i>
                                                </div>
                                                <div class="document-info">
                                                    <div class="document-name" title="<?php echo htmlspecialchars($fileName); ?>"><?php echo htmlspecialchars($label); ?></div>
                                                    <div class="document-type"><?php echo htmlspecialchars($docType); ?></div>
                                                </div>
                                                <?php if ($downloadHref): ?>
                                                    <a href="<?php echo $downloadHref; ?>" download="<?php echo htmlspecialchars($fileName); ?>" class="document-download" title="Download">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($committeeNotes)): ?>
                                <div class="committee-notes">
                                    <h4 class="committee-notes-title"><i class="fas fa-comment-dots"></i> Committee Notes</h4>
                                    <p class="committee-notes-text"><?php echo htmlspecialchars($committeeNotes); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($application['committee_notes'])): ?>
                                <div class="committee-notes">
                                    <h4 class="committee-notes-title"><i class="fas fa-exclamation-triangle"></i> Changes Requested</h4>
                                    <p class="committee-notes-text"><?php echo htmlspecialchars($application['committee_notes']); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Action Buttons Section -->
                            <div style="background: #dcfce7; color: #166534; padding: 8px; border-radius: 4px; margin-top: 10px; font-size: 12px;">
                                Debug: Rendering action buttons below...
                            </div>
                            <div class="action-buttons">
                                <button type="button" class="btn btn-request" onclick="openRequestChangesModal('<?php echo htmlspecialchars($application['id']); ?>', '<?php echo htmlspecialchars($application['institution_name'] ?? ''); ?>')">
                                    <i class="fas fa-edit"></i> Request Changes
                                </button>
                                <button type="button" class="btn btn-approve" onclick="openApproveModal('<?php echo htmlspecialchars($application['id']); ?>', '<?php echo htmlspecialchars($application['institution_name'] ?? ''); ?>', '<?php echo htmlspecialchars($application['email'] ?? ''); ?>', '<?php echo htmlspecialchars($application['contact_person'] ?? ''); ?>')">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button type="button" class="btn btn-reject" onclick="openRejectModal('<?php echo htmlspecialchars($application['id']); ?>', '<?php echo htmlspecialchars($application['institution_name'] ?? ''); ?>')">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </div>
                            <div style="background: #fef3c7; color: #78350f; padding: 8px; border-radius: 4px; margin-top: 10px; font-size: 12px;">
                                Debug: Action buttons rendered above
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
    
    <!-- Request Changes Modal -->
    <div class="modal-overlay" id="requestChangesModal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-icon warning">
                    <i class="fas fa-edit"></i>
                </div>
                <div>
                    <h3 class="modal-title">Request Changes</h3>
                    <p class="modal-subtitle">Ask applicant to modify their submission</p>
                </div>
            </div>
            <div class="modal-body">
                <div class="application-preview">
                    <div class="preview-item">
                        <span class="preview-label">Institution</span>
                        <span class="preview-value" id="requestModalInstitution">-</span>
                    </div>
                </div>
                <form id="requestChangesForm">
                    <input type="hidden" name="action" value="request_changes">
                    <input type="hidden" name="id" id="requestChangesAppId">
                    <div class="form-group">
                        <label class="form-label" for="committeeNotes">Committee Notes <span style="color: var(--danger);">*</span></label>
                        <textarea class="form-textarea" id="committeeNotes" name="notes" rows="6" placeholder="Please specify the exact changes needed (e.g., 'Please provide a clearer copy of the accreditation certificate', 'The MOA needs to be signed by both parties', etc.)" required minlength="20"></textarea>
                        <p class="form-help">Minimum 20 characters. Be specific about what needs to be corrected.</p>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('requestChangesModal')">Cancel</button>
                <button type="button" class="btn btn-request" onclick="submitRequestChanges()">
                    <span class="loading-spinner"></span>
                    <span class="btn-text"><i class="fas fa-paper-plane"></i> Send Request</span>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Approve Modal -->
    <div class="modal-overlay" id="approveModal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <h3 class="modal-title">Approve Application</h3>
                    <p class="modal-subtitle">Confirm approval and create portal account</p>
                </div>
            </div>
            <div class="modal-body">
                <div class="application-preview">
                    <div class="preview-item">
                        <span class="preview-label">Institution</span>
                        <span class="preview-value" id="approveModalInstitution">-</span>
                    </div>
                    <div class="preview-item">
                        <span class="preview-label">Contact Email</span>
                        <span class="preview-value" id="approveModalEmail">-</span>
                    </div>
                    <div class="preview-item">
                        <span class="preview-label">Contact Person</span>
                        <span class="preview-value" id="approveModalContact">-</span>
                    </div>
                </div>
                <div style="background: #dbeafe; border-left: 4px solid #3b82f6; padding: var(--space-4); border-radius: var(--radius-lg); margin-top: var(--space-4);">
                    <p style="margin: 0; color: #1e40af; font-size: 0.95rem;">
                        <i class="fas fa-info-circle"></i> <strong>Important:</strong> Approving this application will:
                    </p>
                    <ul style="margin: var(--space-2) 0 0 0; padding-left: var(--space-5); color: #1e40af; font-size: 0.9rem;">
                        <li>Create a new portal account for the school officer</li>
                        <li>Generate a temporary password (must be changed on first login)</li>
                        <li>Send login credentials via email</li>
                        <li>Update the application status to "Approved"</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('approveModal')">Cancel</button>
                <button type="button" class="btn btn-approve" onclick="submitApprove()">
                    <span class="loading-spinner"></span>
                    <span class="btn-text"><i class="fas fa-check"></i> Confirm Approval</span>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Reject Modal -->
    <div class="modal-overlay" id="rejectModal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-icon danger">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div>
                    <h3 class="modal-title">Reject Application</h3>
                    <p class="modal-subtitle">Decline affiliation request with reason</p>
                </div>
            </div>
            <div class="modal-body">
                <div class="application-preview">
                    <div class="preview-item">
                        <span class="preview-label">Institution</span>
                        <span class="preview-value" id="rejectModalInstitution">-</span>
                    </div>
                </div>
                <form id="rejectForm">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="id" id="rejectAppId">
                    <div class="form-group">
                        <label class="form-label" for="rejectionReason">Rejection Reason <span style="color: var(--danger);">*</span></label>
                        <textarea class="form-textarea" id="rejectionReason" name="reason" rows="5" placeholder="Explain why the application is being rejected (e.g., 'Incomplete documentation', 'Does not meet accreditation requirements', etc.)" required minlength="10"></textarea>
                        <p class="form-help">Minimum 10 characters. This will be included in the rejection email.</p>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
                <button type="button" class="btn btn-reject" onclick="submitReject()">
                    <span class="loading-spinner"></span>
                    <span class="btn-text"><i class="fas fa-times"></i> Confirm Rejection</span>
                </button>
            </div>
        </div>
    </div>
    
    <script>
        const API_URL = '<?php echo BASE_PUBLIC_URL; ?>/api/affiliation-review-action.php';
        
        function openRequestChangesModal(appId, institutionName) {
            document.getElementById('requestChangesAppId').value = appId;
            document.getElementById('requestModalInstitution').textContent = institutionName;
            document.getElementById('requestChangesModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function openApproveModal(appId, institutionName, email, contactPerson) {
            document.getElementById('approveModalInstitution').textContent = institutionName;
            document.getElementById('approveModalEmail').textContent = email;
            document.getElementById('approveModalContact').textContent = contactPerson;
            document.getElementById('approveModal').dataset.appId = appId;
            document.getElementById('approveModal').dataset.email = email;
            document.getElementById('approveModal').dataset.institution = institutionName;
            document.getElementById('approveModal').dataset.contact = contactPerson;
            document.getElementById('approveModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function openRejectModal(appId, institutionName) {
            document.getElementById('rejectAppId').value = appId;
            document.getElementById('rejectModalInstitution').textContent = institutionName;
            document.getElementById('rejectModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            document.body.style.overflow = '';
            
            // Reset forms
            if (modalId === 'requestChangesModal') {
                document.getElementById('requestChangesForm').reset();
            } else if (modalId === 'rejectModal') {
                document.getElementById('rejectForm').reset();
            }
        }
        
        // Close modal on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });
        
        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                    closeModal(modal.id);
                });
            }
        });
        
        function setLoading(button, loading) {
            if (loading) {
                button.classList.add('loading');
                button.disabled = true;
            } else {
                button.classList.remove('loading');
                button.disabled = false;
            }
        }
        
        function showNotification(message, type = 'success') {
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i><span>${message}</span>`;
            alert.style.position = 'fixed';
            alert.style.top = '20px';
            alert.style.right = '20px';
            alert.style.zIndex = '99999';
            alert.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
            document.body.appendChild(alert);
            
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }
        
        async function submitRequestChanges() {
            const form = document.getElementById('requestChangesForm');
            const notes = document.getElementById('committeeNotes').value.trim();
            
            if (!notes || notes.length < 20) {
                showNotification('Please provide detailed notes (minimum 20 characters)', 'error');
                return;
            }
            
            const formData = new FormData(form);
            const submitBtn = document.querySelector('#requestChangesModal .btn-request');
            
            setLoading(submitBtn, true);
            
            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Change request sent successfully. Email notification dispatched.', 'success');
                    closeModal('requestChangesModal');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(result.message || 'Failed to send request', 'error');
                }
            } catch (error) {
                showNotification('Network error. Please try again.', 'error');
                console.error('Error:', error);
            } finally {
                setLoading(submitBtn, false);
            }
        }
        
        async function submitApprove() {
            const modal = document.getElementById('approveModal');
            const appId = modal.dataset.appId;
            const email = modal.dataset.email;
            const institution = modal.dataset.institution;
            const contact = modal.dataset.contact;
            
            const submitBtn = modal.querySelector('.btn-approve');
            setLoading(submitBtn, true);
            
            try {
                const formData = new FormData();
                formData.append('action', 'approve');
                formData.append('id', appId);
                formData.append('email', email);
                formData.append('institution', institution);
                formData.append('contact_person', contact);
                
                const response = await fetch(API_URL, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Application approved! Portal account created and credentials sent.', 'success');
                    closeModal('approveModal');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showNotification(result.message || 'Failed to approve application', 'error');
                }
            } catch (error) {
                showNotification('Network error. Please try again.', 'error');
                console.error('Error:', error);
            } finally {
                setLoading(submitBtn, false);
            }
        }
        
        async function submitReject() {
            const form = document.getElementById('rejectForm');
            const reason = document.getElementById('rejectionReason').value.trim();
            
            if (!reason || reason.length < 10) {
                showNotification('Please provide a rejection reason (minimum 10 characters)', 'error');
                return;
            }
            
            const formData = new FormData(form);
            const submitBtn = document.querySelector('#rejectModal .btn-reject');
            
            setLoading(submitBtn, true);
            
            try {
                const response = await fetch(API_URL, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Application rejected. Notification email sent.', 'success');
                    closeModal('rejectModal');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification(result.message || 'Failed to reject application', 'error');
                }
            } catch (error) {
                showNotification('Network error. Please try again.', 'error');
                console.error('Error:', error);
            } finally {
                setLoading(submitBtn, false);
            }
        }
    </script>
</body>
</html>
