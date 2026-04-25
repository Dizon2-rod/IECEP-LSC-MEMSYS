<?php
require_once __DIR__ . '/../../auth_check.php';
require_role(['eb_pro_1', 'committee_creatives']);

require_once __DIR__ . '/../../../src/lib/SupabaseClient.php';
$config = require __DIR__ . '/../../../src/config/supabase.php';
$supabase = new SupabaseClient($config['url'], $config['anon_key']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $graphics = $_POST['graphics'] ?? [];
    
    try {
        // Delete all existing graphics
        $existing = $supabase->select('creatives_graphics');
        foreach ($existing as $item) {
            $supabase->delete('creatives_graphics', $item['id']);
        }
        
        // Insert all graphics
        foreach ($graphics as $graphic) {
            $data = [
                'name' => $graphic['name'],
                'image' => $graphic['image'],
                'date' => $graphic['date']
            ];
            
            if (!empty($graphic['id'])) {
                // Update existing
                $supabase->update('creatives_graphics', $data, $graphic['id']);
            } else {
                // Insert new
                $supabase->insert('creatives_graphics', $data);
            }
        }
        
        header('Location: graphics.php?success=1');
    } catch (Exception $e) {
        header('Location: graphics.php?error=1');
    }
    exit;
}
