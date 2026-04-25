-- IECEP-LSC MEMSYS Test Accounts
-- Run these queries in Supabase SQL Editor to create test accounts

-- Insert test users into auth.users (Supabase Auth)
-- Note: These need to be created via Supabase Auth API or Dashboard
-- Email: test.admin@iecep-lsc.test, Password: TestAdmin123!
-- Email: test.president@iecep-lsc.test, Password: TestPresident123!
-- Email: test.member@iecep-lsc.test, Password: TestMember123!
-- Email: test.committee@iecep-lsc.test, Password: TestCommittee123!

-- Insert user profiles
INSERT INTO user_profiles (user_id, role, full_name, force_password_change, created_at) VALUES
-- Super Admin (user_id will be replaced with actual auth.users.id)
('super-admin-id', 'super_admin', 'Test Super Admin', false, NOW()),
-- President
('president-id', 'eb_president', 'Test President', false, NOW()),
-- Regular Member
('member-id', 'member', 'Test Member', false, NOW()),
-- Committee Member
('committee-id', 'committee_registration', 'Test Committee Member', false, NOW());

-- Insert member details
INSERT INTO members (user_id, full_name, email, member_type, payment_status, year_level, created_at) VALUES
-- Super Admin
('super-admin-id', 'Test Super Admin', 'test.admin@iecep-lsc.test', 'alumni', 'paid', '4th Year', NOW()),
-- President
('president-id', 'Test President', 'test.president@iecep-lsc.test', 'alumni', 'paid', '4th Year', NOW()),
-- Regular Member
('member-id', 'Test Member', 'test.member@iecep-lsc.test', 'student', 'paid', '3rd Year', NOW()),
-- Committee Member
('committee-id', 'Test Committee Member', 'test.committee@iecep-lsc.test', 'alumni', 'paid', '4th Year', NOW());

-- Insert institutions for testing
INSERT INTO institutions (id, name, type, address, contact_email, created_at) VALUES
('test-inst-1', 'Test University', 'University', '123 Test St, Test City', 'admin@testuniversity.edu.ph', NOW()),
('test-inst-2', 'Test College', 'College', '456 College Ave, Test City', 'info@testcollege.edu.ph', NOW());

-- Link members to institutions
INSERT INTO member_institutions (member_id, institution_id, role, created_at) VALUES
-- Test Member linked to Test University
('member-id', 'test-inst-1', 'student', NOW()),
-- Committee Member linked to Test College
('committee-id', 'test-inst-2', 'faculty', NOW());

-- Create test announcements
INSERT INTO announcements (id, title, content, type, author_id, created_at, published_at) VALUES
('test-ann-1', 'Welcome to IECEP-LSC MEMSYS', 'This is a test announcement for the system.', 'general', 'super-admin-id', NOW(), NOW()),
('test-ann-2', 'Test Meeting Schedule', 'Monthly chapter meeting scheduled for next week.', 'meeting', 'president-id', NOW(), NOW());

-- Create test events
INSERT INTO events (id, title, description, event_date, location, created_at) VALUES
('test-event-1', 'Test Chapter Meeting', 'Monthly chapter meeting for all members.', NOW() + INTERVAL '7 days', 'Test University Campus', NOW()),
('test-event-2', 'Test Workshop', 'Professional development workshop.', NOW() + INTERVAL '14 days', 'Test College Hall', NOW());

-- Create test payments
INSERT INTO payments (id, member_id, amount, payment_type, status, payment_date, created_at) VALUES
('test-pay-1', 'member-id', 500.00, 'annual_fee', 'paid', NOW(), NOW()),
('test-pay-2', 'committee-id', 500.00, 'annual_fee', 'paid', NOW(), NOW());

-- Create test attendance records
INSERT INTO attendance (id, event_id, member_id, status, check_in_time, created_at) VALUES
('test-att-1', 'test-event-1', 'member-id', 'attended', NOW() + INTERVAL '7 days', NOW()),
('test-att-2', 'test-event-1', 'committee-id', 'attended', NOW() + INTERVAL '7 days', NOW());

-- Create test compliance records
INSERT INTO compliance_records (id, member_id, institution_id, type, status, submitted_date, created_at) VALUES
('test-comp-1', 'member-id', 'test-inst-1', 'annual_report', 'compliant', NOW(), NOW()),
('test-comp-2', 'committee-id', 'test-inst-2', 'annual_report', 'compliant', NOW(), NOW());

-- Create test digital IDs
INSERT INTO digital_ids (id, member_id, qr_code, issued_date, expires_date, status, created_at) VALUES
('test-did-1', 'member-id', 'QR-MEMBER-001', NOW(), NOW() + INTERVAL '1 year', 'active', NOW()),
('test-did-2', 'committee-id', 'QR-COMMITTEE-001', NOW(), NOW() + INTERVAL '1 year', 'active', NOW());

-- Create test committee assignments
INSERT INTO committee_assignments (id, committee_type, member_id, role, assigned_date, created_at) VALUES
('test-ca-1', 'registration', 'committee-id', 'member', NOW(), NOW());

-- Create test officer positions
INSERT INTO officer_positions (id, position_type, member_id, institution_id, term_start, term_end, created_at) VALUES
('test-op-1', 'president', 'president-id', NULL, NOW(), NOW() + INTERVAL '1 year', NOW());

-- Note: Replace the placeholder IDs (super-admin-id, president-id, etc.) 
-- with actual user IDs from Supabase Auth after creating the auth users

-- To get the actual user IDs, run:
-- SELECT id, email FROM auth.users WHERE email LIKE 'test.%@iecep-lsc.test';

-- Then update the user_profiles table with the correct user_ids:
-- UPDATE user_profiles SET user_id = 'actual-auth-user-id' WHERE user_id = 'super-admin-id';
-- UPDATE user_profiles SET user_id = 'actual-auth-user-id' WHERE user_id = 'president-id';
-- etc.
