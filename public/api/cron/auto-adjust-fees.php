<?php
/**
 * Automated Fee Adjustment Cron Job
 * Recalculates fees when member count changes
 */

require_once __DIR__ . '/../../includes/config.php';

$db = $GLOBALS['supabaseClient'] ?? null;
if (!$db) {
    error_log('Database connection not available');
    exit(1);
}

try {
    // Get all active institutions
    $institutions = $db->select('institutions', [
        'status' => 'eq.active'
    ]);
    
    $adjustmentsCount = 0;
    
    foreach ($institutions as $institution) {
        $instId = $institution['id'];
        $currentMemberCount = $institution['membership_count'] ?? 0;
        
        // Get current fee bracket
        $currentBracket = $db->select('fee_brackets', [
            'is_active' => 'eq.true',
            'lte' => 'min_members.' . $currentMemberCount,
            'order' => 'min_members.desc',
            'limit' => 1
        ]);
        
        if (empty($currentBracket)) {
            continue;
        }
        
        $currentBracketId = $currentBracket[0]['id'];
        
        // Check if institution's fee bracket has changed
        $lastAdjustment = $db->select('fee_adjustments', [
            'institution_id' => 'eq.' . $instId,
            'order' => 'adjusted_at.desc',
            'limit' => 1
        ]);
        
        $needsAdjustment = false;
        
        if (empty($lastAdjustment)) {
            $needsAdjustment = true;
        } else {
            $lastBracketId = $lastAdjustment[0]['new_bracket_id'];
            if ($lastBracketId !== $currentBracketId) {
                $needsAdjustment = true;
            }
        }
        
        if ($needsAdjustment) {
            // Record the adjustment
            $db->insert('fee_adjustments', [
                'id' => generateUUID(),
                'institution_id' => $instId,
                'old_bracket_id' => $lastAdjustment[0]['new_bracket_id'] ?? null,
                'new_bracket_id' => $currentBracketId,
                'member_count' => $currentMemberCount,
                'adjusted_at' => date('Y-m-d H:i:s'),
                'auto_adjusted' => true
            ]);
            
            // Record on blockchain
            if (isset($GLOBALS['blockchain'])) {
                $GLOBALS['blockchain']->record('fee_adjustment', $instId, [
                    'old_bracket' => $lastAdjustment[0]['new_bracket_id'] ?? null,
                    'new_bracket' => $currentBracketId,
                    'member_count' => $currentMemberCount,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            }
            
            // Notify treasurer
            $treasurers = $db->select('user_profiles', [
                'role' => 'eq.treasurer'
            ]);
            
            foreach ($treasurers as $treasurer) {
                $db->insert('notifications', [
                    'user_id' => $treasurer['id'],
                    'title' => 'Automatic Fee Adjustment',
                    'message' => "Fee bracket automatically adjusted for {$institution['name']} due to member count change.",
                    'type' => 'info',
                    'action_url' => "/portal/treasurer/fee-brackets.php",
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            $adjustmentsCount++;
        }
    }
    
    // Log execution
    $db->insert('cron_logs', [
        'id' => generateUUID(),
        'job_id' => 'auto_adjust_fees',
        'executed_at' => date('Y-m-d H:i:s'),
        'duration' => 0,
        'success' => true,
        'output' => "Processed " . count($institutions) . " institutions. Made {$adjustmentsCount} adjustments.",
        'triggered_by' => 'system'
    ]);
    
    echo "Fee adjustment cron completed. Processed " . count($institutions) . " institutions. Made {$adjustmentsCount} adjustments.\n";
    
} catch (Exception $e) {
    error_log('Fee adjustment cron error: ' . $e->getMessage());
    
    // Log failure
    $db->insert('cron_logs', [
        'id' => generateUUID(),
        'job_id' => 'auto_adjust_fees',
        'executed_at' => date('Y-m-d H:i:s'),
        'duration' => 0,
        'success' => false,
        'output' => $e->getMessage(),
        'triggered_by' => 'system'
    ]);
    
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
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
