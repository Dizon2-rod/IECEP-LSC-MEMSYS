-- =====================================================
-- Migration 009: Fee Brackets & System Settings
-- Board Resolution No. 021-2024
-- PostgreSQL (Supabase) — Idempotent
-- =====================================================

-- 1. Fee Brackets Table
CREATE TABLE IF NOT EXISTS fee_brackets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    bracket_name TEXT NOT NULL UNIQUE,
    min_members INTEGER NOT NULL,
    max_members INTEGER,
    fee DECIMAL(10,2) NOT NULL,
    per_member_fee DECIMAL(10,2) DEFAULT 0.00,
    annual_fee DECIMAL(10,2) DEFAULT 0.00,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_fee_brackets_active ON fee_brackets(is_active);
CREATE INDEX IF NOT EXISTS idx_fee_brackets_min_members ON fee_brackets(min_members);

-- Upsert official fee brackets
INSERT INTO fee_brackets (bracket_name, min_members, max_members, fee, per_member_fee, annual_fee, is_active)
VALUES
    ('Small',      1,   50,  1500.00, 0.00, 0.00, true),
    ('Medium',    51,  100,  2000.00, 0.00, 0.00, true),
    ('Large',    101,  150,  2500.00, 0.00, 0.00, true),
    ('Enterprise', 151, 999999, 3000.00, 0.00, 0.00, true)
ON CONFLICT (bracket_name) DO UPDATE SET
    min_members = EXCLUDED.min_members,
    max_members = EXCLUDED.max_members,
    fee = EXCLUDED.fee,
    per_member_fee = EXCLUDED.per_member_fee,
    annual_fee = EXCLUDED.annual_fee,
    is_active = EXCLUDED.is_active;

-- 2. Member Fees Table
CREATE TABLE IF NOT EXISTS member_fees (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    member_type TEXT NOT NULL UNIQUE,
    fee DECIMAL(10,2) NOT NULL,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

INSERT INTO member_fees (member_type, fee, is_active)
VALUES
    ('new',       250.00, true),
    ('returning', 200.00, true),
    ('honorary',  300.00, true)
ON CONFLICT (member_type) DO UPDATE SET
    fee = EXCLUDED.fee,
    is_active = EXCLUDED.is_active;

-- 3. System Settings Table
CREATE TABLE IF NOT EXISTS system_settings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    key TEXT NOT NULL UNIQUE,
    value TEXT NOT NULL,
    description TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

INSERT INTO system_settings (key, value, description)
VALUES
    ('operational_fee', '800.00', 'Operational and activity fee per organization, collected upon each renewal of affiliation every new school year (Board Resolution No. 021-2024)')
ON CONFLICT (key) DO UPDATE SET
    value = EXCLUDED.value,
    description = EXCLUDED.description;

-- 4. Deactivate any stale brackets not matching official names
UPDATE fee_brackets SET is_active = false
WHERE bracket_name NOT IN ('Small', 'Medium', 'Large', 'Enterprise')
  AND is_active = true;
