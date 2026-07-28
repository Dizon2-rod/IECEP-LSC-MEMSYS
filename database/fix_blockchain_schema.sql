-- Fix blockchain_records table schema to match BlockchainService requirements
-- This script is idempotent - can be run multiple times safely

-- Add missing columns to blockchain_records table
DO $$
BEGIN
    -- Add data_hash column if it doesn't exist
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'blockchain_records' AND column_name = 'data_hash'
    ) THEN
        ALTER TABLE blockchain_records ADD COLUMN data_hash TEXT NOT NULL DEFAULT '';
    END IF;
    
    -- Add previous_hash column if it doesn't exist
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'blockchain_records' AND column_name = 'previous_hash'
    ) THEN
        ALTER TABLE blockchain_records ADD COLUMN previous_hash TEXT;
    END IF;
    
    -- Add data_json column if it doesn't exist
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'blockchain_records' AND column_name = 'data_json'
    ) THEN
        ALTER TABLE blockchain_records ADD COLUMN data_json JSONB;
    END IF;
    
    -- Add merkle_root column if it doesn't exist
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'blockchain_records' AND column_name = 'merkle_root'
    ) THEN
        ALTER TABLE blockchain_records ADD COLUMN merkle_root TEXT;
    END IF;
    
    -- Add record_type column if it doesn't exist (for backward compatibility)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'blockchain_records' AND column_name = 'record_type'
    ) THEN
        ALTER TABLE blockchain_records ADD COLUMN record_type TEXT;
    END IF;
    
    -- Add reference_id column if it doesn't exist (for backward compatibility)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'blockchain_records' AND column_name = 'reference_id'
    ) THEN
        ALTER TABLE blockchain_records ADD COLUMN reference_id UUID;
    END IF;
    
    -- Add metadata column if it doesn't exist
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name = 'blockchain_records' AND column_name = 'metadata'
    ) THEN
        ALTER TABLE blockchain_records ADD COLUMN metadata JSONB;
    END IF;
END $$;

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_blockchain_records_record_type ON blockchain_records(record_type);
CREATE INDEX IF NOT EXISTS idx_blockchain_records_reference_id ON blockchain_records(reference_id);
CREATE INDEX IF NOT EXISTS idx_blockchain_records_data_hash ON blockchain_records(data_hash);
CREATE INDEX IF NOT EXISTS idx_blockchain_records_previous_hash ON blockchain_records(previous_hash);

-- Migrate existing data from old columns to new columns if needed
UPDATE blockchain_records 
SET 
    record_type = entity_type,
    reference_id = entity_id,
    data_hash = COALESCE(record_hash, transaction_hash, ''),
    data_json = '{}'::jsonb
WHERE record_type IS NULL OR reference_id IS NULL;
