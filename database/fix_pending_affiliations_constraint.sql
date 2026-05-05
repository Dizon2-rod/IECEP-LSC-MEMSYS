-- Fix the pending_affiliations status constraint
-- Run this in Supabase SQL Editor

-- First, drop the existing constraint if it exists
ALTER TABLE pending_affiliations DROP CONSTRAINT IF EXISTS pending_affiliations_status_check;

-- Add the correct constraint that allows all needed statuses
ALTER TABLE pending_affiliations 
ADD CONSTRAINT pending_affiliations_status_check 
CHECK (status IN ('pending', 'pending_review', 'approved', 'rejected', 'resubmitted'));

-- Update any existing records that might have invalid status
UPDATE pending_affiliations 
SET status = 'pending_review' 
WHERE status NOT IN ('pending', 'pending_review', 'approved', 'rejected', 'resubmitted');
