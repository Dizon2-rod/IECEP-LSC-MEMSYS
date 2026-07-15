-- IECEP-LSC MEMSYS - Complete Missing Features Migration
-- Run this migration in Supabase SQL Editor

-- ============================================
-- 1. EVENTS MODULE
-- ============================================

-- Events table
CREATE TABLE IF NOT EXISTS events (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  title TEXT NOT NULL,
  description TEXT,
  event_type TEXT CHECK (event_type IN ('seminar','workshop','community','chapter_meeting','other')),
  start_datetime TIMESTAMPTZ NOT NULL,
  end_datetime TIMESTAMPTZ NOT NULL,
  location TEXT,
  is_online BOOLEAN DEFAULT false,
  online_link TEXT,
  max_capacity INT,
  registration_deadline TIMESTAMPTZ,
  fee NUMERIC(10,2) DEFAULT 0,
  requires_payment BOOLEAN DEFAULT false,
  status TEXT DEFAULT 'draft' CHECK (status IN ('draft','published','cancelled','completed')),
  created_by UUID REFERENCES user_profiles(id),
  institution_id UUID REFERENCES institutions(id),
  target_roles TEXT[],
  created_at TIMESTAMPTZ DEFAULT now(),
  updated_at TIMESTAMPTZ DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_events_status ON events(status);
CREATE INDEX IF NOT EXISTS idx_events_start_datetime ON events(start_datetime);
CREATE INDEX IF NOT EXISTS idx_events_institution ON events(institution_id);

-- Event registrations table
CREATE TABLE IF NOT EXISTS event_registrations (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  event_id UUID REFERENCES events(id) ON DELETE CASCADE,
  user_id UUID REFERENCES user_profiles(id),
  status TEXT DEFAULT 'registered' CHECK (status IN ('registered','waitlisted','confirmed','attended','cancelled')),
  payment_status TEXT DEFAULT 'unpaid' CHECK (payment_status IN ('unpaid','paid','waived')),
  registered_at TIMESTAMPTZ DEFAULT now(),
  checked_in_at TIMESTAMPTZ,
  checked_out_at TIMESTAMPTZ,
  qr_token TEXT UNIQUE,
  UNIQUE(event_id, user_id)
);

CREATE INDEX IF NOT EXISTS idx_event_registrations_event ON event_registrations(event_id);
CREATE INDEX IF NOT EXISTS idx_event_registrations_user ON event_registrations(user_id);
CREATE INDEX IF NOT EXISTS idx_event_registrations_qr ON event_registrations(qr_token);

-- Event attachments table
CREATE TABLE IF NOT EXISTS event_attachments (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  event_id UUID REFERENCES events(id) ON DELETE CASCADE,
  file_name TEXT,
  file_path TEXT,
  file_type TEXT,
  uploaded_by UUID,
  created_at TIMESTAMPTZ DEFAULT now()
);

-- ============================================
-- 2. COMPLIANCE MODULE
-- ============================================

-- Compliance scores table
CREATE TABLE IF NOT EXISTS compliance_scores (
  institution_id UUID REFERENCES institutions(id),
  year INT,
  participation_rate NUMERIC(5,2),
  hosted_event_count INT DEFAULT 0,
  overall_score NUMERIC(5,2),
  last_updated TIMESTAMPTZ,
  PRIMARY KEY (institution_id, year)
);

CREATE INDEX IF NOT EXISTS idx_compliance_scores_year ON compliance_scores(year);
CREATE INDEX IF NOT EXISTS idx_compliance_scores_score ON compliance_scores(overall_score);

-- Compliance rules table
CREATE TABLE IF NOT EXISTS compliance_rules (
  id SERIAL PRIMARY KEY,
  rule_key TEXT UNIQUE,
  description TEXT,
  threshold NUMERIC(5,2),
  is_active BOOLEAN DEFAULT true
);

-- Insert default compliance rules
INSERT INTO compliance_rules (rule_key, description, threshold, is_active)
VALUES 
  ('min_participation', 'Minimum participation rate required', 40.00, true),
  ('required_hosted_events', 'Minimum events to host per year', 1.00, true)
ON CONFLICT (rule_key) DO NOTHING;

-- ============================================
-- 3. ANNOUNCEMENTS MODULE
-- ============================================

-- Announcements table
CREATE TABLE IF NOT EXISTS announcements (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  title TEXT NOT NULL,
  body TEXT,
  target_roles TEXT[],
  target_institutions UUID[],
  is_global BOOLEAN DEFAULT false,
  scheduled_at TIMESTAMPTZ,
  expires_at TIMESTAMPTZ,
  created_by UUID REFERENCES user_profiles(id),
  created_at TIMESTAMPTZ DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_announcements_scheduled ON announcements(scheduled_at);
CREATE INDEX IF NOT EXISTS idx_announcements_global ON announcements(is_global);

-- ============================================
-- 4. CERTIFICATES MODULE
-- ============================================

-- Certificates table
CREATE TABLE IF NOT EXISTS certificates (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  member_id UUID REFERENCES members(id),
  event_id UUID REFERENCES events(id),
  issue_date DATE,
  certificate_number TEXT UNIQUE,
  blockchain_hash TEXT,
  file_path TEXT,
  template_type TEXT DEFAULT 'participation',
  created_at TIMESTAMPTZ DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_certificates_member ON certificates(member_id);
CREATE INDEX IF NOT EXISTS idx_certificates_event ON certificates(event_id);
CREATE INDEX IF NOT EXISTS idx_certificates_number ON certificates(certificate_number);
CREATE INDEX IF NOT EXISTS idx_certificates_hash ON certificates(blockchain_hash);

-- ============================================
-- 5. ENHANCED TRANSACTIONS
-- ============================================

-- Add new columns to transactions table if not exists
DO $$ 
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                 WHERE table_name='transactions' AND column_name='transaction_type') THEN
    ALTER TABLE transactions ADD COLUMN transaction_type TEXT DEFAULT 'payment';
  END IF;
  
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                 WHERE table_name='transactions' AND column_name='receipt_number') THEN
    ALTER TABLE transactions ADD COLUMN receipt_number TEXT UNIQUE;
  END IF;
  
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                 WHERE table_name='transactions' AND column_name='blockchain_hash') THEN
    ALTER TABLE transactions ADD COLUMN blockchain_hash TEXT;
  END IF;
  
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                 WHERE table_name='transactions' AND column_name='receipt_path') THEN
    ALTER TABLE transactions ADD COLUMN receipt_path TEXT;
  END IF;
END $$;

-- ============================================
-- 6. FEE BRACKETS
-- ============================================

-- Fee brackets table
CREATE TABLE IF NOT EXISTS fee_brackets (
  id SERIAL PRIMARY KEY,
  bracket_name TEXT,
  min_members INT,
  max_members INT,
  affiliation_fee NUMERIC(10,2),
  per_member_fee NUMERIC(10,2),
  valid_from DATE,
  valid_to DATE
);

-- Insert default fee brackets
INSERT INTO fee_brackets (bracket_name, min_members, max_members, affiliation_fee, per_member_fee, valid_from, valid_to)
VALUES 
  ('Small', 1, 50, 5000.00, 100.00, '2025-01-01', '2025-12-31'),
  ('Medium', 51, 150, 7500.00, 90.00, '2025-01-01', '2025-12-31'),
  ('Large', 151, 999999, 10000.00, 80.00, '2025-01-01', '2025-12-31')
ON CONFLICT DO NOTHING;

-- ============================================
-- 7. AUDIT LOGS
-- ============================================

-- Audit logs table
CREATE TABLE IF NOT EXISTS audit_logs (
  id SERIAL PRIMARY KEY,
  action TEXT,
  table_name TEXT,
  record_id TEXT,
  old_data JSONB,
  new_data JSONB,
  performed_by UUID,
  ip_address TEXT,
  user_agent TEXT,
  created_at TIMESTAMPTZ DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_audit_logs_table ON audit_logs(table_name);
CREATE INDEX IF NOT EXISTS idx_audit_logs_record ON audit_logs(record_id);
CREATE INDEX IF NOT EXISTS idx_audit_logs_user ON audit_logs(performed_by);
CREATE INDEX IF NOT EXISTS idx_audit_logs_created ON audit_logs(created_at);

-- ============================================
-- 8. ENHANCED MEMBERS TABLE
-- ============================================

-- Add membership expiry and renewal columns
DO $$ 
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                 WHERE table_name='members' AND column_name='membership_expiry') THEN
    ALTER TABLE members ADD COLUMN membership_expiry DATE;
  END IF;
  
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                 WHERE table_name='members' AND column_name='last_renewal_date') THEN
    ALTER TABLE members ADD COLUMN last_renewal_date DATE;
  END IF;
END $$;

-- ============================================
-- 9. ENHANCED NOTIFICATIONS
-- ============================================

-- Add new columns to notifications table
DO $$ 
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                 WHERE table_name='notifications' AND column_name='type') THEN
    ALTER TABLE notifications ADD COLUMN type TEXT DEFAULT 'info';
  END IF;
  
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                 WHERE table_name='notifications' AND column_name='action_url') THEN
    ALTER TABLE notifications ADD COLUMN action_url TEXT;
  END IF;
  
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                 WHERE table_name='notifications' AND column_name='institution_id') THEN
    ALTER TABLE notifications ADD COLUMN institution_id UUID REFERENCES institutions(id);
  END IF;
END $$;

-- ============================================
-- 10. ATTENDANCE ENHANCEMENTS
-- ============================================

-- Add institution_id to attendance if not exists
DO $$ 
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                 WHERE table_name='attendance' AND column_name='institution_id') THEN
    ALTER TABLE attendance ADD COLUMN institution_id UUID REFERENCES institutions(id);
  END IF;
  
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns 
                 WHERE table_name='attendance' AND column_name='event_id') THEN
    ALTER TABLE attendance ADD COLUMN event_id UUID REFERENCES events(id);
  END IF;
END $$;

-- ============================================
-- 11. ROW LEVEL SECURITY (RLS) POLICIES
-- ============================================

-- Enable RLS on new tables
ALTER TABLE events ENABLE ROW LEVEL SECURITY;
ALTER TABLE event_registrations ENABLE ROW LEVEL SECURITY;
ALTER TABLE event_attachments ENABLE ROW LEVEL SECURITY;
ALTER TABLE compliance_scores ENABLE ROW LEVEL SECURITY;
ALTER TABLE announcements ENABLE ROW LEVEL SECURITY;
ALTER TABLE certificates ENABLE ROW LEVEL SECURITY;
ALTER TABLE audit_logs ENABLE ROW LEVEL SECURITY;

-- Events policies
CREATE POLICY "Events are viewable by authenticated users" ON events
  FOR SELECT USING (auth.role() = 'authenticated');

CREATE POLICY "Events can be created by admins" ON events
  FOR INSERT WITH CHECK (auth.role() = 'authenticated');

CREATE POLICY "Events can be updated by admins" ON events
  FOR UPDATE USING (auth.role() = 'authenticated');

-- Event registrations policies
CREATE POLICY "Users can view their own registrations" ON event_registrations
  FOR SELECT USING (auth.uid() = user_id OR auth.role() = 'authenticated');

CREATE POLICY "Users can register for events" ON event_registrations
  FOR INSERT WITH CHECK (auth.uid() = user_id);

CREATE POLICY "Users can update their registrations" ON event_registrations
  FOR UPDATE USING (auth.uid() = user_id OR auth.role() = 'authenticated');

-- Certificates policies
CREATE POLICY "Users can view their own certificates" ON certificates
  FOR SELECT USING (true); -- Public verification

-- Announcements policies
CREATE POLICY "Announcements are viewable by authenticated users" ON announcements
  FOR SELECT USING (auth.role() = 'authenticated');

-- Compliance scores policies
CREATE POLICY "Compliance scores are viewable by authenticated users" ON compliance_scores
  FOR SELECT USING (auth.role() = 'authenticated');

-- ============================================
-- 12. FUNCTIONS AND TRIGGERS
-- ============================================

-- Function to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = now();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Trigger for events table
DROP TRIGGER IF EXISTS update_events_updated_at ON events;
CREATE TRIGGER update_events_updated_at
  BEFORE UPDATE ON events
  FOR EACH ROW
  EXECUTE FUNCTION update_updated_at_column();

-- ============================================
-- MIGRATION COMPLETE
-- ============================================

-- Log migration completion
DO $$
BEGIN
  RAISE NOTICE 'IECEP-LSC MEMSYS Complete Features Migration completed successfully at %', now();
END $$;
