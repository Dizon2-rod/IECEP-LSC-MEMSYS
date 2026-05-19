<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../autoload.php';

header('Content-Type: application/json');

require_role(['admin', 'super_admin', 'school_officer', 'eb_auditor']);

$supabaseConfig = require __DIR__ . '/../../includes/supabase.php';
$supabase = new \App\Lib\SupabaseClient($supabaseConfig['url'], $supabaseConfig['anon_key']);
$blockchain = new \App\Lib\BlockchainService($supabase);
$complianceEngine = new \App\Lib\ComplianceEngine($supabase, $blockchain);

$action = $_GET['action'] ?? 'get';
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

switch ($action) {
    case 'get':
        $institutionId = $_GET['institution_id'] ?? $_SESSION['user']['institution_id'] ?? null;

        if (empty($institutionId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'institution_id required']);
            exit;
        }

        try {
            $report = $complianceEngine->getReport($institutionId, $year);
            
            if (!$report) {
                // Calculate if not exists
                $score = $complianceEngine->calculateForInstitution($institutionId, $year);
                $report = $complianceEngine->getReport($institutionId, $year);
            }

            echo json_encode(['success' => true, 'report' => $report]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'calculate':
        require_role(['admin', 'super_admin']);

        $institutionId = $_GET['institution_id'] ?? null;

        try {
            if ($institutionId) {
                $score = $complianceEngine->calculateForInstitution($institutionId, $year);
                echo json_encode(['success' => true, 'score' => $score]);
            } else {
                $results = $complianceEngine->calculateAll($year);
                echo json_encode(['success' => true, 'results' => $results]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'all':
        require_role(['admin', 'super_admin', 'eb_auditor']);

        try {
            $reports = $supabase->select('compliance_scores', ['year' => 'eq.' . $year]);
            echo json_encode(['success' => true, 'reports' => $reports]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
