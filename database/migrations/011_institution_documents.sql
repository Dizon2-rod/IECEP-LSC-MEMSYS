-- =====================================================================
-- Migration 011: Institution Documents Table
-- Storage for approved affiliation requirement documents per school
-- =====================================================================

CREATE TABLE IF NOT EXISTS institution_documents (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    institution_id UUID NOT NULL REFERENCES institutions(id) ON DELETE CASCADE,
    application_id UUID REFERENCES pending_affiliations(id) ON DELETE SET NULL,
    document_type TEXT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    uploaded_at TIMESTAMPTZ DEFAULT NOW()
);

-- Ensure all columns exist
ALTER TABLE institution_documents ADD COLUMN IF NOT EXISTS institution_id UUID;
ALTER TABLE institution_documents ADD COLUMN IF NOT EXISTS application_id UUID;
ALTER TABLE institution_documents ADD COLUMN IF NOT EXISTS document_type TEXT;
ALTER TABLE institution_documents ADD COLUMN IF NOT EXISTS file_name VARCHAR(255);
ALTER TABLE institution_documents ADD COLUMN IF NOT EXISTS file_path VARCHAR(500);
ALTER TABLE institution_documents ADD COLUMN IF NOT EXISTS uploaded_at TIMESTAMPTZ DEFAULT NOW();

-- Indexes for fast query lookup
CREATE INDEX IF NOT EXISTS idx_institution_documents_inst ON institution_documents(institution_id);
CREATE INDEX IF NOT EXISTS idx_institution_documents_app ON institution_documents(application_id);
CREATE INDEX IF NOT EXISTS idx_institution_documents_type ON institution_documents(document_type);

-- Row Level Security
ALTER TABLE institution_documents ENABLE ROW LEVEL SECURITY;

-- Allow authenticated users to read institution documents
DO $$
BEGIN
    DROP POLICY IF EXISTS "Allow authenticated users to read institution documents" ON institution_documents;
    CREATE POLICY "Allow authenticated users to read institution documents" ON institution_documents
        FOR SELECT
        USING (auth.role() = 'authenticated');
EXCEPTION WHEN OTHERS THEN
    NULL;
END $$;

-- Allow admins and system service role to insert/update institution documents
DO $$
BEGIN
    DROP POLICY IF EXISTS "Allow admin and service role manage institution documents" ON institution_documents;
    CREATE POLICY "Allow admin and service role manage institution documents" ON institution_documents
        FOR ALL
        USING (auth.role() IN ('authenticated', 'service_role'));
EXCEPTION WHEN OTHERS THEN
    NULL;
END $$;
