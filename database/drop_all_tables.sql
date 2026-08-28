-- =====================================================================
-- IECEP-LSC MEMSYS - SUPABASE DROP ALL PUBLIC TABLES SCRIPT
-- Safely removes all existing tables, views, and foreign key dependencies
-- Run this in Supabase Dashboard -> SQL Editor
-- =====================================================================

DO $$ 
DECLARE 
    r RECORD;
BEGIN
    -- 1. Drop all views in public schema
    FOR r IN (SELECT table_name FROM information_schema.views WHERE table_schema = 'public') LOOP
        EXECUTE 'DROP VIEW IF EXISTS public.' || quote_ident(r.table_name) || ' CASCADE';
    END LOOP;

    -- 2. Drop all tables in public schema (CASCADE resolves all foreign keys)
    FOR r IN (SELECT tablename FROM pg_tables WHERE schemaname = 'public') LOOP
        EXECUTE 'DROP TABLE IF EXISTS public.' || quote_ident(r.tablename) || ' CASCADE';
    END LOOP;

    -- 3. Drop publication if exists
    DROP PUBLICATION IF EXISTS supabase_realtime CASCADE;

    RAISE NOTICE 'ALL PUBLIC TABLES AND VIEWS HAVE BEEN DROPPED CLEANLY!';
END $$;
