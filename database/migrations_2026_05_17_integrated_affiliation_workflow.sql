-- =====================================================
-- INTEGRATED AFFILIATION WORKFLOW MIGRATION
-- Date: 2026-05-17
-- Purpose: Integrate member directory upload into affiliation workflow
-- =====================================================

-- Table: affiliation_documents - Store each of the 6 required documents
CREATE TABLE IF NOT EXISTS affiliation_documents (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    application_id UUID NOT NULL REFERENCES pending_affiliations(id) ON DELETE CASCADE,
    document_type TEXT NOT NULL CHECK (document_type IN (
        'letter_of_intent',
        'endorsement_letter',
        'constitution_by_laws',
        'officers_cvs',
        'organizational_chart',
        'member_directory'
    )),
    file_name TEXT NOT NULL,
    file_path TEXT NOT NULL,
    file_size INTEGER,
    mime_type TEXT,
    is_verified BOOLEAN DEFAULT false,
    verified_by UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    verified_at TIMESTAMPTZ,
    uploaded_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(application_id, document_type)
);

CREATE INDEX IF NOT EXISTS idx_affiliation_documents_application ON affiliation_documents(application_id);
CREATE INDEX IF NOT EXISTS idx_affiliation_documents_type ON affiliation_documents(document_type);
CREATE INDEX IF NOT EXISTS idx_affiliation_documents_verified ON affiliation_documents(is_verified);

-- Update member_directory_imports to link to application instead of batch
ALTER TABLE IF EXISTS membership_directory_imports 
    ADD COLUMN IF NOT EXISTS application_id UUID REFERENCES pending_affiliations(id) ON DELETE CASCADE;

CREATE INDEX IF NOT EXISTS idx_membership_directory_imports_application ON membership_directory_imports(application_id);

-- Add directory_validated flag to pending_affiliations
ALTER TABLE IF EXISTS pending_affiliations 
    ADD COLUMN IF NOT EXISTS all_documents_verified BOOLEAN DEFAULT false,
    ADD COLUMN IF NOT EXISTS directory_validated BOOLEAN DEFAULT false,
    ADD COLUMN IF NOT EXISTS directory_validated_at TIMESTAMPTZ,
    ADD COLUMN IF NOT EXISTS directory_validated_by UUID REFERENCES auth.users(id) ON DELETE SET NULL;

-- Update upload_batches to link to affiliation application
ALTER TABLE IF EXISTS upload_batches 
    ADD COLUMN IF NOT EXISTS application_id UUID REFERENCES pending_affiliations(id) ON DELETE CASCADE;

CREATE INDEX IF NOT EXISTS idx_upload_batches_application ON upload_batches(application_id);

-- Enable RLS
ALTER TABLE affiliation_documents ENABLE ROW LEVEL SECURITY;

-- Service role full access
DROP POLICY IF EXISTS service_role_full_access_affiliation_documents ON affiliation_documents;
CREATE POLICY service_role_full_access_affiliation_documents ON affiliation_documents FOR ALL USING (true) WITH CHECK (true);

-- Authenticated users can view their own documents
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
