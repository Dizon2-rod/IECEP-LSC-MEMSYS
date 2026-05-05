-- Create pending_affiliations table for affiliation applications
CREATE TABLE IF NOT EXISTS pending_affiliations (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    institution_name VARCHAR(255) NOT NULL,
    address TEXT NOT NULL,
    contact_person VARCHAR(255) NOT NULL,
    contact_position VARCHAR(255) NOT NULL,
    contact_phone VARCHAR(50) NOT NULL,
    documents JSONB NOT NULL,
    status VARCHAR(50) DEFAULT 'pending_review',
    submitted_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    reviewed_at TIMESTAMP WITH TIME ZONE,
    reviewed_by INTEGER,
    review_notes TEXT,
    resubmission_count INTEGER DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create indexes for better query performance
CREATE INDEX IF NOT EXISTS idx_pending_affiliations_email ON pending_affiliations(email);
CREATE INDEX IF NOT EXISTS idx_pending_affiliations_status ON pending_affiliations(status);
CREATE INDEX IF NOT EXISTS idx_pending_affiliations_submitted_at ON pending_affiliations(submitted_at DESC);

-- Enable Row Level Security (RLS)
ALTER TABLE pending_affiliations ENABLE ROW LEVEL SECURITY;

-- Create policy to allow public insert (for applications)
CREATE POLICY "Allow public insert" ON pending_affiliations
    FOR INSERT
    TO public
    WITH CHECK (true);

-- Create policy to allow committee_registration and eb_vp_internal to read/update
CREATE POLICY "Allow committee access" ON pending_affiliations
    FOR ALL
    TO authenticated
    USING (
        auth.jwt() ->> 'role' IN ('committee_registration', 'eb_vp_internal', 'super_admin')
    )
    WITH CHECK (
        auth.jwt() ->> 'role' IN ('committee_registration', 'eb_vp_internal', 'super_admin')
    );

-- Create function to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_pending_affiliations_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Create trigger to automatically update updated_at
CREATE TRIGGER update_pending_affiliations_updated_at
    BEFORE UPDATE ON pending_affiliations
    FOR EACH ROW
    EXECUTE FUNCTION update_pending_affiliations_updated_at();

-- Add comment to table
COMMENT ON TABLE pending_affiliations IS 'Stores affiliation applications pending review by the Registration Committee';
