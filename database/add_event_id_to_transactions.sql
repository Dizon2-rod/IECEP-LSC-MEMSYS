-- Add event_id column to transactions table for linking transactions to events
-- This enables per-event income tracking in the financial dashboard

-- Check if the column exists before adding (PostgreSQL/Supabase)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_name = 'transactions' 
        AND column_name = 'event_id'
    ) THEN
        ALTER TABLE transactions 
        ADD COLUMN event_id UUID REFERENCES events(id) ON DELETE SET NULL;
    END IF;
END $$;

-- For MySQL/MariaDB, use this instead:
-- ALTER TABLE transactions 
-- ADD COLUMN IF NOT EXISTS event_id VARCHAR(36) NULL,
-- ADD CONSTRAINT fk_transactions_event_id 
-- FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE SET NULL;
