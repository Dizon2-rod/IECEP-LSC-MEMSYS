<?php
require_once __DIR__ . '/../../auth_check.php';
require_role(['eb_pro_1', 'committee_creatives']);

require_once __DIR__ . '/../../../src/lib/SupabaseClient.php';
$config = require __DIR__ . '/../../../src/config/supabase.php';
$supabase = new SupabaseClient($config['url'], $config['anon_key']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $publications = $_POST['publications'] ?? [];
    
    try {
        // Delete all existing publications
        $existing = $supabase->select('creatives_publications');
        foreach ($existing as $item) {
            $supabase->delete('creatives_publications', $item['id']);
        }
        
        // Insert all publications
        foreach ($publications as $publication) {
            $data = [
                'title' => $publication['title'],
                'file' => $publication['file'],
                'size' => $publication['size'],
                'date' => $publication['date']
            ];
            
            if (!empty($publication['id'])) {
                // Update existing
                $supabase->update('creatives_publications', $data, $publication['id']);
            } else {
                // Insert new
                $supabase->insert('creatives_publications', $data);
            }
        }
        
        header('Location: publications.php?success=1');
    } catch (Exception $e) {
        header('Location: publications.php?error=1');
    }
    exit;
}
