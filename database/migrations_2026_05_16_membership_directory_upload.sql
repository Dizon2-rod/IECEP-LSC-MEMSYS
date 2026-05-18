-- =====================================================
-- MEMBERSHIP DIRECTORY UPLOAD FEATURE MIGRATION
-- Date: 2026-05-16
-- Purpose: Add tables for bulk member upload and validation
-- =====================================================

-- UP MIGRATIONS
-- =====================================================

-- Table: upload_batches - Track each upload batch
CREATE TABLE IF NOT EXISTS upload_batches (
    id VARCHAR(50) PRIMARY KEY,
    institution_id UUID NOT NULL,
    uploaded_by_user_id UUID,
    file_name VARCHAR(255),
    total_rows INTEGER DEFAULT 0,
    validated_rows INTEGER DEFAULT 0,
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'in_progress', 'completed', 'exported')),
    uploaded_at TIMESTAMPTZ DEFAULT NOW(),
    FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_upload_batches_institution ON upload_batches(institution_id);
CREATE INDEX IF NOT EXISTS idx_upload_batches_uploaded_at ON upload_batches(uploaded_at DESC);
CREATE INDEX IF NOT EXISTS idx_upload_batches_status ON upload_batches(status);

-- Table: membership_directory_imports - Individual row data from uploads
CREATE TABLE IF NOT EXISTS membership_directory_imports (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    batch_id VARCHAR(50) NOT NULL,
    sheet_name TEXT,
    row_index INTEGER,
    is_valid BOOLEAN DEFAULT false,
    validation_errors TEXT,
    
    -- Raw data from Excel
    member_number VARCHAR(50),
    name TEXT,
    birthday DATE,
    address TEXT,
    phone VARCHAR(20),
    email TEXT,
    picture_url TEXT,
    signature_url TEXT,
    
    -- Validation results
    is_paid BOOLEAN DEFAULT false,
    member_type TEXT CHECK (member_type IN ('new', 'old')),
    assigned_membership_id VARCHAR(50),
    
    -- Assignment tracking
    assigned_at TIMESTAMPTZ,
    assigned_by_user_id UUID,
    member_id UUID REFERENCES members(id) ON DELETE SET NULL,
    
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    
    FOREIGN KEY (batch_id) REFERENCES upload_batches(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_membership_directory_imports_batch ON membership_directory_imports(batch_id);
CREATE INDEX IF NOT EXISTS idx_membership_directory_imports_email ON membership_directory_imports(email);
CREATE INDEX IF NOT EXISTS idx_membership_directory_imports_is_valid ON membership_directory_imports(is_valid);
CREATE INDEX IF NOT EXISTS idx_membership_directory_imports_assigned ON membership_directory_imports(assigned_membership_id);

-- Table: membership_id_sequences - Track membership ID sequences per year
CREATE TABLE IF NOT EXISTS membership_id_sequences (
    id SERIAL PRIMARY KEY,
    year INTEGER NOT NULL UNIQUE,
    last_sequence_number INTEGER DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_membership_id_sequences_year ON membership_id_sequences(year);

-- Table: payments - Track payment status (for future payment gateway integration)
CREATE TABLE IF NOT EXISTS payments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    member_id UUID REFERENCES members(id) ON DELETE CASCADE,
    batch_id VARCHAR(50),
    amount DECIMAL(10, 2),
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'paid', 'cancelled')),
    payment_date TIMESTAMPTZ,
    payment_reference TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    FOREIGN KEY (batch_id) REFERENCES upload_batches(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_payments_member ON payments(member_id);
CREATE INDEX IF NOT EXISTS idx_payments_batch ON payments(batch_id);
CREATE INDEX IF NOT EXISTS idx_payments_status ON payments(status);

-- Add new columns to members table if they don't exist
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS school_affiliate TEXT;
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS year_level TEXT;
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS is_new BOOLEAN DEFAULT true;
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS payment_status BOOLEAN DEFAULT false;
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS validated_at TIMESTAMPTZ;
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS validated_by UUID;
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS picture_url TEXT;
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS signature_url TEXT;
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS upload_batch_id VARCHAR(50);
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS birthday DATE;
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS address TEXT;
ALTER TABLE IF EXISTS members ADD COLUMN IF NOT EXISTS phone VARCHAR(20);

-- Add foreign key for validated_by if it doesn't exist
ALTER TABLE IF EXISTS members ADD CONSTRAINT IF NOT EXISTS fk_members_validated_by 
    FOREIGN KEY (validated_by) REFERENCES auth.users(id) ON DELETE SET NULL;

-- Add foreign key for upload_batch_id if it doesn't exist
ALTER TABLE IF EXISTS members ADD CONSTRAINT IF NOT EXISTS fk_members_upload_batch 
    FOREIGN KEY (upload_batch_id) REFERENCES upload_batches(id) ON DELETE SET NULL;

-- Create indexes for new members columns
CREATE INDEX IF NOT EXISTS idx_members_year_level ON members(year_level);
CREATE INDEX IF NOT EXISTS idx_members_payment_status ON members(payment_status);
CREATE INDEX IF NOT EXISTS idx_members_validated_at ON members(validated_at);
CREATE INDEX IF NOT EXISTS idx_members_upload_batch ON members(upload_batch_id);

-- =====================================================
-- DOWN MIGRATIONS (Uncomment to rollback)
-- =====================================================

/*

-- DROP TABLE IF EXISTS payments;
-- DROP TABLE IF EXISTS membership_directory_imports;
-- DROP TABLE IF EXISTS upload_batches;
-- DROP TABLE IF EXISTS membership_id_sequences;

-- ALTER TABLE IF EXISTS members DROP COLUMN IF EXISTS school_affiliate;
-- ALTER TABLE IF EXISTS members DROP COLUMN IF EXISTS year_level;
-- ALTER TABLE IF EXISTS members DROP COLUMN IF EXISTS is_new;
-- ALTER TABLE IF EXISTS members DROP COLUMN IF EXISTS payment_status;
-- ALTER TABLE IF EXISTS members DROP COLUMN IF EXISTS validated_at;
-- ALTER TABLE IF EXISTS members DROP COLUMN IF EXISTS validated_by;
-- ALTER TABLE IF EXISTS members DROP COLUMN IF EXISTS picture_url;
-- ALTER TABLE IF EXISTS members DROP COLUMN IF EXISTS signature_url;
-- ALTER TABLE IF EXISTS members DROP COLUMN IF EXISTS upload_batch_id;
-- ALTER TABLE IF EXISTS members DROP COLUMN IF EXISTS birthday;
-- ALTER TABLE IF EXISTS members DROP COLUMN IF EXISTS address;
-- ALTER TABLE IF EXISTS members DROP COLUMN IF EXISTS phone;
-- ALTER TABLE IF EXISTS members DROP CONSTRAINT IF EXISTS fk_members_validated_by;
-- ALTER TABLE IF EXISTS members DROP CONSTRAINT IF EXISTS fk_members_upload_batch;

*/
