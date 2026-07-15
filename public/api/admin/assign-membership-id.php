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
    $db = getDbConnection();
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

    // Start transaction
    $db->beginTransaction();

    try {
        // Fetch import row with lock
        $stmt = $db->prepare("
            SELECT * FROM membership_directory_imports 
            WHERE id = ? 
            FOR UPDATE
        ");
        $stmt->execute([$row_id]);
        $import_row = $stmt->fetch(PDO::FETCH_ASSOC);

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
            $stmt = $db->prepare("
                SELECT id, membership_id FROM members 
                WHERE email = ? OR (LOWER(full_name) = LOWER(?) AND birthday = ?)
                LIMIT 1
            ");
            $stmt->execute([$email, $name, $birthday]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                throw new Exception('Old member not found in system. Check email or name/birthday combination.');
            }

            $member_id = $existing['id'];
            $membership_id = $existing['membership_id'];

            // Update existing member payment status
            $stmt = $db->prepare("
                UPDATE members 
                SET payment_status = ?, 
                    validated_at = NOW(),
                    validated_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$is_paid, $assigned_by_user_id, $member_id]);

            // Send renewal email
            try {
                $emailService->sendRenewalConfirmation($email, $name, $membership_id);
            } catch (Exception $e) {
                error_log("[assign-id] Renewal email failed for $email: " . $e->getMessage());
            }

        } else { // 'new'
            // Generate membership ID
            $membership_id = generate_membership_id($db, $year);

            // Create new member
            $stmt = $db->prepare("
                INSERT INTO members (
                    full_name, email, birthday, address, phone,
                    membership_id, member_type, payment_status, year_level,
                    upload_batch_id, validated_at, validated_by,
                    picture_url, signature_url, is_new
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $import_row['name'],
                $import_row['email'],
                $import_row['birthday'],
                $import_row['address'],
                $import_row['phone'],
                $membership_id,
                'new',
                $is_paid,
                $import_row['sheet_name'],
                $batch_id,
                $assigned_by_user_id,
                $import_row['picture_url'],
                $import_row['signature_url'],
                true
            ]);

            $member_id = $db->lastInsertId();

            // Check if Supabase user exists
            $existing = checkSupabaseUserByEmail($email);

            if (!$existing) {
                // Create Supabase user
                $password = bin2hex(random_bytes(6)); // 12-char hex password
                $newUser = createSupabaseUser($email, $password, $name);

                if ($newUser && isset($newUser['id'])) {
                    $userId = $newUser['id'];

                    // Insert user_profiles
                    $stmt = $db->prepare("
                        INSERT INTO user_profiles (user_id, role, full_name, force_password_change)
                        VALUES (?, 'member', ?, true)
                    ");
                    $stmt->execute([$userId, $name]);

                    // Link member record
                    $stmt = $db->prepare("
                        UPDATE members SET user_id = ? WHERE id = ?
                    ");
                    $stmt->execute([$userId, $member_id]);

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
                $userId = $existing['id'];
                $stmt = $db->prepare("UPDATE members SET user_id = ? WHERE id = ?");
                $stmt->execute([$userId, $member_id]);
                log_audit('account_linked', 'members', $member_id, null, ['email' => $email]);
            }
        }

        // Update import row with assignment
        $stmt = $db->prepare("
            UPDATE membership_directory_imports 
            SET assigned_membership_id = ?,
                assigned_at = NOW(),
                assigned_by_user_id = ?,
                member_id = ?,
                is_paid = ?
            WHERE id = ?
        ");
        $stmt->execute([$membership_id, $assigned_by_user_id, $member_id, $is_paid, $row_id]);

        // Increment validated_rows in batch
        $stmt = $db->prepare("
            UPDATE upload_batches 
            SET validated_rows = validated_rows + 1
            WHERE id = ?
        ");
        $stmt->execute([$batch_id]);

        // Commit transaction
        $db->commit();

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
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Assign membership ID error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage() ?: 'Failed to assign membership ID'
    ]);
    exit;
}

function generate_membership_id($db, $year) {
    // Get or create sequence for year
    $stmt = $db->prepare("
        SELECT last_sequence_number FROM membership_id_sequences 
        WHERE year = ? 
        FOR UPDATE
    ");
    $stmt->execute([$year]);
    $seq = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($seq) {
        $next_num = $seq['last_sequence_number'] + 1;
    } else {
        $next_num = 1;
        $stmt = $db->prepare("
            INSERT INTO membership_id_sequences (year, last_sequence_number) 
            VALUES (?, 0)
        ");
        $stmt->execute([$year]);
    }

    // Update sequence
    $stmt = $db->prepare("
        UPDATE membership_id_sequences 
        SET last_sequence_number = ?, updated_at = NOW()
        WHERE year = ?
    ");
    $stmt->execute([$next_num, $year]);

    return sprintf('IECEP-LSC-%d-%04d', $year, $next_num);
}

function log_audit($action, $table_name, $record_id, $old_data = null, $new_data = null) {
    if (function_exists('log_audit')) {
        call_user_func('log_audit', $action, $table_name, $record_id, $old_data, $new_data);
    }
}

function getDbConnection() {
    static $db = null;
    if ($db === null) {
        $db = new PDO(
            'pgsql:host=' . env('DB_HOST') . ';port=' . env('DB_PORT', 5432) . ';dbname=' . env('DB_NAME'),
            env('DB_USER'),
            env('DB_PASSWORD')
        );
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $db;
}
?>
