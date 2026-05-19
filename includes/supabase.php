<?php
require_once __DIR__ . '/../bootstrap.php';
// Supabase Configuration
// SECURITY: Service role key is loaded from environment variable to prevent exposure
return [
    'url' => getenv('SUPABASE_URL') ?: 'https://kfvlbjvtwtxnpmmswadf.supabase.co',
    'anon_key' => getenv('SUPABASE_ANON_KEY') ?: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImtmdmxianZ0d3R4bnBtbXN3YWRmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzY0MDY0ODEsImV4cCI6MjA5MTk4MjQ4MX0.4o-RyygAaEnM61wfvc24xWGXMe3jVqZLPvh8bXUYxkg',
    'service_role_key' => getenv('SUPABASE_SERVICE_ROLE_KEY') ?: ''
];
