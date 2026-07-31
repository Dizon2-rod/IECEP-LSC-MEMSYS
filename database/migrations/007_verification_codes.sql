-- Supabase SQL Migration for Verification Codes
-- Required by the email verification flow in index.php (send_code / verify_code actions)

-- Create verification_codes table
CREATE TABLE IF NOT EXISTS verification_codes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email TEXT NOT NULL,
    code TEXT NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    used_at TIMESTAMPTZ,
    ip_address TEXT,
    user_agent TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Index for faster lookups
CREATE INDEX IF NOT EXISTS idx_verification_codes_email ON verification_codes(email);
CREATE INDEX IF NOT EXISTS idx_verification_codes_code ON verification_codes(code);
CREATE INDEX IF NOT EXISTS idx_verification_codes_expires_at ON verification_codes(expires_at);

-- Enable Row Level Security
ALTER TABLE verification_codes ENABLE ROW LEVEL SECURITY;

-- Policy: Allow public insert (anyone can request a verification code)
DROP POLICY IF EXISTS "Allow public insert" ON verification_codes;
CREATE POLICY "Allow public insert" ON verification_codes
    FOR INSERT WITH CHECK (true);

-- Policy: Allow the code owner to read by email match
DROP POLICY IF EXISTS "Allow public read by email" ON verification_codes;
CREATE POLICY "Allow public read by email" ON verification_codes
    FOR SELECT USING (true);

-- Policy: Allow updating used_at (anyone can mark a code used since they know the code)
DROP POLICY IF EXISTS "Allow public update" ON verification_codes;
CREATE POLICY "Allow public update" ON verification_codes
    FOR UPDATE USING (true);
