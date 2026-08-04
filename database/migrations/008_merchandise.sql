-- IECEP-LSC MEMSYS - Merchandise Module Migration
-- Idempotent SQL for Supabase (PostgreSQL)
-- Creates merch_items and merch_orders tables with triggers

-- ============================================================
-- 1. MERCH ITEMS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS merch_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    image_url TEXT,
    stock INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_merch_items_active ON merch_items(is_active);
CREATE INDEX IF NOT EXISTS idx_merch_items_stock ON merch_items(stock);

-- ============================================================
-- 2. MERCH ORDERS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS merch_orders (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    member_id UUID REFERENCES members(id) ON DELETE SET NULL,
    buyer_name TEXT NOT NULL,
    buyer_email TEXT NOT NULL,
    items JSONB NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending','paid','shipped','delivered','cancelled')),
    transaction_id UUID REFERENCES transactions(id) ON DELETE SET NULL,
    notes TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_merch_orders_member ON merch_orders(member_id);
CREATE INDEX IF NOT EXISTS idx_merch_orders_status ON merch_orders(status);
CREATE INDEX IF NOT EXISTS idx_merch_orders_transaction ON merch_orders(transaction_id);
CREATE INDEX IF NOT EXISTS idx_merch_orders_created ON merch_orders(created_at);

-- ============================================================
-- 3. TRIGGER FUNCTION FOR updated_at
-- ============================================================
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- ============================================================
-- 4. TRIGGERS
-- ============================================================
DROP TRIGGER IF EXISTS update_merch_items_updated_at ON merch_items;
CREATE TRIGGER update_merch_items_updated_at
    BEFORE UPDATE ON merch_items
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

DROP TRIGGER IF EXISTS update_merch_orders_updated_at ON merch_orders;
CREATE TRIGGER update_merch_orders_updated_at
    BEFORE UPDATE ON merch_orders
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

-- ============================================================
-- 5. ALTER transactions TYPE CONSTRAINT (add 'merchandise')
-- ============================================================
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'transactions_type_check'
        AND conrelid = 'transactions'::regclass
    ) THEN
        ALTER TABLE transactions DROP CONSTRAINT transactions_type_check;
    END IF;
    ALTER TABLE transactions
        ADD CONSTRAINT transactions_type_check
        CHECK (type IN ('membership_fee','event_fee','donation','refund','penalty','merchandise'));
END $$;

-- ============================================================
-- 6. ROW LEVEL SECURITY (Optional - adjust as needed)
-- ============================================================
ALTER TABLE merch_items ENABLE ROW LEVEL SECURITY;
ALTER TABLE merch_orders ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "Allow public read merch items" ON merch_items;
CREATE POLICY "Allow public read merch items" ON merch_items
    FOR SELECT USING (is_active = true AND stock > 0);

DROP POLICY IF EXISTS "Allow service role full merch items" ON merch_items;
CREATE POLICY "Allow service role full merch items" ON merch_items
    FOR ALL USING (true) WITH CHECK (true);

DROP POLICY IF EXISTS "Allow service role full merch orders" ON merch_orders;
CREATE POLICY "Allow service role full merch orders" ON merch_orders
    FOR ALL USING (true) WITH CHECK (true);

-- ============================================================
-- 7. INSERT SAMPLE DATA (if table is empty)
-- ============================================================
INSERT INTO merch_items (name, description, price, stock, is_active)
SELECT 'IECEP-LSC Chapter Shirt', 'Premium cotton chapter shirt with embroidered IECEP-LSC logo', 450.00, 50, true
WHERE NOT EXISTS (SELECT 1 FROM merch_items WHERE name = 'IECEP-LSC Chapter Shirt');

INSERT INTO merch_items (name, description, price, stock, is_active)
SELECT 'IECEP-LSC Enamel Pin', 'Collectible enamel pin with IECEP-LSC seal', 150.00, 100, true
WHERE NOT EXISTS (SELECT 1 FROM merch_items WHERE name = 'IECEP-LSC Enamel Pin');
