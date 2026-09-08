-- ============================================================================
-- Migration: 010_email_verifications.sql
-- Description: Idempotent SQL table creation for email_verifications
-- Supports: Supabase (PostgreSQL) and XAMPP (MySQL)
-- ============================================================================

-- ----------------------------------------------------------------------------
-- SECTION A: SUPABASE (PostgreSQL)
-- ----------------------------------------------------------------------------

-- 1. Create email_verifications table
CREATE TABLE IF NOT EXISTS email_verifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(255) NOT NULL,
    code VARCHAR(10) NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- 2. Indexes for fast lookups
CREATE INDEX IF NOT EXISTS idx_email_verifications_email ON email_verifications (email);
CREATE INDEX IF NOT EXISTS idx_email_verifications_lookup ON email_verifications (email, code, verified);
CREATE INDEX IF NOT EXISTS idx_email_verifications_expires ON email_verifications (expires_at);

-- 3. Row-Level Security (RLS) for Supabase
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

-- 4. Also ensure verification_codes table has the verified column if it exists
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.tables WHERE table_name = 'verification_codes'
    ) THEN
        IF NOT EXISTS (
            SELECT 1 FROM information_schema.columns 
            WHERE table_name = 'verification_codes' AND column_name = 'verified'
        ) THEN
            ALTER TABLE verification_codes ADD COLUMN verified BOOLEAN DEFAULT FALSE;
        END IF;
    ELSE
        CREATE TABLE verification_codes (
            id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            email VARCHAR(255) NOT NULL,
            code VARCHAR(10) NOT NULL,
            purpose VARCHAR(50) DEFAULT 'affiliation',
            expires_at TIMESTAMPTZ NOT NULL,
            used BOOLEAN DEFAULT FALSE,
            verified BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMPTZ DEFAULT NOW()
        );
        CREATE INDEX idx_ver_codes_lookup ON verification_codes (email, code, used);
        ALTER TABLE verification_codes ENABLE ROW LEVEL SECURITY;
        CREATE POLICY "Allow public insert verification_codes" ON verification_codes FOR INSERT WITH CHECK (true);
        CREATE POLICY "Allow public select verification_codes" ON verification_codes FOR SELECT USING (true);
        CREATE POLICY "Allow public update verification_codes" ON verification_codes FOR UPDATE USING (true);
    END IF;
END $$;


-- ----------------------------------------------------------------------------
-- SECTION B: XAMPP / LOCALHOST (MySQL)
-- Run this section if executing directly inside phpMyAdmin or MySQL console
-- ----------------------------------------------------------------------------
/*
CREATE TABLE IF NOT EXISTS `email_verifications` (
    `id` VARCHAR(36) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `code` VARCHAR(10) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `verified` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_email` (`email`),
    INDEX `idx_lookup` (`email`, `code`, `verified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `verification_codes` (
    `id` VARCHAR(36) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `code` VARCHAR(10) NOT NULL,
    `purpose` VARCHAR(50) DEFAULT 'affiliation',
    `expires_at` DATETIME NOT NULL,
    `used` TINYINT(1) DEFAULT 0,
    `verified` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_email_code` (`email`, `code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
*/
