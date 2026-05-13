-- Migration: Create password_resets table for forgot password functionality
-- This script is idempotent and can be safely run multiple times

CREATE TABLE IF NOT EXISTS password_resets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email TEXT NOT NULL,
    token TEXT UNIQUE NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    used BOOLEAN DEFAULT false,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Create indexes for faster lookups
CREATE INDEX IF NOT EXISTS idx_password_resets_token ON password_resets(token);
CREATE INDEX IF NOT EXISTS idx_password_resets_email ON password_resets(email);
CREATE INDEX IF NOT EXISTS idx_password_resets_expires_at ON password_resets(expires_at);

-- Add RLS policy (optional, enable if using Supabase RLS)
-- Disable RLS for password_resets table as it needs to be accessed via API only
ALTER TABLE password_resets DISABLE ROW LEVEL SECURITY;

-- Create a trigger to automatically update the updated_at timestamp
CREATE OR REPLACE FUNCTION update_password_resets_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS update_password_resets_updated_at_trigger ON password_resets;
CREATE TRIGGER update_password_resets_updated_at_trigger
BEFORE UPDATE ON password_resets
FOR EACH ROW
EXECUTE FUNCTION update_password_resets_updated_at();
