<?php
require_once __DIR__ . '/../../auth_check.php';
require_role(['eb_pro_1', 'committee_creatives']);

// Load path configuration
require_once __DIR__ . '/../../../includes/paths.php';

require_once __DIR__ . '/../../../src/lib/SupabaseClient.php';
$config = require __DIR__ . '/../../../includes/supabase.php';
$supabase = new SupabaseClient($config['url'], $config['anon_key']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $announcements = $_POST['announcements'] ?? [];
    
    try {
        // Delete all existing announcements
        $existing = $supabase->select('creatives_announcements');
        foreach ($existing as $item) {
            $supabase->delete('creatives_announcements', $item['id']);
        }
        
        // Insert all announcements
        foreach ($announcements as $announcement) {
            $data = [
                'title' => $announcement['title'],
                'content' => $announcement['content'],
                'date' => $announcement['date'],
                'author' => $announcement['author'] ?? 'Creatives Committee',
                'status' => $announcement['status'] ?? 'published'
            ];
            
            if (!empty($announcement['id'])) {
                // Update existing
                $supabase->update('creatives_announcements', $data, $announcement['id']);
            } else {
                // Insert new
                $supabase->insert('creatives_announcements', $data);
            }
        }
        
        header('Location: ' . PORTAL_URL . '/creatives/announcements.php?success=1');
    } catch (Exception $e) {
        header('Location: ' . PORTAL_URL . '/creatives/announcements.php?error=1');
    }
    exit;
}
