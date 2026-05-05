-- Fix the pending_affiliations status constraint
-- Run this in Supabase SQL Editor
-- This fixes the "pending_affiliations_status_check" constraint violation error

-- Step 1: Drop the existing constraint if it exists
ALTER TABLE pending_affiliations DROP CONSTRAINT IF EXISTS pending_affiliations_status_check;

-- Step 2: Add the correct constraint that allows all needed statuses
ALTER TABLE pending_affiliations 
ADD CONSTRAINT pending_affiliations_status_check 
CHECK (status IN (
    'pending',
    'pending_review',
    'approved',
    'rejected',
    'resubmitted',
    'changes_requested'
));

-- Step 3: Update any existing records that might have invalid status
UPDATE pending_affiliations 
SET status = 'pending_review' 
WHERE status NOT IN ('pending', 'pending_review', 'approved', 'rejected', 'resubmitted', 'changes_requested');

-- Step 4: Verify the fix
SELECT status, COUNT(*) as count
FROM pending_affiliations
GROUP BY status
ORDER BY status;

-- Expected output: All statuses should be one of the allowed values
