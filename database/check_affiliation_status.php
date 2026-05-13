<?php
/**
 * Verification Script: Check Affiliation Approval Status
 * 
 * This script checks the current state of approved affiliations
 * and identifies any missing user_profiles records.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../src/lib/SupabaseClient.php';

echo "=== Affiliation Approval Status Check ===\n\n";

try {
    $config = require __DIR__ . '/../includes/supabase.php';
    $supabase = new SupabaseClient($config['url'], $config['service_role_key']);
    
    // Get all approved affiliations
    $affiliations = $supabase->select('pending_affiliations', ['status' => 'eq.approved']);
    
    if (empty($affiliations)) {
        echo "✓ No approved affiliations found.\n";
        exit(0);
    }
    
    echo "Found " . count($affiliations) . " approved affiliation(s).\n\n";
    
    $issues = [];
    $ok = [];
    
    foreach ($affiliations as $affiliation) {
        $email = $affiliation['email'];
        $institutionName = $affiliation['institution_name'];
        
        // Check users table
        $users = $supabase->select('users', ['email' => 'eq.' . $email]);
        
        if (empty($users)) {
            $issues[] = [
                'email' => $email,
                'institution' => $institutionName,
                'issue' => 'No user record found'
            ];
            continue;
        }
        
        $userId = $users[0]['id'];
        
        // Check user_profiles table
        $profiles = $supabase->select('user_profiles', ['user_id' => 'eq.' . $userId]);
        
        if (empty($profiles)) {
            $issues[] = [
                'email' => $email,
                'institution' => $institutionName,
                'user_id' => $userId,
                'issue' => 'Missing user_profiles record'
            ];
        } else {
            $ok[] = [
                'email' => $email,
                'institution' => $institutionName,
                'user_id' => $userId
            ];
        }
    }
    
    // Display results
    if (!empty($ok)) {
        echo "✅ WORKING ACCOUNTS (" . count($ok) . "):\n";
        echo str_repeat("-", 80) . "\n";
        foreach ($ok as $item) {
            echo "  • {$item['institution']} ({$item['email']})\n";
            echo "    User ID: {$item['user_id']}\n";
            echo "    Status: Can login successfully\n\n";
        }
    }
    
    if (!empty($issues)) {
        echo "\n❌ ACCOUNTS WITH ISSUES (" . count($issues) . "):\n";
        echo str_repeat("-", 80) . "\n";
        foreach ($issues as $item) {
            echo "  • {$item['institution']} ({$item['email']})\n";
            if (isset($item['user_id'])) {
                echo "    User ID: {$item['user_id']}\n";
            }
            echo "    Issue: {$item['issue']}\n";
            echo "    Fix: Run migration script\n\n";
        }
        
        echo "\n⚠️  TO FIX THESE ISSUES:\n";
        echo "Run: php database/migrate_affiliation_profiles.php\n\n";
    } else {
        echo "\n✅ All approved affiliations have complete user records!\n";
    }
    
    // Summary
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "SUMMARY:\n";
    echo "  Total Approved: " . count($affiliations) . "\n";
    echo "  Working: " . count($ok) . "\n";
    echo "  Issues: " . count($issues) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
