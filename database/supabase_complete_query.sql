-- =====================================================================
-- IECEP-LSC MEMSYS - COMPLETE PRODUCTION SUPABASE POSTGRESQL SCHEMA
-- Laguna Student Chapter Membership & Affiliation Management System
-- Generated: 2026-08-28 | Tested for Supabase PostgreSQL & PostgREST
-- 100% Synced with Localhost XAMPP MySQL Schema
-- =====================================================================

-- 1. EXTENSIONS
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- 2. UTILITY FUNCTIONS & TRIGGERS
CREATE OR REPLACE FUNCTION handle_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- 3. INSTITUTIONS TABLE (Affiliated HEI Universities in Laguna)
CREATE TABLE IF NOT EXISTS institutions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    acronym TEXT,
    type TEXT DEFAULT 'university' CHECK (type IN ('university', 'college', 'institute', 'school', 'company', 'organization')),
    address TEXT,
    city TEXT,
    province TEXT DEFAULT 'Laguna',
    region TEXT DEFAULT 'Region IV-A (CALABARZON)',
    country TEXT DEFAULT 'Philippines',
    contact_person TEXT,
    contact_email TEXT,
    contact_phone TEXT,
    website TEXT,
    facebook_url TEXT,
    status TEXT DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'pending', 'suspended')),
    affiliation_fee_paid BOOLEAN DEFAULT false,
    compliance_status TEXT DEFAULT 'compliant' CHECK (compliance_status IN ('compliant', 'at_risk', 'non_compliant')),
    membership_count INTEGER DEFAULT 0,
    established_year INTEGER,
    accreditation_status TEXT,
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_institutions_status ON institutions(status);
CREATE INDEX IF NOT EXISTS idx_institutions_acronym ON institutions(acronym);

-- 4. USERS & USER PROFILES
CREATE TABLE IF NOT EXISTS user_profiles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID UNIQUE,
    email TEXT UNIQUE NOT NULL,
    full_name TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'member' CHECK (role IN ('super_admin', 'admin', 'school_officer', 'member', 'auditor', 'treasurer', 'guest')),
    institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL,
    phone TEXT,
    avatar_url TEXT,
    status TEXT DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'pending', 'suspended')),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_user_profiles_role ON user_profiles(role);
CREATE INDEX IF NOT EXISTS idx_user_profiles_inst ON user_profiles(institution_id);

-- Ensure all user_profiles columns exist on existing tables
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS user_id UUID;
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS full_name TEXT;
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS institution_id UUID;
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS phone TEXT;
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS avatar_url TEXT;
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'active';

-- 5. MEMBERS TABLE (Official Chapter Membership & Digital ID Roster)
CREATE TABLE IF NOT EXISTS members (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES user_profiles(id) ON DELETE SET NULL,
    membership_id TEXT UNIQUE NOT NULL,
    full_name TEXT NOT NULL,
    first_name TEXT,
    last_name TEXT,
    email TEXT UNIQUE NOT NULL,
    phone TEXT,
    institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE,
    course TEXT DEFAULT 'Bachelor of Science in Electronics Engineering',
    year_level TEXT DEFAULT '4th Year',
    student_number TEXT,
    membership_type TEXT DEFAULT 'student' CHECK (membership_type IN ('student', 'associate', 'regular', 'senior', 'fellow', 'honorary')),
    status TEXT DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'pending', 'expired', 'suspended')),
    payment_status TEXT DEFAULT 'paid' CHECK (payment_status IN ('paid', 'pending', 'waived', 'unpaid', 'overdue')),
    digital_id_hash TEXT,
    qr_code_url TEXT,
    joined_date DATE DEFAULT CURRENT_DATE,
    expiration_date DATE DEFAULT (CURRENT_DATE + INTERVAL '1 year'),
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Ensure all member columns exist on existing tables
ALTER TABLE members ADD COLUMN IF NOT EXISTS payment_status TEXT DEFAULT 'paid';
ALTER TABLE members ADD COLUMN IF NOT EXISTS first_name TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS last_name TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS student_number TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS digital_id_hash TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS phone TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS course TEXT DEFAULT 'Bachelor of Science in Electronics Engineering';
ALTER TABLE members ADD COLUMN IF NOT EXISTS year_level TEXT DEFAULT '4th Year';

CREATE INDEX IF NOT EXISTS idx_members_mem_id ON members(membership_id);
CREATE INDEX IF NOT EXISTS idx_members_inst ON members(institution_id);
CREATE INDEX IF NOT EXISTS idx_members_status ON members(status);
CREATE INDEX IF NOT EXISTS idx_members_payment ON members(payment_status);

-- 6. MEMBER ID COUNTER (For auto-generating sequential IECEP-2026-XXXX)
CREATE TABLE IF NOT EXISTS member_id_counter (
    year INTEGER PRIMARY KEY,
    last_number INTEGER NOT NULL DEFAULT 0,
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- 7. EVENTS TABLE
CREATE TABLE IF NOT EXISTS events (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    description TEXT,
    event_type TEXT DEFAULT 'seminar' CHECK (event_type IN ('seminar', 'workshop', 'technical_summit', 'assembly', 'community', 'competition', 'other')),
    venue TEXT DEFAULT 'Main Auditorium / Online',
    location TEXT,
    start_date TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    end_date TIMESTAMPTZ NOT NULL DEFAULT (NOW() + INTERVAL '4 hours'),
    start_datetime TIMESTAMPTZ,
    end_datetime TIMESTAMPTZ,
    registration_fee NUMERIC(10,2) DEFAULT 0.00,
    fee NUMERIC(10,2) DEFAULT 0.00,
    max_attendees INTEGER DEFAULT 500,
    max_capacity INTEGER DEFAULT 500,
    status TEXT DEFAULT 'published' CHECK (status IN ('draft', 'published', 'ongoing', 'completed', 'cancelled')),
    institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL,
    created_by UUID,
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_events_status ON events(status);
CREATE INDEX IF NOT EXISTS idx_events_start ON events(start_date);

-- 8. EVENT ATTENDEES (Live Dynamic 15s QR & Officer Scanner Attendance)
CREATE TABLE IF NOT EXISTS event_attendees (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_id UUID NOT NULL REFERENCES events(id) ON DELETE CASCADE,
    member_id UUID NOT NULL REFERENCES members(id) ON DELETE CASCADE,
    status TEXT NOT NULL DEFAULT 'attended' CHECK (status IN ('registered', 'attended', 'cancelled', 'waitlisted')),
    check_in_time TIMESTAMPTZ DEFAULT NOW(),
    check_out_time TIMESTAMPTZ,
    qr_hash TEXT,
    verified_by UUID,
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(event_id, member_id)
);

CREATE INDEX IF NOT EXISTS idx_att_event ON event_attendees(event_id);
CREATE INDEX IF NOT EXISTS idx_att_member ON event_attendees(member_id);
CREATE INDEX IF NOT EXISTS idx_att_status ON event_attendees(status);

-- 9. BLOCKCHAIN RECORDS (Cryptographic Proof & SHA-256 Ledger)
CREATE TABLE IF NOT EXISTS blockchain_records (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    block_index BIGINT,
    entity_type TEXT NOT NULL,
    entity_id UUID NOT NULL,
    transaction_hash TEXT NOT NULL,
    record_hash TEXT,
    data_hash TEXT,
    previous_hash TEXT,
    merkle_root TEXT,
    data_json JSONB NOT NULL DEFAULT '{}',
    confirmed BOOLEAN DEFAULT true,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_bc_entity ON blockchain_records(entity_type, entity_id);
CREATE INDEX IF NOT EXISTS idx_bc_hash ON blockchain_records(transaction_hash);

-- 10. TRANSACTIONS & TREASURY
CREATE TABLE IF NOT EXISTS transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id TEXT UNIQUE NOT NULL,
    user_id UUID,
    member_id UUID REFERENCES members(id) ON DELETE SET NULL,
    institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL,
    event_id UUID REFERENCES events(id) ON DELETE SET NULL,
    amount NUMERIC(10,2) NOT NULL,
    fee_type TEXT NOT NULL DEFAULT 'membership_fee',
    payment_method TEXT DEFAULT 'gcash' CHECK (payment_method IN ('gcash', 'maya', 'bank_transfer', 'cash', 'stripe', 'other')),
    reference_number TEXT,
    receipt_url TEXT,
    status TEXT DEFAULT 'completed' CHECK (status IN ('pending', 'completed', 'verified', 'rejected', 'refunded')),
    notes TEXT,
    verified_by UUID,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_tx_status ON transactions(status);
CREATE INDEX IF NOT EXISTS idx_tx_member ON transactions(member_id);

-- 11. PENDING AFFILIATIONS & INSTITUTIONAL APPLICANTS
CREATE TABLE IF NOT EXISTS pending_affiliations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    school_name TEXT NOT NULL,
    acronym TEXT,
    email TEXT NOT NULL,
    contact_person TEXT NOT NULL,
    contact_number TEXT,
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'under_review', 'approved', 'rejected', 'requires_revision')),
    documents JSONB DEFAULT '{}',
    verification_code TEXT,
    verified_at TIMESTAMPTZ,
    rejection_reason TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_pending_aff_status ON pending_affiliations(status);

-- 12. VERIFICATION CODES (Email 2FA & Application Validation)
CREATE TABLE IF NOT EXISTS verification_codes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email TEXT NOT NULL,
    code TEXT NOT NULL,
    purpose TEXT DEFAULT 'affiliation',
    expires_at TIMESTAMPTZ NOT NULL,
    used BOOLEAN DEFAULT false,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_ver_code ON verification_codes(email, code);

-- 13. MERCHANDISE & STORE
CREATE TABLE IF NOT EXISTS merch_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    category TEXT DEFAULT 'apparel',
    description TEXT,
    price NUMERIC(10,2) NOT NULL,
    image_url TEXT,
    badge TEXT,
    stock INTEGER DEFAULT 100,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS merch_orders (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id TEXT UNIQUE NOT NULL,
    customer_name TEXT NOT NULL,
    customer_email TEXT NOT NULL,
    customer_phone TEXT,
    shipping_address TEXT,
    items JSONB NOT NULL DEFAULT '[]',
    total_amount NUMERIC(10,2) NOT NULL,
    payment_method TEXT DEFAULT 'gcash',
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'processing', 'shipped', 'completed', 'cancelled')),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 14. FEATURED CARDS & ANNOUNCEMENTS
CREATE TABLE IF NOT EXISTS featured_cards (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    description TEXT,
    category TEXT DEFAULT 'Announcement',
    image_url TEXT,
    link_url TEXT,
    badge_text TEXT,
    sort_order INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS announcements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    target_role TEXT DEFAULT 'all',
    priority TEXT DEFAULT 'normal' CHECK (priority IN ('low', 'normal', 'high', 'urgent')),
    author_id UUID,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 15. NOTIFICATIONS
CREATE TABLE IF NOT EXISTS notifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID,
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    type TEXT DEFAULT 'info' CHECK (type IN ('info', 'success', 'warning', 'danger', 'event', 'system')),
    link_url TEXT,
    is_read BOOLEAN DEFAULT false,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_notif_user ON notifications(user_id, is_read);

-- 16. SYSTEM SETTINGS & FEE BRACKETS (Board Resolution No. 021-2024)
CREATE TABLE IF NOT EXISTS system_settings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    key TEXT NOT NULL UNIQUE,
    value TEXT NOT NULL,
    description TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS fee_brackets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    bracket_name TEXT NOT NULL UNIQUE,
    min_members INTEGER NOT NULL,
    max_members INTEGER,
    fee NUMERIC(10,2) NOT NULL,
    per_member_fee NUMERIC(10,2) DEFAULT 0.00,
    annual_fee NUMERIC(10,2) DEFAULT 0.00,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- =====================================================================
-- SEED DATA: OFFICIAL LAGUNA HEI CHAPTERS (All 8 Official Campuses)
-- =====================================================================
INSERT INTO institutions (id, email, name, acronym, type, address, city, province, contact_email, facebook_url, status, compliance_status, membership_count)
VALUES
    ('b2c3d4e5-f6a7-8901-bcde-f12345678901', 'ecelss@letran-calamba.edu.ph', 'Colegio de San Juan de Letran - Calamba', 'Letran - Calamba', 'college', 'Colegio de San Juan de Letran, Calamba, Philippines, 4027', 'Calamba', 'Laguna', 'ecelss@letran-calamba.edu.ph', 'https://www.facebook.com/ECELSSrocks', 'active', 'compliant', 95),
    ('3c6f8a12-9844-48f6-b11c-99d9b626e5a1', 'afece_spc@lspu.edu.ph', 'Laguna State Polytechnic University - San Pablo City Campus', 'LSPU - SPCC', 'university', 'San Pablo City, Philippines, 4000', 'San Pablo City', 'Laguna', 'afece_spc@lspu.edu.ph', 'https://www.facebook.com/LSPUAFECE', 'active', 'compliant', 120),
    ('7d8e9f01-1234-4567-89ab-cdef01234567', 'iecepmmcl@gmail.com', 'Mapúa Malayan Colleges Laguna', 'MMCL', 'college', 'Pulo, Cabuyao, Philippines, 4025', 'Cabuyao', 'Laguna', 'iecepmmcl@gmail.com', 'https://www.facebook.com/iecepmmcl', 'active', 'compliant', 110),
    ('4d5e6f7a-8b9c-0123-def4-567890123456', 'jieceppnc@gmail.com', 'University of Cabuyao (Pamantasan ng Cabuyao)', 'PnC', 'university', 'Cabuyao, Philippines, 4025', 'Cabuyao', 'Laguna', 'jieceppnc@gmail.com', 'https://www.facebook.com/jiecep.pnc.official', 'active', 'compliant', 85),
    ('c3d4e5f6-a7b8-9012-cdef-123456789012', 'officialaeces.pupsrc@gmail.com', 'Polytechnic University of the Philippines - Santa Rosa Campus', 'PUP - Santa Rosa', 'university', 'Room 3-4, PUP-Sta. Rosa, Barangay Tagapo, Santa Rosa, Philippines, 4026', 'Santa Rosa', 'Laguna', 'officialaeces.pupsrc@gmail.com', 'https://www.facebook.com/OfficialAECES', 'active', 'compliant', 130),
    ('e5f6a7b8-c9d0-1234-ef12-345678901234', 'uphsl.pieces@gmail.com', 'University of Perpetual Help System Laguna – Biñan Campus', 'UPHSL - Biñan', 'university', 'National Hi-way, Brgy. Sto. Niño, Biñan, Philippines, 4024', 'Biñan', 'Laguna', 'uphsl.pieces@gmail.com', 'https://www.facebook.com/uphslpieces', 'active', 'compliant', 90),
    ('d4e5f6a7-b8c9-0123-def1-234567890123', 'pieces.uphsd@gmail.com', 'University of Perpetual Help System DALTA - Calamba Campus', 'UPHSD - Calamba', 'university', 'Calamba, Philippines, 4027', 'Calamba', 'Laguna', 'pieces.uphsd@gmail.com', 'https://www.facebook.com/eceperpslp.org', 'active', 'compliant', 75),
    ('1fe48809-8ac6-4428-a6f1-3025cc47f5bb', 'official.lspusccecess@gmail.com', 'Laguna State Polytechnic University - Santa Cruz Campus', 'LSPU - SCC', 'university', 'Santa Cruz National High-way, Brgy. Bubukal, Santa Cruz, Laguna', 'Santa Cruz', 'Laguna', 'official.lspusccecess@gmail.com', 'https://www.facebook.com/LSPUSCCECESS', 'active', 'compliant', 150)
ON CONFLICT (id) DO UPDATE SET
    email = EXCLUDED.email,
    name = EXCLUDED.name,
    acronym = EXCLUDED.acronym,
    address = EXCLUDED.address,
    city = EXCLUDED.city,
    facebook_url = EXCLUDED.facebook_url,
    contact_email = EXCLUDED.contact_email,
    compliance_status = EXCLUDED.compliance_status,
    membership_count = EXCLUDED.membership_count;

-- SEED DATA: OFFICIAL EVENTS
INSERT INTO events (id, title, description, event_type, venue, start_date, end_date, status, registration_fee, max_attendees)
VALUES
    ('2f2f99ce-98e1-49f6-8949-760687189aa6', 'IECEP-LSC Regional Technical Summit 2026', 'Flagship regional technical convention and research exposition for Laguna electronics engineering students.', 'technical_summit', 'Main Auditorium / Online', NOW() - INTERVAL '2 hours', NOW() + INTERVAL '8 hours', 'published', 150.00, 500),
    ('a9b8c7d6-e5f4-3210-fedc-ba9876543210', 'IECEP Leadership & Chapter Assembly 2026', 'Annual quorum and leadership transition assembly for affiliated Laguna HEI chapters.', 'assembly', 'LSPU Main Hall', NOW() + INTERVAL '7 days', NOW() + INTERVAL '7 days 5 hours', 'published', 0.00, 300)
ON CONFLICT (id) DO UPDATE SET
    title = EXCLUDED.title,
    description = EXCLUDED.description,
    status = EXCLUDED.status;

-- SEED DATA: OFFICIAL AUTH USERS & PROFILES
INSERT INTO users (id, email, password, full_name, role, is_active, created_at, updated_at)
VALUES
    ('00000000-0000-0000-0000-000000000001', 'lspuscc.adminece@gmail.com', '$2y$12$mypSMbD3y1XR5uuewBIV5ONYYT3yODWWKdOINbV7/2n86Xu0PupXK', 'IECEP-LSC Regional Admin', 'super_admin', true, NOW(), NOW()),
    ('00000000-0000-0000-0000-000000000002', 'ieceptest86@gmail.com', '$2y$12$7QzP4zCK2as87c1og7U59et9vvPHU90pCYCNXn.zM7RuH/cti.cXa', 'LSPU - SCC School Officer', 'school_officer', true, NOW(), NOW()),
    ('00000000-0000-0000-0000-000000000003', 'rasheddizon7@gmail.com', '$2y$12$t6adOxlvvxUJa4Lu2U6EX.R5U.2KGRTwQNeE9i51ou9Cw59Ft2vDi', 'Rashed Dizon', 'member', true, NOW(), NOW())
ON CONFLICT (email) DO UPDATE SET
    password = EXCLUDED.password,
    full_name = EXCLUDED.full_name,
    role = EXCLUDED.role,
    is_active = EXCLUDED.is_active;

INSERT INTO user_profiles (id, user_id, email, full_name, role, institution_id, phone, status)
VALUES
    ('00000000-0000-0000-0000-000000000001', '00000000-0000-0000-0000-000000000001', 'lspuscc.adminece@gmail.com', 'IECEP-LSC Regional Admin', 'super_admin', '1fe48809-8ac6-4428-a6f1-3025cc47f5bb', '09171234567', 'active'),
    ('00000000-0000-0000-0000-000000000002', '00000000-0000-0000-0000-000000000002', 'ieceptest86@gmail.com', 'LSPU - SCC School Officer', 'school_officer', '1fe48809-8ac6-4428-a6f1-3025cc47f5bb', '09181234567', 'active'),
    ('00000000-0000-0000-0000-000000000003', '00000000-0000-0000-0000-000000000003', 'rasheddizon7@gmail.com', 'Rashed Dizon', 'member', '1fe48809-8ac6-4428-a6f1-3025cc47f5bb', '09191234567', 'active')
ON CONFLICT (email) DO UPDATE SET
    full_name = EXCLUDED.full_name,
    role = EXCLUDED.role,
    institution_id = EXCLUDED.institution_id;

INSERT INTO members (
    id, membership_id, full_name, first_name, last_name, email,
    phone, institution_id, course, year_level, student_number,
    membership_type, status, payment_status, digital_id_hash
)
VALUES
    -- 1. Colegio de San Juan de Letran - Calamba (ECELSS)
    ('50000000-0000-0000-0000-000000000001', 'IECEP-2026-0501', 'Alyssa Reyes', 'Alyssa', 'Reyes', 'alyssa.reyes@letran-calamba.edu.ph', '09215550001', 'b2c3d4e5-f6a7-8901-bcde-f12345678901', 'BS Electronics Engineering', '4th Year', '2022-05001', 'student', 'active', 'paid', 'a1b2c3d4e5f60501'),
    ('50000000-0000-0000-0000-000000000002', 'IECEP-2026-0502', 'Gabriel Santos', 'Gabriel', 'Santos', 'gabriel.santos@letran-calamba.edu.ph', '09215550002', 'b2c3d4e5-f6a7-8901-bcde-f12345678901', 'BS Electronics Engineering', '2nd Year', '2024-05002', 'student', 'active', 'paid', 'a1b2c3d4e5f60502'),

    -- 2. LSPU - San Pablo City Campus (AFECE)
    ('20000000-0000-0000-0000-000000000001', 'IECEP-2026-0201', 'Patricia Reyes', 'Patricia', 'Reyes', 'patricia.spcc@lspu.edu.ph', '09182220001', '3c6f8a12-9844-48f6-b11c-99d9b626e5a1', 'BS Electronics Engineering', '1st Year', '2025-02001', 'student', 'active', 'paid', 'a1b2c3d4e5f60201'),
    ('20000000-0000-0000-0000-000000000002', 'IECEP-2026-0202', 'Angelo Bautista', 'Angelo', 'Bautista', 'angelo.spcc@lspu.edu.ph', '09182220002', '3c6f8a12-9844-48f6-b11c-99d9b626e5a1', 'BS Electronics Engineering', '3rd Year', '2023-02002', 'student', 'active', 'paid', 'a1b2c3d4e5f60202'),

    -- 3. Mapúa Malayan Colleges Laguna (IECEP - MMCL)
    ('30000000-0000-0000-0000-000000000001', 'IECEP-2026-0301', 'Kyla Ramos', 'Kyla', 'Ramos', 'kyla.ramos@mmcl.edu.ph', '09193330001', '7d8e9f01-1234-4567-89ab-cdef01234567', 'BS Electronics Engineering', '2nd Year', '2024-03001', 'student', 'active', 'paid', 'a1b2c3d4e5f60301'),
    ('30000000-0000-0000-0000-000000000002', 'IECEP-2026-0302', 'Justin Tan', 'Justin', 'Tan', 'justin.tan@mmcl.edu.ph', '09193330002', '7d8e9f01-1234-4567-89ab-cdef01234567', 'BS Electronics Engineering', '4th Year', '2022-03002', 'student', 'active', 'paid', 'a1b2c3d4e5f60302'),

    -- 4. University of Cabuyao / Pamantasan ng Cabuyao (OECES / PnC)
    ('40000000-0000-0000-0000-000000000001', 'IECEP-2026-0401', 'Christian Flores', 'Christian', 'Flores', 'christian.pnc@gmail.com', '09204440001', '4d5e6f7a-8b9c-0123-def4-567890123456', 'BS Electronics Engineering', '1st Year', '2025-04001', 'student', 'active', 'paid', 'a1b2c3d4e5f60401'),
    ('40000000-0000-0000-0000-000000000002', 'IECEP-2026-0402', 'Erika Mae Ramos', 'Erika Mae', 'Ramos', 'erika.pnc@gmail.com', '09204440002', '4d5e6f7a-8b9c-0123-def4-567890123456', 'BS Electronics Engineering', '3rd Year', '2023-04002', 'student', 'active', 'paid', 'a1b2c3d4e5f60402'),

    -- 5. PUP - Santa Rosa Campus (AECES)
    ('60000000-0000-0000-0000-000000000001', 'IECEP-2026-0601', 'John Paul Castro', 'John Paul', 'Castro', 'jp.castro@pup.edu.ph', '09226660001', 'c3d4e5f6-a7b8-9012-cdef-123456789012', 'BS Electronics Engineering', '2nd Year', '2024-06001', 'student', 'active', 'paid', 'a1b2c3d4e5f60601'),
    ('60000000-0000-0000-0000-000000000002', 'IECEP-2026-0602', 'Nicole Mendoza', 'Nicole', 'Mendoza', 'nicole.castro@pup.edu.ph', '09226660002', 'c3d4e5f6-a7b8-9012-cdef-123456789012', 'BS Electronics Engineering', '4th Year', '2022-06002', 'student', 'active', 'paid', 'a1b2c3d4e5f60602'),

    -- 6. UPHSL – Biñan Campus (PIECES)
    ('70000000-0000-0000-0000-000000000001', 'IECEP-2026-0701', 'Joshua Garcia', 'Joshua', 'Garcia', 'joshua.uphsl@gmail.com', '09237770001', 'e5f6a7b8-c9d0-1234-ef12-345678901234', 'BS Electronics Engineering', '3rd Year', '2023-07001', 'student', 'active', 'paid', 'a1b2c3d4e5f60701'),

    -- 7. UPHSD - Calamba Campus (ECESS - UPHSD)
    ('80000000-0000-0000-0000-000000000001', 'IECEP-2026-0801', 'Marielle Cruz', 'Marielle', 'Cruz', 'marielle.uphsd@gmail.com', '09248880001', 'd4e5f6a7-b8c9-0123-def1-234567890123', 'BS Electronics Engineering', '1st Year', '2025-08001', 'student', 'active', 'paid', 'a1b2c3d4e5f60801'),

    -- 8. LSPU - Santa Cruz Campus (ECESS - LSPU SCC)
    ('10000000-0000-0000-0000-000000000001', 'IECEP-2026-0101', 'Juan Dela Cruz', 'Juan', 'Dela Cruz', 'juan.scc1@lspu.edu.ph', '09171110001', '1fe48809-8ac6-4428-a6f1-3025cc47f5bb', 'BS Electronics Engineering', '1st Year', '2025-01001', 'student', 'active', 'paid', 'a1b2c3d4e5f60101'),
    ('10000000-0000-0000-0000-000000000002', 'IECEP-2026-0102', 'Maria Santos', 'Maria', 'Santos', 'maria.scc2@lspu.edu.ph', '09171110002', '1fe48809-8ac6-4428-a6f1-3025cc47f5bb', 'BS Electronics Engineering', '2nd Year', '2024-01002', 'student', 'active', 'paid', 'a1b2c3d4e5f60102'),
    ('10000000-0000-0000-0000-000000000003', 'IECEP-2026-0103', 'Rashed Dizon', 'Rashed', 'Dizon', 'rasheddizon7@gmail.com', '09191234567', '1fe48809-8ac6-4428-a6f1-3025cc47f5bb', 'BS Electronics Engineering', '3rd Year', '2022-00123', 'student', 'active', 'paid', 'a1b2c3d4e5f60103'),
    ('10000000-0000-0000-0000-000000000004', 'IECEP-2026-0104', 'Carlo Mendoza', 'Carlo', 'Mendoza', 'carlo.scc4@lspu.edu.ph', '09171110004', '1fe48809-8ac6-4428-a6f1-3025cc47f5bb', 'BS Electronics Engineering', '4th Year', '2022-01004', 'student', 'active', 'paid', 'a1b2c3d4e5f60104')
ON CONFLICT (email) DO UPDATE SET
    full_name = EXCLUDED.full_name,
    membership_id = EXCLUDED.membership_id,
    institution_id = EXCLUDED.institution_id,
    year_level = EXCLUDED.year_level,
    course = EXCLUDED.course,
    student_number = EXCLUDED.student_number;

INSERT INTO member_id_counter (year, last_number)
VALUES (2026, 14)
ON CONFLICT (year) DO UPDATE SET last_number = GREATEST(member_id_counter.last_number, EXCLUDED.last_number);

-- SEED DATA: INITIAL ATTENDANCE & BLOCKCHAIN LOG
INSERT INTO event_attendees (event_id, member_id, status, check_in_time)
VALUES
    ('2f2f99ce-98e1-49f6-8949-760687189aa6', '10000000-0000-0000-0000-000000000001', 'attended', NOW() - INTERVAL '45 minutes'),
    ('2f2f99ce-98e1-49f6-8949-760687189aa6', '10000000-0000-0000-0000-000000000003', 'attended', NOW() - INTERVAL '30 minutes')
ON CONFLICT (event_id, member_id) DO NOTHING;

INSERT INTO blockchain_records (block_index, entity_type, entity_id, transaction_hash, record_hash, data_json, confirmed)
VALUES
    (1, 'event_attendance', '10000000-0000-0000-0000-000000000001', 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f01234', '{"event": "IECEP-LSC Regional Technical Summit 2026", "student": "Juan Dela Cruz", "membership_id": "IECEP-2026-0101"}', true)
ON CONFLICT (id) DO NOTHING;

-- SEED DATA: SYSTEM SETTINGS & FEES
INSERT INTO fee_brackets (bracket_name, min_members, max_members, fee, is_active)
VALUES
    ('Small', 1, 50, 1500.00, true),
    ('Medium', 51, 100, 2000.00, true),
    ('Large', 101, 150, 2500.00, true),
    ('Enterprise', 151, 999999, 3000.00, true)
ON CONFLICT (bracket_name) DO UPDATE SET fee = EXCLUDED.fee;

INSERT INTO system_settings (key, value, description)
VALUES
    ('operational_fee', '800.00', 'Annual organization operational fee per Board Resolution No. 021-2024'),
    ('facebook_page_url', 'https://www.facebook.com/IECEPLSC', 'Official IECEP-LSC Facebook URL')
ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value;

-- =====================================================================
-- ROW LEVEL SECURITY (RLS) & ANONYMOUS/AUTHENTICATED ACCESS POLICIES
-- =====================================================================
ALTER TABLE institutions ENABLE ROW LEVEL SECURITY;
ALTER TABLE user_profiles ENABLE ROW LEVEL SECURITY;
ALTER TABLE members ENABLE ROW LEVEL SECURITY;
ALTER TABLE events ENABLE ROW LEVEL SECURITY;
ALTER TABLE event_attendees ENABLE ROW LEVEL SECURITY;
ALTER TABLE blockchain_records ENABLE ROW LEVEL SECURITY;
ALTER TABLE transactions ENABLE ROW LEVEL SECURITY;
ALTER TABLE pending_affiliations ENABLE ROW LEVEL SECURITY;
ALTER TABLE verification_codes ENABLE ROW LEVEL SECURITY;
ALTER TABLE merch_items ENABLE ROW LEVEL SECURITY;
ALTER TABLE merch_orders ENABLE ROW LEVEL SECURITY;
ALTER TABLE featured_cards ENABLE ROW LEVEL SECURITY;
ALTER TABLE announcements ENABLE ROW LEVEL SECURITY;
ALTER TABLE notifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE system_settings ENABLE ROW LEVEL SECURITY;
ALTER TABLE fee_brackets ENABLE ROW LEVEL SECURITY;

-- Grant Full Public & Service Role Access for PostgREST Client
DO $$ 
DECLARE
    tbl text;
BEGIN
    FOR tbl IN 
        SELECT tablename FROM pg_tables WHERE schemaname = 'public'
    LOOP
        EXECUTE format('DROP POLICY IF EXISTS "Public access on %I" ON %I;', tbl, tbl);
        EXECUTE format('CREATE POLICY "Public access on %I" ON %I FOR ALL TO public USING (true) WITH CHECK (true);', tbl, tbl);
    END LOOP;
END $$;

-- =====================================================================
-- REALTIME WEB-SOCKET SUBSCRIPTIONS
-- =====================================================================
BEGIN;
    DROP PUBLICATION IF EXISTS supabase_realtime CASCADE;
    CREATE PUBLICATION supabase_realtime FOR TABLE
        notifications,
        announcements,
        events,
        event_attendees,
        transactions,
        members,
        institutions,
        pending_affiliations,
        merch_orders;
COMMIT;
