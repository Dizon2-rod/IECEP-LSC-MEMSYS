# Setup Test Accounts for IECEP-LSC MEMSYS

## Step 1: Create Auth Users in Supabase

Go to your Supabase Dashboard > Authentication > Users and create these users manually:

### Test Accounts to Create:

1. **Super Admin**
   - Email: `test.admin@iecep-lsc.test`
   - Password: `TestAdmin123!`

2. **President**
   - Email: `test.president@iecep-lsc.test`
   - Password: `TestPresident123!`

3. **Regular Member**
   - Email: `test.member@iecep-lsc.test`
   - Password: `TestMember123!`

4. **Committee Member**
   - Email: `test.committee@iecep-lsc.test`
   - Password: `TestCommittee123!`

## Step 2: Get User IDs

After creating the auth users, run this query in Supabase SQL Editor to get the user IDs:

```sql
SELECT id, email FROM auth.users WHERE email LIKE 'test.%@iecep-lsc.test';
```

Copy the user IDs for the next step.

## Step 3: Update User Profiles

Replace the placeholder IDs in this query with the actual user IDs from Step 2:

```sql
-- Update user profiles with actual user IDs
-- Replace: 'super-admin-id', 'president-id', 'member-id', 'committee-id' 
-- with actual user IDs from Step 2

INSERT INTO user_profiles (user_id, role, full_name, force_password_change, created_at) VALUES
('REPLACE_SUPER_ADMIN_ID', 'super_admin', 'Test Super Admin', false, NOW()),
('REPLACE_PRESIDENT_ID', 'eb_president', 'Test President', false, NOW()),
('REPLACE_MEMBER_ID', 'member', 'Test Member', false, NOW()),
('REPLACE_COMMITTEE_ID', 'committee_registration', 'Test Committee Member', false, NOW())
ON CONFLICT (user_id) DO UPDATE SET
  role = EXCLUDED.role,
  full_name = EXCLUDED.full_name,
  force_password_change = EXCLUDED.force_password_change;
```

## Step 4: Insert Member Details

```sql
-- Insert member details
INSERT INTO members (user_id, full_name, email, member_type, payment_status, year_level, created_at) VALUES
('REPLACE_SUPER_ADMIN_ID', 'Test Super Admin', 'test.admin@iecep-lsc.test', 'alumni', 'paid', '4th Year', NOW()),
('REPLACE_PRESIDENT_ID', 'Test President', 'test.president@iecep-lsc.test', 'alumni', 'paid', '4th Year', NOW()),
('REPLACE_MEMBER_ID', 'Test Member', 'test.member@iecep-lsc.test', 'student', 'paid', '3rd Year', NOW()),
('REPLACE_COMMITTEE_ID', 'Test Committee Member', 'test.committee@iecep-lsc.test', 'alumni', 'paid', '4th Year', NOW())
ON CONFLICT (user_id) DO UPDATE SET
  full_name = EXCLUDED.full_name,
  email = EXCLUDED.email,
  member_type = EXCLUDED.member_type,
  payment_status = EXCLUDED.payment_status,
  year_level = EXCLUDED.year_level;
```

## Step 5: Create Test Institutions

```sql
-- Create test institutions
INSERT INTO institutions (id, name, type, address, contact_email, created_at) VALUES
('test-inst-1', 'Test University', 'University', '123 Test St, Test City', 'admin@testuniversity.edu.ph', NOW()),
('test-inst-2', 'Test College', 'College', '456 College Ave, Test City', 'info@testcollege.edu.ph', NOW())
ON CONFLICT (id) DO NOTHING;
```

## Step 6: Link Members to Institutions

```sql
-- Link members to institutions
INSERT INTO member_institutions (member_id, institution_id, role, created_at) VALUES
('REPLACE_MEMBER_ID', 'test-inst-1', 'student', NOW()),
('REPLACE_COMMITTEE_ID', 'test-inst-2', 'faculty', NOW())
ON CONFLICT (member_id, institution_id) DO NOTHING;
```

## Step 7: Create Test Data

```sql
-- Create test announcements
INSERT INTO announcements (id, title, content, type, author_id, created_at, published_at) VALUES
('test-ann-1', 'Welcome to IECEP-LSC MEMSYS', 'This is a test announcement for the system.', 'general', 'REPLACE_SUPER_ADMIN_ID', NOW(), NOW()),
('test-ann-2', 'Test Meeting Schedule', 'Monthly chapter meeting scheduled for next week.', 'meeting', 'REPLACE_PRESIDENT_ID', NOW(), NOW())
ON CONFLICT (id) DO NOTHING;

-- Create test events
INSERT INTO events (id, title, description, event_date, location, created_at) VALUES
('test-event-1', 'Test Chapter Meeting', 'Monthly chapter meeting for all members.', NOW() + INTERVAL '7 days', 'Test University Campus', NOW()),
('test-event-2', 'Test Workshop', 'Professional development workshop.', NOW() + INTERVAL '14 days', 'Test College Hall', NOW())
ON CONFLICT (id) DO NOTHING;
```

## Step 8: Verify Setup

Run this query to verify all test accounts are properly set up:

```sql
-- Verify test accounts
SELECT 
  u.id as user_id,
  u.email,
  up.role,
  up.full_name,
  m.member_type,
  m.payment_status
FROM auth.users u
LEFT JOIN user_profiles up ON u.id = up.user_id
LEFT JOIN members m ON u.id = m.user_id
WHERE u.email LIKE 'test.%@iecep-lsc.test'
ORDER BY u.email;
```

## Testing

After completing all steps, you can test the login system:

1. Go to: `http://localhost/IECEP-LSC-MEMSYS/public/login.php`
2. Use any of the test accounts from Step 1
3. Verify the login works and redirects to the correct portal

## Notes

- Make sure to replace all placeholder IDs with actual user IDs from Supabase Auth
- The `ON CONFLICT` clauses ensure the queries can be run multiple times without errors
- All test accounts have `force_password_change = false` for easier testing
- All test accounts have `payment_status = 'paid'` for full system access
