-- Featured cards table for IECEP-LSC MEMSYS landing page
CREATE EXTENSION IF NOT EXISTS pgcrypto;

CREATE TABLE IF NOT EXISTS featured_cards (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title TEXT NOT NULL,
    description TEXT,
    image_url TEXT,
    gradient_from TEXT DEFAULT '#0B1D4A',
    gradient_to TEXT DEFAULT '#132a5e',
    button_text TEXT DEFAULT 'Learn More',
    button_url TEXT DEFAULT '#',
    button_color TEXT DEFAULT '#0B1D4A',
    is_active BOOLEAN DEFAULT true,
    sort_order INTEGER DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE OR REPLACE FUNCTION update_featured_cards_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_update_featured_cards_updated_at ON featured_cards;
CREATE TRIGGER trg_update_featured_cards_updated_at
BEFORE UPDATE ON featured_cards
FOR EACH ROW
EXECUTE FUNCTION update_featured_cards_updated_at();

COMMENT ON TABLE featured_cards IS 'Landing page featured cards managed from the admin portal';
