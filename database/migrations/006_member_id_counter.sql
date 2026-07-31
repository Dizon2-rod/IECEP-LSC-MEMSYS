-- Migration: Create member_id_counter table for tracking member IDs
-- This table maintains a counter for generating unique member IDs in format MEM-YYYY-0001

-- Create table if it doesn't exist (may already exist from supabase_complete_query.sql)
CREATE TABLE IF NOT EXISTS member_id_counter (
    id SERIAL PRIMARY KEY,
    year INT NOT NULL UNIQUE,
    counter INT NOT NULL DEFAULT 0,
    last_number INT DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Add counter column if missing (table may pre-exist with 'last_number' instead of 'counter')
DO $$ 
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                 WHERE table_name='member_id_counter' AND column_name='counter') THEN
    ALTER TABLE member_id_counter ADD COLUMN counter INT NOT NULL DEFAULT 0;
  END IF;
END $$;

-- Also add other missing columns if the table pre-exists
DO $$ 
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                 WHERE table_name='member_id_counter' AND column_name='updated_at') THEN
    ALTER TABLE member_id_counter ADD COLUMN updated_at TIMESTAMPTZ DEFAULT NOW();
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                 WHERE table_name='member_id_counter' AND column_name='created_at') THEN
    ALTER TABLE member_id_counter ADD COLUMN created_at TIMESTAMPTZ DEFAULT NOW();
  END IF;
END $$;

-- Create index on year for faster lookups
CREATE INDEX IF NOT EXISTS idx_member_id_counter_year ON member_id_counter(year);

-- Add comment
COMMENT ON TABLE member_id_counter IS 'Tracks member ID counters by year for generating unique member IDs';
COMMENT ON COLUMN member_id_counter.year IS 'The year for which this counter applies';
COMMENT ON COLUMN member_id_counter.counter IS 'The current counter value for this year';
