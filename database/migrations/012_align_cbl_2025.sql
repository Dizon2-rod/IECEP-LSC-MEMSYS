-- =====================================================
-- Migration 012: Align Affiliation & Compliance with 2025 Constitution & By-Laws
-- Platform: Supabase (PostgreSQL) — Idempotent
-- Board Resolution No. 021-2024 & CBL Articles IV & V
-- =====================================================

-- 1. Ensure Fee Brackets Table and Upsert Official 2025 Brackets
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
    is_active = EXCLUDED.is_active,
    updated_at = NOW();

-- 2. Ensure Member Fees Table and Upsert 2025 Member Dues
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
    is_active = EXCLUDED.is_active,
    updated_at = NOW();

-- 3. Ensure System Settings Table and Upsert CBL 2025 Rates
CREATE TABLE IF NOT EXISTS system_settings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    key TEXT NOT NULL UNIQUE,
    value TEXT NOT NULL,
    description TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_system_settings_key ON system_settings(key);

INSERT INTO system_settings (key, value, description)
VALUES
    ('operational_fee', '800.00', 'Annual organization operational fee per Board Resolution No. 021-2024 (Art. IV)'),
    ('returning_member_fee', '200.00', 'Individual membership due for returning (old) members per CBL Art. IV Sec. 2'),
    ('new_member_fee', '250.00', 'Individual membership due for new members per CBL Art. IV Sec. 2'),
    ('honorary_member_fee', '300.00', 'Individual membership due for honorary members per CBL Art. IV Sec. 2')
ON CONFLICT (key) DO UPDATE SET
    value = EXCLUDED.value,
    description = EXCLUDED.description,
    updated_at = NOW();

-- 4. Compliance Rules Table (Art. V Sec. 3)
CREATE TABLE IF NOT EXISTS compliance_rules (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    rule_key TEXT NOT NULL UNIQUE,
    description TEXT,
    threshold NUMERIC(5,2),
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

INSERT INTO compliance_rules (rule_key, description, threshold, is_active)
VALUES 
    ('min_participation', 'Minimum participation rate required for chapter compliance (40%)', 40.00, true),
    ('required_hosted_events', 'Minimum hosted or venue events per academic year (1)', 1.00, true)
ON CONFLICT (rule_key) DO UPDATE SET
    threshold = EXCLUDED.threshold,
    description = EXCLUDED.description,
    is_active = EXCLUDED.is_active;

-- 5. Add venue_institution_id to Events Table to support venue hosting credit (Art. V Sec. 3)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'events' AND column_name = 'venue_institution_id'
    ) THEN
        ALTER TABLE events ADD COLUMN venue_institution_id UUID REFERENCES institutions(id) ON DELETE SET NULL;
        CREATE INDEX IF NOT EXISTS idx_events_venue_institution ON events(venue_institution_id);
    END IF;
END $$;

-- 6. Ensure institutions and compliance_scores support compliance_status ('compliant', 'at_risk', 'non_compliant')
ALTER TABLE institutions ADD COLUMN IF NOT EXISTS compliance_status TEXT DEFAULT 'compliant';
ALTER TABLE compliance_scores ADD COLUMN IF NOT EXISTS compliance_status TEXT DEFAULT 'compliant';

-- 7. Ensure members table has membership_expiry for 1 academic year validity
ALTER TABLE members ADD COLUMN IF NOT EXISTS membership_expiry DATE;
ALTER TABLE members ADD COLUMN IF NOT EXISTS payment_status TEXT DEFAULT 'paid';
