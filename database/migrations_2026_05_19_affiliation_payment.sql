-- =====================================================================
-- IECEP-LSC MEMSYS - Affiliation Payment Workflow Migration
-- May 19, 2026
-- SOURCE: Affiliation Payment Implementation
-- =====================================================================
-- This migration depends on schema_consolidated.sql being applied first
-- =====================================================================

-- =====================================================================
-- PART 1: ADD PAYMENT COLUMNS TO pending_affiliations
-- =====================================================================

-- SOURCE: Affiliation Payment Workflow - Section 5.1
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'pending_affiliations')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'pending_affiliations' AND column_name = 'estimated_member_count') THEN
        ALTER TABLE pending_affiliations ADD COLUMN estimated_member_count INT;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'pending_affiliations')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'pending_affiliations' AND column_name = 'affiliation_fee') THEN
        ALTER TABLE pending_affiliations ADD COLUMN affiliation_fee DECIMAL(10,2);
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'pending_affiliations')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'pending_affiliations' AND column_name = 'operational_fee') THEN
        ALTER TABLE pending_affiliations ADD COLUMN operational_fee DECIMAL(10,2) DEFAULT 800.00;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'pending_affiliations')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'pending_affiliations' AND column_name = 'membership_fees_total') THEN
        ALTER TABLE pending_affiliations ADD COLUMN membership_fees_total DECIMAL(10,2);
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'pending_affiliations')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'pending_affiliations' AND column_name = 'total_fee') THEN
        ALTER TABLE pending_affiliations ADD COLUMN total_fee DECIMAL(10,2);
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'pending_affiliations')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'pending_affiliations' AND column_name = 'payment_reference') THEN
        ALTER TABLE pending_affiliations ADD COLUMN payment_reference VARCHAR(100);
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'pending_affiliations')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'pending_affiliations' AND column_name = 'receipt_number') THEN
        ALTER TABLE pending_affiliations ADD COLUMN receipt_number VARCHAR(50);
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'pending_affiliations')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'pending_affiliations' AND column_name = 'payment_status') THEN
        ALTER TABLE pending_affiliations ADD COLUMN payment_status VARCHAR(20) DEFAULT 'unpaid';
        ALTER TABLE pending_affiliations ADD CONSTRAINT chk_payment_status CHECK (payment_status IN ('unpaid', 'pending_verification', 'verified', 'failed'));
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'pending_affiliations')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'pending_affiliations' AND column_name = 'payment_simulated_at') THEN
        ALTER TABLE pending_affiliations ADD COLUMN payment_simulated_at TIMESTAMPTZ;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'pending_affiliations')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'pending_affiliations' AND column_name = 'simulation_token') THEN
        ALTER TABLE pending_affiliations ADD COLUMN simulation_token VARCHAR(100);
    END IF;
END $$;

-- =====================================================================
-- PART 2: ADD receipt_url TO transactions (Fix Issue)
-- =====================================================================

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'transactions')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'transactions' AND column_name = 'receipt_url') THEN
        ALTER TABLE transactions ADD COLUMN receipt_url TEXT;
    END IF;
END $$;

-- =====================================================================
-- PART 3: CREATE INDEXES FOR PAYMENT WORKFLOW
-- =====================================================================

CREATE INDEX IF NOT EXISTS idx_pending_affiliations_payment_status ON pending_affiliations(payment_status);
CREATE INDEX IF NOT EXISTS idx_transactions_pending_affiliation ON transactions(pending_affiliation_id);

-- =====================================================================
-- PART 4: CREATE HELPER FUNCTION FOR PAYMENT SIMULATION
-- =====================================================================

-- Generate GCash-style reference number
-- Usage: SELECT generate_payment_reference()
CREATE OR REPLACE FUNCTION generate_payment_reference()
RETURNS VARCHAR AS $$
DECLARE
    ref VARCHAR;
BEGIN
    ref := 'GCASH-' || TO_CHAR(NOW(), 'YYYYMMDD') || '-' || LPAD(CAST(FLOOR(RANDOM() * 99999) AS INT) AS VARCHAR, 5, '0');
    RETURN ref;
END;
$$ LANGUAGE plpgsql;

-- Generate receipt number
-- Usage: SELECT generate_receipt_number()
CREATE OR REPLACE FUNCTION generate_receipt_number()
RETURNS VARCHAR AS $$
DECLARE
    rec VARCHAR;
BEGIN
    rec := 'RCP-' || TO_CHAR(NOW(), 'YYYY') || '-' || LPAD(CAST(FLOOR(RANDOM() * 99999) AS INT) AS VARCHAR, 5, '0');
    RETURN rec;
END;
$$ LANGUAGE plpgsql;

-- =====================================================================
-- VERIFICATION QUERIES (for manual inspection)
-- =====================================================================

-- Verify all fee brackets are correct per Constitution Art. IV
-- SELECT min_members, max_members, fee FROM fee_brackets WHERE is_active = true ORDER BY min_members;
-- Expected output:
--  1  |  50  | 1500.00
--  51 | 100  | 2000.00
-- 101 | 150  | 2500.00
-- 151 | NULL | 3000.00

-- Verify member fee rates per Constitution Art. IV Sec. 2
-- SELECT member_type, fee FROM member_fees WHERE is_active = true ORDER BY fee DESC;
-- Expected output:
-- honorary  | 300.00
-- new       | 250.00
-- returning | 200.00

-- Verify operational fee per Constitution Art. IX Sec. 7
-- SELECT value FROM system_settings WHERE key = 'operational_fee';
-- Expected output: 800.00
