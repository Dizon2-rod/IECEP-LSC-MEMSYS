-- Supabase SQL Migration for Affiliation System
-- Run this in Supabase SQL Editor if the table doesn't exist

-- Create pending_affiliations table
CREATE TABLE IF NOT EXISTS pending_affiliations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    institution_name TEXT NOT NULL,
    institution_address TEXT NOT NULL,
    contact_person TEXT NOT NULL,
    contact_position TEXT NOT NULL,
    contact_email TEXT NOT NULL,
    contact_phone TEXT NOT NULL,
    letter_of_intent TEXT,
    endorsement_letter TEXT,
    constitution_by_laws TEXT,
    officers_cvs TEXT,
    organizational_chart TEXT,
    member_directory TEXT,
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
    submitted_at TIMESTAMP DEFAULT NOW(),
    ip_address TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Create index on status for faster queries
CREATE INDEX IF NOT EXISTS idx_pending_affiliations_status ON pending_affiliations(status);

-- Create index on email for duplicate checking
CREATE INDEX IF NOT EXISTS idx_pending_affiliations_email ON pending_affiliations(contact_email);

-- Create index on submitted_at for sorting
CREATE INDEX IF NOT EXISTS idx_pending_affiliations_submitted_at ON pending_affiliations(submitted_at DESC);

-- Enable RLS (Row Level Security)
ALTER TABLE pending_affiliations ENABLE ROW LEVEL SECURITY;

-- Policy: Allow anyone to insert (for public submissions)
CREATE POLICY "Allow public insert" ON pending_affiliations
    FOR INSERT
    WITH CHECK (true);

-- Policy: Allow authenticated users to read
CREATE POLICY "Allow authenticated read" ON pending_affiliations
    FOR SELECT
    USING (auth.role() = 'authenticated');

-- Policy: Allow admins to update
CREATE POLICY "Allow admin update" ON pending_affiliations
    FOR UPDATE
    USING (auth.role() = 'authenticated')
    WITH CHECK (auth.role() = 'authenticated');

-- Create trigger to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER update_pending_affiliations_updated_at
    BEFORE UPDATE ON pending_affiliations
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();
