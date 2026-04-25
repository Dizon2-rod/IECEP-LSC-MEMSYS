// IECEP-LSC MEMSYS - Supabase Client for Browser
// This is a JavaScript version of the Supabase client for browser use

import { createBrowserClient } from 'https://cdn.skypack.dev/@supabase/supabase-js';

const supabaseUrl = 'https://kfvlbjvtwtxnpmmswadf.supabase.co';
const supabaseKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImtmdmxianZ0d3R4bnBtbXN3YWRmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzY0MDY0ODEsImV4cCI6MjA5MTk4MjQ4MX0.4o-RyygAaEnM61wfvc24xWGXMe3jVqZLPvh8bXUYxkg';

export const createClient = () =>
    createBrowserClient(
        supabaseUrl,
        supabaseKey,
    );