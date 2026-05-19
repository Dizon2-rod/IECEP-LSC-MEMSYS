<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../src/lib/Supabase.php';
require_once __DIR__ . '/../../src/lib/BlockchainService.php';
require_once __DIR__ . '/../../src/lib/EmailService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$action = filter_var($_POST['action'] ?? '', FILTER_SANITIZE_STRING);
$supabase = new Supabase();
$blockchain = new BlockchainService();
$email = new EmailService();

try {
    if ($action === 'request_revision') {
        require_role(['registration_committee', 'admin', 'super_admin']);
        
        $affiliation_id = filter_var($_POST['affiliation_id'] ?? '', FILTER_SANITIZE_STRING);
        $explanation = filter_var($_POST['explanation'] ?? '', FILTER_SANITIZE_STRING);
        $deadline_days = filter_var($_POST['deadline_days'] ?? 7, FILTER_VALIDATE_INT);
        
        if (!$affiliation_id || !$explanation) {
            throw new Exception('Missing required fields');
        }
        
        // Get affiliation details
        $affiliation = $supabase->select('pending_affiliations', '*', ['id' => $affiliation_id])[0] ?? null;
        if (!$affiliation) {
            throw new Exception('Affiliation not found');
        }
        
        // Generate secure token
        $token = bin2hex(random_bytes(32));
        $deadline = date('Y-m-d H:i:s', strtotime("+{$deadline_days} days"));
        
        // Create revision request
        $revision = $supabase->insert('revision_requests', [
            'affiliation_id' => $affiliation_id,
            'token' => $token,
            'explanation' => $explanation,
            'requested_by' => $_SESSION['user']['id'],
            'deadline' => $deadline,
            'status' => 'pending'
        ]);
        
        // Update affiliation status
        $supabase->update('pending_affiliations', 
            ['status' => 'revision_requested', 'updated_at' => date('Y-m-d H:i:s')],
            ['id' => $affiliation_id]
        );
        
        // Send email
        $revision_link = BASE_URL . "/public/revise-affiliation.php?token={$token}";
        $email_body = "Dear Applicant,\n\n";
        $email_body .= "Your affiliation application requires revision.\n\n";
        $email_body .= "Reason: {$explanation}\n\n";
        $email_body .= "Please submit your revised application by {$deadline} using this link:\n";
        $email_body .= "{$revision_link}\n\n";
        $email_body .= "Best regards,\nIECEP-LSC Registration Committee";
        
        $email->send(
            $affiliation['contact_email'],
            'Affiliation Application - Revision Required',
            $email_body
        );
        
        // Blockchain record
        $blockchain->recordEvent('affiliation_revision_request', $affiliation_id, hash('sha256', json_encode($revision)));
        
        // Audit log
        log_audit('affiliation_revision_request', 'pending_affiliations', $affiliation_id, null, ['explanation' => $explanation, 'deadline' => $deadline]);
        
        echo json_encode(['success' => true, 'message' => 'Revision request sent']);
        
    } elseif ($action === 'submit_revision') {
        // Public action - no auth required
        $token = filter_var($_POST['token'] ?? '', FILTER_SANITIZE_STRING);
        
        if (!$token) {
            throw new Exception('Invalid token');
        }
        
        // Validate token and deadline
        $revision = $supabase->select('revision_requests', '*', ['token' => $token, 'status' => 'pending'])[0] ?? null;
        if (!$revision) {
            throw new Exception('Invalid or expired revision request');
        }
        
        if (strtotime($revision['deadline']) < time()) {
            $supabase->update('revision_requests', ['status' => 'expired'], ['id' => $revision['id']]);
            throw new Exception('Revision deadline has passed');
        }
        
        // Update affiliation with new data
        $update_data = [];
        if (!empty($_POST['institution_name'])) {
            $update_data['institution_name'] = filter_var($_POST['institution_name'], FILTER_SANITIZE_STRING);
        }
        if (!empty($_POST['contact_person'])) {
            $update_data['contact_person'] = filter_var($_POST['contact_person'], FILTER_SANITIZE_STRING);
        }
        if (!empty($_POST['contact_email'])) {
            $update_data['contact_email'] = filter_var($_POST['contact_email'], FILTER_VALIDATE_EMAIL);
        }
        if (!empty($_POST['contact_phone'])) {
            $update_data['contact_phone'] = filter_var($_POST['contact_phone'], FILTER_SANITIZE_STRING);
        }
        
        // Handle file uploads (MOA, accreditation, etc.)
        if (!empty($_FILES)) {
            foreach ($_FILES as $key => $file) {
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = __DIR__ . '/../../uploads/affiliations/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    $filename = uniqid() . '_' . basename($file['name']);
                    move_uploaded_file($file['tmp_name'], $upload_dir . $filename);
                    $update_data[$key] = $filename;
                }
            }
        }
        
        $update_data['status'] = 'pending';
        $update_data['updated_at'] = date('Y-m-d H:i:s');
        
        $supabase->update('pending_affiliations', $update_data, ['id' => $revision['affiliation_id']]);
        
        // Mark revision as submitted
        $supabase->update('revision_requests', ['status' => 'submitted', 'updated_at' => date('Y-m-d H:i:s')], ['id' => $revision['id']]);
        
        // Blockchain record
        $blockchain->recordEvent('affiliation_revision_submit', $revision['affiliation_id'], hash('sha256', json_encode($update_data)));
        
        // Notify committee
        $committee_users = $supabase->select('user_profiles', 'id,email', ['role' => 'registration_committee']);
        foreach ($committee_users as $user) {
            $supabase->insert('notifications', [
                'user_id' => $user['id'],
                'type' => 'affiliation_revision',
                'title' => 'Affiliation Revision Submitted',
                'message' => 'A revised affiliation application has been submitted for review.',
                'reference_id' => $revision['affiliation_id']
            ]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Revision submitted successfully']);
        
    } else {
        throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
