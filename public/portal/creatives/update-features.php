<?php
// IECEP-LSC MEMSYS - Features Update Script
// Creatives & Publication Committee can use this to update features content

require_once __DIR__ . '/../auth_check.php';
require_role(['eb_pro_1', 'committee_creatives']);

require_once __DIR__ . '/../../../src/lib/SupabaseClient.php';
$config = require __DIR__ . '/../../../src/config/supabase.php';
$supabase = new SupabaseClient($config['url'], $config['anon_key']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $features = $_POST['features'] ?? [];
    $uploadDir = __DIR__ . '/../../../public/uploads/features/';
    
    // Create upload directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    try {
        // Get existing features to track which ones to delete
        $existing = $supabase->select('creatives_features');
        $existingIds = array_column($existing, 'id');
        $submittedIds = [];
        
        // Update or insert each feature
        foreach ($features as $index => $feature) {
            // Handle image upload
            $imagePath = $feature['existing_image'] ?? '';
            if (isset($_FILES['features']['name'][$index]['image']) && 
                $_FILES['features']['error'][$index]['image'] === UPLOAD_ERR_OK) {
                
                $file = $_FILES['features']['tmp_name'][$index]['image'];
                $fileName = time() . '_' . basename($_FILES['features']['name'][$index]['image']);
                $targetPath = $uploadDir . $fileName;
                
                // Validate file type
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $fileType = $_FILES['features']['type'][$index]['image'];
                
                if (in_array($fileType, $allowedTypes)) {
                    if (move_uploaded_file($file, $targetPath)) {
                        $imagePath = '/uploads/features/' . $fileName;
                    }
                }
            }
            
            $data = [
                'title' => $feature['title'],
                'description' => $feature['description'],
                'image' => $imagePath,
                'link' => $feature['link'] ?? '#'
            ];
            
            if (!empty($feature['id'])) {
                // Update existing
                $supabase->update('creatives_features', $data, $feature['id']);
                $submittedIds[] = $feature['id'];
            } else {
                // Insert new
                $supabase->insert('creatives_features', $data);
            }
        }
        
        // Delete features that were removed (exist in DB but not in submitted form)
        foreach ($existingIds as $existingId) {
            if (!in_array($existingId, $submittedIds)) {
                $supabase->delete('creatives_features', $existingId);
            }
        }
        
        header('Location: features-manager.php?success=1');
    } catch (Exception $e) {
        header('Location: features-manager.php?error=1');
    }
    exit;
} else {
    header('Location: features-manager.php');
    exit;
}
