<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $config = require __DIR__ . '/../../../../src/config/supabase.php';
        require_once __DIR__ . '/../../../../src/lib/SupabaseClient.php';
        require_once __DIR__ . '/../../../../src/lib/EmailService.php';
        
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
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
exit;
?>
