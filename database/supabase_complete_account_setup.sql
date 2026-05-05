-- ============================================================================
-- IECEP-LSC MEMSYS - COMPLETE SUPABASE SETUP FOR AUTO ACCOUNT CREATION
-- Copy and paste this ENTIRE script into Supabase SQL Editor
-- Safe to run multiple times (uses IF NOT EXISTS)
-- ============================================================================

-- ============================================================================
-- STEP 1: CREATE USERS TABLE (Portal accounts with forced password change)
-- ============================================================================
CREATE TABLE IF NOT EXISTS users (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    role VARCHAR(50) DEFAULT 'member',
    must_change_password BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    school_id UUID,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- ============================================================================
-- STEP 2: CREATE PENDING_AFFILIATIONS TABLE (Applications with workflow)
-- ============================================================================
CREATE TABLE IF NOT EXISTS pending_affiliations (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    institution_name VARCHAR(255) NOT NULL,
    address TEXT,
    contact_person VARCHAR(255),
    contact_position VARCHAR(255),
    contact_phone VARCHAR(50),
    email VARCHAR(255) NOT NULL,
    
    -- Workflow status columns for auto account creation
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'changes_requested', 'resubmitted', 'approved', 'rejected')),
    committee_notes TEXT,
    requested_at TIMESTAMP WITH TIME ZONE,
    resubmitted_at TIMESTAMP WITH TIME ZONE,
    rejected_at TIMESTAMP WITH TIME ZONE,
    approved_at TIMESTAMP WITH TIME ZONE,
    rejection_reason TEXT,
    portal_user_id UUID REFERENCES users(id),
    edit_token VARCHAR(64),
    login_credentials_sent BOOLEAN DEFAULT FALSE,
    
    -- Documents stored as JSON
    documents JSONB DEFAULT '{}',
    
    -- Timestamps
    submitted_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- ============================================================================
-- STEP 3: CREATE AFFILIATED_SCHOOLS TABLE (Approved schools)
-- ============================================================================
CREATE TABLE IF NOT EXISTS affiliated_schools (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address TEXT,
    accreditation_number VARCHAR(100),
    contact_email VARCHAR(255),
    contact_person VARCHAR(255),
    contact_phone VARCHAR(50),
    status VARCHAR(20) DEFAULT 'active',
    portal_user_id UUID REFERENCES users(id),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- ============================================================================
-- STEP 4: ADD MISSING COLUMNS (Safe if table exists)
-- ============================================================================
DO $$
BEGIN
    -- Add columns to users table if missing
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name='users' AND column_name='must_change_password') THEN
        ALTER TABLE users ADD COLUMN must_change_password BOOLEAN DEFAULT FALSE;
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name='users' AND column_name='school_id') THEN
        ALTER TABLE users ADD COLUMN school_id UUID;
    END IF;
    
    -- Add workflow columns to pending_affiliations if missing
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name='pending_affiliations' AND column_name='status') THEN
        ALTER TABLE pending_affiliations ADD COLUMN status VARCHAR(20) DEFAULT 'pending';
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name='pending_affiliations' AND column_name='committee_notes') THEN
        ALTER TABLE pending_affiliations ADD COLUMN committee_notes TEXT;
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name='pending_affiliations' AND column_name='requested_at') THEN
        ALTER TABLE pending_affiliations ADD COLUMN requested_at TIMESTAMP WITH TIME ZONE;
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name='pending_affiliations' AND column_name='resubmitted_at') THEN
        ALTER TABLE pending_affiliations ADD COLUMN resubmitted_at TIMESTAMP WITH TIME ZONE;
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name='pending_affiliations' AND column_name='rejected_at') THEN
        ALTER TABLE pending_affiliations ADD COLUMN rejected_at TIMESTAMP WITH TIME ZONE;
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name='pending_affiliations' AND column_name='approved_at') THEN
        ALTER TABLE pending_affiliations ADD COLUMN approved_at TIMESTAMP WITH TIME ZONE;
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name='pending_affiliations' AND column_name='rejection_reason') THEN
        ALTER TABLE pending_affiliations ADD COLUMN rejection_reason TEXT;
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name='pending_affiliations' AND column_name='portal_user_id') THEN
        ALTER TABLE pending_affiliations ADD COLUMN portal_user_id UUID;
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name='pending_affiliations' AND column_name='edit_token') THEN
        ALTER TABLE pending_affiliations ADD COLUMN edit_token VARCHAR(64);
    END IF;
    
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name='pending_affiliations' AND column_name='login_credentials_sent') THEN
        ALTER TABLE pending_affiliations ADD COLUMN login_credentials_sent BOOLEAN DEFAULT FALSE;
    END IF;
END $$;

-- ============================================================================
-- STEP 5: ADD FOREIGN KEY CONSTRAINTS (Safe creation)
-- ============================================================================
DO $$
BEGIN
    -- Add FK to pending_affiliations
    IF EXISTS (SELECT 1 FROM information_schema.columns 
               WHERE table_name='pending_affiliations' AND column_name='portal_user_id') THEN
        IF NOT EXISTS (
            SELECT 1 FROM pg_constraint 
            WHERE conname = 'fk_pending_affiliations_user'
        ) THEN
            ALTER TABLE pending_affiliations 
            ADD CONSTRAINT fk_pending_affiliations_user 
            FOREIGN KEY (portal_user_id) REFERENCES users(id);
        END IF;
    END IF;
    
    -- Add FK to affiliated_schools
    IF EXISTS (SELECT 1 FROM information_schema.columns 
               WHERE table_name='affiliated_schools' AND column_name='portal_user_id') THEN
        IF NOT EXISTS (
            SELECT 1 FROM pg_constraint 
            WHERE conname = 'fk_affiliated_schools_user'
        ) THEN
            ALTER TABLE affiliated_schools 
            ADD CONSTRAINT fk_affiliated_schools_user 
            FOREIGN KEY (portal_user_id) REFERENCES users(id);
        END IF;
    END IF;
END $$;

-- ============================================================================
-- STEP 6: CREATE INDEXES FOR PERFORMANCE
-- ============================================================================
-- Users table indexes
DROP INDEX IF EXISTS idx_users_email;
DROP INDEX IF EXISTS idx_users_role;
DROP INDEX IF EXISTS idx_users_must_change_password;

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_must_change_password ON users(must_change_password) WHERE must_change_password = TRUE;

-- Pending affiliations indexes
DROP INDEX IF EXISTS idx_pending_affiliations_status;
DROP INDEX IF EXISTS idx_pending_affiliations_email;
DROP INDEX IF EXISTS idx_pending_affiliations_edit_token;
DROP INDEX IF EXISTS idx_pending_affiliations_portal_user;

CREATE INDEX idx_pending_affiliations_status ON pending_affiliations(status);
CREATE INDEX idx_pending_affiliations_email ON pending_affiliations(email);
CREATE INDEX idx_pending_affiliations_edit_token ON pending_affiliations(edit_token) WHERE edit_token IS NOT NULL;
CREATE INDEX idx_pending_affiliations_portal_user ON pending_affiliations(portal_user_id) WHERE portal_user_id IS NOT NULL;

-- Affiliated schools indexes
DROP INDEX IF EXISTS idx_affiliated_schools_status;
DROP INDEX IF EXISTS idx_affiliated_schools_user;

CREATE INDEX idx_affiliated_schools_status ON affiliated_schools(status);
CREATE INDEX idx_affiliated_schools_user ON affiliated_schools(portal_user_id);

-- ============================================================================
-- STEP 7: CREATE TRIGGERS FOR updated_at
-- ============================================================================
-- Function to auto-update updated_at column
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Apply trigger to users
DROP TRIGGER IF EXISTS update_users_updated_at ON users;
CREATE TRIGGER update_users_updated_at 
    BEFORE UPDATE ON users 
    FOR EACH ROW 
    EXECUTE FUNCTION update_updated_at_column();

-- Apply trigger to pending_affiliations
DROP TRIGGER IF EXISTS update_pending_affiliations_updated_at ON pending_affiliations;
CREATE TRIGGER update_pending_affiliations_updated_at 
    BEFORE UPDATE ON pending_affiliations 
    FOR EACH ROW 
    EXECUTE FUNCTION update_updated_at_column();

-- Apply trigger to affiliated_schools
DROP TRIGGER IF EXISTS update_affiliated_schools_updated_at ON affiliated_schools;
CREATE TRIGGER update_affiliated_schools_updated_at 
    BEFORE UPDATE ON affiliated_schools 
    FOR EACH ROW 
    EXECUTE FUNCTION update_updated_at_column();

-- ============================================================================
-- STEP 8: ENABLE ROW LEVEL SECURITY (RLS)
-- ============================================================================
ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE pending_affiliations ENABLE ROW LEVEL SECURITY;
ALTER TABLE affiliated_schools ENABLE ROW LEVEL SECURITY;

-- ============================================================================
-- STEP 9: CREATE RLS POLICIES
-- ============================================================================
-- Users policies
DROP POLICY IF EXISTS "Users can view own profile" ON users;
CREATE POLICY "Users can view own profile" ON users
    FOR SELECT USING (auth.uid() = id);

DROP POLICY IF EXISTS "Users can update own profile" ON users;
CREATE POLICY "Users can update own profile" ON users
    FOR UPDATE USING (auth.uid() = id);

-- Pending affiliations policies
DROP POLICY IF EXISTS "Registration committee can view all applications" ON pending_affiliations;
CREATE POLICY "Registration committee can view all applications" ON pending_affiliations
    FOR ALL USING (
        EXISTS (
            SELECT 1 FROM users 
            WHERE users.id = auth.uid() 
            AND users.role IN ('registration', 'committee_registration', 'admin', 'super_admin')
        )
    );

DROP POLICY IF EXISTS "Applicants can view own application by token" ON pending_affiliations;
CREATE POLICY "Applicants can view own application by token" ON pending_affiliations
    FOR SELECT USING (edit_token IS NOT NULL);

-- Affiliated schools policies
DROP POLICY IF EXISTS "Authenticated users can view active schools" ON affiliated_schools;
CREATE POLICY "Authenticated users can view active schools" ON affiliated_schools
    FOR SELECT USING (status = 'active');

-- ============================================================================
-- STEP 10: INSERT TEST DATA (Optional - for testing auto account creation)
-- ============================================================================
-- Insert a test registration committee member
INSERT INTO users (email, password, full_name, role, is_active)
VALUES (
    'test.committee@iecep-lsc.test', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: "password"
    'Test Registration Committee', 
    'registration', 
    TRUE
)
ON CONFLICT (email) DO NOTHING;

-- Insert a test affiliation application
INSERT INTO pending_affiliations (
    institution_name, 
    address, 
    contact_person, 
    contact_position, 
    contact_phone, 
    email, 
    status,
    submitted_at
)
VALUES (
    'Test University Laguna',
    '123 Main Street, Laguna, Philippines',
    'Juan Dela Cruz',
    'Student President',
    '09171234567',
    'test.university@example.com',
    'pending',
    NOW()
)
ON CONFLICT DO NOTHING;

-- ============================================================================
-- STEP 11: VERIFICATION QUERY
-- ============================================================================
SELECT 
    'SUPABASE SETUP COMPLETE FOR AUTO ACCOUNT CREATION!' as status,
    NOW() as timestamp;

-- Show created tables and columns
SELECT 
    t.table_name,
    COUNT(c.column_name) as column_count
FROM information_schema.tables t
LEFT JOIN information_schema.columns c ON t.table_name = c.table_name
WHERE t.table_schema = 'public' 
AND t.table_name IN ('users', 'pending_affiliations', 'affiliated_schools')
GROUP BY t.table_name
ORDER BY t.table_name;

-- Show workflow columns in pending_affiliations
SELECT 
    column_name,
    data_type,
    column_default
FROM information_schema.columns 
WHERE table_name = 'pending_affiliations' 
AND column_name IN ('status', 'committee_notes', 'portal_user_id', 'edit_token', 'login_credentials_sent')
ORDER BY ordinal_position;

-- Done! Your Supabase is now ready for auto account creation.
-- The PHP code will automatically create users when applications are approved.
