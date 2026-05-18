-- =====================================================================
-- IECEP-LSC MEMSYS - COMPLETE CONSOLIDATED PRODUCTION SCHEMA
-- Supabase PostgreSQL Database Setup
-- Consolidated from 9 migration files - Fully Idempotent
-- Created: May 18, 2026
-- =====================================================================

-- =====================================================================
-- EXTENSIONS
-- =====================================================================
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- =====================================================================
-- FUNCTIONS
-- =====================================================================
CREATE OR REPLACE FUNCTION handle_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' OR TG_OP = 'UPDATE' THEN
        NEW.updated_at = NOW();
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- =====================================================================
-- CORE ENTITIES - LEVEL 1 (No dependencies)
-- =====================================================================

-- From: schema.sql
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

CREATE INDEX IF NOT EXISTS idx_institutions_status ON institutions(status);
CREATE INDEX IF NOT EXISTS idx_institutions_name ON institutions(name);
CREATE INDEX IF NOT EXISTS idx_institutions_email ON institutions(email);

DROP TRIGGER IF EXISTS update_institutions_updated_at ON institutions;
CREATE TRIGGER update_institutions_updated_at
    BEFORE UPDATE ON institutions
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

-- From: schema.sql
CREATE TABLE IF NOT EXISTS affiliated_schools (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL UNIQUE,
    facebook_url TEXT,
    member_count INTEGER DEFAULT 0,
    status TEXT DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'pending')),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_affiliated_schools_status ON affiliated_schools(status);
CREATE INDEX IF NOT EXISTS idx_affiliated_schools_name ON affiliated_schools(name);

DROP TRIGGER IF EXISTS update_affiliated_schools_updated_at ON affiliated_schools;
CREATE TRIGGER update_affiliated_schools_updated_at
    BEFORE UPDATE ON affiliated_schools
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

-- From: migrations_2026_05.sql
CREATE TABLE IF NOT EXISTS partner_chapters (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL,
    institution TEXT NOT NULL,
    contact_email TEXT,
    contact_phone TEXT,
    partnership_status TEXT DEFAULT 'active' CHECK (partnership_status IN ('active', 'inactive', 'pending')),
    website TEXT,
    description TEXT,
    headquarters_location TEXT,
    founding_year INTEGER,
    member_count INTEGER DEFAULT 0,
    created_by UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_partner_chapters_status ON partner_chapters(partnership_status);
CREATE INDEX IF NOT EXISTS idx_partner_chapters_created_by ON partner_chapters(created_by);

DROP TRIGGER IF EXISTS update_partner_chapters_updated_at ON partner_chapters;
CREATE TRIGGER update_partner_chapters_updated_at
    BEFORE UPDATE ON partner_chapters
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

-- =====================================================================
-- SYSTEM TABLES - LEVEL 1 (No dependencies)
-- =====================================================================

-- From: 002_events_compliance.sql
CREATE TABLE IF NOT EXISTS fee_brackets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    bracket_name TEXT UNIQUE NOT NULL,
    min_members INTEGER NOT NULL DEFAULT 0,
    max_members INTEGER,
    affiliation_fee DECIMAL(10,2) NOT NULL,
    per_member_fee DECIMAL(10,2),
    annual_fee DECIMAL(10,2),
    valid_from DATE,
    valid_to DATE,
    is_active BOOLEAN DEFAULT true,
    description TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

INSERT INTO fee_brackets (bracket_name, min_members, max_members, affiliation_fee, per_member_fee, annual_fee, is_active, created_at, updated_at) VALUES
    ('Small', 1, 50, 5000.00, 100.00, 1200.00, true, NOW(), NOW()),
    ('Medium', 51, 150, 7500.00, 90.00, 2400.00, true, NOW(), NOW()),
    ('Large', 151, 999999, 10000.00, 80.00, 4200.00, true, NOW(), NOW()),
    ('Enterprise', 501, NULL, NULL, NULL, 6800.00, true, NOW(), NOW())
ON CONFLICT (bracket_name) DO NOTHING;

CREATE INDEX IF NOT EXISTS idx_fee_brackets_min_max ON fee_brackets(min_members, max_members);
CREATE INDEX IF NOT EXISTS idx_fee_brackets_is_active ON fee_brackets(is_active);

DROP TRIGGER IF EXISTS update_fee_brackets_updated_at ON fee_brackets;
CREATE TRIGGER update_fee_brackets_updated_at
    BEFORE UPDATE ON fee_brackets
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

-- From: 002_events_compliance.sql
CREATE TABLE IF NOT EXISTS compliance_rules (
    id SERIAL PRIMARY KEY,
    rule_key TEXT UNIQUE NOT NULL,
    description TEXT,
    threshold NUMERIC(5,2),
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

INSERT INTO compliance_rules (rule_key, description, threshold, is_active)
VALUES 
    ('min_participation', 'Minimum participation rate required', 40.00, true),
    ('required_hosted_events', 'Minimum events to host per year', 1.00, true)
ON CONFLICT (rule_key) DO NOTHING;

CREATE TABLE IF NOT EXISTS system_settings (
    id SERIAL PRIMARY KEY,
    key TEXT UNIQUE NOT NULL,
    value TEXT,
    description TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

INSERT INTO system_settings (key, value, description)
VALUES 
    ('academic_year_start', '2026-06-01', 'Start date of academic year'),
    ('academic_year_end', '2027-05-31', 'End date of academic year'),
    ('compliance_participation_threshold', '40', 'Minimum participation percentage required')
ON CONFLICT (key) DO NOTHING;

-- =====================================================================
-- USER & PROFILE - LEVEL 2 (Depends on auth.users)
-- =====================================================================

-- From: schema.sql
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
        'school_officer', 'member', 'admin', 'super_admin', 'auditor', 'treasurer',
        'secretary', 'officer', 'pro', 'vp_internal', 'vp_external', 'vp_academic', 'president'
    )),
    full_name TEXT,
    school_name TEXT,
    contact_phone TEXT,
    address TEXT,
    membership_status TEXT DEFAULT 'active' CHECK (membership_status IN ('active', 'inactive', 'suspended', 'pending')),
    membership_type TEXT DEFAULT 'regular' CHECK (membership_type IN ('regular', 'student', 'lifetime')),
    force_password_change BOOLEAN DEFAULT true,
    profile_data JSONB DEFAULT '{}',
    last_login TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_user_profiles_user_id ON user_profiles(user_id);
CREATE INDEX IF NOT EXISTS idx_user_profiles_role ON user_profiles(role);
CREATE INDEX IF NOT EXISTS idx_user_profiles_institution ON user_profiles(institution_id);

DROP TRIGGER IF EXISTS update_user_profiles_updated_at ON user_profiles;
CREATE TRIGGER update_user_profiles_updated_at
    BEFORE UPDATE ON user_profiles
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

-- =====================================================================
-- MEMBERSHIP - LEVEL 3 (Depends on institutions, user_profiles, auth.users)
-- =====================================================================

-- From: schema.sql + migrations_2026_05_16_affiliation_integration.sql
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
    digital_id_hash VARCHAR(64),
    year_level TEXT,
    school_affiliate TEXT,
    is_new BOOLEAN DEFAULT true,
    validated_at TIMESTAMPTZ,
    validated_by UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    picture_url TEXT,
    signature_url TEXT,
    birthday DATE,
    phone VARCHAR(20),
    alumni_status BOOLEAN DEFAULT FALSE,
    alumni_since DATE,
    graduation_year INT,
    membership_expiry DATE,
    last_renewal_date DATE,
    application_id UUID,
    upload_batch_id VARCHAR(50),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_members_institution ON members(institution_id);
CREATE INDEX IF NOT EXISTS idx_members_user_id ON members(user_id);
CREATE INDEX IF NOT EXISTS idx_members_email ON members(email);
CREATE INDEX IF NOT EXISTS idx_members_membership_id ON members(membership_id);
CREATE INDEX IF NOT EXISTS idx_members_digital_id_hash ON members(digital_id_hash);
CREATE INDEX IF NOT EXISTS idx_members_year_level ON members(year_level);
CREATE INDEX IF NOT EXISTS idx_members_payment_status ON members(payment_status);
CREATE INDEX IF NOT EXISTS idx_members_validated_at ON members(validated_at);
CREATE INDEX IF NOT EXISTS idx_members_upload_batch ON members(upload_batch_id);

DROP TRIGGER IF EXISTS update_members_updated_at ON members;
CREATE TRIGGER update_members_updated_at
    BEFORE UPDATE ON members
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

-- From: schema.sql + create_member_id_counter_table.sql
CREATE TABLE IF NOT EXISTS member_id_counter (
    id SERIAL PRIMARY KEY,
    year INTEGER UNIQUE,
    last_number INTEGER DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_member_id_counter_year ON member_id_counter(year);

-- From: migrations_2026_05_16_membership_directory_upload.sql
CREATE TABLE IF NOT EXISTS upload_batches (
    id VARCHAR(50) PRIMARY KEY,
    institution_id UUID NOT NULL REFERENCES institutions(id) ON DELETE CASCADE,
    application_id UUID,
    uploaded_by_user_id UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    file_name VARCHAR(255),
    total_rows INTEGER DEFAULT 0,
    validated_rows INTEGER DEFAULT 0,
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'in_progress', 'completed', 'exported')),
    uploaded_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_upload_batches_institution ON upload_batches(institution_id);
CREATE INDEX IF NOT EXISTS idx_upload_batches_uploaded_at ON upload_batches(uploaded_at DESC);
CREATE INDEX IF NOT EXISTS idx_upload_batches_status ON upload_batches(status);
CREATE INDEX IF NOT EXISTS idx_upload_batches_application ON upload_batches(application_id);

-- =====================================================================
-- AFFILIATION & APPLICATIONS - LEVEL 3 (Depends on institutions, user_profiles)
-- =====================================================================

-- From: schema.sql
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
    all_documents_verified BOOLEAN DEFAULT false,
    directory_validated BOOLEAN DEFAULT false,
    directory_validated_at TIMESTAMPTZ,
    directory_validated_by UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_pending_affiliations_status ON pending_affiliations(status);
CREATE INDEX IF NOT EXISTS idx_pending_affiliations_institution ON pending_affiliations(institution_id);

DROP TRIGGER IF EXISTS update_pending_affiliations_updated_at ON pending_affiliations;
CREATE TRIGGER update_pending_affiliations_updated_at
    BEFORE UPDATE ON pending_affiliations
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

-- From: migrations_2026_05_16_affiliation_integration.sql
CREATE TABLE IF NOT EXISTS affiliation_applications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    school_name TEXT NOT NULL,
    org_name TEXT NOT NULL,
    rep_name TEXT NOT NULL,
    rep_email TEXT NOT NULL,
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected', 'requires_revision')),
    submitted_at TIMESTAMPTZ DEFAULT NOW(),
    reviewed_by UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    reviewed_at TIMESTAMPTZ,
    rejection_reason TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_affiliation_applications_status ON affiliation_applications(status);
CREATE INDEX IF NOT EXISTS idx_affiliation_applications_email ON affiliation_applications(rep_email);

-- From: migrations_2026_05_16_affiliation_integration.sql + migrations_2026_05_17_integrated_affiliation_workflow.sql
CREATE TABLE IF NOT EXISTS affiliation_documents (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    application_id UUID NOT NULL REFERENCES pending_affiliations(id) ON DELETE CASCADE,
    document_type TEXT NOT NULL CHECK (document_type IN (
        'letter_of_intent',
        'endorsement_letter',
        'constitution_bylaws',
        'constitution_by_laws',
        'officers_cv',
        'officers_cvs',
        'org_chart',
        'organizational_chart',
        'member_directory'
    )),
    file_name TEXT NOT NULL,
    file_path TEXT NOT NULL,
    file_size INTEGER,
    mime_type TEXT,
    verified BOOLEAN DEFAULT false,
    is_verified BOOLEAN DEFAULT false,
    verified_by UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    verified_at TIMESTAMPTZ,
    uploaded_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(application_id, document_type)
);

CREATE INDEX IF NOT EXISTS idx_affiliation_documents_application ON affiliation_documents(application_id);
CREATE INDEX IF NOT EXISTS idx_affiliation_documents_type ON affiliation_documents(document_type);
CREATE INDEX IF NOT EXISTS idx_affiliation_documents_verified ON affiliation_documents(verified);
CREATE INDEX IF NOT EXISTS idx_affiliation_documents_is_verified ON affiliation_documents(is_verified);

-- From: schema.sql
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

-- =====================================================================
-- MEMBER DIRECTORY IMPORTS - LEVEL 4 (Depends on members, affiliation_applications, upload_batches)
-- =====================================================================

-- From: migrations_2026_05_16_affiliation_integration.sql + migrations_2026_05_16_membership_directory_upload.sql + migrations_2026_05_17_integrated_affiliation_workflow.sql
CREATE TABLE IF NOT EXISTS member_directory_imports (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    batch_id VARCHAR(50) REFERENCES upload_batches(id) ON DELETE CASCADE,
    application_id UUID REFERENCES pending_affiliations(id) ON DELETE CASCADE,
    sheet_name TEXT,
    row_index INTEGER,
    full_name TEXT,
    birthday_clean DATE,
    address TEXT,
    phone TEXT,
    email TEXT,
    picture_raw TEXT,
    signature_raw TEXT,
    member_number VARCHAR(50),
    name TEXT,
    birthday DATE,
    picture_url TEXT,
    signature_url TEXT,
    is_valid BOOLEAN DEFAULT false,
    validation_errors TEXT,
    payment_status BOOLEAN DEFAULT false,
    is_paid BOOLEAN DEFAULT false,
    is_new_member BOOLEAN DEFAULT true,
    member_type TEXT CHECK (member_type IN ('new', 'old')),
    assigned_membership_id VARCHAR(50),
    member_id UUID REFERENCES members(id) ON DELETE SET NULL,
    assigned_at TIMESTAMPTZ,
    assigned_by UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_member_directory_imports_batch ON member_directory_imports(batch_id);
CREATE INDEX IF NOT EXISTS idx_member_directory_imports_application ON member_directory_imports(application_id);
CREATE INDEX IF NOT EXISTS idx_member_directory_imports_email ON member_directory_imports(email);
CREATE INDEX IF NOT EXISTS idx_member_directory_imports_assigned ON member_directory_imports(assigned_membership_id);
CREATE INDEX IF NOT EXISTS idx_member_directory_imports_is_valid ON member_directory_imports(is_valid);

-- From: migrations_2026_05_16_affiliation_integration.sql
CREATE TABLE IF NOT EXISTS membership_id_sequences (
    id SERIAL PRIMARY KEY,
    year INTEGER NOT NULL UNIQUE,
    last_number INTEGER DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_membership_id_sequences_year ON membership_id_sequences(year);

-- =====================================================================
-- TRANSACTIONS & PAYMENTS - LEVEL 3 (Depends on members, institutions, user_profiles)
-- =====================================================================

-- From: schema.sql + 002_events_compliance.sql
CREATE TABLE IF NOT EXISTS transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    receipt_id TEXT UNIQUE,
    user_id UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    member_id UUID REFERENCES members(id) ON DELETE SET NULL,
    institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency TEXT DEFAULT 'PHP',
    type TEXT NOT NULL CHECK (type IN ('membership_fee', 'event_fee', 'donation', 'refund', 'penalty')),
    transaction_type TEXT DEFAULT 'payment',
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'paid', 'failed', 'refunded', 'cancelled')),
    payment_method TEXT CHECK (payment_method IN ('bank_transfer', 'credit_card', 'debit_card', 'online_payment', 'cash', 'check')),
    reference_number TEXT UNIQUE,
    receipt_number TEXT UNIQUE,
    transaction_date TIMESTAMPTZ DEFAULT NOW() NOT NULL,
    due_date TIMESTAMPTZ,
    paid_at TIMESTAMPTZ,
    notes TEXT,
    receipt_url TEXT,
    receipt_path TEXT,
    blockchain_hash TEXT,
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_transactions_user ON transactions(user_id);
CREATE INDEX IF NOT EXISTS idx_transactions_member ON transactions(member_id);
CREATE INDEX IF NOT EXISTS idx_transactions_institution ON transactions(institution_id);
CREATE INDEX IF NOT EXISTS idx_transactions_status ON transactions(status);
CREATE INDEX IF NOT EXISTS idx_transactions_date ON transactions(transaction_date);

DROP TRIGGER IF EXISTS update_transactions_updated_at ON transactions;
CREATE TRIGGER update_transactions_updated_at
    BEFORE UPDATE ON transactions
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

-- From: migrations_2026_05_16_membership_directory_upload.sql
CREATE TABLE IF NOT EXISTS payments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    member_id UUID REFERENCES members(id) ON DELETE CASCADE,
    batch_id VARCHAR(50) REFERENCES upload_batches(id) ON DELETE SET NULL,
    amount DECIMAL(10, 2),
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'paid', 'cancelled')),
    payment_date TIMESTAMPTZ,
    payment_reference TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_payments_member ON payments(member_id);
CREATE INDEX IF NOT EXISTS idx_payments_batch ON payments(batch_id);
CREATE INDEX IF NOT EXISTS idx_payments_status ON payments(status);

-- From: migrations_2026_05_15_all_features.sql
CREATE TABLE IF NOT EXISTS invoices (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    invoice_number TEXT UNIQUE NOT NULL,
    member_id UUID NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    description TEXT,
    issue_date DATE NOT NULL,
    due_date DATE,
    pdf_path TEXT,
    status TEXT DEFAULT 'draft' CHECK (status IN ('draft', 'sent', 'paid', 'overdue', 'cancelled')),
    created_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS payment_plans (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    invoice_id UUID NOT NULL,
    member_id UUID NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    installments INT DEFAULT 1,
    frequency TEXT DEFAULT 'monthly' CHECK (frequency IN ('monthly', 'quarterly', 'semi-annual')),
    start_date DATE,
    status TEXT DEFAULT 'active' CHECK (status IN ('active', 'completed', 'cancelled')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS budgets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    fiscal_year INT NOT NULL,
    department TEXT,
    category TEXT NOT NULL,
    budgeted_amount DECIMAL(10, 2) NOT NULL,
    actual_amount DECIMAL(10, 2) DEFAULT 0,
    variance DECIMAL(10, 2) DEFAULT 0,
    status TEXT DEFAULT 'active',
    created_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS transactions_archive (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    original_transaction_id UUID,
    fiscal_year INT,
    amount DECIMAL(10, 2),
    description TEXT,
    archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- From: migrations_2026_05_15_all_features.sql
CREATE TABLE IF NOT EXISTS payment_gateway_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    gateway_name TEXT NOT NULL,
    transaction_id TEXT NOT NULL,
    amount DECIMAL(10, 2),
    currency TEXT DEFAULT 'PHP',
    status TEXT,
    response_data JSONB,
    member_id UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================================
-- EVENTS - LEVEL 3 (Depends on institutions, user_profiles)
-- =====================================================================

-- From: schema.sql + 002_events_compliance.sql
CREATE TABLE IF NOT EXISTS events (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL,
    title TEXT NOT NULL,
    description TEXT,
    event_type TEXT CHECK (event_type IN ('conference', 'seminar', 'workshop', 'meeting', 'training', 'social', 'ceremony', 'seminar', 'workshop', 'community', 'chapter_meeting', 'other')),
    start_date TIMESTAMPTZ NOT NULL,
    start_datetime TIMESTAMPTZ NOT NULL,
    end_date TIMESTAMPTZ NOT NULL,
    end_datetime TIMESTAMPTZ NOT NULL,
    venue TEXT,
    location TEXT,
    address TEXT,
    city TEXT,
    is_online BOOLEAN DEFAULT false,
    online_link TEXT,
    max_attendees INTEGER,
    max_capacity INT,
    registration_deadline TIMESTAMPTZ,
    registration_fee DECIMAL(10,2) DEFAULT 0,
    fee NUMERIC(10,2) DEFAULT 0,
    requires_payment BOOLEAN DEFAULT false,
    status TEXT DEFAULT 'upcoming' CHECK (status IN ('draft', 'upcoming', 'ongoing', 'completed', 'cancelled', 'published')),
    organizer_id UUID REFERENCES user_profiles(id),
    created_by UUID REFERENCES user_profiles(id),
    is_public BOOLEAN DEFAULT true,
    requires_registration BOOLEAN DEFAULT false,
    agenda JSONB DEFAULT '[]',
    resources JSONB DEFAULT '[]',
    target_roles TEXT[],
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_events_institution ON events(institution_id);
CREATE INDEX IF NOT EXISTS idx_events_start_date ON events(start_date);
CREATE INDEX IF NOT EXISTS idx_events_start_datetime ON events(start_datetime);
CREATE INDEX IF NOT EXISTS idx_events_status ON events(status);

DROP TRIGGER IF EXISTS update_events_updated_at ON events;
CREATE TRIGGER update_events_updated_at
    BEFORE UPDATE ON events
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

-- From: schema.sql + 002_events_compliance.sql
CREATE TABLE IF NOT EXISTS event_registrations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_id UUID REFERENCES events(id) ON DELETE CASCADE NOT NULL,
    user_id UUID REFERENCES user_profiles(id) ON DELETE CASCADE NOT NULL,
    registration_date TIMESTAMPTZ DEFAULT NOW() NOT NULL,
    status TEXT DEFAULT 'registered' CHECK (status IN ('registered', 'confirmed', 'attended', 'cancelled', 'waitlist', 'waitlisted')),
    payment_status TEXT DEFAULT 'pending' CHECK (payment_status IN ('pending', 'paid', 'refunded', 'unpaid', 'waived')),
    special_requirements TEXT,
    checked_in_at TIMESTAMPTZ,
    checked_out_at TIMESTAMPTZ,
    qr_token TEXT UNIQUE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(event_id, user_id)
);

CREATE INDEX IF NOT EXISTS idx_event_registrations_event ON event_registrations(event_id);
CREATE INDEX IF NOT EXISTS idx_event_registrations_user ON event_registrations(user_id);
CREATE INDEX IF NOT EXISTS idx_event_registrations_qr ON event_registrations(qr_token);

DROP TRIGGER IF EXISTS update_event_registrations_updated_at ON event_registrations;
CREATE TRIGGER update_event_registrations_updated_at
    BEFORE UPDATE ON event_registrations
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

-- From: 002_events_compliance.sql
CREATE TABLE IF NOT EXISTS event_attachments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_id UUID REFERENCES events(id) ON DELETE CASCADE,
    file_name TEXT,
    file_path TEXT,
    file_type TEXT,
    uploaded_by UUID,
    created_at TIMESTAMPTZ DEFAULT now()
);

CREATE TABLE IF NOT EXISTS event_attendees (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_id UUID NOT NULL REFERENCES events(id) ON DELETE CASCADE,
    member_id UUID NOT NULL REFERENCES members(id) ON DELETE CASCADE,
    status TEXT DEFAULT 'registered' CHECK (status IN ('registered', 'attended', 'absent', 'cancelled')),
    check_in_time TIMESTAMP,
    UNIQUE(event_id, member_id)
);

-- From: migrations_2026_05_15_all_features.sql
CREATE TABLE IF NOT EXISTS event_logistics (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_id UUID NOT NULL REFERENCES events(id) ON DELETE CASCADE,
    venue_name TEXT,
    venue_address TEXT,
    capacity INT,
    catering_needed BOOLEAN DEFAULT FALSE,
    transport_needed BOOLEAN DEFAULT FALSE,
    equipment_needed TEXT[],
    budget DECIMAL(10, 2),
    status TEXT DEFAULT 'planning' CHECK (status IN ('planning', 'confirmed', 'completed', 'cancelled')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================================
-- NOTIFICATIONS & MESSAGING - LEVEL 3 (Depends on auth.users, institutions)
-- =====================================================================

-- From: migrations_2026_05.sql
CREATE TABLE IF NOT EXISTS notifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    type TEXT DEFAULT 'system' CHECK (type IN ('system', 'alert', 'success', 'info', 'warning')),
    link TEXT,
    action_url TEXT,
    institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL,
    is_read BOOLEAN DEFAULT false,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_notifications_user_id ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_is_read ON notifications(is_read);
CREATE INDEX IF NOT EXISTS idx_notifications_created_at ON notifications(created_at DESC);

DROP TRIGGER IF EXISTS update_notifications_updated_at ON notifications;
CREATE TRIGGER update_notifications_updated_at
    BEFORE UPDATE ON notifications
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

-- From: migrations_2026_05.sql
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    subscription_json JSONB NOT NULL,
    is_active BOOLEAN DEFAULT true,
    last_notified_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_push_subscriptions_user_id ON push_subscriptions(user_id);
CREATE INDEX IF NOT EXISTS idx_push_subscriptions_is_active ON push_subscriptions(is_active);

DROP TRIGGER IF EXISTS update_push_subscriptions_updated_at ON push_subscriptions;
CREATE TRIGGER update_push_subscriptions_updated_at
    BEFORE UPDATE ON push_subscriptions
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

-- From: migrations_2026_05_15_all_features.sql
CREATE TABLE IF NOT EXISTS email_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    template_key TEXT UNIQUE NOT NULL,
    subject TEXT NOT NULL,
    html_body TEXT NOT NULL,
    text_body TEXT,
    variables JSONB,
    created_by UUID,
    updated_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- From: migrations_2026_05_15_all_features.sql
CREATE TABLE IF NOT EXISTS email_verification_tokens (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL,
    token TEXT UNIQUE NOT NULL,
    new_email TEXT NOT NULL,
    expires_at TIMESTAMP,
    verified_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- From: migrations_password_reset.sql
CREATE TABLE IF NOT EXISTS password_resets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email TEXT NOT NULL,
    token TEXT UNIQUE NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    used BOOLEAN DEFAULT false,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_password_resets_token ON password_resets(token);
CREATE INDEX IF NOT EXISTS idx_password_resets_email ON password_resets(email);

-- =====================================================================
-- AUDIT & COMPLIANCE - LEVEL 3 (Depends on auth.users, institutions)
-- =====================================================================

-- From: migrations_2026_05.sql + 002_events_compliance.sql
CREATE TABLE IF NOT EXISTS audit_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE,
    action TEXT NOT NULL,
    details JSONB,
    affected_entity_type TEXT,
    affected_entity_id UUID,
    ip_address INET,
    user_agent TEXT,
    table_name TEXT,
    record_id TEXT,
    old_data JSONB,
    new_data JSONB,
    performed_by UUID,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_audit_logs_user_id ON audit_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_action ON audit_logs(action);
CREATE INDEX IF NOT EXISTS idx_audit_logs_created_at ON audit_logs(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_audit_logs_affected_entity ON audit_logs(affected_entity_type, affected_entity_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_table ON audit_logs(table_name);
CREATE INDEX IF NOT EXISTS idx_audit_logs_record ON audit_logs(record_id);

-- From: 002_events_compliance.sql
CREATE TABLE IF NOT EXISTS compliance_scores (
    institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE NOT NULL,
    year INT NOT NULL,
    participation_rate NUMERIC(5,2),
    hosted_event_count INT DEFAULT 0,
    overall_score NUMERIC(5,2),
    last_updated TIMESTAMPTZ,
    PRIMARY KEY (institution_id, year)
);

CREATE INDEX IF NOT EXISTS idx_compliance_scores_year ON compliance_scores(year);
CREATE INDEX IF NOT EXISTS idx_compliance_scores_score ON compliance_scores(overall_score);

-- From: migrations_2026_05_15_all_features.sql
CREATE TABLE IF NOT EXISTS financial_audit_trail (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id UUID,
    action_type TEXT,
    old_values JSONB,
    new_values JSONB,
    audit_user_id UUID,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS compliance_checks (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    check_type TEXT NOT NULL,
    target_entity_id UUID,
    target_entity_type TEXT,
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'passed', 'failed', 'exception')),
    details JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    checked_at TIMESTAMP
);

-- =====================================================================
-- ANNOUNCEMENTS & CONTENT - LEVEL 3 (Depends on user_profiles)
-- =====================================================================

-- From: 002_events_compliance.sql
CREATE TABLE IF NOT EXISTS announcements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    body TEXT,
    target_roles TEXT[],
    target_institutions UUID[],
    is_global BOOLEAN DEFAULT false,
    scheduled_at TIMESTAMPTZ,
    expires_at TIMESTAMPTZ,
    created_by UUID REFERENCES user_profiles(id),
    created_at TIMESTAMPTZ DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_announcements_scheduled ON announcements(scheduled_at);
CREATE INDEX IF NOT EXISTS idx_announcements_global ON announcements(is_global);

-- From: migrations_2026_05_15_all_features.sql
CREATE TABLE IF NOT EXISTS scheduled_announcements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    content TEXT,
    scheduled_for TIMESTAMP,
    published_at TIMESTAMP,
    status TEXT DEFAULT 'scheduled' CHECK (status IN ('scheduled', 'published', 'cancelled')),
    created_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS content_workflow (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    content_id UUID NOT NULL,
    content_type TEXT NOT NULL,
    current_state TEXT DEFAULT 'draft' CHECK (current_state IN ('draft', 'review', 'approved', 'published', 'archived')),
    created_by UUID,
    submitted_by UUID,
    approved_by UUID,
    submitted_at TIMESTAMP,
    approved_at TIMESTAMP,
    published_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================================
-- CERTIFICATES & DIGITAL CREDENTIALS - LEVEL 4 (Depends on members, events)
-- =====================================================================

-- From: 002_events_compliance.sql
CREATE TABLE IF NOT EXISTS certificates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    member_id UUID REFERENCES members(id) ON DELETE CASCADE,
    event_id UUID REFERENCES events(id) ON DELETE SET NULL,
    issue_date DATE,
    certificate_number TEXT UNIQUE,
    blockchain_hash TEXT,
    file_path TEXT,
    template_type TEXT DEFAULT 'participation',
    created_at TIMESTAMPTZ DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_certificates_member ON certificates(member_id);
CREATE INDEX IF NOT EXISTS idx_certificates_event ON certificates(event_id);
CREATE INDEX IF NOT EXISTS idx_certificates_number ON certificates(certificate_number);
CREATE INDEX IF NOT EXISTS idx_certificates_hash ON certificates(blockchain_hash);

-- =====================================================================
-- BLOCKCHAIN - LEVEL 3
-- =====================================================================

-- From: migrations_2026_05.sql
CREATE TABLE IF NOT EXISTS blockchain_records (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    entity_type TEXT NOT NULL,
    entity_id UUID NOT NULL,
    transaction_hash TEXT UNIQUE,
    record_hash TEXT,
    block_number INTEGER,
    confirmed BOOLEAN DEFAULT false,
    institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_blockchain_records_entity ON blockchain_records(entity_type, entity_id);
CREATE INDEX IF NOT EXISTS idx_blockchain_records_hash ON blockchain_records(transaction_hash);
CREATE INDEX IF NOT EXISTS idx_blockchain_records_confirmed ON blockchain_records(confirmed);
CREATE INDEX IF NOT EXISTS idx_blockchain_records_institution_id ON blockchain_records(institution_id);

-- =====================================================================
-- COLLABORATION - LEVEL 3 (Depends on partner_chapters, auth.users)
-- =====================================================================

-- From: migrations_2026_05.sql
CREATE TABLE IF NOT EXISTS collaboration_posts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    chapter_id UUID NOT NULL REFERENCES partner_chapters(id) ON DELETE CASCADE,
    content TEXT NOT NULL,
    created_by UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    attachment_url TEXT,
    likes_count INTEGER DEFAULT 0,
    shares_count INTEGER DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_collaboration_posts_chapter_id ON collaboration_posts(chapter_id);
CREATE INDEX IF NOT EXISTS idx_collaboration_posts_created_at ON collaboration_posts(created_at DESC);

DROP TRIGGER IF EXISTS update_collaboration_posts_updated_at ON collaboration_posts;
CREATE TRIGGER update_collaboration_posts_updated_at
    BEFORE UPDATE ON collaboration_posts
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

-- =====================================================================
-- ADMIN & SUPER-ADMIN FEATURES - LEVEL 3
-- =====================================================================

-- From: migrations_2026_05_15_all_features.sql
CREATE TABLE IF NOT EXISTS system_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    log_level TEXT NOT NULL,
    category TEXT NOT NULL,
    message TEXT NOT NULL,
    details JSONB,
    ip_address INET,
    user_id UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_system_logs_level ON system_logs(log_level);
CREATE INDEX IF NOT EXISTS idx_system_logs_category ON system_logs(category);
CREATE INDEX IF NOT EXISTS idx_system_logs_created_at ON system_logs(created_at DESC);

-- From: migrations_2026_05_15_all_features.sql
CREATE TABLE IF NOT EXISTS role_permissions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    role TEXT NOT NULL,
    permission TEXT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(role, permission)
);

-- From: migrations_2026_05_15_all_features.sql
CREATE TABLE IF NOT EXISTS cron_jobs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    job_name TEXT UNIQUE NOT NULL,
    handler_file TEXT NOT NULL,
    schedule TEXT NOT NULL,
    last_run_at TIMESTAMP,
    next_run_at TIMESTAMP,
    is_enabled BOOLEAN DEFAULT TRUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- From: migrations_2026_05_15_all_features.sql
CREATE TABLE IF NOT EXISTS impersonation_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    admin_user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    impersonated_user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP,
    actions_taken JSONB,
    notes TEXT
);

-- =====================================================================
-- MEMBER FEATURES - LEVEL 3
-- =====================================================================

-- From: migrations_2026_05_15_all_features.sql
CREATE TABLE IF NOT EXISTS user_reminder_settings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL UNIQUE REFERENCES auth.users(id) ON DELETE CASCADE,
    affiliation_renewal_days INT DEFAULT 30,
    payment_due_days INT DEFAULT 7,
    event_reminder_days INT DEFAULT 3,
    push_notifications_enabled BOOLEAN DEFAULT TRUE,
    email_notifications_enabled BOOLEAN DEFAULT TRUE,
    sms_notifications_enabled BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================================
-- SCHOOL OFFICER FEATURES - LEVEL 3
-- =====================================================================

-- From: migrations_2026_05_15_all_features.sql
CREATE TABLE IF NOT EXISTS schools (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL,
    address TEXT,
    contact_person TEXT,
    contact_email TEXT,
    contact_phone TEXT,
    website TEXT,
    logo_path TEXT,
    member_count INT DEFAULT 0,
    active_members INT DEFAULT 0,
    alumni_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS temp_school_members (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    school_id UUID NOT NULL,
    upload_batch_id UUID,
    email TEXT,
    full_name TEXT,
    student_id TEXT,
    program TEXT,
    year_level INT,
    status TEXT DEFAULT 'pending',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================================
-- SECRETARY FEATURES - LEVEL 3
-- =====================================================================

-- From: migrations_2026_05_15_all_features.sql
CREATE TABLE IF NOT EXISTS documents (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    parent_id UUID,
    title TEXT NOT NULL,
    content TEXT,
    file_path TEXT,
    version INT DEFAULT 1,
    created_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS minutes_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    template_name TEXT NOT NULL,
    sections JSONB,
    created_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS committee_tasks (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    committee_id UUID,
    task_title TEXT NOT NULL,
    description TEXT,
    assigned_to UUID,
    assigned_by UUID,
    due_date DATE,
    priority TEXT DEFAULT 'medium' CHECK (priority IN ('low', 'medium', 'high', 'critical')),
    status TEXT DEFAULT 'open' CHECK (status IN ('open', 'in_progress', 'completed', 'cancelled')),
    depends_on UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================================
-- CREATIVES COMMITTEE - LEVEL 3
-- =====================================================================

-- From: migrations_2026_05_15_all_features.sql
CREATE TABLE IF NOT EXISTS scheduled_announcements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    content TEXT,
    scheduled_for TIMESTAMP,
    published_at TIMESTAMP,
    status TEXT DEFAULT 'scheduled' CHECK (status IN ('scheduled', 'published', 'cancelled')),
    created_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================================
-- MARKETING COMMITTEE - LEVEL 3
-- =====================================================================

-- From: migrations_2026_05_15_all_features.sql
CREATE TABLE IF NOT EXISTS marketing_campaigns (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    campaign_name TEXT NOT NULL,
    description TEXT,
    start_date DATE,
    end_date DATE,
    budget DECIMAL(10, 2),
    status TEXT DEFAULT 'draft' CHECK (status IN ('draft', 'active', 'completed', 'cancelled')),
    created_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS email_blasts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    campaign_id UUID,
    subject TEXT,
    html_content TEXT,
    recipient_count INT,
    sent_at TIMESTAMP,
    status TEXT DEFAULT 'draft' CHECK (status IN ('draft', 'sent', 'failed', 'scheduled')),
    created_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS email_tracking (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email_blast_id UUID,
    member_id UUID,
    opened_at TIMESTAMP,
    clicked_at TIMESTAMP,
    bounce_status TEXT,
    tracking_code TEXT UNIQUE
);

CREATE TABLE IF NOT EXISTS leads (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    first_name TEXT,
    last_name TEXT,
    email TEXT NOT NULL,
    phone TEXT,
    organization TEXT,
    source TEXT,
    status TEXT DEFAULT 'new' CHECK (status IN ('new', 'contacted', 'interested', 'converted', 'rejected')),
    notes TEXT,
    assigned_to UUID,
    converted_member_id UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS social_posts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT,
    content TEXT,
    scheduled_for TIMESTAMP,
    posted_at TIMESTAMP,
    platform TEXT CHECK (platform IN ('facebook', 'twitter', 'instagram', 'linkedin')),
    status TEXT DEFAULT 'scheduled' CHECK (status IN ('scheduled', 'posted', 'failed', 'cancelled')),
    created_by UUID,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================================
-- LOGISTICS COMMITTEE - LEVEL 3
-- =====================================================================

-- From: migrations_2026_05_15_all_features.sql
CREATE TABLE IF NOT EXISTS inventory_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    item_name TEXT NOT NULL,
    category TEXT,
    quantity INT DEFAULT 0,
    reorder_level INT,
    unit_cost DECIMAL(10, 2),
    location TEXT,
    supplier_id UUID,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS vendors (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    vendor_name TEXT NOT NULL,
    contact_person TEXT,
    email TEXT,
    phone TEXT,
    service_category TEXT,
    rating FLOAT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS asset_loans (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    asset_id UUID,
    borrower_id UUID NOT NULL,
    checkout_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    due_date DATE,
    return_date TIMESTAMP,
    condition_on_checkout TEXT,
    condition_on_return TEXT,
    status TEXT DEFAULT 'loaned' CHECK (status IN ('loaned', 'returned', 'overdue')),
    notes TEXT
);

-- =====================================================================
-- REGISTRATION COMMITTEE - LEVEL 3
-- =====================================================================

-- From: migrations_2026_05_15_all_features.sql
CREATE TABLE IF NOT EXISTS potential_duplicates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    primary_record_id UUID NOT NULL,
    potential_duplicate_id UUID NOT NULL,
    similarity_score FLOAT DEFAULT 0,
    fields_matched TEXT[],
    status TEXT DEFAULT 'unreviewed' CHECK (status IN ('unreviewed', 'confirmed_duplicate', 'false_positive')),
    reviewed_by UUID,
    reviewed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS temp_user_imports (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    import_batch_id UUID NOT NULL,
    email TEXT NOT NULL,
    full_name TEXT NOT NULL,
    role TEXT NOT NULL,
    institution_id UUID,
    status TEXT DEFAULT 'pending',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP
);

-- =====================================================================
-- ROW LEVEL SECURITY POLICIES
-- =====================================================================

-- Enable RLS on sensitive tables
ALTER TABLE notifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE audit_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE push_subscriptions ENABLE ROW LEVEL SECURITY;
ALTER TABLE events ENABLE ROW LEVEL SECURITY;
ALTER TABLE event_registrations ENABLE ROW LEVEL SECURITY;
ALTER TABLE event_attachments ENABLE ROW LEVEL SECURITY;
ALTER TABLE compliance_scores ENABLE ROW LEVEL SECURITY;
ALTER TABLE announcements ENABLE ROW LEVEL SECURITY;
ALTER TABLE certificates ENABLE ROW LEVEL SECURITY;
ALTER TABLE affiliation_applications ENABLE ROW LEVEL SECURITY;
ALTER TABLE affiliation_documents ENABLE ROW LEVEL SECURITY;
ALTER TABLE member_directory_imports ENABLE ROW LEVEL SECURITY;
ALTER TABLE pending_affiliations ENABLE ROW LEVEL SECURITY;
ALTER TABLE transactions ENABLE ROW LEVEL SECURITY;
ALTER TABLE members ENABLE ROW LEVEL SECURITY;

-- Service role policies (full access)
DROP POLICY IF EXISTS service_role_full_access ON notifications;
CREATE POLICY service_role_full_access ON notifications FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access ON audit_logs;
CREATE POLICY service_role_full_access ON audit_logs FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access ON push_subscriptions;
CREATE POLICY service_role_full_access ON push_subscriptions FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access ON events;
CREATE POLICY service_role_full_access ON events FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access ON event_registrations;
CREATE POLICY service_role_full_access ON event_registrations FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access ON affiliation_applications;
CREATE POLICY service_role_full_access ON affiliation_applications FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access ON affiliation_documents;
CREATE POLICY service_role_full_access ON affiliation_documents FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access ON member_directory_imports;
CREATE POLICY service_role_full_access ON member_directory_imports FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access ON pending_affiliations;
CREATE POLICY service_role_full_access ON pending_affiliations FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access ON transactions;
CREATE POLICY service_role_full_access ON transactions FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access ON members;
CREATE POLICY service_role_full_access ON members FOR ALL USING (true) WITH CHECK (true);

-- User-friendly policies (read own data)
DROP POLICY IF EXISTS user_notifications_select ON notifications;
CREATE POLICY user_notifications_select ON notifications
    FOR SELECT USING (user_id = auth.uid());

DROP POLICY IF EXISTS user_subscriptions_select ON push_subscriptions;
CREATE POLICY user_subscriptions_select ON push_subscriptions
    FOR SELECT USING (user_id = auth.uid());

DROP POLICY IF EXISTS authenticated_can_select_own_applications ON affiliation_applications;
CREATE POLICY authenticated_can_select_own_applications ON affiliation_applications 
    FOR SELECT TO authenticated 
    USING (rep_email = (SELECT email FROM auth.users WHERE id = auth.uid()));

DROP POLICY IF EXISTS authenticated_can_select_own_affiliation_documents ON affiliation_documents;
CREATE POLICY authenticated_can_select_own_affiliation_documents ON affiliation_documents 
    FOR SELECT TO authenticated 
    USING (
        application_id IN (
            SELECT id FROM pending_affiliations WHERE applicant_id IN (
                SELECT id FROM user_profiles WHERE user_id = auth.uid()
            )
        )
    );

-- =====================================================================
-- REALTIME SUBSCRIPTIONS
-- =====================================================================

BEGIN;
    DROP PUBLICATION IF EXISTS supabase_realtime CASCADE;
END;

CREATE PUBLICATION supabase_realtime FOR TABLE
    notifications,
    announcements,
    events,
    event_registrations,
    transactions,
    audit_logs,
    pending_affiliations,
    members,
    user_profiles,
    institutions;

-- =====================================================================
-- COMPLETION
-- =====================================================================

COMMIT;

-- Log completion
DO $$
BEGIN
    RAISE NOTICE 'IECEP-LSC MEMSYS Complete Consolidated Schema Setup - Completed at %', now();
END $$;
