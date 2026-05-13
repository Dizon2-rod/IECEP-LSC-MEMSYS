# Affiliation Approval Login Fix - Implementation Summary

## Problem Statement
Kapag nag-approve ng affiliation request:
1. ✅ Nag-send ng email with credentials
2. ✅ Nag-save sa `users` table sa Supabase
3. ❌ Pero pag nag-login gamit ang credentials, lumalabas: **"User profile not found. Please contact the administrator."**

## Root Cause Analysis

### Issue 1: Missing `user_profiles` Record
**File:** `public/portal/admin/affiliation_action.php`

Ang approval process ay:
- ✅ Nag-create ng record sa `users` table
- ❌ **HINDI nag-create ng record sa `user_profiles` table**

### Issue 2: Login Requires `user_profiles`
**File:** `login.php`

Ang login process ay:
1. Nag-verify ng password sa `users` table
2. **Nag-query ng `user_profiles` table para sa role at profile info**
3. Kung walang `user_profiles` record → Login fails with error message

### Why This Happens
```php
// Sa login.php (line ~90-95)
$profiles = $supabaseService->select('user_profiles', ['user_id' => 'eq.' . $user['id']]);
if (empty($profiles)) {
    // ❌ Walang profile = Login fails
    error_log("Login: no user_profiles row for user_id={$user['id']}");
    $error = 'User profile not found. Please contact the administrator.';
}
```

## Solution Implemented

### 1. Fixed Approval Process - New Users
**File:** `public/portal/admin/affiliation_action.php`

**Changes:**
```php
// Generate UUID for new user
$userId = sprintf(
    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    mt_rand(0, 0xffff),
    mt_rand(0, 0x0fff) | 0x4000,
    mt_rand(0, 0x3fff) | 0x8000,
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
);

// Create user in users table
$userData = [
    'id'          => $userId,  // ← Explicitly set UUID
    'email'       => $appData['email'],
    'password'    => $passwordHash,
    'full_name'   => $appData['contact_person'],
    'role'        => 'school_officer',
    'must_change_password' => 1,
    'is_active'   => 1,
    'created_at'  => date('Y-m-d H:i:s'),
    'updated_at'  => date('Y-m-d H:i:s')
];
$supabase->insert('users', $userData);

// ✅ NEW: Create user_profiles record
$profileData = [
    'user_id'     => $userId,
    'role'        => 'school_officer',
    'full_name'   => $appData['contact_person'],
    'school_name' => $appData['institution_name'],
    'contact_phone' => $appData['contact_phone'],
    'address'     => $appData['address'],
    'force_password_change' => true,
    'created_at'  => date('Y-m-d H:i:s'),
    'updated_at'  => date('Y-m-d H:i:s')
];
$supabase->insert('user_profiles', $profileData);
```

### 2. Fixed Approval Process - Existing Users
**File:** `public/portal/admin/affiliation_action.php`

**Changes:**
```php
if (!empty($existingUsers)) {
    $userId = $existingUsers[0]['id'];
    
    // Update users table
    $supabase->update('users', [
        'role' => 'school_officer',
        'full_name' => $appData['contact_person'],
        'updated_at' => date('Y-m-d H:i:s')
    ], $userId);
    
    // ✅ NEW: Check if user_profiles exists
    $existingProfiles = $supabase->select('user_profiles', ['user_id' => 'eq.' . $userId]);
    
    if (empty($existingProfiles)) {
        // Create missing profile
        $profileData = [
            'user_id'     => $userId,
            'role'        => 'school_officer',
            'full_name'   => $appData['contact_person'],
            'school_name' => $appData['institution_name'],
            'contact_phone' => $appData['contact_phone'],
            'address'     => $appData['address'],
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s')
        ];
        $supabase->insert('user_profiles', $profileData);
    } else {
        // Update existing profile
        $supabase->update('user_profiles', [
            'role' => 'school_officer',
            'school_name' => $appData['institution_name'],
            'updated_at' => date('Y-m-d H:i:s')
        ], $existingProfiles[0]['id']);
    }
}
```

### 3. Migration Script for Existing Data
**File:** `database/migrate_affiliation_profiles.php`

Created a migration script to fix existing approved affiliations that don't have `user_profiles` records.

**To run:**
```bash
cd c:\Users\ADMIN\Documents\xampp\htdocs\IECEP-LSC-MEMSYS
php database/migrate_affiliation_profiles.php
```

## Expected Flow After Fix

### Scenario 1: New Affiliation Approval
1. Admin approves affiliation request
2. System creates:
   - ✅ Record sa `users` table (with password hash)
   - ✅ Record sa `user_profiles` table (with role, school_name, etc.)
3. System sends email with credentials
4. School officer receives email
5. School officer logs in:
   - ✅ Password verified from `users` table
   - ✅ Profile found in `user_profiles` table
   - ✅ Login successful → Redirect to school-officer dashboard

### Scenario 2: Existing User Affiliation
1. Admin approves affiliation for existing user
2. System updates:
   - ✅ `users` table (role = school_officer)
   - ✅ `user_profiles` table (creates if missing, updates if exists)
3. System sends notification email
4. User logs in:
   - ✅ Password verified
   - ✅ Profile found with updated role
   - ✅ Login successful

## Database Schema

### `users` Table
```sql
- id (uuid, primary key)
- email (text, unique)
- password (text, bcrypt hash)
- full_name (text)
- role (text)
- must_change_password (boolean)
- is_active (boolean)
- created_at (timestamp)
- updated_at (timestamp)
```

### `user_profiles` Table
```sql
- id (uuid, primary key)
- user_id (uuid, foreign key → users.id)
- role (text)
- full_name (text)
- school_name (text)
- contact_phone (text)
- address (text)
- force_password_change (boolean)
- created_at (timestamp)
- updated_at (timestamp)
```

## Testing Checklist

### For New Affiliations
- [ ] Submit new affiliation request
- [ ] Admin approves the request
- [ ] Check email for credentials
- [ ] Verify `users` table has new record
- [ ] Verify `user_profiles` table has new record
- [ ] Login using email and password from email
- [ ] Verify successful login
- [ ] Verify redirect to school-officer dashboard

### For Existing Users
- [ ] Submit affiliation for existing user email
- [ ] Admin approves the request
- [ ] Verify `users` table updated (role = school_officer)
- [ ] Verify `user_profiles` table exists/updated
- [ ] Login using existing credentials
- [ ] Verify successful login with school_officer role

### Migration Script
- [ ] Run migration script
- [ ] Check output for fixed/skipped/errors count
- [ ] Verify all approved affiliations have user_profiles
- [ ] Test login for previously broken accounts

## Files Modified

1. **public/portal/admin/affiliation_action.php**
   - Added user_profiles creation for new users
   - Added user_profiles check/create for existing users
   - Added explicit UUID generation

2. **database/migrate_affiliation_profiles.php** (NEW)
   - Migration script to fix existing data

## Error Messages

### Before Fix
- ❌ "User profile not found. Please contact the administrator."
- ❌ "Invalid email or password. Please try again." (even with correct credentials)

### After Fix
- ✅ Successful login with credentials from email
- ✅ Redirect to appropriate dashboard
- ✅ Force password change on first login (if must_change_password = 1)

## Implementation Date
January 2025

## Notes

- The fix ensures both `users` and `user_profiles` tables are populated during approval
- Existing approved affiliations need to run the migration script once
- Future approvals will automatically create both records
- The login process requires both tables to have matching records
- UUID is explicitly generated to ensure consistency between tables
