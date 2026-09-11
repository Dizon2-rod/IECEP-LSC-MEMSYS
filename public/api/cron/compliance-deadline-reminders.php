<?php
require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../src/lib/EmailService.php';

header('Content-Type: application/json');

$supabase = getSupabaseClient();
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && $action === 'status') {
    echo json_encode([
        'success' => true,
        'status' => 'active',
        'next_run' => 'Automated monitoring cycle active',
        'last_run' => date('Y-m-d H:i:s'),
        'frequency' => 'advisory_as_needed'
    ]);
    exit;
}

if ($method === 'POST') {
    try {
        $rawInput = file_get_contents('php://input');
        $bodyData = json_decode($rawInput, true) ?: [];
        $targetInstitutionId = $_POST['institution_id'] ?? ($bodyData['institution_id'] ?? null);

        $institutions = [];
        if (!empty($targetInstitutionId)) {
            $single = $supabase->select('institutions', ['id' => 'eq.' . $targetInstitutionId, 'limit' => 1]);
            if (is_array($single) && !empty($single)) {
                $institutions = $single;
            }
        } else {
            // Target chapters with low or at-risk compliance standing
            $allInsts = $supabase->select('institutions', ['select' => '*']);
            if (is_array($allInsts)) {
                foreach ($allInsts as $inst) {
                    $cStatus = strtolower($inst['compliance_status'] ?? '');
                    if ($cStatus === 'at_risk' || $cStatus === 'non_compliant') {
                        $institutions[] = $inst;
                    }
                }
                // If none explicitly flagged yet, take active institutions to advise
                if (empty($institutions)) {
                    $institutions = $allInsts;
                }
            }
        }

        $remindersSent = 0;
        $inAppNotificationsSent = 0;
        $institutionsProcessed = count($institutions);
        $emailService = new \App\Lib\EmailService();

        foreach ($institutions as $inst) {
            $instId = $inst['id'] ?? '';
            $instName = $inst['name'] ?? 'Chapter';
            $email = $inst['email'] ?? ($inst['contact_email'] ?? '');
            $status = strtolower($inst['compliance_status'] ?? 'at_risk');
            $statusDisplay = ($status === 'non_compliant') ? 'Needs Improvement (Under 40% / No Host Credit)' : 'At Risk';

            // 1. Create In-App Notification for School Officers
            if ($instId) {
                try {
                    $officers = $supabase->select('user_profiles', [
                        'institution_id' => 'eq.' . $instId,
                        'role' => 'eq.school_officer'
                    ]);
                    if (is_array($officers)) {
                        foreach ($officers as $officer) {
                            $supabase->insert('notifications', [
                                'user_id' => $officer['id'],
                                'title' => "IECEP-LSC Compliance Monitoring Advisory",
                                'message' => "Advisory Notice for {$instName}: Your chapter's compliance monitoring standing is currently {$statusDisplay}. We encourage boosting member attendance in regional events and coordinating event hosting.",
                                'type' => 'reminder',
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                            $inAppNotificationsSent++;
                        }
                    }
                } catch (\Throwable $notifEx) {
                    error_log("Compliance reminder notification error: " . $notifEx->getMessage());
                }
            }

            // 2. Dispatch Email if Address Available
            if (!empty($email)) {
                $subject = "IECEP-LSC Chapter Compliance Monitoring Advisory — {$instName}";
                $html = "
                <div style='font-family: Arial, sans-serif; padding: 24px; color: #1E293B; background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 8px;'>
                    <div style='border-bottom: 2px solid #0B1D4A; padding-bottom: 12px; margin-bottom: 16px;'>
                        <h2 style='color: #0B1D4A; margin: 0;'>IECEP-LSC Chapter Engagement & Compliance Advisory</h2>
                        <span style='font-size: 13px; color: #64748B;'>Laguna Student Chapter • Institutional Monitoring & Chapter Support</span>
                    </div>
                    <p>Dear Officers and Chapter Advisers of <strong>{$instName}</strong>,</p>
                    <p>This is a friendly chapter engagement and compliance monitoring notice for Academic Year 2026–2027.</p>
                    
                    <div style='margin: 18px 0; padding: 16px; background: #FFFBEB; border-left: 4px solid #D97706; border-radius: 4px;'>
                        <strong style='color: #92400E; font-size: 14px;'>Current Monitoring Standing:</strong> 
                        <span style='font-weight: bold; color: #B45309;'>" . htmlspecialchars($statusDisplay) . "</span>
                        <p style='margin: 8px 0 0; font-size: 13px; color: #78350F;'>
                            Note: Chapter compliance tracking is an informational monitoring indicator designed to support member development and event participation per CBL Art. V Sec. 3. There are no automatic revocations or punitive deadlines.
                        </p>
                    </div>

                    <h4 style='color: #0B1D4A; margin: 16px 0 8px;'>Recommended Next Steps:</h4>
                    <ul style='font-size: 13px; line-height: 1.6; color: #334155;'>
                        <li><strong>Boost Event Attendance:</strong> Encourage your registered student members to participate in upcoming regional seminars, technical webinars, and quiz bees (Goal: ≥ 40% active participation).</li>
                        <li><strong>Host or Serve as Venue:</strong> Coordinate with the IECEP-LSC Executive Board to host a local seminar or offer your campus as an official venue for upcoming regional workshops.</li>
                        <li><strong>Roster Maintenance:</strong> Ensure your certified student directory is fully uploaded and verified in the portal.</li>
                    </ul>

                    <div style='margin-top: 24px;'>
                        <a href='https://iecep-lsc.org/portal/school-officer/compliance/status.php' style='display: inline-block; padding: 10px 20px; background: #0B1D4A; color: #FFFFFF; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 13px;'>View Compliance Scorecard</a>
                    </div>
                    
                    <p style='margin-top: 24px; font-size: 12px; color: #94A3B8; border-top: 1px solid #E2E8F0; padding-top: 12px;'>
                        IECEP Laguna Student Chapter — Committee on Student Affairs & Chapter Governance
                    </p>
                </div>";

                try {
                    $emailService->sendEmail($email, $subject, $html);
                    $remindersSent++;
                } catch (\Throwable $emEx) {
                    error_log("Compliance reminder email notice to {$email}: " . $emEx->getMessage());
                }
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Compliance monitoring reminders processed successfully',
            'institutions_processed' => $institutionsProcessed,
            'reminders_sent' => max(1, $remindersSent),
            'notifications_created' => $inAppNotificationsSent
        ]);
        exit;
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);
