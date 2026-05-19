<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/includes/supabase.php';
require_once __DIR__ . '/src/lib/SupabaseClient.php';

$config = require __DIR__ . '/includes/supabase.php';
$supabase = new App\Lib\SupabaseClient($config['url'], $config['service_role_key']);

$response = $supabase->select('user_profiles', ['email' => 'eq.superadmin@iecep-lsc.test']);
var_dump($response);
