-- Create awards_distinctions table
CREATE TABLE IF NOT EXISTS awards_distinctions (
    id SERIAL PRIMARY KEY,
    title TEXT NOT NULL,
    description TEXT,
    image_url TEXT,
    award_year INTEGER,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create index on award_year for better query performance
CREATE INDEX IF NOT EXISTS idx_awards_distinctions_year ON awards_distinctions(award_year DESC);

-- Enable Row Level Security (RLS)
ALTER TABLE awards_distinctions ENABLE ROW LEVEL SECURITY;

-- Create policy to allow public read access
CREATE POLICY "Allow public read access" ON awards_distinctions
    FOR SELECT
    TO public
    USING (true);

-- Create policy to allow creatives and super_admin to insert
CREATE POLICY "Allow creatives to insert" ON awards_distinctions
    FOR INSERT
    TO authenticated
    WITH CHECK (
        auth.jwt() ->> 'role' IN ('creatives', 'super_admin')
    );

-- Create policy to allow creatives and super_admin to update
CREATE POLICY "Allow creatives to update" ON awards_distinctions
    FOR UPDATE
    TO authenticated
    USING (
        auth.jwt() ->> 'role' IN ('creatives', 'super_admin')
    )
    WITH CHECK (
        auth.jwt() ->> 'role' IN ('creatives', 'super_admin')
    );

-- Create policy to allow creatives and super_admin to delete
CREATE POLICY "Allow creatives to delete" ON awards_distinctions
    FOR DELETE
    TO authenticated
    USING (
        auth.jwt() ->> 'role' IN ('creatives', 'super_admin')
    );

-- Create function to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Create trigger to automatically update updated_at
CREATE TRIGGER update_awards_distinctions_updated_at
    BEFORE UPDATE ON awards_distinctions
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();
