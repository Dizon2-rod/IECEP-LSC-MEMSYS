-- =====================================================
-- IECEP-LSC MEMSYS Unified PostgreSQL Schema
-- Supabase-ready, idempotent, and compatible with auth.users
-- =====================================================

CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- =====================================================
-- TRIGGERS AND FUNCTIONS
-- =====================================================
CREATE OR REPLACE FUNCTION handle_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' OR TG_OP = 'UPDATE' THEN
        NEW.updated_at = NOW();
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- =====================================================
-- CORE ENTITIES
-- =====================================================
CREATE TABLE IF NOT EXISTS institutions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    acronym TEXT,
    type TEXT NOT NULL CHECK (type IN ('university', 'college', 'institute', 'school', 'company', 'organization')),
    address TEXT,
    city TEXT,
    province TEXT,
    region TEXT,
    country TEXT DEFAULT 'Philippines',
    contact_person TEXT,
    contact_email TEXT,
    contact_phone TEXT,
    website TEXT,
    status TEXT DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'pending', 'suspended')),
    affiliation_fee_paid BOOLEAN DEFAULT false,
    compliance_status TEXT CHECK (compliance_status IN ('compliant', 'at_risk', 'non_compliant')),
    membership_count INTEGER DEFAULT 0,
    established_year INTEGER,
    accreditation_status TEXT,
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'institutions')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'institutions' AND column_name = 'status') THEN
        ALTER TABLE institutions ADD COLUMN status TEXT DEFAULT 'active';
        ALTER TABLE institutions ADD CONSTRAINT chk_institutions_status CHECK (status IN ('active', 'inactive', 'pending', 'suspended'));
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_institutions_status ON institutions(status);
CREATE INDEX IF NOT EXISTS idx_institutions_name ON institutions(name);

-- =====================================================
-- SAFE COLUMN MIGRATIONS
-- =====================================================
DO $$
BEGIN
    -- Institutions status column
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'institutions')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'institutions' AND column_name = 'status') THEN
        ALTER TABLE institutions ADD COLUMN status TEXT DEFAULT 'active';
        ALTER TABLE institutions ADD CONSTRAINT chk_institutions_status CHECK (status IN ('active', 'inactive', 'pending', 'suspended'));
    END IF;
END $$;

DO $$
BEGIN
    -- Institutions timestamp columns
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'institutions') THEN
        IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'institutions' AND column_name = 'created_at') THEN
            ALTER TABLE institutions ADD COLUMN created_at TIMESTAMPTZ DEFAULT NOW();
        END IF;
        IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'institutions' AND column_name = 'updated_at') THEN
            ALTER TABLE institutions ADD COLUMN updated_at TIMESTAMPTZ DEFAULT NOW();
        END IF;
    END IF;
END $$;

DROP TRIGGER IF EXISTS update_institutions_updated_at ON institutions;
CREATE TRIGGER update_institutions_updated_at
    BEFORE UPDATE ON institutions
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

INSERT INTO institutions (id, email, name, address, status, affiliation_fee_paid, created_at, updated_at)
VALUES (
    '00000000-0000-0000-0000-000000000001',
    'executive@iecep-lsc.org',
    'IECEP-LSC Executive Council',
    'Laguna, Philippines',
    'active',
    true,
    NOW(),
    NOW()
)
ON CONFLICT (id) DO NOTHING;

CREATE TABLE IF NOT EXISTS affiliated_schools (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL UNIQUE,
    facebook_url TEXT,
    member_count INTEGER DEFAULT 0,
    status TEXT DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'pending')),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'affiliated_schools')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'affiliated_schools' AND column_name = 'status') THEN
        ALTER TABLE affiliated_schools ADD COLUMN status TEXT DEFAULT 'active';
        ALTER TABLE affiliated_schools ADD CONSTRAINT chk_affiliated_schools_status CHECK (status IN ('active', 'inactive', 'pending'));
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_affiliated_schools_status ON affiliated_schools(status);
CREATE INDEX IF NOT EXISTS idx_affiliated_schools_name ON affiliated_schools(name);

DROP TRIGGER IF EXISTS update_affiliated_schools_updated_at ON affiliated_schools;
CREATE TRIGGER update_affiliated_schools_updated_at
    BEFORE UPDATE ON affiliated_schools
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

CREATE TABLE IF NOT EXISTS user_profiles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE UNIQUE,
    institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL,
    role TEXT NOT NULL CHECK (role IN (
        'eb_president', 'eb_vp_internal', 'eb_vp_external', 'eb_vp_academic',
        'eb_secretary_general', 'eb_assistant_secretary', 'eb_treasurer', 'eb_auditor',
        'eb_pro_1', 'eb_pro_2',
        'committee_creatives', 'committee_documentation', 'committee_logistics',
        'committee_marketing', 'committee_registration', 'committee_technical',
        'school_officer', 'member'
    )),
    full_name TEXT,
    school_name TEXT,
    contact_phone TEXT,
    address TEXT,
    membership_status TEXT DEFAULT 'active' CHECK (membership_status IN ('active', 'inactive', 'suspended', 'pending')),
    membership_type TEXT DEFAULT 'regular' CHECK (membership_type IN ('regular', 'student', 'lifetime')),
    force_password_change BOOLEAN DEFAULT true,
    profile_data JSONB DEFAULT '{}',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    last_login TIMESTAMPTZ
);

ALTER TABLE IF EXISTS user_profiles ADD COLUMN IF NOT EXISTS institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL;
ALTER TABLE IF EXISTS user_profiles ADD COLUMN IF NOT EXISTS user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE UNIQUE;
ALTER TABLE IF EXISTS user_profiles ADD COLUMN IF NOT EXISTS school_name TEXT;
ALTER TABLE IF EXISTS user_profiles ADD COLUMN IF NOT EXISTS contact_phone TEXT;
ALTER TABLE IF EXISTS user_profiles ADD COLUMN IF NOT EXISTS address TEXT;

CREATE INDEX IF NOT EXISTS idx_user_profiles_user_id ON user_profiles(user_id);
CREATE INDEX IF NOT EXISTS idx_user_profiles_role ON user_profiles(role);
CREATE INDEX IF NOT EXISTS idx_user_profiles_institution ON user_profiles(institution_id);

DROP TRIGGER IF EXISTS update_user_profiles_updated_at ON user_profiles;
CREATE TRIGGER update_user_profiles_updated_at
    BEFORE UPDATE ON user_profiles
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

-- =====================================================
-- MEMBERSHIP & FINANCE
-- =====================================================
CREATE TABLE IF NOT EXISTS members (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE,
    user_id UUID REFERENCES auth.users(id) ON DELETE SET NULL UNIQUE,
    full_name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    membership_id TEXT UNIQUE,
    member_type TEXT CHECK (member_type IN ('new', 'returning', 'honorary')),
    payment_status BOOLEAN DEFAULT false,
    digital_id_url TEXT,
    qr_code TEXT,
    year_level TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE;
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS user_id UUID REFERENCES auth.users(id) ON DELETE SET NULL UNIQUE;
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS membership_id TEXT UNIQUE;

CREATE INDEX IF NOT EXISTS idx_members_institution ON members(institution_id);
CREATE INDEX IF NOT EXISTS idx_members_user_id ON members(user_id);
CREATE INDEX IF NOT EXISTS idx_members_email ON members(email);
CREATE INDEX IF NOT EXISTS idx_members_membership_id ON members(membership_id);

DROP TRIGGER IF EXISTS update_members_updated_at ON members;
CREATE TRIGGER update_members_updated_at
    BEFORE UPDATE ON members
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

CREATE TABLE IF NOT EXISTS member_id_counter (
    id SERIAL PRIMARY KEY,
    year INTEGER NOT NULL UNIQUE,
    last_number INTEGER NOT NULL DEFAULT 0
);

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_tables WHERE schemaname = current_schema() AND tablename = 'member_id_counter') THEN
        CREATE TABLE member_id_counter (
            id SERIAL PRIMARY KEY,
            year INTEGER NOT NULL UNIQUE,
            last_number INTEGER NOT NULL DEFAULT 0
        );
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'member_id_counter' AND column_name = 'year') THEN
        ALTER TABLE member_id_counter ADD COLUMN year INTEGER;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'member_id_counter' AND column_name = 'last_number') THEN
        ALTER TABLE member_id_counter ADD COLUMN last_number INTEGER NOT NULL DEFAULT 0;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'member_id_counter' AND indexname = 'idx_member_id_counter_year') THEN
        CREATE UNIQUE INDEX idx_member_id_counter_year ON member_id_counter(year);
    END IF;
END $$;

CREATE TABLE IF NOT EXISTS member_upload_batches (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE,
    file_name TEXT,
    status TEXT DEFAULT 'pending_approval' CHECK (status IN ('pending_approval', 'approved_payment_pending', 'fully_paid')),
    uploaded_at TIMESTAMPTZ DEFAULT NOW(),
    approved_at TIMESTAMPTZ
);

ALTER TABLE IF EXISTS member_upload_batches ADD COLUMN IF NOT EXISTS institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE;

CREATE INDEX IF NOT EXISTS idx_member_upload_batches_institution ON member_upload_batches(institution_id);
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'member_upload_batches')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'member_upload_batches' AND column_name = 'status') THEN
        ALTER TABLE member_upload_batches ADD COLUMN status TEXT DEFAULT 'pending_approval';
        ALTER TABLE member_upload_batches ADD CONSTRAINT chk_member_upload_batches_status CHECK (status IN ('pending_approval', 'approved_payment_pending', 'fully_paid'));
    END IF;
END $$;
CREATE INDEX IF NOT EXISTS idx_member_upload_batches_status ON member_upload_batches(status);

CREATE TABLE IF NOT EXISTS pending_members (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    batch_id UUID REFERENCES member_upload_batches(id) ON DELETE CASCADE,
    full_name TEXT,
    email TEXT,
    member_type TEXT,
    year_level TEXT,
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'approved_payment_pending', 'paid_account_created'))
);

ALTER TABLE IF EXISTS pending_members ADD COLUMN IF NOT EXISTS batch_id UUID REFERENCES member_upload_batches(id) ON DELETE CASCADE;

CREATE INDEX IF NOT EXISTS idx_pending_members_batch ON pending_members(batch_id);
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'pending_members')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'pending_members' AND column_name = 'status') THEN
        ALTER TABLE pending_members ADD COLUMN status TEXT DEFAULT 'pending';
        ALTER TABLE pending_members ADD CONSTRAINT chk_pending_members_status CHECK (status IN ('pending', 'approved_payment_pending', 'paid_account_created'));
    END IF;
END $$;
CREATE INDEX IF NOT EXISTS idx_pending_members_status ON pending_members(status);

CREATE TABLE IF NOT EXISTS fee_brackets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL UNIQUE,
    description TEXT,
    min_members INTEGER NOT NULL DEFAULT 0,
    max_members INTEGER,
    annual_fee DECIMAL(10,2) NOT NULL,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

INSERT INTO fee_brackets (name, description, min_members, max_members, annual_fee, is_active, created_at, updated_at) VALUES
    ('Small Institution', '1-50 members', 1, 50, 1200.00, true, NOW(), NOW()),
    ('Medium Institution', '51-200 members', 51, 200, 2400.00, true, NOW(), NOW()),
    ('Large Institution', '201-500 members', 201, 500, 4200.00, true, NOW(), NOW()),
    ('Enterprise', '501+ members', 501, NULL, 6800.00, true, NOW(), NOW())
ON CONFLICT (name) DO NOTHING;

ALTER TABLE IF EXISTS fee_brackets ADD COLUMN IF NOT EXISTS min_members INTEGER DEFAULT 0;
ALTER TABLE IF EXISTS fee_brackets ADD COLUMN IF NOT EXISTS max_members INTEGER;
ALTER TABLE IF EXISTS fee_brackets ADD COLUMN IF NOT EXISTS annual_fee DECIMAL(10,2);

CREATE INDEX IF NOT EXISTS idx_fee_brackets_min_max ON fee_brackets(min_members, max_members);

DROP TRIGGER IF EXISTS update_fee_brackets_updated_at ON fee_brackets;
CREATE TRIGGER update_fee_brackets_updated_at
    BEFORE UPDATE ON fee_brackets
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

CREATE OR REPLACE FUNCTION calculate_membership_fee(member_count INT)
RETURNS DECIMAL(10,2) AS $$
SELECT COALESCE(annual_fee, 0)
FROM fee_brackets
WHERE (min_members IS NULL OR member_count >= min_members)
  AND (max_members IS NULL OR member_count <= max_members)
ORDER BY COALESCE(min_members, -1) DESC
LIMIT 1;
$$ LANGUAGE sql STABLE;

CREATE TABLE IF NOT EXISTS transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    receipt_id TEXT UNIQUE,
    user_id UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    member_id UUID REFERENCES members(id) ON DELETE SET NULL,
    institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency TEXT DEFAULT 'PHP',
    type TEXT NOT NULL CHECK (type IN ('membership_fee', 'event_fee', 'donation', 'refund', 'penalty')),
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'paid', 'failed', 'refunded', 'cancelled')),
    payment_method TEXT CHECK (payment_method IN ('bank_transfer', 'credit_card', 'debit_card', 'online_payment', 'cash', 'check')),
    reference_number TEXT UNIQUE,
    transaction_date TIMESTAMPTZ DEFAULT NOW() NOT NULL,
    due_date TIMESTAMPTZ,
    paid_at TIMESTAMPTZ,
    notes TEXT,
    receipt_url TEXT,
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE IF EXISTS transactions ADD COLUMN IF NOT EXISTS institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL;
ALTER TABLE IF EXISTS transactions ADD COLUMN IF NOT EXISTS user_id UUID REFERENCES auth.users(id) ON DELETE SET NULL;
ALTER TABLE IF EXISTS transactions ADD COLUMN IF NOT EXISTS member_id UUID REFERENCES members(id) ON DELETE SET NULL;
ALTER TABLE IF EXISTS transactions ADD COLUMN IF NOT EXISTS transaction_date TIMESTAMPTZ DEFAULT NOW() NOT NULL;

CREATE INDEX IF NOT EXISTS idx_transactions_user ON transactions(user_id);
CREATE INDEX IF NOT EXISTS idx_transactions_member ON transactions(member_id);
CREATE INDEX IF NOT EXISTS idx_transactions_institution ON transactions(institution_id);
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'transactions')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'transactions' AND column_name = 'status') THEN
        ALTER TABLE transactions ADD COLUMN status TEXT DEFAULT 'pending';
        ALTER TABLE transactions ADD CONSTRAINT chk_transactions_status CHECK (status IN ('pending', 'paid', 'failed', 'refunded', 'cancelled'));
    END IF;
END $$;
CREATE INDEX IF NOT EXISTS idx_transactions_status ON transactions(status);
CREATE INDEX IF NOT EXISTS idx_transactions_date ON transactions(transaction_date);

DROP TRIGGER IF EXISTS update_transactions_updated_at ON transactions;
CREATE TRIGGER update_transactions_updated_at
    BEFORE UPDATE ON transactions
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

-- =====================================================
-- AFFILIATION & APPROVALS
-- =====================================================
CREATE TABLE IF NOT EXISTS pending_affiliations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE,
    applicant_id UUID REFERENCES user_profiles(id) ON DELETE SET NULL,
    application_type TEXT NOT NULL CHECK (application_type IN ('new_membership', 'renewal', 'upgrade', 'transfer')),
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'under_review', 'approved', 'rejected', 'requires_revision')),
    submitted_at TIMESTAMPTZ DEFAULT NOW(),
    reviewed_at TIMESTAMPTZ,
    approved_at TIMESTAMPTZ,
    reviewed_by UUID REFERENCES user_profiles(id),
    approval_notes TEXT,
    documents JSONB DEFAULT '[]',
    requirements_checklist JSONB DEFAULT '{}',
    rejection_reason TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE IF EXISTS pending_affiliations ADD COLUMN IF NOT EXISTS institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE;
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'pending_affiliations')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'pending_affiliations' AND column_name = 'status') THEN
        ALTER TABLE pending_affiliations ADD COLUMN status TEXT DEFAULT 'pending';
        ALTER TABLE pending_affiliations ADD CONSTRAINT chk_pending_affiliations_status CHECK (status IN ('pending', 'under_review', 'approved', 'rejected', 'requires_revision'));
    END IF;
END $$;
CREATE INDEX IF NOT EXISTS idx_pending_affiliations_status ON pending_affiliations(status);
CREATE INDEX IF NOT EXISTS idx_pending_affiliations_institution ON pending_affiliations(institution_id);

DROP TRIGGER IF EXISTS update_pending_affiliations_updated_at ON pending_affiliations;
CREATE TRIGGER update_pending_affiliations_updated_at
    BEFORE UPDATE ON pending_affiliations
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

CREATE TABLE IF NOT EXISTS affiliation_approvals (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    affiliation_id UUID REFERENCES pending_affiliations(id) ON DELETE CASCADE NOT NULL,
    approver_id UUID REFERENCES user_profiles(id) ON DELETE SET NULL NOT NULL,
    approval_level TEXT NOT NULL CHECK (approval_level IN ('initial_review', 'board_review', 'final_approval')),
    status TEXT NOT NULL CHECK (status IN ('pending', 'approved', 'rejected', 'conditional')),
    comments TEXT,
    approved_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_affiliation_approvals_affiliation ON affiliation_approvals(affiliation_id);
CREATE INDEX IF NOT EXISTS idx_affiliation_approvals_approver ON affiliation_approvals(approver_id);

-- =====================================================
-- EVENTS & ATTENDANCE
-- =====================================================
CREATE TABLE IF NOT EXISTS events (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL,
    title TEXT NOT NULL,
    description TEXT,
    event_type TEXT NOT NULL CHECK (event_type IN ('conference', 'seminar', 'workshop', 'meeting', 'training', 'social', 'ceremony')),
    start_date TIMESTAMPTZ NOT NULL,
    end_date TIMESTAMPTZ NOT NULL,
    venue TEXT,
    address TEXT,
    city TEXT,
    max_attendees INTEGER,
    registration_deadline TIMESTAMPTZ,
    registration_fee DECIMAL(10,2) DEFAULT 0,
    status TEXT DEFAULT 'upcoming' CHECK (status IN ('draft', 'upcoming', 'ongoing', 'completed', 'cancelled')),
    organizer_id UUID REFERENCES user_profiles(id),
    is_public BOOLEAN DEFAULT true,
    requires_registration BOOLEAN DEFAULT false,
    agenda JSONB DEFAULT '[]',
    resources JSONB DEFAULT '[]',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE IF EXISTS events ADD COLUMN IF NOT EXISTS institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL;
ALTER TABLE IF EXISTS events ADD COLUMN IF NOT EXISTS start_date TIMESTAMPTZ NOT NULL DEFAULT NOW();
ALTER TABLE IF EXISTS events ADD COLUMN IF NOT EXISTS end_date TIMESTAMPTZ NOT NULL DEFAULT NOW();
ALTER TABLE IF EXISTS events ADD COLUMN IF NOT EXISTS title TEXT NOT NULL DEFAULT '';

CREATE INDEX IF NOT EXISTS idx_events_institution ON events(institution_id);
CREATE INDEX IF NOT EXISTS idx_events_start_date ON events(start_date);
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'events')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'events' AND column_name = 'status') THEN
        ALTER TABLE events ADD COLUMN status TEXT DEFAULT 'upcoming';
        ALTER TABLE events ADD CONSTRAINT chk_events_status CHECK (status IN ('draft', 'upcoming', 'ongoing', 'completed', 'cancelled'));
    END IF;
END $$;
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'events')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'events' AND column_name = 'is_public') THEN
        ALTER TABLE events ADD COLUMN is_public BOOLEAN DEFAULT true;
    END IF;
END $$;
CREATE INDEX IF NOT EXISTS idx_events_status ON events(status);

DROP TRIGGER IF EXISTS update_events_updated_at ON events;
CREATE TRIGGER update_events_updated_at
    BEFORE UPDATE ON events
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

CREATE TABLE IF NOT EXISTS event_registrations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_id UUID REFERENCES events(id) ON DELETE CASCADE NOT NULL,
    user_id UUID REFERENCES user_profiles(id) ON DELETE CASCADE NOT NULL,
    registration_date TIMESTAMPTZ DEFAULT NOW() NOT NULL,
    status TEXT DEFAULT 'registered' CHECK (status IN ('registered', 'confirmed', 'attended', 'cancelled', 'waitlist')),
    payment_status TEXT DEFAULT 'pending' CHECK (payment_status IN ('pending', 'paid', 'refunded')),
    special_requirements TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(event_id, user_id)
);

ALTER TABLE IF EXISTS event_registrations ADD COLUMN IF NOT EXISTS user_id UUID REFERENCES user_profiles(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_event_registrations_event ON event_registrations(event_id);
CREATE INDEX IF NOT EXISTS idx_event_registrations_user ON event_registrations(user_id);

DROP TRIGGER IF EXISTS update_event_registrations_updated_at ON event_registrations;
CREATE TRIGGER update_event_registrations_updated_at
    BEFORE UPDATE ON event_registrations
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

CREATE TABLE IF NOT EXISTS attendance (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_id UUID REFERENCES events(id) ON DELETE CASCADE NOT NULL,
    user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE NOT NULL,
    member_id UUID REFERENCES members(id) ON DELETE CASCADE NOT NULL,
    check_in_time TIMESTAMPTZ DEFAULT NOW() NOT NULL,
    check_out_time TIMESTAMPTZ,
    attendance_status TEXT DEFAULT 'present' CHECK (attendance_status IN ('present', 'late', 'absent', 'excused')),
    verified_by UUID REFERENCES user_profiles(id),
    notes TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW() NOT NULL,
    UNIQUE(event_id, member_id)
);

ALTER TABLE IF EXISTS attendance ADD COLUMN IF NOT EXISTS user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_attendance_event ON attendance(event_id);
CREATE INDEX IF NOT EXISTS idx_attendance_user ON attendance(user_id);

-- =====================================================
-- DIGITAL IDENTITY & BLOCKCHAIN
-- =====================================================
CREATE TABLE IF NOT EXISTS blockchain_records (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    record_type TEXT NOT NULL CHECK (record_type IN ('transaction','membership_change','compliance_attendance','document_hash','affiliation_action','digital_id')),
    reference_id TEXT NOT NULL,
    data_hash TEXT NOT NULL,
    previous_hash TEXT,
    data_json JSONB,
    merkle_root TEXT,
    signature TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    metadata JSONB DEFAULT '{}'
);

ALTER TABLE IF EXISTS blockchain_records ADD COLUMN IF NOT EXISTS record_type TEXT DEFAULT '';
ALTER TABLE IF EXISTS blockchain_records ADD COLUMN IF NOT EXISTS reference_id TEXT DEFAULT '';
ALTER TABLE IF EXISTS blockchain_records ADD COLUMN IF NOT EXISTS data_hash TEXT DEFAULT '';
ALTER TABLE IF EXISTS blockchain_records ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_blockchain_records_chain ON blockchain_records(record_type, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_blockchain_records_reference ON blockchain_records(reference_id);

CREATE TABLE IF NOT EXISTS digital_certificates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES user_profiles(id) ON DELETE CASCADE NOT NULL,
    certificate_type TEXT NOT NULL CHECK (certificate_type IN ('membership', 'achievement', 'completion', 'recognition')),
    title TEXT NOT NULL,
    description TEXT,
    issued_by UUID REFERENCES user_profiles(id) NOT NULL,
    issued_at TIMESTAMPTZ DEFAULT NOW() NOT NULL,
    expires_at TIMESTAMPTZ,
    certificate_number TEXT UNIQUE NOT NULL,
    blockchain_record_id UUID REFERENCES blockchain_records(id),
    qr_code_url TEXT,
    pdf_url TEXT,
    status TEXT DEFAULT 'active' CHECK (status IN ('active', 'revoked', 'expired')),
    revocation_reason TEXT,
    revoked_at TIMESTAMPTZ,
    revoked_by UUID REFERENCES user_profiles(id),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE IF EXISTS digital_certificates ADD COLUMN IF NOT EXISTS user_id UUID REFERENCES user_profiles(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_digital_certificates_user ON digital_certificates(user_id);
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'digital_certificates')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'digital_certificates' AND column_name = 'status') THEN
        ALTER TABLE digital_certificates ADD COLUMN status TEXT DEFAULT 'active';
        ALTER TABLE digital_certificates ADD CONSTRAINT chk_digital_certificates_status CHECK (status IN ('active', 'revoked', 'expired'));
    END IF;
END $$;
CREATE INDEX IF NOT EXISTS idx_digital_certificates_status ON digital_certificates(status);

DROP TRIGGER IF EXISTS update_digital_certificates_updated_at ON digital_certificates;
CREATE TRIGGER update_digital_certificates_updated_at
    BEFORE UPDATE ON digital_certificates
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

-- =====================================================
-- NOTIFICATIONS & COMMUNICATIONS
-- =====================================================
CREATE TABLE IF NOT EXISTS notifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES user_profiles(id) ON DELETE CASCADE NOT NULL,
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    type TEXT NOT NULL CHECK (type IN ('system', 'payment', 'event', 'affiliation', 'achievement', 'reminder', 'announcement')),
    priority TEXT DEFAULT 'normal' CHECK (priority IN ('low', 'normal', 'high', 'urgent')),
    read BOOLEAN DEFAULT false,
    read_at TIMESTAMPTZ,
    action_url TEXT,
    action_text TEXT,
    expires_at TIMESTAMPTZ,
    sent_by UUID REFERENCES user_profiles(id),
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE IF EXISTS notifications ADD COLUMN IF NOT EXISTS user_id UUID REFERENCES user_profiles(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_read ON notifications(read);
CREATE INDEX IF NOT EXISTS idx_notifications_type ON notifications(type);
CREATE INDEX IF NOT EXISTS idx_notifications_created_at ON notifications(created_at);

DROP TRIGGER IF EXISTS update_notifications_updated_at ON notifications;
CREATE TRIGGER update_notifications_updated_at
    BEFORE UPDATE ON notifications
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE,
    endpoint TEXT UNIQUE NOT NULL,
    keys JSONB,
    browser TEXT,
    platform TEXT,
    metadata JSONB DEFAULT '{}',
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    last_active TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE IF EXISTS push_subscriptions ADD COLUMN IF NOT EXISTS user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_push_subscriptions_user ON push_subscriptions(user_id);
CREATE INDEX IF NOT EXISTS idx_push_subscriptions_active ON push_subscriptions(active);

CREATE TABLE IF NOT EXISTS notification_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    body TEXT NOT NULL,
    data JSONB DEFAULT '{}',
    sent_count INTEGER DEFAULT 0,
    failed_count INTEGER DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE IF EXISTS notification_logs ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();

CREATE TABLE IF NOT EXISTS notification_delivery_log (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    notification_id UUID REFERENCES notification_logs(id) ON DELETE SET NULL,
    subscription_id UUID REFERENCES push_subscriptions(id) ON DELETE SET NULL,
    endpoint TEXT NOT NULL,
    payload JSONB,
    keys JSONB,
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'sent', 'failed')),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_notification_logs_created_at ON notification_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_notification_delivery_log_notification ON notification_delivery_log(notification_id);

CREATE TABLE IF NOT EXISTS email_verifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email TEXT NOT NULL,
    code TEXT NOT NULL,
    verification_type TEXT NOT NULL CHECK (verification_type IN ('email_verification', 'password_reset', 'email_change')),
    expires_at TIMESTAMPTZ NOT NULL,
    used BOOLEAN DEFAULT false,
    used_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE IF EXISTS email_verifications ADD COLUMN IF NOT EXISTS email TEXT DEFAULT '';
ALTER TABLE IF EXISTS email_verifications ADD COLUMN IF NOT EXISTS code TEXT DEFAULT '';
ALTER TABLE IF EXISTS email_verifications ADD COLUMN IF NOT EXISTS expires_at TIMESTAMPTZ;

CREATE INDEX IF NOT EXISTS idx_email_verifications_email ON email_verifications(email);
CREATE INDEX IF NOT EXISTS idx_email_verifications_code ON email_verifications(code);
CREATE INDEX IF NOT EXISTS idx_email_verifications_expires_at ON email_verifications(expires_at);

-- =====================================================
-- COLLABORATION & COMMUNITY
-- =====================================================
CREATE TABLE IF NOT EXISTS collaboration_posts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    author_id UUID REFERENCES user_profiles(id) ON DELETE CASCADE NOT NULL,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    post_type TEXT DEFAULT 'discussion' CHECK (post_type IN ('discussion', 'question', 'announcement', 'resource', 'event', 'project')),
    category TEXT,
    tags TEXT[] DEFAULT '{}',
    status TEXT DEFAULT 'published' CHECK (status IN ('draft', 'published', 'archived', 'deleted')),
    is_pinned BOOLEAN DEFAULT false,
    is_featured BOOLEAN DEFAULT false,
    view_count INTEGER DEFAULT 0,
    like_count INTEGER DEFAULT 0,
    comment_count INTEGER DEFAULT 0,
    attachments JSONB DEFAULT '[]',
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE IF EXISTS collaboration_posts ADD COLUMN IF NOT EXISTS author_id UUID REFERENCES user_profiles(id) ON DELETE CASCADE;
ALTER TABLE IF EXISTS collaboration_posts ADD COLUMN IF NOT EXISTS post_type TEXT DEFAULT 'discussion' CHECK (post_type IN ('discussion', 'question', 'announcement', 'resource', 'event', 'project'));
ALTER TABLE IF EXISTS collaboration_posts ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();

CREATE TABLE IF NOT EXISTS collaboration_likes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    post_id UUID REFERENCES collaboration_posts(id) ON DELETE CASCADE NOT NULL,
    user_id UUID REFERENCES user_profiles(id) ON DELETE CASCADE NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(post_id, user_id)
);

CREATE TABLE IF NOT EXISTS collaboration_comments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    post_id UUID REFERENCES collaboration_posts(id) ON DELETE CASCADE NOT NULL,
    author_id UUID REFERENCES user_profiles(id) ON DELETE CASCADE NOT NULL,
    parent_comment_id UUID REFERENCES collaboration_comments(id) ON DELETE CASCADE,
    content TEXT NOT NULL,
    status TEXT DEFAULT 'published' CHECK (status IN ('published', 'deleted', 'spam')),
    like_count INTEGER DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_collaboration_posts_author ON collaboration_posts(author_id);
CREATE INDEX IF NOT EXISTS idx_collaboration_posts_type ON collaboration_posts(post_type);
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'collaboration_posts')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'collaboration_posts' AND column_name = 'status') THEN
        ALTER TABLE collaboration_posts ADD COLUMN status TEXT DEFAULT 'published';
        ALTER TABLE collaboration_posts ADD CONSTRAINT chk_collaboration_posts_status CHECK (status IN ('draft', 'published', 'archived', 'deleted'));
    END IF;
END $$;
CREATE INDEX IF NOT EXISTS idx_collaboration_posts_status ON collaboration_posts(status);
CREATE INDEX IF NOT EXISTS idx_collaboration_posts_created_at ON collaboration_posts(created_at);
CREATE INDEX IF NOT EXISTS idx_collaboration_likes_post ON collaboration_likes(post_id);
CREATE INDEX IF NOT EXISTS idx_collaboration_comments_post ON collaboration_comments(post_id);

DROP TRIGGER IF EXISTS update_collaboration_posts_updated_at ON collaboration_posts;
CREATE TRIGGER update_collaboration_posts_updated_at
    BEFORE UPDATE ON collaboration_posts
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

DROP TRIGGER IF EXISTS update_collaboration_comments_updated_at ON collaboration_comments;
CREATE TRIGGER update_collaboration_comments_updated_at
    BEFORE UPDATE ON collaboration_comments
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

CREATE TABLE IF NOT EXISTS partner_chapters (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL,
    acronym TEXT,
    type TEXT NOT NULL CHECK (type IN ('academic', 'industry', 'professional', 'international')),
    region TEXT,
    country TEXT DEFAULT 'Philippines',
    contact_person TEXT,
    contact_email TEXT,
    contact_phone TEXT,
    website TEXT,
    partnership_type TEXT NOT NULL CHECK (partnership_type IN ('strategic', 'academic', 'research', 'training', 'exchange')),
    partnership_status TEXT DEFAULT 'active' CHECK (partnership_status IN ('active', 'inactive', 'pending', 'terminated')),
    start_date DATE,
    end_date DATE,
    agreement_details TEXT,
    benefits JSONB DEFAULT '[]',
    activities JSONB DEFAULT '[]',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_partner_chapters_status ON partner_chapters(partnership_status);

DROP TRIGGER IF EXISTS update_partner_chapters_updated_at ON partner_chapters;
CREATE TRIGGER update_partner_chapters_updated_at
    BEFORE UPDATE ON partner_chapters
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

-- =====================================================
-- AWARDS & DISTINCTIONS
-- =====================================================
CREATE TABLE IF NOT EXISTS awards_distinctions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    recipient_id UUID REFERENCES user_profiles(id) ON DELETE CASCADE NOT NULL,
    award_type TEXT NOT NULL CHECK (award_type IN ('academic_excellence', 'service', 'leadership', 'innovation', 'lifetime', 'special_recognition')),
    title TEXT NOT NULL,
    description TEXT,
    awarded_by UUID REFERENCES user_profiles(id) NOT NULL,
    awarded_at TIMESTAMPTZ DEFAULT NOW() NOT NULL,
    ceremony_date DATE,
    certificate_number TEXT UNIQUE,
    blockchain_record_id UUID REFERENCES blockchain_records(id),
    status TEXT DEFAULT 'active' CHECK (status IN ('active', 'revoked')),
    revocation_reason TEXT,
    revoked_at TIMESTAMPTZ,
    revoked_by UUID REFERENCES user_profiles(id),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE IF EXISTS awards_distinctions ADD COLUMN IF NOT EXISTS awarded_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE IF EXISTS awards_distinctions ADD COLUMN IF NOT EXISTS recipient_id UUID REFERENCES user_profiles(id) ON DELETE CASCADE;

CREATE INDEX IF NOT EXISTS idx_awards_distinctions_awarded_at ON awards_distinctions(awarded_at);
CREATE INDEX IF NOT EXISTS idx_awards_distinctions_recipient ON awards_distinctions(recipient_id);

DROP TRIGGER IF EXISTS update_awards_distinctions_updated_at ON awards_distinctions;
CREATE TRIGGER update_awards_distinctions_updated_at
    BEFORE UPDATE ON awards_distinctions
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

-- =====================================================
-- ANNOUNCEMENTS & READ RECEIPTS
-- =====================================================
CREATE TABLE IF NOT EXISTS announcements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    sent_by UUID REFERENCES auth.users(id),
    sent_at TIMESTAMPTZ DEFAULT NOW(),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE IF EXISTS announcements ADD COLUMN IF NOT EXISTS sent_by UUID REFERENCES auth.users(id);
ALTER TABLE IF EXISTS announcements ADD COLUMN IF NOT EXISTS sent_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_announcements_sent_by ON announcements(sent_by);
CREATE INDEX IF NOT EXISTS idx_announcements_sent_at ON announcements(sent_at);

DROP TRIGGER IF EXISTS update_announcements_updated_at ON announcements;
CREATE TRIGGER update_announcements_updated_at
    BEFORE UPDATE ON announcements
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

CREATE TABLE IF NOT EXISTS read_receipts (
    announcement_id UUID REFERENCES announcements(id) ON DELETE CASCADE,
    user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE,
    read_at TIMESTAMPTZ DEFAULT NOW(),
    PRIMARY KEY (announcement_id, user_id)
);

ALTER TABLE IF EXISTS read_receipts ADD COLUMN IF NOT EXISTS user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE;
CREATE INDEX IF NOT EXISTS idx_read_receipts_user ON read_receipts(user_id);

-- =====================================================
-- COMPLIANCE RECORDS
-- =====================================================
CREATE TABLE IF NOT EXISTS compliance_records (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE,
    period_start DATE,
    period_end DATE,
    total_members INT,
    attending_members INT,
    participation_rate DECIMAL(5,2),
    status TEXT CHECK (status IN ('compliant', 'at_risk', 'non_compliant')),
    calculated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE IF EXISTS compliance_records ADD COLUMN IF NOT EXISTS institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE;

CREATE INDEX IF NOT EXISTS idx_compliance_records_institution ON compliance_records(institution_id);
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'compliance_records')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'compliance_records' AND column_name = 'status') THEN
        ALTER TABLE compliance_records ADD COLUMN status TEXT;
        ALTER TABLE compliance_records ADD CONSTRAINT chk_compliance_records_status CHECK (status IN ('compliant', 'at_risk', 'non_compliant'));
    END IF;
END $$;
CREATE INDEX IF NOT EXISTS idx_compliance_records_status ON compliance_records(status);

-- =====================================================
-- CONTACT MESSAGES
-- =====================================================
CREATE TABLE IF NOT EXISTS contact_messages (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    message TEXT NOT NULL,
    status TEXT DEFAULT 'unread' CHECK (status IN ('unread', 'read', 'replied')),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE IF EXISTS contact_messages ADD COLUMN IF NOT EXISTS email TEXT NOT NULL DEFAULT '';
ALTER TABLE IF EXISTS contact_messages ADD COLUMN IF NOT EXISTS name TEXT NOT NULL DEFAULT '';

CREATE INDEX IF NOT EXISTS idx_contact_messages_email ON contact_messages(email);
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'contact_messages')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'contact_messages' AND column_name = 'status') THEN
        ALTER TABLE contact_messages ADD COLUMN status TEXT DEFAULT 'unread';
        ALTER TABLE contact_messages ADD CONSTRAINT chk_contact_messages_status CHECK (status IN ('unread', 'read', 'replied'));
    END IF;
END $$;
CREATE INDEX IF NOT EXISTS idx_contact_messages_status ON contact_messages(status);

-- =====================================================
-- COMMITTEE TASKS & AUDIT
-- =====================================================
CREATE TABLE IF NOT EXISTS committee_tasks (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    committee_name TEXT NOT NULL,
    title TEXT NOT NULL,
    description TEXT,
    assigned_to UUID REFERENCES auth.users(id),
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'in_progress', 'completed', 'cancelled')),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE IF EXISTS committee_tasks ADD COLUMN IF NOT EXISTS assigned_to UUID REFERENCES auth.users(id);

CREATE INDEX IF NOT EXISTS idx_committee_tasks_assigned_to ON committee_tasks(assigned_to);
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'committee_tasks')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'committee_tasks' AND column_name = 'status') THEN
        ALTER TABLE committee_tasks ADD COLUMN status TEXT DEFAULT 'pending';
        ALTER TABLE committee_tasks ADD CONSTRAINT chk_committee_tasks_status CHECK (status IN ('pending', 'in_progress', 'completed', 'cancelled'));
    END IF;
END $$;
CREATE INDEX IF NOT EXISTS idx_committee_tasks_status ON committee_tasks(status);

DROP TRIGGER IF EXISTS update_committee_tasks_updated_at ON committee_tasks;
CREATE TRIGGER update_committee_tasks_updated_at
    BEFORE UPDATE ON committee_tasks
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

CREATE TABLE IF NOT EXISTS audit_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES auth.users(id),
    action TEXT,
    details JSONB,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE IF EXISTS audit_logs ADD COLUMN IF NOT EXISTS user_id UUID REFERENCES auth.users(id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_user ON audit_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_created_at ON audit_logs(created_at);

-- =====================================================
-- CREATIVES & PUBLICATIONS
-- =====================================================
CREATE TABLE IF NOT EXISTS creatives_announcements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    date DATE NOT NULL,
    author TEXT DEFAULT 'Creatives Committee',
    status TEXT DEFAULT 'published' CHECK (status IN ('published', 'draft', 'archived')),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS creatives_graphics (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL,
    image TEXT NOT NULL,
    date DATE NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS creatives_publications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    file TEXT NOT NULL,
    size TEXT NOT NULL,
    date DATE NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS creatives_team (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL,
    role TEXT NOT NULL,
    email TEXT NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS creatives_features (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    image TEXT NOT NULL,
    link TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_creatives_announcements_date ON creatives_announcements(date);
CREATE INDEX IF NOT EXISTS idx_creatives_graphics_date ON creatives_graphics(date);
CREATE INDEX IF NOT EXISTS idx_creatives_publications_date ON creatives_publications(date);

ALTER TABLE IF EXISTS creatives_announcements ADD COLUMN IF NOT EXISTS date DATE DEFAULT NOW()::date;
ALTER TABLE IF EXISTS creatives_graphics ADD COLUMN IF NOT EXISTS date DATE DEFAULT NOW()::date;
ALTER TABLE IF EXISTS creatives_publications ADD COLUMN IF NOT EXISTS date DATE DEFAULT NOW()::date;

DROP TRIGGER IF EXISTS update_creatives_announcements_updated_at ON creatives_announcements;
CREATE TRIGGER update_creatives_announcements_updated_at
    BEFORE UPDATE ON creatives_announcements
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

DROP TRIGGER IF EXISTS update_creatives_graphics_updated_at ON creatives_graphics;
CREATE TRIGGER update_creatives_graphics_updated_at
    BEFORE UPDATE ON creatives_graphics
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

DROP TRIGGER IF EXISTS update_creatives_publications_updated_at ON creatives_publications;
CREATE TRIGGER update_creatives_publications_updated_at
    BEFORE UPDATE ON creatives_publications
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

DROP TRIGGER IF EXISTS update_creatives_team_updated_at ON creatives_team;
CREATE TRIGGER update_creatives_team_updated_at
    BEFORE UPDATE ON creatives_team
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

DROP TRIGGER IF EXISTS update_creatives_features_updated_at ON creatives_features;
CREATE TRIGGER update_creatives_features_updated_at
    BEFORE UPDATE ON creatives_features
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

-- =====================================================
-- SYSTEM SETTINGS
-- =====================================================
CREATE TABLE IF NOT EXISTS system_settings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    key TEXT UNIQUE NOT NULL,
    value TEXT NOT NULL,
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

INSERT INTO system_settings (key, value, updated_at) VALUES
    ('fee_new_member', '250', NOW()),
    ('fee_returning_member', '200', NOW()),
    ('fee_honorary_member', '300', NOW()),
    ('fee_affiliation', '500', NOW()),
    ('member_id_prefix', 'IECEP', NOW()),
    ('academic_year', '2025-2026', NOW()),
    ('compliance_threshold', '40', NOW()),
    ('at_risk_threshold', '20', NOW())
ON CONFLICT (key) DO NOTHING;

-- =====================================================
-- SAFE COLUMN MIGRATIONS
-- =====================================================
DO $$
BEGIN
    -- Affiliated schools status column
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'affiliated_schools')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'affiliated_schools' AND column_name = 'status') THEN
        ALTER TABLE affiliated_schools ADD COLUMN status TEXT DEFAULT 'active';
        ALTER TABLE affiliated_schools ADD CONSTRAINT chk_affiliated_schools_status CHECK (status IN ('active', 'inactive', 'pending'));
    END IF;
END $$;

DO $$
BEGIN
    -- Member upload batches status column
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'member_upload_batches')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'member_upload_batches' AND column_name = 'status') THEN
        ALTER TABLE member_upload_batches ADD COLUMN status TEXT DEFAULT 'pending_approval';
        ALTER TABLE member_upload_batches ADD CONSTRAINT chk_member_upload_batches_status CHECK (status IN ('pending_approval', 'approved_payment_pending', 'fully_paid'));
    END IF;
END $$;

DO $$
BEGIN
    -- Pending members status column
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'pending_members')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'pending_members' AND column_name = 'status') THEN
        ALTER TABLE pending_members ADD COLUMN status TEXT DEFAULT 'pending';
        ALTER TABLE pending_members ADD CONSTRAINT chk_pending_members_status CHECK (status IN ('pending', 'approved_payment_pending', 'paid_account_created'));
    END IF;
END $$;

DO $$
BEGIN
    -- Pending affiliations status column
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'pending_affiliations')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'pending_affiliations' AND column_name = 'status') THEN
        ALTER TABLE pending_affiliations ADD COLUMN status TEXT DEFAULT 'pending';
        ALTER TABLE pending_affiliations ADD CONSTRAINT chk_pending_affiliations_status CHECK (status IN ('pending', 'under_review', 'approved', 'rejected', 'requires_revision'));
    END IF;
END $$;

DO $$
BEGIN
    -- Collaboration posts status column
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'collaboration_posts')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'collaboration_posts' AND column_name = 'status') THEN
        ALTER TABLE collaboration_posts ADD COLUMN status TEXT DEFAULT 'published';
        ALTER TABLE collaboration_posts ADD CONSTRAINT chk_collaboration_posts_status CHECK (status IN ('draft', 'published', 'archived', 'deleted'));
    END IF;
END $$;

DO $$
BEGIN
    -- Partner chapters partnership_status column
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'partner_chapters')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'partner_chapters' AND column_name = 'partnership_status') THEN
        ALTER TABLE partner_chapters ADD COLUMN partnership_status TEXT DEFAULT 'active';
        ALTER TABLE partner_chapters ADD CONSTRAINT chk_partner_chapters_partnership_status CHECK (partnership_status IN ('active', 'inactive', 'pending', 'terminated'));
    END IF;
END $$;

DO $$
BEGIN
    -- Compliance records status column
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'compliance_records')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'compliance_records' AND column_name = 'status') THEN
        ALTER TABLE compliance_records ADD COLUMN status TEXT;
        ALTER TABLE compliance_records ADD CONSTRAINT chk_compliance_records_status CHECK (status IN ('compliant', 'at_risk', 'non_compliant'));
    END IF;
END $$;

DO $$
BEGIN
    -- Contact messages status column
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'contact_messages')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'contact_messages' AND column_name = 'status') THEN
        ALTER TABLE contact_messages ADD COLUMN status TEXT DEFAULT 'unread';
        ALTER TABLE contact_messages ADD CONSTRAINT chk_contact_messages_status CHECK (status IN ('unread', 'read', 'replied'));
    END IF;
END $$;

DO $$
BEGIN
    -- Committee tasks status column
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'committee_tasks')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'committee_tasks' AND column_name = 'status') THEN
        ALTER TABLE committee_tasks ADD COLUMN status TEXT DEFAULT 'pending';
        ALTER TABLE committee_tasks ADD CONSTRAINT chk_committee_tasks_status CHECK (status IN ('pending', 'in_progress', 'completed', 'cancelled'));
    END IF;
END $$;

DO $$
BEGIN
    -- Creatives announcements status column
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'creatives_announcements')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'creatives_announcements' AND column_name = 'status') THEN
        ALTER TABLE creatives_announcements ADD COLUMN status TEXT DEFAULT 'published';
        ALTER TABLE creatives_announcements ADD CONSTRAINT chk_creatives_announcements_status CHECK (status IN ('published', 'draft', 'archived'));
    END IF;
END $$;

DO $$
BEGIN
    -- Awards distinctions status column
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = 'awards_distinctions')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'awards_distinctions' AND column_name = 'status') THEN
        ALTER TABLE awards_distinctions ADD COLUMN status TEXT DEFAULT 'active';
        ALTER TABLE awards_distinctions ADD CONSTRAINT chk_awards_distinctions_status CHECK (status IN ('active', 'revoked'));
    END IF;
END $$;

-- =====================================================
-- ROW LEVEL SECURITY
-- =====================================================
ALTER TABLE institutions ENABLE ROW LEVEL SECURITY;
ALTER TABLE affiliated_schools ENABLE ROW LEVEL SECURITY;
ALTER TABLE pending_affiliations ENABLE ROW LEVEL SECURITY;
ALTER TABLE affiliation_approvals ENABLE ROW LEVEL SECURITY;
ALTER TABLE email_verifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE contact_messages ENABLE ROW LEVEL SECURITY;
ALTER TABLE user_profiles ENABLE ROW LEVEL SECURITY;
ALTER TABLE members ENABLE ROW LEVEL SECURITY;
ALTER TABLE member_upload_batches ENABLE ROW LEVEL SECURITY;
ALTER TABLE pending_members ENABLE ROW LEVEL SECURITY;
ALTER TABLE events ENABLE ROW LEVEL SECURITY;
ALTER TABLE event_registrations ENABLE ROW LEVEL SECURITY;
ALTER TABLE attendance ENABLE ROW LEVEL SECURITY;
ALTER TABLE compliance_records ENABLE ROW LEVEL SECURITY;
ALTER TABLE transactions ENABLE ROW LEVEL SECURITY;
ALTER TABLE digital_certificates ENABLE ROW LEVEL SECURITY;
ALTER TABLE announcements ENABLE ROW LEVEL SECURITY;
ALTER TABLE read_receipts ENABLE ROW LEVEL SECURITY;
ALTER TABLE committee_tasks ENABLE ROW LEVEL SECURITY;
ALTER TABLE audit_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE system_settings ENABLE ROW LEVEL SECURITY;
ALTER TABLE blockchain_records ENABLE ROW LEVEL SECURITY;
ALTER TABLE creatives_announcements ENABLE ROW LEVEL SECURITY;
ALTER TABLE creatives_graphics ENABLE ROW LEVEL SECURITY;
ALTER TABLE creatives_publications ENABLE ROW LEVEL SECURITY;
ALTER TABLE creatives_team ENABLE ROW LEVEL SECURITY;
ALTER TABLE creatives_features ENABLE ROW LEVEL SECURITY;
ALTER TABLE fee_brackets ENABLE ROW LEVEL SECURITY;
ALTER TABLE push_subscriptions ENABLE ROW LEVEL SECURITY;
ALTER TABLE notification_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE notification_delivery_log ENABLE ROW LEVEL SECURITY;
ALTER TABLE notifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE partner_chapters ENABLE ROW LEVEL SECURITY;
ALTER TABLE collaboration_posts ENABLE ROW LEVEL SECURITY;
ALTER TABLE collaboration_likes ENABLE ROW LEVEL SECURITY;
ALTER TABLE collaboration_comments ENABLE ROW LEVEL SECURITY;
ALTER TABLE awards_distinctions ENABLE ROW LEVEL SECURITY;

-- =====================================================
-- SERVICE ROLE POLICIES
-- =====================================================
DROP POLICY IF EXISTS service_role_full_access_institutions ON institutions;
CREATE POLICY service_role_full_access_institutions ON institutions FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_affiliated_schools ON affiliated_schools;
CREATE POLICY service_role_full_access_affiliated_schools ON affiliated_schools FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_pending_affiliations ON pending_affiliations;
CREATE POLICY service_role_full_access_pending_affiliations ON pending_affiliations FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_affiliation_approvals ON affiliation_approvals;
CREATE POLICY service_role_full_access_affiliation_approvals ON affiliation_approvals FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_email_verifications ON email_verifications;
CREATE POLICY service_role_full_access_email_verifications ON email_verifications FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_contact_messages ON contact_messages;
CREATE POLICY service_role_full_access_contact_messages ON contact_messages FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_user_profiles ON user_profiles;
CREATE POLICY service_role_full_access_user_profiles ON user_profiles FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_members ON members;
CREATE POLICY service_role_full_access_members ON members FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_member_upload_batches ON member_upload_batches;
CREATE POLICY service_role_full_access_member_upload_batches ON member_upload_batches FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_pending_members ON pending_members;
CREATE POLICY service_role_full_access_pending_members ON pending_members FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_events ON events;
CREATE POLICY service_role_full_access_events ON events FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_event_registrations ON event_registrations;
CREATE POLICY service_role_full_access_event_registrations ON event_registrations FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_attendance ON attendance;
CREATE POLICY service_role_full_access_attendance ON attendance FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_compliance_records ON compliance_records;
CREATE POLICY service_role_full_access_compliance_records ON compliance_records FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_transactions ON transactions;
CREATE POLICY service_role_full_access_transactions ON transactions FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_digital_certificates ON digital_certificates;
CREATE POLICY service_role_full_access_digital_certificates ON digital_certificates FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_announcements ON announcements;
CREATE POLICY service_role_full_access_announcements ON announcements FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_read_receipts ON read_receipts;
CREATE POLICY service_role_full_access_read_receipts ON read_receipts FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_committee_tasks ON committee_tasks;
CREATE POLICY service_role_full_access_committee_tasks ON committee_tasks FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_audit_logs ON audit_logs;
CREATE POLICY service_role_full_access_audit_logs ON audit_logs FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_system_settings ON system_settings;
CREATE POLICY service_role_full_access_system_settings ON system_settings FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_blockchain_records ON blockchain_records;
CREATE POLICY service_role_full_access_blockchain_records ON blockchain_records FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_creatives_announcements ON creatives_announcements;
CREATE POLICY service_role_full_access_creatives_announcements ON creatives_announcements FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_creatives_graphics ON creatives_graphics;
CREATE POLICY service_role_full_access_creatives_graphics ON creatives_graphics FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_creatives_publications ON creatives_publications;
CREATE POLICY service_role_full_access_creatives_publications ON creatives_publications FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_creatives_team ON creatives_team;
CREATE POLICY service_role_full_access_creatives_team ON creatives_team FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_creatives_features ON creatives_features;
CREATE POLICY service_role_full_access_creatives_features ON creatives_features FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_fee_brackets ON fee_brackets;
CREATE POLICY service_role_full_access_fee_brackets ON fee_brackets FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_push_subscriptions ON push_subscriptions;
CREATE POLICY service_role_full_access_push_subscriptions ON push_subscriptions FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_notification_logs ON notification_logs;
CREATE POLICY service_role_full_access_notification_logs ON notification_logs FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_notification_delivery_log ON notification_delivery_log;
CREATE POLICY service_role_full_access_notification_delivery_log ON notification_delivery_log FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_notifications ON notifications;
CREATE POLICY service_role_full_access_notifications ON notifications FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_partner_chapters ON partner_chapters;
CREATE POLICY service_role_full_access_partner_chapters ON partner_chapters FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_collaboration_posts ON collaboration_posts;
CREATE POLICY service_role_full_access_collaboration_posts ON collaboration_posts FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_collaboration_likes ON collaboration_likes;
CREATE POLICY service_role_full_access_collaboration_likes ON collaboration_likes FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_collaboration_comments ON collaboration_comments;
CREATE POLICY service_role_full_access_collaboration_comments ON collaboration_comments FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_awards_distinctions ON awards_distinctions;
CREATE POLICY service_role_full_access_awards_distinctions ON awards_distinctions FOR ALL USING (true) WITH CHECK (true);

-- =====================================================
-- AUTHENTICATED ACCESS POLICIES
-- =====================================================
DROP POLICY IF EXISTS authenticated_can_select_institutions ON institutions;
CREATE POLICY authenticated_can_select_institutions ON institutions FOR SELECT TO authenticated USING (true);

DROP POLICY IF EXISTS authenticated_can_select_affiliated_schools ON affiliated_schools;
CREATE POLICY authenticated_can_select_affiliated_schools ON affiliated_schools FOR SELECT TO authenticated USING (status = 'active');

DROP POLICY IF EXISTS authenticated_can_select_pending_affiliations ON pending_affiliations;
CREATE POLICY authenticated_can_select_pending_affiliations ON pending_affiliations FOR SELECT TO authenticated USING (true);

DROP POLICY IF EXISTS authenticated_can_select_events ON events;
CREATE POLICY authenticated_can_select_events ON events FOR SELECT TO authenticated USING (true);

DROP POLICY IF EXISTS public_can_select_public_events ON events;
CREATE POLICY public_can_select_public_events ON events FOR SELECT TO public USING (is_public = true);

DROP POLICY IF EXISTS public_can_select_announcements ON announcements;
CREATE POLICY public_can_select_announcements ON announcements FOR SELECT TO public USING (true);

DROP POLICY IF EXISTS public_can_select_creatives_features ON creatives_features;
CREATE POLICY public_can_select_creatives_features ON creatives_features FOR SELECT TO public USING (true);

DROP POLICY IF EXISTS user_can_select_own_profiles ON user_profiles;
CREATE POLICY user_can_select_own_profiles ON user_profiles FOR SELECT TO authenticated USING (user_id = auth.uid());
DROP POLICY IF EXISTS user_can_update_own_profiles ON user_profiles;
CREATE POLICY user_can_update_own_profiles ON user_profiles FOR UPDATE TO authenticated USING (user_id = auth.uid()) WITH CHECK (user_id = auth.uid());
DROP POLICY IF EXISTS user_can_delete_own_profiles ON user_profiles;
CREATE POLICY user_can_delete_own_profiles ON user_profiles FOR DELETE TO authenticated USING (user_id = auth.uid());

DROP POLICY IF EXISTS user_can_select_own_members ON members;
CREATE POLICY user_can_select_own_members ON members FOR SELECT TO authenticated USING (user_id = auth.uid());
DROP POLICY IF EXISTS user_can_update_own_members ON members;
CREATE POLICY user_can_update_own_members ON members FOR UPDATE TO authenticated USING (user_id = auth.uid()) WITH CHECK (user_id = auth.uid());
DROP POLICY IF EXISTS user_can_delete_own_members ON members;
CREATE POLICY user_can_delete_own_members ON members FOR DELETE TO authenticated USING (user_id = auth.uid());

DROP POLICY IF EXISTS user_can_select_own_push_subscriptions ON push_subscriptions;
CREATE POLICY user_can_select_own_push_subscriptions ON push_subscriptions FOR SELECT TO authenticated USING (user_id = auth.uid());
DROP POLICY IF EXISTS user_can_update_own_push_subscriptions ON push_subscriptions;
CREATE POLICY user_can_update_own_push_subscriptions ON push_subscriptions FOR UPDATE TO authenticated USING (user_id = auth.uid()) WITH CHECK (user_id = auth.uid());
DROP POLICY IF EXISTS user_can_delete_own_push_subscriptions ON push_subscriptions;
CREATE POLICY user_can_delete_own_push_subscriptions ON push_subscriptions FOR DELETE TO authenticated USING (user_id = auth.uid());
DROP POLICY IF EXISTS user_can_insert_push_subscriptions ON push_subscriptions;
CREATE POLICY user_can_insert_push_subscriptions ON push_subscriptions FOR INSERT TO authenticated WITH CHECK (user_id = auth.uid());

DROP POLICY IF EXISTS user_can_read_own_notifications ON notifications;
CREATE POLICY user_can_read_own_notifications ON notifications FOR SELECT TO authenticated USING (user_id = auth.uid());
DROP POLICY IF EXISTS user_can_update_own_notifications ON notifications;
CREATE POLICY user_can_update_own_notifications ON notifications FOR UPDATE TO authenticated USING (user_id = auth.uid()) WITH CHECK (user_id = auth.uid());
DROP POLICY IF EXISTS user_can_delete_own_notifications ON notifications;
CREATE POLICY user_can_delete_own_notifications ON notifications FOR DELETE TO authenticated USING (user_id = auth.uid());
DROP POLICY IF EXISTS user_can_create_notifications ON notifications;
CREATE POLICY user_can_create_notifications ON notifications FOR INSERT TO authenticated WITH CHECK (user_id = auth.uid());

DROP POLICY IF EXISTS user_can_select_own_event_registrations ON event_registrations;
CREATE POLICY user_can_select_own_event_registrations ON event_registrations FOR SELECT TO authenticated USING (user_id = auth.uid());
DROP POLICY IF EXISTS user_can_update_own_event_registrations ON event_registrations;
CREATE POLICY user_can_update_own_event_registrations ON event_registrations FOR UPDATE TO authenticated USING (user_id = auth.uid()) WITH CHECK (user_id = auth.uid());
DROP POLICY IF EXISTS user_can_delete_own_event_registrations ON event_registrations;
CREATE POLICY user_can_delete_own_event_registrations ON event_registrations FOR DELETE TO authenticated USING (user_id = auth.uid());
DROP POLICY IF EXISTS user_can_insert_event_registrations ON event_registrations;
CREATE POLICY user_can_insert_event_registrations ON event_registrations FOR INSERT TO authenticated WITH CHECK (user_id = auth.uid());

DROP POLICY IF EXISTS user_can_manage_own_attendance ON attendance;
CREATE POLICY user_can_manage_own_attendance ON attendance FOR SELECT TO authenticated USING (user_id = auth.uid());

DROP POLICY IF EXISTS authenticated_can_select_user_profiles ON user_profiles;
CREATE POLICY authenticated_can_select_user_profiles ON user_profiles FOR SELECT TO authenticated USING (true);

DROP POLICY IF EXISTS authenticated_can_select_members ON members;
CREATE POLICY authenticated_can_select_members ON members FOR SELECT TO authenticated USING (true);

-- =====================================================
-- PUBLICATION
-- =====================================================
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_publication WHERE pubname = 'supabase_realtime') THEN
        CREATE PUBLICATION supabase_realtime;
    END IF;
END$$;

DO $$
DECLARE
    pub_oid OID := (SELECT oid FROM pg_publication WHERE pubname = 'supabase_realtime');
    tbl TEXT;
BEGIN
    FOR tbl IN SELECT unnest(ARRAY['institutions', 'affiliated_schools', 'pending_affiliations', 'affiliation_approvals',
        'user_profiles', 'members', 'transactions', 'events', 'event_registrations',
        'attendance', 'notifications', 'collaboration_posts', 'blockchain_records'])
    LOOP
        IF EXISTS (SELECT 1 FROM pg_class WHERE relname = tbl AND relkind = 'r') THEN
            IF NOT EXISTS (
                SELECT 1 FROM pg_publication_rel
                WHERE prpubid = pub_oid
                  AND prrelid = (SELECT oid FROM pg_class WHERE relname = tbl AND relkind = 'r')
            ) THEN
                EXECUTE format('ALTER PUBLICATION supabase_realtime ADD TABLE %I', tbl);
            END IF;
        END IF;
    END LOOP;
END$$;
