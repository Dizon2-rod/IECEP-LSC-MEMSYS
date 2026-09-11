-- =====================================================================
-- [DEPRECATED] IECEP-LSC MEMSYS - COMPLETE UNIFIED SUPABASE POSTGRESQL MASTER SCHEMA
-- NOTICE: This file is DEPRECATED as of Migration 013 and kept solely for legacy reference.
-- For new installs and schema updates, run sequential migrations in database/migrations/
-- tracked by the `schema_migrations` table using `php scripts/migrate.php`.
-- =====================================================================
-- Laguna Student Chapter Membership & Affiliation Management System
-- Unified All-In-One SQL Script Combining All Repository SQL Migrations:
--   - database/additional_tables.sql
--   - database/add_event_id_to_transactions.sql
--   - database/enhancements_sql.sql (Surveys, Email Blasts, MFA)
--   - database/featured_cards.sql
--   - database/fix_blockchain_schema.sql
--   - database/seed_accounts.sql
--   - database/migrations/002_events_compliance.sql
--   - database/migrations/003_cbl_compliance_system.sql
--   - database/migrations/004_auto_generate_accounts.sql
--   - database/migrations/005_pending_affiliations.sql
--   - database/migrations/006_member_id_counter.sql
--   - database/migrations/007_verification_codes.sql
--   - database/migrations/008_merchandise.sql
--   - database/migrations/009_fee_brackets_system_settings.sql
--   - database/migrations/010_email_verifications.sql
--   - migrations/create_revision_requests.sql
--   - database/backups/backup_memsys_blockchain_v2_20260828.sql
--
-- 100% IDEMPOTENT & RESILIENT:
--   1. Every table uses CREATE TABLE IF NOT EXISTS
--   2. Every column is explicitly checked/added via ALTER TABLE ... ADD COLUMN IF NOT EXISTS
--   3. All columns are ensured BEFORE indexes are created (prevents 42703 column missing errors)
--   4. Seed data and triggers are wrapped in safe DO $$ BEGIN ... EXCEPTION WHEN OTHERS THEN NULL; END $$ blocks
--   5. Full RLS enabled with permissive policies for API client access
--   6. Dynamic Supabase Realtime publication registration
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
-- 3. INSTITUTIONS & AFFILIATED SCHOOLS
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

ALTER TABLE institutions ADD COLUMN IF NOT EXISTS email TEXT;
ALTER TABLE institutions ADD COLUMN IF NOT EXISTS name TEXT;
ALTER TABLE institutions ADD COLUMN IF NOT EXISTS acronym TEXT;
ALTER TABLE institutions ADD COLUMN IF NOT EXISTS type TEXT DEFAULT 'university';
ALTER TABLE institutions ADD COLUMN IF NOT EXISTS address TEXT;
ALTER TABLE institutions ADD COLUMN IF NOT EXISTS city TEXT;
ALTER TABLE institutions ADD COLUMN IF NOT EXISTS province TEXT DEFAULT 'Laguna';
ALTER TABLE institutions ADD COLUMN IF NOT EXISTS region TEXT DEFAULT 'Region IV-A (CALABARZON)';
ALTER TABLE institutions ADD COLUMN IF NOT EXISTS country TEXT DEFAULT 'Philippines';
ALTER TABLE institutions ADD COLUMN IF NOT EXISTS contact_person TEXT;
ALTER TABLE institutions ADD COLUMN IF NOT EXISTS contact_email TEXT;
ALTER TABLE institutions ADD COLUMN IF NOT EXISTS contact_phone TEXT;
ALTER TABLE institutions ADD COLUMN IF NOT EXISTS website TEXT;
ALTER TABLE institutions ADD COLUMN IF NOT EXISTS facebook_url TEXT;
ALTER TABLE institutions ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'active';
ALTER TABLE institutions ADD COLUMN IF NOT EXISTS affiliation_fee_paid BOOLEAN DEFAULT false;
ALTER TABLE institutions ADD COLUMN IF NOT EXISTS compliance_status TEXT DEFAULT 'compliant';
ALTER TABLE institutions ADD COLUMN IF NOT EXISTS membership_count INTEGER DEFAULT 0;
ALTER TABLE institutions ADD COLUMN IF NOT EXISTS established_year INTEGER;
ALTER TABLE institutions ADD COLUMN IF NOT EXISTS accreditation_status TEXT;
ALTER TABLE institutions ADD COLUMN IF NOT EXISTS metadata JSONB DEFAULT '{}';
ALTER TABLE institutions ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE institutions ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_institutions_status ON institutions(status);
CREATE INDEX IF NOT EXISTS idx_institutions_acronym ON institutions(acronym);

CREATE TABLE IF NOT EXISTS affiliated_schools (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) UNIQUE NOT NULL,
    facebook_url VARCHAR(500),
    member_count INT DEFAULT 0,
    status TEXT DEFAULT 'active',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE affiliated_schools ADD COLUMN IF NOT EXISTS name VARCHAR(255);
ALTER TABLE affiliated_schools ADD COLUMN IF NOT EXISTS facebook_url VARCHAR(500);
ALTER TABLE affiliated_schools ADD COLUMN IF NOT EXISTS member_count INT DEFAULT 0;
ALTER TABLE affiliated_schools ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'active';
ALTER TABLE affiliated_schools ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE affiliated_schools ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

-- =====================================================================
-- 4. USERS, AUTH & PROFILES
-- =====================================================================
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

ALTER TABLE users ADD COLUMN IF NOT EXISTS email TEXT;
ALTER TABLE users ADD COLUMN IF NOT EXISTS password TEXT;
ALTER TABLE users ADD COLUMN IF NOT EXISTS password_hash TEXT;
ALTER TABLE users ADD COLUMN IF NOT EXISTS full_name TEXT;
ALTER TABLE users ADD COLUMN IF NOT EXISTS role TEXT DEFAULT 'member';
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT true;
ALTER TABLE users ADD COLUMN IF NOT EXISTS institution_id UUID;
ALTER TABLE users ADD COLUMN IF NOT EXISTS must_change_password BOOLEAN DEFAULT false;
ALTER TABLE users ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE users ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email_uq ON users(email);

-- Compatibility Auth Users Table (for seed_accounts.sql & legacy queries)
CREATE TABLE IF NOT EXISTS auth_users (
    id TEXT PRIMARY KEY,
    email TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE auth_users ADD COLUMN IF NOT EXISTS email TEXT;
ALTER TABLE auth_users ADD COLUMN IF NOT EXISTS password_hash TEXT;
ALTER TABLE auth_users ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE auth_users ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_auth_users_email ON auth_users(email);

-- User Profiles (Linked with auth or users)
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
    membership_status TEXT DEFAULT 'active',
    force_password_change BOOLEAN DEFAULT false,
    mfa_enabled BOOLEAN DEFAULT false,
    mfa_secret TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS user_id UUID;
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS email TEXT;
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS full_name TEXT;
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS role TEXT DEFAULT 'member';
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS institution_id UUID;
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS phone TEXT;
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS avatar_url TEXT;
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'active';
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS membership_status TEXT DEFAULT 'active';
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS force_password_change BOOLEAN DEFAULT false;
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS mfa_enabled BOOLEAN DEFAULT false;
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS mfa_secret TEXT;
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE user_profiles ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_user_profiles_role ON user_profiles(role);
CREATE INDEX IF NOT EXISTS idx_user_profiles_inst ON user_profiles(institution_id);
CREATE INDEX IF NOT EXISTS idx_user_profiles_uid ON user_profiles(user_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_user_profiles_email_uq ON user_profiles(email);

-- =====================================================================
-- 5. MEMBERS (Digital ID, Roster, Batch Import & Profiles)
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
    member_type TEXT DEFAULT 'new',
    status TEXT DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'pending', 'expired', 'suspended')),
    payment_status TEXT DEFAULT 'paid' CHECK (payment_status IN ('paid', 'pending', 'waived', 'unpaid', 'overdue')),
    avatar_url TEXT,
    birthday DATE,
    address TEXT,
    digital_id_hash TEXT,
    qr_code_url TEXT,
    joined_date DATE DEFAULT CURRENT_DATE,
    expiration_date DATE DEFAULT (CURRENT_DATE + INTERVAL '1 year'),
    membership_expiry DATE,
    last_renewal_date DATE,
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE members ADD COLUMN IF NOT EXISTS user_id UUID;
ALTER TABLE members ADD COLUMN IF NOT EXISTS membership_id TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS full_name TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS first_name TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS last_name TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS email TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS phone TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS institution_id UUID;
ALTER TABLE members ADD COLUMN IF NOT EXISTS course TEXT DEFAULT 'Bachelor of Science in Electronics Engineering';
ALTER TABLE members ADD COLUMN IF NOT EXISTS program TEXT DEFAULT 'Bachelor of Science in Electronics Engineering';
ALTER TABLE members ADD COLUMN IF NOT EXISTS year_level TEXT DEFAULT '4th Year';
ALTER TABLE members ADD COLUMN IF NOT EXISTS student_number TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS membership_type TEXT DEFAULT 'student';
ALTER TABLE members ADD COLUMN IF NOT EXISTS member_type TEXT DEFAULT 'new';
ALTER TABLE members ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'active';
ALTER TABLE members ADD COLUMN IF NOT EXISTS payment_status TEXT DEFAULT 'paid';
ALTER TABLE members ADD COLUMN IF NOT EXISTS avatar_url TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS birthday DATE;
ALTER TABLE members ADD COLUMN IF NOT EXISTS address TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS digital_id_hash TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS digital_id_url TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS qr_code_url TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS joined_date DATE DEFAULT CURRENT_DATE;
ALTER TABLE members ADD COLUMN IF NOT EXISTS expiration_date DATE DEFAULT (CURRENT_DATE + INTERVAL '1 year');
ALTER TABLE members ADD COLUMN IF NOT EXISTS membership_expiry DATE;
ALTER TABLE members ADD COLUMN IF NOT EXISTS last_renewal_date DATE;
ALTER TABLE members ADD COLUMN IF NOT EXISTS metadata JSONB DEFAULT '{}';
ALTER TABLE members ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE members ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_members_mem_id ON members(membership_id);
CREATE INDEX IF NOT EXISTS idx_members_inst ON members(institution_id);
CREATE INDEX IF NOT EXISTS idx_members_status ON members(status);
CREATE INDEX IF NOT EXISTS idx_members_payment ON members(payment_status);
CREATE INDEX IF NOT EXISTS idx_members_user_id ON members(user_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_members_email_uq ON members(email);

CREATE TABLE IF NOT EXISTS member_profiles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID,
    member_id UUID REFERENCES members(id) ON DELETE CASCADE,
    full_name TEXT,
    email TEXT,
    phone TEXT,
    address TEXT,
    bio TEXT,
    avatar_url TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE member_profiles ADD COLUMN IF NOT EXISTS user_id UUID;
ALTER TABLE member_profiles ADD COLUMN IF NOT EXISTS member_id UUID;
ALTER TABLE member_profiles ADD COLUMN IF NOT EXISTS full_name TEXT;
ALTER TABLE member_profiles ADD COLUMN IF NOT EXISTS email TEXT;
ALTER TABLE member_profiles ADD COLUMN IF NOT EXISTS phone TEXT;
ALTER TABLE member_profiles ADD COLUMN IF NOT EXISTS address TEXT;
ALTER TABLE member_profiles ADD COLUMN IF NOT EXISTS bio TEXT;
ALTER TABLE member_profiles ADD COLUMN IF NOT EXISTS avatar_url TEXT;
ALTER TABLE member_profiles ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE member_profiles ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

-- Member upload batches and temporary imports
CREATE TABLE IF NOT EXISTS member_upload_batches (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE,
    batch_name TEXT,
    uploaded_by UUID,
    total_rows INTEGER DEFAULT 0,
    status TEXT DEFAULT 'pending_approval',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE member_upload_batches ADD COLUMN IF NOT EXISTS institution_id UUID;
ALTER TABLE member_upload_batches ADD COLUMN IF NOT EXISTS batch_name TEXT;
ALTER TABLE member_upload_batches ADD COLUMN IF NOT EXISTS uploaded_by UUID;
ALTER TABLE member_upload_batches ADD COLUMN IF NOT EXISTS total_rows INTEGER DEFAULT 0;
ALTER TABLE member_upload_batches ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'pending_approval';
ALTER TABLE member_upload_batches ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE member_upload_batches ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

CREATE TABLE IF NOT EXISTS upload_batches (
    id VARCHAR(50) PRIMARY KEY,
    institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE,
    application_id UUID,
    uploaded_by_user_id UUID,
    file_name VARCHAR(255),
    total_rows INT DEFAULT 0,
    validated_rows INT DEFAULT 0,
    status TEXT DEFAULT 'pending',
    uploaded_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE upload_batches ADD COLUMN IF NOT EXISTS institution_id UUID;
ALTER TABLE upload_batches ADD COLUMN IF NOT EXISTS application_id UUID;
ALTER TABLE upload_batches ADD COLUMN IF NOT EXISTS uploaded_by_user_id UUID;
ALTER TABLE upload_batches ADD COLUMN IF NOT EXISTS file_name VARCHAR(255);
ALTER TABLE upload_batches ADD COLUMN IF NOT EXISTS total_rows INT DEFAULT 0;
ALTER TABLE upload_batches ADD COLUMN IF NOT EXISTS validated_rows INT DEFAULT 0;
ALTER TABLE upload_batches ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'pending';
ALTER TABLE upload_batches ADD COLUMN IF NOT EXISTS uploaded_at TIMESTAMPTZ DEFAULT NOW();

CREATE TABLE IF NOT EXISTS pending_members (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    batch_id UUID,
    institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE,
    full_name TEXT NOT NULL,
    email TEXT NOT NULL,
    student_id TEXT,
    student_number TEXT,
    course TEXT,
    year_level TEXT,
    contact_number TEXT,
    phone TEXT,
    member_type TEXT DEFAULT 'new',
    status TEXT DEFAULT 'pending',
    error_message TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE pending_members ADD COLUMN IF NOT EXISTS batch_id UUID;
ALTER TABLE pending_members ADD COLUMN IF NOT EXISTS institution_id UUID;
ALTER TABLE pending_members ADD COLUMN IF NOT EXISTS full_name TEXT;
ALTER TABLE pending_members ADD COLUMN IF NOT EXISTS email TEXT;
ALTER TABLE pending_members ADD COLUMN IF NOT EXISTS student_id TEXT;
ALTER TABLE pending_members ADD COLUMN IF NOT EXISTS student_number TEXT;
ALTER TABLE pending_members ADD COLUMN IF NOT EXISTS course TEXT;
ALTER TABLE pending_members ADD COLUMN IF NOT EXISTS year_level TEXT;
ALTER TABLE pending_members ADD COLUMN IF NOT EXISTS contact_number TEXT;
ALTER TABLE pending_members ADD COLUMN IF NOT EXISTS phone TEXT;
ALTER TABLE pending_members ADD COLUMN IF NOT EXISTS member_type TEXT DEFAULT 'new';
ALTER TABLE pending_members ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'pending';
ALTER TABLE pending_members ADD COLUMN IF NOT EXISTS error_message TEXT;
ALTER TABLE pending_members ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE pending_members ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_pending_members_inst ON pending_members(institution_id);
CREATE INDEX IF NOT EXISTS idx_pending_members_batch ON pending_members(batch_id);

CREATE TABLE IF NOT EXISTS member_applications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE,
    full_name TEXT NOT NULL,
    email TEXT NOT NULL,
    student_id TEXT,
    course TEXT,
    year_level TEXT,
    contact_number TEXT,
    status TEXT DEFAULT 'pending',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE member_applications ADD COLUMN IF NOT EXISTS institution_id UUID;
ALTER TABLE member_applications ADD COLUMN IF NOT EXISTS full_name TEXT;
ALTER TABLE member_applications ADD COLUMN IF NOT EXISTS email TEXT;
ALTER TABLE member_applications ADD COLUMN IF NOT EXISTS student_id TEXT;
ALTER TABLE member_applications ADD COLUMN IF NOT EXISTS course TEXT;
ALTER TABLE member_applications ADD COLUMN IF NOT EXISTS year_level TEXT;
ALTER TABLE member_applications ADD COLUMN IF NOT EXISTS contact_number TEXT;
ALTER TABLE member_applications ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'pending';
ALTER TABLE member_applications ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE member_applications ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

-- =====================================================================
-- 6. SEQUENTIAL MEMBER ID COUNTERS
-- =====================================================================
CREATE TABLE IF NOT EXISTS member_id_counter (
    id SERIAL,
    year INTEGER,
    last_number INTEGER NOT NULL DEFAULT 0,
    counter INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE member_id_counter ADD COLUMN IF NOT EXISTS year INTEGER;
ALTER TABLE member_id_counter ADD COLUMN IF NOT EXISTS last_number INTEGER DEFAULT 0;
ALTER TABLE member_id_counter ADD COLUMN IF NOT EXISTS counter INTEGER DEFAULT 0;
ALTER TABLE member_id_counter ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE member_id_counter ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();
CREATE UNIQUE INDEX IF NOT EXISTS idx_member_id_counter_year_uq ON member_id_counter(year);

CREATE TABLE IF NOT EXISTS membership_id_sequences (
    id SERIAL PRIMARY KEY,
    year INT,
    last_number INT NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE membership_id_sequences ADD COLUMN IF NOT EXISTS year INT;
ALTER TABLE membership_id_sequences ADD COLUMN IF NOT EXISTS last_number INT DEFAULT 0;
CREATE UNIQUE INDEX IF NOT EXISTS idx_mem_seq_year_uq ON membership_id_sequences(year);

-- =====================================================================
-- 7. EVENTS, ATTENDANCE & CERTIFICATES
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

ALTER TABLE events ADD COLUMN IF NOT EXISTS title TEXT;
ALTER TABLE events ADD COLUMN IF NOT EXISTS description TEXT;
ALTER TABLE events ADD COLUMN IF NOT EXISTS event_type TEXT DEFAULT 'seminar';
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
ALTER TABLE events ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'published';
ALTER TABLE events ADD COLUMN IF NOT EXISTS institution_id UUID;
ALTER TABLE events ADD COLUMN IF NOT EXISTS created_by UUID;
ALTER TABLE events ADD COLUMN IF NOT EXISTS target_roles TEXT[];
ALTER TABLE events ADD COLUMN IF NOT EXISTS venue_institution_id UUID;
ALTER TABLE events ADD COLUMN IF NOT EXISTS metadata JSONB DEFAULT '{}';
ALTER TABLE events ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE events ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_events_status ON events(status);
CREATE INDEX IF NOT EXISTS idx_events_start ON events(start_date);
CREATE INDEX IF NOT EXISTS idx_events_start_datetime ON events(start_datetime);
CREATE INDEX IF NOT EXISTS idx_events_venue_institution ON events(venue_institution_id);

-- Event Attendees (Officer Scanner & 15s Dynamic QR)
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

ALTER TABLE event_attendees ADD COLUMN IF NOT EXISTS event_id UUID;
ALTER TABLE event_attendees ADD COLUMN IF NOT EXISTS member_id UUID;
ALTER TABLE event_attendees ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'attended';
ALTER TABLE event_attendees ADD COLUMN IF NOT EXISTS check_in_time TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE event_attendees ADD COLUMN IF NOT EXISTS check_out_time TIMESTAMPTZ;
ALTER TABLE event_attendees ADD COLUMN IF NOT EXISTS qr_hash TEXT;
ALTER TABLE event_attendees ADD COLUMN IF NOT EXISTS verified_by UUID;
ALTER TABLE event_attendees ADD COLUMN IF NOT EXISTS metadata JSONB DEFAULT '{}';
ALTER TABLE event_attendees ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_att_event ON event_attendees(event_id);
CREATE INDEX IF NOT EXISTS idx_att_member ON event_attendees(member_id);
CREATE INDEX IF NOT EXISTS idx_att_status ON event_attendees(status);

-- Event Registrations Table
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

ALTER TABLE event_registrations ADD COLUMN IF NOT EXISTS event_id UUID;
ALTER TABLE event_registrations ADD COLUMN IF NOT EXISTS user_id UUID;
ALTER TABLE event_registrations ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'registered';
ALTER TABLE event_registrations ADD COLUMN IF NOT EXISTS payment_status TEXT DEFAULT 'unpaid';
ALTER TABLE event_registrations ADD COLUMN IF NOT EXISTS registered_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE event_registrations ADD COLUMN IF NOT EXISTS checked_in_at TIMESTAMPTZ;
ALTER TABLE event_registrations ADD COLUMN IF NOT EXISTS checked_out_at TIMESTAMPTZ;
ALTER TABLE event_registrations ADD COLUMN IF NOT EXISTS qr_token TEXT;

CREATE INDEX IF NOT EXISTS idx_event_reg_event ON event_registrations(event_id);
CREATE INDEX IF NOT EXISTS idx_event_reg_qr ON event_registrations(qr_token);

-- Event Attachments (Presentations, Program Materials)
CREATE TABLE IF NOT EXISTS event_attachments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_id UUID REFERENCES events(id) ON DELETE CASCADE,
    file_name TEXT,
    file_path TEXT,
    file_type TEXT,
    uploaded_by UUID,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE event_attachments ADD COLUMN IF NOT EXISTS event_id UUID;
ALTER TABLE event_attachments ADD COLUMN IF NOT EXISTS file_name TEXT;
ALTER TABLE event_attachments ADD COLUMN IF NOT EXISTS file_path TEXT;
ALTER TABLE event_attachments ADD COLUMN IF NOT EXISTS file_type TEXT;
ALTER TABLE event_attachments ADD COLUMN IF NOT EXISTS uploaded_by UUID;
ALTER TABLE event_attachments ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();

-- Attendance Logs Table (CBL compliance)
CREATE TABLE IF NOT EXISTS attendance_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL,
    event_id UUID REFERENCES events(id) ON DELETE CASCADE NOT NULL,
    timestamp TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(user_id, event_id)
);

ALTER TABLE attendance_logs ADD COLUMN IF NOT EXISTS user_id UUID;
ALTER TABLE attendance_logs ADD COLUMN IF NOT EXISTS event_id UUID;
ALTER TABLE attendance_logs ADD COLUMN IF NOT EXISTS timestamp TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_att_logs_user ON attendance_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_att_logs_event ON attendance_logs(event_id);

-- Attendance Table (Queried by attendance APIs, event-qr-checkin & ComplianceEngine)
CREATE TABLE IF NOT EXISTS attendance (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_id UUID REFERENCES events(id) ON DELETE CASCADE,
    user_id UUID,
    member_id UUID REFERENCES members(id) ON DELETE CASCADE,
    institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL,
    attended BOOLEAN DEFAULT true,
    timestamp TIMESTAMPTZ DEFAULT NOW(),
    recorded_at TIMESTAMPTZ DEFAULT NOW(),
    type TEXT DEFAULT 'checkin',
    created_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE attendance ADD COLUMN IF NOT EXISTS event_id UUID;
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS user_id UUID;
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS member_id UUID;
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS institution_id UUID;
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS attended BOOLEAN DEFAULT true;
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS timestamp TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS recorded_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS type TEXT DEFAULT 'checkin';
ALTER TABLE attendance ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_attendance_event ON attendance(event_id);
CREATE INDEX IF NOT EXISTS idx_attendance_user ON attendance(user_id);
CREATE INDEX IF NOT EXISTS idx_attendance_member ON attendance(member_id);
CREATE INDEX IF NOT EXISTS idx_attendance_inst ON attendance(institution_id);

-- Certificates Table
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

ALTER TABLE certificates ADD COLUMN IF NOT EXISTS member_id UUID;
ALTER TABLE certificates ADD COLUMN IF NOT EXISTS event_id UUID;
ALTER TABLE certificates ADD COLUMN IF NOT EXISTS issue_date DATE DEFAULT CURRENT_DATE;
ALTER TABLE certificates ADD COLUMN IF NOT EXISTS certificate_number TEXT;
ALTER TABLE certificates ADD COLUMN IF NOT EXISTS blockchain_hash TEXT;
ALTER TABLE certificates ADD COLUMN IF NOT EXISTS file_path TEXT;
ALTER TABLE certificates ADD COLUMN IF NOT EXISTS template_type TEXT DEFAULT 'participation';
ALTER TABLE certificates ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_certificates_member ON certificates(member_id);
CREATE INDEX IF NOT EXISTS idx_certificates_event ON certificates(event_id);
CREATE INDEX IF NOT EXISTS idx_certificates_number ON certificates(certificate_number);

-- =====================================================================
-- 8. BLOCKCHAIN RECORDS (SHA-256 Ledger & Merkle Trees)
-- =====================================================================
CREATE TABLE IF NOT EXISTS blockchain_records (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    block_index BIGINT,
    entity_type TEXT NOT NULL,
    entity_id UUID NOT NULL,
    record_type TEXT,
    reference_id UUID,
    transaction_hash TEXT NOT NULL,
    record_hash TEXT,
    data_hash TEXT DEFAULT '',
    previous_hash TEXT,
    merkle_root TEXT,
    data_json JSONB NOT NULL DEFAULT '{}',
    confirmed BOOLEAN DEFAULT true,
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE blockchain_records ADD COLUMN IF NOT EXISTS block_index BIGINT;
ALTER TABLE blockchain_records ADD COLUMN IF NOT EXISTS entity_type TEXT;
ALTER TABLE blockchain_records ADD COLUMN IF NOT EXISTS entity_id UUID;
ALTER TABLE blockchain_records ADD COLUMN IF NOT EXISTS record_type TEXT;
ALTER TABLE blockchain_records ADD COLUMN IF NOT EXISTS reference_id UUID;
ALTER TABLE blockchain_records ADD COLUMN IF NOT EXISTS transaction_hash TEXT;
ALTER TABLE blockchain_records ADD COLUMN IF NOT EXISTS record_hash TEXT;
ALTER TABLE blockchain_records ADD COLUMN IF NOT EXISTS data_hash TEXT DEFAULT '';
ALTER TABLE blockchain_records ADD COLUMN IF NOT EXISTS previous_hash TEXT;
ALTER TABLE blockchain_records ADD COLUMN IF NOT EXISTS merkle_root TEXT;
ALTER TABLE blockchain_records ADD COLUMN IF NOT EXISTS data_json JSONB DEFAULT '{}';
ALTER TABLE blockchain_records ADD COLUMN IF NOT EXISTS confirmed BOOLEAN DEFAULT true;
ALTER TABLE blockchain_records ADD COLUMN IF NOT EXISTS metadata JSONB DEFAULT '{}';
ALTER TABLE blockchain_records ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_bc_entity ON blockchain_records(entity_type, entity_id);
CREATE INDEX IF NOT EXISTS idx_bc_hash ON blockchain_records(transaction_hash);
CREATE INDEX IF NOT EXISTS idx_blockchain_records_record_type ON blockchain_records(record_type);
CREATE INDEX IF NOT EXISTS idx_blockchain_records_reference_id ON blockchain_records(reference_id);
CREATE INDEX IF NOT EXISTS idx_blockchain_records_data_hash ON blockchain_records(data_hash);
CREATE INDEX IF NOT EXISTS idx_blockchain_records_previous_hash ON blockchain_records(previous_hash);

-- =====================================================================
-- 9. PENDING AFFILIATIONS & REVISION REQUESTS
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

-- Ensure all columns exist before indexes (prevents 42703 contact_email error)
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
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS member_count INTEGER DEFAULT 0;
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
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_pending_aff_status ON pending_affiliations(status);
CREATE INDEX IF NOT EXISTS idx_pending_aff_email ON pending_affiliations(email);
CREATE INDEX IF NOT EXISTS idx_pending_aff_contact_email ON pending_affiliations(contact_email);
CREATE INDEX IF NOT EXISTS idx_pending_aff_institution ON pending_affiliations(institution_id);

CREATE TABLE IF NOT EXISTS institution_documents (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    institution_id UUID NOT NULL REFERENCES institutions(id) ON DELETE CASCADE,
    application_id UUID REFERENCES pending_affiliations(id) ON DELETE SET NULL,
    document_type TEXT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    uploaded_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE institution_documents ADD COLUMN IF NOT EXISTS institution_id UUID;
ALTER TABLE institution_documents ADD COLUMN IF NOT EXISTS application_id UUID;
ALTER TABLE institution_documents ADD COLUMN IF NOT EXISTS document_type TEXT;
ALTER TABLE institution_documents ADD COLUMN IF NOT EXISTS file_name VARCHAR(255);
ALTER TABLE institution_documents ADD COLUMN IF NOT EXISTS file_path VARCHAR(500);
ALTER TABLE institution_documents ADD COLUMN IF NOT EXISTS uploaded_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_institution_documents_inst ON institution_documents(institution_id);
CREATE INDEX IF NOT EXISTS idx_institution_documents_app ON institution_documents(application_id);

DO $$
BEGIN
    ALTER TABLE pending_affiliations DROP CONSTRAINT IF EXISTS pending_affiliations_status_check;
    ALTER TABLE pending_affiliations ADD CONSTRAINT pending_affiliations_status_check 
        CHECK (status IN ('pending', 'under_review', 'approved', 'rejected', 'requires_revision', 'resubmitted'));
EXCEPTION WHEN OTHERS THEN
    NULL;
END $$;

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

ALTER TABLE revision_requests ADD COLUMN IF NOT EXISTS affiliation_id UUID;
ALTER TABLE revision_requests ADD COLUMN IF NOT EXISTS token TEXT;
ALTER TABLE revision_requests ADD COLUMN IF NOT EXISTS explanation TEXT;
ALTER TABLE revision_requests ADD COLUMN IF NOT EXISTS requested_by UUID;
ALTER TABLE revision_requests ADD COLUMN IF NOT EXISTS deadline TIMESTAMPTZ;
ALTER TABLE revision_requests ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'pending';
ALTER TABLE revision_requests ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE revision_requests ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_rev_req_token ON revision_requests(token);
CREATE INDEX IF NOT EXISTS idx_rev_req_aff ON revision_requests(affiliation_id);
CREATE INDEX IF NOT EXISTS idx_rev_req_status ON revision_requests(status);

CREATE TABLE IF NOT EXISTS affiliation_documents (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    application_id UUID,
    document_type TEXT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    file_hash VARCHAR(64),
    uploaded_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE affiliation_documents ADD COLUMN IF NOT EXISTS application_id UUID;
ALTER TABLE affiliation_documents ADD COLUMN IF NOT EXISTS document_type TEXT;
ALTER TABLE affiliation_documents ADD COLUMN IF NOT EXISTS file_name VARCHAR(255);
ALTER TABLE affiliation_documents ADD COLUMN IF NOT EXISTS file_path VARCHAR(500);
ALTER TABLE affiliation_documents ADD COLUMN IF NOT EXISTS file_size INT;
ALTER TABLE affiliation_documents ADD COLUMN IF NOT EXISTS file_hash VARCHAR(64);
ALTER TABLE affiliation_documents ADD COLUMN IF NOT EXISTS uploaded_at TIMESTAMPTZ DEFAULT NOW();

-- =====================================================================
-- 10. TRANSACTIONS, TREASURY, INVOICES & PAYMENTS
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
    status TEXT DEFAULT 'completed' CHECK (status IN ('pending', 'paid', 'completed', 'verified', 'rejected', 'refunded', 'cancelled', 'canceled', 'failed')),
    notes TEXT,
    verified_by UUID,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE transactions ADD COLUMN IF NOT EXISTS transaction_id TEXT;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS user_id UUID;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS member_id UUID;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS institution_id UUID;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS event_id UUID;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS pending_affiliation_id UUID;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS amount NUMERIC(10,2) DEFAULT 0.00;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS fee_type TEXT DEFAULT 'membership_fee';
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS type TEXT DEFAULT 'payment';
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS transaction_type TEXT DEFAULT 'payment';
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS payment_method TEXT DEFAULT 'gcash';
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS reference_number TEXT;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS receipt_number TEXT;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS receipt_url TEXT;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS receipt_path TEXT;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS blockchain_hash TEXT;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'completed';
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS synchronized_at TIMESTAMPTZ NULL;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS notes TEXT;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS verified_by UUID;
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE transactions ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_tx_status ON transactions(status);
CREATE INDEX IF NOT EXISTS idx_tx_member ON transactions(member_id);
CREATE INDEX IF NOT EXISTS idx_tx_receipt_number ON transactions(receipt_number);
CREATE INDEX IF NOT EXISTS idx_tx_event_id ON transactions(event_id);
CREATE INDEX IF NOT EXISTS idx_tx_institution_status ON transactions(institution_id, status);

DO $$
BEGIN
    ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_status_check;
    ALTER TABLE transactions ADD CONSTRAINT transactions_status_check
        CHECK (status IN ('pending', 'paid', 'completed', 'verified', 'rejected', 'refunded', 'cancelled', 'canceled', 'failed'));
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

CREATE TABLE IF NOT EXISTS institution_financial_totals (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    institution_id UUID NOT NULL UNIQUE REFERENCES institutions(id) ON DELETE CASCADE,
    total_paid NUMERIC(12,2) NOT NULL DEFAULT 0,
    total_pending NUMERIC(12,2) NOT NULL DEFAULT 0,
    total_refunded NUMERIC(12,2) NOT NULL DEFAULT 0,
    total_cancelled NUMERIC(12,2) NOT NULL DEFAULT 0,
    grand_total_all_time NUMERIC(12,2) NOT NULL DEFAULT 0,
    current_year_total NUMERIC(12,2) NOT NULL DEFAULT 0,
    transaction_count INTEGER NOT NULL DEFAULT 0,
    last_synced_at TIMESTAMPTZ NULL
);

CREATE TABLE IF NOT EXISTS financial_audit_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL,
    transaction_id TEXT,
    action TEXT NOT NULL,
    old_value JSONB,
    new_value JSONB,
    performed_by UUID,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_fin_audit_institution ON financial_audit_logs(institution_id);
CREATE INDEX IF NOT EXISTS idx_fin_audit_transaction ON financial_audit_logs(transaction_id);

CREATE OR REPLACE FUNCTION audit_transaction_financial_change()
RETURNS TRIGGER AS $$
DECLARE
    old_payload JSONB;
    new_payload JSONB;
    audit_action TEXT;
BEGIN
    IF TG_OP = 'DELETE' THEN
        old_payload := jsonb_build_object('amount', OLD.amount, 'status', OLD.status, 'institution_id', OLD.institution_id, 'event_id', OLD.event_id);
        INSERT INTO financial_audit_logs (institution_id, transaction_id, action, old_value, new_value, performed_by)
        VALUES (OLD.institution_id, OLD.transaction_id, 'deleted', old_payload, NULL, COALESCE(OLD.verified_by, auth.uid()));
        RETURN OLD;
    END IF;

    new_payload := jsonb_build_object('amount', NEW.amount, 'status', NEW.status, 'institution_id', NEW.institution_id, 'event_id', NEW.event_id);
    IF TG_OP = 'INSERT' THEN
        audit_action := 'created';
    ELSIF OLD.status IS DISTINCT FROM NEW.status AND NEW.status IN ('paid', 'completed', 'verified') THEN
        audit_action := 'marked_paid';
    ELSIF OLD.status IS DISTINCT FROM NEW.status AND NEW.status = 'refunded' THEN
        audit_action := 'refunded';
    ELSE
        audit_action := 'updated';
    END IF;

    IF TG_OP = 'INSERT' OR OLD.amount IS DISTINCT FROM NEW.amount OR OLD.status IS DISTINCT FROM NEW.status OR OLD.institution_id IS DISTINCT FROM NEW.institution_id OR OLD.event_id IS DISTINCT FROM NEW.event_id THEN
        old_payload := CASE WHEN TG_OP = 'UPDATE' THEN jsonb_build_object('amount', OLD.amount, 'status', OLD.status, 'institution_id', OLD.institution_id, 'event_id', OLD.event_id) ELSE NULL END;
        INSERT INTO financial_audit_logs (institution_id, transaction_id, action, old_value, new_value, performed_by)
        VALUES (NEW.institution_id, NEW.transaction_id, audit_action, old_payload, new_payload, COALESCE(NEW.verified_by, auth.uid()));
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

DROP TRIGGER IF EXISTS trg_audit_transaction_financial_change ON transactions;
CREATE TRIGGER trg_audit_transaction_financial_change
AFTER INSERT OR UPDATE OR DELETE ON transactions
FOR EACH ROW EXECUTE FUNCTION audit_transaction_financial_change();

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

ALTER TABLE financial_records ADD COLUMN IF NOT EXISTS school_id UUID;
ALTER TABLE financial_records ADD COLUMN IF NOT EXISTS amount DECIMAL(10,2);
ALTER TABLE financial_records ADD COLUMN IF NOT EXISTS payment_type TEXT;
ALTER TABLE financial_records ADD COLUMN IF NOT EXISTS payment_status TEXT DEFAULT 'Pending';
ALTER TABLE financial_records ADD COLUMN IF NOT EXISTS proof_of_payment TEXT;
ALTER TABLE financial_records ADD COLUMN IF NOT EXISTS official_receipt_url TEXT;
ALTER TABLE financial_records ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_fin_rec_school ON financial_records(school_id);
CREATE INDEX IF NOT EXISTS idx_fin_rec_status ON financial_records(payment_status);

CREATE TABLE IF NOT EXISTS invoices (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    invoice_number VARCHAR(100) UNIQUE NOT NULL,
    member_id UUID,
    institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    issue_date DATE NOT NULL DEFAULT CURRENT_DATE,
    due_date DATE,
    pdf_path VARCHAR(500),
    status TEXT DEFAULT 'draft' CHECK (status IN ('draft', 'sent', 'paid', 'overdue', 'cancelled')),
    created_by UUID,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE invoices ADD COLUMN IF NOT EXISTS invoice_number VARCHAR(100);
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS member_id UUID;
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS institution_id UUID;
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS amount DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS description TEXT;
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS issue_date DATE DEFAULT CURRENT_DATE;
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS due_date DATE;
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS pdf_path VARCHAR(500);
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'draft';
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS created_by UUID;
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_invoices_number ON invoices(invoice_number);

CREATE TABLE IF NOT EXISTS payments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    member_id UUID,
    institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL,
    transaction_id UUID REFERENCES transactions(id) ON DELETE SET NULL,
    batch_id VARCHAR(50),
    amount DECIMAL(10,2) NOT NULL,
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'paid', 'cancelled', 'verified')),
    payment_date TIMESTAMPTZ DEFAULT NOW(),
    payment_reference VARCHAR(100),
    proof_of_payment TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE payments ADD COLUMN IF NOT EXISTS member_id UUID;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS institution_id UUID;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS transaction_id UUID;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS batch_id VARCHAR(50);
ALTER TABLE payments ADD COLUMN IF NOT EXISTS amount DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'pending';
ALTER TABLE payments ADD COLUMN IF NOT EXISTS payment_date TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE payments ADD COLUMN IF NOT EXISTS payment_reference VARCHAR(100);
ALTER TABLE payments ADD COLUMN IF NOT EXISTS proof_of_payment TEXT;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE payments ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

-- Fee Waivers & Adjustment Modules
CREATE TABLE IF NOT EXISTS fee_waivers (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE,
    requested_by UUID,
    reason TEXT NOT NULL,
    requested_amount DECIMAL(10,2) DEFAULT 0.00,
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
    reviewed_by UUID,
    reviewed_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE fee_waivers ADD COLUMN IF NOT EXISTS institution_id UUID;
ALTER TABLE fee_waivers ADD COLUMN IF NOT EXISTS requested_by UUID;
ALTER TABLE fee_waivers ADD COLUMN IF NOT EXISTS reason TEXT;
ALTER TABLE fee_waivers ADD COLUMN IF NOT EXISTS requested_amount DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE fee_waivers ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'pending';
ALTER TABLE fee_waivers ADD COLUMN IF NOT EXISTS reviewed_by UUID;
ALTER TABLE fee_waivers ADD COLUMN IF NOT EXISTS reviewed_at TIMESTAMPTZ;
ALTER TABLE fee_waivers ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE fee_waivers ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

CREATE TABLE IF NOT EXISTS fee_waiver_requests (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE,
    student_name TEXT NOT NULL,
    student_number TEXT,
    waiver_type TEXT DEFAULT 'Financial Hardship',
    reason TEXT NOT NULL,
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
    requested_by TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE fee_waiver_requests ADD COLUMN IF NOT EXISTS institution_id UUID;
ALTER TABLE fee_waiver_requests ADD COLUMN IF NOT EXISTS student_name TEXT;
ALTER TABLE fee_waiver_requests ADD COLUMN IF NOT EXISTS student_number TEXT;
ALTER TABLE fee_waiver_requests ADD COLUMN IF NOT EXISTS waiver_type TEXT DEFAULT 'Financial Hardship';
ALTER TABLE fee_waiver_requests ADD COLUMN IF NOT EXISTS reason TEXT;
ALTER TABLE fee_waiver_requests ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'pending';
ALTER TABLE fee_waiver_requests ADD COLUMN IF NOT EXISTS requested_by TEXT;
ALTER TABLE fee_waiver_requests ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE fee_waiver_requests ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

CREATE TABLE IF NOT EXISTS fee_adjustments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE,
    old_bracket_id TEXT,
    new_bracket_id TEXT,
    member_count INT,
    adjusted_at TIMESTAMPTZ DEFAULT NOW(),
    auto_adjusted BOOLEAN DEFAULT true,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE fee_adjustments ADD COLUMN IF NOT EXISTS institution_id UUID;
ALTER TABLE fee_adjustments ADD COLUMN IF NOT EXISTS old_bracket_id TEXT;
ALTER TABLE fee_adjustments ADD COLUMN IF NOT EXISTS new_bracket_id TEXT;
ALTER TABLE fee_adjustments ADD COLUMN IF NOT EXISTS member_count INT;
ALTER TABLE fee_adjustments ADD COLUMN IF NOT EXISTS adjusted_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE fee_adjustments ADD COLUMN IF NOT EXISTS auto_adjusted BOOLEAN DEFAULT true;
ALTER TABLE fee_adjustments ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_fee_adj_inst ON fee_adjustments(institution_id);

CREATE TABLE IF NOT EXISTS expenditures (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    description TEXT,
    amount DECIMAL(10,2) NOT NULL,
    category TEXT,
    institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL,
    receipt_url TEXT,
    approved_by UUID,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE expenditures ADD COLUMN IF NOT EXISTS title TEXT;
ALTER TABLE expenditures ADD COLUMN IF NOT EXISTS description TEXT;
ALTER TABLE expenditures ADD COLUMN IF NOT EXISTS amount DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE expenditures ADD COLUMN IF NOT EXISTS category TEXT;
ALTER TABLE expenditures ADD COLUMN IF NOT EXISTS institution_id UUID;
ALTER TABLE expenditures ADD COLUMN IF NOT EXISTS receipt_url TEXT;
ALTER TABLE expenditures ADD COLUMN IF NOT EXISTS approved_by UUID;
ALTER TABLE expenditures ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE expenditures ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

-- =====================================================================
-- 11. VERIFICATION CODES & EMAIL VERIFICATIONS
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

ALTER TABLE verification_codes ADD COLUMN IF NOT EXISTS email TEXT;
ALTER TABLE verification_codes ADD COLUMN IF NOT EXISTS code TEXT;
ALTER TABLE verification_codes ADD COLUMN IF NOT EXISTS purpose TEXT DEFAULT 'affiliation';
ALTER TABLE verification_codes ADD COLUMN IF NOT EXISTS expires_at TIMESTAMPTZ;
ALTER TABLE verification_codes ADD COLUMN IF NOT EXISTS used BOOLEAN DEFAULT false;
ALTER TABLE verification_codes ADD COLUMN IF NOT EXISTS verified BOOLEAN DEFAULT false;
ALTER TABLE verification_codes ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_ver_code ON verification_codes(email, code);

CREATE TABLE IF NOT EXISTS email_verifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(255) NOT NULL,
    code VARCHAR(10) NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE email_verifications ADD COLUMN IF NOT EXISTS email VARCHAR(255);
ALTER TABLE email_verifications ADD COLUMN IF NOT EXISTS code VARCHAR(10);
ALTER TABLE email_verifications ADD COLUMN IF NOT EXISTS expires_at TIMESTAMPTZ;
ALTER TABLE email_verifications ADD COLUMN IF NOT EXISTS verified BOOLEAN DEFAULT FALSE;
ALTER TABLE email_verifications ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();

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

ALTER TABLE school_profiles ADD COLUMN IF NOT EXISTS school_name TEXT;
ALTER TABLE school_profiles ADD COLUMN IF NOT EXISTS affiliation_status TEXT DEFAULT 'Pending';
ALTER TABLE school_profiles ADD COLUMN IF NOT EXISTS total_members INTEGER DEFAULT 0;
ALTER TABLE school_profiles ADD COLUMN IF NOT EXISTS institution_id UUID;
ALTER TABLE school_profiles ADD COLUMN IF NOT EXISTS validity_expiry DATE;
ALTER TABLE school_profiles ADD COLUMN IF NOT EXISTS last_renewal_date DATE;
ALTER TABLE school_profiles ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE school_profiles ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

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

ALTER TABLE compliance_docs ADD COLUMN IF NOT EXISTS school_id UUID;
ALTER TABLE compliance_docs ADD COLUMN IF NOT EXISTS doc_type TEXT;
ALTER TABLE compliance_docs ADD COLUMN IF NOT EXISTS file_url TEXT;
ALTER TABLE compliance_docs ADD COLUMN IF NOT EXISTS is_verified BOOLEAN DEFAULT false;
ALTER TABLE compliance_docs ADD COLUMN IF NOT EXISTS verified_by UUID;
ALTER TABLE compliance_docs ADD COLUMN IF NOT EXISTS verified_at TIMESTAMPTZ;
ALTER TABLE compliance_docs ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();

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

ALTER TABLE compliance_scores ADD COLUMN IF NOT EXISTS institution_id UUID;
ALTER TABLE compliance_scores ADD COLUMN IF NOT EXISTS year INT;
ALTER TABLE compliance_scores ADD COLUMN IF NOT EXISTS participation_rate NUMERIC(5,2);
ALTER TABLE compliance_scores ADD COLUMN IF NOT EXISTS hosted_event_count INT DEFAULT 0;
ALTER TABLE compliance_scores ADD COLUMN IF NOT EXISTS overall_score NUMERIC(5,2);
ALTER TABLE compliance_scores ADD COLUMN IF NOT EXISTS last_updated TIMESTAMPTZ DEFAULT NOW();
CREATE INDEX IF NOT EXISTS idx_compliance_scores_year ON compliance_scores(year);
CREATE UNIQUE INDEX IF NOT EXISTS idx_compliance_scores_inst_year_uq ON compliance_scores(institution_id, year);
ALTER TABLE compliance_scores REPLICA IDENTITY FULL;

CREATE TABLE IF NOT EXISTS compliance_rules (
    id SERIAL PRIMARY KEY,
    rule_key TEXT UNIQUE NOT NULL,
    description TEXT,
    threshold NUMERIC(5,2),
    is_active BOOLEAN DEFAULT true
);

ALTER TABLE compliance_rules ADD COLUMN IF NOT EXISTS rule_key TEXT;
ALTER TABLE compliance_rules ADD COLUMN IF NOT EXISTS description TEXT;
ALTER TABLE compliance_rules ADD COLUMN IF NOT EXISTS threshold NUMERIC(5,2);
ALTER TABLE compliance_rules ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT true;
CREATE UNIQUE INDEX IF NOT EXISTS idx_compliance_rules_key_uq ON compliance_rules(rule_key);

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

ALTER TABLE policy_compliance ADD COLUMN IF NOT EXISTS institution_id UUID;
ALTER TABLE policy_compliance ADD COLUMN IF NOT EXISTS policy_name VARCHAR(255);
ALTER TABLE policy_compliance ADD COLUMN IF NOT EXISTS policy_description TEXT;
ALTER TABLE policy_compliance ADD COLUMN IF NOT EXISTS is_compliant BOOLEAN DEFAULT FALSE;
ALTER TABLE policy_compliance ADD COLUMN IF NOT EXISTS completed_at TIMESTAMPTZ;
ALTER TABLE policy_compliance ADD COLUMN IF NOT EXISTS completed_by UUID;
ALTER TABLE policy_compliance ADD COLUMN IF NOT EXISTS notes TEXT;
ALTER TABLE policy_compliance ADD COLUMN IF NOT EXISTS due_date DATE;
ALTER TABLE policy_compliance ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE policy_compliance ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

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
ALTER TABLE merch_items ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE merch_items ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_merch_items_active ON merch_items(is_active);

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
ALTER TABLE merch_orders ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE merch_orders ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_merch_orders_member ON merch_orders(member_id);
CREATE INDEX IF NOT EXISTS idx_merch_orders_status ON merch_orders(status);

-- =====================================================================
-- 14. COMMUNICATIONS: FEATURED CARDS, ANNOUNCEMENTS, NOTIFICATIONS, MESSAGES, MEMOS, NEWSLETTERS
-- =====================================================================
CREATE TABLE IF NOT EXISTS featured_cards (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    description TEXT,
    category TEXT DEFAULT 'Announcement',
    image_url TEXT,
    link_url TEXT,
    gradient_from TEXT DEFAULT '#0B1D4A',
    gradient_to TEXT DEFAULT '#132a5e',
    button_text TEXT DEFAULT 'Learn More',
    button_url TEXT DEFAULT '#',
    button_color TEXT DEFAULT '#0B1D4A',
    badge_text TEXT,
    sort_order INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE featured_cards ADD COLUMN IF NOT EXISTS title TEXT;
ALTER TABLE featured_cards ADD COLUMN IF NOT EXISTS description TEXT;
ALTER TABLE featured_cards ADD COLUMN IF NOT EXISTS category TEXT DEFAULT 'Announcement';
ALTER TABLE featured_cards ADD COLUMN IF NOT EXISTS image_url TEXT;
ALTER TABLE featured_cards ADD COLUMN IF NOT EXISTS link_url TEXT;
ALTER TABLE featured_cards ADD COLUMN IF NOT EXISTS gradient_from TEXT DEFAULT '#0B1D4A';
ALTER TABLE featured_cards ADD COLUMN IF NOT EXISTS gradient_to TEXT DEFAULT '#132a5e';
ALTER TABLE featured_cards ADD COLUMN IF NOT EXISTS button_text TEXT DEFAULT 'Learn More';
ALTER TABLE featured_cards ADD COLUMN IF NOT EXISTS button_url TEXT DEFAULT '#';
ALTER TABLE featured_cards ADD COLUMN IF NOT EXISTS button_color TEXT DEFAULT '#0B1D4A';
ALTER TABLE featured_cards ADD COLUMN IF NOT EXISTS badge_text TEXT;
ALTER TABLE featured_cards ADD COLUMN IF NOT EXISTS sort_order INTEGER DEFAULT 0;
ALTER TABLE featured_cards ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT true;
ALTER TABLE featured_cards ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE featured_cards ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

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

ALTER TABLE announcements ADD COLUMN IF NOT EXISTS title TEXT;
ALTER TABLE announcements ADD COLUMN IF NOT EXISTS content TEXT;
ALTER TABLE announcements ADD COLUMN IF NOT EXISTS body TEXT;
ALTER TABLE announcements ADD COLUMN IF NOT EXISTS target_role TEXT DEFAULT 'all';
ALTER TABLE announcements ADD COLUMN IF NOT EXISTS target_roles TEXT[];
ALTER TABLE announcements ADD COLUMN IF NOT EXISTS target_institutions UUID[];
ALTER TABLE announcements ADD COLUMN IF NOT EXISTS priority TEXT DEFAULT 'normal';
ALTER TABLE announcements ADD COLUMN IF NOT EXISTS is_global BOOLEAN DEFAULT false;
ALTER TABLE announcements ADD COLUMN IF NOT EXISTS scheduled_at TIMESTAMPTZ;
ALTER TABLE announcements ADD COLUMN IF NOT EXISTS expires_at TIMESTAMPTZ;
ALTER TABLE announcements ADD COLUMN IF NOT EXISTS author_id UUID;
ALTER TABLE announcements ADD COLUMN IF NOT EXISTS created_by UUID;
ALTER TABLE announcements ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT true;
ALTER TABLE announcements ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_announcements_active ON announcements(is_active);

CREATE TABLE IF NOT EXISTS notifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID,
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    type TEXT DEFAULT 'info',
    action_url TEXT,
    link_url TEXT,
    institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL,
    reference_id UUID,
    is_read BOOLEAN DEFAULT false,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE notifications ADD COLUMN IF NOT EXISTS user_id UUID;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS title TEXT;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS message TEXT;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS type TEXT DEFAULT 'info';
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS action_url TEXT;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS link_url TEXT;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS institution_id UUID;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS reference_id UUID;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS is_read BOOLEAN DEFAULT false;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();

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

ALTER TABLE messages ADD COLUMN IF NOT EXISTS sender_id UUID;
ALTER TABLE messages ADD COLUMN IF NOT EXISTS receiver_id UUID;
ALTER TABLE messages ADD COLUMN IF NOT EXISTS subject VARCHAR(255);
ALTER TABLE messages ADD COLUMN IF NOT EXISTS body TEXT;
ALTER TABLE messages ADD COLUMN IF NOT EXISTS is_read BOOLEAN DEFAULT FALSE;
ALTER TABLE messages ADD COLUMN IF NOT EXISTS read_at TIMESTAMPTZ;
ALTER TABLE messages ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();

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

ALTER TABLE memoranda ADD COLUMN IF NOT EXISTS title VARCHAR(255);
ALTER TABLE memoranda ADD COLUMN IF NOT EXISTS content TEXT;
ALTER TABLE memoranda ADD COLUMN IF NOT EXISTS sent_by UUID;
ALTER TABLE memoranda ADD COLUMN IF NOT EXISTS sent_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE memoranda ADD COLUMN IF NOT EXISTS expires_at TIMESTAMPTZ;
ALTER TABLE memoranda ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE;
ALTER TABLE memoranda ADD COLUMN IF NOT EXISTS target_roles JSONB DEFAULT '[]'::jsonb;
ALTER TABLE memoranda ADD COLUMN IF NOT EXISTS target_institutions JSONB DEFAULT '[]'::jsonb;
ALTER TABLE memoranda ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE memoranda ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

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

ALTER TABLE newsletters ADD COLUMN IF NOT EXISTS subject VARCHAR(255);
ALTER TABLE newsletters ADD COLUMN IF NOT EXISTS html_content TEXT;
ALTER TABLE newsletters ADD COLUMN IF NOT EXISTS text_content TEXT;
ALTER TABLE newsletters ADD COLUMN IF NOT EXISTS sent_by UUID;
ALTER TABLE newsletters ADD COLUMN IF NOT EXISTS target_roles JSONB DEFAULT '[]'::jsonb;
ALTER TABLE newsletters ADD COLUMN IF NOT EXISTS target_institutions JSONB DEFAULT '[]'::jsonb;
ALTER TABLE newsletters ADD COLUMN IF NOT EXISTS sent_at TIMESTAMPTZ;
ALTER TABLE newsletters ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'draft';
ALTER TABLE newsletters ADD COLUMN IF NOT EXISTS scheduled_for TIMESTAMPTZ;
ALTER TABLE newsletters ADD COLUMN IF NOT EXISTS recipient_count INT DEFAULT 0;
ALTER TABLE newsletters ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE newsletters ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_newsletters_sent_by ON newsletters(sent_by);
CREATE INDEX IF NOT EXISTS idx_newsletters_status ON newsletters(status);
CREATE INDEX IF NOT EXISTS idx_newsletters_sent_at ON newsletters(sent_at DESC);

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

ALTER TABLE documents ADD COLUMN IF NOT EXISTS title VARCHAR(255);
ALTER TABLE documents ADD COLUMN IF NOT EXISTS description TEXT;
ALTER TABLE documents ADD COLUMN IF NOT EXISTS category TEXT;
ALTER TABLE documents ADD COLUMN IF NOT EXISTS file_name VARCHAR(255);
ALTER TABLE documents ADD COLUMN IF NOT EXISTS file_path VARCHAR(500);
ALTER TABLE documents ADD COLUMN IF NOT EXISTS file_size INT;
ALTER TABLE documents ADD COLUMN IF NOT EXISTS mime_type VARCHAR(100);
ALTER TABLE documents ADD COLUMN IF NOT EXISTS file_hash VARCHAR(64);
ALTER TABLE documents ADD COLUMN IF NOT EXISTS version INT DEFAULT 1;
ALTER TABLE documents ADD COLUMN IF NOT EXISTS uploaded_by UUID;
ALTER TABLE documents ADD COLUMN IF NOT EXISTS institution_id UUID;
ALTER TABLE documents ADD COLUMN IF NOT EXISTS is_public BOOLEAN DEFAULT FALSE;
ALTER TABLE documents ADD COLUMN IF NOT EXISTS expires_at TIMESTAMPTZ;
ALTER TABLE documents ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE documents ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

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

ALTER TABLE document_versions ADD COLUMN IF NOT EXISTS document_id UUID;
ALTER TABLE document_versions ADD COLUMN IF NOT EXISTS version_number INT;
ALTER TABLE document_versions ADD COLUMN IF NOT EXISTS file_name VARCHAR(255);
ALTER TABLE document_versions ADD COLUMN IF NOT EXISTS file_path VARCHAR(500);
ALTER TABLE document_versions ADD COLUMN IF NOT EXISTS file_size INT;
ALTER TABLE document_versions ADD COLUMN IF NOT EXISTS file_hash VARCHAR(64);
ALTER TABLE document_versions ADD COLUMN IF NOT EXISTS uploaded_by UUID;
ALTER TABLE document_versions ADD COLUMN IF NOT EXISTS change_notes TEXT;
ALTER TABLE document_versions ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_document_versions_document_id ON document_versions(document_id);
CREATE INDEX IF NOT EXISTS idx_document_versions_number ON document_versions(version_number);

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

ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS action TEXT;
ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS table_name TEXT;
ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS record_id TEXT;
ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS old_data JSONB;
ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS new_data JSONB;
ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS performed_by UUID;
ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS ip_address TEXT;
ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS user_agent TEXT;
ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_audit_logs_table ON audit_logs(table_name);
CREATE INDEX IF NOT EXISTS idx_audit_logs_created ON audit_logs(created_at);

CREATE TABLE IF NOT EXISTS system_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    log_level VARCHAR(50) NOT NULL,
    category VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    details JSONB DEFAULT '{}',
    ip_address VARCHAR(45),
    user_id UUID,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE system_logs ADD COLUMN IF NOT EXISTS log_level VARCHAR(50);
ALTER TABLE system_logs ADD COLUMN IF NOT EXISTS category VARCHAR(100);
ALTER TABLE system_logs ADD COLUMN IF NOT EXISTS message TEXT;
ALTER TABLE system_logs ADD COLUMN IF NOT EXISTS details JSONB DEFAULT '{}';
ALTER TABLE system_logs ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45);
ALTER TABLE system_logs ADD COLUMN IF NOT EXISTS user_id UUID;
ALTER TABLE system_logs ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_system_logs_level ON system_logs(log_level);

CREATE TABLE IF NOT EXISTS cron_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    job_id TEXT NOT NULL,
    duration NUMERIC(10,2) DEFAULT 0,
    success BOOLEAN DEFAULT true,
    output TEXT,
    triggered_by TEXT DEFAULT 'system',
    executed_at TIMESTAMPTZ DEFAULT NOW(),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE cron_logs ADD COLUMN IF NOT EXISTS job_id TEXT;
ALTER TABLE cron_logs ADD COLUMN IF NOT EXISTS duration NUMERIC(10,2) DEFAULT 0;
ALTER TABLE cron_logs ADD COLUMN IF NOT EXISTS success BOOLEAN DEFAULT true;
ALTER TABLE cron_logs ADD COLUMN IF NOT EXISTS output TEXT;
ALTER TABLE cron_logs ADD COLUMN IF NOT EXISTS triggered_by TEXT DEFAULT 'system';
ALTER TABLE cron_logs ADD COLUMN IF NOT EXISTS executed_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE cron_logs ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_cron_logs_job ON cron_logs(job_id);

-- =====================================================================
-- 16. SYSTEM SETTINGS, FEE SCHEDULES & MEMBER FEES
-- =====================================================================
CREATE TABLE IF NOT EXISTS system_settings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    key TEXT NOT NULL UNIQUE,
    value TEXT NOT NULL,
    description TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE system_settings ADD COLUMN IF NOT EXISTS key TEXT;
ALTER TABLE system_settings ADD COLUMN IF NOT EXISTS value TEXT;
ALTER TABLE system_settings ADD COLUMN IF NOT EXISTS description TEXT;
ALTER TABLE system_settings ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE system_settings ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();
CREATE UNIQUE INDEX IF NOT EXISTS idx_system_settings_key_uq ON system_settings(key);

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

ALTER TABLE fee_brackets ADD COLUMN IF NOT EXISTS bracket_name TEXT;
ALTER TABLE fee_brackets ADD COLUMN IF NOT EXISTS min_members INTEGER;
ALTER TABLE fee_brackets ADD COLUMN IF NOT EXISTS max_members INTEGER;
ALTER TABLE fee_brackets ADD COLUMN IF NOT EXISTS fee NUMERIC(10,2) DEFAULT 0.00;
ALTER TABLE fee_brackets ADD COLUMN IF NOT EXISTS per_member_fee NUMERIC(10,2) DEFAULT 0.00;
ALTER TABLE fee_brackets ADD COLUMN IF NOT EXISTS annual_fee NUMERIC(10,2) DEFAULT 0.00;
ALTER TABLE fee_brackets ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT true;
ALTER TABLE fee_brackets ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE fee_brackets ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();
CREATE UNIQUE INDEX IF NOT EXISTS idx_fee_brackets_name_uq ON fee_brackets(bracket_name);

CREATE TABLE IF NOT EXISTS member_fees (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    member_type TEXT NOT NULL UNIQUE,
    fee DECIMAL(10,2) NOT NULL,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE member_fees ADD COLUMN IF NOT EXISTS member_type TEXT;
ALTER TABLE member_fees ADD COLUMN IF NOT EXISTS fee DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE member_fees ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT true;
ALTER TABLE member_fees ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE member_fees ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();
CREATE UNIQUE INDEX IF NOT EXISTS idx_member_fees_type ON member_fees(member_type);

-- =====================================================================
-- 17. SURVEYS & EMAIL BLASTS (Enhancements Module)
-- =====================================================================
CREATE TABLE IF NOT EXISTS surveys (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    description TEXT,
    questions JSONB NOT NULL DEFAULT '[]',
    event_id UUID REFERENCES events(id) ON DELETE SET NULL,
    target_roles TEXT[] DEFAULT ARRAY['member'],
    is_active BOOLEAN DEFAULT true,
    created_by UUID,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE surveys ADD COLUMN IF NOT EXISTS title TEXT;
ALTER TABLE surveys ADD COLUMN IF NOT EXISTS description TEXT;
ALTER TABLE surveys ADD COLUMN IF NOT EXISTS questions JSONB DEFAULT '[]';
ALTER TABLE surveys ADD COLUMN IF NOT EXISTS event_id UUID;
ALTER TABLE surveys ADD COLUMN IF NOT EXISTS target_roles TEXT[] DEFAULT ARRAY['member'];
ALTER TABLE surveys ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT true;
ALTER TABLE surveys ADD COLUMN IF NOT EXISTS created_by UUID;
ALTER TABLE surveys ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE surveys ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_surveys_event ON surveys(event_id);
CREATE INDEX IF NOT EXISTS idx_surveys_active ON surveys(is_active);

CREATE TABLE IF NOT EXISTS survey_responses (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    survey_id UUID NOT NULL,
    member_id UUID NOT NULL REFERENCES members(id) ON DELETE CASCADE,
    event_id UUID REFERENCES events(id) ON DELETE SET NULL,
    answers JSONB NOT NULL DEFAULT '{}',
    submitted_at TIMESTAMPTZ DEFAULT NOW(),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE survey_responses ADD COLUMN IF NOT EXISTS survey_id UUID;
ALTER TABLE survey_responses ADD COLUMN IF NOT EXISTS member_id UUID;
ALTER TABLE survey_responses ADD COLUMN IF NOT EXISTS event_id UUID;
ALTER TABLE survey_responses ADD COLUMN IF NOT EXISTS answers JSONB DEFAULT '{}';
ALTER TABLE survey_responses ADD COLUMN IF NOT EXISTS submitted_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE survey_responses ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_survey_responses_survey ON survey_responses(survey_id);
CREATE INDEX IF NOT EXISTS idx_survey_responses_member ON survey_responses(member_id);
CREATE INDEX IF NOT EXISTS idx_survey_responses_event ON survey_responses(event_id);

CREATE TABLE IF NOT EXISTS email_blasts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    campaign_id UUID,
    subject TEXT NOT NULL,
    html_content TEXT NOT NULL,
    recipient_count INTEGER DEFAULT 0,
    sent_at TIMESTAMPTZ,
    status TEXT DEFAULT 'draft' CHECK (status IN ('draft', 'sent', 'failed', 'scheduled')),
    scheduled_for TIMESTAMPTZ,
    created_by UUID,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE email_blasts ADD COLUMN IF NOT EXISTS campaign_id UUID;
ALTER TABLE email_blasts ADD COLUMN IF NOT EXISTS subject TEXT;
ALTER TABLE email_blasts ADD COLUMN IF NOT EXISTS html_content TEXT;
ALTER TABLE email_blasts ADD COLUMN IF NOT EXISTS recipient_count INTEGER DEFAULT 0;
ALTER TABLE email_blasts ADD COLUMN IF NOT EXISTS sent_at TIMESTAMPTZ;
ALTER TABLE email_blasts ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'draft';
ALTER TABLE email_blasts ADD COLUMN IF NOT EXISTS scheduled_for TIMESTAMPTZ;
ALTER TABLE email_blasts ADD COLUMN IF NOT EXISTS created_by UUID;
ALTER TABLE email_blasts ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE email_blasts ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_email_blasts_status ON email_blasts(status);

CREATE TABLE IF NOT EXISTS email_tracking (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email_blast_id UUID,
    member_id UUID REFERENCES members(id) ON DELETE SET NULL,
    opened_at TIMESTAMPTZ,
    clicked_at TIMESTAMPTZ,
    bounce_status TEXT,
    tracking_code TEXT UNIQUE,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE email_tracking ADD COLUMN IF NOT EXISTS email_blast_id UUID;
ALTER TABLE email_tracking ADD COLUMN IF NOT EXISTS member_id UUID;
ALTER TABLE email_tracking ADD COLUMN IF NOT EXISTS opened_at TIMESTAMPTZ;
ALTER TABLE email_tracking ADD COLUMN IF NOT EXISTS clicked_at TIMESTAMPTZ;
ALTER TABLE email_tracking ADD COLUMN IF NOT EXISTS bounce_status TEXT;
ALTER TABLE email_tracking ADD COLUMN IF NOT EXISTS tracking_code TEXT;
ALTER TABLE email_tracking ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_email_tracking_blast ON email_tracking(email_blast_id);
CREATE INDEX IF NOT EXISTS idx_email_tracking_member ON email_tracking(member_id);
CREATE INDEX IF NOT EXISTS idx_email_tracking_code ON email_tracking(tracking_code);

-- =====================================================================
-- 18. AWARDS, CALENDAR ACTIVITIES, CONTACT & SECURITY
-- =====================================================================
CREATE TABLE IF NOT EXISTS awards_distinctions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    award_year VARCHAR(20) NOT NULL,
    description TEXT,
    category VARCHAR(100) DEFAULT 'Regional Recognition',
    image_url TEXT,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE awards_distinctions ADD COLUMN IF NOT EXISTS title TEXT;
ALTER TABLE awards_distinctions ADD COLUMN IF NOT EXISTS award_year VARCHAR(20);
ALTER TABLE awards_distinctions ADD COLUMN IF NOT EXISTS description TEXT;
ALTER TABLE awards_distinctions ADD COLUMN IF NOT EXISTS category VARCHAR(100);
ALTER TABLE awards_distinctions ADD COLUMN IF NOT EXISTS image_url TEXT;
ALTER TABLE awards_distinctions ADD COLUMN IF NOT EXISTS sort_order INT DEFAULT 0;
ALTER TABLE awards_distinctions ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT true;
ALTER TABLE awards_distinctions ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE awards_distinctions ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_awards_year ON awards_distinctions(award_year);

CREATE TABLE IF NOT EXISTS calendar_activities (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    venue TEXT,
    time_text TEXT,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE calendar_activities ADD COLUMN IF NOT EXISTS title TEXT;
ALTER TABLE calendar_activities ADD COLUMN IF NOT EXISTS description TEXT;
ALTER TABLE calendar_activities ADD COLUMN IF NOT EXISTS event_date DATE;
ALTER TABLE calendar_activities ADD COLUMN IF NOT EXISTS venue TEXT;
ALTER TABLE calendar_activities ADD COLUMN IF NOT EXISTS time_text TEXT;
ALTER TABLE calendar_activities ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT true;
ALTER TABLE calendar_activities ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE calendar_activities ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_cal_act_date ON calendar_activities(event_date);

CREATE TABLE IF NOT EXISTS contact_messages (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    subject TEXT,
    message TEXT NOT NULL,
    status TEXT DEFAULT 'unread' CHECK (status IN ('unread', 'read', 'replied', 'archived')),
    ip_address TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS name TEXT;
ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS email TEXT;
ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS subject TEXT;
ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS message TEXT;
ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'unread';
ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS ip_address TEXT;
ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_contact_status ON contact_messages(status);
CREATE INDEX IF NOT EXISTS idx_contact_created ON contact_messages(created_at DESC);

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL,
    subscription_json JSONB NOT NULL,
    is_active BOOLEAN DEFAULT true,
    last_notified_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE push_subscriptions ADD COLUMN IF NOT EXISTS user_id UUID;
ALTER TABLE push_subscriptions ADD COLUMN IF NOT EXISTS subscription_json JSONB;
ALTER TABLE push_subscriptions ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT true;
ALTER TABLE push_subscriptions ADD COLUMN IF NOT EXISTS last_notified_at TIMESTAMPTZ;
ALTER TABLE push_subscriptions ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();
ALTER TABLE push_subscriptions ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ DEFAULT NOW();

CREATE TABLE IF NOT EXISTS password_resets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    used BOOLEAN DEFAULT false,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

ALTER TABLE password_resets ADD COLUMN IF NOT EXISTS email VARCHAR(255);
ALTER TABLE password_resets ADD COLUMN IF NOT EXISTS token VARCHAR(255);
ALTER TABLE password_resets ADD COLUMN IF NOT EXISTS expires_at TIMESTAMPTZ;
ALTER TABLE password_resets ADD COLUMN IF NOT EXISTS used BOOLEAN DEFAULT false;
ALTER TABLE password_resets ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();

CREATE INDEX IF NOT EXISTS idx_pw_resets_token ON password_resets(token);
CREATE INDEX IF NOT EXISTS idx_pw_resets_email ON password_resets(email);

CREATE TABLE IF NOT EXISTS role_permissions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    role VARCHAR(100) NOT NULL,
    permission VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(role, permission)
);

ALTER TABLE role_permissions ADD COLUMN IF NOT EXISTS role VARCHAR(100);
ALTER TABLE role_permissions ADD COLUMN IF NOT EXISTS permission VARCHAR(100);
ALTER TABLE role_permissions ADD COLUMN IF NOT EXISTS description TEXT;
ALTER TABLE role_permissions ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ DEFAULT NOW();

-- =====================================================================
-- 19. AUTOMATED UPDATED_AT TRIGGERS
-- =====================================================================
DO $$
DECLARE
    t text;
    tables_list text[] := ARRAY[
        'institutions', 'affiliated_schools', 'users', 'auth_users', 'user_profiles',
        'members', 'member_profiles', 'member_upload_batches', 'pending_members',
        'member_applications', 'events', 'event_attendees', 'event_registrations',
        'attendance', 'blockchain_records', 'pending_affiliations', 'revision_requests',
        'transactions', 'financial_records', 'invoices', 'payments', 'fee_waivers',
        'fee_waiver_requests', 'fee_adjustments', 'expenditures', 'school_profiles',
        'policy_compliance', 'merch_items', 'merch_orders', 'featured_cards',
        'announcements', 'notifications', 'messages', 'memoranda', 'newsletters',
        'documents', 'system_settings', 'fee_brackets', 'member_fees', 'surveys',
        'email_blasts', 'awards_distinctions', 'calendar_activities', 'contact_messages',
        'push_subscriptions'
    ];
BEGIN
    FOREACH t IN ARRAY tables_list
    LOOP
        BEGIN
            EXECUTE format('DROP TRIGGER IF EXISTS trg_%I_updated_at ON %I;', t, t);
            EXECUTE format('CREATE TRIGGER trg_%I_updated_at BEFORE UPDATE ON %I FOR EACH ROW EXECUTE FUNCTION handle_updated_at();', t, t);
        EXCEPTION WHEN OTHERS THEN NULL;
        END;
    END LOOP;
END $$;

-- =====================================================================
-- 20. BUSINESS LOGIC, STORED FUNCTIONS & PROCEDURES (Full Localhost Parity)
-- =====================================================================

-- 20.1 Ensure extra compliance columns
ALTER TABLE compliance_scores ADD COLUMN IF NOT EXISTS compliance_status TEXT DEFAULT 'compliant';
ALTER TABLE blockchain_records ADD COLUMN IF NOT EXISTS payload JSONB DEFAULT '{}';
ALTER TABLE blockchain_records ADD COLUMN IF NOT EXISTS institution_id UUID;

-- 20.2 Membership ID Sequential Generator (IECEP-YYYY-XXXX)
-- Parity: process-member-batch.php, generate-membership-id.php, validate-directory.php
CREATE OR REPLACE FUNCTION generate_next_membership_id(p_year INT DEFAULT NULL)
RETURNS TEXT AS $$
DECLARE
    v_year INT;
    v_seq INT;
    v_prefix TEXT := 'IECEP';
BEGIN
    v_year := COALESCE(p_year, EXTRACT(YEAR FROM CURRENT_DATE)::INT);
    
    -- Fetch configurable prefix if defined
    BEGIN
        SELECT value INTO v_prefix FROM system_settings WHERE key = 'member_id_prefix' LIMIT 1;
        IF v_prefix IS NULL OR TRIM(v_prefix) = '' THEN
            v_prefix := 'IECEP';
        ELSE
            v_prefix := UPPER(TRIM(v_prefix));
        END IF;
    EXCEPTION WHEN OTHERS THEN
        v_prefix := 'IECEP';
    END;

    -- Atomically increment counter for the year
    INSERT INTO member_id_counter (year, last_number, counter, updated_at)
    VALUES (v_year, 1, 1, NOW())
    ON CONFLICT (year) DO UPDATE
    SET last_number = member_id_counter.last_number + 1,
        counter = member_id_counter.counter + 1,
        updated_at = NOW()
    RETURNING last_number INTO v_seq;

    -- Also keep membership_id_sequences in sync
    BEGIN
        INSERT INTO membership_id_sequences (year, last_number, updated_at)
        VALUES (v_year, v_seq, NOW())
        ON CONFLICT (year) DO UPDATE
        SET last_number = GREATEST(membership_id_sequences.last_number, v_seq),
            updated_at = NOW();
    EXCEPTION WHEN OTHERS THEN NULL;
    END;

    RETURN v_prefix || '-' || v_year::TEXT || '-' || LPAD(v_seq::TEXT, 4, '0');
END;
$$ LANGUAGE plpgsql;

-- 20.3 Auto-Assign Membership ID & Expiry Trigger Function
CREATE OR REPLACE FUNCTION trg_auto_assign_membership_id()
RETURNS TRIGGER AS $$
DECLARE
    v_target_year INT;
BEGIN
    IF NEW.membership_id IS NULL OR TRIM(NEW.membership_id) = '' THEN
        v_target_year := EXTRACT(YEAR FROM COALESCE(NEW.joined_date, CURRENT_DATE))::INT;
        NEW.membership_id := generate_next_membership_id(v_target_year);
    END IF;

    IF NEW.joined_date IS NULL THEN
        NEW.joined_date := CURRENT_DATE;
    END IF;

    IF NEW.expiration_date IS NULL THEN
        NEW.expiration_date := NEW.joined_date + INTERVAL '1 year';
    END IF;

    IF NEW.membership_expiry IS NULL THEN
        NEW.membership_expiry := NEW.expiration_date;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_members_auto_id ON members;
CREATE TRIGGER trg_members_auto_id
    BEFORE INSERT ON members
    FOR EACH ROW
    EXECUTE FUNCTION trg_auto_assign_membership_id();

-- 20.4 Synchronize Institution Member Counts
-- Parity: Member batch registration, active roster status transitions
CREATE OR REPLACE FUNCTION sync_institution_member_counts()
RETURNS TRIGGER AS $$
DECLARE
    v_inst_text TEXT;
    v_inst_id UUID;
    v_count INT;
BEGIN
    v_inst_text := COALESCE(NEW.institution_id::TEXT, OLD.institution_id::TEXT);
    IF v_inst_text IS NOT NULL AND v_inst_text ~* '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$' THEN
        BEGIN
            v_inst_id := v_inst_text::UUID;
            SELECT COUNT(*) INTO v_count
            FROM members
            WHERE institution_id::TEXT = v_inst_text
              AND status = 'active';

            UPDATE institutions
            SET membership_count = v_count,
                updated_at = NOW()
            WHERE id = v_inst_id;

            UPDATE school_profiles
            SET total_members = v_count,
                updated_at = NOW()
            WHERE institution_id::TEXT = v_inst_text;
        EXCEPTION WHEN OTHERS THEN
            NULL;
        END;
    END IF;

    RETURN COALESCE(NEW, OLD);
EXCEPTION WHEN OTHERS THEN
    RETURN COALESCE(NEW, OLD);
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_sync_institution_member_counts ON members;
CREATE TRIGGER trg_sync_institution_member_counts
    AFTER INSERT OR UPDATE OF institution_id, status OR DELETE ON members
    FOR EACH ROW
    EXECUTE FUNCTION sync_institution_member_counts();

-- 20.5 Affiliation & Member Fee Calculator
-- Parity: App\Lib\FeeCalculator, Board Resolution No. 021-2024
CREATE OR REPLACE FUNCTION calculate_affiliation_fees(
    p_member_count INT,
    p_new_members INT DEFAULT 0,
    p_returning_members INT DEFAULT 0,
    p_honorary_members INT DEFAULT 0
)
RETURNS JSONB AS $$
DECLARE
    v_national_fee NUMERIC(10,2) := 2000.00;
    v_operational_fee NUMERIC(10,2) := 800.00;
    v_new_rate NUMERIC(10,2) := 250.00;
    v_ret_rate NUMERIC(10,2) := 200.00;
    v_hon_rate NUMERIC(10,2) := 300.00;
    v_membership_total NUMERIC(10,2) := 0.00;
    v_total_fee NUMERIC(10,2) := 0.00;
BEGIN
    -- Query fee_brackets for national fee
    BEGIN
        SELECT fee INTO v_national_fee
        FROM fee_brackets
        WHERE is_active = true
          AND min_members <= p_member_count
        ORDER BY min_members DESC
        LIMIT 1;

        IF v_national_fee IS NULL THEN
            IF p_member_count <= 50 THEN v_national_fee := 1500.00;
            ELSIF p_member_count <= 100 THEN v_national_fee := 2000.00;
            ELSIF p_member_count <= 150 THEN v_national_fee := 2500.00;
            ELSE v_national_fee := 3000.00;
            END IF;
        END IF;
    EXCEPTION WHEN OTHERS THEN
        v_national_fee := 2000.00;
    END;

    -- Query operational fee from system settings
    BEGIN
        SELECT value::NUMERIC INTO v_operational_fee
        FROM system_settings
        WHERE key = 'operational_fee'
        LIMIT 1;
        IF v_operational_fee IS NULL THEN v_operational_fee := 800.00; END IF;
    EXCEPTION WHEN OTHERS THEN
        v_operational_fee := 800.00;
    END;

    -- Query member type rates from member_fees
    BEGIN
        SELECT fee INTO v_new_rate FROM member_fees WHERE member_type = 'new' AND is_active = true LIMIT 1;
        IF v_new_rate IS NULL THEN v_new_rate := 250.00; END IF;
    EXCEPTION WHEN OTHERS THEN v_new_rate := 250.00;
    END;

    BEGIN
        SELECT fee INTO v_ret_rate FROM member_fees WHERE member_type = 'returning' AND is_active = true LIMIT 1;
        IF v_ret_rate IS NULL THEN v_ret_rate := 200.00; END IF;
    EXCEPTION WHEN OTHERS THEN v_ret_rate := 200.00;
    END;

    BEGIN
        SELECT fee INTO v_hon_rate FROM member_fees WHERE member_type = 'honorary' AND is_active = true LIMIT 1;
        IF v_hon_rate IS NULL THEN v_hon_rate := 300.00; END IF;
    EXCEPTION WHEN OTHERS THEN v_hon_rate := 300.00;
    END;

    v_membership_total := (COALESCE(p_new_members, 0) * v_new_rate) +
                          (COALESCE(p_returning_members, 0) * v_ret_rate) +
                          (COALESCE(p_honorary_members, 0) * v_hon_rate);

    v_total_fee := v_national_fee + v_operational_fee + v_membership_total;

    RETURN jsonb_build_object(
        'member_count', p_member_count,
        'national_fee', ROUND(v_national_fee, 2),
        'affiliation_fee', ROUND(v_national_fee, 2),
        'operational_fee', ROUND(v_operational_fee, 2),
        'new_members', COALESCE(p_new_members, 0),
        'returning_members', COALESCE(p_returning_members, 0),
        'honorary_members', COALESCE(p_honorary_members, 0),
        'membership_fees_total', ROUND(v_membership_total, 2),
        'total_fee', ROUND(v_total_fee, 2)
    );
END;
$$ LANGUAGE plpgsql;

-- 20.6 Verification Code & OTP Validation Function
-- Parity: public/api/email.php (verifyCode), 2FA & Affiliation verification
CREATE OR REPLACE FUNCTION verify_code(
    p_email TEXT,
    p_code TEXT,
    p_type TEXT DEFAULT 'affiliation'
)
RETURNS BOOLEAN AS $$
DECLARE
    v_email TEXT;
    v_code TEXT;
    v_id UUID;
BEGIN
    v_email := LOWER(TRIM(p_email));
    v_code := TRIM(p_code);

    IF v_email = '' OR v_code = '' THEN
        RETURN FALSE;
    END IF;

    -- Check verification_codes table
    UPDATE verification_codes
    SET used = true, verified = true
    WHERE id = (
        SELECT id FROM verification_codes
        WHERE LOWER(email) = v_email
          AND code = v_code
          AND (used IS FALSE OR used IS NULL)
          AND (expires_at IS NULL OR expires_at > NOW())
        ORDER BY created_at DESC
        LIMIT 1
    )
    RETURNING id INTO v_id;

    IF v_id IS NOT NULL THEN
        RETURN TRUE;
    END IF;

    -- Check email_verifications table
    UPDATE email_verifications
    SET verified = true
    WHERE id = (
        SELECT id FROM email_verifications
        WHERE LOWER(email) = v_email
          AND code = v_code
          AND (verified IS FALSE OR verified IS NULL)
          AND (expires_at IS NULL OR expires_at > NOW())
        ORDER BY created_at DESC
        LIMIT 1
    )
    RETURNING id INTO v_id;

    RETURN (v_id IS NOT NULL);
END;
$$ LANGUAGE plpgsql;

-- 20.7 Institution CBL Compliance Calculator
-- Parity: App\Lib\ComplianceEngine, Constitution Art. V Sec. 3
CREATE OR REPLACE FUNCTION calculate_institution_compliance(
    p_institution_id UUID,
    p_year INT DEFAULT NULL
)
RETURNS JSONB AS $$
DECLARE
    v_year INT;
    v_total_members INT := 0;
    v_attended_count INT := 0;
    v_hosted_events INT := 0;
    v_part_rate NUMERIC(5,2) := 0.00;
    v_part_score NUMERIC(5,2) := 0.00;
    v_host_score NUMERIC(5,2) := 0.00;
    v_overall_score NUMERIC(5,2) := 0.00;
    v_status TEXT := 'at_risk';
BEGIN
    v_year := COALESCE(p_year, EXTRACT(YEAR FROM CURRENT_DATE)::INT);

    -- 1. Active members in chapter
    SELECT COUNT(*) INTO v_total_members
    FROM members
    WHERE institution_id = p_institution_id
      AND status = 'active';

    -- 2. Distinct attendees for events in that year
    SELECT COUNT(DISTINCT a.user_id) INTO v_attended_count
    FROM attendance a
    WHERE a.institution_id = p_institution_id
      AND EXTRACT(YEAR FROM a.created_at) = v_year;

    -- 3. Participation percentage
    IF v_total_members > 0 THEN
        v_part_rate := ROUND((v_attended_count::NUMERIC / v_total_members::NUMERIC) * 100.0, 2);
    ELSE
        v_part_rate := 0.00;
    END IF;

    -- 4. Count hosted events with completed status
    SELECT COUNT(*) INTO v_hosted_events
    FROM events
    WHERE institution_id = p_institution_id
      AND status = 'completed'
      AND EXTRACT(YEAR FROM COALESCE(start_date, created_at)) = v_year;

    -- 5. Constitution Art. V Sec. 3: Participation >= 40% AND hosted_events >= 1
    IF v_part_rate >= 40.0 AND v_hosted_events >= 1 THEN
        v_status := 'compliant';
    ELSE
        v_status := 'at_risk';
    END IF;

    v_part_score := CASE WHEN v_part_rate >= 40.0 THEN 50.0 ELSE (v_part_rate / 40.0) * 50.0 END;
    v_host_score := CASE WHEN v_hosted_events >= 1 THEN 50.0 ELSE 0.0 END;
    v_overall_score := LEAST(100.00, ROUND(v_part_score + v_host_score, 2));

    -- 6. Upsert into compliance_scores table
    INSERT INTO compliance_scores (
        institution_id, year, participation_rate, hosted_event_count,
        overall_score, compliance_status, last_updated
    )
    VALUES (
        p_institution_id, v_year, v_part_rate, v_hosted_events,
        v_overall_score, v_status, NOW()
    )
    ON CONFLICT (institution_id, year) DO UPDATE SET
        participation_rate = EXCLUDED.participation_rate,
        hosted_event_count = EXCLUDED.hosted_event_count,
        overall_score = EXCLUDED.overall_score,
        compliance_status = EXCLUDED.compliance_status,
        last_updated = NOW();

    -- 7. Sync back to institutions table
    UPDATE institutions
    SET compliance_status = v_status,
        updated_at = NOW()
    WHERE id = p_institution_id;

    RETURN jsonb_build_object(
        'institution_id', p_institution_id,
        'year', v_year,
        'total_members', v_total_members,
        'attended_count', v_attended_count,
        'participation_rate', v_part_rate,
        'hosted_events', v_hosted_events,
        'overall_score', v_overall_score,
        'compliance_status', v_status
    );
END;
$$ LANGUAGE plpgsql;

-- 20.8 Calculate Compliance for All Institutions RPC
CREATE OR REPLACE FUNCTION calculate_all_institution_compliance(p_year INT DEFAULT NULL)
RETURNS JSONB AS $$
DECLARE
    v_year INT;
    v_inst RECORD;
    v_results JSONB := '[]'::JSONB;
    v_score JSONB;
BEGIN
    v_year := COALESCE(p_year, EXTRACT(YEAR FROM CURRENT_DATE)::INT);

    FOR v_inst IN SELECT id, name FROM institutions WHERE status = 'active' ORDER BY name
    LOOP
        v_score := calculate_institution_compliance(v_inst.id, v_year);
        v_results := v_results || jsonb_build_object(
            'institution_id', v_inst.id,
            'name', v_inst.name,
            'data', v_score
        );
    END LOOP;

    RETURN v_results;
END;
$$ LANGUAGE plpgsql;

-- 20.9 User Provisioning Compatibility (Disabled trigger to prevent type mismatch error 42804; PHP handles user_profiles creation)
DROP TRIGGER IF EXISTS trg_sync_user_profile ON users;
DROP FUNCTION IF EXISTS sync_user_to_user_profile();

-- 20.10 Auto-Generate Transaction Reference & Blockchain Hash Trigger Function
-- Parity: Treasury and payment processing
CREATE OR REPLACE FUNCTION trg_auto_transaction_blockchain_hash()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.transaction_id IS NULL OR TRIM(NEW.transaction_id) = '' THEN
        NEW.transaction_id := 'TXN-' || TO_CHAR(NOW(), 'YYYYMMDD') || '-' || UPPER(SUBSTRING(gen_random_uuid()::TEXT FROM 1 FOR 8));
    END IF;

    IF NEW.reference_number IS NULL OR TRIM(NEW.reference_number) = '' THEN
        NEW.reference_number := 'REF-' || TO_CHAR(NOW(), 'YYYY') || '-' || UPPER(SUBSTRING(gen_random_uuid()::TEXT FROM 1 FOR 8));
    END IF;

    IF NEW.receipt_number IS NULL OR TRIM(NEW.receipt_number) = '' THEN
        NEW.receipt_number := 'OR-' || TO_CHAR(NOW(), 'YYYY') || '-' || UPPER(SUBSTRING(gen_random_uuid()::TEXT FROM 1 FOR 6));
    END IF;

    IF NEW.blockchain_hash IS NULL OR TRIM(NEW.blockchain_hash) = '' THEN
        NEW.blockchain_hash := encode(digest(CONCAT_WS(':', NEW.transaction_id, NEW.reference_number, NEW.amount, NEW.institution_id, clock_timestamp()), 'sha256'), 'hex');
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_tx_auto_hash ON transactions;
CREATE TRIGGER trg_tx_auto_hash
    BEFORE INSERT ON transactions
    FOR EACH ROW
    EXECUTE FUNCTION trg_auto_transaction_blockchain_hash();

-- 20.11 Cryptographic Blockchain Audit Recorder
-- Parity: App\Lib\BlockchainService (SHA-256 hash-chaining)
CREATE OR REPLACE FUNCTION record_blockchain_audit(
    p_entity_type TEXT,
    p_entity_id TEXT,
    p_institution_id UUID,
    p_payload JSONB
)
RETURNS UUID AS $$
DECLARE
    v_record_id UUID := gen_random_uuid();
    v_record_hash TEXT;
    v_tx_hash TEXT;
    v_prev_hash TEXT := '0000000000000000000000000000000000000000000000000000000000000000';
    v_block_index BIGINT;
BEGIN
    v_record_hash := encode(digest(p_payload::TEXT, 'sha256'), 'hex');
    v_tx_hash := encode(digest(CONCAT_WS(':', p_entity_type, p_entity_id, v_record_hash, clock_timestamp()), 'sha256'), 'hex');

    SELECT transaction_hash, COALESCE(block_index, 0) + 1
    INTO v_prev_hash, v_block_index
    FROM blockchain_records
    ORDER BY created_at DESC
    LIMIT 1;

    v_block_index := COALESCE(v_block_index, 1);
    v_prev_hash := COALESCE(v_prev_hash, '0000000000000000000000000000000000000000000000000000000000000000');

    INSERT INTO blockchain_records (
        id, block_index, entity_type, entity_id, transaction_hash,
        record_hash, previous_hash, confirmed, institution_id,
        data_json, payload, metadata, created_at
    )
    VALUES (
        v_record_id, v_block_index, p_entity_type, v_record_id, v_tx_hash,
        v_record_hash, v_prev_hash, true, p_institution_id,
        p_payload, p_payload, jsonb_build_object('source', 'postgres_rpc'), NOW()
    );

    RETURN v_record_id;
END;
$$ LANGUAGE plpgsql;

-- 20.12 Membership Expiry Sweep Procedure
-- Parity: cron/expire_memberships.php
CREATE OR REPLACE FUNCTION check_and_expire_memberships()
RETURNS INTEGER AS $$
DECLARE
    v_expired_count INT;
BEGIN
    UPDATE members
    SET status = 'expired',
        updated_at = NOW()
    WHERE status = 'active'
      AND (
          (expiration_date IS NOT NULL AND expiration_date < CURRENT_DATE)
          OR (membership_expiry IS NOT NULL AND membership_expiry < CURRENT_DATE)
      );

    GET DIAGNOSTICS v_expired_count = ROW_COUNT;
    RETURN v_expired_count;
END;
$$ LANGUAGE plpgsql;

-- 20.13 Institution Dashboard Stats RPC
CREATE OR REPLACE FUNCTION get_institution_dashboard_summary(p_institution_id UUID)
RETURNS JSONB AS $$
DECLARE
    v_inst RECORD;
    v_total_members INT;
    v_active_members INT;
    v_pending_members INT;
    v_completed_events INT;
    v_latest_score NUMERIC(5,2);
    v_comp_status TEXT;
BEGIN
    SELECT * INTO v_inst FROM institutions WHERE id = p_institution_id;
    IF NOT FOUND THEN
        RETURN jsonb_build_object('error', 'Institution not found');
    END IF;

    SELECT COUNT(*) INTO v_total_members FROM members WHERE institution_id = p_institution_id;
    SELECT COUNT(*) INTO v_active_members FROM members WHERE institution_id = p_institution_id AND status = 'active';
    SELECT COUNT(*) INTO v_pending_members FROM members WHERE institution_id = p_institution_id AND status = 'pending';
    SELECT COUNT(*) INTO v_completed_events FROM events WHERE institution_id = p_institution_id AND status = 'completed';

    SELECT overall_score, compliance_status
    INTO v_latest_score, v_comp_status
    FROM compliance_scores
    WHERE institution_id = p_institution_id
    ORDER BY year DESC, last_updated DESC
    LIMIT 1;

    RETURN jsonb_build_object(
        'institution_id', p_institution_id,
        'name', v_inst.name,
        'acronym', v_inst.acronym,
        'status', v_inst.status,
        'affiliation_fee_paid', v_inst.affiliation_fee_paid,
        'compliance_status', COALESCE(v_comp_status, v_inst.compliance_status, 'compliant'),
        'overall_score', COALESCE(v_latest_score, 100.00),
        'total_members', v_total_members,
        'active_members', v_active_members,
        'pending_members', v_pending_members,
        'completed_events', v_completed_events
    );
END;
$$ LANGUAGE plpgsql;

-- 20.14 Module-Specific Timestamp Trigger Functions
CREATE OR REPLACE FUNCTION update_featured_cards_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_featured_cards_updated_at ON featured_cards;
CREATE TRIGGER trg_featured_cards_updated_at
    BEFORE UPDATE ON featured_cards
    FOR EACH ROW
    EXECUTE FUNCTION update_featured_cards_updated_at();

CREATE OR REPLACE FUNCTION update_pending_affiliations_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_pending_affiliations_updated_at ON pending_affiliations;
CREATE TRIGGER trg_pending_affiliations_updated_at
    BEFORE UPDATE ON pending_affiliations
    FOR EACH ROW
    EXECUTE FUNCTION update_pending_affiliations_updated_at();

CREATE OR REPLACE FUNCTION update_school_profiles_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_school_profiles_updated_at ON school_profiles;
CREATE TRIGGER trg_school_profiles_updated_at
    BEFORE UPDATE ON school_profiles
    FOR EACH ROW
    EXECUTE FUNCTION update_school_profiles_updated_at();

-- =====================================================================
-- 21. SEED DATA: OFFICIAL LAGUNA HEI CHAPTERS (All 8 Campuses)
-- =====================================================================
DO $$
BEGIN
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
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

-- =====================================================================
-- 22. SEED DATA: OFFICIAL USERS, AUTH & PROFILES
-- =====================================================================
DO $$
BEGIN
    -- 1. Direct Auth users table
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

    -- 2. Auth Users (for seed_accounts.sql & legacy queries)
    INSERT INTO auth_users (id, email, password_hash, created_at, updated_at)
    VALUES
        ('admin-001-iecep-lsc', 'lspuscc.adminece@gmail.com', '$2y$12$mypSMbD3y1XR5uuewBIV5ONYYT3yODWWKdOINbV7/2n86Xu0PupXK', NOW(), NOW()),
        ('school-001-pupsta', 'ieceptest86@gmail.com', '$2y$12$7QzP4zCK2as87c1og7U59et9vvPHU90pCYCNXn.zM7RuH/cti.cXa', NOW(), NOW()),
        ('member-001', 'rasheddizon7@gmail.com', '$2y$12$t6adOxlvvxUJa4Lu2U6EX.R5U.2KGRTwQNeE9i51ou9Cw59Ft2vDi', NOW(), NOW())
    ON CONFLICT (id) DO UPDATE SET password_hash = EXCLUDED.password_hash, updated_at = NOW();

    -- 3. User Profiles
    INSERT INTO user_profiles (id, user_id, email, full_name, role, institution_id, phone, status, force_password_change)
    VALUES
        ('00000000-0000-0000-0000-000000000001', '00000000-0000-0000-0000-000000000001', 'lspuscc.adminece@gmail.com', 'IECEP-LSC Regional Admin', 'super_admin', '1fe48809-8ac6-4428-a6f1-3025cc47f5bb', '09171234567', 'active', false),
        ('00000000-0000-0000-0000-000000000002', '00000000-0000-0000-0000-000000000002', 'ieceptest86@gmail.com', 'LSPU - SCC School Officer', 'school_officer', '1fe48809-8ac6-4428-a6f1-3025cc47f5bb', '09181234567', 'active', false),
        ('00000000-0000-0000-0000-000000000003', '00000000-0000-0000-0000-000000000003', 'rasheddizon7@gmail.com', 'Rashed Dizon', 'member', '1fe48809-8ac6-4428-a6f1-3025cc47f5bb', '09191234567', 'active', false)
    ON CONFLICT (email) DO UPDATE SET
        full_name = EXCLUDED.full_name,
        role = EXCLUDED.role,
        institution_id = EXCLUDED.institution_id;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

-- =====================================================================
-- 23. SEED DATA: OFFICIAL MEMBERS & COUNTERS
-- =====================================================================
DO $$
BEGIN
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
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM member_id_counter WHERE year = 2026) THEN
        INSERT INTO member_id_counter (year, last_number, counter) VALUES (2026, 1, 1);
    ELSE
        UPDATE member_id_counter 
        SET last_number = GREATEST(COALESCE(last_number, 0), 1),
            counter = GREATEST(COALESCE(counter, 0), 1)
        WHERE year = 2026;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM membership_id_sequences WHERE year = 2026) THEN
        INSERT INTO membership_id_sequences (year, last_number) VALUES (2026, 1);
    ELSE
        UPDATE membership_id_sequences 
        SET last_number = GREATEST(COALESCE(last_number, 0), 1) 
        WHERE year = 2026;
    END IF;
EXCEPTION WHEN OTHERS THEN
    NULL;
END $$;

-- =====================================================================
-- 24. SEED DATA: EVENTS & ANNOUNCEMENTS
-- =====================================================================
DO $$
BEGIN
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
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

-- =====================================================================
-- 25. SEED DATA: SETTINGS, FEE SCHEDULES, MEMBER FEES, COMPLIANCE RULES, MERCH
-- =====================================================================
DO $$
BEGIN
    INSERT INTO fee_brackets (bracket_name, min_members, max_members, fee, per_member_fee, annual_fee, is_active)
    VALUES
        ('Small',      1,   50,  1500.00, 0.00, 0.00, true),
        ('Medium',    51,  100,  2000.00, 0.00, 0.00, true),
        ('Large',    101,  150,  2500.00, 0.00, 0.00, true),
        ('Enterprise', 151, 999999, 3000.00, 0.00, 0.00, true)
    ON CONFLICT (bracket_name) DO UPDATE SET 
        fee = EXCLUDED.fee,
        min_members = EXCLUDED.min_members,
        max_members = EXCLUDED.max_members,
        is_active = EXCLUDED.is_active;

    INSERT INTO member_fees (member_type, fee, is_active)
    VALUES
        ('new',       250.00, true),
        ('returning', 200.00, true),
        ('honorary',  300.00, true)
    ON CONFLICT (member_type) DO UPDATE SET
        fee = EXCLUDED.fee,
        is_active = EXCLUDED.is_active;

    INSERT INTO system_settings (key, value, description)
    VALUES
        ('operational_fee', '800.00', 'Annual organization operational fee per Board Resolution No. 021-2024'),
        ('returning_member_fee', '200.00', 'Individual membership due for returning (old) members per CBL Art. IV Sec. 2'),
        ('new_member_fee', '250.00', 'Individual membership due for new members per CBL Art. IV Sec. 2'),
        ('honorary_member_fee', '300.00', 'Individual membership due for honorary members per CBL Art. IV Sec. 2'),
        ('facebook_page_url', 'https://www.facebook.com/IECEPLSC', 'Official IECEP-LSC Facebook URL'),
        ('treasurer_email', 'treasurer@iecep-lsc.org', 'Email address for receiving monthly financial reports'),
        ('president_email', 'president@iecep-lsc.org', 'Email address for receiving monthly financial reports'),
        ('cron_secret', '', 'Secret key for protecting cron job endpoints')
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

    INSERT INTO awards_distinctions (title, award_year, description, category, is_active)
    VALUES
        ('Most Outstanding Student Chapter of the Year (Region IV-A)', '2025', 'Conferred during the IECEP National Convention for unprecedented member growth, exceptional technical seminars, and exemplary institutional governance across Laguna HEIs.', 'National Recognition', true),
        ('Excellence in Student Technical Research & Innovation', '2024', 'Awarded for premier student technical research papers and IoT embedded hardware prototypes demonstrated at the Annual Regional Electronics Engineering Symposium.', 'Research & Tech', true),
        ('PRC ECE & ECT Licensure Examination Topnotchers Plaque of Distinction', '2024', 'Honoring chapter-affiliated graduates and student alumni achieving Top 10 national ranking in the Electronics Engineering PRC Board Exams.', 'Academic Distinction', true)
    ON CONFLICT DO NOTHING;

    INSERT INTO calendar_activities (title, description, event_date, venue, time_text, is_active)
    VALUES
        ('Annual Institutional Affiliation Renewal Deadline', 'Accreditation period closing for all Higher Education Institutions in Laguna offering ECE and ECT degree curricula.', '2026-09-15', 'IECEP-LSC Portal', '11:59 PM PST', true),
        ('IECEP-LSC Regional Student Convention 2026', 'The flagship gathering of engineering students, research symposiums, technical quiz bowl, and robotics innovation challenges.', '2026-10-24', 'Laguna Provincial Capitol Cultural Center', '8:00 AM – 5:00 PM', true),
        ('TechX & IoT Embedded Systems Masterclass', 'Hands-on microcontrollers, RF protocols, firmware debugging, and smart sensing workshop led by certified industry engineers.', '2026-11-12', 'Virtual (Zoom / Live Stream)', '1:00 PM – 4:30 PM', true)
    ON CONFLICT DO NOTHING;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

-- =====================================================================
-- 26. ROW LEVEL SECURITY (RLS) POLICIES
-- =====================================================================
DO $$
DECLARE
    tbl text;
BEGIN
    FOR tbl IN 
        SELECT tablename FROM pg_tables WHERE schemaname = 'public'
    LOOP
        BEGIN
            EXECUTE format('ALTER TABLE %I ENABLE ROW LEVEL SECURITY;', tbl);
            EXECUTE format('DROP POLICY IF EXISTS "Public access on %I" ON %I;', tbl, tbl);
            EXECUTE format('CREATE POLICY "Public access on %I" ON %I FOR ALL TO public USING (true) WITH CHECK (true);', tbl, tbl);
        EXCEPTION WHEN OTHERS THEN NULL;
        END;
    END LOOP;
END $$;

-- =====================================================================
-- 27. REALTIME WEB-SOCKET SUBSCRIPTIONS
-- =====================================================================
DO $$
DECLARE
    tbl text;
    realtime_tables text[] := ARRAY[
        'notifications', 'announcements', 'events', 'event_attendees',
        'event_registrations', 'transactions', 'members', 'institutions',
        'pending_affiliations', 'revision_requests', 'merch_orders',
        'merch_items', 'messages', 'surveys', 'survey_responses',
        'featured_cards', 'attendance', 'compliance_scores'
    ];
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_publication WHERE pubname = 'supabase_realtime') THEN
        CREATE PUBLICATION supabase_realtime;
    END IF;

    FOREACH tbl IN ARRAY realtime_tables
    LOOP
        BEGIN
            EXECUTE format('ALTER PUBLICATION supabase_realtime ADD TABLE %I;', tbl);
        EXCEPTION
            WHEN duplicate_object THEN NULL;
            WHEN OTHERS THEN NULL;
        END;
    END LOOP;
EXCEPTION WHEN OTHERS THEN NULL;
END $$;

SELECT 'IECEP-LSC MEMSYS Unified Master Supabase Schema executed successfully!' AS result;
