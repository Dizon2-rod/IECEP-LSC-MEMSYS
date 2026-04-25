<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        require_once __DIR__ . '/../config/config.php';
        $config = require __DIR__ . '/../config/supabase.php';
        require_once __DIR__ . '/../lib/SupabaseClient.php';
        require_once __DIR__ . '/../lib/EmailService.php';

        $supabase = new SupabaseClient($config['url'], $config['anon_key']);

        $applicationId = $_POST['application_id'];
        $status = $_POST['status'];
        $rejectionReason = $_POST['rejection_reason'] ?? '';
        $changesInstructions = $_POST['changes_instructions'] ?? '';

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
                    // Generate login credentials
                    $loginEmail = $appData['email'];
                    $loginPassword = generatePassword();

                    // Store credentials in the application record
                    $credentialData = [
                        'login_email' => $loginEmail,
                        'login_password' => $loginPassword
                    ];
                    $supabase->update('pending_affiliations', $credentialData, $applicationId);

                    // Send credentials via email
                    $emailService->sendCredentials($loginEmail, $loginEmail, $loginPassword);
                    $emailService->sendAffiliationApproved($appData['email'], $appData['institution_name']);
                } elseif ($status === 'rejected') {
                    $emailService->sendAffiliationRejected($appData['email'], $appData['institution_name'], $rejectionReason);
                } elseif ($_POST['action'] === 'request_changes' && !empty($changesInstructions)) {
                    $emailService->sendChangesRequested($appData['email'], $appData['institution_name'], $changesInstructions, $appData);
                }
            }

            echo json_encode(['success' => true, 'message' => 'Application updated successfully']);
            exit;
        }

        echo json_encode(['success' => false]);
        exit;
    } catch (Throwable $e) {
        error_log("affiliation_status.php Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

function generatePassword() {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    $password = '';
    for ($i = 0; $i < 12; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
exit;
?>
