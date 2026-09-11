-- =====================================================
-- Migration 013: Schema Migrations Tracking & Data Sync Parity
-- Platform: Supabase (PostgreSQL) — Idempotent
-- =====================================================

-- 1. Create schema_migrations tracking table
CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(255) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    applied_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 2. Seed all migration history up to 013
INSERT INTO schema_migrations (version, name, applied_at) VALUES
    ('001', 'initial_schema', NOW()),
    ('002', 'events_compliance', NOW()),
    ('003', 'cbl_compliance_system', NOW()),
    ('004', 'auto_generate_accounts', NOW()),
    ('005', 'pending_affiliations', NOW()),
    ('006', 'member_id_counter', NOW()),
    ('007', 'verification_codes', NOW()),
    ('008', 'merchandise', NOW()),
    ('009', 'fee_brackets_system_settings', NOW()),
    ('010', 'email_verifications', NOW()),
    ('011', 'institution_documents', NOW()),
    ('012', 'align_cbl_2025', NOW()),
    ('013', 'schema_migrations_and_sync', NOW())
ON CONFLICT (version) DO NOTHING;

-- 3. Ensure schema parity columns exist across tables
ALTER TABLE institutions ADD COLUMN IF NOT EXISTS compliance_status VARCHAR(50) DEFAULT 'at_risk';

ALTER TABLE events ADD COLUMN IF NOT EXISTS venue_institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL;
CREATE INDEX IF NOT EXISTS idx_events_venue_institution ON events(venue_institution_id);

ALTER TABLE compliance_scores ADD COLUMN IF NOT EXISTS compliance_status VARCHAR(50) DEFAULT 'at_risk';
ALTER TABLE compliance_scores ADD COLUMN IF NOT EXISTS last_updated TIMESTAMPTZ DEFAULT NOW();
CREATE INDEX IF NOT EXISTS idx_compliance_scores_inst_year ON compliance_scores(institution_id, year);

ALTER TABLE email_verifications ADD COLUMN IF NOT EXISTS attempts INTEGER DEFAULT 0;
CREATE INDEX IF NOT EXISTS idx_email_verifications_email ON email_verifications(email);
