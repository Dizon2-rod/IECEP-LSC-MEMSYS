-- ============================================================================
-- IECEP-LSC MEMSYS - FINAL DATABASE SETUP (Error-Free)
-- Copy and paste this ENTIRE code into Supabase SQL Editor
-- ============================================================================

-- ============================================================================
-- STEP 1: CREATE USERS TABLE (if not exists)
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
-- STEP 2: CREATE PENDING_AFFILIATIONS TABLE (if not exists)
-- ============================================================================
CREATE TABLE IF NOT EXISTS pending_affiliations (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    institution_name VARCHAR(255) NOT NULL,
    address TEXT,
    contact_person VARCHAR(255),
    contact_position VARCHAR(255),
    contact_phone VARCHAR(50),
    email VARCHAR(255) NOT NULL,
    
    -- Workflow status columns (included in CREATE)
    status VARCHAR(20) DEFAULT 'pending',
    committee_notes TEXT,
    requested_at TIMESTAMP WITH TIME ZONE,
    resubmitted_at TIMESTAMP WITH TIME ZONE,
    rejected_at TIMESTAMP WITH TIME ZONE,
    approved_at TIMESTAMP WITH TIME ZONE,
    rejection_reason TEXT,
    portal_user_id UUID,
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
-- STEP 3: ADD COLUMNS TO EXISTING TABLES (if table already exists without columns)
-- ============================================================================
-- Add columns to users table if missing
DO $$
BEGIN
    -- Check and add must_change_password
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name='users' AND column_name='must_change_password') THEN
        ALTER TABLE users ADD COLUMN must_change_password BOOLEAN DEFAULT FALSE;
    END IF;
    
    -- Check and add school_id
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                   WHERE table_name='users' AND column_name='school_id') THEN
        ALTER TABLE users ADD COLUMN school_id UUID;
    END IF;
END $$;

-- Add columns to pending_affiliations table if missing
DO $$
BEGIN
    -- Add workflow columns one by one safely
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
-- STEP 4: ADD FOREIGN KEY CONSTRAINTS (only if portal_user_id column exists)
-- ============================================================================
DO $$
BEGIN
    -- Add FK to pending_affiliations
    IF EXISTS (SELECT 1 FROM information_schema.columns 
               WHERE table_name='pending_affiliations' AND column_name='portal_user_id') THEN
        
        -- Check if constraint already exists
        IF NOT EXISTS (
            SELECT 1 FROM pg_constraint 
            WHERE conname = 'fk_pending_affiliations_user'
        ) THEN
            ALTER TABLE pending_affiliations 
            ADD CONSTRAINT fk_pending_affiliations_user 
            FOREIGN KEY (portal_user_id) REFERENCES users(id);
        END IF;
    END IF;
END $$;

-- ============================================================================
-- STEP 5: CREATE INDEXES (safe to drop and recreate)
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

-- ============================================================================
-- STEP 6: VERIFY SETUP
-- ============================================================================
SELECT 'DATABASE SETUP COMPLETE!' as status, NOW() as timestamp;

-- Show tables created
SELECT 
    table_name,
    (SELECT COUNT(*) FROM information_schema.columns 
     WHERE table_schema = 'public' AND table_name = t.table_name) as columns
FROM information_schema.tables t
WHERE table_schema = 'public' 
AND table_name IN ('users', 'pending_affiliations')
ORDER BY table_name;
