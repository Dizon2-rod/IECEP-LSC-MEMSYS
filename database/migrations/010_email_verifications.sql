-- ============================================================================
-- Migration: 010_email_verifications.sql
-- Description: Idempotent SQL table creation for email_verifications
-- Supports: Supabase (PostgreSQL) and XAMPP (MySQL/MariaDB)
-- Includes rate-limiting and brute-force protection columns (attempts & indexes)
-- ============================================================================

-- ----------------------------------------------------------------------------
-- SECTION A: SUPABASE (PostgreSQL)
-- Run this in the Supabase SQL Editor:
-- ----------------------------------------------------------------------------

-- 1. Create table if it doesn't already exist
CREATE TABLE IF NOT EXISTS email_verifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email TEXT NOT NULL,
    code TEXT NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    verified BOOLEAN DEFAULT false,
    attempts INTEGER DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 2. Ensure the attempts column exists if the table was created previously
ALTER TABLE email_verifications ADD COLUMN IF NOT EXISTS attempts INTEGER DEFAULT 0;

-- 3. Idempotent Index on email to support rate-limiting and fast verification lookups
CREATE INDEX IF NOT EXISTS idx_email_verifications_email ON email_verifications(email);
CREATE INDEX IF NOT EXISTS idx_email_verifications_email_code ON email_verifications(email, code);

-- 4. Enable Row Level Security (RLS) & Policies
ALTER TABLE email_verifications ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "Allow public insert for email_verifications" ON email_verifications;
CREATE POLICY "Allow public insert for email_verifications" ON email_verifications
    FOR INSERT WITH CHECK (true);

DROP POLICY IF EXISTS "Allow public select for email_verifications" ON email_verifications;
CREATE POLICY "Allow public select for email_verifications" ON email_verifications
    FOR SELECT USING (true);

DROP POLICY IF EXISTS "Allow public update for email_verifications" ON email_verifications;
CREATE POLICY "Allow public update for email_verifications" ON email_verifications
    FOR UPDATE USING (true);


-- ----------------------------------------------------------------------------
-- SECTION B: XAMPP / LOCALHOST (MySQL / MariaDB)
-- Run this in phpMyAdmin or the MySQL terminal for local database:
-- ----------------------------------------------------------------------------
/*
CREATE TABLE IF NOT EXISTS email_verifications (
    id CHAR(36) PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    code VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    verified TINYINT(1) DEFAULT 0,
    attempts INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email)
);
*/
