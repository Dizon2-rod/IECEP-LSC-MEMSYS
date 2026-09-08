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
    updated_at TIMESTAMP DEFAULT NOW(),
    email TEXT,
    total_members INTEGER DEFAULT 0,
    new_members INTEGER DEFAULT 0,
    old_members INTEGER DEFAULT 0,
    affiliation_fee DECIMAL(10,2) DEFAULT 0,
    membership_total DECIMAL(10,2) DEFAULT 0,
    total_fee DECIMAL(10,2) DEFAULT 0,
    receipt_number TEXT
);

-- Ensure all columns exist if table was created in an earlier migration
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS school_name TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS institution_name TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS acronym TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS email TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS contact_email TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS contact_person TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS contact_number TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS contact_phone TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS contact_position TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS institution_address TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS documents JSONB DEFAULT '{}';
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS letter_of_intent TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS endorsement_letter TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS constitution_by_laws TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS officers_cvs TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS organizational_chart TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS member_directory TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS total_members INTEGER DEFAULT 0;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS new_members INTEGER DEFAULT 0;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS old_members INTEGER DEFAULT 0;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS affiliation_fee DECIMAL(10,2) DEFAULT 0;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS membership_total DECIMAL(10,2) DEFAULT 0;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS total_fee DECIMAL(10,2) DEFAULT 0;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS receipt_number TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS status TEXT DEFAULT 'pending';
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS submitted_at TIMESTAMP DEFAULT NOW();
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT NOW();
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT NOW();

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

-- Add missing columns to existing tables (safe if table was created without them)
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS email TEXT;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS total_members INTEGER DEFAULT 0;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS new_members INTEGER DEFAULT 0;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS old_members INTEGER DEFAULT 0;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS affiliation_fee DECIMAL(10,2) DEFAULT 0;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS membership_total DECIMAL(10,2) DEFAULT 0;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS total_fee DECIMAL(10,2) DEFAULT 0;
ALTER TABLE pending_affiliations ADD COLUMN IF NOT EXISTS receipt_number TEXT;

-- Fix PostgreSQL 55000: set REPLICA IDENTITY for tables in publication
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'compliance_scores') THEN
        ALTER TABLE compliance_scores REPLICA IDENTITY FULL;
    END IF;
END $$;

