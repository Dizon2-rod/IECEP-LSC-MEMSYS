-- =====================================================================
-- IECEP-LSC MEMSYS - ADDITIONAL TABLES FOR NEW MODULES
-- PostgreSQL (Supabase) Database Setup
-- =====================================================================

-- =====================================================================
-- MESSAGES TABLE (Module 9.2)
-- =====================================================================
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
CREATE INDEX IF NOT EXISTS idx_messages_is_read ON messages(is_read);
CREATE INDEX IF NOT EXISTS idx_messages_created_at ON messages(created_at DESC);

-- =====================================================================
-- MEMORANDA TABLE (Module 6.2)
-- =====================================================================
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

CREATE INDEX IF NOT EXISTS idx_memoranda_sent_by ON memoranda(sent_by);
CREATE INDEX IF NOT EXISTS idx_memoranda_sent_at ON memoranda(sent_at DESC);
CREATE INDEX IF NOT EXISTS idx_memoranda_is_active ON memoranda(is_active);

-- =====================================================================
-- DOCUMENTS TABLE WITH VERSION TRACKING (Module 6.1, 8.1, 8.2)
-- =====================================================================
CREATE TABLE IF NOT EXISTS documents (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category TEXT NOT NULL CHECK (category IN ('affiliation', 'member_records', 'financial', 'compliance', 'memoranda', 'policy', 'constitution', 'bylaws', 'other')),
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
CREATE INDEX IF NOT EXISTS idx_documents_uploaded_by ON documents(uploaded_by);
CREATE INDEX IF NOT EXISTS idx_documents_institution ON documents(institution_id);
CREATE INDEX IF NOT EXISTS idx_documents_version ON documents(version);
CREATE INDEX IF NOT EXISTS idx_documents_file_hash ON documents(file_hash);

-- =====================================================================
-- DOCUMENT VERSIONS TABLE (Module 8.2)
-- =====================================================================
CREATE TABLE IF NOT EXISTS document_versions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    document_id UUID NOT NULL,
    version_number INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    file_hash VARCHAR(64),
    uploaded_by UUID,
    change_notes TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_document_versions_document_id ON document_versions(document_id);
CREATE INDEX IF NOT EXISTS idx_document_versions_number ON document_versions(version_number);
CREATE INDEX IF NOT EXISTS idx_document_versions_created_at ON document_versions(created_at DESC);

-- =====================================================================
-- POLICY COMPLIANCE TABLE (Module 6.3)
-- =====================================================================
CREATE TABLE IF NOT EXISTS policy_compliance (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    institution_id UUID NOT NULL,
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

CREATE INDEX IF NOT EXISTS idx_policy_compliance_institution ON policy_compliance(institution_id);
CREATE INDEX IF NOT EXISTS idx_policy_compliance_is_compliant ON policy_compliance(is_compliant);
CREATE INDEX IF NOT EXISTS idx_policy_compliance_due_date ON policy_compliance(due_date);

-- =====================================================================
-- NEWSLETTER TABLE (Module 9.3)
-- =====================================================================
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

CREATE INDEX IF NOT EXISTS idx_newsletters_sent_by ON newsletters(sent_by);
CREATE INDEX IF NOT EXISTS idx_newsletters_status ON newsletters(status);
CREATE INDEX IF NOT EXISTS idx_newsletters_sent_at ON newsletters(sent_at DESC);

-- =====================================================================
-- COMPLETION
-- =====================================================================
SELECT 'Additional tables for new modules completed' AS status;
