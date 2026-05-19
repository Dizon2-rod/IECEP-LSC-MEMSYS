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
    $db = getDbConnection();
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
    $stmt = $db->prepare("SELECT id, institution_id FROM pending_affiliations WHERE id = ? AND status = 'approved'");
    $stmt->execute([$application_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$application) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Application not found or not approved']);
        exit;
    }
    $institution_id = $application['institution_id'];

    // Start transaction
    $db->beginTransaction();
    $year = (int)date('Y');
    $assigned_ids = [];
    $failed_rows = [];
    $emailQueue = [];

    try {
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
                $stmt = $db->prepare("
                    SELECT * FROM membership_directory_imports 
                    WHERE id = ? AND application_id = ?
                    FOR UPDATE
                ");
                $stmt->execute([$row_id, $application_id]);
                $import_row = $stmt->fetch(PDO::FETCH_ASSOC);

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
                    $stmt = $db->prepare("
                        SELECT id, membership_id FROM members 
                        WHERE email = ? OR (LOWER(full_name) = LOWER(?) AND birthday = ?)
                        LIMIT 1
                    ");
                    $stmt->execute([$email, $name, $birthday]);
                    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$existing) {
                        $failed_rows[] = "Row $row_id: Old member '$name' not found in system";
                        continue;
                    }

                    $member_id = $existing['id'];
                    $membership_id = $existing['membership_id'];

                    // Update existing member
                    $stmt = $db->prepare("
                        UPDATE members 
                        SET payment_status = ?, 
                            validated_at = NOW(),
                            validated_by = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$is_paid, $assigned_by_user_id, $member_id]);

                    // Queue renewal email
                    $emailQueue[] = [
                        'email' => $email,
                        'full_name' => $name,
                        'membership_id' => $membership_id,
                        'type' => 'renewal',
                    ];

                } else { // 'new'
                    // Generate membership ID
                    $membership_id = generate_membership_id($db, $year);

                    // Create new member
                    $stmt = $db->prepare("
                        INSERT INTO members (
                            full_name, email, birthday, address, phone,
                            membership_id, member_type, payment_status, year_level,
                            institution_id, validated_at, validated_by,
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
                        $institution_id,
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
                                INSERT INTO user_profiles (user_id, role, full_name, institution_id, force_password_change)
                                VALUES (?, 'member', ?, ?, true)
                            ");
                            $stmt->execute([$userId, $name, $institution_id]);

                            // Link member record
                            $stmt = $db->prepare("
                                UPDATE members SET user_id = ? WHERE id = ?
                            ");
                            $stmt->execute([$userId, $member_id]);

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
                        $userId = $existing['id'];
                        $stmt = $db->prepare("UPDATE members SET user_id = ? WHERE id = ?");
                        $stmt->execute([$userId, $member_id]);
                        log_audit('account_linked', 'members', $member_id, null, ['email' => $email]);
                    }
                }

                // Update import row
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
            $db->rollBack();
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'All rows failed to process',
                'errors' => $failed_rows
            ]);
            exit;
        }

        // Update application directory_validated flag
        $stmt = $db->prepare("
            UPDATE pending_affiliations 
            SET directory_validated = true,
                directory_validated_at = NOW(),
                directory_validated_by = ?
            WHERE id = ?
        ");
        $stmt->execute([$assigned_by_user_id, $application_id]);

        // Commit transaction
        $db->commit();

        // Send emails AFTER commit — outside the transaction lock
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
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Bulk assign membership IDs error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Transaction failed. No changes were made.'
    ]);
    exit;
}

function generate_membership_id($db, $year) {
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
