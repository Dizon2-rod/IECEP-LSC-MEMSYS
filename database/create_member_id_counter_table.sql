-- Migration: Add member_id_counter and membership_id support
-- This creates a counter table for issuing unique membership IDs and adds a membership_id column to members.

ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS membership_id TEXT UNIQUE;

CREATE TABLE IF NOT EXISTS member_id_counter (
    id SERIAL PRIMARY KEY,
    last_counter INTEGER NOT NULL DEFAULT 0
);

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM member_id_counter) THEN
        INSERT INTO member_id_counter (last_counter) VALUES (0);
    END IF;
END $$;
