<?php
/**
 * Test Accounts Configuration
 * 
 * DEVELOPMENT ONLY - Do NOT use in production
 * 
 * These are hardcoded test credentials for rapid development and testing.
 * Each role has a dedicated test account with corresponding dashboard access.
 * 
 * In production, remove the test account fallback from login.php
 */

return [
    // Super Admin - Full System Control
    'super.admin@memsys.test' => [
        'password' => 'SuperAdmin123!',
        'role' => 'eb_president',
        'full_name' => 'Super Administrator',
    ],
    
    // Admin - System Management
    'admin@memsys.test' => [
        'password' => 'Admin123!',
        'role' => 'admin',
        'full_name' => 'System Administrator',
    ],
    
    // Registration Committee - Affiliation Review
    'registration@memsys.test' => [
        'password' => 'Reg123!',
        'role' => 'committee_registration',
        'full_name' => 'Registration Officer',
    ],
    
    // Creatives Committee - Announcements & Publications
    'creatives@memsys.test' => [
        'password' => 'Creatives123!',
        'role' => 'committee_creatives',
        'full_name' => 'Creatives Committee Head',
    ],
    
    // Treasurer - Financial Management
    'treasurer@memsys.test' => [
        'password' => 'Treasurer123!',
        'role' => 'eb_treasurer',
        'full_name' => 'Treasurer',
    ],
    
    // Auditor - Compliance & Audit
    'auditor@memsys.test' => [
        'password' => 'Auditor123!',
        'role' => 'eb_auditor',
        'full_name' => 'Auditor',
    ],
    
    // Secretary - Record Keeping
    'secretary@memsys.test' => [
        'password' => 'Secretary123!',
        'role' => 'eb_secretary_general',
        'full_name' => 'Secretary General',
    ],
    
    // School Officer - Institution Member Management
    'school.officer@school.edu' => [
        'password' => 'Officer123!',
        'role' => 'school_officer',
        'full_name' => 'School Officer',
    ],
    
    // Regular Member - Member Portal
    'member@school.edu' => [
        'password' => 'Member123!',
        'role' => 'member',
        'full_name' => 'Regular Member',
    ],
];
