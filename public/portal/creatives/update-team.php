<?php
require_once __DIR__ . '/../bootstrap.php';
$current_page = basename(__FILE__, '.php');
require_once __DIR__ . '/../../auth_check.php';
require_role(['eb_pro_1']);

// Load path configuration
require_once __DIR__ . '/../../../includes/paths.php';

require_once __DIR__ . '/../../../src/lib/SupabaseClient.php';
$config = require __DIR__ . '/../../../includes/supabase.php';
$supabase = new SupabaseClient($config['url'], $config['anon_key']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $team = $_POST['team'] ?? [];
    
    try {
        // Delete all existing team members
        $existing = $supabase->select('creatives_team');
        foreach ($existing as $item) {
            $supabase->delete('creatives_team', $item['id']);
        }
        
        // Insert all team members
        foreach ($team as $member) {
            $data = [
                'name' => $member['name'],
                'role' => $member['role'],
                'email' => $member['email']
            ];
            
            if (!empty($member['id'])) {
                // Update existing
                $supabase->update('creatives_team', $data, $member['id']);
            } else {
                // Insert new
                $supabase->insert('creatives_team', $data);
            }
        }
        
        header('Location: ' . PORTAL_URL . '/creatives/team.php?success=1');
    } catch (Exception $e) {
        header('Location: ' . PORTAL_URL . '/creatives/team.php?error=1');
    }
    exit;
}

