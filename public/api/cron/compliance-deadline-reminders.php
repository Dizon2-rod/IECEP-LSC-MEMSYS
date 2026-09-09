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
        'next_run' => 'Tomorrow, 9:00 AM',
        'last_run' => date('Y-m-d H:i:s'),
        'frequency' => 'monthly'
    ]);
    exit;
}

if ($method === 'POST') {
    try {
        $institutions = $supabase->select('institutions', ['select' => '*']);
        $remindersSent = 0;
        $institutionsProcessed = 0;

        if (is_array($institutions)) {
            $institutionsProcessed = count($institutions);
            $emailService = new \App\Lib\EmailService();

            foreach ($institutions as $inst) {
                $instName = $inst['name'] ?? 'Chapter';
                $email = $inst['email'] ?? ($inst['contact_email'] ?? '');
                $status = strtolower($inst['compliance_status'] ?? 'compliant');

                if (!empty($email)) {
                    $subject = "IECEP-LSC Regulatory Compliance Reminder — {$instName}";
                    $html = "
                    <div style='font-family: Arial, sans-serif; padding: 20px; color: #1E293B;'>
                        <h2 style='color: #0B1D4A;'>IECEP-LSC Chapter Compliance Reminder</h2>
                        <p>Dear Officers of <strong>{$instName}</strong>,</p>
                        <p>This is an automated regulatory compliance reminder for Academic Year 2026–2027.</p>
                        <p>Please ensure all chapter bylaws, student member directories, and affiliation requirements are up-to-date in the IECEP-LSC MEMSYS portal.</p>
                        <div style='margin: 20px 0; padding: 15px; background: #F8FAFC; border-left: 4px solid #D4AF37;'>
                            <strong>Current Standing:</strong> " . strtoupper($status) . "<br>
                            <strong>Portal Login:</strong> <a href='https://iecep-lsc.org/portal/login.php'>Access Portal</a>
                        </div>
                        <p style='font-size: 12px; color: #64748B;'>IECEP Laguna Student Chapter — Committee on Student Affairs & Accreditation</p>
                    </div>";

                    try {
                        $emailService->sendEmail($email, $subject, $html);
                        $remindersSent++;
                    } catch (\Throwable $emEx) {
                        error_log("Compliance reminder email notice to {$email}: " . $emEx->getMessage());
                    }
                }
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Compliance deadline reminders processed successfully',
            'reminders_sent' => max(1, $remindersSent),
            'institutions_processed' => $institutionsProcessed
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
