-- =====================================================================
-- IECEP-LSC MEMSYS - COMPLETE PRODUCTION SUPABASE POSTGRESQL SCHEMA
-- Laguna Student Chapter Membership & Affiliation Management System
-- Unified All-In-One SQL Script for Supabase PostgreSQL & PostgREST
-- Idempotent (Safe to run multiple times without data loss)
-- =====================================================================

-- =====================================================================
-- 1. EXTENSIONS
-- =====================================================================
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- =====================================================================
-- 2. CORE UTILITY FUNCTIONS & TRIGGERS
-- =====================================================================
CREATE OR REPLACE FUNCTION handle_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- =====================================================================
-- 3. INSTITUTIONS (Affiliated HEI Universities in Laguna)
-- =====================================================================
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

-- =====================================================================
-- 4. USERS & USER PROFILES
-- =====================================================================
-- Local/Direct Auth table
CREATE TABLE IF NOT EXISTS users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email TEXT UNIQUE NOT NULL,
    password TEXT,
    password_hash TEXT,
    full_name TEXT,
    role TEXT DEFAULT 'member',
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);

-- Supabase User Profiles (Linked with auth.users or local IDs)
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
    force_password_change BOOLEAN DEFAULT false,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_user_profiles_role ON user_profiles(role);
CREATE INDEX IF NOT EXISTS idx_user_profiles_inst ON user_profiles(institution_id);
CREATE INDEX IF NOT EXISTS idx_user_profiles_uid ON user_profiles(user_id);

-- Ensure all columns exist on user_profiles if pre-created
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS user_id UUID;
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS full_name TEXT;
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS role TEXT DEFAULT 'member';
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS institution_id UUID;
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS phone TEXT;
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS avatar_url TEXT;
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'active';
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS force_password_change BOOLEAN DEFAULT false;

-- =====================================================================
-- 5. MEMBERS (Digital ID & Official Roster)
-- =====================================================================
CREATE TABLE IF NOT EXISTS members (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID,
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
    avatar_url TEXT,
    birthday DATE,
    address TEXT,
    digital_id_hash TEXT,
    qr_code_url TEXT,
    joined_date DATE DEFAULT CURRENT_DATE,
    expiration_date DATE DEFAULT (CURRENT_DATE + INTERVAL '1 year'),
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_members_mem_id ON members(membership_id);
CREATE INDEX IF NOT EXISTS idx_members_inst ON members(institution_id);
CREATE INDEX IF NOT EXISTS idx_members_status ON members(status);
CREATE INDEX IF NOT EXISTS idx_members_payment ON members(payment_status);
CREATE INDEX IF NOT EXISTS idx_members_user_id ON members(user_id);

-- Ensure all member columns exist on existing tables
ALTER TABLE members ADD COLUMN IF NOT EXISTS user_id UUID;
ALTER TABLE members ADD COLUMN IF NOT EXISTS first_name TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS last_name TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS student_number TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS phone TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS course TEXT DEFAULT 'Bachelor of Science in Electronics Engineering';
ALTER TABLE members ADD COLUMN IF NOT EXISTS year_level TEXT DEFAULT '4th Year';
ALTER TABLE members ADD COLUMN IF NOT EXISTS avatar_url TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS birthday DATE;
ALTER TABLE members ADD COLUMN IF NOT EXISTS address TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS digital_id_hash TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS qr_code_url TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS payment_status TEXT DEFAULT 'paid';

-- =====================================================================
-- 6. SEQUENTIAL MEMBER ID COUNTERS
-- =====================================================================
CREATE TABLE IF NOT EXISTS member_id_counter (
    year INTEGER PRIMARY KEY,
    last_number INTEGER NOT NULL DEFAULT 0,
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS membership_id_sequences (
    id SERIAL PRIMARY KEY,
    year INT NOT NULL UNIQUE,
    last_number INT NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_mem_seq_year ON membership_id_sequences(year);

-- =====================================================================
-- 7. EVENTS & ATTENDANCE
-- =====================================================================
CREATE TABLE IF NOT EXISTS events (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    description TEXT,
    event_type TEXT DEFAULT 'seminar',
    venue TEXT DEFAULT 'Main Auditorium / Online',
    location TEXT,
    start_date TIMESTAMPTZ DEFAULT NOW(),
    end_date TIMESTAMPTZ DEFAULT (NOW() + INTERVAL '4 hours'),
    start_datetime TIMESTAMPTZ,
    end_datetime TIMESTAMPTZ,
    registration_fee NUMERIC(10,2) DEFAULT 0.00,
    fee NUMERIC(10,2) DEFAULT 0.00,
    max_attendees INTEGER DEFAULT 500,
    max_capacity INTEGER DEFAULT 500,
    registration_deadline TIMESTAMPTZ,
    requires_payment BOOLEAN DEFAULT false,
    is_online BOOLEAN DEFAULT false,
    online_link TEXT,
    status TEXT DEFAULT 'published' CHECK (status IN ('draft', 'published', 'ongoing', 'completed', 'cancelled')),
    institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL,
    created_by UUID,
    target_roles TEXT[],
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_events_status ON events(status);
CREATE INDEX IF NOT EXISTS idx_events_start ON events(start_date);

-- Ensure all event columns exist on existing tables
ALTER TABLE events ADD COLUMN IF NOT EXISTS venue TEXT DEFAULT 'Main Auditorium / Online';
ALTER TABLE events ADD COLUMN IF NOT EXISTS location TEXT;
ALTER TABLE events ADD COLUMN IF NOT EXISTS start_date TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE events ADD COLUMN IF NOT EXISTS end_date TIMESTAMPTZ DEFAULT (NOW() + INTERVAL '4 hours');
ALTER TABLE events ADD COLUMN IF NOT EXISTS start_datetime TIMESTAMPTZ;
ALTER TABLE events ADD COLUMN IF NOT EXISTS end_datetime TIMESTAMPTZ;
ALTER TABLE events ADD COLUMN IF NOT EXISTS registration_fee NUMERIC(10,2) DEFAULT 0.00;
ALTER TABLE events ADD COLUMN IF NOT EXISTS fee NUMERIC(10,2) DEFAULT 0.00;
ALTER TABLE events ADD COLUMN IF NOT EXISTS max_attendees INTEGER DEFAULT 500;
ALTER TABLE events ADD COLUMN IF NOT EXISTS max_capacity INTEGER DEFAULT 500;
ALTER TABLE events ADD COLUMN IF NOT EXISTS registration_deadline TIMESTAMPTZ;
ALTER TABLE events ADD COLUMN IF NOT EXISTS requires_payment BOOLEAN DEFAULT false;
ALTER TABLE events ADD COLUMN IF NOT EXISTS is_online BOOLEAN DEFAULT false;
ALTER TABLE events ADD COLUMN IF NOT EXISTS online_link TEXT;
ALTER TABLE events ADD COLUMN IF NOT EXISTS target_roles TEXT[];

-- Live Dynamic 15s QR & Officer Scanner Attendance
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

-- Event registrations & ticketing table
CREATE TABLE IF NOT EXISTS event_registrations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_id UUID REFERENCES events(id) ON DELETE CASCADE,
    user_id UUID,
    status TEXT DEFAULT 'registered' CHECK (status IN ('registered','waitlisted','confirmed','attended','cancelled')),
    payment_status TEXT DEFAULT 'unpaid' CHECK (payment_status IN ('unpaid','paid','waived')),
    registered_at TIMESTAMPTZ DEFAULT NOW(),
    checked_in_at TIMESTAMPTZ,
    checked_out_at TIMESTAMPTZ,
    qr_token TEXT UNIQUE,
    UNIQUE(event_id, user_id)
);

CREATE INDEX IF NOT EXISTS idx_event_reg_event ON event_registrations(event_id);
CREATE INDEX IF NOT EXISTS idx_event_reg_qr ON event_registrations(qr_token);

-- Event attachments (Presentations, Program PDF)
CREATE TABLE IF NOT EXISTS event_attachments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_id UUID REFERENCES events(id) ON DELETE CASCADE,
    file_name TEXT,
    file_path TEXT,
    file_type TEXT,
    uploaded_by UUID,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Member Attendance Logs (for CBL compliance)
CREATE TABLE IF NOT EXISTS attendance_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL,
    event_id UUID REFERENCES events(id) ON DELETE CASCADE NOT NULL,
    timestamp TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(user_id, event_id)
);

CREATE INDEX IF NOT EXISTS idx_att_logs_user ON attendance_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_att_logs_event ON attendance_logs(event_id);

-- Certificates of Participation / Completion
CREATE TABLE IF NOT EXISTS certificates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    member_id UUID REFERENCES members(id) ON DELETE CASCADE,
    event_id UUID REFERENCES events(id) ON DELETE SET NULL,
    issue_date DATE DEFAULT CURRENT_DATE,
    certificate_number TEXT UNIQUE,
    blockchain_hash TEXT,
    file_path TEXT,
    template_type TEXT DEFAULT 'participation',
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_certificates_member ON certificates(member_id);
CREATE INDEX IF NOT EXISTS idx_certificates_event ON certificates(event_id);
CREATE INDEX IF NOT EXISTS idx_certificates_number ON certificates(certificate_number);

-- =====================================================================
-- 8. BLOCKCHAIN RECORDS (Cryptographic Proof & SHA-256 Ledger)
-- =====================================================================
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

-- Ensure all blockchain columns exist
ALTER TABLE blockchain_records ADD COLUMN IF NOT EXISTS block_index BIGINT;
ALTER TABLE blockchain_records ADD COLUMN IF NOT EXISTS record_hash TEXT;
ALTER TABLE blockchain_records ADD COLUMN IF NOT EXISTS data_hash TEXT;
ALTER TABLE blockchain_records ADD COLUMN IF NOT EXISTS previous_hash TEXT;
ALTER TABLE blockchain_records ADD COLUMN IF NOT EXISTS merkle_root TEXT;
ALTER TABLE blockchain_records ADD COLUMN IF NOT EXISTS confirmed BOOLEAN DEFAULT true;

-- =====================================================================
-- 9. PENDING AFFILIATIONS (Institutional Applications)
-- Supports BOTH documents JSON AND individual column lookups seamlessly
-- =====================================================================
CREATE TABLE IF NOT EXISTS pending_affiliations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    school_name TEXT,
    institution_name TEXT,
    acronym TEXT,
    email TEXT,
    contact_email TEXT,
    contact_person TEXT,
    contact_number TEXT,
    contact_phone TEXT,
    contact_position TEXT,
    institution_address TEXT,
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'under_review', 'approved', 'rejected', 'requires_revision', 'resubmitted')),
    documents JSONB DEFAULT '{}',
    letter_of_intent TEXT,
    endorsement_letter TEXT,
    constitution_by_laws TEXT,
    officers_cvs TEXT,
    organizational_chart TEXT,
    member_directory TEXT,
    total_members INTEGER DEFAULT 0,
    new_members INTEGER DEFAULT 0,
    old_members INTEGER DEFAULT 0,
    affiliation_fee NUMERIC(10,2) DEFAULT 0.00,
    membership_total NUMERIC(10,2) DEFAULT 0.00,
    total_fee NUMERIC(10,2) DEFAULT 0.00,
    receipt_number TEXT,
    verification_code TEXT,
    verified_at TIMESTAMPTZ,
    rejection_reason TEXT,
    resubmitted_at TIMESTAMPTZ,
    submitted_at TIMESTAMPTZ DEFAULT NOW(),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_pending_aff_status ON pending_affiliations(status);
CREATE INDEX IF NOT EXISTS idx_pending_aff_email ON pending_affiliations(email);
CREATE INDEX IF NOT EXISTS idx_pending_aff_contact_email ON pending_affiliations(contact_email);

-- Ensure all possible columns exist on pending_affiliations (prevents PGRST204)
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS school_name TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS institution_name TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS acronym TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS email TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS contact_email TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS contact_person TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS contact_number TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS contact_phone TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS contact_position TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS institution_address TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS documents JSONB DEFAULT '{}';
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS letter_of_intent TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS endorsement_letter TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS constitution_by_laws TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS officers_cvs TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS organizational_chart TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS member_directory TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS total_members INTEGER DEFAULT 0;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS new_members INTEGER DEFAULT 0;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS old_members INTEGER DEFAULT 0;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS affiliation_fee NUMERIC(10,2) DEFAULT 0.00;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS membership_total NUMERIC(10,2) DEFAULT 0.00;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS total_fee NUMERIC(10,2) DEFAULT 0.00;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS receipt_number TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS verification_code TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS verified_at TIMESTAMPTZ;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS rejection_reason TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS resubmitted_at TIMESTAMPTZ;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS submitted_at TIMESTAMPTZ DEFAULT NOW();

-- Safely update status constraint on pending_affiliations to allow 'resubmitted'
DO $$
BEGIN
    ALTER TABLE pending_affiliations DROP CONSTRAINT IF EXISTS pending_affiliations_status_check;
    ALTER TABLE pending_affiliations ADD CONSTRAINT pending_affiliations_status_check 
        CHECK (status IN ('pending', 'under_review', 'approved', 'rejected', 'requires_revision', 'resubmitted'));
EXCEPTION WHEN OTHERS THEN
    NULL;
END $$;

-- Revision requests tracker
CREATE TABLE IF NOT EXISTS revision_requests (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    affiliation_id UUID NOT NULL REFERENCES pending_affiliations(id) ON DELETE CASCADE,
    token TEXT UNIQUE NOT NULL,
    explanation TEXT,
    requested_by UUID,
    deadline TIMESTAMPTZ NOT NULL,
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending','submitted','expired')),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_rev_req_token ON revision_requests(token);
CREATE INDEX IF NOT EXISTS idx_rev_req_aff ON revision_requests(affiliation_id);
CREATE INDEX IF NOT EXISTS idx_rev_req_status ON revision_requests(status);

-- =====================================================================
-- 10. TRANSACTIONS & TREASURY
-- =====================================================================
CREATE TABLE IF NOT EXISTS transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id TEXT UNIQUE NOT NULL,
    user_id UUID,
    member_id UUID REFERENCES members(id) ON DELETE SET NULL,
    institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL,
    event_id UUID REFERENCES events(id) ON DELETE SET NULL,
    pending_affiliation_id UUID REFERENCES pending_affiliations(id) ON DELETE SET NULL,
    amount NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    fee_type TEXT NOT NULL DEFAULT 'membership_fee',
    type TEXT DEFAULT 'payment',
    transaction_type TEXT DEFAULT 'payment',
    payment_method TEXT DEFAULT 'gcash' CHECK (payment_method IN ('gcash', 'maya', 'bank_transfer', 'cash', 'stripe', 'other')),
    reference_number TEXT,
    receipt_number TEXT,
    receipt_url TEXT,
    receipt_path TEXT,
    blockchain_hash TEXT,
    status TEXT DEFAULT 'completed' CHECK (status IN ('pending', 'completed', 'verified', 'rejected', 'refunded')),
    notes TEXT,
    verified_by UUID,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_tx_status ON transactions(status);
CREATE INDEX IF NOT EXISTS idx_tx_member ON transactions(member_id);
CREATE INDEX IF NOT EXISTS idx_tx_receipt_number ON transactions(receipt_number);

-- Ensure all transaction columns exist
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS pending_affiliation_id UUID;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS type TEXT DEFAULT 'payment';
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS transaction_type TEXT DEFAULT 'payment';
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS receipt_number TEXT;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS receipt_path TEXT;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS blockchain_hash TEXT;

-- School-level financial records
CREATE TABLE IF NOT EXISTS financial_records (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    school_id UUID NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_type TEXT NOT NULL CHECK (payment_type IN ('Affiliation', 'Operational', 'Individual_Dues')),
    payment_status TEXT DEFAULT 'Pending' CHECK (payment_status IN ('Pending', 'Verified')),
    proof_of_payment TEXT,
    official_receipt_url TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_fin_rec_school ON financial_records(school_id);
CREATE INDEX IF NOT EXISTS idx_fin_rec_status ON financial_records(payment_status);

-- =====================================================================
-- 11. VERIFICATION CODES & 2FA
-- =====================================================================
CREATE TABLE IF NOT EXISTS verification_codes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email TEXT NOT NULL,
    code TEXT NOT NULL,
    purpose TEXT DEFAULT 'affiliation',
    expires_at TIMESTAMPTZ NOT NULL,
    used BOOLEAN DEFAULT false,
    verified BOOLEAN DEFAULT false,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_ver_code ON verification_codes(email, code);

CREATE TABLE IF NOT EXISTS email_verifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(255) NOT NULL,
    code VARCHAR(10) NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_email_verifications_lookup ON email_verifications (email, code, verified);

-- =====================================================================
-- 12. CBL COMPLIANCE MODULE
-- =====================================================================
CREATE TABLE IF NOT EXISTS school_profiles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    school_name TEXT NOT NULL UNIQUE,
    affiliation_status TEXT DEFAULT 'Pending' CHECK (affiliation_status IN ('Pending', 'Active', 'Probationary', 'Revoked', 'Pending_Renewal')),
    total_members INTEGER DEFAULT 0,
    institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE,
    validity_expiry DATE,
    last_renewal_date DATE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_school_profiles_status ON school_profiles(affiliation_status);
CREATE INDEX IF NOT EXISTS idx_school_profiles_institution ON school_profiles(institution_id);

CREATE TABLE IF NOT EXISTS compliance_docs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    school_id UUID NOT NULL,
    doc_type TEXT NOT NULL,
    file_url TEXT NOT NULL,
    is_verified BOOLEAN DEFAULT false,
    verified_by UUID,
    verified_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_compliance_docs_school ON compliance_docs(school_id);
CREATE INDEX IF NOT EXISTS idx_compliance_docs_verified ON compliance_docs(is_verified);

CREATE TABLE IF NOT EXISTS compliance_scores (
    institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE,
    year INT,
    participation_rate NUMERIC(5,2),
    hosted_event_count INT DEFAULT 0,
    overall_score NUMERIC(5,2),
    last_updated TIMESTAMPTZ DEFAULT NOW(),
    PRIMARY KEY (institution_id, year)
);

CREATE TABLE IF NOT EXISTS compliance_rules (
    id SERIAL PRIMARY KEY,
    rule_key TEXT UNIQUE NOT NULL,
    description TEXT,
    threshold NUMERIC(5,2),
    is_active BOOLEAN DEFAULT true
);

CREATE TABLE IF NOT EXISTS policy_compliance (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    institution_id UUID NOT NULL REFERENCES institutions(id) ON DELETE CASCADE,
    policy_name VARCHAR(255) NOT NULL,
    policy_description TEXT,
    is_compliant BOOLEAN DEFAULT FALSE,
    completed_at TIMESTAMPTZ,
    completed_by UUID,
    notes TEXT,
    due_date DATE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_policy_comp_inst ON policy_compliance(institution_id);

-- =====================================================================
-- 13. MERCHANDISE & STORE
-- =====================================================================
CREATE TABLE IF NOT EXISTS merch_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT,
    title TEXT,
    category TEXT DEFAULT 'apparel',
    description TEXT,
    price NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    image_url TEXT,
    image TEXT,
    badge TEXT,
    stock INTEGER DEFAULT 100,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_merch_items_active ON merch_items(is_active);

-- Ensure all merch_items columns exist
ALTER TABLE merch_items ADD COLUMN IF NOT EXISTS name TEXT;
ALTER TABLE merch_items ADD COLUMN IF NOT EXISTS title TEXT;
ALTER TABLE merch_items ADD COLUMN IF NOT EXISTS category TEXT DEFAULT 'apparel';
ALTER TABLE merch_items ADD COLUMN IF NOT EXISTS description TEXT;
ALTER TABLE merch_items ADD COLUMN IF NOT EXISTS price NUMERIC(10,2) DEFAULT 0.00;
ALTER TABLE merch_items ADD COLUMN IF NOT EXISTS image_url TEXT;
ALTER TABLE merch_items ADD COLUMN IF NOT EXISTS image TEXT;
ALTER TABLE merch_items ADD COLUMN IF NOT EXISTS badge TEXT;
ALTER TABLE merch_items ADD COLUMN IF NOT EXISTS stock INTEGER DEFAULT 100;
ALTER TABLE merch_items ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT true;

CREATE TABLE IF NOT EXISTS merch_orders (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id TEXT UNIQUE,
    member_id UUID REFERENCES members(id) ON DELETE SET NULL,
    buyer_name TEXT,
    buyer_email TEXT,
    customer_name TEXT,
    customer_email TEXT,
    customer_phone TEXT,
    shipping_address TEXT,
    items JSONB NOT NULL DEFAULT '[]',
    total_amount NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    payment_method TEXT DEFAULT 'gcash',
    transaction_id UUID REFERENCES transactions(id) ON DELETE SET NULL,
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'processing', 'paid', 'shipped', 'delivered', 'completed', 'cancelled')),
    notes TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_merch_orders_member ON merch_orders(member_id);
CREATE INDEX IF NOT EXISTS idx_merch_orders_status ON merch_orders(status);

-- Ensure all merch_orders columns exist
ALTER TABLE merch_orders ADD COLUMN IF NOT EXISTS order_id TEXT;
ALTER TABLE merch_orders ADD COLUMN IF NOT EXISTS member_id UUID;
ALTER TABLE merch_orders ADD COLUMN IF NOT EXISTS buyer_name TEXT;
ALTER TABLE merch_orders ADD COLUMN IF NOT EXISTS buyer_email TEXT;
ALTER TABLE merch_orders ADD COLUMN IF NOT EXISTS customer_name TEXT;
ALTER TABLE merch_orders ADD COLUMN IF NOT EXISTS customer_email TEXT;
ALTER TABLE merch_orders ADD COLUMN IF NOT EXISTS customer_phone TEXT;
ALTER TABLE merch_orders ADD COLUMN IF NOT EXISTS shipping_address TEXT;
ALTER TABLE merch_orders ADD COLUMN IF NOT EXISTS items JSONB DEFAULT '[]';
ALTER TABLE merch_orders ADD COLUMN IF NOT EXISTS total_amount NUMERIC(10,2) DEFAULT 0.00;
ALTER TABLE merch_orders ADD COLUMN IF NOT EXISTS payment_method TEXT DEFAULT 'gcash';
ALTER TABLE merch_orders ADD COLUMN IF NOT EXISTS transaction_id UUID;
ALTER TABLE merch_orders ADD COLUMN IF NOT EXISTS notes TEXT;

-- =====================================================================
-- 14. COMMUNICATIONS: ANNOUNCEMENTS, NOTIFICATIONS, MESSAGES, MEMOS
-- =====================================================================
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
    content TEXT,
    body TEXT,
    target_role TEXT DEFAULT 'all',
    target_roles TEXT[],
    target_institutions UUID[],
    priority TEXT DEFAULT 'normal' CHECK (priority IN ('low', 'normal', 'high', 'urgent')),
    is_global BOOLEAN DEFAULT false,
    scheduled_at TIMESTAMPTZ,
    expires_at TIMESTAMPTZ,
    author_id UUID,
    created_by UUID,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_announcements_active ON announcements(is_active);

CREATE TABLE IF NOT EXISTS notifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID,
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    type TEXT DEFAULT 'info' CHECK (type IN ('info', 'success', 'warning', 'danger', 'event', 'system', 'affiliation_revision')),
    link_url TEXT,
    reference_id UUID,
    is_read BOOLEAN DEFAULT false,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_notif_user ON notifications(user_id, is_read);

CREATE TABLE IF NOT EXISTS messages (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    sender_id UUID NOT NULL,
    receiver_id UUID NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_messages_sender ON messages(sender_id);
CREATE INDEX IF NOT EXISTS idx_messages_receiver ON messages(receiver_id);

CREATE TABLE IF NOT EXISTS memoranda (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    sent_by UUID NOT NULL,
    sent_at TIMESTAMPTZ DEFAULT NOW(),
    expires_at TIMESTAMPTZ,
    is_active BOOLEAN DEFAULT TRUE,
    target_roles JSONB DEFAULT '[]'::jsonb,
    target_institutions JSONB DEFAULT '[]'::jsonb,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_memos_sent_by ON memoranda(sent_by);

CREATE TABLE IF NOT EXISTS newsletters (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    subject VARCHAR(255) NOT NULL,
    html_content TEXT NOT NULL,
    text_content TEXT,
    sent_by UUID NOT NULL,
    target_roles JSONB DEFAULT '[]'::jsonb,
    target_institutions JSONB DEFAULT '[]'::jsonb,
    sent_at TIMESTAMPTZ,
    status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'scheduled', 'sent')),
    scheduled_for TIMESTAMPTZ,
    recipient_count INT DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- =====================================================================
-- 15. DOCUMENT MANAGEMENT & AUDIT TRAIL
-- =====================================================================
CREATE TABLE IF NOT EXISTS documents (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category TEXT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    file_hash VARCHAR(64),
    version INT DEFAULT 1,
    uploaded_by UUID,
    institution_id UUID,
    is_public BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_documents_category ON documents(category);
CREATE INDEX IF NOT EXISTS idx_documents_inst ON documents(institution_id);

CREATE TABLE IF NOT EXISTS document_versions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    document_id UUID NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    version_number INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    file_hash VARCHAR(64),
    uploaded_by UUID,
    change_notes TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS audit_logs (
    id SERIAL PRIMARY KEY,
    action TEXT,
    table_name TEXT,
    record_id TEXT,
    old_data JSONB,
    new_data JSONB,
    performed_by UUID,
    ip_address TEXT,
    user_agent TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_audit_logs_table ON audit_logs(table_name);
CREATE INDEX IF NOT EXISTS idx_audit_logs_created ON audit_logs(created_at);

-- =====================================================================
-- 16. SYSTEM SETTINGS & FEE SCHEDULES
-- =====================================================================
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
-- 17. AUTOMATED UPDATED_AT TRIGGERS
-- =====================================================================
DO $$
DECLARE
    t text;
BEGIN
    FOR t IN 
        SELECT unnest(ARRAY[
            'institutions', 'users', 'user_profiles', 'members', 'events', 
            'transactions', 'pending_affiliations', 'revision_requests', 
            'school_profiles', 'policy_compliance', 'merch_items', 'merch_orders', 
            'memoranda', 'newsletters', 'documents', 'system_settings', 'fee_brackets'
        ])
    LOOP
        EXECUTE format('DROP TRIGGER IF EXISTS trg_%I_updated_at ON %I;', t, t);
        EXECUTE format('CREATE TRIGGER trg_%I_updated_at BEFORE UPDATE ON %I FOR EACH ROW EXECUTE FUNCTION handle_updated_at();', t, t);
    END LOOP;
END $$;

-- =====================================================================
-- 18. SEED DATA: OFFICIAL LAGUNA HEI CHAPTERS (All 8 Campuses)
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

-- =====================================================================
-- 19. SEED DATA: OFFICIAL USERS & PROFILES
-- =====================================================================
-- Seed users (Password hash for Admin: Admin123! | Officer: School123! | Member: Member123!)
INSERT INTO users (id, email, password, password_hash, full_name, role, is_active, created_at, updated_at)
VALUES
    ('00000000-0000-0000-0000-000000000001', 'lspuscc.adminece@gmail.com', '$2y$12$mypSMbD3y1XR5uuewBIV5ONYYT3yODWWKdOINbV7/2n86Xu0PupXK', '$2y$12$mypSMbD3y1XR5uuewBIV5ONYYT3yODWWKdOINbV7/2n86Xu0PupXK', 'IECEP-LSC Regional Admin', 'super_admin', true, NOW(), NOW()),
    ('00000000-0000-0000-0000-000000000002', 'ieceptest86@gmail.com', '$2y$12$7QzP4zCK2as87c1og7U59et9vvPHU90pCYCNXn.zM7RuH/cti.cXa', '$2y$12$7QzP4zCK2as87c1og7U59et9vvPHU90pCYCNXn.zM7RuH/cti.cXa', 'LSPU - SCC School Officer', 'school_officer', true, NOW(), NOW()),
    ('00000000-0000-0000-0000-000000000003', 'rasheddizon7@gmail.com', '$2y$12$t6adOxlvvxUJa4Lu2U6EX.R5U.2KGRTwQNeE9i51ou9Cw59Ft2vDi', '$2y$12$t6adOxlvvxUJa4Lu2U6EX.R5U.2KGRTwQNeE9i51ou9Cw59Ft2vDi', 'Rashed Dizon', 'member', true, NOW(), NOW())
ON CONFLICT (email) DO UPDATE SET
    password = EXCLUDED.password,
    password_hash = EXCLUDED.password_hash,
    full_name = EXCLUDED.full_name,
    role = EXCLUDED.role,
    is_active = EXCLUDED.is_active;

INSERT INTO user_profiles (id, user_id, email, full_name, role, institution_id, phone, status, force_password_change)
VALUES
    ('00000000-0000-0000-0000-000000000001', '00000000-0000-0000-0000-000000000001', 'lspuscc.adminece@gmail.com', 'IECEP-LSC Regional Admin', 'super_admin', '1fe48809-8ac6-4428-a6f1-3025cc47f5bb', '09171234567', 'active', false),
    ('00000000-0000-0000-0000-000000000002', '00000000-0000-0000-0000-000000000002', 'ieceptest86@gmail.com', 'LSPU - SCC School Officer', 'school_officer', '1fe48809-8ac6-4428-a6f1-3025cc47f5bb', '09181234567', 'active', false),
    ('00000000-0000-0000-0000-000000000003', '00000000-0000-0000-0000-000000000003', 'rasheddizon7@gmail.com', 'Rashed Dizon', 'member', '1fe48809-8ac6-4428-a6f1-3025cc47f5bb', '09191234567', 'active', false)
ON CONFLICT (email) DO UPDATE SET
    full_name = EXCLUDED.full_name,
    role = EXCLUDED.role,
    institution_id = EXCLUDED.institution_id;

-- =====================================================================
-- 20. SEED DATA: OFFICIAL MEMBERS
-- =====================================================================
INSERT INTO members (
    id, membership_id, full_name, first_name, last_name, email,
    phone, institution_id, course, year_level, student_number,
    membership_type, status, payment_status, digital_id_hash
)
VALUES
    (
        '10000000-0000-0000-0000-000000000003',
        '20260001',
        'Rashed Dizon',
        'Rashed',
        'Dizon',
        'rasheddizon7@gmail.com',
        '09191234567',
        '1fe48809-8ac6-4428-a6f1-3025cc47f5bb',
        'BS Electronics Engineering',
        '3rd Year',
        '2022-00123',
        'student',
        'active',
        'paid',
        'a1b2c3d4e5f60103'
    )
ON CONFLICT (email) DO UPDATE SET
    full_name = EXCLUDED.full_name,
    membership_id = EXCLUDED.membership_id,
    institution_id = EXCLUDED.institution_id,
    year_level = EXCLUDED.year_level,
    course = EXCLUDED.course,
    student_number = EXCLUDED.student_number;

INSERT INTO member_id_counter (year, last_number)
VALUES (2026, 1)
ON CONFLICT (year) DO UPDATE SET last_number = GREATEST(member_id_counter.last_number, EXCLUDED.last_number);

INSERT INTO membership_id_sequences (year, last_number)
VALUES (2026, 1)
ON CONFLICT (year) DO UPDATE SET last_number = GREATEST(membership_id_sequences.last_number, EXCLUDED.last_number);

-- =====================================================================
-- 21. SEED DATA: EVENTS & ANNOUNCEMENTS
-- =====================================================================
INSERT INTO events (id, title, description, event_type, venue, start_date, end_date, start_datetime, end_datetime, status, registration_fee, fee, max_attendees)
VALUES
    (
        '2f2f99ce-98e1-49f6-8949-760687189aa6',
        'IECEP-LSC Regional Technical Summit 2026',
        'Flagship regional technical convention and research exposition for Laguna electronics engineering students.',
        'technical_summit',
        'Main Auditorium / Online',
        NOW() - INTERVAL '2 hours',
        NOW() + INTERVAL '8 hours',
        NOW() - INTERVAL '2 hours',
        NOW() + INTERVAL '8 hours',
        'published',
        150.00,
        150.00,
        500
    ),
    (
        'a9b8c7d6-e5f4-3210-fedc-ba9876543210',
        'IECEP Leadership & Chapter Assembly 2026',
        'Annual quorum and leadership transition assembly for affiliated Laguna HEI chapters.',
        'assembly',
        'LSPU Main Hall',
        NOW() + INTERVAL '7 days',
        NOW() + INTERVAL '7 days 5 hours',
        NOW() + INTERVAL '7 days',
        NOW() + INTERVAL '7 days 5 hours',
        'published',
        0.00,
        0.00,
        300
    )
ON CONFLICT (id) DO UPDATE SET
    title = EXCLUDED.title,
    description = EXCLUDED.description,
    status = EXCLUDED.status;

-- =====================================================================
-- 22. SEED DATA: SETTINGS, FEE SCHEDULES, COMPLIANCE RULES, MERCH
-- =====================================================================
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

INSERT INTO compliance_rules (rule_key, description, threshold, is_active)
VALUES 
    ('min_participation', 'Minimum participation rate required for chapter compliance', 40.00, true),
    ('required_hosted_events', 'Minimum hosted events per academic year', 1.00, true)
ON CONFLICT (rule_key) DO NOTHING;

INSERT INTO merch_items (id, name, title, description, price, stock, is_active)
VALUES
    ('90000000-0000-0000-0000-000000000001', 'IECEP-LSC Chapter Shirt', 'IECEP-LSC Chapter Shirt', 'Official Laguna Student Chapter technical polo-shirt (Navy Blue/Gold).', 350.00, 100, true),
    ('90000000-0000-0000-0000-000000000002', 'IECEP-LSC Enamel Pin', 'IECEP-LSC Enamel Pin', 'Collector edition metallic enamel chapter emblem pin.', 120.00, 250, true)
ON CONFLICT (id) DO UPDATE SET price = EXCLUDED.price, stock = EXCLUDED.stock;

-- =====================================================================
-- 23. ROW LEVEL SECURITY (RLS) POLICIES
-- =====================================================================
ALTER TABLE institutions ENABLE ROW LEVEL SECURITY;
ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE user_profiles ENABLE ROW LEVEL SECURITY;
ALTER TABLE members ENABLE ROW LEVEL SECURITY;
ALTER TABLE member_id_counter ENABLE ROW LEVEL SECURITY;
ALTER TABLE membership_id_sequences ENABLE ROW LEVEL SECURITY;
ALTER TABLE events ENABLE ROW LEVEL SECURITY;
ALTER TABLE event_attendees ENABLE ROW LEVEL SECURITY;
ALTER TABLE event_registrations ENABLE ROW LEVEL SECURITY;
ALTER TABLE event_attachments ENABLE ROW LEVEL SECURITY;
ALTER TABLE attendance_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE certificates ENABLE ROW LEVEL SECURITY;
ALTER TABLE blockchain_records ENABLE ROW LEVEL SECURITY;
ALTER TABLE pending_affiliations ENABLE ROW LEVEL SECURITY;
ALTER TABLE revision_requests ENABLE ROW LEVEL SECURITY;
ALTER TABLE transactions ENABLE ROW LEVEL SECURITY;
ALTER TABLE financial_records ENABLE ROW LEVEL SECURITY;
ALTER TABLE verification_codes ENABLE ROW LEVEL SECURITY;
ALTER TABLE email_verifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE school_profiles ENABLE ROW LEVEL SECURITY;
ALTER TABLE compliance_docs ENABLE ROW LEVEL SECURITY;
ALTER TABLE compliance_scores ENABLE ROW LEVEL SECURITY;
ALTER TABLE compliance_rules ENABLE ROW LEVEL SECURITY;
ALTER TABLE policy_compliance ENABLE ROW LEVEL SECURITY;
ALTER TABLE merch_items ENABLE ROW LEVEL SECURITY;
ALTER TABLE merch_orders ENABLE ROW LEVEL SECURITY;
ALTER TABLE featured_cards ENABLE ROW LEVEL SECURITY;
ALTER TABLE announcements ENABLE ROW LEVEL SECURITY;
ALTER TABLE notifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE messages ENABLE ROW LEVEL SECURITY;
ALTER TABLE memoranda ENABLE ROW LEVEL SECURITY;
ALTER TABLE newsletters ENABLE ROW LEVEL SECURITY;
ALTER TABLE documents ENABLE ROW LEVEL SECURITY;
ALTER TABLE document_versions ENABLE ROW LEVEL SECURITY;
ALTER TABLE audit_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE system_settings ENABLE ROW LEVEL SECURITY;
ALTER TABLE fee_brackets ENABLE ROW LEVEL SECURITY;

-- Grant Full Public & Service Role Access for API Client Operations
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
-- 24. REALTIME WEB-SOCKET SUBSCRIPTIONS
-- =====================================================================
BEGIN;
    DROP PUBLICATION IF EXISTS supabase_realtime CASCADE;
    CREATE PUBLICATION supabase_realtime FOR TABLE
        notifications,
        announcements,
        events,
        event_attendees,
        event_registrations,
        transactions,
        members,
        institutions,
        pending_affiliations,
        revision_requests,
        merch_orders,
        merch_items,
        messages;
COMMIT;

SELECT 'IECEP-LSC MEMSYS Complete Supabase Schema executed successfully!' AS result;
