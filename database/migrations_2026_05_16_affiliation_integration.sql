-- =====================================================
-- INTEGRATED AFFILIATION WORKFLOW MIGRATION
-- Date: 2026-05-16
-- Purpose: Integrate member directory into affiliation workflow
-- =====================================================

-- Table: affiliation_applications
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

-- Table: affiliation_documents
CREATE TABLE IF NOT EXISTS affiliation_documents (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    application_id UUID NOT NULL REFERENCES affiliation_applications(id) ON DELETE CASCADE,
    document_type TEXT NOT NULL CHECK (document_type IN (
        'letter_of_intent',
        'endorsement_letter',
        'constitution_bylaws',
        'officers_cv',
        'org_chart',
        'member_directory'
    )),
    filename TEXT NOT NULL,
    file_path TEXT NOT NULL,
    file_size INTEGER,
    mime_type TEXT,
    verified BOOLEAN DEFAULT false,
    verified_by UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    verified_at TIMESTAMPTZ,
    uploaded_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(application_id, document_type)
);

CREATE INDEX IF NOT EXISTS idx_affiliation_documents_application ON affiliation_documents(application_id);
CREATE INDEX IF NOT EXISTS idx_affiliation_documents_verified ON affiliation_documents(verified);

-- Table: member_directory_imports
CREATE TABLE IF NOT EXISTS member_directory_imports (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    application_id UUID NOT NULL REFERENCES affiliation_applications(id) ON DELETE CASCADE,
    sheet_name TEXT,
    row_index INTEGER,
    full_name TEXT,
    birthday_clean DATE,
    address TEXT,
    phone TEXT,
    email TEXT,
    picture_raw TEXT,
    signature_raw TEXT,
    is_valid BOOLEAN DEFAULT false,
    validation_errors TEXT,
    payment_status BOOLEAN DEFAULT false,
    is_new_member BOOLEAN DEFAULT true,
    assigned_membership_id TEXT,
    member_id UUID REFERENCES members(id) ON DELETE SET NULL,
    assigned_at TIMESTAMPTZ,
    assigned_by UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_member_directory_imports_application ON member_directory_imports(application_id);
CREATE INDEX IF NOT EXISTS idx_member_directory_imports_email ON member_directory_imports(email);
CREATE INDEX IF NOT EXISTS idx_member_directory_imports_assigned ON member_directory_imports(assigned_membership_id);

-- Table: membership_id_sequences
CREATE TABLE IF NOT EXISTS membership_id_sequences (
    id SERIAL PRIMARY KEY,
    year INTEGER NOT NULL UNIQUE,
    last_number INTEGER DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_membership_id_sequences_year ON membership_id_sequences(year);

-- Add columns to members table if they don't exist
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS school_affiliate TEXT;
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS year_level TEXT;
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS is_new BOOLEAN DEFAULT true;
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS payment_status BOOLEAN DEFAULT false;
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS validated_at TIMESTAMPTZ;
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS validated_by UUID REFERENCES auth.users(id) ON DELETE SET NULL;
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS picture_url TEXT;
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS signature_url TEXT;
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS application_id UUID REFERENCES affiliation_applications(id) ON DELETE SET NULL;
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS birthday DATE;
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS address TEXT;
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS phone TEXT;

CREATE INDEX IF NOT EXISTS idx_members_application ON members(application_id);
CREATE INDEX IF NOT EXISTS idx_members_year_level ON members(year_level);
CREATE INDEX IF NOT EXISTS idx_members_payment_status ON members(payment_status);

-- Enable RLS
ALTER TABLE affiliation_applications ENABLE ROW LEVEL SECURITY;
ALTER TABLE affiliation_documents ENABLE ROW LEVEL SECURITY;
ALTER TABLE member_directory_imports ENABLE ROW LEVEL SECURITY;

-- Service role full access
DROP POLICY IF EXISTS service_role_full_access_affiliation_applications ON affiliation_applications;
CREATE POLICY service_role_full_access_affiliation_applications ON affiliation_applications FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_affiliation_documents ON affiliation_documents;
CREATE POLICY service_role_full_access_affiliation_documents ON affiliation_documents FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS service_role_full_access_member_directory_imports ON member_directory_imports;
CREATE POLICY service_role_full_access_member_directory_imports ON member_directory_imports FOR ALL USING (true) WITH CHECK (true);

-- Authenticated users can view their own applications
DROP POLICY IF EXISTS authenticated_can_select_own_applications ON affiliation_applications;
CREATE POLICY authenticated_can_select_own_applications ON affiliation_applications 
    FOR SELECT TO authenticated 
    USING (rep_email = (SELECT email FROM auth.users WHERE id = auth.uid()));
