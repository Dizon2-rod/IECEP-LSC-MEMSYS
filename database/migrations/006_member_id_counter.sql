-- Migration: Create member_id_counter table for tracking member IDs
-- This table maintains a counter for generating unique member IDs in format MEM-YYYY-0001

CREATE TABLE IF NOT EXISTS member_id_counter (
    id BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    year INT NOT NULL UNIQUE,
    counter INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create index on year for faster lookups
CREATE INDEX IF NOT EXISTS idx_member_id_counter_year ON member_id_counter(year);

-- Add comment
COMMENT ON TABLE member_id_counter IS 'Tracks member ID counters by year for generating unique member IDs';
COMMENT ON COLUMN member_id_counter.year IS 'The year for which this counter applies';
COMMENT ON COLUMN member_id_counter.counter IS 'The current counter value for this year';
