<?php
require_once __DIR__ . '/../bootstrap.php';
/**
 * Audit Logging Helper
 * 
 * MANDATORY AUDIT ACTIONS - All developers must log these actions:
 * 
 * Authentication & Authorization:
 * - login, logout, login_failed
 * - password_change, password_reset_request, password_reset_complete
 * - role_change, permission_change
 * 
 * User Management:
 * - user_create, user_update, user_delete, user_suspend, user_reactivate
 * - profile_update
 * 
 * Affiliation & Membership:
 * - affiliation_submit, affiliation_approve, affiliation_reject, affiliation_revision_request, affiliation_revision_submit
 * - member_create, member_update, member_delete, member_suspend, member_reactivate
 * - member_batch_import, member_batch_approve, member_batch_reject
 * - membership_renewal, membership_expiry
 * 
 * Financial:
 * - payment_create, payment_confirm, payment_void
 * - transaction_create, transaction_update, transaction_delete
 * - receipt_generate, receipt_void
 * 
 * Events:
 * - event_create, event_update, event_delete, event_publish
 * - event_registration, event_checkin, event_cancel
 * 
 * Compliance:
 * - compliance_score_update, compliance_violation, compliance_reminder_sent
 * 
 * Content Management:
 * - announcement_create, announcement_update, announcement_delete, announcement_publish
 * - certificate_generate, certificate_revoke
 * 
 * System:
 * - settings_change, backup_create, data_export, data_import
 * - any record deletion (delete operations on any table)
 */

require_once __DIR__ . '/../src/lib/supabase.php';

function log_audit($action, $table_name, $record_id, $old_data = null, $new_data = null) {
    try {
        $supabase = new \App\Lib\Supabase();
        $supabase->from('audit_logs')->insert([
            'action' => $action,
            'table_name' => $table_name,
            'record_id' => $record_id,
            'old_data' => $old_data ? json_encode($old_data) : null,
            'new_data' => $new_data ? json_encode($new_data) : null,
            'performed_by' => $_SESSION['user']['id'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Audit log failed: " . $e->getMessage());
    }
}
