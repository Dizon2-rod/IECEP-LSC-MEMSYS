<?php
/**
 * Migration Script: Fix Approved Affiliations Without User Profiles
 * 
 * This script creates missing user_profiles records for approved affiliations
 * that have users in the users table but no corresponding user_profiles entry.
 * 
 * Run this once to fix existing data.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../src/lib/SupabaseClient.php';

echo "=== Affiliation User Profiles Migration ===\n\n";

try {
    $config = require __DIR__ . '/../includes/supabase.php';
    $supabase = new SupabaseClient($config['url'], $config['service_role_key']);
    
    echo "1. Fetching approved affiliations...\n";
    $affiliations = $supabase->select('pending_affiliations', ['status' => 'eq.approved']);
    
    if (empty($affiliations)) {
        echo "   No approved affiliations found.\n";
        exit(0);
    }
    
    echo "   Found " . count($affiliations) . " approved affiliation(s).\n\n";
    
    $fixed = 0;
    $skipped = 0;
    $errors = 0;
    
    foreach ($affiliations as $affiliation) {
        $email = $affiliation['email'];
        $institutionName = $affiliation['institution_name'];
        
        echo "2. Processing: $institutionName ($email)\n";
        
        // Find user by email
        $users = $supabase->select('users', ['email' => 'eq.' . $email]);
        
        if (empty($users)) {
            echo "   ⚠️  No user found for this email. Skipping.\n\n";
            $skipped++;
            continue;
        }
        
        $user = $users[0];
        $userId = $user['id'];
        
        echo "   User ID: $userId\n";
        
        // Check if user_profiles exists
        $profiles = $supabase->select('user_profiles', ['user_id' => 'eq.' . $userId]);
        
        if (!empty($profiles)) {
            echo "   ✓ User profile already exists. Skipping.\n\n";
            $skipped++;
            continue;
        }
        
        // Create user_profiles record
        echo "   Creating user_profiles record...\n";
        
        $profileData = [
            'user_id'     => $userId,
            'role'        => 'school_officer',
            'full_name'   => $affiliation['contact_person'] ?? $institutionName,
            'school_name' => $institutionName,
            'contact_phone' => $affiliation['contact_phone'] ?? null,
            'address'     => $affiliation['address'] ?? null,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s')
        ];
        
        $result = $supabase->insert('user_profiles', $profileData);
        
        if ($result) {
            echo "   ✅ User profile created successfully!\n\n";
            $fixed++;
        } else {
            echo "   ❌ Failed to create user profile.\n\n";
            $errors++;
        }
    }
    
    echo "\n=== Migration Complete ===\n";
    echo "Fixed: $fixed\n";
    echo "Skipped: $skipped\n";
    echo "Errors: $errors\n";
    echo "Total: " . count($affiliations) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
