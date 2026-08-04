<?php
require_once __DIR__ . '/bootstrap.php';
/**
 * Assign Single Membership ID
 * 
 * POST endpoint for assigning a membership ID to a single imported member
 * Handles payment status validation and member type matching
 * Auto-creates Supabase user accounts and sends credentials
 */

require_once __DIR__ . '/../../../includes/paths.php';
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/supabase-admin.php';
require_once __DIR__ . '/../../../src/lib/EmailService.php';
require_once __DIR__ . '/../../portal/auth_check.php';

// Require admin or registration role
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
    
    $row_id = $_POST['row_id'] ?? null;
    $is_paid = isset($_POST['is_paid']) ? (bool)$_POST['is_paid'] : false;
    $member_type = $_POST['member_type'] ?? null;
    $assigned_by_user_id = $_SESSION['user']['id'] ?? null;
    
    // Validate inputs
    if (!$row_id || !$member_type || !in_array($member_type, ['new', 'old'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid input parameters']);
        exit;
    }

    if (!$is_paid) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payment status must be marked as Paid']);
        exit;
    }

    // Fetch import row
    $importResponse = $supabase->from('membership_directory_imports')
        ->select('*')
        ->eq('id', $row_id)
        ->execute();

    $import_row = $importResponse->data[0] ?? null;

    if (!$import_row) {
        throw new Exception('Import row not found');
    }

    $batch_id = $import_row['batch_id'];
    $email = $import_row['email'];
    $name = $import_row['name'];
    $birthday = $import_row['birthday'];
    $year = (int)date('Y');
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
            throw new Exception('Old member not found in system. Check email or name/birthday combination.');
        }

        $member_id = $existing['id'];
        $membership_id = $existing['membership_id'];

        // Update existing member payment status
        $supabase->from('members')
            ->update([
                'payment_status' => $is_paid,
                'validated_at' => date('Y-m-d H:i:s'),
                'validated_by' => $assigned_by_user_id,
            ])
            ->eq('id', $member_id)
            ->execute();

        // Send renewal email
        try {
            $emailService->sendRenewalConfirmation($email, $name, $membership_id);
        } catch (Exception $e) {
            error_log("[assign-id] Renewal email failed for $email: " . $e->getMessage());
        }

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
            'upload_batch_id' => $batch_id,
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
                    'force_password_change' => true,
                ]])->execute();

                // Link member record
                $supabase->from('members')
                    ->update(['user_id' => $userId])
                    ->eq('id', $member_id)
                    ->execute();

                // Send credential email
                try {
                    $emailService->sendMemberCredentials($email, $name, $membership_id, $password);
                } catch (Exception $e) {
                    error_log("[assign-id] Credential email failed for $email: " . $e->getMessage());
                }

                log_audit('account_created', 'members', $member_id, null, [
                    'membership_id' => $membership_id,
                    'email' => $email
                ]);
            } else {
                error_log("[assign-id] Failed to create Supabase user for: $email");
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

    // Update import row with assignment
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

    // Increment validated_rows in batch
    $supabase->from('upload_batches')
        ->update([
            'validated_rows' => "validated_rows + 1",
        ])
        ->eq('id', $batch_id)
        ->execute();

    // Log audit
    log_audit('member_assign_id', 'members', $member_id, null, [
        'membership_id' => $membership_id,
        'member_type' => $member_type,
        'payment_status' => $is_paid
    ]);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'membership_id' => $membership_id,
        'member_id' => $member_id,
        'message' => "Membership ID $membership_id assigned successfully"
    ]);

} catch (Exception $e) {
    error_log("Assign membership ID error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage() ?: 'Failed to assign membership ID'
    ]);
    exit;
}

function generate_membership_id($supabase, $year) {
    // Get or create sequence for year
    $seqResponse = $supabase->from('membership_id_sequences')
        ->select('*')
        ->eq('year', $year)
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

    // Update sequence
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
