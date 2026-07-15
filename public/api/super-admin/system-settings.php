<?php
/**
 * System Settings API
 * Manage global system configuration
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/middleware/auth.php';

header('Content-Type: application/json');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    $db = $GLOBALS['supabaseClient'] ?? null;
    if (!$db) {
        throw new Exception('Database connection not available');
    }
    
    // Check if user is super admin
    $userRole = $_SESSION['user']['role'] ?? '';
    if (!in_array($userRole, ['super_admin', 'admin'])) {
        throw new Exception('Unauthorized access');
    }
    
    switch ($method) {
        case 'GET':
            if ($action === 'all') {
                // Get all system settings
                $settings = $db->select('system_settings', [
                    'order' => 'key.asc'
                ]);
                
                // Group by category
                $groupedSettings = [];
                foreach ($settings as $setting) {
                    $key = $setting['key'];
                    $category = getCategoryForKey($key);
                    
                    if (!isset($groupedSettings[$category])) {
                        $groupedSettings[$category] = [];
                    }
                    
                    $groupedSettings[$category][] = [
                        'key' => $key,
                        'value' => $setting['value'],
                        'description' => $setting['description']
                    ];
                }
                
                echo json_encode([
                    'success' => true,
                    'settings' => $groupedSettings
                ]);
                
            } elseif ($action === 'fee-brackets') {
                // Get fee brackets
                $feeBrackets = $db->select('fee_brackets', [
                    'is_active' => 'eq.true',
                    'order' => 'min_members.asc'
                ]);
                
                echo json_encode([
                    'success' => true,
                    'fee_brackets' => $feeBrackets
                ]);
                
            } else {
                throw new Exception('Invalid action');
            }
            break;
            
        case 'POST':
            if ($action === 'update') {
                // Update system setting
                $input = json_decode(file_get_contents('php://input'), true);
                
                $key = $input['key'] ?? '';
                $value = $input['value'] ?? '';
                
                if (empty($key)) {
                    throw new Exception('key parameter is required');
                }
                
                // Check if setting exists
                $existing = $db->select('system_settings', [
                    'key' => 'eq.' . $key
                ]);
                
                if (!empty($existing)) {
                    // Update existing
                    $db->update('system_settings', [
                        'value' => $value
                    ])->eq('key', $key)->update();
                } else {
                    // Create new
                    $db->insert('system_settings', [
                        'key' => $key,
                        'value' => $value,
                        'description' => $input['description'] ?? ''
                    ]);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Setting updated successfully'
                ]);
                
            } elseif ($action === 'update-fee-bracket') {
                // Update fee bracket
                $input = json_decode(file_get_contents('php://input'), true);
                
                $bracketId = $input['id'] ?? '';
                $minMembers = $input['min_members'] ?? 0;
                $maxMembers = $input['max_members'] ?? null;
                $affiliationFee = $input['affiliation_fee'] ?? 0;
                $perMemberFee = $input['per_member_fee'] ?? 0;
                $annualFee = $input['annual_fee'] ?? 0;
                
                if (empty($bracketId)) {
                    throw new Exception('id parameter is required');
                }
                
                $db->update('fee_brackets', [
                    'min_members' => $minMembers,
                    'max_members' => $maxMembers,
                    'affiliation_fee' => $affiliationFee,
                    'per_member_fee' => $perMemberFee,
                    'annual_fee' => $annualFee
                ])->eq('id', $bracketId)->update();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Fee bracket updated successfully'
                ]);
                
            } elseif ($action === 'create-fee-bracket') {
                // Create new fee bracket
                $input = json_decode(file_get_contents('php://input'), true);
                
                $bracketName = $input['bracket_name'] ?? '';
                $minMembers = $input['min_members'] ?? 0;
                $maxMembers = $input['max_members'] ?? null;
                $affiliationFee = $input['affiliation_fee'] ?? 0;
                $perMemberFee = $input['per_member_fee'] ?? 0;
                $annualFee = $input['annual_fee'] ?? 0;
                
                if (empty($bracketName) || empty($minMembers)) {
                    throw new Exception('bracket_name and min_members are required');
                }
                
                $db->insert('fee_brackets', [
                    'id' => generateUUID(),
                    'bracket_name' => $bracketName,
                    'min_members' => $minMembers,
                    'max_members' => $maxMembers,
                    'affiliation_fee' => $affiliationFee,
                    'per_member_fee' => $perMemberFee,
                    'annual_fee' => $annualFee,
                    'is_active' => true,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Fee bracket created successfully'
                ]);
                
            } else {
                throw new Exception('Invalid action');
            }
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function getCategoryForKey($key) {
    $categories = [
        'academic_year_start' => 'academic',
        'academic_year_end' => 'academic',
        'compliance_participation_threshold' => 'compliance',
        'compliance_hosted_events_required' => 'compliance',
        'membership_id_prefix' => 'membership',
        'membership_renewal_days' => 'membership',
        'operational_fee' => 'financial',
        'default_currency' => 'financial',
        'email_notifications_enabled' => 'notifications',
        'push_notifications_enabled' => 'notifications',
        'maintenance_mode' => 'system'
    ];
    
    return $categories[$key] ?? 'general';
}

function generateUUID() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
