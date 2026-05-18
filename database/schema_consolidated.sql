-- =====================================================================
-- IECEP-LSC MEMSYS - COMPLETE CONSOLIDATED PRODUCTION SCHEMA v2
-- Supabase PostgreSQL Database Setup - Fully Idempotent
-- SOURCE: Constitution Art. IV, VIII, IX + Concept Paper
-- Created: May 19, 2026
-- =====================================================================

-- =====================================================================
-- EXTENSIONS
-- =====================================================================
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- =====================================================================
-- CORE FUNCTIONS
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

-- =====================================================================
-- LEVEL 1: CORE ENTITIES (No dependencies)
-- =====================================================================

-- SOURCE: Constitution Art. IV
CREATE TABLE IF NOT EXISTS institutions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    acronym TEXT,
    type TEXT NOT NULL CHECK (type IN ('university', 'college', 'institute', 'school', 'organization')),
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
CREATE TRIGGER update_institutions_updated_at BEFORE UPDATE ON institutions FOR EACH ROW EXECUTE FUNCTION handle_updated_at();

-- SOURCE: Concept Paper §Affiliate Portal
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
CREATE TRIGGER update_affiliated_schools_updated_at BEFORE UPDATE ON affiliated_schools FOR EACH ROW EXECUTE FUNCTION handle_updated_at();

-- SOURCE: Concept Paper §Chapter Collaborations
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
CREATE TRIGGER update_partner_chapters_updated_at BEFORE UPDATE ON partner_chapters FOR EACH ROW EXECUTE FUNCTION handle_updated_at();

-- =====================================================================
-- SYSTEM TABLES - FEES & COMPLIANCE
-- =====================================================================

-- SOURCE: Constitution Art. IV Sec. 1 - Affiliation Fee Brackets
CREATE TABLE IF NOT EXISTS fee_brackets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    min_members INTEGER NOT NULL,
    max_members INTEGER,
    fee DECIMAL(10,2) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

INSERT INTO fee_brackets (min_members, max_members, fee, is_active) VALUES
    (1, 50, 1500.00, true),
    (51, 100, 2000.00, true),
    (101, 150, 2500.00, true),
    (151, NULL, 3000.00, true)
ON CONFLICT DO NOTHING;

CREATE INDEX IF NOT EXISTS idx_fee_brackets_min_max ON fee_brackets(min_members, max_members);
DROP TRIGGER IF EXISTS update_fee_brackets_updated_at ON fee_brackets;
CREATE TRIGGER update_fee_brackets_updated_at BEFORE UPDATE ON fee_brackets FOR EACH ROW EXECUTE FUNCTION handle_updated_at();

-- SOURCE: Constitution Art. IV Sec. 2 - Member Fees
CREATE TABLE IF NOT EXISTS member_fees (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    member_type TEXT NOT NULL UNIQUE CHECK (member_type IN ('new', 'returning', 'honorary')),
    fee DECIMAL(10,2) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

INSERT INTO member_fees (member_type, fee, is_active) VALUES
    ('new', 250.00, true),
    ('returning', 200.00, true),
    ('honorary', 300.00, true)
ON CONFLICT (member_type) DO NOTHING;

DROP TRIGGER IF EXISTS update_member_fees_updated_at ON member_fees;
CREATE TRIGGER update_member_fees_updated_at BEFORE UPDATE ON member_fees FOR EACH ROW EXECUTE FUNCTION handle_updated_at();

-- SOURCE: Constitution Art. IX Sec. 7 - System Settings
CREATE TABLE IF NOT EXISTS system_settings (
    key VARCHAR PRIMARY KEY,
    value TEXT,
    description TEXT,
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

INSERT INTO system_settings (key, value, description) VALUES
    ('operational_fee', '800.00', 'Fixed operational fee per affiliation'),
    ('app_name', 'IECEP-LSC MEMSYS', 'System name'),
    ('academic_year', '2025-2026', 'Current academic year')
ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value;

-- SOURCE: Constitution Art. V Sec. 3 - Compliance Rules
CREATE TABLE IF NOT EXISTS compliance_rules (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    min_participation_rate DECIMAL(5,4) DEFAULT 0.40,
    required_hosted_events INT DEFAULT 1,
    academic_year TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

INSERT INTO compliance_rules (min_participation_rate, required_hosted_events, academic_year) VALUES
    (0.40, 1, '2025-2026')
ON CONFLICT DO NOTHING;

DROP TRIGGER IF EXISTS update_compliance_rules_updated_at ON compliance_rules;
CREATE TRIGGER update_compliance_rules_updated_at BEFORE UPDATE ON compliance_rules FOR EACH ROW EXECUTE FUNCTION handle_updated_at();

-- =====================================================================
-- LEVEL 2: USER PROFILES & ROLES
-- =====================================================================

-- SOURCE: Concept Paper §User Management + Constitution Art. VIII Sec. 1
CREATE TABLE IF NOT EXISTS user_profiles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE UNIQUE,
    institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL,
    full_name TEXT,
    school_name TEXT,
    contact_phone TEXT,
    address TEXT,
    role TEXT NOT NULL CHECK (role IN ('super_admin', 'admin', 'treasurer', 'school_officer', 'member')),
    membership_status TEXT,
    avatar_url TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_user_profiles_user_id ON user_profiles(user_id);
CREATE INDEX IF NOT EXISTS idx_user_profiles_role ON user_profiles(role);
CREATE INDEX IF NOT EXISTS idx_user_profiles_institution ON user_profiles(institution_id);

DROP TRIGGER IF EXISTS update_user_profiles_updated_at ON user_profiles;
CREATE TRIGGER update_user_profiles_updated_at BEFORE UPDATE ON user_profiles FOR EACH ROW EXECUTE FUNCTION handle_updated_at();

-- Fine-grained permissions (stub for future use)
CREATE TABLE IF NOT EXISTS user_permissions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES user_profiles(id) ON DELETE CASCADE,
    permission_key TEXT NOT NULL,
    granted_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_user_permissions_user ON user_permissions(user_id);

-- =====================================================================
-- LEVEL 3: MEMBERS & DIRECTORY
-- =====================================================================

-- SOURCE: Constitution Art. III + Concept Paper §Membership Directory
CREATE TABLE IF NOT EXISTS members (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE,
    membership_id TEXT UNIQUE,
    full_name TEXT NOT NULL,
    year_level TEXT,
    member_type TEXT CHECK (member_type IN ('new', 'returning', 'honorary')),
    payment_status TEXT,
    digital_id_url TEXT,
    qr_code_url TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_members_institution ON members(institution_id);
CREATE INDEX IF NOT EXISTS idx_members_membership_id ON members(membership_id);
CREATE INDEX IF NOT EXISTS idx_members_user_id ON members(user_id);

DROP TRIGGER IF EXISTS update_members_updated_at ON members;
CREATE TRIGGER update_members_updated_at BEFORE UPDATE ON members FOR EACH ROW EXECUTE FUNCTION handle_updated_at();

-- Member ID sequence tracker
CREATE TABLE IF NOT EXISTS membership_id_sequences (
    year INT PRIMARY KEY,
    last_sequence INT DEFAULT 0
);

-- Member upload batches
CREATE TABLE IF NOT EXISTS upload_batches (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE,
    uploaded_by UUID REFERENCES user_profiles(id) ON DELETE SET NULL,
    file_name TEXT,
    file_path TEXT,
    status TEXT CHECK (status IN ('pending', 'processing', 'completed', 'failed')),
    total_rows INT,
    valid_rows INT,
    invalid_rows INT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_upload_batches_institution ON upload_batches(institution_id);
CREATE INDEX IF NOT EXISTS idx_upload_batches_status ON upload_batches(status);

DROP TRIGGER IF EXISTS update_upload_batches_updated_at ON upload_batches;
CREATE TRIGGER update_upload_batches_updated_at BEFORE UPDATE ON upload_batches FOR EACH ROW EXECUTE FUNCTION handle_updated_at();

-- Member directory imports
CREATE TABLE IF NOT EXISTS member_directory_imports (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    batch_id UUID REFERENCES upload_batches(id) ON DELETE CASCADE,
    row_number INT,
    year_level TEXT,
    raw_data JSONB,
    parsed_name TEXT,
    parsed_member_type TEXT,
    validation_status TEXT,
    validation_errors JSONB,
    member_id UUID REFERENCES members(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_member_directory_imports_batch ON member_directory_imports(batch_id);
CREATE INDEX IF NOT EXISTS idx_member_directory_imports_member ON member_directory_imports(member_id);

-- =====================================================================
-- LEVEL 4: AFFILIATION WORKFLOW
-- =====================================================================

-- SOURCE: Constitution Art. IV Sec. 3 + Concept Paper §Affiliate Portal
CREATE TABLE IF NOT EXISTS pending_affiliations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE,
    applicant_id UUID REFERENCES user_profiles(id) ON DELETE SET NULL,
    rep_name TEXT NOT NULL,
    rep_email TEXT NOT NULL,
    rep_phone TEXT,
    institution_name TEXT NOT NULL,
    estimated_member_count INT,
    affiliation_fee DECIMAL(10,2),
    operational_fee DECIMAL(10,2) DEFAULT 800.00,
    membership_fees_total DECIMAL(10,2),
    total_fee DECIMAL(10,2),
    payment_reference VARCHAR(100),
    receipt_number VARCHAR(50),
    payment_status VARCHAR(20) DEFAULT 'unpaid' CHECK (payment_status IN ('unpaid', 'pending_verification', 'verified', 'failed')),
    payment_simulated_at TIMESTAMPTZ,
    simulation_token VARCHAR(100),
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'under_review', 'approved', 'rejected', 'requires_revision')),
    submitted_at TIMESTAMPTZ,
    notes TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_pending_affiliations_institution ON pending_affiliations(institution_id);
CREATE INDEX IF NOT EXISTS idx_pending_affiliations_status ON pending_affiliations(status);
CREATE INDEX IF NOT EXISTS idx_pending_affiliations_payment_status ON pending_affiliations(payment_status);

DROP TRIGGER IF EXISTS update_pending_affiliations_updated_at ON pending_affiliations;
CREATE TRIGGER update_pending_affiliations_updated_at BEFORE UPDATE ON pending_affiliations FOR EACH ROW EXECUTE FUNCTION handle_updated_at();

-- Affiliation documents
CREATE TABLE IF NOT EXISTS affiliation_documents (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    application_id UUID REFERENCES pending_affiliations(id) ON DELETE CASCADE,
    document_type TEXT CHECK (document_type IN ('letter_of_intent', 'endorsement_letter', 'constitution_bylaws', 'officers_cv', 'org_chart', 'member_directory')),
    file_path TEXT,
    file_name TEXT,
    file_size INT,
    mime_type TEXT,
    is_verified BOOLEAN DEFAULT FALSE,
    verified_by UUID REFERENCES user_profiles(id) ON DELETE SET NULL,
    verified_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_affiliation_documents_application ON affiliation_documents(application_id);
CREATE INDEX IF NOT EXISTS idx_affiliation_documents_verified ON affiliation_documents(is_verified);

-- Affiliation approvals (legacy - retained for backward compatibility)
-- LEGACY: used by older admin approval flow; do not write new code against this table
CREATE TABLE IF NOT EXISTS affiliation_approvals (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    pending_affiliation_id UUID REFERENCES pending_affiliations(id) ON DELETE CASCADE,
    approver_id UUID REFERENCES user_profiles(id) ON DELETE SET NULL,
    approval_status TEXT,
    notes TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_affiliation_approvals_application ON affiliation_approvals(pending_affiliation_id);
CREATE INDEX IF NOT EXISTS idx_affiliation_approvals_approver ON affiliation_approvals(approver_id);

DROP TRIGGER IF EXISTS update_affiliation_approvals_updated_at ON affiliation_approvals;
CREATE TRIGGER update_affiliation_approvals_updated_at BEFORE UPDATE ON affiliation_approvals FOR EACH ROW EXECUTE FUNCTION handle_updated_at();

-- =====================================================================
-- LEVEL 5: FINANCIAL MANAGEMENT
-- =====================================================================

-- SOURCE: Constitution Art. IX Sec. 7 + Concept Paper §Financial Transparency
CREATE TABLE IF NOT EXISTS transactions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL,
    member_id UUID REFERENCES members(id) ON DELETE SET NULL,
    pending_affiliation_id UUID REFERENCES pending_affiliations(id) ON DELETE SET NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method TEXT,
    payment_reference VARCHAR(100),
    receipt_number VARCHAR(50),
    receipt_url TEXT,
    transaction_type TEXT CHECK (transaction_type IN ('membership_fee', 'affiliation_fee', 'operational_fee', 'event_fee', 'donation', 'refund')),
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'completed', 'failed', 'refunded')),
    blockchain_hash TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_transactions_institution ON transactions(institution_id);
CREATE INDEX IF NOT EXISTS idx_transactions_member ON transactions(member_id);
CREATE INDEX IF NOT EXISTS idx_transactions_pending_affiliation ON transactions(pending_affiliation_id);
CREATE INDEX IF NOT EXISTS idx_transactions_status ON transactions(status);

DROP TRIGGER IF EXISTS update_transactions_updated_at ON transactions;
CREATE TRIGGER update_transactions_updated_at BEFORE UPDATE ON transactions FOR EACH ROW EXECUTE FUNCTION handle_updated_at();

-- Invoices
CREATE TABLE IF NOT EXISTS invoices (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id UUID REFERENCES transactions(id) ON DELETE CASCADE,
    institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE,
    line_items JSONB,
    subtotal DECIMAL(10,2),
    total DECIMAL(10,2),
    issued_at TIMESTAMPTZ,
    due_at TIMESTAMPTZ,
    paid_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_invoices_transaction ON invoices(transaction_id);
CREATE INDEX IF NOT EXISTS idx_invoices_institution ON invoices(institution_id);

-- Budgets
CREATE TABLE IF NOT EXISTS budgets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    institution_id UUID REFERENCES institutions(id) ON DELETE CASCADE,
    fiscal_year TEXT,
    category TEXT,
    allocated_amount DECIMAL(10,2),
    spent_amount DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_budgets_institution ON budgets(institution_id);

-- Payment gateway logs
CREATE TABLE IF NOT EXISTS payment_gateway_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    transaction_id UUID REFERENCES transactions(id) ON DELETE CASCADE,
    gateway TEXT,
    request_payload JSONB,
    response_payload JSONB,
    http_status INT,
    logged_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_payment_gateway_logs_transaction ON payment_gateway_logs(transaction_id);

-- =====================================================================
-- LEVEL 6: EVENTS & ATTENDANCE
-- =====================================================================

-- SOURCE: Constitution Art. V + Concept Paper §Events
CREATE TABLE IF NOT EXISTS events (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL,
    title TEXT NOT NULL,
    description TEXT,
    event_type TEXT,
    start_at TIMESTAMPTZ,
    end_at TIMESTAMPTZ,
    venue TEXT,
    registration_fee DECIMAL(10,2),
    max_participants INT,
    status TEXT DEFAULT 'draft' CHECK (status IN ('draft', 'published', 'cancelled', 'completed')),
    created_by UUID REFERENCES user_profiles(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_events_institution ON events(institution_id);
CREATE INDEX IF NOT EXISTS idx_events_status ON events(status);

DROP TRIGGER IF EXISTS update_events_updated_at ON events;
CREATE TRIGGER update_events_updated_at BEFORE UPDATE ON events FOR EACH ROW EXECUTE FUNCTION handle_updated_at();

CREATE TABLE IF NOT EXISTS event_registrations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_id UUID REFERENCES events(id) ON DELETE CASCADE,
    member_id UUID REFERENCES members(id) ON DELETE CASCADE,
    registered_at TIMESTAMPTZ DEFAULT NOW(),
    check_in_at TIMESTAMPTZ,
    qr_token TEXT,
    status TEXT CHECK (status IN ('registered', 'attended', 'cancelled'))
);

CREATE INDEX IF NOT EXISTS idx_event_registrations_event ON event_registrations(event_id);
CREATE INDEX IF NOT EXISTS idx_event_registrations_member ON event_registrations(member_id);

CREATE TABLE IF NOT EXISTS attendance (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_id UUID REFERENCES events(id) ON DELETE CASCADE,
    member_id UUID REFERENCES members(id) ON DELETE CASCADE,
    check_in_at TIMESTAMPTZ,
    check_out_at TIMESTAMPTZ,
    recorded_by UUID REFERENCES user_profiles(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_attendance_event ON attendance(event_id);
CREATE INDEX IF NOT EXISTS idx_attendance_member ON attendance(member_id);

CREATE TABLE IF NOT EXISTS certificates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_id UUID REFERENCES events(id) ON DELETE CASCADE,
    member_id UUID REFERENCES members(id) ON DELETE CASCADE,
    certificate_number TEXT,
    issued_at TIMESTAMPTZ,
    file_url TEXT,
    blockchain_hash TEXT
);

CREATE INDEX IF NOT EXISTS idx_certificates_event ON certificates(event_id);
CREATE INDEX IF NOT EXISTS idx_certificates_member ON certificates(member_id);

-- =====================================================================
-- LEVEL 7: BLOCKCHAIN & AUDIT
-- =====================================================================

-- SOURCE: Concept Paper §Blockchain Integration
CREATE TABLE IF NOT EXISTS blockchain_records (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    record_type TEXT CHECK (record_type IN ('transaction', 'membership', 'compliance', 'affiliation', 'document_hash')),
    reference_id UUID,
    institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL,
    data_hash TEXT,
    previous_hash TEXT,
    merkle_root TEXT,
    signature TEXT,
    block_index BIGINT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_blockchain_records_reference ON blockchain_records(reference_id);
CREATE INDEX IF NOT EXISTS idx_blockchain_records_institution ON blockchain_records(institution_id);

-- Audit logs
CREATE TABLE IF NOT EXISTS audit_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    action TEXT NOT NULL,
    entity_type TEXT,
    entity_id UUID,
    ip_address INET,
    old_data JSONB,
    new_data JSONB,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_audit_logs_user ON audit_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_entity ON audit_logs(entity_type, entity_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_action ON audit_logs(action);
CREATE INDEX IF NOT EXISTS idx_audit_logs_created ON audit_logs(created_at);

-- =====================================================================
-- LEVEL 8: NOTIFICATIONS & PWA
-- =====================================================================

-- SOURCE: Concept Paper §Notifications, §PWA
CREATE TABLE IF NOT EXISTS notifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE,
    title TEXT,
    body TEXT,
    action_url TEXT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_read ON notifications(is_read);

DROP TRIGGER IF EXISTS update_notifications_is_read ON notifications;
-- Note: No automatic updated_at needed for notifications as they're mostly read-only

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE,
    endpoint TEXT NOT NULL UNIQUE,
    p256dh TEXT,
    auth_key TEXT,
    user_agent TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_push_subscriptions_user ON push_subscriptions(user_id);

CREATE TABLE IF NOT EXISTS email_verifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE,
    token TEXT UNIQUE,
    expires_at TIMESTAMPTZ,
    verified_at TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS idx_email_verifications_user ON email_verifications(user_id);

CREATE TABLE IF NOT EXISTS password_resets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email TEXT NOT NULL,
    token TEXT UNIQUE,
    expires_at TIMESTAMPTZ,
    used_at TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS idx_password_resets_email ON password_resets(email);

-- =====================================================================
-- LEVEL 9: ANNOUNCEMENTS & CONTENT
-- =====================================================================

-- SOURCE: Concept Paper §Content Management
CREATE TABLE IF NOT EXISTS announcements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    body TEXT,
    target_roles TEXT[],
    target_institution_ids UUID[],
    published_at TIMESTAMPTZ,
    expires_at TIMESTAMPTZ,
    created_by UUID REFERENCES user_profiles(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_announcements_published ON announcements(published_at);

CREATE TABLE IF NOT EXISTS scheduled_announcements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    announcement_id UUID REFERENCES announcements(id) ON DELETE CASCADE,
    scheduled_for TIMESTAMPTZ,
    is_published BOOLEAN DEFAULT FALSE,
    published_at TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS idx_scheduled_announcements_scheduled ON scheduled_announcements(scheduled_for);

-- Creatives/Marketing stub tables
CREATE TABLE IF NOT EXISTS creatives_announcements (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT,
    content JSONB,
    created_by UUID REFERENCES user_profiles(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS creatives_graphics (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT,
    content JSONB,
    created_by UUID REFERENCES user_profiles(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS creatives_publications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT,
    content JSONB,
    created_by UUID REFERENCES user_profiles(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS creatives_team (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT,
    content JSONB,
    created_by UUID REFERENCES user_profiles(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS creatives_features (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT,
    content JSONB,
    created_by UUID REFERENCES user_profiles(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- =====================================================================
-- SECURITY: ROW LEVEL SECURITY
-- =====================================================================
ALTER TABLE user_profiles ENABLE ROW LEVEL SECURITY;
ALTER TABLE members ENABLE ROW LEVEL SECURITY;
ALTER TABLE notifications ENABLE ROW LEVEL SECURITY;
ALTER TABLE push_subscriptions ENABLE ROW LEVEL SECURITY;
ALTER TABLE transactions ENABLE ROW LEVEL SECURITY;
ALTER TABLE event_registrations ENABLE ROW LEVEL SECURITY;
ALTER TABLE pending_affiliations ENABLE ROW LEVEL SECURITY;
ALTER TABLE affiliation_documents ENABLE ROW LEVEL SECURITY;

-- Service role policies (full access for backend operations)
CREATE POLICY "service_admin_all" ON user_profiles FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "service_admin_members" ON members FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "service_admin_notifications" ON notifications FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "service_admin_subscriptions" ON push_subscriptions FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "service_admin_transactions" ON transactions FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "service_admin_events" ON event_registrations FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "service_admin_affiliations" ON pending_affiliations FOR ALL USING (true) WITH CHECK (true);
CREATE POLICY "service_admin_documents" ON affiliation_documents FOR ALL USING (true) WITH CHECK (true);

-- Authenticated user own data policies
CREATE POLICY "users_own_profile" ON user_profiles FOR SELECT USING (user_id = auth.uid());
CREATE POLICY "users_own_notifications" ON notifications FOR SELECT USING (user_id = auth.uid());
CREATE POLICY "users_update_own_notifications" ON notifications FOR UPDATE USING (user_id = auth.uid());

-- Pending affiliations read-only for applicants
CREATE POLICY "applicants_read_own_affiliations" ON pending_affiliations FOR SELECT 
    USING (rep_email = auth.email());

-- =====================================================================
-- REALTIME & REPLICATION
-- =====================================================================
BEGIN;
  DROP PUBLICATION IF EXISTS supabase_realtime CASCADE;
  CREATE PUBLICATION supabase_realtime FOR TABLE
    notifications, announcements, events, event_registrations, 
    transactions, compliance_rules;
COMMIT;

-- =====================================================================
-- SEED DATA
-- =====================================================================

-- Default institution
INSERT INTO institutions (id, email, name, type, address, status, created_at, updated_at) VALUES
    ('00000000-0000-0000-0000-000000000001', 'executive@iecep-lsc.org', 'IECEP-LSC Executive Council', 'organization', 'Laguna, Philippines', 'active', NOW(), NOW())
ON CONFLICT (id) DO NOTHING;

-- Compliance rules (already seeded above)
-- Fee brackets (already seeded above)
-- Member fees (already seeded above)
-- System settings (already seeded above)

-- =====================================================================
-- FINAL VERIFICATION
-- =====================================================================
-- Run this to verify all critical tables exist:
-- SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name;
