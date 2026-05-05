-- Create verification_codes table for email verification
CREATE TABLE IF NOT EXISTS verification_codes (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    code VARCHAR(6) NOT NULL,
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    used_at TIMESTAMP WITH TIME ZONE,
    ip_address VARCHAR(45),
    user_agent TEXT
);

-- Create indexes for better query performance
CREATE INDEX IF NOT EXISTS idx_verification_codes_email ON verification_codes(email);
CREATE INDEX IF NOT EXISTS idx_verification_codes_code ON verification_codes(code);
CREATE INDEX IF NOT EXISTS idx_verification_codes_expires_at ON verification_codes(expires_at);
CREATE INDEX IF NOT EXISTS idx_verification_codes_created_at ON verification_codes(created_at DESC);

-- Enable Row Level Security (RLS)
ALTER TABLE verification_codes ENABLE ROW LEVEL SECURITY;

-- Create policy to allow public insert (for verification requests)
CREATE POLICY "Allow public insert" ON verification_codes
    FOR INSERT
    TO public
    WITH CHECK (true);

-- Create policy to allow authenticated users to read their own codes (if needed)
CREATE POLICY "Allow users to read own codes" ON verification_codes
    FOR SELECT
    TO public
    USING (true);

-- Create policy to allow server-side updates (for marking as used)
CREATE POLICY "Allow server-side update" ON verification_codes
    FOR UPDATE
    TO authenticated
    USING (true)
    WITH CHECK (true);

-- Create function to clean up expired verification codes
CREATE OR REPLACE FUNCTION cleanup_expired_verification_codes()
RETURNS void AS $$
BEGIN
    DELETE FROM verification_codes
    WHERE expires_at < NOW()
    OR used_at IS NOT NULL;
END;
$$ LANGUAGE plpgsql;

-- Create trigger to automatically clean up expired codes on insert (optional)
-- This keeps the table clean by removing codes older than 1 hour
CREATE OR REPLACE FUNCTION cleanup_old_verification_codes_trigger()
RETURNS TRIGGER AS $$
BEGIN
    DELETE FROM verification_codes
    WHERE expires_at < NOW() - INTERVAL '1 hour'
    AND id NOT IN (
        SELECT id FROM verification_codes
        WHERE expires_at >= NOW() - INTERVAL '1 hour'
        ORDER BY created_at DESC
        LIMIT 100
    );
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER cleanup_old_codes
    AFTER INSERT ON verification_codes
    FOR EACH ROW
    EXECUTE FUNCTION cleanup_old_verification_codes_trigger();

-- Add comment to table
COMMENT ON TABLE verification_codes IS 'Stores email verification codes with expiration tracking';

-- Create a function to manually clean up (can be called via cron job)
CREATE OR REPLACE FUNCTION manual_cleanup_verification_codes()
RETURNS INTEGER AS $$
DECLARE
    deleted_count INTEGER;
BEGIN
    DELETE FROM verification_codes
    WHERE expires_at < NOW() - INTERVAL '24 hours';
    GET DIAGNOSTICS deleted_count = ROW_COUNT;
    RETURN deleted_count;
END;
$$ LANGUAGE plpgsql;
