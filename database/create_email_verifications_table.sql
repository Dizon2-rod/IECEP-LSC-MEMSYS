-- Create email_verifications table for IECEP-LSC MEMSYS
CREATE TABLE IF NOT EXISTS email_verifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email TEXT NOT NULL,
    code TEXT NOT NULL,
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    verified BOOLEAN DEFAULT false,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT now(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS idx_email_verifications_email ON email_verifications(email);
CREATE INDEX IF NOT EXISTS idx_email_verifications_code ON email_verifications(code);
CREATE INDEX IF NOT EXISTS idx_email_verifications_expires_at ON email_verifications(expires_at);
CREATE INDEX IF NOT EXISTS idx_email_verifications_verified ON email_verifications(verified);

-- Add RLS (Row Level Security) policies
ALTER TABLE email_verifications ENABLE ROW LEVEL SECURITY;

-- Policy to allow inserts (for creating verification codes)
CREATE POLICY "Allow insert operations" ON email_verifications
    FOR INSERT WITH CHECK (true);

-- Policy to allow select operations (for verification)
CREATE POLICY "Allow select operations" ON email_verifications
    FOR SELECT USING (true);

-- Policy to allow updates (for marking as verified)
CREATE POLICY "Allow update operations" ON email_verifications
    FOR UPDATE USING (true);

-- Policy to allow service role to do everything
CREATE POLICY "Allow service role full access" ON email_verifications
    FOR ALL USING (auth.role() = 'service_role');

-- Function to automatically update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Trigger to automatically update updated_at
CREATE TRIGGER update_email_verifications_updated_at 
    BEFORE UPDATE ON email_verifications 
    FOR EACH ROW 
    EXECUTE FUNCTION update_updated_at_column();

-- Function to clean up expired verification codes (optional cleanup job)
CREATE OR REPLACE FUNCTION cleanup_expired_verifications()
RETURNS void AS $$
BEGIN
    DELETE FROM email_verifications 
    WHERE expires_at < now() - interval '1 hour';
END;
$$ LANGUAGE plpgsql;
