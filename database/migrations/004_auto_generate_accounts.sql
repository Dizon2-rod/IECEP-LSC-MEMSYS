-- Migration: Auto-Generate Member Accounts & Send Credentials
-- Date: 2025-01-XX
-- Description: Add membership_id_sequences table and force_password_change column

-- 1. Membership ID sequence tracker
CREATE TABLE IF NOT EXISTS membership_id_sequences (
    id SERIAL PRIMARY KEY,
    year INT NOT NULL UNIQUE,
    last_number INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Create index for faster year lookups
CREATE INDEX IF NOT EXISTS idx_membership_id_sequences_year ON membership_id_sequences(year);

-- 2. Force password change flag on user_profiles
ALTER TABLE user_profiles
    ADD COLUMN IF NOT EXISTS force_password_change BOOLEAN NOT NULL DEFAULT false;

-- 3. Add user_id column to members table if not exists (for linking Supabase auth users)
ALTER TABLE members
    ADD COLUMN IF NOT EXISTS user_id UUID REFERENCES auth.users(id) ON DELETE SET NULL;

-- Create index for faster user_id lookups
CREATE INDEX IF NOT EXISTS idx_members_user_id ON members(user_id);

-- 4. Add comments for documentation
COMMENT ON TABLE membership_id_sequences IS 'Tracks sequential membership ID generation per year (format: YYYY-XXXX)';
COMMENT ON COLUMN membership_id_sequences.year IS 'Calendar year for membership ID generation';
COMMENT ON COLUMN membership_id_sequences.last_number IS 'Last assigned sequence number for this year';
COMMENT ON COLUMN user_profiles.force_password_change IS 'Flag to force password change on next login (for temporary passwords)';
COMMENT ON COLUMN members.user_id IS 'Link to Supabase auth.users table for member login accounts';
