<?php
require_once __DIR__ . '/bootstrap.php';
/**
 * Bulk Assign Membership IDs
 * 
 * POST endpoint for assigning multiple membership IDs in a single transaction
 * All rows succeed or all fail (atomic operation)
 * Auto-creates Supabase user accounts and sends credentials
 */

require_once __DIR__ . '/../../../includes/paths.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/supabase-admin.php';
require_once __DIR__ . '/../../../src/lib/EmailService.php';
require_once __DIR__ . '/../../portal/auth_check.php';

require_role(['admin', 'registration']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
    exit;
}

try {
    $supabase = getSupabaseClient();
    $emailService = new \App\Lib\EmailService();
    
    $application_id = $_POST['application_id'] ?? null;
    $rows_json = $_POST['rows'] ?? '[]';
    $rows = json_decode($rows_json, true);
    $assigned_by_user_id = $_SESSION['user']['id'] ?? null;
    
    if (!$application_id || !is_array($rows) || empty($rows)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid input: application_id and rows array required']);
        exit;
    }

    // Validate application exists and is approved
    $appResponse = $supabase->from('pending_affiliations')
        ->select('id, institution_id')
        ->eq('id', $application_id)
        ->eq('status', 'approved')
        ->limit(1)
        ->execute();

    $application = $appResponse->data[0] ?? null;
    if (!$application) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Application not found or not approved']);
        exit;
    }
    $institution_id = $application['institution_id'];

    $year = (int)date('Y');
    $assigned_ids = [];
    $failed_rows = [];
    $emailQueue = [];

    foreach ($rows as $row) {
        try {
            $row_id = $row['id'] ?? null;
            $is_paid = isset($row['is_paid']) ? (bool)$row['is_paid'] : false;
            $member_type = $row['member_type'] ?? null;

            if (!$row_id || !$member_type || !in_array($member_type, ['new', 'old'])) {
                $failed_rows[] = "Row $row_id: Invalid input parameters";
                continue;
            }

            if (!$is_paid) {
                $failed_rows[] = "Row $row_id: Payment not marked as paid";
                continue;
            }

            // Fetch import row
            $importResponse = $supabase->from('membership_directory_imports')
                ->select('*')
                ->eq('id', $row_id)
                ->eq('application_id', $application_id)
                ->limit(1)
                ->execute();

            $import_row = $importResponse->data[0] ?? null;

            if (!$import_row) {
                $failed_rows[] = "Row $row_id: Import row not found";
                continue;
            }

            $email = $import_row['email'];
            $name = $import_row['name'];
            $birthday = $import_row['birthday'];
            $member_id = null;
            $membership_id = null;

            if ($member_type === 'old') {
                // Find existing member
                $existingResponse = $supabase->from('members')
                    ->select('id, membership_id')
                    ->or('email.eq.' . $email . ',full_name.eq.' . $name . ',birthday.eq.' . $birthday)
                    ->limit(1)
                    ->execute();

                $existing = $existingResponse->data[0] ?? null;

                if (!$existing) {
                    $failed_rows[] = "Row $row_id: Old member '$name' not found in system";
                    continue;
                }

                $member_id = $existing['id'];
                $membership_id = $existing['membership_id'];

                // Update existing member
                $supabase->from('members')
                    ->update([
                        'payment_status' => $is_paid,
                        'validated_at' => date('Y-m-d H:i:s'),
                        'validated_by' => $assigned_by_user_id,
                    ])
                    ->eq('id', $member_id)
                    ->execute();

                // Queue renewal email
                $emailQueue[] = [
                    'email' => $email,
                    'full_name' => $name,
                    'membership_id' => $membership_id,
                    'type' => 'renewal',
                ];

            } else { // 'new'
                // Generate membership ID
                $membership_id = generate_membership_id($supabase, $year);

                // Create new member
                $memberData = [
                    'full_name' => $import_row['name'],
                    'email' => $import_row['email'],
                    'birthday' => $import_row['birthday'],
                    'address' => $import_row['address'],
                    'phone' => $import_row['phone'],
                    'membership_id' => $membership_id,
                    'member_type' => 'new',
                    'payment_status' => $is_paid,
                    'year_level' => $import_row['sheet_name'],
                    'institution_id' => $institution_id,
                    'validated_at' => date('Y-m-d H:i:s'),
                    'validated_by' => $assigned_by_user_id,
                    'picture_url' => $import_row['picture_url'],
                    'signature_url' => $import_row['signature_url'],
                    'is_new' => true,
                ];
                $insertResponse = $supabase->from('members')->insert([$memberData])->execute();
                $member_id = $insertResponse->data[0]['id'] ?? null;

                // Check if Supabase user exists
                $existingUser = checkSupabaseUserByEmail($email);

                if (!$existingUser) {
                    // Create Supabase user
                    $password = bin2hex(random_bytes(6)); // 12-char hex password
                    $newUser = createSupabaseUser($email, $password, $name);

                    if ($newUser && isset($newUser['id'])) {
                        $userId = $newUser['id'];

                        // Insert user_profiles
                        $supabase->from('user_profiles')->insert([[
                            'user_id' => $userId,
                            'role' => 'member',
                            'full_name' => $name,
                            'institution_id' => $institution_id,
                            'force_password_change' => true,
                        ]])->execute();

                        // Link member record
                        $supabase->from('members')
                            ->update(['user_id' => $userId])
                            ->eq('id', $member_id)
                            ->execute();

                        // Queue credential email
                        $emailQueue[] = [
                            'email' => $email,
                            'full_name' => $name,
                            'membership_id' => $membership_id,
                            'password' => $password,
                            'type' => 'new_member',
                        ];

                        log_audit('account_created', 'members', $member_id, null, [
                            'membership_id' => $membership_id,
                            'email' => $email
                        ]);
                    } else {
                        error_log("[bulk-assign] Failed to create Supabase user for: $email");
                        log_audit('account_error', 'members', $member_id, null, ['email' => $email]);
                    }
                } else {
                    // User exists — link only
                    $userId = $existingUser['id'];
                    $supabase->from('members')
                        ->update(['user_id' => $userId])
                        ->eq('id', $member_id)
                        ->execute();
                    log_audit('account_linked', 'members', $member_id, null, ['email' => $email]);
                }
            }

            // Update import row
            $supabase->from('membership_directory_imports')
                ->update([
                    'assigned_membership_id' => $membership_id,
                    'assigned_at' => date('Y-m-d H:i:s'),
                    'assigned_by_user_id' => $assigned_by_user_id,
                    'member_id' => $member_id,
                    'is_paid' => $is_paid,
                ])
                ->eq('id', $row_id)
                ->execute();

            $assigned_ids[] = [
                'row_id' => $row_id,
                'membership_id' => $membership_id,
                'member_id' => $member_id
            ];

        } catch (Exception $e) {
            $failed_rows[] = "Row $row_id: " . $e->getMessage();
        }
    }

    if (!empty($failed_rows) && count($failed_rows) === count($rows)) {
        // All failed
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'All rows failed to process',
            'errors' => $failed_rows
        ]);
        exit;
    }

    // Update application directory_validated flag
    $supabase->from('pending_affiliations')
        ->update([
            'directory_validated' => true,
            'directory_validated_at' => date('Y-m-d H:i:s'),
            'directory_validated_by' => $assigned_by_user_id,
        ])
        ->eq('id', $application_id)
        ->execute();

    // Send emails AFTER processing — outside any transaction
    foreach ($emailQueue as $item) {
        try {
            if ($item['type'] === 'new_member') {
                $emailService->sendMemberCredentials(
                    $item['email'],
                    $item['full_name'],
                    $item['membership_id'],
                    $item['password']
                );
            } elseif ($item['type'] === 'renewal') {
                $emailService->sendRenewalConfirmation(
                    $item['email'],
                    $item['full_name'],
                    $item['membership_id']
                );
            }
        } catch (Exception $e) {
            error_log('[bulk-assign] Email failed for ' . $item['email'] . ': ' . $e->getMessage());
            // Non-fatal — member record is already saved
        }
    }

    // Log audit
    log_audit('member_bulk_assign_ids', 'pending_affiliations', $application_id, null, [
        'assigned_count' => count($assigned_ids),
        'failed_count' => count($failed_rows),
        'emails_queued' => count($emailQueue)
    ]);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'assigned_count' => count($assigned_ids),
        'failed_count' => count($failed_rows),
        'assigned_ids' => $assigned_ids,
        'failed_rows' => !empty($failed_rows) ? $failed_rows : null,
        'message' => count($assigned_ids) . ' membership IDs assigned successfully'
    ]);

} catch (Exception $e) {
    error_log("Bulk assign membership IDs error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Transaction failed. No changes were made.'
    ]);
    exit;
}

function generate_membership_id($supabase, $year) {
    $seqResponse = $supabase->from('membership_id_sequences')
        ->select('*')
        ->eq('year', $year)
        ->limit(1)
        ->execute();

    $seq = $seqResponse->data[0] ?? null;

    if ($seq) {
        $next_num = $seq['last_sequence_number'] + 1;
    } else {
        $next_num = 1;
        $supabase->from('membership_id_sequences')->insert([[
            'year' => $year,
            'last_sequence_number' => 0,
        ]])->execute();
    }

    $supabase->from('membership_id_sequences')
        ->update([
            'last_sequence_number' => $next_num,
            'updated_at' => date('Y-m-d H:i:s'),
        ])
        ->eq('year', $year)
        ->execute();

    return sprintf('IECEP-LSC-%d-%04d', $year, $next_num);
}

function log_audit($action, $table_name, $record_id, $old_data = null, $new_data = null) {
    if (function_exists('log_audit')) {
        call_user_func('log_audit', $action, $table_name, $record_id, $old_data, $new_data);
    }
}
?>
