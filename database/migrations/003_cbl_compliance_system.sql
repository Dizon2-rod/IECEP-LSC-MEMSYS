-- =====================================================
-- CBL Compliance System Migration
-- Implements: School Profiles, Financial Records, 
-- Compliance Docs, and Attendance Logs
-- =====================================================

-- 1. SCHOOL PROFILES TABLE
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

-- 2. FINANCIAL RECORDS TABLE
CREATE TABLE IF NOT EXISTS financial_records (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    school_id UUID REFERENCES school_profiles(id) ON DELETE CASCADE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_type TEXT NOT NULL CHECK (payment_type IN ('Affiliation', 'Operational', 'Individual_Dues')),
    payment_status TEXT DEFAULT 'Pending' CHECK (payment_status IN ('Pending', 'Verified')),
    proof_of_payment TEXT,
    official_receipt_url TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_financial_records_school ON financial_records(school_id);
CREATE INDEX IF NOT EXISTS idx_financial_records_status ON financial_records(payment_status);

-- 3. COMPLIANCE DOCS TABLE
CREATE TABLE IF NOT EXISTS compliance_docs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    school_id UUID REFERENCES school_profiles(id) ON DELETE CASCADE NOT NULL,
    doc_type TEXT NOT NULL,
    file_url TEXT NOT NULL,
    is_verified BOOLEAN DEFAULT false,
    verified_by UUID REFERENCES user_profiles(id),
    verified_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_compliance_docs_school ON compliance_docs(school_id);
CREATE INDEX IF NOT EXISTS idx_compliance_docs_verified ON compliance_docs(is_verified);

-- 4. ATTENDANCE LOGS TABLE
CREATE TABLE IF NOT EXISTS attendance_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES auth.users(id) ON DELETE CASCADE NOT NULL,
    event_id UUID REFERENCES events(id) ON DELETE CASCADE NOT NULL,
    timestamp TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(user_id, event_id)
);

CREATE INDEX IF NOT EXISTS idx_attendance_logs_user ON attendance_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_attendance_logs_event ON attendance_logs(event_id);

-- 5. TRIGGERS
DROP TRIGGER IF EXISTS update_school_profiles_updated_at ON school_profiles;
CREATE TRIGGER update_school_profiles_updated_at
    BEFORE UPDATE ON school_profiles
    FOR EACH ROW
    EXECUTE FUNCTION handle_updated_at();

-- 6. ROW LEVEL SECURITY
ALTER TABLE school_profiles ENABLE ROW LEVEL SECURITY;
ALTER TABLE financial_records ENABLE ROW LEVEL SECURITY;
ALTER TABLE compliance_docs ENABLE ROW LEVEL SECURITY;
ALTER TABLE attendance_logs ENABLE ROW LEVEL SECURITY;

-- Service role full access
DROP POLICY IF EXISTS service_role_full_access_school_profiles ON school_profiles;
CREATE POLICY service_role_full_access_school_profiles ON school_profiles FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_financial_records ON financial_records;
CREATE POLICY service_role_full_access_financial_records ON financial_records FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_compliance_docs ON compliance_docs;
CREATE POLICY service_role_full_access_compliance_docs ON compliance_docs FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_attendance_logs ON attendance_logs;
CREATE POLICY service_role_full_access_attendance_logs ON attendance_logs FOR ALL USING (true) WITH CHECK (true);

-- Authenticated user policies
DROP POLICY IF EXISTS authenticated_can_select_school_profiles ON school_profiles;
CREATE POLICY authenticated_can_select_school_profiles ON school_profiles FOR SELECT TO authenticated USING (true);

DROP POLICY IF EXISTS authenticated_can_select_financial_records ON financial_records;
CREATE POLICY authenticated_can_select_financial_records ON financial_records FOR SELECT TO authenticated USING (true);

DROP POLICY IF EXISTS authenticated_can_select_compliance_docs ON compliance_docs;
CREATE POLICY authenticated_can_select_compliance_docs ON compliance_docs FOR SELECT TO authenticated USING (true);

DROP POLICY IF EXISTS authenticated_can_select_attendance_logs ON attendance_logs;
CREATE POLICY authenticated_can_select_attendance_logs ON attendance_logs FOR SELECT TO authenticated USING (true);
