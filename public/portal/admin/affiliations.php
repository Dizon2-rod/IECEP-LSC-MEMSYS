<?php
error_reporting(0);
ini_set('display_errors', 0);

// Handle POST requests for status updates - must be before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    error_log("POST request received: action=" . $_POST['action']);
    
    // Load dependencies
    $config = require __DIR__ . '/../../../src/config/supabase.php';
    require_once __DIR__ . '/../../../src/lib/SupabaseClient.php';
    require_once __DIR__ . '/../../../src/lib/EmailService.php';
    
    $supabase = new SupabaseClient($config['url'], $config['anon_key']);
    
    $applicationId = $_POST['application_id'];
    $status = $_POST['status'];
    $rejectionReason = $_POST['rejection_reason'] ?? '';
    $changesInstructions = $_POST['changes_instructions'] ?? '';
    
    try {
        $updateData = [
            'status' => $status,
            'reviewed_at' => date('Y-m-d H:i:s')
        ];
        
        if ($status === 'rejected' && !empty($rejectionReason)) {
            $updateData['rejection_reason'] = $rejectionReason;
        }
        
        if ($_POST['action'] === 'request_changes' && !empty($changesInstructions)) {
            $application = $supabase->select('pending_affiliations', ['id' => 'eq.' . $applicationId]);
            if (!empty($application) && is_array($application)) {
                $appData = $application[0];
                $documents = [];
                if (!empty($appData['documents'])) {
                    $documents = json_decode($appData['documents'], true) ?: [];
                }
                $documents['changes_instructions'] = $changesInstructions;
                $updateData['documents'] = json_encode($documents);
            }
        }
        
        $result = $supabase->update('pending_affiliations', $updateData, $applicationId);
        
        if ($result) {
            $application = $supabase->select('pending_affiliations', ['id' => 'eq.' . $applicationId]);
            
            if (!empty($application) && is_array($application)) {
                $appData = $application[0];
                $emailService = new \App\Lib\EmailService();
                
                if ($status === 'approved') {
                    $emailService->sendAffiliationApproved($appData['email'], $appData['institution_name']);
                } elseif ($status === 'rejected') {
                    $emailService->sendAffiliationRejected($appData['email'], $appData['institution_name'], $rejectionReason);
                } elseif ($_POST['action'] === 'request_changes' && !empty($changesInstructions)) {
                    $emailService->sendChangesRequested($appData['email'], $appData['institution_name'], $changesInstructions);
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'Application updated successfully']);
            exit;
        }
        
        echo json_encode(['success' => false]);
        exit;
    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../../../src/config/config.php';

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = array();
    session_unset();
    session_destroy();
    session_write_close();
    setcookie(session_name(), '', time() - 42000, '/');
    setcookie('PHPSESSID', '', time() - 42000, '/');
    header('Location: /IECEP-LSC-MEMSYS/login.php');
    exit;
}

require_role(['admin', 'eb_president']);

$user = get_user_info();

// Load Supabase configuration
require_once __DIR__ . '/../../../src/lib/SupabaseClient.php';
$config = require __DIR__ . '/../../../src/config/supabase.php';
$supabase = new SupabaseClient($config['url'], $config['anon_key']);

// Stop output buffering for regular page display
ob_end_clean();

// Fetch pending affiliations
try {
    $applications = $supabase->select('pending_affiliations', null, 'submitted_at', 'DESC');
} catch (Exception $e) {
    $applications = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affiliation Applications - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="/IECEP-LSC-MEMSYS/public/css/dashboard.css">
    <style>
        :root {
            --navy: #0A2F6C;
            --navy-light: #1e4a8a;
            --navy-dark: #072255;
            --gold: #F5A623;
            --gold-dark: #d48f1f;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-700: #334155;
            --gray-900: #0f172a;
            --success: #10b981;
            --success-light: #d1fae5;
            --error: #ef4444;
            --error-light: #fee2e2;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --info: #3b82f6;
            --info-light: #dbeafe;
        }

        .dashboard-content {
            padding: 40px;
            max-width: 1440px;
            margin: 0 auto;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: calc(100vh - 72px);
        }

        .main-content {
            margin-left: 260px;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }

        .section-header {
            margin-bottom: 36px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .section-header h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 6px;
            letter-spacing: -0.02em;
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-header p {
            color: var(--gray-500);
            font-size: 0.95rem;
            font-weight: 400;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.025);
        }

        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            font-size: 1.25rem;
        }

        .stat-card .stat-icon.pending {
            background: var(--info-light);
            color: var(--info);
        }

        .stat-card .stat-icon.approved {
            background: var(--success-light);
            color: var(--success);
        }

        .stat-card .stat-icon.rejected {
            background: var(--error-light);
            color: var(--error);
        }

        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 4px;
        }

        .stat-card .stat-label {
            font-size: 0.875rem;
            color: var(--gray-500);
            font-weight: 500;
        }

        .applications-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .application-card {
            background: white;
            padding: 28px;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid var(--gray-200);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .application-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gray-300);
        }

        .application-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
        }

        .application-card.pending_review::before {
            background: linear-gradient(90deg, var(--navy) 0%, var(--navy-light) 100%);
        }

        .application-card.approved::before {
            background: linear-gradient(90deg, var(--success) 0%, #34d399 100%);
        }

        .application-card.rejected::before {
            background: linear-gradient(90deg, var(--error) 0%, #f87171 100%);
        }

        .application-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .application-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .application-info h3 {
            color: var(--gray-900);
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .application-info span {
            color: var(--gray-500);
            font-size: 0.875rem;
            font-weight: 400;
        }

        .application-meta {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-badge.pending_review {
            background: var(--info-light);
            color: var(--info);
        }

        .status-badge.approved {
            background: var(--success-light);
            color: var(--success);
        }

        .status-badge.rejected {
            background: var(--error-light);
            color: var(--error);
        }

        .application-date {
            color: var(--gray-400);
            font-size: 0.75rem;
            font-weight: 500;
        }

        .application-details {
            color: var(--gray-700);
            line-height: 1.7;
            padding: 20px;
            background: var(--gray-50);
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .application-details p {
            margin: 0;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .application-details p strong {
            color: var(--gray-900);
            font-weight: 600;
            min-width: 100px;
        }

        .application-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            outline: none;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-outline {
            background: white;
            border: 1.5px solid var(--gray-300);
            color: var(--gray-700);
        }

        .btn-outline:hover {
            border-color: var(--navy);
            color: var(--navy);
            background: var(--gray-50);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success) 0%, #34d399 100%);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);
        }

        .btn-success:hover {
            box-shadow: 0 6px 8px -1px rgba(16, 185, 129, 0.3);
            transform: translateY(-2px);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning) 0%, #fbbf24 100%);
            color: var(--gray-900);
            box-shadow: 0 4px 6px -1px rgba(245, 158, 11, 0.2);
        }

        .btn-warning:hover {
            box-shadow: 0 6px 8px -1px rgba(245, 158, 11, 0.3);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--error) 0%, #f87171 100%);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2);
        }

        .btn-danger:hover {
            box-shadow: 0 6px 8px -1px rgba(239, 68, 68, 0.3);
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 80px 40px;
            background: white;
            border-radius: 16px;
            border: 2px dashed var(--gray-300);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--gray-300);
            margin-bottom: 24px;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 12px;
        }

        .empty-state p {
            color: var(--gray-500);
            font-size: 0.95rem;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 36px;
            border-radius: 20px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
        }

        .modal-header h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            letter-spacing: -0.01em;
        }

        .close-modal {
            background: var(--gray-100);
            border: none;
            font-size: 1.25rem;
            cursor: pointer;
            color: var(--gray-500);
            padding: 0;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            transition: all 0.2s;
        }

        .close-modal:hover {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .modal-body textarea {
            width: 100%;
            padding: 14px;
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            font-size: 0.95rem;
            font-family: inherit;
            resize: vertical;
            min-height: 140px;
            transition: all 0.2s;
        }

        .modal-body textarea:focus {
            outline: none;
            border-color: var(--navy);
            box-shadow: 0 0 0 4px rgba(10, 47, 108, 0.1);
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 28px;
        }

        /* Documents Modal */
        .documents-modal-content {
            max-width: 800px;
            padding: 32px;
        }

        .documents-modal-header {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 24px;
            position: relative;
        }

        .documents-modal-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .documents-modal-title h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--navy);
            margin: 0;
        }

        .modal-close-btn {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            border: none;
            background: var(--gray-100);
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-600);
        }

        .document-count-badge {
            background: var(--navy);
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .documents-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .document-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            transition: all 0.2s;
        }

        .document-item:hover {
            border-color: var(--navy);
            background: var(--gray-50);
        }

        .document-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .document-icon {
            width: 40px;
            height: 40px;
            background: var(--gray-100);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .document-icon i {
            color: var(--navy);
            font-size: 1.25rem;
        }

        .document-details {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .document-name {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--navy);
        }

        .document-type {
            font-size: 0.75rem;
            color: var(--gray-500);
            font-weight: 500;
            text-transform: uppercase;
        }

        .document-actions {
            display: flex;
            gap: 8px;
        }

        .btn-view {
            padding: 8px 16px;
            background: var(--navy);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-view:hover {
            background: var(--navy-light);
        }

        .btn-download {
            padding: 8px 16px;
            background: white;
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-download:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
            color: var(--navy);
        }

        .no-documents {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray-500);
        }

        .no-documents i {
            font-size: 3rem;
            color: var(--gray-300);
            margin-bottom: 16px;
        }

        .no-documents p {
            font-size: 0.9rem;
            color: var(--gray-500);
            font-weight: 500;
        }

        .documents-modal-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--gray-200);
        }

        .documents-modal-actions .btn-outline {
            padding: 10px 24px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        /* Preview Modal */
        .preview-modal-content {
            padding: 0;
            border-radius: 12px;
            width: 95%;
            max-width: 1000px;
            height: 95vh;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        
        .preview-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }
        
        .preview-header h3 {
            color: var(--navy);
            margin: 0;
            font-size: 1.1rem;
        }
        
        .preview-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #94a3b8;
        }
        
        .preview-close:hover {
            color: var(--navy);
        }
        
        .preview-body {
            flex: 1;
            padding: 0;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 600px;
            background: white;
        }
        
        .preview-body iframe {
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 0;
        }
        
        .preview-body img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border-radius: 8px;
        }
        
        .preview-unsupported {
            text-align: center;
            color: #94a3b8;
        }
        
        .preview-unsupported i {
            font-size: 3rem;
            margin-bottom: 16px;
            color: var(--gray-300);
        }

        /* Credentials Section */
        .credentials-section {
            margin-top: 20px;
            padding: 20px;
            background: linear-gradient(135deg, var(--success-light) 0%, #a7f3d0 100%);
            border-radius: 12px;
            border: 2px solid var(--success);
        }

        .credentials-section h4 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--success-dark, #065f46);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .credentials-section h4 i {
            font-size: 1.25rem;
        }

        .credentials-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .credential-item {
            background: white;
            padding: 16px;
            border-radius: 10px;
            border: 1px solid var(--success);
        }

        .credential-item label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
        }

        .credential-item .credential-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--gray-900);
            word-break: break-all;
        }

        .credential-item .credential-value.password {
            font-family: 'Courier New', monospace;
            letter-spacing: 0.5px;
        }

        .copy-credential {
            margin-left: 8px;
            background: none;
            border: none;
            color: var(--success);
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            transition: all 0.2s;
            font-size: 0.875rem;
        }

        .copy-credential:hover {
            background: var(--success-light);
        }
        
        @media (max-width: 768px) {
            .dashboard-content {
                padding: 24px;
            }

            .application-header {
                flex-direction: column;
                gap: 8px;
            }

            .documents-modal-content {
                width: 95%;
                margin: 20% auto;
            }

            .preview-modal-content {
                width: 95%;
                margin: 20% auto;
            }
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .toast {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 16px 20px;
            border-radius: 12px;
            background: white;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            min-width: 320px;
            max-width: 420px;
            animation: slideIn 0.3s ease-out;
            border-left: 4px solid;
        }

        .toast.success {
            border-left-color: #10b981;
        }

        .toast.error {
            border-left-color: #ef4444;
        }

        .toast.warning {
            border-left-color: #f59e0b;
        }

        .toast.info {
            border-left-color: #3b82f6;
        }

        .toast-icon {
            flex-shrink: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toast.success .toast-icon {
            color: #10b981;
        }

        .toast.error .toast-icon {
            color: #ef4444;
        }

        .toast.warning .toast-icon {
            color: #f59e0b;
        }

        .toast.info .toast-icon {
            color: #3b82f6;
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 600;
            font-size: 14px;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .toast-message {
            font-size: 13px;
            color: #64748b;
            line-height: 1.4;
        }

        .toast-close {
            flex-shrink: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #94a3b8;
            transition: color 0.2s;
            border: none;
            background: none;
            padding: 0;
        }

        .toast-close:hover {
            color: #64748b;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        .toast.removing {
            animation: slideOut 0.3s ease-in forwards;
        }
    </style>
</head>
<body>
    <div class="toast-container" id="toastContainer"></div>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../sidebar_admin.php'; ?>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="dashboard-header">
                <div class="header-content">
                    <div>
                        <h1>Affiliations</h1>
                        <p class="welcome-message">Review and manage affiliation applications</p>
                    </div>
                    <div class="header-actions">
                        <div class="user-menu">
                            <img src="<?php echo $user['user_metadata']['avatar_url'] ?? '/IECEP-LSC-MEMSYS/public/assets/images/default-avatar.png'; ?>" alt="User Avatar" class="user-avatar">
                            <span><?php echo htmlspecialchars($user['user_metadata']['full_name'] ?? 'User'); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="dashboard-content">
                <div class="section-header">
                    <div>
                        <h2>Pending Affiliations</h2>
                        <p><?php echo count($applications); ?> affiliation(s) received</p>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon pending">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-value">
                            <?php echo count(array_filter($applications, fn($a) => $a['status'] === 'pending_review')); ?>
                        </div>
                        <div class="stat-label">Pending Review</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon approved">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-value">
                            <?php echo count(array_filter($applications, fn($a) => $a['status'] === 'approved')); ?>
                        </div>
                        <div class="stat-label">Approved</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon rejected">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-value">
                            <?php echo count(array_filter($applications, fn($a) => $a['status'] === 'rejected')); ?>
                        </div>
                        <div class="stat-label">Rejected</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: var(--warning-light); color: var(--warning);">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="stat-value">
                            <?php echo count($applications); ?>
                        </div>
                        <div class="stat-label">Total Applications</div>
                    </div>
                </div>

                <?php if (empty($applications)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <h3>No affiliations yet</h3>
                    <p>Affiliation applications will appear here when submitted.</p>
                </div>
                <?php else: ?>
                <div class="applications-list">
                    <?php foreach ($applications as $application): ?>
                    <div class="application-card <?php echo htmlspecialchars($application['status']); ?>">
                        <div class="application-header">
                            <div class="application-info">
                                <h3><?php echo htmlspecialchars($application['institution_name']); ?></h3>
                                <span><?php echo htmlspecialchars($application['email']); ?></span>
                            </div>
                            <div class="application-meta">
                                <span class="status-badge <?php echo htmlspecialchars($application['status']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($application['status']))); ?>
                                </span>
                                <span class="application-date">
                                    <?php echo date('M j, Y g:i A', strtotime($application['submitted_at'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="application-details">
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($application['email'] ?? 'N/A'); ?></p>
                            <p><strong>Contact Person:</strong> <?php echo htmlspecialchars($application['contact_person']); ?> (<?php echo htmlspecialchars($application['contact_position'] ?? 'N/A'); ?>)</p>
                            <p><strong>Phone:</strong> <?php echo htmlspecialchars($application['contact_phone'] ?? 'N/A'); ?></p>
                            <p><strong>Address:</strong> <?php echo htmlspecialchars($application['address']); ?></p>
                        </div>

                        <?php if ($application['status'] === 'approved' && !empty($application['login_email']) && !empty($application['login_password'])): ?>
                        <div class="credentials-section">
                            <h4><i class="fas fa-key"></i> Login Credentials</h4>
                            <div class="credentials-grid">
                                <div class="credential-item">
                                    <label>Email</label>
                                    <div class="credential-value">
                                        <?php echo htmlspecialchars($application['login_email']); ?>
                                        <button class="copy-credential" onclick="copyToClipboard('<?php echo htmlspecialchars($application['login_email']); ?>', this)">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="credential-item">
                                    <label>Password</label>
                                    <div class="credential-value password">
                                        <?php echo htmlspecialchars($application['login_password']); ?>
                                        <button class="copy-credential" onclick="copyToClipboard('<?php echo htmlspecialchars($application['login_password']); ?>', this)">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="application-actions">
                            <button onclick="viewDocuments('<?php echo htmlspecialchars($application['id']); ?>')" class="btn btn-outline">
                                <i class="fas fa-file-alt"></i> View Documents
                            </button>
                            <?php if ($application['status'] === 'pending_review'): ?>
                            <button onclick="approveApplication('<?php echo htmlspecialchars($application['id']); ?>')" class="btn btn-success">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button onclick="showRequestChangesModal('<?php echo htmlspecialchars($application['id']); ?>')" class="btn btn-warning">
                                <i class="fas fa-clock"></i> Request Changes
                            </button>
                            <button onclick="showRejectModal('<?php echo htmlspecialchars($application['id']); ?>')" class="btn btn-danger">
                                <i class="fas fa-times"></i> Reject
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    
    <!-- Documents Modal -->
    <div id="documentsModal" class="modal">
        <div class="documents-modal-content">
            <div class="documents-modal-header">
                <div class="documents-modal-title">
                    <i class="fas fa-folder-open"></i>
                    <h3>Submitted Documents</h3>
                    <span id="documentCount" class="document-count-badge">0 files</span>
                </div>
                <button class="modal-close-btn" onclick="closeDocumentsModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="documentsList" class="documents-list"></div>
            <div class="documents-modal-actions">
                <button onclick="closeDocumentsModal()" class="btn btn-outline">Close</button>
            </div>
        </div>
    </div>
    
    <!-- Document Preview Modal -->
    <div id="previewModal" class="modal">
        <div class="preview-modal-content">
            <div class="preview-header">
                <h3 id="previewFileName">Document Preview</h3>
                <button class="preview-close" onclick="closePreviewModal()">&times;</button>
            </div>
            <div class="preview-body" id="previewBody">
                <div class="preview-unsupported">
                    <i class="fas fa-file"></i>
                    <p>Preview not available for this file type</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Request Changes Modal -->
    <div id="requestChangesModal" class="modal">
        <div class="modal-content">
            <h3>Request Changes</h3>
            <p style="margin-bottom: 16px; color: #64748b;">Provide specific instructions for the Registration Committee on what needs to be changed:</p>
            <textarea id="changesInstructions" placeholder="Example: Please update the school address to match the official registration document. Also provide a certified copy of the school's accreditation certificate." style="width: 100%; min-height: 120px;"></textarea>
            <div class="modal-actions">
                <button onclick="closeRequestChangesModal()" class="btn btn-outline">Cancel</button>
                <button onclick="confirmRequestChanges()" class="btn btn-warning">Send Changes Request</button>
            </div>
        </div>
    </div>
    
    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <h3>Reject Application</h3>
            <p style="margin-bottom: 16px; color: #64748b;">Please provide a reason for rejection:</p>
            <textarea id="rejectionReason" placeholder="Enter the reason for rejection..."></textarea>
            <div class="modal-actions">
                <button onclick="closeRejectModal()" class="btn btn-outline">Cancel</button>
                <button onclick="confirmReject()" class="btn btn-danger">Reject</button>
            </div>
        </div>
    </div>

    <script>
    // Global functions accessible from onclick handlers
    let currentApplicationId = null;
    let realtimeChannel = null;
    let applicationsData = <?php echo json_encode($applications); ?>;

    // Toast notification system
    function showToast(type, title, message, duration = 4000) {
        const container = document.getElementById('toastContainer');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = `toast ${type}`;

        const icons = {
            success: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>',
            error: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>',
            warning: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            info: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
        };

        toast.innerHTML = `
            <div class="toast-icon">${icons[type] || icons.info}</div>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        `;

        container.appendChild(toast);

        // Auto-remove after duration
        setTimeout(() => {
            toast.classList.add('removing');
            toast.addEventListener('animationend', () => {
                toast.remove();
            });
        }, duration);
    }

    // Copy to clipboard function
    window.copyToClipboard = function(text, button) {
        navigator.clipboard.writeText(text).then(() => {
            const originalIcon = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i>';
            showToast('success', 'Copied', 'Copied to clipboard');
            setTimeout(() => {
                button.innerHTML = originalIcon;
            }, 2000);
        }).catch(err => {
            showToast('error', 'Error', 'Failed to copy to clipboard');
        });
    };

    // Modal functions - must be in global scope for onclick handlers
    window.showRequestChangesModal = function(applicationId) {
        currentApplicationId = applicationId;
        document.getElementById('requestChangesModal').style.display = 'block';
    };

    window.closeRequestChangesModal = function() {
        document.getElementById('requestChangesModal').style.display = 'none';
        document.getElementById('changesInstructions').value = '';
        currentApplicationId = null;
    };

    window.showRejectModal = function(applicationId) {
        currentApplicationId = applicationId;
        document.getElementById('rejectModal').style.display = 'block';
    };

    window.closeRejectModal = function() {
        document.getElementById('rejectModal').style.display = 'none';
        document.getElementById('rejectionReason').value = '';
        currentApplicationId = null;
    };

    window.approveApplication = function(applicationId) {
        if (!confirm('Are you sure you want to approve this affiliation?')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'approve');
        formData.append('application_id', applicationId);
        formData.append('status', 'approved');

        const apiUrl = window.location.origin + '/IECEP-LSC-MEMSYS/api/affiliation_status';

        fetch(apiUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('success', 'Success', 'Affiliation approved successfully!');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('error', 'Error', 'Failed to approve affiliation: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('error', 'Error', 'Failed to approve affiliation: ' + error.message);
        });
    };

    window.viewDocuments = function(applicationId) {
        const application = applicationsData.find(app => app.id === applicationId);
        if (!application) {
            console.error('Affiliation not found:', applicationId);
            return;
        }

        let documents = {};
        if (application.documents) {
            // Documents is stored as JSON string, need to parse it
            if (typeof application.documents === 'string') {
                try {
                    documents = JSON.parse(application.documents);
                } catch (e) {
                    console.error('Error parsing documents:', e);
                    documents = {};
                }
            } else {
                documents = application.documents;
            }
        }

        const documentsList = document.getElementById('documentsList');

        // Filter out review_notes and other non-document fields
        const documentFiles = Object.keys(documents)
            .filter(key => key !== 'review_notes' && documents[key] && documents[key].name && documents[key].content)
            .map(key => documents[key]);

        if (documentFiles.length === 0) {
            documentsList.innerHTML = '<div class="no-documents"><i class="fas fa-file-alt" style="font-size: 2rem; margin-bottom: 10px;"></i><p>No documents submitted for this affiliation</p></div>';
        } else {
            let html = '';
            documentFiles.forEach((doc) => {
                html += `
                    <div class="document-item">
                        <div class="document-info">
                            <i class="fas fa-file"></i>
                            <span>${doc.name}</span>
                        </div>
                        <button class="btn-view" onclick="viewDocument('${doc.name}', '${doc.content}', '${doc.type}')">
                            <i class="fas fa-eye"></i> View
                        </button>
                    </div>
                `;
            });
            documentsList.innerHTML = html;
        }

        document.getElementById('documentsModal').style.display = 'block';
    };

    window.closeDocumentsModal = function() {
        document.getElementById('documentsModal').style.display = 'none';
    };

    window.viewDocument = function(fileName, base64Content, mimeType) {
        const fileExtension = fileName.split('.').pop().toLowerCase();
        const previewBody = document.getElementById('previewBody');
        const previewFileName = document.getElementById('previewFileName');
        previewFileName.textContent = fileName;

        try {
            // Handle PDF files
            if (mimeType === 'application/pdf' || fileExtension === 'pdf') {
                const dataUri = 'data:application/pdf;base64,' + base64Content;
                previewBody.innerHTML = `<iframe src="${dataUri}#toolbar=0&navpanes=0&scrollbar=0" style="width: 100%; height: 100%; border: none;"></iframe>`;
            }
            // Handle image files
            else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExtension)) {
                const dataUri = 'data:' + mimeType + ';base64,' + base64Content;
                previewBody.innerHTML = `<img src="${dataUri}" alt="${fileName}" style="max-width: 100%; max-height: 100%; object-fit: contain;">`;
            }
            // Handle text files
            else if (['txt', 'csv', 'json'].includes(fileExtension) || mimeType.includes('text')) {
                const binaryString = atob(base64Content);
                const text = new TextDecoder().decode(new Uint8Array(binaryString.split('').map(char => char.charCodeAt(0))));
                previewBody.innerHTML = `<pre style="white-space: pre-wrap; word-wrap: break-word; font-family: monospace; font-size: 0.9rem; overflow-x: auto; padding: 20px;">${text.substring(0, 10000)}</pre>`;
            }
            // For other file types
            else {
                previewBody.innerHTML = `
                    <div class="preview-unsupported">
                        <i class="fas fa-file"></i>
                        <p>Preview not available for ${fileExtension.toUpperCase()} files</p>
                        <p style="margin-top: 16px; font-size: 0.9rem;">Please download to view this file</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error previewing document:', error);
            previewBody.innerHTML = `
                <div class="preview-unsupported">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>Failed to preview document</p>
                </div>
            `;
        }

        document.getElementById('documentPreviewModal').style.display = 'block';
    };

    window.closePreviewModal = function() {
        const modal = document.getElementById('documentPreviewModal');
        const previewBody = document.getElementById('previewBody');
        if (modal) {
            modal.style.display = 'none';
        }
        if (previewBody) {
            previewBody.innerHTML = '';
        }
    };

    window.confirmRequestChanges = function() {
        const instructions = document.getElementById('changesInstructions').value;
        if (!instructions.trim()) {
            showToast('warning', 'Required', 'Please provide instructions for the changes requested');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'request_changes');
        formData.append('application_id', currentApplicationId);
        formData.append('status', 'pending_review');
        formData.append('changes_instructions', instructions);

        const apiUrl = window.location.origin + '/IECEP-LSC-MEMSYS/api/affiliation_status';
        console.log('Sending to:', apiUrl);

        fetch(apiUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('success', 'Success', 'Changes requested successfully!');
                closeRequestChangesModal();
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('error', 'Error', 'Failed to request changes: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('error', 'Error', 'Failed to request changes: ' + error.message);
        });
    };

    window.confirmReject = function() {
        const reason = document.getElementById('rejectionReason').value;
        if (!reason.trim()) {
            showToast('warning', 'Required', 'Please provide a reason for rejection');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'reject');
        formData.append('application_id', currentApplicationId);
        formData.append('status', 'rejected');
        formData.append('rejection_reason', reason);

        const apiUrl = window.location.origin + '/IECEP-LSC-MEMSYS/api/affiliation_status';

        fetch(apiUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('success', 'Success', 'Affiliation rejected successfully!');
                closeRejectModal();
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('error', 'Error', 'Failed to reject affiliation: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('error', 'Error', 'Failed to reject affiliation: ' + error.message);
        });
    };

    // Initialize realtime subscription
    async function initRealtime() {
        try {
            if (window.realtimeService) {
                realtimeChannel = await window.realtimeService.subscribeToAffiliations((payload) => {
                    console.log('Realtime update received:', payload);
                    // Reload page to show latest data
                    location.reload();
                });
                console.log('Realtime subscription established');
            }
        } catch (error) {
            console.error('Failed to initialize realtime:', error);
        }
    }

    // Initialize realtime on page load
    document.addEventListener('DOMContentLoaded', () => {
        initRealtime();
    });

    window.viewDocuments = function(applicationId) {
        const application = applicationsData.find(app => app.id === applicationId);
        if (!application) {
            showToast('error', 'Error', 'Affiliation not found');
            return;
        }

        let documents = {};
        if (application.documents) {
            // Documents is stored as JSON string, need to parse it
            if (typeof application.documents === 'string') {
                try {
                    documents = JSON.parse(application.documents);
                } catch (e) {
                    console.error('Error parsing documents:', e);
                    documents = {};
                }
            } else {
                documents = application.documents;
            }
        }

        const documentsList = document.getElementById('documentsList');

        // Filter out review_notes and other non-document fields
        const documentFiles = Object.keys(documents)
            .filter(key => key !== 'review_notes' && documents[key] && documents[key].name && documents[key].content)
            .map(key => documents[key]);

        if (documentFiles.length === 0) {
            documentsList.innerHTML = '<div class="no-documents"><i class="fas fa-folder-open"></i><p>No documents submitted for this affiliation</p></div>';
            document.getElementById('documentCount').textContent = '0 files';
        } else {
            let html = '';
            documentFiles.forEach((doc) => {
                const fileName = doc.name || 'Unnamed Document';
                const fileType = doc.type || 'application/octet-stream';
                const base64Content = doc.content || '';
                const fileExtension = fileName.split('.').pop().toLowerCase();

                // Get appropriate icon based on file type
                let iconClass = 'fa-file';
                if (fileExtension === 'pdf') {
                    iconClass = 'fa-file-pdf';
                } else if (fileExtension === 'doc' || fileExtension === 'docx') {
                    iconClass = 'fa-file-word';
                } else if (fileExtension === 'xls' || fileExtension === 'xlsx') {
                    iconClass = 'fa-file-excel';
                } else if (fileExtension === 'jpg' || fileExtension === 'jpeg' || fileExtension === 'png') {
                    iconClass = 'fa-file-image';
                } else if (fileExtension === 'zip' || fileExtension === 'rar') {
                    iconClass = 'fa-file-archive';
                }

                html += `
                    <div class="document-item">
                        <div class="document-info">
                            <div class="document-icon">
                                <i class="fas ${iconClass}"></i>
                            </div>
                            <div class="document-details">
                                <div class="document-name">${fileName}</div>
                                <div class="document-type">${fileExtension.toUpperCase()}</div>
                            </div>
                        </div>
                        <div class="document-actions">
                            <button class="btn-view" onclick="viewDocument('${fileName.replace(/'/g, "\\'")}', '${base64Content.replace(/'/g, "\\'")}', '${fileType}')">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button class="btn-download" onclick="downloadDocument('${fileName.replace(/'/g, "\\'")}', '${base64Content.replace(/'/g, "\\'")}', '${fileType}')">
                                <i class="fas fa-download"></i> Download
                            </button>
                        </div>
                    </div>
                `;
            });

            documentsList.innerHTML = html;
            document.getElementById('documentCount').textContent = `${documentFiles.length} file${documentFiles.length !== 1 ? 's' : ''}`;
        }

        document.getElementById('documentsModal').style.display = 'block';
    }

    function closeDocumentsModal() {
        document.getElementById('documentsModal').style.display = 'none';
    }

    window.viewDocument = function(fileName, base64Content, mimeType) {
        const fileExtension = fileName.split('.').pop().toLowerCase();
        const previewBody = document.getElementById('previewBody');
        const previewFileName = document.getElementById('previewFileName');

        if (!previewBody || !previewFileName) {
            console.error('Preview modal elements not found');
            showToast('error', 'Error', 'Preview modal not available');
            return;
        }

        previewFileName.textContent = fileName;

        try {
            // Handle PDF files
            if (mimeType === 'application/pdf' || fileExtension === 'pdf') {
                const dataUri = 'data:application/pdf;base64,' + base64Content;
                previewBody.innerHTML = `<iframe src="${dataUri}#toolbar=0&navpanes=0&scrollbar=0" style="width: 100%; height: 100%; border: none;"></iframe>`;
            }
            // Handle image files
            else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExtension)) {
                const dataUri = 'data:' + mimeType + ';base64,' + base64Content;
                previewBody.innerHTML = `<img src="${dataUri}" alt="${fileName}" style="max-width: 100%; max-height: 100%; object-fit: contain;">`;
            }
            // Handle text files
            else if (['txt', 'csv', 'json'].includes(fileExtension) || mimeType.includes('text')) {
                const binaryString = atob(base64Content);
                const text = new TextDecoder().decode(new Uint8Array(binaryString.split('').map(char => char.charCodeAt(0))));
                previewBody.innerHTML = `<pre style="white-space: pre-wrap; word-wrap: break-word; font-family: monospace; font-size: 0.9rem; overflow-x: auto; padding: 20px;">${text.substring(0, 10000)}</pre>`;
            }
            // For other file types
            else {
                previewBody.innerHTML = `
                    <div class="preview-unsupported">
                        <i class="fas fa-file"></i>
                        <p>Preview not available for ${fileExtension.toUpperCase()} files</p>
                        <p style="margin-top: 16px; font-size: 0.9rem;">Please download to view this file</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error previewing document:', error);
            previewBody.innerHTML = `
                <div class="preview-unsupported">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>Failed to preview document</p>
                </div>
            `;
        }
        
        document.getElementById('previewModal').style.display = 'block';
    }

    function closePreviewModal() {
        document.getElementById('previewModal').style.display = 'none';
    }

    window.downloadDocument = function(fileName, base64Content, mimeType) {
        try {
            const binaryString = atob(base64Content);
            const bytes = new Uint8Array(binaryString.length);
            for (let i = 0; i < binaryString.length; i++) {
                bytes[i] = binaryString.charCodeAt(i);
            }

            const blob = new Blob([bytes], { type: mimeType });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = fileName;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);

            showToast('success', 'Success', 'Document downloaded successfully!');
        } catch (error) {
            console.error('Error downloading document:', error);
            showToast('error', 'Error', 'Failed to download document');
        }
    }

    function approveApplication(applicationId) {
        if (!confirm('Are you sure you want to approve this affiliation?')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'update_status');
        formData.append('application_id', applicationId);
        formData.append('status', 'approved');

        fetch('/IECEP-LSC-MEMSYS/src/api/affiliation_status.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('success', 'Success', 'Affiliation approved successfully!');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('error', 'Error', 'Failed to approve affiliation: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('error', 'Error', 'Failed to approve affiliation: ' + error.message);
        });
    }

    function rejectApplication(applicationId) {
        // This function is now handled by showRejectModal and confirmReject
        showRejectModal(applicationId);
    }

    function confirmReject() {
        const reason = document.getElementById('rejectionReason').value;
        if (!reason.trim()) {
            showToast('warning', 'Required', 'Please provide a reason for rejection');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'update_status');
        formData.append('application_id', currentApplicationId);
        formData.append('status', 'rejected');
        formData.append('rejection_reason', reason);

        fetch('/IECEP-LSC-MEMSYS/src/api/affiliation_status.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('success', 'Success', 'Affiliation rejected successfully!');
                closeRejectModal();
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('error', 'Error', 'Failed to reject affiliation: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('error', 'Error', 'Failed to reject affiliation: ' + error.message);
        });
    }

    function showRequestChangesModal(applicationId) {
        currentApplicationId = applicationId;
        document.getElementById('requestChangesModal').style.display = 'block';
    }

    function closeRequestChangesModal() {
        document.getElementById('requestChangesModal').style.display = 'none';
        document.getElementById('changesInstructions').value = '';
        currentApplicationId = null;
    }

    function confirmRequestChanges() {
        const instructions = document.getElementById('changesInstructions').value;
        if (!instructions.trim()) {
            showToast('warning', 'Required', 'Please provide instructions for the changes requested');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'request_changes');
        formData.append('application_id', currentApplicationId);
        formData.append('status', 'pending_review');
        formData.append('changes_instructions', instructions);

        const apiUrl = window.location.origin + '/IECEP-LSC-MEMSYS/api/affiliation_status';

        fetch(apiUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response content-type:', response.headers.get('content-type'));
            return response.text();
        })
        .then(text => {
            console.log('Raw response:', text.substring(0, 200));
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    showToast('success', 'Success', 'Changes request sent to the applicant via email!');
                    closeRequestChangesModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('error', 'Error', 'Failed to send changes request: ' + (data.error || 'Unknown error'));
                }
            } catch (e) {
                console.error('JSON parse error:', e);
                showToast('error', 'Error', 'Server returned invalid response. Check console for details.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('error', 'Error', 'Failed to send changes request: ' + error.message);
        });
    }

    function showRejectModal(applicationId) {
        currentApplicationId = applicationId;
        document.getElementById('rejectModal').style.display = 'block';
    }

    function closeRejectModal() {
        document.getElementById('rejectModal').style.display = 'none';
        document.getElementById('rejectionReason').value = '';
        currentApplicationId = null;
    }

    function confirmReject() {
        const reason = document.getElementById('rejectionReason').value;
        if (!reason.trim()) {
            showToast('warning', 'Required', 'Please provide a reason for rejection');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'approve');
        formData.append('application_id', currentApplicationId);
        formData.append('status', 'rejected');
        formData.append('rejection_reason', reason);

        fetch('/IECEP-LSC-MEMSYS/public/portal/admin/affiliations.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('success', 'Success', 'Affiliation rejected successfully!');
                closeRejectModal();
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('error', 'Error', 'Failed to reject affiliation');
            }
        });
    }

    // Close modals when clicking outside
    window.onclick = function(event) {
        const docModal = document.getElementById('documentsModal');
        const previewModal = document.getElementById('previewModal');
        const requestChangesModal = document.getElementById('requestChangesModal');
        const rejectModal = document.getElementById('rejectModal');
        if (event.target === docModal) {
            closeDocumentsModal();
        }
        if (event.target === previewModal) {
            closePreviewModal();
        }
        if (event.target === requestChangesModal) {
            closeRequestChangesModal();
        }
        if (event.target === rejectModal) {
            closeRejectModal();
        }
    }
    </script>
</body>
</html>
