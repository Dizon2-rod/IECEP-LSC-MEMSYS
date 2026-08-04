<?php
require_once __DIR__ . '/../bootstrap.php';
// Database Configuration for Creatives Committee
// DISABLED — Railway uses Supabase only; local MySQL/PostgreSQL is not supported.
// Use App\Lib\SupabaseClient or the $supabase global for all database operations.
class Database {
    private static $instance = null;

    private function __construct() {
        throw new \RuntimeException('Local database connections are disabled. Use Supabase via App\Lib\SupabaseClient or the $supabase global.');
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
