-- Create a test user account for login testing
-- Run this in Supabase SQL Editor

-- Step 1: Insert user directly into auth.users table
-- Note: This requires service role privileges. You can also create users via Dashboard.

INSERT INTO auth.users (
  id,
  instance_id,
  aud,
  role,
  email,
  encrypted_password,
  email_confirmed_at,
  created_at,
  updated_at,
  raw_user_meta_data
) VALUES (
  gen_random_uuid(),
  '00000000-0000-0000-0000-000000000000',
  'authenticated',
  'authenticated',
  'test@iecep-lsc.org',
  crypt('TestPassword123!', gen_salt('bf')),
  NOW(),
  NOW(),
  NOW(),
  '{"full_name": "Test User", "role": "member"}'::jsonb
);

-- Step 2: Create user profile
INSERT INTO user_profiles (user_id, role, full_name, force_password_change)
VALUES (
  (SELECT id FROM auth.users WHERE email = 'test@iecep-lsc.org'),
  'member',
  'Test User',
  false
) ON CONFLICT (user_id) DO NOTHING;

-- Step 3: Create member record linked to UPLB institution
INSERT INTO members (institution_id, user_id, full_name, email, member_type, payment_status, year_level)
VALUES (
  (SELECT id FROM institutions WHERE email = 'admin@uplb.edu.ph'),
  (SELECT id FROM auth.users WHERE email = 'test@iecep-lsc.org'),
  'Test User',
  'test@iecep-lsc.org',
  'new',
  true,
  '3rd Year'
) ON CONFLICT (email) DO NOTHING;

-- Step 4: Verify the user was created
SELECT 'User Created' as status,
       u.id as user_id,
       u.email,
       u.email_confirmed_at,
       up.role as profile_role,
       m.full_name as member_name,
       i.name as institution
FROM auth.users u
LEFT JOIN user_profiles up ON u.id = up.user_id
LEFT JOIN members m ON u.id = m.user_id
LEFT JOIN institutions i ON m.institution_id = i.id
WHERE u.email = 'test@iecep-lsc.org';
