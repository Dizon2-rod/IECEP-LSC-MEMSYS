-- =====================================================================
-- IECEP-LSC MEMSYS – ACCOUNT SEEDING SCRIPT (Supabase PostgreSQL)
-- Creates initial admin, school officer, and member accounts
-- Safe to run multiple times
-- =====================================================================

-- =====================================================================
-- ADMIN ACCOUNT
-- =====================================================================
-- Password: Admin123! (bcrypt hash)
INSERT INTO auth_users (id, email, password_hash, created_at, updated_at)
VALUES (
    'admin-001-iecep-lsc',
    'lspuscc.adminece@gmail.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    NOW(),
    NOW()
)
ON CONFLICT (id) DO UPDATE SET updated_at = NOW();

-- Insert admin profile
INSERT INTO user_profiles (id, name, email, role, institution, verification_status, created_at, updated_at)
VALUES (
    'admin-001-iecep-lsc',
    'IECEP-LSC Administrator',
    'lspuscc.adminece@gmail.com',
    'admin',                    -- role must match your CHECK constraint
    'IECEP-LSC National Office',
    'verified',
    NOW(),
    NOW()
)
ON CONFLICT (id) DO UPDATE SET updated_at = NOW();

-- =====================================================================
-- SCHOOL ACCOUNT (Affiliated School)
-- =====================================================================
-- Password: School123!
INSERT INTO auth_users (id, email, password_hash, created_at, updated_at)
VALUES (
    'school-001-pupsta',
    'ieceptest86@gmail.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    NOW(),
    NOW()
)
ON CONFLICT (id) DO UPDATE SET updated_at = NOW();

-- Insert institution
INSERT INTO institutions (id, email, name, acronym, type, address, city, province, region, country,
                          contact_person, contact_email, contact_phone, status, affiliation_fee_paid,
                          compliance_status, membership_count, established_year, created_at, updated_at)
VALUES (
    'school-001-pupsta',
    'ieceptest86@gmail.com',
    'Laguna State Polytechnic University - Santa Cruz Campus',
    'LSPU - SCC',
    'university',
    'Santa Cruz, Laguna',
    'Santa Cruz',
    'Laguna',
    'Region IV-A',
    'Philippines',
    'Jonnel Pabico',
    'ieceptest86@gmail.com',
    '632-5335-1787',
    'active',
    TRUE,
    'compliant',
    150,
    1904,
    NOW(),
    NOW()
)
ON CONFLICT (id) DO UPDATE SET updated_at = NOW();

-- Insert school officer profile
INSERT INTO user_profiles (id, name, email, role, institution, verification_status, created_at, updated_at)
VALUES (
    'school-001-pupsta',
    'LSPU - SCC School Officer',
    'ieceptest86@gmail.com',
    'school_officer',
    'Laguna State Polytechnic University - Santa Cruz Campus',
    'verified',
    NOW(),
    NOW()
)
ON CONFLICT (id) DO UPDATE SET updated_at = NOW();

-- =====================================================================
-- MEMBER ACCOUNT
-- =====================================================================
-- Password: Member123!
INSERT INTO auth_users (id, email, password_hash, created_at, updated_at)
VALUES (
    'member-001',
    'rasheddizon7@gmail.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    NOW(),
    NOW()
)
ON CONFLICT (id) DO UPDATE SET updated_at = NOW();

-- Insert member record
INSERT INTO members (id, full_name, email, membership_id, school_affiliate, year_level, member_type, status, created_at, updated_at)
VALUES (
    'member-001',
    'Rashed Dizon',
    'rasheddizon7@gmail.com',
    'IECEP-2026-0001',
    'Laguna State Polytechnic University - Santa Cruz Campus',
    '3rd Year',
    'returning',
    'active',
    NOW(),
    NOW()
)
ON CONFLICT (id) DO UPDATE SET updated_at = NOW();

-- Insert member profile
INSERT INTO user_profiles (id, name, email, role, institution, verification_status, digital_id_hash, created_at, updated_at)
VALUES (
    'member-001',
    'Rashed Dizon',
    'rasheddizon7@gmail.com',
    'member',
    'Laguna State Polytechnic University - Santa Cruz Campus',
    'verified',
    sha256('member-001-verified-2026'),   -- PostgreSQL SHA‑256 function
    NOW(),
    NOW()
)
ON CONFLICT (id) DO UPDATE SET updated_at = NOW();

-- =====================================================================
-- FEE BRACKETS SEEDING
-- =====================================================================
INSERT INTO fee_brackets (id, bracket_name, min_members, max_members, affiliation_fee, per_member_fee, annual_fee, valid_from, valid_to, is_active, description, created_at, updated_at)
VALUES 
    ('bracket-001', 'Small Institution (1-50 members)', 1, 50, 5000.00, 100.00, 2000.00, '2024-01-01', '2025-12-31', TRUE, 'For small institutions with 1-50 members', NOW(), NOW()),
    ('bracket-002', 'Medium Institution (51-150 members)', 51, 150, 8000.00, 80.00, 3500.00, '2024-01-01', '2025-12-31', TRUE, 'For medium institutions with 51-150 members', NOW(), NOW()),
    ('bracket-003', 'Large Institution (151-300 members)', 151, 300, 12000.00, 60.00, 5000.00, '2024-01-01', '2025-12-31', TRUE, 'For large institutions with 151-300 members', NOW(), NOW()),
    ('bracket-004', 'Very Large Institution (301+ members)', 301, NULL, 15000.00, 50.00, 7000.00, '2024-01-01', '2025-12-31', TRUE, 'For very large institutions with 301+ members', NOW(), NOW())
ON CONFLICT (id) DO UPDATE SET updated_at = NOW();

-- =====================================================================
-- SAMPLE EVENTS
-- =====================================================================
INSERT INTO events (id, title, description, start_date, end_date, venue, max_participants, registration_fee, status, created_at, updated_at)
VALUES 
    ('event-001-seminar', 'IECEP-LSC Technical Seminar 2026', 'Annual technical seminar featuring industry experts and research presentations', '2026-08-15 09:00:00+08', '2026-08-15 17:00:00+08', 'PUP Main Auditorium, Sta. Mesa', 500, 500.00, 'upcoming', NOW(), NOW()),
    ('event-002-workshop', 'Electronics Design Workshop', 'Hands-on workshop on PCB design and microcontroller programming', '2026-09-20 08:00:00+08', '2026-09-20 16:00:00+08', 'DLSU Electronics Lab', 30, 750.00, 'upcoming', NOW(), NOW()),
    ('event-003-conference', 'IECEP-LSC Annual Conference', 'Annual conference with networking opportunities and technical sessions', '2026-10-25 09:00:00+08', '2026-10-26 17:00:00+08', 'UPLB Operations Management Building', 200, 1000.00, 'upcoming', NOW(), NOW())
ON CONFLICT (id) DO UPDATE SET updated_at = NOW();

-- =====================================================================
-- ACCOUNT CREDENTIALS SUMMARY (unchanged)
-- =====================================================================
-- ADMIN ACCOUNT:
-- Email: lspuscc.adminece@gmail.com
-- Password: Admin123!
-- Role: admin

-- SCHOOL ACCOUNT:
-- Email: ieceptest86@gmail.com
-- Password: School123!
-- Role: school_officer

-- MEMBER ACCOUNT:
-- Email: rasheddizon7@gmail.com
-- Password: Member123!
-- Role: member